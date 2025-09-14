<?php
/**
 * Enhanced PIN Reset System with SMS
 * 
 * This system provides secure PIN reset functionality using SMS codes
 * and integrates with the SIGNSYNC SMS service for notifications.
 */

require_once 'db.php';
require_once 'SignSyncSMSService.php';
require_once 'sms_config.php';

// Initialize SMS service
try {
    $smsService = createSMSService($conn);
} catch (Exception $e) {
    error_log("SMS Service initialization failed: " . $e->getMessage());
    $smsService = null;
}

/**
 * Generate secure PIN reset code
 */
function generateResetCode() {
    return sprintf('%06d', mt_rand(100000, 999999));
}

/**
 * Generate secure PIN
 */
function generateSecurePIN() {
    return sprintf('%04d', mt_rand(1000, 9999));
}

/**
 * Store PIN reset code in database
 */
function storeResetCode($conn, $employeeId, $code, $phoneNumber) {
    // Create table if not exists
    $conn->exec("
        CREATE TABLE IF NOT EXISTS tbl_pin_reset_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            reset_code VARCHAR(10) NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_employee_code (employee_id, reset_code),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Clean expired codes
    $conn->prepare("DELETE FROM tbl_pin_reset_codes WHERE expires_at < NOW()")->execute();
    
    // Set expiration time (15 minutes from now)
    $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60));
    
    // Store new code
    $stmt = $conn->prepare("
        INSERT INTO tbl_pin_reset_codes (employee_id, reset_code, phone_number, expires_at) 
        VALUES (?, ?, ?, ?)
    ");
    
    return $stmt->execute([$employeeId, $code, $phoneNumber, $expiresAt]);
}

/**
 * Verify reset code
 */
