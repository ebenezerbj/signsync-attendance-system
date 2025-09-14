<?php
/**
 * SIGNSYNC Admin PIN Management API
 * Provides admin functions for managing employee PINs
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
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Invalid request - action required');
    }
    
    $action = $input['action'];
    
    switch ($action) {
        case 'list_employees':
            handleListEmployees($conn);
            break;
            
        case 'reset_pin':
            handleResetPin($input, $conn);
            break;
            
        case 'reset_all_pins':
            handleResetAllPins($conn);
            break;
            
        case 'employee_details':
            handleEmployeeDetails($input, $conn);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * List all employees with PIN status
 */
function handleListEmployees($conn) {
    try {
        $stmt = $conn->query("
            SELECT e.EmployeeID, e.FullName, e.PhoneNumber,
                   d.DepartmentName, b.BranchName,
                   e.CustomPIN, e.PINSetupComplete,
                   (SELECT MAX(Timestamp) FROM activity_logs al WHERE al.EmployeeID = e.EmployeeID) as LastActivity
            FROM tbl_employees e
            LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
            ORDER BY e.EmployeeID
        ");
        
        $employees = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $employees[] = [
                'employee_id' => $row['EmployeeID'],
                'name' => $row['FullName'],
                'phone' => $row['PhoneNumber'],
                'department' => $row['DepartmentName'],
                'branch' => $row['BranchName'],
                'has_custom_pin' => !empty($row['CustomPIN']),
                'pin_setup_complete' => (bool)$row['PINSetupComplete'],
                'last_activity' => $row['LastActivity']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Employees loaded successfully',
            'data' => $employees
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to load employees: ' . $e->getMessage());
    }
}

/**
 * Reset PIN for specific employee
 */
function handleResetPin($input, $conn) {
    if (!isset($input['employee_id'])) {
        throw new Exception('Employee ID required');
    }
    
    $employeeId = trim($input['employee_id']);
    
    try {
        // Check if employee exists
        $checkStmt = $conn->prepare("SELECT EmployeeID, FullName FROM tbl_employees WHERE EmployeeID = ?");
        $checkStmt->execute([$employeeId]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Employee not found: ' . $employeeId);
        }
        
        $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Reset PIN
        $resetStmt = $conn->prepare("
            UPDATE tbl_employees 
            SET CustomPIN = NULL, PINSetupComplete = 0 
            WHERE EmployeeID = ?
        ");
        $resetStmt->execute([$employeeId]);
        
        // Log the reset action
        try {
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (EmployeeID, ActivityType, ActivityDescription, Timestamp)
                VALUES (?, 'PIN_RESET', 'PIN reset by admin', NOW())
            ");
            $logStmt->execute([$employeeId]);
        } catch (Exception $e) {
            // Log error but don't fail the reset
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'PIN reset successfully for ' . $employee['FullName'],
            'data' => [
                'employee_id' => $employeeId,
                'employee_name' => $employee['FullName']
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to reset PIN: ' . $e->getMessage());
    }
}

/**
 * Reset ALL employee PINs
 */
function handleResetAllPins($conn) {
    try {
        // Count employees that will be affected
        $countStmt = $conn->query("SELECT COUNT(*) as count FROM tbl_employees WHERE CustomPIN IS NOT NULL");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Reset all PINs
        $resetStmt = $conn->exec("
            UPDATE tbl_employees 
            SET CustomPIN = NULL, PINSetupComplete = 0 
            WHERE CustomPIN IS NOT NULL
        ");
        
        // Log the bulk reset action
        try {
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (EmployeeID, ActivityType, ActivityDescription, Timestamp)
                VALUES ('ADMIN', 'BULK_PIN_RESET', 'All employee PINs reset by admin', NOW())
            ");
            $logStmt->execute();
        } catch (Exception $e) {
            // Log error but don't fail the reset
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'All employee PINs reset successfully',
            'data' => [
                'reset_count' => $resetStmt
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to reset all PINs: ' . $e->getMessage());
    }
}

/**
 * Get detailed employee information
 */
function handleEmployeeDetails($input, $conn) {
    if (!isset($input['employee_id'])) {
        throw new Exception('Employee ID required');
    }
    
    $employeeId = trim($input['employee_id']);
    
    try {
        $stmt = $conn->prepare("
            SELECT e.EmployeeID, e.FullName, e.PhoneNumber, e.Username,
                   d.DepartmentName, b.BranchName,
                   e.CustomPIN, e.PINSetupComplete,
                   (SELECT MAX(Timestamp) FROM activity_logs al WHERE al.EmployeeID = e.EmployeeID) as LastActivity,
                   (SELECT COUNT(*) FROM activity_logs al WHERE al.EmployeeID = e.EmployeeID AND al.ActivityType = 'PIN_AUTH') as LoginCount,
                   (SELECT MAX(ClockInTime) FROM tbl_clockinout tc WHERE tc.EmployeeID = e.EmployeeID) as LastClockIn
            FROM tbl_employees e
            LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
            WHERE e.EmployeeID = ?
        ");
        $stmt->execute([$employeeId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Employee not found: ' . $employeeId);
        }
        
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employee details loaded successfully',
            'data' => [
                'employee_id' => $employee['EmployeeID'],
                'name' => $employee['FullName'],
                'phone' => $employee['PhoneNumber'],
                'username' => $employee['Username'],
                'department' => $employee['DepartmentName'],
                'branch' => $employee['BranchName'],
                'has_custom_pin' => !empty($employee['CustomPIN']),
                'pin_setup_complete' => (bool)$employee['PINSetupComplete'],
                'last_activity' => $employee['LastActivity'],
                'login_count' => (int)$employee['LoginCount'],
                'last_clock_in' => $employee['LastClockIn']
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to load employee details: ' . $e->getMessage());
    }
}
?>
