<?php
/**
 * CCTV-IoT Stress Monitoring Integration API
 * Automatically triggers camera footage when IoT wearables detect high stress levels
 */

session_start();
include 'db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-ID, X-Employee-ID');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Function to get nearby cameras based on employee location
function getNearByCameras($conn, $employeeID) {
    $query = "
        SELECT 
            c.DeviceID, c.DeviceName, c.Location, c.Identifier as CameraIP,
            c.Manufacturer, c.Model, c.Metadata,
            e.FullName, b.BranchName, dept.DepartmentName,
            CASE 
                WHEN dept.DepartmentName LIKE '%IT%' AND c.Location LIKE '%Office%' THEN 10
                WHEN dept.DepartmentName LIKE '%Credit%' AND c.Location LIKE '%Office%' THEN 10
                WHEN c.Location LIKE '%Entrance%' THEN 8
                WHEN c.Location LIKE '%Main%' THEN 7
                ELSE 5
            END as proximity_score
        FROM tbl_devices c
        CROSS JOIN tbl_employees e
        LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
        LEFT JOIN tbl_departments dept ON e.DepartmentID = dept.DepartmentID
        WHERE c.DeviceType = 'camera' 
        AND c.IsActive = 1 
        AND e.EmployeeID = ?
        ORDER BY proximity_score DESC, c.DeviceName
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$employeeID]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to create camera viewing session
function createCameraSession($conn, $employeeID, $cameraID, $alertID) {
    $sessionToken = bin2hex(random_bytes(16));
    
    $stmt = $conn->prepare("
        INSERT INTO tbl_camera_sessions (
            EmployeeID, CameraID, AlertID, SessionToken, 
            StartTime, ExpiresAt, IsActive
        ) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE), 1)
    ");
    
    $stmt->execute([$employeeID, $cameraID, $alertID, $sessionToken]);
    return $sessionToken;
}

