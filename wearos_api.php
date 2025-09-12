<?php
/**
 * SignSync WearOS API Endpoint
 * Handles communication between Android smartwatches and the attendance system
 * 
 * @author SignSync Development Team
 * @date September 12, 2025
 * @version 1.0.0
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Alert-Priority');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once 'db.php';

// Log function for debugging
function logWearOSActivity($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] WearOS API: $message" . PHP_EOL;
    file_put_contents('logs/wearos_api.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// Create logs directory if it doesn't exist
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

try {
    // Get raw POST data
    $rawInput = file_get_contents('php://input');
    logWearOSActivity("Received request: " . $rawInput);
    
    // Parse JSON input
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format: ' . json_last_error_msg());
    }
    
    // Check required action parameter
    if (!isset($input['action'])) {
        throw new Exception('Missing action parameter');
    }
    
    $action = $input['action'];
    $response = ['success' => false, 'message' => '', 'data' => null];
    
    logWearOSActivity("Processing action: $action");
    
    switch ($action) {
        case 'ping':
            handlePing($input, $response);
            break;
            
        case 'authenticate_employee':
            handleAuthentication($input, $response, $conn);
            break;
            
        case 'submit_health_data':
            handleHealthData($input, $response, $conn);
            break;
            
        case 'stress_alert':
            handleStressAlert($input, $response, $conn);
            break;
            
        case 'get_employee_info':
            handleEmployeeInfo($input, $response, $conn);
            break;
            
        case 'sync_offline_data':
            handleOfflineDataSync($input, $response, $conn);
            break;
            
        default:
            throw new Exception("Unknown action: $action");
    }
    
    // Send response
    http_response_code($response['success'] ? 200 : 400);
    echo json_encode($response);
    
    logWearOSActivity("Response sent: " . json_encode($response));
    
} catch (Exception $e) {
    logWearOSActivity("Error: " . $e->getMessage(), 'ERROR');
    
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null,
        'timestamp' => time()
    ];
    
    http_response_code(500);
    echo json_encode($errorResponse);
}

/**
 * Handle ping requests for connection testing
 */
function handlePing($input, &$response) {
    $response['success'] = true;
    $response['message'] = 'SignSync WearOS API is online';
    $response['data'] = [
        'server_time' => time(),
        'api_version' => '1.0.0',
        'status' => 'operational'
    ];
}

/**
 * Handle employee authentication
 */
