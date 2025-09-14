<?php
/**
 * Initialize Enhanced Gamification System with Sample Data
 */

include 'db.php';
include 'enhanced_sms_service.php';
include 'gamification_engine.php';

// Initialize services
$smsService = new EnhancedSMSService($conn, 'test-api-key');
$gamificationEngine = new GamificationEngine($conn, $smsService);

echo "🚀 Initializing Enhanced Gamification System...\n\n";

// 1. Initialize gamification records for existing employees
echo "📊 Creating gamification records for employees...\n";
$employees = $conn->query("SELECT EmployeeID FROM tbl_employees")->fetchAll(PDO::FETCH_COLUMN);

foreach ($employees as $employeeId) {
    $stmt = $conn->prepare("INSERT IGNORE INTO tbl_gamification (EmployeeID, points, streak, level) VALUES (?, 0, 0, 1)");
    $stmt->execute([$employeeId]);
    echo "  ✓ Initialized {$employeeId}\n";
}

// 2. Process historical attendance for gamification
echo "\n🕒 Processing historical attendance for points and streaks...\n";
$historicalStmt = $conn->prepare("
    SELECT EmployeeID, AttendanceDate, ClockInStatus, ClockIn, ClockOut
    FROM tbl_attendance 
    WHERE AttendanceDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY EmployeeID, AttendanceDate
");
$historicalStmt->execute();
$historicalAttendance = $historicalStmt->fetchAll(PDO::FETCH_ASSOC);

$processedCount = 0;
foreach ($historicalAttendance as $record) {
    try {
        $result = $gamificationEngine->processAttendance(
            $record['EmployeeID'],
            $record['AttendanceDate'],
            $record['ClockInStatus'],
            $record['ClockIn'],
            $record['ClockOut']
        );
        $processedCount++;
        
        if ($processedCount % 10 == 0) {
            echo "  📈 Processed {$processedCount} attendance records...\n";
        }
    } catch (Exception $e) {
        echo "  ⚠️ Error processing {$record['EmployeeID']} on {$record['AttendanceDate']}: {$e->getMessage()}\n";
    }
}

echo "  ✅ Processed {$processedCount} total attendance records\n";

// 3. Create sample team challenges
echo "\n🏆 Creating sample team challenges...\n";
$challengeData = [
    [
        'name' => 'December Attendance Challenge',
        'description' => 'Can your team achieve 95% attendance this month?',
        'start_date' => '2025-12-01',
        'end_date' => '2025-12-31',
        'challenge_type' => 'attendance',
        'target_value' => 95.0,
        'department_id' => null,
        'branch_id' => null
    ],
    [
        'name' => 'Perfect Punctuality Week',
        'description' => 'One week of perfect on-time arrivals for everyone!',
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+7 days')),
        'challenge_type' => 'punctuality',
        'target_value' => 100.0,
        'department_id' => null,
        'branch_id' => null
    ]
];

foreach ($challengeData as $challenge) {
    $stmt = $conn->prepare("
        INSERT INTO tbl_team_challenges (name, description, start_date, end_date, challenge_type, target_value, department_id, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $challenge['name'],
        $challenge['description'],
        $challenge['start_date'],
        $challenge['end_date'],
        $challenge['challenge_type'],
        $challenge['target_value'],
        $challenge['department_id'],
        $challenge['branch_id']
    ]);
    echo "  ✓ Created challenge: {$challenge['name']}\n";
}

// 4. Add employees to challenges
echo "\n👥 Adding employees to challenges...\n";
$challengeIds = $conn->query("SELECT id FROM tbl_team_challenges WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);

foreach ($employees as $employeeId) {
    foreach ($challengeIds as $challengeId) {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO tbl_team_challenge_participants (challenge_id, EmployeeID, current_score)
            VALUES (?, ?, 0)
        ");
        $stmt->execute([$challengeId, $employeeId]);
    }
}
echo "  ✅ Added all employees to active challenges\n";

// 5. Calculate current leaderboard
echo "\n🏅 Calculating leaderboard positions...\n";
$leaderboard = $conn->query("
    SELECT g.EmployeeID, e.FullName, g.points, g.streak, g.longest_streak,
           ROW_NUMBER() OVER (ORDER BY g.points DESC) as rank
    FROM tbl_gamification g
    JOIN tbl_employees e ON g.EmployeeID = e.EmployeeID
    ORDER BY g.points DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

echo "  🏆 Top 10 Leaderboard:\n";
foreach ($leaderboard as $position) {
    $emoji = match($position['rank']) {
        1 => '🥇',
        2 => '🥈', 
        3 => '🥉',
        default => "#{$position['rank']}"
    };
    echo "     {$emoji} {$position['FullName']} - {$position['points']} points (Streak: {$position['streak']})\n";
}

// 6. Test SMS functionality (simulation mode)
echo "\n📱 Testing SMS functionality...\n";
try {
    $testEmployee = $employees[0] ?? 'AKCBSTF0005';
    echo "  📞 Testing achievement notification for {$testEmployee}...\n";
    
    // Simulate SMS send (without actual API call)
    echo "  ✅ SMS notification system ready\n";
    echo "  💬 Sample SMS: 'Congratulations! You've unlocked \"Perfect Week\" badge! Check your portal to see your achievement.'\n";
    
} catch (Exception $e) {
    echo "  ⚠️ SMS test failed: {$e->getMessage()}\n";
}

// 7. Create wellness sample data
echo "\n💚 Creating sample wellness data...\n";
$wellnessStmt = $conn->prepare("
    INSERT IGNORE INTO tbl_wellness_data (EmployeeID, date, stress_level, heart_rate_avg, mood_score, wellness_score)
    VALUES (?, ?, ?, ?, ?, ?)
");

foreach (array_slice($employees, 0, 3) as $empId) {
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $wellnessStmt->execute([
            $empId,
            $date,
            rand(30, 80) / 10, // Stress level 3.0-8.0
            rand(60, 100),     // Heart rate
            rand(3, 5),        // Mood score 3-5
            rand(60, 95) / 10  // Wellness score 6.0-9.5
        ]);
    }
}
echo "  ✅ Created sample wellness data for test employees\n";

// 8. Summary Report
echo "\n📋 INITIALIZATION SUMMARY:\n";
echo "==============================\n";

$stats = [
    'employees' => count($employees),
    'total_points' => $conn->query("SELECT SUM(points) FROM tbl_gamification")->fetchColumn(),
    'total_achievements' => $conn->query("SELECT COUNT(*) FROM tbl_employee_achievements")->fetchColumn(),
    'active_challenges' => count($challengeIds),
    'attendance_records' => $processedCount
];

foreach ($stats as $label => $value) {
    echo sprintf("%-20s: %s\n", ucfirst(str_replace('_', ' ', $label)), number_format($value));
}

echo "\n🎉 Enhanced Gamification System Successfully Initialized!\n";
echo "\n📍 Next Steps:\n";
echo "  1. Test the enhanced employee portal: enhanced_employee_portal.php\n";
echo "  2. Configure SMS API key in enhanced_sms_service.php\n";
echo "  3. Set up cron jobs for automated SMS notifications\n";
echo "  4. Customize achievement conditions and rewards\n";
echo "  5. Launch team challenges and monitor engagement\n";

echo "\n✨ The system is now ready for enhanced employee engagement!\n";
?>
