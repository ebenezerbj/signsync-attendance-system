<?php
include 'db.php';
include 'AttendanceManager.php';

header('Content-Type: application/json');

// Test with bypass mode enabled
$_POST = [
    'employee_id' => 'EMP001',
    'action' => 'clock_in',
    'latitude' => '5.6037',
    'longitude' => '-0.1870',
    'test_mode' => 'true'
];

try {
    $attendanceManager = new AttendanceManager($conn);
    
    $employee_id = $_POST['employee_id'];
    $action = $_POST['action'];
    $latitude = (float)$_POST['latitude'];
    $longitude = (float)$_POST['longitude'];
    
    echo "Testing with BYPASS MODE enabled:\n";
    echo "Employee ID: $employee_id\n";
    echo "Action: $action\n";
    echo "Latitude: $latitude\n";
    echo "Longitude: $longitude\n\n";
    
    $locationData = [
        'latitude' => $latitude,
        'longitude' => $longitude
    ];
    
    $additionalData = [
        'bypass_location_verification' => true,
        'test_mode' => true
    ];
    
    echo "Calling AttendanceManager->clockIn() with bypass...\n";
    
    if ($action === 'clock_in') {
        $result = $attendanceManager->clockIn($employee_id, $locationData, $additionalData);
    } else {
        $result = $attendanceManager->clockOut($employee_id, $locationData, $additionalData);
    }
    
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
