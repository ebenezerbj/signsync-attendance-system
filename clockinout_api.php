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

$employee_id = $_POST['employee_id'] ?? '';
$action = $_POST['action'] ?? '';
$latitude = $_POST['latitude'] ?? 0;
$longitude = $_POST['longitude'] ?? 0;
$branch_id = $_POST['branch_id'] ?? 1;

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

try {
    $current_time = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    
    if ($action === 'clock_in') {
        // Check if already clocked in today
        $stmt = $conn->prepare("
            SELECT ID FROM clockinout 
            WHERE EmployeeID = ? AND DATE(ClockIn) = ? AND ClockOut IS NULL
        ");
        $stmt->execute([$employee_id, $today]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Already clocked in today']);
            exit;
        }
        
        // Insert clock in record
        $stmt = $conn->prepare("
            INSERT INTO clockinout (EmployeeID, ClockIn, ClockInSource, ClockInLocation, ClockInDevice, gps_latitude, gps_longitude) 
            VALUES (?, ?, 'Phone App', ?, 'Android Phone', ?, ?)
        ");
        $location_string = $latitude . ',' . $longitude;
        $stmt->execute([$employee_id, $current_time, $location_string, $latitude, $longitude]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully clocked in',
            'data' => [
                'employee_id' => $employee_id,
                'clock_in_time' => $current_time,
                'status' => 'Present',
                'action' => 'clock_in'
            ]
        ]);
        
    } else if ($action === 'clock_out') {
        // Find today's clock in record
        $stmt = $conn->prepare("
            SELECT ID FROM clockinout 
            WHERE EmployeeID = ? AND DATE(ClockIn) = ? AND ClockOut IS NULL
            ORDER BY ClockIn DESC LIMIT 1
        ");
        $stmt->execute([$employee_id, $today]);
        $record = $stmt->fetch();
        
        if (!$record) {
            echo json_encode(['success' => false, 'message' => 'No clock in record found for today']);
            exit;
        }
        
        // Update with clock out time
        $location_string = $latitude . ',' . $longitude;
        $stmt = $conn->prepare("
            UPDATE clockinout 
            SET ClockOut = ?, ClockOutSource = 'Phone App', ClockOutLocation = ?, ClockOutDevice = 'Android Phone'
            WHERE ID = ?
        ");
        $stmt->execute([$current_time, $location_string, $record['ID']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Successfully clocked out',
            'data' => [
                'employee_id' => $employee_id,
                'clock_out_time' => $current_time,
                'status' => 'Present',
                'action' => 'clock_out'
            ]
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Clock in/out API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
