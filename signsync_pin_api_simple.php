<?php
/**
 * SIGNSYNC PIN Validation API - Simple Version
 * Validates Employee ID and PIN for watch attendance (without logging)
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
    if (!isset($input['employee_id']) || !isset($input['pin'])) {
        throw new Exception('Employee ID and PIN are required');
    }
    
    $employeeId = trim($input['employee_id']);
    $pin = trim($input['pin']);
    
    // Validate input format
    if (empty($employeeId) || empty($pin)) {
        throw new Exception('Employee ID and PIN cannot be empty');
    }
    
    // Query employee data
    $stmt = $conn->prepare("
        SELECT e.EmployeeID, e.FullName, e.DepartmentID, d.DepartmentName,
               e.Username, e.PhoneNumber, e.BranchID, b.BranchName,
               e.Password
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
    
    // PIN Validation Strategy:
    $validPin = false;
    $pinSource = '';
    
    // Strategy 1: Last 4 digits of phone number
    if (!empty($employee['PhoneNumber']) && strlen($employee['PhoneNumber']) >= 4) {
        $phonePin = substr($employee['PhoneNumber'], -4);
        if ($pin === $phonePin) {
            $validPin = true;
            $pinSource = 'phone';
        }
    }
    
    // Strategy 2: Default PIN "1234"
    if (!$validPin && $pin === '1234') {
        $validPin = true;
        $pinSource = 'default';
    }
    
    // Strategy 3: Check if PIN matches the actual password
    if (!$validPin && password_verify($pin, $employee['Password'])) {
        $validPin = true;
        $pinSource = 'password';
    }
    
    // Strategy 4: Simple PIN based on employee ID suffix
    if (!$validPin) {
        // Extract numeric part from employee ID (e.g., AKCBSTF001 -> 001 -> 0001)
        preg_match('/(\d+)$/', $employeeId, $matches);
        if (!empty($matches[1])) {
            $numericPart = str_pad($matches[1], 4, '0', STR_PAD_LEFT);
            if ($pin === $numericPart) {
                $validPin = true;
                $pinSource = 'employee_id';
            }
        }
    }
    
    if (!$validPin) {
        throw new Exception('Invalid PIN');
    }
    
    // Return success response with employee data
    echo json_encode([
        'success' => true,
        'message' => 'Authentication successful',
        'data' => [
            'employee_id' => $employee['EmployeeID'],
            'name' => $employee['FullName'],
            'department' => $employee['DepartmentName'],
            'branch' => $employee['BranchName'],
            'pin_source' => $pinSource
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