function verifyResetCode($conn, $employeeId, $code) {
    $stmt = $conn->prepare("
        SELECT id FROM tbl_pin_reset_codes 
        WHERE employee_id = ? AND reset_code = ? 
        AND expires_at > NOW() AND used = FALSE
    ");
    $stmt->execute([$employeeId, $code]);
    
    if ($resetId = $stmt->fetchColumn()) {
        // Mark code as used
        $conn->prepare("UPDATE tbl_pin_reset_codes SET used = TRUE WHERE id = ?")->execute([$resetId]);
        return true;
    }
    
    return false;
}

/**
 * Request PIN reset via SMS
 */
function requestPINReset($conn, $smsService, $employeeId) {
    try {
        // Get employee details
        $stmt = $conn->prepare("
            SELECT FullName, PhoneNumber, Email 
            FROM tbl_employees 
            WHERE EmployeeID = ?
        ");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }
        
        if (empty($employee['PhoneNumber'])) {
            return ['success' => false, 'message' => 'No phone number on file. Contact admin for PIN reset.'];
        }
        
        // Check rate limiting (max 3 requests per hour)
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM tbl_pin_reset_codes 
            WHERE employee_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$employeeId]);
        $recentRequests = $stmt->fetchColumn();
        
        if ($recentRequests >= 3) {
            return ['success' => false, 'message' => 'Too many reset requests. Please wait before trying again.'];
        }
        
        // Generate reset code
        $resetCode = generateResetCode();
        
        // Store code
        if (!storeResetCode($conn, $employeeId, $resetCode, $employee['PhoneNumber'])) {
            return ['success' => false, 'message' => 'Failed to generate reset code. Please try again.'];
        }
        
        // Send SMS if service is available
        if ($smsService) {
            try {
                $templateData = [
                    'name' => $employee['FullName'],
                    'employee_id' => $employeeId,
                    'reset_code' => $resetCode
                ];
                
                // Use PIN reset code template
                $template = 'Hi {name}, your SIGNSYNC PIN reset code is: {reset_code}. This code expires in 15 minutes. If you didn\'t request this, contact admin immediately.';
                
                $smsService->sendMessage(
                    $employee['PhoneNumber'], 
                    str_replace(['{name}', '{reset_code}'], [$employee['FullName'], $resetCode], $template),
                    SignSyncSMSService::PRIORITY_HIGH
                );
                
                return [
                    'success' => true, 
                    'message' => 'Reset code sent to your phone. Please check your messages.',
                    'phone_mask' => maskPhoneNumber($employee['PhoneNumber'])
                ];
                
            } catch (Exception $e) {
                error_log("SMS sending failed during PIN reset: " . $e->getMessage());
                
                // Return the code if SMS fails
                return [
                    'success' => true,
                    'message' => 'SMS service unavailable. Your reset code is: ' . $resetCode,
                    'reset_code' => $resetCode
                ];
            }
        } else {
            // SMS service not available, return code directly
            return [
                'success' => true,
                'message' => 'SMS service unavailable. Your reset code is: ' . $resetCode,
                'reset_code' => $resetCode
            ];
        }
        
    } catch (Exception $e) {
        error_log("PIN reset request failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error. Please try again later.'];
    }
}

/**
 * Reset PIN with code verification
 */
function resetPINWithCode($conn, $smsService, $employeeId, $resetCode, $newPIN = null) {
    try {
        // Verify reset code
        if (!verifyResetCode($conn, $employeeId, $resetCode)) {
            return ['success' => false, 'message' => 'Invalid or expired reset code'];
        }
        
        // Generate new PIN if not provided
        if (!$newPIN) {
            $newPIN = generateSecurePIN();
        }
        
        // Validate PIN format
        if (!preg_match('/^\d{4}$/', $newPIN)) {
            return ['success' => false, 'message' => 'PIN must be 4 digits'];
        }
        
        // Update PIN in database
        $stmt = $conn->prepare("
            UPDATE tbl_employees 
            SET CustomPIN = ?, PINSetupComplete = 1, PINUpdatedAt = NOW() 
            WHERE EmployeeID = ?
        ");
        
        if (!$stmt->execute([$newPIN, $employeeId])) {
            return ['success' => false, 'message' => 'Failed to update PIN'];
        }
        
        // Get employee details for notification
        $stmt = $conn->prepare("SELECT FullName, PhoneNumber FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Send confirmation SMS
        if ($smsService && $employee && !empty($employee['PhoneNumber'])) {
            try {
                $templateData = [
                    'name' => $employee['FullName'],
                    'pin' => $newPIN
                ];
                
                $smsService->sendTemplateMessage('pin_reset', $employee['PhoneNumber'], $templateData);
            } catch (Exception $e) {
                error_log("PIN reset confirmation SMS failed: " . $e->getMessage());
            }
        }
        
        // Log PIN reset activity
        try {
            $conn->prepare("
                INSERT INTO activity_logs (EmployeeID, ActivityType, Description, Timestamp) 
                VALUES (?, 'PIN_RESET', 'PIN reset via SMS code', NOW())
            ")->execute([$employeeId]);
        } catch (Exception $e) {
            error_log("Failed to log PIN reset activity: " . $e->getMessage());
        }
        
        return [
            'success' => true,
            'message' => 'PIN reset successfully. Your new PIN is: ' . $newPIN,
            'new_pin' => $newPIN
        ];
        
    } catch (Exception $e) {
        error_log("PIN reset with code failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error. Please try again later.'];
    }
}

/**
 * Admin reset PIN (bypasses SMS verification)
 */
function adminResetPIN($conn, $smsService, $employeeId, $newPIN = null) {
    try {
        // Generate new PIN if not provided
        if (!$newPIN) {
            $newPIN = generateSecurePIN();
        }
        
        // Validate PIN format
        if (!preg_match('/^\d{4}$/', $newPIN)) {
            return ['success' => false, 'message' => 'PIN must be 4 digits'];
        }
        
        // Update PIN in database
        $stmt = $conn->prepare("
            UPDATE tbl_employees 
            SET CustomPIN = ?, PINSetupComplete = 1, PINUpdatedAt = NOW() 
            WHERE EmployeeID = ?
        ");
        
        if (!$stmt->execute([$newPIN, $employeeId])) {
            return ['success' => false, 'message' => 'Failed to update PIN'];
        }
        
        // Get employee details for notification
        $stmt = $conn->prepare("SELECT FullName, PhoneNumber FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }
        
        // Send notification SMS
        if ($smsService && !empty($employee['PhoneNumber'])) {
            try {
                $templateData = [
                    'name' => $employee['FullName'],
                    'pin' => $newPIN
                ];
                
                $message = "Hi {$employee['FullName']}, your SIGNSYNC PIN has been reset by admin. Your new PIN is: $newPIN. Please change it after login for security.";
                
                $smsService->sendMessage($employee['PhoneNumber'], $message, SignSyncSMSService::PRIORITY_HIGH);
            } catch (Exception $e) {
                error_log("Admin PIN reset notification SMS failed: " . $e->getMessage());
            }
        }
        
        // Log admin PIN reset activity
        try {
            $conn->prepare("
                INSERT INTO activity_logs (EmployeeID, ActivityType, Description, Timestamp) 
                VALUES (?, 'ADMIN_PIN_RESET', 'PIN reset by administrator', NOW())
            ")->execute([$employeeId]);
        } catch (Exception $e) {
            error_log("Failed to log admin PIN reset activity: " . $e->getMessage());
        }
        
        return [
            'success' => true,
            'message' => 'PIN reset successfully. Employee notified via SMS.',
            'new_pin' => $newPIN,
            'employee_name' => $employee['FullName']
        ];
        
    } catch (Exception $e) {
        error_log("Admin PIN reset failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error. Please try again later.'];
    }
}

/**
 * Mask phone number for privacy
 */
function maskPhoneNumber($phone) {
    if (strlen($phone) >= 4) {
        return substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 6) . substr($phone, -3);
    }
    return '***';
}

/**
 * Send new employee PIN setup notification
 */
function sendNewEmployeePINNotification($conn, $smsService, $employeeId) {
    try {
        $stmt = $conn->prepare("SELECT FullName, PhoneNumber FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee || empty($employee['PhoneNumber'])) {
            return false;
        }
        
        if ($smsService) {
            $templateData = [
                'name' => $employee['FullName'],
                'employee_id' => $employeeId
            ];
            
            $smsService->sendTemplateMessage('pin_setup', $employee['PhoneNumber'], $templateData);
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("New employee PIN notification failed: " . $e->getMessage());
        return false;
    }
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $employeeId = $_POST['employee_id'] ?? '';
    
    switch ($action) {
        case 'request_reset':
            if (empty($employeeId)) {
                echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
                break;
            }
            
            $result = requestPINReset($conn, $smsService, $employeeId);
            echo json_encode($result);
            break;
            
        case 'verify_reset':
            $resetCode = $_POST['reset_code'] ?? '';
            $newPIN = $_POST['new_pin'] ?? null;
            
            if (empty($employeeId) || empty($resetCode)) {
                echo json_encode(['success' => false, 'message' => 'Employee ID and reset code are required']);
                break;
            }
            
            $result = resetPINWithCode($conn, $smsService, $employeeId, $resetCode, $newPIN);
            echo json_encode($result);
            break;
            
        case 'admin_reset':
            $newPIN = $_POST['new_pin'] ?? null;
            
            if (empty($employeeId)) {
                echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
                break;
            }
            
            $result = adminResetPIN($conn, $smsService, $employeeId, $newPIN);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// If not a POST request, show the PIN reset interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIN Reset - SIGNSYNC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .reset-container {
            max-width: 400px;
            margin: 2rem auto;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="reset-container">
            <div class="card shadow">
                <div class="card-header text-center bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-shield-lock"></i> PIN Reset
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Step 1: Request Reset -->
                    <div id="step1" class="step active">
                        <h5 class="card-title">Reset Your PIN</h5>
                        <p class="text-muted">Enter your Employee ID to receive a reset code via SMS.</p>
                        
                        <form id="requestForm">
                            <div class="mb-3">
                                <label for="employee_id" class="form-label">Employee ID</label>
                                <input type="text" class="form-control" id="employee_id" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-send"></i> Send Reset Code
                            </button>
                        </form>
                    </div>
                    
                    <!-- Step 2: Verify Code -->
                    <div id="step2" class="step">
                        <h5 class="card-title">Enter Reset Code</h5>
                        <p class="text-muted">Check your phone for the 6-digit reset code.</p>
                        
                        <form id="verifyForm">
                            <div class="mb-3">
                                <label for="reset_code" class="form-label">Reset Code</label>
                                <input type="text" class="form-control text-center" id="reset_code" 
                                       maxlength="6" pattern="[0-9]{6}" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_pin" class="form-label">New PIN (Optional)</label>
                                <input type="password" class="form-control text-center" id="new_pin" 
                                       maxlength="4" pattern="[0-9]{4}" placeholder="Leave blank for auto-generated">
                                <div class="form-text">4 digits, leave blank for auto-generated PIN</div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Reset PIN
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="goToStep(1)">
                                    <i class="bi bi-arrow-left"></i> Back
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Step 3: Success -->
                    <div id="step3" class="step">
                        <div class="text-center">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">PIN Reset Successful!</h5>
                            <p id="successMessage" class="text-muted"></p>
                            <a href="login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Login Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentEmployeeId = '';
        
        function goToStep(step) {
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.getElementById('step' + step).classList.add('active');
        }
        
        function showAlert(message, type = 'danger') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.querySelector('.card-body').insertBefore(alert, document.querySelector('.step.active'));
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }
        
        document.getElementById('requestForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const employeeId = document.getElementById('employee_id').value;
            currentEmployeeId = employeeId;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'request_reset',
                        employee_id: employeeId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    setTimeout(() => goToStep(2), 1500);
                } else {
                    showAlert(result.message);
                }
            } catch (error) {
                showAlert('Network error. Please try again.');
            }
        });
        
        document.getElementById('verifyForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const resetCode = document.getElementById('reset_code').value;
            const newPin = document.getElementById('new_pin').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'verify_reset',
                        employee_id: currentEmployeeId,
                        reset_code: resetCode,
                        new_pin: newPin
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('successMessage').textContent = result.message;
                    goToStep(3);
                } else {
                    showAlert(result.message);
                }
            } catch (error) {
                showAlert('Network error. Please try again.');
            }
        });
        
        // Auto-format reset code input
        document.getElementById('reset_code').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
        
        // Auto-format PIN input
        document.getElementById('new_pin').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>
