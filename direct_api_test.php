<?php
// Direct API test for clock in/out functionality
include 'db.php';
include 'AttendanceManager.php';

header('Content-Type: application/json');

try {
    $attendanceManager = new AttendanceManager($conn);
    
    // Test data
    $testData = [
        'employee_id' => 'EMP001',
        'location' => [
            'latitude' => 5.6037,
            'longitude' => -0.1870,
            'address' => 'Test Location',
            'accuracy' => 10.0
        ],
        'additional' => [
            'bypass_location_verification' => true
        ]
    ];
    
    echo "<h2>Testing Clock In/Out API</h2>";
    echo "<h3>Test Data:</h3>";
    echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";
    
    // Test clock in
    echo "<h3>Clock In Test:</h3>";
    $clockInResult = $attendanceManager->clockIn(
        $testData['employee_id'],
        $testData['location'],
        $testData['additional']
    );
    
    echo "<pre>" . json_encode($clockInResult, JSON_PRETTY_PRINT) . "</pre>";
    
    if ($clockInResult['success']) {
        echo "<p style='color: green;'>✅ Clock In Successful!</p>";
        
        // Wait a moment then test clock out
        echo "<h3>Clock Out Test:</h3>";
        $clockOutResult = $attendanceManager->clockOut(
            $testData['employee_id'],
            $testData['location'],
            $testData['additional']
        );
        
        echo "<pre>" . json_encode($clockOutResult, JSON_PRETTY_PRINT) . "</pre>";
        
        if ($clockOutResult['success']) {
            echo "<p style='color: green;'>✅ Clock Out Successful!</p>";
        } else {
            echo "<p style='color: red;'>❌ Clock Out Failed: " . $clockOutResult['message'] . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Clock In Failed: " . $clockInResult['message'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
