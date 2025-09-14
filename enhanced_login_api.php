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
        // Set appropriate HTTP status
        http_response_code(200);
        
        // Return successful authentication response
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'employee_id' => $result['employee_id'],
            'employee_name' => $result['employee_name'],
            'session_token' => $result['session_token'],
            'is_default_pin' => $result['is_default_pin'],
            'requires_pin_change' => $result['requires_pin_change'],
            'pin_setup_complete' => $result['pin_setup_complete'],
            'is_first_login' => !$result['pin_setup_complete'] || $result['is_default_pin']
        ]);
    } else {
        // Set appropriate HTTP status for failed authentication
        if (isset($result['locked']) && $result['locked']) {
            http_response_code(423); // Locked
        } else {
            http_response_code(401); // Unauthorized
        }
        
        echo json_encode([
            'success' => false,
            'message' => $result['message'],
            'is_first_login' => false,
            'employee_id' => '',
            'locked' => $result['locked'] ?? false
        ]);
    }
    
} catch (Exception $e) {
    error_log("Login API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error occurred during authentication',
        'is_first_login' => false,
        'employee_id' => ''
    ]);
}
?>
