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
include_once 'EmployeeAuthenticationManager.php';
include_once 'SignSyncSMSService.php';
include_once 'sms_config.php';

// Initialize SMS service
$smsService = null;
try {
    $smsService = createSMSService($conn);
} catch (Exception $e) {
    error_log('SMS Service init failed: ' . $e->getMessage());
}

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
    
    global $smsService;
    
    switch ($action) {
        case 'list_employees':
            handleListEmployees($conn);
            break;
            
        case 'set_pin':
            handleSetPin($input, $conn, $smsService);
            break;
            
        case 'reset_pin':
            handleResetPin($input, $conn, $smsService);
            break;
            
        case 'reset_all_pins':
            handleResetAllPins($conn, $smsService);
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
 * Admin set/create PIN for an employee
 */
function handleSetPin($input, $conn, $smsService = null) {
    if (!isset($input['employee_id']) || !isset($input['new_pin'])) {
        throw new Exception('Employee ID and new PIN are required');
    }
    
    $employeeId = trim($input['employee_id']);
    $newPin = trim($input['new_pin']);
    
    // Validate PIN format
    if (!preg_match('/^\d{4}$/', $newPin)) {
        throw new Exception('PIN must be exactly 4 digits');
    }
    
    if ($newPin === '1234') {
        throw new Exception('Cannot set default PIN. Choose a different PIN.');
    }
    
    try {
        // Check if employee exists
        $checkStmt = $conn->prepare("SELECT EmployeeID, FullName, PhoneNumber FROM tbl_employees WHERE EmployeeID = ?");
        $checkStmt->execute([$employeeId]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Employee not found: ' . $employeeId);
        }
        
        $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        $conn->beginTransaction();
        
        // Update tbl_employees.CustomPIN
        $stmt = $conn->prepare("UPDATE tbl_employees SET CustomPIN = ?, PINSetupComplete = 1 WHERE EmployeeID = ?");
        $stmt->execute([$newPin, $employeeId]);
        
        // Update or insert into employee_pins with hashed PIN
        $hashedPin = password_hash($newPin, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO employee_pins (EmployeeID, pin, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE pin = VALUES(pin), updated_at = NOW()
        ");
        $stmt->execute([$employeeId, $hashedPin]);
        
        $conn->commit();
        
        // Log the action
        try {
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (EmployeeID, ActivityType, ActivityDescription, Timestamp)
                VALUES (?, 'PIN_SET_BY_ADMIN', 'PIN created/set by admin', NOW())
            ");
            $logStmt->execute([$employeeId]);
        } catch (Exception $e) {
            // Log error but don't fail
        }
        
        // Send SMS notification
        $smsSent = false;
        if ($smsService && !empty($employee['PhoneNumber'])) {
            try {
                $smsService->sendTemplateMessage('pin_reset', $employee['PhoneNumber'], [
                    'name' => $employee['FullName'],
                    'pin' => $newPin
                ]);
                $smsSent = true;
            } catch (Exception $smsEx) {
                error_log('SMS notification failed for PIN set: ' . $smsEx->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'PIN set successfully for ' . $employee['FullName'] . ($smsSent ? ' (SMS sent)' : ''),
            'data' => [
                'employee_id' => $employeeId,
                'employee_name' => $employee['FullName'],
                'sms_sent' => $smsSent
            ]
        ]);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw new Exception('Failed to set PIN: ' . $e->getMessage());
    }
}

/**
 * Reset PIN for specific employee
 */
function handleResetPin($input, $conn, $smsService = null) {
    if (!isset($input['employee_id'])) {
        throw new Exception('Employee ID required');
    }
    
    $employeeId = trim($input['employee_id']);
    
    try {
        // Check if employee exists
        $checkStmt = $conn->prepare("SELECT EmployeeID, FullName, PhoneNumber FROM tbl_employees WHERE EmployeeID = ?");
        $checkStmt->execute([$employeeId]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Employee not found: ' . $employeeId);
        }
        
        $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Reset PIN in tbl_employees
        $resetStmt = $conn->prepare("
            UPDATE tbl_employees 
            SET CustomPIN = NULL, PINSetupComplete = 0 
            WHERE EmployeeID = ?
        ");
        $resetStmt->execute([$employeeId]);
        
        // Also clear employee_pins table
        $clearPinsStmt = $conn->prepare("DELETE FROM employee_pins WHERE EmployeeID = ?");
        $clearPinsStmt->execute([$employeeId]);
        
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
        
        // Send SMS notification
        $smsSent = false;
        if ($smsService && !empty($employee['PhoneNumber'])) {
            try {
                $smsService->sendTemplateMessage('pin_reset', $employee['PhoneNumber'], [
                    'name' => $employee['FullName'],
                    'pin' => '1234'
                ]);
                $smsSent = true;
            } catch (Exception $smsEx) {
                error_log('SMS notification failed for PIN reset: ' . $smsEx->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'PIN reset successfully for ' . $employee['FullName'] . ($smsSent ? ' (SMS sent)' : ''),
            'data' => [
                'employee_id' => $employeeId,
                'employee_name' => $employee['FullName'],
                'sms_sent' => $smsSent
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to reset PIN: ' . $e->getMessage());
    }
}

/**
 * Reset ALL employee PINs
 */
function handleResetAllPins($conn, $smsService = null) {
    try {
        // Count employees that will be affected
        $countStmt = $conn->query("SELECT COUNT(*) as count FROM tbl_employees WHERE CustomPIN IS NOT NULL");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get all employees with phone numbers for SMS notification
        $empStmt = $conn->query("SELECT EmployeeID, FullName, PhoneNumber FROM tbl_employees WHERE CustomPIN IS NOT NULL AND PhoneNumber IS NOT NULL AND PhoneNumber != ''");
        $affectedEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Reset all PINs in tbl_employees
        $resetStmt = $conn->exec("
            UPDATE tbl_employees 
            SET CustomPIN = NULL, PINSetupComplete = 0 
            WHERE CustomPIN IS NOT NULL
        ");
        
        // Also clear all employee_pins records
        $conn->exec("DELETE FROM employee_pins");
        
        // Send SMS to all affected employees
        $smsSentCount = 0;
        if ($smsService && !empty($affectedEmployees)) {
            foreach ($affectedEmployees as $emp) {
                try {
                    $smsService->sendTemplateMessage('pin_reset', $emp['PhoneNumber'], [
                        'name' => $emp['FullName'],
                        'pin' => '1234'
                    ]);
                    $smsSentCount++;
                } catch (Exception $smsEx) {
                    error_log('SMS failed for bulk reset (' . $emp['EmployeeID'] . '): ' . $smsEx->getMessage());
                }
            }
        }
        
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
            'message' => 'All employee PINs reset successfully' . ($smsSentCount > 0 ? " ($smsSentCount SMS sent)" : ''),
            'data' => [
                'reset_count' => $resetStmt,
                'sms_sent_count' => $smsSentCount
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
