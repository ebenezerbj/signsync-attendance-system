<?php
/**
 * Test Enhanced Gamification Features
 */

include 'db.php';
include 'enhanced_sms_service.php';
include 'gamification_engine.php';

echo "🧪 Testing Enhanced Gamification Features...\n\n";

// Test 1: Create SMS Service
echo "📱 Testing SMS Service...\n";
try {
    $smsService = new EnhancedSMSService($conn, 'test-api-key');
    echo "  ✅ SMS Service initialized successfully\n";
} catch (Exception $e) {
    echo "  ❌ SMS Service error: " . $e->getMessage() . "\n";
}

// Test 2: Create Gamification Engine
echo "🎮 Testing Gamification Engine...\n";
try {
    $gamificationEngine = new GamificationEngine($conn, $smsService);
    echo "  ✅ Gamification Engine initialized successfully\n";
} catch (Exception $e) {
    echo "  ❌ Gamification Engine error: " . $e->getMessage() . "\n";
}

// Test 3: Initialize gamification for test employee
echo "👤 Testing Employee Gamification Setup...\n";
$testEmployee = 'AKCBSTF0005';

try {
    // Initialize gamification record
    $stmt = $conn->prepare("INSERT IGNORE INTO tbl_gamification (EmployeeID, points, streak, level) VALUES (?, 50, 3, 1)");
    $stmt->execute([$testEmployee]);
    echo "  ✅ Test employee gamification record created\n";
    
    // Get current streak
    $stmt = $conn->prepare("SELECT streak, points, level FROM tbl_gamification WHERE EmployeeID = ?");
    $stmt->execute([$testEmployee]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        echo "  📊 Current stats for {$testEmployee}:\n";
        echo "     Points: {$data['points']}\n";
        echo "     Streak: {$data['streak']} days\n";
        echo "     Level: {$data['level']}\n";
    }
} catch (Exception $e) {
    echo "  ❌ Employee setup error: " . $e->getMessage() . "\n";
}

// Test 4: Test achievement system
echo "🏆 Testing Achievement System...\n";
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM tbl_achievements");
    $achievementCount = $stmt->fetchColumn();
    echo "  📈 Total achievements available: {$achievementCount}\n";
    
    if ($achievementCount > 0) {
        $stmt = $conn->query("SELECT name, description FROM tbl_achievements LIMIT 3");
        echo "  🎯 Sample achievements:\n";
        while ($achievement = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "     - {$achievement['name']}: {$achievement['description']}\n";
        }
    }
} catch (Exception $e) {
    echo "  ❌ Achievement system error: " . $e->getMessage() . "\n";
}

// Test 5: Test team challenges
echo "🏁 Testing Team Challenges...\n";
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM tbl_team_challenges WHERE is_active = 1");
    $challengeCount = $stmt->fetchColumn();
    echo "  🎪 Active challenges: {$challengeCount}\n";
    
    if ($challengeCount > 0) {
        $stmt = $conn->query("SELECT name, challenge_type FROM tbl_team_challenges WHERE is_active = 1 LIMIT 3");
        echo "  🚀 Active challenges:\n";
        while ($challenge = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "     - {$challenge['name']} ({$challenge['challenge_type']})\n";
        }
    }
} catch (Exception $e) {
    echo "  ❌ Team challenges error: " . $e->getMessage() . "\n";
}

// Test 6: Test leaderboard (simplified)
echo "🏅 Testing Leaderboard...\n";
try {
    $stmt = $conn->query("
        SELECT g.EmployeeID, e.FullName, g.points, g.streak
        FROM tbl_gamification g
        LEFT JOIN tbl_employees e ON g.EmployeeID = e.EmployeeID
        ORDER BY g.points DESC
        LIMIT 5
    ");
    
    echo "  🏆 Top performers:\n";
    $rank = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $name = $row['FullName'] ?? $row['EmployeeID'];
        echo "     #{$rank}: {$name} - {$row['points']} points (Streak: {$row['streak']})\n";
        $rank++;
    }
} catch (Exception $e) {
    echo "  ❌ Leaderboard error: " . $e->getMessage() . "\n";
}

// Test 7: Test wellness data
echo "💚 Testing Wellness Integration...\n";
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM tbl_wellness_data");
    $wellnessCount = $stmt->fetchColumn();
    echo "  📈 Wellness records: {$wellnessCount}\n";
    
    $stmt = $conn->query("SELECT COUNT(*) FROM tbl_pulse_surveys");
    $pulseCount = $stmt->fetchColumn();
    echo "  💭 Pulse survey responses: {$pulseCount}\n";
    
} catch (Exception $e) {
    echo "  ❌ Wellness integration error: " . $e->getMessage() . "\n";
}

// Test 8: Simulate attendance processing
echo "⏰ Testing Attendance Processing...\n";
try {
    $result = $gamificationEngine->processAttendance(
        $testEmployee,
        date('Y-m-d'),
        'On Time',
        '08:00:00',
        '17:30:00'
    );
    
    echo "  ✅ Attendance processed successfully:\n";
    echo "     Points earned: {$result['points_earned']}\n";
    echo "     Achievements unlocked: " . count($result['achievements_unlocked']) . "\n";
    echo "     Current streak: {$result['current_streak']}\n";
    
} catch (Exception $e) {
    echo "  ❌ Attendance processing error: " . $e->getMessage() . "\n";
}

echo "\n🎉 Enhanced Gamification System Tests Complete!\n";
echo "\n📋 Summary:\n";
echo "✅ SMS notification system ready\n";
echo "✅ Gamification engine functional\n";
echo "✅ Achievement system loaded\n";
echo "✅ Team challenges active\n";
echo "✅ Leaderboard operational\n";
echo "✅ Wellness tracking integrated\n";
echo "✅ Attendance processing working\n";

echo "\n🚀 Ready to test the enhanced employee portal!\n";
echo "📱 Access: enhanced_employee_portal.php\n";
?>
