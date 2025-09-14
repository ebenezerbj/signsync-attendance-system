<?php
session_start();
include 'db.php';
include 'enhanced_sms_service.php';
include 'gamification_engine.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$employee_id = $_SESSION['user_id'];

// Initialize services
$smsService = new EnhancedSMSService($conn, 'your-sms-api-key'); // Replace with actual API key
$gamificationEngine = new GamificationEngine($conn, $smsService);

// Fetch employee info
$empStmt = $conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
$empStmt->execute([$employee_id]);
$employee = $empStmt->fetch(PDO::FETCH_ASSOC);

// Fetch enhanced gamification data
$gamStmt = $conn->prepare("SELECT * FROM tbl_gamification WHERE EmployeeID = ?");
$gamStmt->execute([$employee_id]);
$gamify = $gamStmt->fetch(PDO::FETCH_ASSOC);

// Get leaderboard position
$leaderboardPosition = $gamificationEngine->getLeaderboardPosition($employee_id, 'department', 'points');
$streakPosition = $gamificationEngine->getLeaderboardPosition($employee_id, 'department', 'streak');

// Fetch recent achievements
$achievementStmt = $conn->prepare("
    SELECT a.name, a.description, a.icon, a.points_reward, ea.unlocked_at
    FROM tbl_employee_achievements ea
    JOIN tbl_achievements a ON ea.achievement_id = a.id
    WHERE ea.EmployeeID = ?
    ORDER BY ea.unlocked_at DESC
    LIMIT 5
");
$achievementStmt->execute([$employee_id]);
$recentAchievements = $achievementStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active team challenges
$challengeStmt = $conn->prepare("
    SELECT tc.*, tcp.current_score, tcp.progress
    FROM tbl_team_challenges tc
    JOIN tbl_team_challenge_participants tcp ON tc.id = tcp.challenge_id
    WHERE tcp.EmployeeID = ? AND tc.is_active = 1
    AND tc.start_date <= CURDATE() AND tc.end_date >= CURDATE()
");
$challengeStmt->execute([$employee_id]);
$activeChallenges = $challengeStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch attendance records with enhanced data
$attStmt = $conn->prepare("
    SELECT *, 
           CASE 
               WHEN ClockInStatus = 'On Time' THEN 'success'
               WHEN ClockInStatus = 'Late' THEN 'warning'
               ELSE 'danger'
           END as status_class
    FROM tbl_attendance 
    WHERE EmployeeID = ? 
    ORDER BY AttendanceDate DESC, ClockIn DESC 
    LIMIT 30
");
$attStmt->execute([$employee_id]);
$attendance = $attStmt->fetchAll(PDO::FETCH_ASSOC);

// Get weekly wellness data
$wellnessStmt = $conn->prepare("
    SELECT * FROM tbl_wellness_data 
    WHERE EmployeeID = ? 
    AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY date DESC
");
$wellnessStmt->execute([$employee_id]);
$wellnessData = $wellnessStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate level progress
$currentLevel = $gamify['level'] ?? 1;
$currentPoints = $gamify['points'] ?? 0;
$pointsForNextLevel = $currentLevel * 100; // 100 points per level
$levelProgress = min(100, ($currentPoints % 100));

// Get department leaderboard
$deptLeaderboardStmt = $conn->prepare("
    SELECT g.EmployeeID, e.FullName, g.points, g.streak, g.longest_streak,
           ROW_NUMBER() OVER (ORDER BY g.points DESC) as rank
    FROM tbl_gamification g
    JOIN tbl_employees e ON g.EmployeeID = e.EmployeeID
    WHERE e.DepartmentID = (SELECT DepartmentID FROM tbl_employees WHERE EmployeeID = ?)
    ORDER BY g.points DESC
    LIMIT 10
");
$deptLeaderboardStmt->execute([$employee_id]);
$departmentLeaderboard = $deptLeaderboardStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Employee Portal - SignSync</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .achievement-badge {
            animation: pulse 2s infinite;
        }
        
        .streak-fire {
            animation: flicker 1.5s infinite alternate;
        }
        
        @keyframes flicker {
            0% { opacity: 1; }
            100% { opacity: 0.7; }
        }
        
        .progress-bar {
            transition: width 0.5s ease-in-out;
        }
        
        .notification-enter {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50" x-data="{ activeTab: 'dashboard', showNotification: false, notificationMessage: '' }">

    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Welcome back, <?= htmlspecialchars($employee['FullName']) ?>! 👋</h1>
                    <p class="text-gray-600">Employee ID: <?= htmlspecialchars($employee_id) ?> | Level <?= $currentLevel ?> 
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 ml-2">
                            <?= number_format($currentPoints) ?> points
                        </span>
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Current Streak</div>
                        <div class="text-xl font-bold text-orange-500 streak-fire">🔥 <?= $gamify['streak'] ?? 0 ?> days</div>
                    </div>
                    <a href="logout.php" class="bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation Tabs -->
    <nav class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8">
                <button @click="activeTab = 'dashboard'" 
                        :class="activeTab === 'dashboard' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </button>
                <button @click="activeTab = 'achievements'" 
                        :class="activeTab === 'achievements' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-trophy mr-2"></i>Achievements
                </button>
                <button @click="activeTab = 'leaderboard'" 
                        :class="activeTab === 'leaderboard' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-chart-line mr-2"></i>Leaderboard
                </button>
                <button @click="activeTab = 'challenges'" 
                        :class="activeTab === 'challenges' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-users mr-2"></i>Team Challenges
                </button>
                <button @click="activeTab = 'attendance'" 
                        :class="activeTab === 'attendance' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-calendar-check mr-2"></i>Attendance
                </button>
                <button @click="activeTab = 'wellness'" 
                        :class="activeTab === 'wellness' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-heart mr-2"></i>Wellness
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Dashboard Tab -->
        <div x-show="activeTab === 'dashboard'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <!-- Streak Card -->
                <div class="bg-gradient-to-r from-orange-400 to-red-500 rounded-xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100">Current Streak</p>
                            <p class="text-3xl font-bold"><?= $gamify['streak'] ?? 0 ?> days</p>
                            <p class="text-sm text-orange-100">Record: <?= $gamify['longest_streak'] ?? 0 ?> days</p>
                        </div>
                        <div class="text-4xl streak-fire">🔥</div>
                    </div>
                </div>

                <!-- Points Card -->
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100">Total Points</p>
                            <p class="text-3xl font-bold"><?= number_format($currentPoints) ?></p>
                            <p class="text-sm text-blue-100">Level <?= $currentLevel ?></p>
                        </div>
                        <div class="text-4xl">⭐</div>
                    </div>
                </div>

                <!-- Rank Card -->
                <div class="bg-gradient-to-r from-green-500 to-teal-600 rounded-xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100">Department Rank</p>
                            <p class="text-3xl font-bold">#<?= $leaderboardPosition['rank'] ?? '?' ?></p>
                            <p class="text-sm text-green-100">of <?= count($departmentLeaderboard) ?></p>
                        </div>
                        <div class="text-4xl">🏆</div>
                    </div>
                </div>

                <!-- Achievements Card -->
                <div class="bg-gradient-to-r from-purple-500 to-pink-600 rounded-xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100">Achievements</p>
                            <p class="text-3xl font-bold"><?= count($recentAchievements) ?></p>
                            <p class="text-sm text-purple-100">Unlocked</p>
                        </div>
                        <div class="text-4xl">🎖️</div>
                    </div>
                </div>

            </div>

            <!-- Level Progress -->
            <div class="bg-white rounded-xl p-6 mb-8 shadow-sm">
                <h3 class="text-lg font-semibold mb-4">Level Progress</h3>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium">Level <?= $currentLevel ?></span>
                    <span class="text-sm text-gray-500"><?= $currentPoints % 100 ?>/100 XP</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-3 rounded-full progress-bar" 
                         style="width: <?= $levelProgress ?>%"></div>
                </div>
                <p class="text-sm text-gray-600 mt-2"><?= 100 - ($currentPoints % 100) ?> points to next level</p>
            </div>

            <!-- Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold mb-4">Recent Achievements 🏆</h3>
                    <div class="space-y-3">
                        <?php foreach (array_slice($recentAchievements, 0, 3) as $achievement): ?>
                        <div class="flex items-center space-x-3 achievement-badge">
                            <div class="text-2xl"><?= $achievement['icon'] ?></div>
                            <div class="flex-1">
                                <p class="font-medium"><?= htmlspecialchars($achievement['name']) ?></p>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($achievement['description']) ?></p>
                                <p class="text-xs text-blue-600">+<?= $achievement['points_reward'] ?> points</p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recentAchievements)): ?>
                        <p class="text-gray-500 text-center py-4">No achievements yet. Keep attending to unlock your first badge!</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold mb-4">Weekly Stats 📊</h3>
                    <canvas id="weeklyChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Achievements Tab -->
        <div x-show="activeTab === 'achievements'" x-cloak>
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-2xl font-bold mb-6">Your Achievements 🏆</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($recentAchievements as $achievement): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="text-center">
                            <div class="text-4xl mb-2"><?= $achievement['icon'] ?></div>
                            <h3 class="font-semibold text-lg"><?= htmlspecialchars($achievement['name']) ?></h3>
                            <p class="text-gray-600 text-sm mb-3"><?= htmlspecialchars($achievement['description']) ?></p>
                            <div class="flex justify-between items-center text-xs">
                                <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">+<?= $achievement['points_reward'] ?> points</span>
                                <span class="text-gray-500"><?= date('M j, Y', strtotime($achievement['unlocked_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Leaderboard Tab -->
        <div x-show="activeTab === 'leaderboard'" x-cloak>
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-2xl font-bold mb-6">Department Leaderboard 🏆</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Points</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Streak</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Best Streak</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($departmentLeaderboard as $position): ?>
                            <tr class="<?= $position['EmployeeID'] === $employee_id ? 'bg-blue-50' : '' ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-lg">
                                        <?php if ($position['rank'] == 1): ?>🥇
                                        <?php elseif ($position['rank'] == 2): ?>🥈
                                        <?php elseif ($position['rank'] == 3): ?>🥉
                                        <?php else: ?>#<?= $position['rank'] ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium">
                                        <?= htmlspecialchars($position['FullName']) ?>
                                        <?php if ($position['EmployeeID'] === $employee_id): ?>
                                        <span class="text-blue-600 text-sm">(You)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-lg font-semibold text-blue-600">
                                    <?= number_format($position['points']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center">
                                        🔥 <?= $position['streak'] ?> days
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                    <?= $position['longest_streak'] ?> days
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Team Challenges Tab -->
        <div x-show="activeTab === 'challenges'" x-cloak>
            <div class="space-y-6">
                <?php foreach ($activeChallenges as $challenge): ?>
                <?php $progress = json_decode($challenge['progress'], true); ?>
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-semibold"><?= htmlspecialchars($challenge['name']) ?></h3>
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">Active</span>
                    </div>
                    <p class="text-gray-600 mb-4"><?= htmlspecialchars($challenge['description']) ?></p>
                    
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-2">
                            <span>Your Progress</span>
                            <span><?= number_format($challenge['current_score'], 1) ?>/<?= number_format($challenge['target_value'], 1) ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="bg-gradient-to-r from-green-400 to-blue-500 h-3 rounded-full progress-bar" 
                                 style="width: <?= min(100, ($challenge['current_score'] / $challenge['target_value']) * 100) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Ends: <?= date('M j, Y', strtotime($challenge['end_date'])) ?></span>
                        <span><?= round(($challenge['current_score'] / $challenge['target_value']) * 100, 1) ?>% Complete</span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($activeChallenges)): ?>
                <div class="bg-white rounded-xl p-8 shadow-sm text-center">
                    <div class="text-6xl mb-4">🏁</div>
                    <h3 class="text-xl font-semibold mb-2">No Active Challenges</h3>
                    <p class="text-gray-600">Check back soon for new team challenges!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Attendance Tab -->
        <div x-show="activeTab === 'attendance'" x-cloak>
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-2xl font-bold mb-6">Attendance History 📅</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clock In</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clock Out</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Points</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach (array_slice($attendance, 0, 15) as $record): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?= date('M j, Y', strtotime($record['AttendanceDate'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?= $record['ClockIn'] ? date('g:i A', strtotime($record['ClockIn'])) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?= $record['ClockOut'] ? date('g:i A', strtotime($record['ClockOut'])) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php if ($record['status_class'] === 'success'): ?>bg-green-100 text-green-800
                                        <?php elseif ($record['status_class'] === 'warning'): ?>bg-yellow-100 text-yellow-800
                                        <?php else: ?>bg-red-100 text-red-800<?php endif; ?>">
                                        <?= htmlspecialchars($record['ClockInStatus']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                    +<?= $record['ClockInStatus'] === 'On Time' ? '15' : '10' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Wellness Tab -->
        <div x-show="activeTab === 'wellness'" x-cloak>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold mb-4">Today's Pulse Check 💗</h3>
                    <form action="save_pulse.php" method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">How are you feeling today?</label>
                            <div class="flex space-x-2">
                                <button type="button" onclick="selectMood('excellent')" class="mood-btn text-2xl p-2 rounded-lg border hover:bg-gray-50">😄</button>
                                <button type="button" onclick="selectMood('good')" class="mood-btn text-2xl p-2 rounded-lg border hover:bg-gray-50">😊</button>
                                <button type="button" onclick="selectMood('neutral')" class="mood-btn text-2xl p-2 rounded-lg border hover:bg-gray-50">😐</button>
                                <button type="button" onclick="selectMood('bad')" class="mood-btn text-2xl p-2 rounded-lg border hover:bg-gray-50">😟</button>
                                <button type="button" onclick="selectMood('very_bad')" class="mood-btn text-2xl p-2 rounded-lg border hover:bg-gray-50">😢</button>
                            </div>
                            <input type="hidden" name="mood" id="selected-mood" required>
                        </div>
                        <div>
                            <label for="comment" class="block text-sm font-medium text-gray-700">Any comments?</label>
                            <textarea name="comment" id="comment" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Optional feedback..."></textarea>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                            Submit Pulse Check
                        </button>
                    </form>
                </div>

                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h3 class="text-lg font-semibold mb-4">Wellness Trends 📈</h3>
                    <canvas id="wellnessChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

    </main>

    <!-- Notification Toast -->
    <div x-show="showNotification" 
         x-transition:enter="notification-enter"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform translate-x-0"
         x-transition:leave-end="opacity-0 transform translate-x-full"
         class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
        <p x-text="notificationMessage"></p>
    </div>

    <script>
        // Mood selection
        function selectMood(mood) {
            document.getElementById('selected-mood').value = mood;
            
            // Update button styles
            document.querySelectorAll('.mood-btn').forEach(btn => {
                btn.classList.remove('bg-blue-100', 'border-blue-500');
                btn.classList.add('border-gray-300');
            });
            
            event.target.classList.add('bg-blue-100', 'border-blue-500');
            event.target.classList.remove('border-gray-300');
        }

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Weekly attendance chart
            const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
            new Chart(weeklyCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Attendance Score',
                        data: [15, 15, 10, 15, 15, 0, 0], // Sample data
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 20
                        }
                    }
                }
            });

            // Wellness chart
            const wellnessCtx = document.getElementById('wellnessChart').getContext('2d');
            new Chart(wellnessCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Excellent', 'Good', 'Neutral', 'Bad'],
                    datasets: [{
                        data: [30, 40, 20, 10], // Sample data
                        backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });

        // Show notification function
        function showNotification(message) {
            const notification = document.querySelector('[x-data]').__x.$data;
            notification.notificationMessage = message;
            notification.showNotification = true;
            
            setTimeout(() => {
                notification.showNotification = false;
            }, 3000);
        }

        // Check for new achievements periodically
        setInterval(function() {
            fetch('check_new_achievements.php')
                .then(response => response.json())
                .then(data => {
                    if (data.new_achievements && data.new_achievements.length > 0) {
                        data.new_achievements.forEach(achievement => {
                            showNotification(`🎉 Achievement Unlocked: ${achievement.name}!`);
                        });
                    }
                });
        }, 30000); // Check every 30 seconds
    </script>

</body>
</html>
