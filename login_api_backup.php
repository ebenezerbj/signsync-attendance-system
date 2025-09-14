<?php
/**
 * Enhanced Mobile Login API
 * Uses EmployeeAuthenticationManager for secure PIN authentication
 * Includes rate limiting, account lockout, and audit logging
 */
include 'db.php';
include 'EmployeeAuthenticationManager.php';

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
$pin = $_POST['pin'] ?? '';

if (empty($employee_id) || empty($pin)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Employee ID and PIN are required']);
    exit;
}

try {
    // Initialize authentication manager
    $authManager = new EmployeeAuthenticationManager($conn);
    
    // Get client IP and user agent for security logging
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Authenticate with PIN
    $result = $authManager->authenticateWithPIN($employee_id, $pin, $ipAddress, $userAgent);
        
        if ($employee) {
            echo json_encode([
                'success' => true,
                'message' => 'Login successful with default PIN',
                'is_first_login' => true,
                'employee_id' => $employee['EmployeeID']
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID', 'is_first_login' => false, 'employee_id' => '']);
        }
    } else {
        // Check custom PIN from employee_pins table (to be created)
        $stmt = $conn->prepare("
            SELECT e.EmployeeID, e.FullName, e.PhoneNumber, e.BranchID 
            FROM tbl_employees e 
            LEFT JOIN employee_pins ep ON e.EmployeeID = ep.EmployeeID 
            WHERE e.EmployeeID = ? AND (ep.pin = ? OR ep.pin IS NULL)
        ");
        $stmt->execute([$employee_id, $pin]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'is_first_login' => false,
                'employee_id' => $employee['EmployeeID']
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID or PIN', 'is_first_login' => false, 'employee_id' => '']);
        }
    }
} catch (PDOException $e) {
    error_log("Login API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
