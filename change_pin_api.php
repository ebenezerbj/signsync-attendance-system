<?php
include 'db.php';
include_once 'EmployeeAuthenticationManager.php';
include_once 'SignSyncSMSService.php';
include_once 'sms_config.php';

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
$is_first_login = filter_var($_POST['is_first_login'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (empty($employee_id) || empty($new_pin)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Employee ID and new PIN are required']);
    exit;
}

// Only require current PIN if not first login
if (!$is_first_login && empty($current_pin)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Current PIN is required']);
    exit;
}

if (!preg_match('/^\d{4}$/', $new_pin)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'PIN must be exactly 4 digits']);
    exit;
}

if ($new_pin === '1234') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot use default PIN']);
    exit;
}

try {
    $authManager = new EmployeeAuthenticationManager($conn);

    // For first login, treat current PIN as the default
    $oldPin = $is_first_login ? '1234' : $current_pin;

    $result = $authManager->changePIN($employee_id, $oldPin, $new_pin);

    if ($result['success']) {
        // Send SMS notification
        $smsSent = false;
        try {
            $stmt = $conn->prepare("SELECT FullName, PhoneNumber FROM tbl_employees WHERE EmployeeID = ?");
            $stmt->execute([$employee_id]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($emp && !empty($emp['PhoneNumber'])) {
                $smsService = createSMSService($conn);
                $smsService->sendTemplateMessage('pin_changed', $emp['PhoneNumber'], [
                    'name' => $emp['FullName']
                ]);
                $smsSent = true;
            }
        } catch (Exception $smsEx) {
            error_log('SMS notification failed for PIN change: ' . $smsEx->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'PIN changed successfully'
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
} catch (Exception $e) {
    error_log("Change PIN API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
