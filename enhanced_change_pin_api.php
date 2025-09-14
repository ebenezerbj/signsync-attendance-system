<?php
/**
 * Enhanced PIN Change API
 * Uses EmployeeAuthenticationManager for secure PIN management
 * Includes validation, security logging, and proper authentication
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
$current_pin = $_POST['current_pin'] ?? '';
$new_pin = $_POST['new_pin'] ?? '';
$session_token = $_POST['session_token'] ?? '';

// Validate required fields
if (empty($employee_id) || empty($current_pin) || empty($new_pin)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Employee ID, current PIN, and new PIN are required']);
    exit;
}

try {
    // Initialize authentication manager
    $authManager = new EmployeeAuthenticationManager($conn);
    
    // Validate session if provided (optional for mobile app compatibility)
    if (!empty($session_token)) {
        $session = $authManager->validateSession($session_token);
        if (!$session) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired session']);
            exit;
        }
        
        // Ensure session matches employee ID
        if ($session['employee_id'] !== strtoupper(trim($employee_id))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Session does not match employee ID']);
            exit;
        }
    }
    
    // Validate new PIN format
    if (!preg_match('/^\d{4}$/', $new_pin)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PIN must be exactly 4 digits']);
        exit;
    }
    
    // Prevent weak PINs
    $weakPins = ['0000', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999', '1234', '4321', '0123', '9876'];
    if (in_array($new_pin, $weakPins)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please choose a more secure PIN. Avoid sequential or repeated numbers.']);
        exit;
    }
    
    // Prevent same PIN
    if ($current_pin === $new_pin) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New PIN must be different from current PIN']);
        exit;
    }
    
    // Attempt to change PIN
    $result = $authManager->changePIN($employee_id, $current_pin, $new_pin);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'pin_setup_complete' => true
        ]);
        
        // Log successful PIN change
        error_log("PIN changed successfully for employee: $employee_id");
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
        
        // Log failed PIN change attempt
        error_log("Failed PIN change attempt for employee: $employee_id - " . $result['message']);
    }
    
} catch (Exception $e) {
    error_log("PIN Change API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error occurred during PIN change'
    ]);
}
?>
