<?php
include 'db.php';
include 'AttendanceManager.php';

header('Content-Type: application/json');

// Simulate the exact POST request that would come from the mobile app
$_POST = [
    'employee_id' => 'EMP001',
    'action' => 'clock_in',
    'latitude' => '5.6037',
    'longitude' => '-0.1870'
];

try {
    $attendanceManager = new AttendanceManager($conn);
    
    // Input validation and collection
    $employee_id = $_POST['employee_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    
    echo "Testing clock in/out with:\n";
    echo "Employee ID: $employee_id\n";
    echo "Action: $action\n";
    echo "Latitude: $latitude\n";
    echo "Longitude: $longitude\n\n";
    
    if (empty($employee_id) || empty($action)) {
        throw new Exception('Employee ID and action are required');
    }

    if (!in_array($action, ['clock_in', 'clock_out'])) {
        throw new Exception('Invalid action. Use clock_in or clock_out');
    }

    if ($latitude === null || $longitude === null) {
        throw new Exception('Location data is required');
    }
    
    // Prepare location and additional data
    $locationData = [
        'latitude' => $latitude,
        'longitude' => $longitude
    ];
    $additionalData = [];
    
    echo "Calling AttendanceManager->clockIn()...\n";
    
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
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
