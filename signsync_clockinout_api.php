<?php
/**
 * SIGNSYNC Clock In/Out API
 * Handles attendance clock in and clock out for watch devices
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include 'db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    if (!isset($input['employee_id']) || !isset($input['action'])) {
        throw new Exception('Employee ID and action are required');
    }
    
    $employeeId = trim($input['employee_id']);
    $action = trim(strtolower($input['action']));
    
    // Validate action
    if (!in_array($action, ['clock_in', 'clock_out'])) {
        throw new Exception('Invalid action. Must be clock_in or clock_out');
    }
    
    // Verify employee exists
    $stmt = $conn->prepare("SELECT EmployeeID, FullName FROM tbl_employees WHERE EmployeeID = ?");
    $stmt->execute([$employeeId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Employee not found');
    }
    
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentDateTime = date('Y-m-d H:i:s');
    $currentDate = date('Y-m-d');
    
    if ($action === 'clock_in') {
        // Check if already clocked in today
        $checkStmt = $conn->prepare("
            SELECT ClockInTime FROM tbl_clockinout 
            WHERE EmployeeID = ? AND DATE(ClockInTime) = ? AND ClockOutTime IS NULL
        ");
        $checkStmt->execute([$employeeId, $currentDate]);
        
        if ($checkStmt->rowCount() > 0) {
            throw new Exception('Already clocked in today');
        }
        
        // Insert clock in record
        $insertStmt = $conn->prepare("
            INSERT INTO tbl_clockinout (EmployeeID, ClockInTime, Source)
            VALUES (?, ?, 'SIGNSYNC_WATCH')
        ");
        $insertStmt->execute([$employeeId, $currentDateTime]);
        
        $message = 'Clocked in successfully';
        
    } else { // clock_out
        // Find today's clock in record without clock out
        $clockInStmt = $conn->prepare("
            SELECT ClockInOutID, ClockInTime FROM tbl_clockinout 
            WHERE EmployeeID = ? AND DATE(ClockInTime) = ? AND ClockOutTime IS NULL
            ORDER BY ClockInTime DESC LIMIT 1
        ");
        $clockInStmt->execute([$employeeId, $currentDate]);
        
        if ($clockInStmt->rowCount() === 0) {
            throw new Exception('No clock in record found for today');
        }
        
        $clockInRecord = $clockInStmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate hours worked
        $clockInTime = new DateTime($clockInRecord['ClockInTime']);
        $clockOutTime = new DateTime($currentDateTime);
        $hoursWorked = $clockOutTime->diff($clockInTime)->format('%H:%I:%S');
        
        // Update with clock out time
        $updateStmt = $conn->prepare("
            UPDATE tbl_clockinout 
            SET ClockOutTime = ?, HoursWorked = ?
            WHERE ClockInOutID = ?
        ");
        $updateStmt->execute([$currentDateTime, $hoursWorked, $clockInRecord['ClockInOutID']]);
        
        $message = 'Clocked out successfully';
    }
    
    // Log the activity
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (EmployeeID, ActivityType, ActivityDescription, Timestamp)
        VALUES (?, ?, ?, NOW())
    ");
    $logStmt->execute([
        $employeeId,
        strtoupper($action),
        "{$action} via SIGNSYNC watch at {$currentDateTime}"
    ]);
    
    // Get today's attendance summary
    $summaryStmt = $conn->prepare("
        SELECT ClockInTime, ClockOutTime, HoursWorked
        FROM tbl_clockinout 
        WHERE EmployeeID = ? AND DATE(ClockInTime) = ?
        ORDER BY ClockInTime DESC LIMIT 1
    ");
    $summaryStmt->execute([$employeeId, $currentDate]);
    $todayRecord = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'employee_id' => $employeeId,
            'employee_name' => $employee['FullName'],
            'action' => $action,
            'timestamp' => $currentDateTime,
            'today_summary' => [
                'clock_in' => $todayRecord['ClockInTime'] ?? null,
                'clock_out' => $todayRecord['ClockOutTime'] ?? null,
                'hours_worked' => $todayRecord['HoursWorked'] ?? null
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
