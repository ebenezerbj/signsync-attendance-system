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
    
    if ($result['success']) {
        // Authentication successful
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'is_first_login' => $result['is_first_login'] ?? false,
            'employee_id' => $employee_id,
            'employee_data' => $result['employee_data'] ?? null
        ]);
    } else {
        // Authentication failed
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $result['message'],
            'is_first_login' => false,
            'employee_id' => '',
            'locked_until' => $result['locked_until'] ?? null,
            'attempts_remaining' => $result['attempts_remaining'] ?? null
        ]);
    }
} catch (Exception $e) {
    error_log("Login API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error occurred',
        'is_first_login' => false,
        'employee_id' => ''
    ]);
}
?>
