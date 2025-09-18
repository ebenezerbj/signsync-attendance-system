<?php
include 'db.php';
include 'AttendanceManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Create AttendanceManager with relaxed settings for testing
    $attendanceManager = new AttendanceManager($conn);
    
    // Input validation and collection
    $employee_id = $_POST['employee_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $test_mode = $_POST['test_mode'] ?? 'false';
    
    if (empty($employee_id) || empty($action)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Employee ID and action are required']);
        exit;
    }

    if (!in_array($action, ['clock_in', 'clock_out'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action. Use clock_in or clock_out']);
        exit;
    }

    if ($latitude === null || $longitude === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Location data is required']);
        exit;
    }
    
    // Prepare location and additional data
    $locationData = [
        'latitude' => $latitude,
        'longitude' => $longitude
    ];
    
    // Add test mode flag to bypass location verification if requested
    $additionalData = [];
    if ($test_mode === 'true') {
        $additionalData['bypass_location_verification'] = true;
        $additionalData['test_mode'] = true;
    }
    
    if ($action === 'clock_in') {
        $result = $attendanceManager->clockIn($employee_id, $locationData, $additionalData);
    } else {
        $result = $attendanceManager->clockOut($employee_id, $locationData, $additionalData);
    }
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }

} catch (PDOException $e) {
    error_log("Test clockinout API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Test clockinout API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