// Handle different endpoints
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'trigger_camera_alert':
        // Called when IoT device detects high stress
        $employeeID = $_POST['employee_id'] ?? '';
        $stressLevel = $_POST['stress_level'] ?? '';
        $alertID = $_POST['alert_id'] ?? '';
        
        if (!$employeeID || !in_array($stressLevel, ['high', 'critical'])) {
            echo json_encode(['error' => 'Invalid parameters']);
            exit;
        }
        
        // Get nearby cameras
        $cameras = getNearByCameras($conn, $employeeID);
        
        if (empty($cameras)) {
            echo json_encode(['error' => 'No cameras available for this location']);
            exit;
        }
        
        // Create camera sessions for monitoring
        $cameraSessions = [];
        foreach ($cameras as $camera) {
            $sessionToken = createCameraSession($conn, $employeeID, $camera['DeviceID'], $alertID);
            $cameraSessions[] = [
                'camera' => $camera,
                'session_token' => $sessionToken,
                'stream_url' => generateStreamURL($camera),
                'proximity_score' => $camera['proximity_score']
            ];
        }
        
        // Log the camera trigger event
        $stmt = $conn->prepare("
            INSERT INTO tbl_camera_triggers (
                EmployeeID, AlertID, TriggerTime, StressLevel, 
                CamerasActivated, Status
            ) VALUES (?, ?, NOW(), ?, ?, 'active')
        ");
        $stmt->execute([$employeeID, $alertID, $stressLevel, count($cameraSessions)]);
        
        echo json_encode([
            'success' => true,
            'employee' => $cameras[0]['FullName'],
            'stress_level' => $stressLevel,
            'cameras_activated' => count($cameraSessions),
            'camera_sessions' => $cameraSessions
        ]);
        break;
        
    case 'get_camera_feed':
        // Get live camera feed for stress monitoring
        $sessionToken = $_GET['session_token'] ?? '';
        
        if (!$sessionToken) {
            echo json_encode(['error' => 'Session token required']);
            exit;
        }
        
        // Validate session
        $stmt = $conn->prepare("
            SELECT cs.*, c.DeviceName, c.Identifier, c.Location, c.Metadata,
                   e.FullName, ba.Severity
            FROM tbl_camera_sessions cs
            JOIN tbl_devices c ON cs.CameraID = c.DeviceID
            JOIN tbl_employees e ON cs.EmployeeID = e.EmployeeID
            LEFT JOIN tbl_biometric_alerts ba ON cs.AlertID = ba.AlertID
            WHERE cs.SessionToken = ? AND cs.IsActive = 1 AND cs.ExpiresAt > NOW()
        ");
        $stmt->execute([$sessionToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            echo json_encode(['error' => 'Invalid or expired session']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'employee' => $session['FullName'],
            'camera' => $session['DeviceName'],
            'location' => $session['Location'],
            'stress_level' => $session['Severity'] ?? 'unknown',
            'stream_url' => generateStreamURL($session),
            'session_expires' => $session['ExpiresAt']
        ]);
        break;
        
    case 'get_active_alerts':
        // Get current stress alerts with camera options
        $activeAlerts = $conn->query("
            SELECT ba.*, e.FullName, e.EmployeeID,
                   ct.CamerasActivated, ct.Status as CameraStatus
            FROM tbl_biometric_alerts ba
            JOIN tbl_employees e ON ba.EmployeeID = e.EmployeeID
            LEFT JOIN tbl_camera_triggers ct ON ba.AlertID = ct.AlertID
            WHERE ba.AlertType = 'stress' 
            AND ba.Severity IN ('high', 'critical')
            AND ba.IsAcknowledged = 0
            ORDER BY ba.CreatedAt DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($activeAlerts as &$alert) {
            $alert['available_cameras'] = getNearByCameras($conn, $alert['EmployeeID']);
        }
        
        echo json_encode([
            'success' => true,
            'active_alerts' => $activeAlerts
        ]);
        break;
        
    case 'manual_camera_trigger':
        // Manually trigger camera monitoring for an employee
        $employeeID = $_POST['employee_id'] ?? '';
        $reason = $_POST['reason'] ?? 'manual_check';
        
        if (!$employeeID) {
            echo json_encode(['error' => 'Employee ID required']);
            exit;
        }
        
        $cameras = getNearByCameras($conn, $employeeID);
        
        // Create manual monitoring session
        $stmt = $conn->prepare("
            INSERT INTO tbl_biometric_alerts (
                EmployeeID, AlertType, Severity, Message, 
                CreatedAt, IsAcknowledged
            ) VALUES (?, 'manual_check', 'moderate', ?, NOW(), 0)
        ");
        $stmt->execute([$employeeID, 'Manual camera check: ' . $reason]);
        $alertID = $conn->lastInsertId();
        
        $cameraSessions = [];
        foreach ($cameras as $camera) {
            $sessionToken = createCameraSession($conn, $employeeID, $camera['DeviceID'], $alertID);
            $cameraSessions[] = [
                'camera' => $camera,
                'session_token' => $sessionToken,
                'stream_url' => generateStreamURL($camera)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Manual camera monitoring activated',
            'camera_sessions' => $cameraSessions
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}

// Helper function to generate camera stream URL
function generateStreamURL($camera) {
    $metadata = json_decode($camera['Metadata'] ?? '{}', true);
    $username = $metadata['username'] ?? 'admin';
    $password = $metadata['password'] ?? 'admin123';
    $port = $metadata['port'] ?? '554';
    
    // Generate RTSP stream URL (adjust based on your camera model)
    $baseIP = $camera['Identifier'] ?? $camera['CameraIP'];
    
    // Common RTSP URL patterns for different camera brands
    if (strpos($camera['Manufacturer'], 'Hikvision') !== false) {
        return "rtsp://{$username}:{$password}@{$baseIP}:{$port}/Streaming/Channels/101";
    } elseif (strpos($camera['Manufacturer'], 'Dahua') !== false) {
        return "rtsp://{$username}:{$password}@{$baseIP}:{$port}/cam/realmonitor?channel=1&subtype=0";
    } elseif (strpos($camera['Manufacturer'], 'Axis') !== false) {
        return "rtsp://{$username}:{$password}@{$baseIP}:{$port}/axis-media/media.amp";
    } else {
        // Generic RTSP URL
        return "rtsp://{$username}:{$password}@{$baseIP}:{$port}/live";
    }
}
?>
