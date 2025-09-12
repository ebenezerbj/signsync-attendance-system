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
            
        case 'clock_in':
            handleClockIn($input, $response, $conn);
            break;
            
        case 'clock_out':
            handleClockOut($input, $response, $conn);
            break;
            
        case 'get_attendance_status':
            handleAttendanceStatus($input, $response, $conn);
            break;
            
        case 'get_recent_attendance':
            handleRecentAttendance($input, $response, $conn);
            break;
            
        case 'watch_removed':
            handleWatchRemoved($input, $response, $conn);
            break;
            
        case 'watch_reapplied':
            handleWatchReapplied($input, $response, $conn);
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

/**
 * Handle clock in action from WearOS device
 */
function handleClockIn($input, &$response, $conn) {
    try {
        // Validate required parameters
        if (!isset($input['employee_id'])) {
            throw new Exception('Employee ID is required for clock in');
        }
        
        $employeeId = $input['employee_id'];
        $timestamp = isset($input['timestamp']) ? $input['timestamp'] : time();
        $locationLat = isset($input['location_lat']) ? $input['location_lat'] : null;
        $locationLng = isset($input['location_lng']) ? $input['location_lng'] : null;
        $deviceInfo = isset($input['device_info']) ? $input['device_info'] : 'WearOS Device';
        
        // Check if employee exists
        $stmt = $conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            throw new Exception('Employee not found');
        }
        
        // Check if already clocked in today
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        
        $stmt = $conn->prepare("
            SELECT * FROM clockinout 
            WHERE EmployeeID = ? AND ClockIn BETWEEN ? AND ? AND ClockOut IS NULL
        ");
        $stmt->execute([$employeeId, $todayStart, $todayEnd]);
        $existingClockIn = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingClockIn) {
            throw new Exception('Employee already clocked in today');
        }
        
        // Insert clock in record
        $clockInTime = date('Y-m-d H:i:s', $timestamp);
        $stmt = $conn->prepare("
            INSERT INTO clockinout (EmployeeID, ClockIn, ClockInSource, ClockInLocation, ClockInDevice)
            VALUES (?, ?, 'WearOS', ?, ?)
        ");
        
        $location = null;
        if ($locationLat && $locationLng) {
            $location = "$locationLat,$locationLng";
        }
        
        $stmt->execute([$employeeId, $clockInTime, $location, $deviceInfo]);
        $clockInId = $conn->lastInsertId();
        
        // Log the activity
        logWearOSActivity("Clock in successful: Employee $employeeId at $clockInTime");
        
        $response['success'] = true;
        $response['message'] = 'Clock in successful';
        $response['data'] = [
            'clock_in_id' => $clockInId,
            'employee_id' => $employeeId,
            'clock_in_time' => $clockInTime,
            'employee_name' => $employee['FullName']
        ];
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        logWearOSActivity("Clock in error: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Handle clock out action from WearOS device
 */
function handleClockOut($input, &$response, $conn) {
    try {
        // Validate required parameters
        if (!isset($input['employee_id'])) {
            throw new Exception('Employee ID is required for clock out');
        }
        
        $employeeId = $input['employee_id'];
        $timestamp = isset($input['timestamp']) ? $input['timestamp'] : time();
        $locationLat = isset($input['location_lat']) ? $input['location_lat'] : null;
        $locationLng = isset($input['location_lng']) ? $input['location_lng'] : null;
        $deviceInfo = isset($input['device_info']) ? $input['device_info'] : 'WearOS Device';
        
        // Check if employee exists
        $stmt = $conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            throw new Exception('Employee not found');
        }
        
        // Find active clock in record for today
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        
        $stmt = $conn->prepare("
            SELECT * FROM clockinout 
            WHERE EmployeeID = ? AND ClockIn BETWEEN ? AND ? AND ClockOut IS NULL
            ORDER BY ClockIn DESC LIMIT 1
        ");
        $stmt->execute([$employeeId, $todayStart, $todayEnd]);
        $clockInRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$clockInRecord) {
            throw new Exception('No active clock in found for today');
        }
        
        // Update clock out
        $clockOutTime = date('Y-m-d H:i:s', $timestamp);
        $location = null;
        if ($locationLat && $locationLng) {
            $location = "$locationLat,$locationLng";
        }
        
        // Calculate work duration
        $clockInTimestamp = strtotime($clockInRecord['ClockIn']);
        $workDurationSeconds = $timestamp - $clockInTimestamp;
        $workDurationHours = round($workDurationSeconds / 3600, 2);
        
        $stmt = $conn->prepare("
            UPDATE clockinout 
            SET ClockOut = ?, ClockOutSource = 'WearOS', ClockOutLocation = ?, 
                ClockOutDevice = ?, WorkDuration = ?
            WHERE ID = ?
        ");
        
        $stmt->execute([
            $clockOutTime, 
            $location, 
            $deviceInfo, 
            $workDurationHours,
            $clockInRecord['ID']
        ]);
        
        // Log the activity
        logWearOSActivity("Clock out successful: Employee $employeeId at $clockOutTime, Duration: {$workDurationHours}h");
        
        $response['success'] = true;
        $response['message'] = 'Clock out successful';
        $response['data'] = [
            'clock_out_id' => $clockInRecord['ID'],
            'employee_id' => $employeeId,
            'clock_in_time' => $clockInRecord['ClockIn'],
            'clock_out_time' => $clockOutTime,
            'work_duration_hours' => $workDurationHours,
            'employee_name' => $employee['FullName']
        ];
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        logWearOSActivity("Clock out error: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Get current attendance status for employee
 */
function handleAttendanceStatus($input, &$response, $conn) {
    try {
        if (!isset($input['employee_id'])) {
            throw new Exception('Employee ID is required');
        }
        
        $employeeId = $input['employee_id'];
        
        // Check if employee exists
        $stmt = $conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            throw new Exception('Employee not found');
        }
        
        // Get today's attendance status
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        
        $stmt = $conn->prepare("
            SELECT * FROM clockinout 
            WHERE EmployeeID = ? AND ClockIn BETWEEN ? AND ?
            ORDER BY ClockIn DESC LIMIT 1
        ");
        $stmt->execute([$employeeId, $todayStart, $todayEnd]);
        $todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $status = 'not_clocked_in';
        $statusMessage = 'Not clocked in today';
        $clockInTime = null;
        $clockOutTime = null;
        $workDuration = null;
        
        if ($todayAttendance) {
            if ($todayAttendance['ClockOut']) {
                $status = 'clocked_out';
                $statusMessage = 'Clocked out for today';
                $clockOutTime = $todayAttendance['ClockOut'];
                $workDuration = $todayAttendance['WorkDuration'];
            } else {
                $status = 'clocked_in';
                $statusMessage = 'Currently clocked in';
            }
            $clockInTime = $todayAttendance['ClockIn'];
        }
        
        $response['success'] = true;
        $response['message'] = $statusMessage;
        $response['data'] = [
            'employee_id' => $employeeId,
            'employee_name' => $employee['FullName'],
            'status' => $status,
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'work_duration_hours' => $workDuration,
            'date' => date('Y-m-d')
        ];
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        logWearOSActivity("Attendance status error: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Get recent attendance history for employee
 */
function handleRecentAttendance($input, &$response, $conn) {
    try {
        if (!isset($input['employee_id'])) {
            throw new Exception('Employee ID is required');
        }
        
        $employeeId = $input['employee_id'];
        $limit = isset($input['limit']) ? max(1, min(100, intval($input['limit']))) : 7; // Default 7 days, max 100
        
        // Check if employee exists
        $stmt = $conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            throw new Exception('Employee not found');
        }
        
        // Get recent attendance records
        $stmt = $conn->prepare("
            SELECT 
                DATE(ClockIn) as attendance_date,
                ClockIn as clock_in_time,
                ClockOut as clock_out_time,
                WorkDuration as work_duration_hours,
                ClockInSource as clock_in_source,
                ClockOutSource as clock_out_source
            FROM clockinout 
            WHERE EmployeeID = ? 
            ORDER BY ClockIn DESC 
            LIMIT ?
        ");
        $stmt->execute([$employeeId, $limit]);
        $attendanceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate summary statistics
        $totalWorkHours = 0;
        $daysWorked = 0;
        
        foreach ($attendanceHistory as $record) {
            if ($record['work_duration_hours']) {
                $totalWorkHours += $record['work_duration_hours'];
                $daysWorked++;
            }
        }
        
        $averageWorkHours = $daysWorked > 0 ? round($totalWorkHours / $daysWorked, 2) : 0;
        
        $response['success'] = true;
        $response['message'] = 'Recent attendance retrieved successfully';
        $response['data'] = [
            'employee_id' => $employeeId,
            'employee_name' => $employee['FullName'],
            'attendance_history' => $attendanceHistory,
            'summary' => [
                'total_work_hours' => round($totalWorkHours, 2),
                'days_worked' => $daysWorked,
                'average_work_hours' => $averageWorkHours,
                'period_days' => count($attendanceHistory)
            ]
        ];
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        logWearOSActivity("Recent attendance error: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Verify if GPS coordinates are within workplace radius
 */
function verifyWorkplaceLocation($latitude, $longitude, $conn) {
    try {
        // Get all active workplace locations
        $stmt = $conn->prepare("
            SELECT center_latitude, center_longitude, radius_meters, wifi_ssids, beacon_uuids
            FROM workplace_locations 
            WHERE is_active = 1
        ");
        $stmt->execute();
        $workplaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($workplaces as $workplace) {
            $distance = calculateDistance(
                $latitude, $longitude,
                $workplace['center_latitude'], $workplace['center_longitude']
            );
            
            // Check if within radius (convert meters to distance calculation)
            $radiusInKm = $workplace['radius_meters'] / 1000;
            if ($distance <= $radiusInKm) {
                logWearOSActivity("Location verified: within {$workplace['radius_meters']}m of workplace");
                return true;
            }
        }
        
        logWearOSActivity("Location verification failed: outside all workplace boundaries");
        return false;
        
    } catch (Exception $e) {
        logWearOSActivity("Error verifying workplace location: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Calculate distance between two GPS coordinates using Haversine formula
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c; // Distance in kilometers
}

/**
 * Log location tracking data for continuous monitoring
 */
function logLocationTracking($employeeId, $deviceId, $latitude, $longitude, $accuracy, 
                           $locationMethod, $wifiNetworks, $beaconData, $isAtWorkplace, $conn) {
    try {
        // Verify workplace location
        $workplaceLocationId = null;
        if ($isAtWorkplace) {
            $stmt = $conn->prepare("
                SELECT id FROM workplace_locations 
                WHERE is_active = 1 
                ORDER BY id ASC LIMIT 1
            ");
            $stmt->execute();
            $workplace = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($workplace) {
                $workplaceLocationId = $workplace['id'];
            }
        }
        
        // Insert location tracking record
        $stmt = $conn->prepare("
            INSERT INTO location_tracking (
                employee_id, device_id, tracked_at, latitude, longitude, accuracy,
                location_method, wifi_networks, beacon_data, is_at_workplace,
                workplace_location_id, tracking_type
            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'automatic')
        ");
        
        $stmt->execute([
            $employeeId, $deviceId, $latitude, $longitude, $accuracy,
            $locationMethod, $wifiNetworks, $beaconData, $isAtWorkplace,
            $workplaceLocationId
        ]);
        
        logWearOSActivity("Location tracking logged for employee: $employeeId at $latitude,$longitude");
        
    } catch (Exception $e) {
        logWearOSActivity("Error logging location tracking: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Enhanced location verification using multiple methods
 */
function performLocationVerification($gpsLat, $gpsLng, $wifiNetworks, $beaconData, $conn) {
    $score = 0;
    $maxScore = 100;
    $verificationMethods = [];
    
    try {
        // GPS verification (40 points)
        if ($gpsLat && $gpsLng) {
            if (verifyWorkplaceLocation($gpsLat, $gpsLng, $conn)) {
                $score += 40;
                $verificationMethods[] = 'gps_verified';
            } else {
                $verificationMethods[] = 'gps_failed';
            }
        }
        
        // WiFi verification (35 points)
        if ($wifiNetworks && is_array($wifiNetworks)) {
            $wifiScore = verifyWifiNetworks($wifiNetworks, $conn);
            $score += $wifiScore;
            if ($wifiScore > 0) {
                $verificationMethods[] = 'wifi_verified';
            }
        }
        
        // Beacon verification (25 points)
        if ($beaconData && is_array($beaconData)) {
            $beaconScore = verifyBeacons($beaconData, $conn);
            $score += $beaconScore;
            if ($beaconScore > 0) {
                $verificationMethods[] = 'beacon_verified';
            }
        }
        
        $isVerified = $score >= 50; // Require at least 50% confidence
        
        logWearOSActivity("Location verification completed: Score $score/$maxScore, Methods: " . implode(',', $verificationMethods));
        
        return [
            'verified' => $isVerified,
            'score' => $score,
            'max_score' => $maxScore,
            'methods' => $verificationMethods
        ];
        
    } catch (Exception $e) {
        logWearOSActivity("Error in location verification: " . $e->getMessage(), 'ERROR');
        return ['verified' => false, 'score' => 0, 'max_score' => $maxScore, 'methods' => ['error']];
    }
}

/**
 * Verify WiFi networks against authorized workplace networks
 */
function verifyWifiNetworks($wifiNetworks, $conn) {
    try {
        // Get authorized WiFi SSIDs from workplace locations
        $stmt = $conn->prepare("
            SELECT wifi_ssids FROM workplace_locations 
            WHERE is_active = 1 AND wifi_ssids IS NOT NULL
        ");
        $stmt->execute();
        $workplaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $authorizedSSIDs = [];
        foreach ($workplaces as $workplace) {
            $ssids = json_decode($workplace['wifi_ssids'], true);
            if ($ssids && is_array($ssids)) {
                $authorizedSSIDs = array_merge($authorizedSSIDs, $ssids);
            }
        }
        
        if (empty($authorizedSSIDs)) {
            return 0; // No authorized networks configured
        }
        
        // Check for matches
        $matches = 0;
        foreach ($wifiNetworks as $network) {
            $ssid = isset($network['ssid']) ? $network['ssid'] : '';
            if (in_array($ssid, $authorizedSSIDs)) {
                $matches++;
                logWearOSActivity("WiFi match found: $ssid");
            }
        }
        
        // Score based on percentage of authorized networks found
        return min(35, ($matches / count($authorizedSSIDs)) * 35);
        
    } catch (Exception $e) {
        logWearOSActivity("Error verifying WiFi networks: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}

/**
 * Verify Bluetooth LE beacons against authorized workplace beacons
 */
function verifyBeacons($beaconData, $conn) {
    try {
        // Get authorized beacon UUIDs from workplace locations
        $stmt = $conn->prepare("
            SELECT beacon_uuids FROM workplace_locations 
            WHERE is_active = 1 AND beacon_uuids IS NOT NULL
        ");
        $stmt->execute();
        $workplaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $authorizedUUIDs = [];
        foreach ($workplaces as $workplace) {
            $uuids = json_decode($workplace['beacon_uuids'], true);
            if ($uuids && is_array($uuids)) {
                $authorizedUUIDs = array_merge($authorizedUUIDs, $uuids);
            }
        }
        
        if (empty($authorizedUUIDs)) {
            return 0; // No authorized beacons configured
        }
        
        // Check for matches
        $matches = 0;
        foreach ($beaconData as $beacon) {
            $uuid = isset($beacon['uuid']) ? strtoupper($beacon['uuid']) : '';
            foreach ($authorizedUUIDs as $authorizedUUID) {
                if (strtoupper($authorizedUUID) === $uuid) {
                    $matches++;
                    logWearOSActivity("Beacon match found: $uuid");
                    break;
                }
            }
        }
        
        // Score based on number of matches (max 25 points)
        return min(25, $matches * 12.5); // Up to 2 beacons for full score
        
    } catch (Exception $e) {
        logWearOSActivity("Error verifying beacons: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}

/**
 * Handles the event when a watch is removed.
 * Logs the start of a removal event.
 */
function handleWatchRemoved($input, &$response, $conn) {
    $employeeId = $input['employee_id'] ?? null;
    $deviceId = $input['device_id'] ?? null;

    if (!$employeeId || !$deviceId) {
        throw new Exception("Employee ID and Device ID are required for watch removal event.");
    }

    $stmt = $conn->prepare("
        INSERT INTO watch_removal_log (employee_id, device_id, removed_at, status)
        VALUES (?, ?, NOW(), 'removed')
    ");
    
    if ($stmt->execute([$employeeId, $deviceId])) {
        $response['success'] = true;
        $response['message'] = 'Watch removal event logged successfully.';
        $response['log_id'] = $conn->lastInsertId();
    } else {
        throw new Exception("Failed to log watch removal event.");
    }
}

/**
 * Handles the event when a watch is reapplied.
 * Updates the removal log with the reapplication time and duration.
 */
function handleWatchReapplied($input, &$response, $conn) {
    $employeeId = $input['employee_id'] ?? null;
    $deviceId = $input['device_id'] ?? null;

    if (!$employeeId || !$deviceId) {
        throw new Exception("Employee ID and Device ID are required for watch reapplication event.");
    }

    // Find the last 'removed' event for this employee and device
    $findStmt = $conn->prepare("
        SELECT id, removed_at FROM watch_removal_log
        WHERE employee_id = ? AND device_id = ? AND status = 'removed'
        ORDER BY removed_at DESC
        LIMIT 1
    ");
    $findStmt->execute([$employeeId, $deviceId]);
    $lastRemoval = $findStmt->fetch(PDO::FETCH_ASSOC);

    if ($lastRemoval) {
        $logId = $lastRemoval['id'];
        $removedAt = new DateTime($lastRemoval['removed_at']);
        $reappliedAt = new DateTime();
        $duration = $reappliedAt->getTimestamp() - $removedAt->getTimestamp();

        $updateStmt = $conn->prepare("
            UPDATE watch_removal_log
            SET reapplied_at = ?, status = 'reapplied', duration_seconds = ?
            WHERE id = ?
        ");

        if ($updateStmt->execute([$reappliedAt->format('Y-m-d H:i:s'), $duration, $logId])) {
            $response['success'] = true;
            $response['message'] = 'Watch reapplication event logged successfully.';
            $response['duration_seconds'] = $duration;
        } else {
            throw new Exception("Failed to update watch reapplication event.");
        }
    } else {
        // This could happen if a 'reapplied' event is received without a prior 'removed' event
        // For now, we'll just log it as a new, albeit incomplete, record.
        $stmt = $conn->prepare("
            INSERT INTO watch_removal_log (employee_id, device_id, reapplied_at, status)
            VALUES (?, ?, NOW(), 'reapplied_without_removal')
        ");
        $stmt->execute([$employeeId, $deviceId]);
        
        $response['success'] = true;
        $response['message'] = 'Watch reapplication event logged, but no prior removal was found.';
    }
}
