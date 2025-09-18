<?php
/**
 * SIGNSYNC PIN Authentication API (Mobile)
 * Accepts JSON or form data. Uses EmployeeAuthenticationManager for validation.
 * Returns top-level fields compatible with Android AuthResponse.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include 'db.php';
include_once 'EmployeeAuthenticationManager.php';
// Auth configuration and logger
$authConfig = include __DIR__ . '/config_auth.php';
include_once __DIR__ . '/lib/AuthLogger.php';
$logger = new AuthLogger($authConfig);
$requestId = AuthLogger::newRequestId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Support both JSON and form-encoded payloads
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    
    $employeeId = '';
    $pin = '';
    
    if (is_array($json)) {
        $employeeId = $json['employee_id'] ?? '';
        $pin = $json['pin'] ?? '';
    } else {
        $employeeId = $_POST['employee_id'] ?? '';
        $pin = $_POST['pin'] ?? '';
    }
    
    $employeeId = trim((string)$employeeId);
    $pin = trim((string)$pin);
    
    if ($employeeId === '' || $pin === '') {
        $logger->log('warn', 'Missing credentials', [
            'request_id' => $requestId,
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Employee ID and PIN are required', 'request_id' => $requestId]);
        exit;
    }
    
    // Look up extra employee details for response
    $stmt = $conn->prepare("\r
        SELECT e.EmployeeID, e.FullName, e.DepartmentID, d.DepartmentName, e.BranchID, b.BranchName\r
        FROM tbl_employees e\r
        LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID\r
        LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID\r
        WHERE e.EmployeeID = ?\r
    ");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        $logger->log('info', 'Employee not found', [
            'request_id' => $requestId,
            'employee_id' => $employeeId,
        ]);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found', 'request_id' => $requestId]);
        exit;
    }
    
    // Authenticate via central manager (handles default/custom PINs and lockouts)
    $authManager = new EmployeeAuthenticationManager($conn);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $logPin = ($authConfig['mask_pin'] ?? true) ? '****' : $pin;
    $logger->log('info', 'PIN auth request', [
        'request_id' => $requestId,
        'employee_id' => $employeeId,
        'pin' => $logPin,
        'remote_ip' => $ipAddress,
        'user_agent' => $userAgent,
    ]);

    $result = $authManager->authenticateWithPIN($employeeId, $pin, $ipAddress, $userAgent);
    
    if (!$result['success']) {
        $logger->log('warn', 'Central auth failed', [
            'request_id' => $requestId,
            'employee_id' => $employeeId,
            'reason' => $result['message'] ?? 'unknown',
            'locked' => $result['locked'] ?? false,
        ]);

        // Fallback to legacy strategies only if explicitly enabled
        if (!($authConfig['enable_legacy_pin_fallback'] ?? false)) {
            http_response_code(isset($result['locked']) && $result['locked'] ? 423 : 401);
            echo json_encode([
                'success' => false,
                'message' => $result['message'] ?? 'Authentication failed',
                'request_id' => $requestId,
            ]);
            exit;
        }

        // Legacy compatibility path
        $pinValid = false;
        
        // 1) Last 4 digits of phone number
        if (!$pinValid && !empty($employee['PhoneNumber']) && strlen($employee['PhoneNumber']) >= 4) {
            $phonePin = substr($employee['PhoneNumber'], -4);
            if ($pin === $phonePin) { $pinValid = true; }
        }
        // 2) Check if PIN matches the actual password hash
        if (!$pinValid) {
            $pwdStmt = $conn->prepare("SELECT Password FROM tbl_employees WHERE EmployeeID = ?");
            $pwdStmt->execute([$employeeId]);
            $pwdRow = $pwdStmt->fetch(PDO::FETCH_ASSOC);
            if ($pwdRow && !empty($pwdRow['Password'])) {
                if (@password_verify($pin, $pwdRow['Password'])) { $pinValid = true; }
            }
        }
        // 3) Employee ID suffix (last 4 digits)
        if (!$pinValid) {
            if (preg_match('/(\n+)$/', $employeeId) === 1) {}
            if (preg_match('/(\d+)$/', $employeeId, $m)) {
                $suffix = str_pad($m[1], 4, '0', STR_PAD_LEFT);
                if ($pin === $suffix) { $pinValid = true; }
            }
        }
        
        if ($pinValid) {
            $logger->log('info', 'Legacy PIN accepted', [
                'request_id' => $requestId,
                'employee_id' => $employeeId,
            ]);
            // Issue a session token for compatibility
            $sessionToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);
            $sessStmt = $conn->prepare("INSERT INTO tbl_authentication_sessions (employee_id, session_token, session_type, ip_address, expires_at) VALUES (?, ?, 'mobile', ?, ?)");
            $sessStmt->execute([$employeeId, $sessionToken, $ipAddress, $expiresAt]);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Authentication successful',
                'employeeName' => $employee['FullName'] ?? '',
                'department' => $employee['DepartmentName'] ?? '',
                'employeeId' => $employee['EmployeeID'],
                'token' => $sessionToken,
                'request_id' => $requestId,
            ]);
            exit;
        }
        
        http_response_code(isset($result['locked']) && $result['locked'] ? 423 : 401);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Authentication failed',
            'request_id' => $requestId,
        ]);
        exit;
    }
    
    // Success: return top-level fields expected by the app
    http_response_code(200);
    $logger->log('info', 'PIN auth success', [
        'request_id' => $requestId,
        'employee_id' => $employeeId,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Authentication successful',
        'employeeName' => $employee['FullName'] ?? ($result['employee_name'] ?? ''),
        'department' => $employee['DepartmentName'] ?? '',
        'employeeId' => $employee['EmployeeID'],
        'token' => $result['session_token'] ?? '',
        'request_id' => $requestId,
    ]);
    
} catch (Throwable $e) {
    $logger->log('error', 'PIN auth fatal error', [
        'request_id' => $requestId ?? 'n/a',
        'error' => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'request_id' => $requestId ?? 'n/a',
    ]);
}
?>