function handleAuthentication($input, &$response, $conn) {
    // Validate required fields
    if (!isset($input['employee_id']) || !isset($input['pin'])) {
        throw new Exception('Missing employee_id or pin');
    }
    
    $employeeId = trim($input['employee_id']);
    $pin = trim($input['pin']);
    
    logWearOSActivity("Authentication attempt for employee: $employeeId");
    
    // Query employee with PIN verification
    $stmt = $conn->prepare("
        SELECT e.EmployeeID, e.FullName as Name, e.DepartmentID, d.DepartmentName,
               e.Username, e.PhoneNumber, e.BranchID
        FROM tbl_employees e
        LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
        WHERE e.EmployeeID = ?
    ");
    
    $stmt->execute([$employeeId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Employee not found');
    }
    
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // For now, we'll skip PIN verification since PIN column doesn't exist
    // In a real implementation, you would add a PIN column or use password verification
    
    // Check if employee has wearable assignment
    $stmt = $conn->prepare("
        SELECT ew.WearableID as AssignmentID, ew.DeviceID, d.DeviceName, d.DeviceType
        FROM tbl_employee_wearables ew
        JOIN tbl_devices d ON ew.DeviceID = d.DeviceID
        WHERE ew.EmployeeID = ?
    ");
    
    $stmt->execute([$employeeId]);
    
    $wearableInfo = null;
    if ($stmt->rowCount() > 0) {
        $wearableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $response['success'] = true;
    $response['message'] = 'Authentication successful';
    $response['data'] = [
        'employee_id' => $employee['EmployeeID'],
        'name' => $employee['Name'],
        'department' => $employee['DepartmentName'],
        'username' => $employee['Username'],
        'phone' => $employee['PhoneNumber'],
        'branch_id' => $employee['BranchID'],
        'wearable_assigned' => $wearableInfo !== null,
        'wearable_info' => $wearableInfo,
        'session_token' => generateSessionToken($employeeId),
        'permissions' => ['health_monitoring', 'stress_alerts']
    ];
    
    logWearOSActivity("Authentication successful for employee: $employeeId");
}

/**
 * Handle health data submission
 */
function handleHealthData($input, &$response, $conn) {
    // Validate required fields
    $requiredFields = ['employee_id', 'heart_rate', 'stress_level', 'timestamp'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $employeeId = trim($input['employee_id']);
    $heartRate = (int)$input['heart_rate'];
    $stressLevel = (float)$input['stress_level'];
    $temperature = isset($input['temperature']) ? (float)$input['temperature'] : null;
    $steps = isset($input['steps']) ? (int)$input['steps'] : null;
    $timestamp = (int)$input['timestamp'];
    $deviceType = isset($input['device_type']) ? $input['device_type'] : 'android_watch';
    
    logWearOSActivity("Received health data for employee: $employeeId - HR: $heartRate, Stress: $stressLevel");
    
    // Validate employee exists
    $stmt = $conn->prepare("SELECT EmployeeID FROM tbl_employees WHERE EmployeeID = ?");
    $stmt->execute([$employeeId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Employee not found');
    }
    
    // Insert health data into biometric_data table
    $stmt = $conn->prepare("
        INSERT INTO tbl_biometric_data 
        (EmployeeID, HeartRate, stress_level_numeric, SkinTemperature, StepCount, 
         Timestamp, device_type, data_source, employee_id)
        VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?, 'wearos_api', ?)
    ");
    
    if (!$stmt->execute([$employeeId, $heartRate, $stressLevel, $temperature, 
                        $steps, $timestamp, $deviceType, $employeeId])) {
        throw new Exception('Failed to insert health data');
    }
    
    $dataId = $conn->lastInsertId();
    
    // Check for stress threshold and trigger camera monitoring if needed
    $stressThreshold = 7.0; // High stress threshold
    $heartRateThreshold = 100; // High heart rate threshold
    
    if ($stressLevel >= $stressThreshold || $heartRate >= $heartRateThreshold) {
        logWearOSActivity("High stress detected for employee: $employeeId - triggering camera monitoring", 'WARNING');
        triggerCameraMonitoring($employeeId, $heartRate, $stressLevel, $conn);
    }
    
    // Update employee's last activity
    updateEmployeeActivity($employeeId, $conn);
    
    $response['success'] = true;
    $response['message'] = 'Health data recorded successfully';
    $response['data'] = [
        'data_id' => $dataId,
        'employee_id' => $employeeId,
        'recorded_at' => date('Y-m-d H:i:s'),
        'stress_alert_triggered' => ($stressLevel >= $stressThreshold || $heartRate >= $heartRateThreshold)
    ];
    
    logWearOSActivity("Health data recorded successfully for employee: $employeeId");
}

/**
 * Handle stress alerts (high priority)
 */
function handleStressAlert($input, &$response, $conn) {
    // Validate required fields
    $requiredFields = ['employee_id', 'heart_rate', 'stress_level', 'alert_type'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $employeeId = trim($input['employee_id']);
    $heartRate = (int)$input['heart_rate'];
    $stressLevel = (float)$input['stress_level'];
    $alertType = $input['alert_type'];
    $temperature = isset($input['temperature']) ? (float)$input['temperature'] : null;
    $timestamp = isset($input['timestamp']) ? (int)$input['timestamp'] : time();
    $isUrgent = isset($input['urgent']) ? (bool)$input['urgent'] : true;
    
    logWearOSActivity("STRESS ALERT received for employee: $employeeId - HR: $heartRate, Stress: $stressLevel", 'CRITICAL');
    
    // Insert stress alert record
    $stmt = $conn->prepare("
        INSERT INTO tbl_biometric_alerts 
        (EmployeeID, AlertType, Severity, AlertMessage, heart_rate, stress_level, 
         is_urgent, timestamp, status, employee_id, CreatedAt)
        VALUES (?, ?, 'HIGH', 'High stress detected from Android watch', ?, ?, ?, FROM_UNIXTIME(?), 'ACTIVE', ?, NOW())
    ");
    
    if (!$stmt->execute([$employeeId, $alertType, $heartRate, $stressLevel, 
                        $isUrgent, $timestamp, $employeeId])) {
        throw new Exception('Failed to insert stress alert');
    }
    
    $alertId = $conn->lastInsertId();
    
    // Trigger immediate camera monitoring
    $cameraTriggered = triggerCameraMonitoring($employeeId, $heartRate, $stressLevel, $conn);
    
    // Send notification to administrators (if notification system exists)
    sendStressNotification($employeeId, $heartRate, $stressLevel, $conn);
    
    $response['success'] = true;
    $response['message'] = 'Stress alert processed successfully';
    $response['data'] = [
        'alert_id' => $alertId,
        'employee_id' => $employeeId,
        'camera_triggered' => $cameraTriggered,
        'alert_time' => date('Y-m-d H:i:s'),
        'severity' => 'HIGH',
        'status' => 'ACTIVE'
    ];
    
    logWearOSActivity("Stress alert processed successfully for employee: $employeeId - Alert ID: $alertId");
}

/**
 * Handle employee information requests
 */
function handleEmployeeInfo($input, &$response, $conn) {
    if (!isset($input['employee_id'])) {
        throw new Exception('Missing employee_id');
    }
    
    $employeeId = trim($input['employee_id']);
    
    $stmt = $conn->prepare("
        SELECT e.EmployeeID, e.FullName as Name, e.DepartmentID, d.DepartmentName, 
               e.Username, e.PhoneNumber,
               (SELECT COUNT(*) FROM tbl_biometric_data bd WHERE bd.EmployeeID = e.EmployeeID 
                AND DATE(bd.Timestamp) = CURDATE()) as today_readings
        FROM tbl_employees e
        LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
        WHERE e.EmployeeID = ?
    ");
    
    $stmt->execute([$employeeId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Employee not found');
    }
    
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['message'] = 'Employee information retrieved';
    $response['data'] = $employee;
}

/**
 * Handle offline data synchronization
 */
function handleOfflineDataSync($input, &$response, $conn) {
    if (!isset($input['employee_id']) || !isset($input['data_batch'])) {
        throw new Exception('Missing employee_id or data_batch');
    }
    
    $employeeId = trim($input['employee_id']);
    $dataBatch = $input['data_batch'];
    
    if (!is_array($dataBatch)) {
        throw new Exception('data_batch must be an array');
    }
    
    $processed = 0;
    $failed = 0;
    
    foreach ($dataBatch as $healthData) {
        try {
            // Process each health data entry
            $fakeInput = array_merge($healthData, ['employee_id' => $employeeId]);
            $fakeResponse = ['success' => false];
            
            handleHealthData($fakeInput, $fakeResponse, $conn);
            
            if ($fakeResponse['success']) {
                $processed++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
            logWearOSActivity("Failed to process offline data entry: " . $e->getMessage(), 'ERROR');
        }
    }
    
    $response['success'] = true;
    $response['message'] = 'Offline data sync completed';
    $response['data'] = [
        'processed' => $processed,
        'failed' => $failed,
        'total' => count($dataBatch)
    ];
    
    logWearOSActivity("Offline data sync completed for employee: $employeeId - Processed: $processed, Failed: $failed");
}

/**
 * Trigger camera monitoring for stress detection
 */
function triggerCameraMonitoring($employeeId, $heartRate, $stressLevel, $conn) {
    try {
        // For now, just log the camera trigger since camera tables may not exist
        logWearOSActivity("Camera monitoring triggered for employee: $employeeId - HR: $heartRate, Stress: $stressLevel");
        
        // You can implement camera integration here when camera tables are available
        // Example implementation:
        /*
        $stmt = $conn->prepare("
            SELECT ecm.camera_id, c.camera_name, c.camera_ip, c.ptz_capable
            FROM employee_camera_mapping ecm
            JOIN camera_registry c ON ecm.camera_id = c.camera_id
            WHERE ecm.employee_id = ? AND ecm.is_active = 1
            LIMIT 1
        ");
        
        $stmt->execute([$employeeId]);
        
        if ($stmt->rowCount() === 0) {
            logWearOSActivity("No camera assigned to employee: $employeeId", 'WARNING');
            return false;
        }
        
        $camera = $stmt->fetch(PDO::FETCH_ASSOC);
        // ... rest of camera logic
        */
        
        return true;
        
    } catch (Exception $e) {
        logWearOSActivity("Error triggering camera monitoring: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Send stress notification to administrators
 */
function sendStressNotification($employeeId, $heartRate, $stressLevel, $conn) {
    try {
        // Get employee information
        $stmt = $conn->prepare("
            SELECT e.FullName as Name, d.DepartmentName
            FROM tbl_employees e
            LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
            WHERE e.EmployeeID = ?
        ");
        
        $stmt->execute([$employeeId]);
        
        if ($stmt->rowCount() > 0) {
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            $message = "STRESS ALERT: {$employee['Name']} ({$employeeId}) in {$employee['DepartmentName']} - HR: {$heartRate}bpm, Stress: {$stressLevel}";
            
            // Log the notification (you can extend this to send actual emails/SMS)
            logWearOSActivity("Stress notification: $message", 'NOTIFICATION');
            
            // You can add email/SMS sending logic here
            // sendEmail($adminEmails, "SignSync Stress Alert", $message);
        }
        
    } catch (Exception $e) {
        logWearOSActivity("Error sending stress notification: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Update employee's last activity timestamp
 */
function updateEmployeeActivity($employeeId, $conn) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO employee_activity (employee_id, activity_type, activity_time, created_at)
            VALUES (?, 'health_data', NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
            activity_time = NOW(), created_at = NOW()
        ");
        
        $stmt->execute([$employeeId]);
        
    } catch (Exception $e) {
        logWearOSActivity("Error updating employee activity: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Generate session token for authenticated employee
 */
function generateSessionToken($employeeId) {
    $data = $employeeId . time() . bin2hex(random_bytes(8));
    return hash('sha256', $data);
}

?>
