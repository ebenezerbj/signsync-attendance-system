<?php
session_start();
include 'db.php';

// Set JSON header
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $conn);
            break;
        case 'POST':
            handlePostRequest($action, $conn);
            break;
        case 'PUT':
            handlePutRequest($action, $conn);
            break;
        case 'DELETE':
            handleDeleteRequest($action, $conn);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetRequest($action, $conn) {
    switch ($action) {
        case 'devices':
            getDevices($conn);
            break;
        case 'device':
            getDevice($conn);
            break;
        case 'discover':
            discoverDevices($conn);
            break;
        case 'activity':
            getDeviceActivity($conn);
            break;
        case 'stats':
            getDeviceStats($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePostRequest($action, $conn) {
    switch ($action) {
        case 'register':
            registerDevice($conn);
            break;
        case 'heartbeat':
            deviceHeartbeat($conn);
            break;
        case 'log_activity':
            logDeviceActivity($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePutRequest($action, $conn) {
    switch ($action) {
        case 'update':
            updateDevice($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handleDeleteRequest($action, $conn) {
    switch ($action) {
        case 'device':
            deleteDevice($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function getDevices($conn) {
    $type = $_GET['type'] ?? '';
    $branch = $_GET['branch'] ?? '';
    $active = $_GET['active'] ?? '';
    
    $where = ['1=1'];
    $params = [];
    
    if ($type) {
        $where[] = "DeviceType = ?";
        $params[] = $type;
    }
    if ($branch) {
        $where[] = "BranchID = ?";
        $params[] = $branch;
    }
    if ($active !== '') {
        $where[] = "IsActive = ?";
        $params[] = intval($active);
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "
        SELECT d.*, b.BranchName,
               TIMESTAMPDIFF(MINUTE, d.LastSeenAt, NOW()) as MinutesSinceLastSeen
        FROM tbl_devices d
        LEFT JOIN tbl_branches b ON d.BranchID = b.BranchID
        WHERE $whereClause
        ORDER BY d.CreatedAt DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['devices' => $devices]);
}

function getDevice($conn) {
    $deviceId = $_GET['id'] ?? '';
    if (!$deviceId) {
        http_response_code(400);
        echo json_encode(['error' => 'Device ID required']);
        return;
    }
    
    $sql = "
        SELECT d.*, b.BranchName, e.FullName as CreatedByName
        FROM tbl_devices d
        LEFT JOIN tbl_branches b ON d.BranchID = b.BranchID
        LEFT JOIN tbl_employees e ON d.CreatedBy = e.EmployeeID
        WHERE d.DeviceID = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        http_response_code(404);
        echo json_encode(['error' => 'Device not found']);
        return;
    }
    
    // Get recent activity
    $activitySql = "
        SELECT ActivityType, ActivityData, Timestamp, e.FullName as DetectedByName
        FROM tbl_device_activity da
        LEFT JOIN tbl_employees e ON da.DetectedBy = e.EmployeeID
        WHERE da.DeviceID = ?
        ORDER BY da.Timestamp DESC
        LIMIT 10
    ";
    
    $activityStmt = $conn->prepare($activitySql);
    $activityStmt->execute([$deviceId]);
    $activity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'device' => $device,
        'recent_activity' => $activity
    ]);
}

function registerDevice($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['device_name', 'device_type', 'identifier'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    $sql = "
        INSERT INTO tbl_devices (DeviceName, DeviceType, Identifier, BranchID, Location, Manufacturer, Model, Description, IsActive, CreatedBy)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        $input['device_name'],
        $input['device_type'],
        $input['identifier'],
        $input['branch_id'] ?? null,
        $input['location'] ?? null,
        $input['manufacturer'] ?? null,
        $input['model'] ?? null,
        $input['description'] ?? null,
        $input['is_active'] ?? 1,
        $_SESSION['user_id']
    ]);
    
    if ($result) {
        $deviceId = $conn->lastInsertId();
        echo json_encode([
            'success' => true,
            'device_id' => $deviceId,
            'message' => 'Device registered successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to register device']);
    }
}

function deviceHeartbeat($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['identifier']) || empty($input['device_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Identifier and device_type required']);
        return;
    }
    
    // Update last seen timestamp
    $sql = "
        UPDATE tbl_devices 
        SET LastSeenAt = NOW(), 
            Metadata = ?
        WHERE DeviceType = ? AND Identifier = ?
    ";
    
    $metadata = json_encode($input['metadata'] ?? []);
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$metadata, $input['device_type'], $input['identifier']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Heartbeat recorded']);
    } else {
        // Device not registered, suggest registration
        echo json_encode([
            'success' => false,
            'message' => 'Device not registered',
            'suggest_registration' => true
        ]);
    }
}

function logDeviceActivity($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['device_id', 'activity_type'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }
    
    $sql = "
        INSERT INTO tbl_device_activity (DeviceID, ActivityType, ActivityData, DetectedBy)
        VALUES (?, ?, ?, ?)
    ";
    
    $activityData = json_encode($input['activity_data'] ?? []);
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        $input['device_id'],
        $input['activity_type'],
        $activityData,
        $input['detected_by'] ?? $_SESSION['user_id']
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Activity logged']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to log activity']);
    }
}

function getDeviceActivity($conn) {
    $deviceId = $_GET['device_id'] ?? '';
    $hours = $_GET['hours'] ?? 24;
    
    $where = ['1=1'];
    $params = [];
    
    if ($deviceId) {
        $where[] = "da.DeviceID = ?";
        $params[] = $deviceId;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "
        SELECT da.*, d.DeviceName, d.DeviceType, e.FullName as DetectedByName
        FROM tbl_device_activity da
        JOIN tbl_devices d ON da.DeviceID = d.DeviceID
        LEFT JOIN tbl_employees e ON da.DetectedBy = e.EmployeeID
        WHERE $whereClause AND da.Timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ORDER BY da.Timestamp DESC
        LIMIT 100
    ";
    
    $params[] = $hours;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['activity' => $activity]);
}

function getDeviceStats($conn) {
    $stats = [];
    
    // Total devices by type
    $typeStats = $conn->query("
        SELECT DeviceType, COUNT(*) as count, 
               SUM(IsActive) as active_count
        FROM tbl_devices 
        GROUP BY DeviceType
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent activity
    $recentActivity = $conn->query("
        SELECT COUNT(*) as count 
        FROM tbl_device_activity 
        WHERE Timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ")->fetchColumn();
    
    // Devices by branch
    $branchStats = $conn->query("
        SELECT b.BranchName, COUNT(d.DeviceID) as device_count
        FROM tbl_branches b
        LEFT JOIN tbl_devices d ON b.BranchID = d.BranchID
        GROUP BY b.BranchID, b.BranchName
        ORDER BY device_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recently active devices (seen in last hour)
    $recentlyActive = $conn->query("
        SELECT COUNT(*) as count
        FROM tbl_devices 
        WHERE LastSeenAt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")->fetchColumn();
    
    echo json_encode([
        'device_types' => $typeStats,
        'recent_activity_24h' => $recentActivity,
        'branch_distribution' => $branchStats,
        'recently_active' => $recentlyActive,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function discoverDevices($conn) {
    // This would typically integrate with network scanning tools
    // For demo purposes, return mock discovered devices
    $discoveredDevices = [
        [
            'identifier' => '00:11:22:33:44:55',
            'type' => 'wifi',
            'name' => 'Office WiFi AP',
            'signal_strength' => -45,
            'discovered_at' => date('Y-m-d H:i:s')
        ],
        [
            'identifier' => '550e8400-e29b-41d4-a716-446655440000',
            'type' => 'beacon',
            'name' => 'Entrance Beacon',
            'signal_strength' => -65,
            'discovered_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    echo json_encode([
        'discovered_devices' => $discoveredDevices,
        'scan_timestamp' => date('Y-m-d H:i:s')
    ]);
}

function updateDevice($conn) {
    // Implementation for updating device details
    http_response_code(501);
    echo json_encode(['error' => 'Not implemented yet']);
}

function deleteDevice($conn) {
    // Implementation for deleting devices
    http_response_code(501);
    echo json_encode(['error' => 'Not implemented yet']);
}
?>
