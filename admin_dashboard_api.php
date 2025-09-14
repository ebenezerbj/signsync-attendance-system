<?php
include 'db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    switch ($action) {
        case 'get_stats':
            handleGetStats($conn);
            break;
            
        case 'get_recent_activity':
            handleGetRecentActivity($conn);
            break;
            
        case 'get_location_alerts':
            handleGetLocationAlerts($conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Admin dashboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function handleGetStats($conn) {
    $today = date('Y-m-d');
    
    try {
        // Total employees
        $stmt = $conn->query("SELECT COUNT(*) as total FROM tbl_employees WHERE IsActive = 1 OR IsActive IS NULL");
        $totalEmployees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Present today (employees who have clocked in)
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT EmployeeID) as present 
            FROM tbl_attendance 
            WHERE AttendanceDate = ? AND ClockIn IS NOT NULL
        ");
        $stmt->execute([$today]);
        $presentToday = $stmt->fetch(PDO::FETCH_ASSOC)['present'];
        
        // Absent today (total employees - present today)
        $absentToday = $totalEmployees - $presentToday;
        
        // Late arrivals today
        $stmt = $conn->prepare("
            SELECT COUNT(*) as late 
            FROM tbl_attendance 
            WHERE AttendanceDate = ? AND (Status = 'Late' OR ClockInStatus = 'Late')
        ");
        $stmt->execute([$today]);
        $lateArrivals = $stmt->fetch(PDO::FETCH_ASSOC)['late'];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_employees' => $totalEmployees,
                'present_today' => $presentToday,
                'absent_today' => $absentToday,
                'late_arrivals' => $lateArrivals
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching stats: ' . $e->getMessage()]);
    }
}

function handleGetRecentActivity($conn) {
    $limit = $_POST['limit'] ?? 10;
    $today = date('Y-m-d');
    
    try {
        // Get recent clock in/out activity from both tables
        $sql = "
            (SELECT 
                'clock_in' as action,
                c.EmployeeID,
                e.FullName as employee_name,
                c.ClockIn as timestamp,
                CASE 
                    WHEN TIME(c.ClockIn) <= '09:15:00' THEN 'On Time'
                    ELSE 'Late'
                END as status,
                'clockinout' as source
            FROM clockinout c
            LEFT JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID
            WHERE DATE(c.ClockIn) = ?
            AND c.ClockIn IS NOT NULL)
            
            UNION ALL
            
            (SELECT 
                'clock_out' as action,
                c.EmployeeID,
                e.FullName as employee_name,
                c.ClockOut as timestamp,
                CASE 
                    WHEN TIME(c.ClockOut) >= '17:00:00' THEN 'On Time'
                    ELSE 'Early Leave'
                END as status,
                'clockinout' as source
            FROM clockinout c
            LEFT JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID
            WHERE DATE(c.ClockOut) = ?
            AND c.ClockOut IS NOT NULL)
            
            ORDER BY timestamp DESC
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$today, $today, $limit]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $activities
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching recent activity: ' . $e->getMessage()]);
    }
}

function handleGetLocationAlerts($conn) {
    $today = date('Y-m-d');
    
    try {
        // Get location alerts for today
        $sql = "
            SELECT 
                c.EmployeeID,
                e.FullName as employee_name,
                c.ClockIn as timestamp,
                c.location_verification_score,
                c.is_at_workplace,
                CASE 
                    WHEN c.location_verification_score < 70 THEN 'Low location accuracy'
                    WHEN c.is_at_workplace = 0 THEN 'Clock in from outside workplace'
                    ELSE 'Location verification failed'
                END as message
            FROM clockinout c
            LEFT JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID
            WHERE DATE(c.ClockIn) = ?
            AND (c.location_verification_score < 70 OR c.is_at_workplace = 0)
            ORDER BY c.ClockIn DESC
            LIMIT 20
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$today]);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $alerts
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching location alerts: ' . $e->getMessage()]);
    }
}
?>
