<?php
/**
 * SIGNSYNC Enhanced PIN API with Custom PIN Setup
 * Handles first-time login with default PIN and custom PIN creation
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
    
    // Check if this is a PIN setup request
    if (isset($input['action']) && $input['action'] === 'setup_pin') {
        handlePinSetup($input, $conn);
        return;
    }
    
    // Regular PIN validation
    if (!isset($input['employee_id']) || !isset($input['pin'])) {
        throw new Exception('Employee ID and PIN are required');
    }
    
    $employeeId = trim($input['employee_id']);
    $pin = trim($input['pin']);
    
    // Validate input format
    if (empty($employeeId) || empty($pin)) {
        throw new Exception('Employee ID and PIN cannot be empty');
    }
    
    // Query employee data including custom PIN info
    $stmt = $conn->prepare("
        SELECT e.EmployeeID, e.FullName, e.DepartmentID, d.DepartmentName,
               e.Username, e.PhoneNumber, e.BranchID, b.BranchName,
               e.Password, e.CustomPIN, e.PINSetupComplete
        FROM tbl_employees e
        LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
        LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
        WHERE e.EmployeeID = ?
    ");
    
    $stmt->execute([$employeeId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Employee not found');
    }
    
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // PIN Validation Strategy with Custom PIN Support
    $validPin = false;
    $pinSource = '';
    $needsPinSetup = false;
    
    // Priority 1: Custom PIN (if set)
    if (!empty($employee['CustomPIN'])) {
        if ($pin === $employee['CustomPIN']) {
            $validPin = true;
            $pinSource = 'custom';
        }
    }
    
    // Priority 2: Default PIN "1234" (for first-time users or fallback)
    if (!$validPin && $pin === '1234') {
        $validPin = true;
        $pinSource = 'default';
        
        // Check if user needs to set up custom PIN
        if (empty($employee['CustomPIN']) || $employee['PINSetupComplete'] != 1) {
            $needsPinSetup = true;
        }
    }
    
    // Priority 3: Legacy PIN strategies (for backward compatibility)
    if (!$validPin) {
        // Phone PIN strategy
        if (!empty($employee['PhoneNumber']) && strlen($employee['PhoneNumber']) >= 4) {
            $phonePin = substr($employee['PhoneNumber'], -4);
            if ($pin === $phonePin) {
                $validPin = true;
                $pinSource = 'phone';
            }
        }
        
        // Employee ID PIN strategy
        if (!$validPin) {
            preg_match('/(\d+)$/', $employeeId, $matches);
            if (!empty($matches[1])) {
                $numericPart = str_pad($matches[1], 4, '0', STR_PAD_LEFT);
                if ($pin === $numericPart) {
                    $validPin = true;
                    $pinSource = 'employee_id';
                }
            }
        }
        
        // Password verification strategy
        if (!$validPin && password_verify($pin, $employee['Password'])) {
            $validPin = true;
            $pinSource = 'password';
        }
    }
    
    if (!$validPin) {
        throw new Exception('Invalid PIN');
    }
    
    // Log successful authentication
    try {
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (EmployeeID, ActivityType, ActivityDescription, Timestamp)
            VALUES (?, 'PIN_AUTH', ?, NOW())
        ");
        $logStmt->execute([
            $employeeId,
            "PIN authentication successful via {$pinSource}"
        ]);
    } catch (Exception $e) {
        // Log error but don't fail authentication
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Authentication successful',
        'data' => [
            'employee_id' => $employee['EmployeeID'],
            'name' => $employee['FullName'],
            'department' => $employee['DepartmentName'],
            'branch' => $employee['BranchName'],
            'pin_source' => $pinSource,
            'needs_pin_setup' => $needsPinSetup,
            'has_custom_pin' => !empty($employee['CustomPIN'])
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle PIN setup for first-time users
 */
function handlePinSetup($input, $conn) {
    try {
        if (!isset($input['employee_id']) || !isset($input['new_pin'])) {
            throw new Exception('Employee ID and new PIN are required for setup');
        }
        
        $employeeId = trim($input['employee_id']);
        $newPin = trim($input['new_pin']);
        
        // Validate new PIN
        if (strlen($newPin) < 4 || strlen($newPin) > 8) {
            throw new Exception('PIN must be between 4 and 8 digits');
        }
        
        if (!preg_match('/^\d+$/', $newPin)) {
            throw new Exception('PIN must contain only numbers');
        }
        
        // Check if employee exists
        $stmt = $conn->prepare("SELECT EmployeeID, FullName FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Employee not found');
        }
        
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update employee with custom PIN
        $updateStmt = $conn->prepare("
            UPDATE tbl_employees 
            SET CustomPIN = ?, PINSetupComplete = 1 
            WHERE EmployeeID = ?
        ");
        $updateStmt->execute([$newPin, $employeeId]);
        
        // Log PIN setup
        try {
            $logStmt = $conn->prepare("
                INSERT INTO activity_logs (EmployeeID, ActivityType, ActivityDescription, Timestamp)
                VALUES (?, 'PIN_SETUP', 'Custom PIN created successfully', NOW())
            ");
            $logStmt->execute([$employeeId]);
        } catch (Exception $e) {
            // Log error but don't fail setup
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Custom PIN created successfully',
            'data' => [
                'employee_id' => $employeeId,
                'name' => $employee['FullName'],
                'pin_setup_complete' => true
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>
