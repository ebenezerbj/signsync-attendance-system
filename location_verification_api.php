<?php
include 'db.php';
include 'LocationVerificationManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-ID, X-Employee-ID');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action is required']);
    exit;
}

try {
    $locationManager = new LocationVerificationManager($conn);
    
    switch ($action) {
        case 'get_dashboard_stats':
            handleGetDashboardStats($conn);
            break;
            
        case 'get_boundaries':
            handleGetBoundaries($conn);
            break;
            
        case 'add_boundary':
            handleAddBoundary($conn, $locationManager);
            break;
            
        case 'delete_boundary':
            handleDeleteBoundary($conn);
            break;
            
        case 'get_settings':
            handleGetSettings($conn);
            break;
            
        case 'update_settings':
            handleUpdateSettings($conn, $locationManager);
            break;
            
        case 'get_analytics':
            handleGetAnalytics($conn, $locationManager);
            break;
            
        case 'get_history':
            handleGetHistory($conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Location verification API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function handleGetDashboardStats($conn) {
    try {
        // Total boundaries
        $stmt = $conn->query("SELECT COUNT(*) as total FROM workplace_boundaries WHERE is_active = 1");
        $totalBoundaries = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Average accuracy and score from today's verifications
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT 
                AVG(accuracy_meters) as avg_accuracy,
                AVG(verification_score) as avg_score,
                COUNT(*) as total_verifications
            FROM location_verification_history 
            WHERE DATE(timestamp) = ?
        ");
        $stmt->execute([$today]);
        $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Alert count (low scores or outside workplace)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as alert_count
            FROM location_verification_history 
            WHERE DATE(timestamp) = ? 
            AND (verification_score < 60 OR is_at_workplace = 0)
        ");
        $stmt->execute([$today]);
        $alertCount = $stmt->fetch(PDO::FETCH_ASSOC)['alert_count'];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_boundaries' => $totalBoundaries,
                'avg_accuracy' => $todayStats['avg_accuracy'],
                'avg_score' => $todayStats['avg_score'],
                'alert_count' => $alertCount,
                'total_verifications_today' => $todayStats['total_verifications']
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching dashboard stats: ' . $e->getMessage()]);
    }
}

function handleGetBoundaries($conn) {
    try {
        $stmt = $conn->query("
            SELECT * FROM workplace_boundaries 
            ORDER BY branch_id, boundary_name
        ");
        $boundaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $boundaries
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching boundaries: ' . $e->getMessage()]);
    }
}

function handleAddBoundary($conn, $locationManager) {
    try {
        $boundaryData = [
            'branch_id' => $_POST['branch_id'] ?? '',
            'boundary_name' => $_POST['boundary_name'] ?? '',
            'center_latitude' => floatval($_POST['center_latitude'] ?? 0),
            'center_longitude' => floatval($_POST['center_longitude'] ?? 0),
            'radius_meters' => intval($_POST['radius_meters'] ?? 200),
            'boundary_type' => 'circular',
            'work_hours_start' => $_POST['work_hours_start'] ?? '08:00:00',
            'work_hours_end' => $_POST['work_hours_end'] ?? '17:00:00',
            'timezone' => $_POST['timezone'] ?? 'Asia/Manila'
        ];
        
        // Validate required fields
        if (empty($boundaryData['branch_id']) || empty($boundaryData['boundary_name']) || 
            $boundaryData['center_latitude'] == 0 || $boundaryData['center_longitude'] == 0) {
            throw new Exception('Missing required boundary data');
        }
        
        $result = $locationManager->addWorkplaceBoundary($boundaryData);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Boundary added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add boundary']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding boundary: ' . $e->getMessage()]);
    }
}

function handleDeleteBoundary($conn) {
    try {
        $boundaryId = intval($_POST['boundary_id'] ?? 0);
        
        if ($boundaryId <= 0) {
            throw new Exception('Invalid boundary ID');
        }
        
        $stmt = $conn->prepare("UPDATE workplace_boundaries SET is_active = 0 WHERE id = ?");
        $result = $stmt->execute([$boundaryId]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Boundary deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete boundary']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting boundary: ' . $e->getMessage()]);
    }
}

function handleGetSettings($conn) {
    try {
        $stmt = $conn->query("SELECT config_key, config_value FROM location_verification_config WHERE is_active = 1");
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = json_decode($row['config_value'], true);
            $settings[$row['config_key']] = $value !== null ? $value : $row['config_value'];
        }
        
        // Set defaults if no settings exist
        $defaults = [
            'default_workplace_radius' => 200,
            'min_gps_accuracy' => 50,
            'min_location_score' => 60,
            'distance_alert_threshold' => 300,
            'require_location_for_clockin' => true,
            'auto_detect_branch' => true
        ];
        
        $settings = array_merge($defaults, $settings);
        
        echo json_encode([
            'success' => true,
            'data' => $settings
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching settings: ' . $e->getMessage()]);
    }
}

function handleUpdateSettings($conn, $locationManager) {
    try {
        $settings = [
            'default_workplace_radius' => intval($_POST['default_workplace_radius'] ?? 200),
            'min_gps_accuracy' => intval($_POST['min_gps_accuracy'] ?? 50),
            'min_location_score' => intval($_POST['min_location_score'] ?? 60),
            'distance_alert_threshold' => intval($_POST['distance_alert_threshold'] ?? 300),
            'require_location_for_clockin' => ($_POST['require_location_for_clockin'] ?? 'false') === 'true',
            'auto_detect_branch' => ($_POST['auto_detect_branch'] ?? 'false') === 'true'
        ];
        
        $success = true;
        foreach ($settings as $key => $value) {
            $result = $locationManager->updateConfiguration($key, $value);
            if (!$result) {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update some settings']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating settings: ' . $e->getMessage()]);
    }
}

function handleGetAnalytics($conn, $locationManager) {
    try {
        $days = intval($_POST['days'] ?? 7);
        $analytics = $locationManager->getLocationAnalytics(null, $days);
        
        echo json_encode([
            'success' => true,
            'data' => $analytics
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching analytics: ' . $e->getMessage()]);
    }
}

function handleGetHistory($conn) {
    try {
        $employeeId = trim($_POST['employee_id'] ?? '');
        $verificationType = trim($_POST['verification_type'] ?? '');
        $date = trim($_POST['date'] ?? '');
        
        $sql = "SELECT * FROM location_verification_history WHERE 1=1";
        $params = [];
        
        if (!empty($employeeId)) {
            $sql .= " AND employee_id LIKE ?";
            $params[] = "%$employeeId%";
        }
        
        if (!empty($verificationType)) {
            $sql .= " AND verification_type = ?";
            $params[] = $verificationType;
        }
        
        if (!empty($date)) {
            $sql .= " AND DATE(timestamp) = ?";
            $params[] = $date;
        }
        
        $sql .= " ORDER BY timestamp DESC LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $history
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching history: ' . $e->getMessage()]);
    }
}
?>
