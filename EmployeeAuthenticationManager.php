<?php
/**
 * Comprehensive Employee Authentication Manager
 * Handles both PIN (mobile app) and Password (web interface) authentication
 * Ensures security, proper validation, and audit logging
 */
class EmployeeAuthenticationManager {
    private PDO $conn;
    private array $config;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
        $this->config = [
            'max_login_attempts' => 5,
            'lockout_duration' => 30, // minutes
            'pin_length' => 4,
            'min_password_length' => 8,
            'default_pin' => '1234',
            'require_pin_change' => true,
            'require_password_change' => true,
            'session_timeout' => 3600 // 1 hour
        ];
        
        $this->initializeTables();
    }
    
    /**
     * Initialize required tables for authentication tracking
     */
    private function initializeTables(): void {
        // Create login_attempts table for security tracking
        $sql = "CREATE TABLE IF NOT EXISTS tbl_login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(15) NOT NULL,
            login_type ENUM('pin', 'password') NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            success BOOLEAN NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            failure_reason VARCHAR(255),
            INDEX idx_employee_id (employee_id),
            INDEX idx_attempt_time (attempt_time)
        )";
        $this->conn->exec($sql);
        
        // Create authentication_sessions table
        $sql = "CREATE TABLE IF NOT EXISTS tbl_authentication_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(15) NOT NULL,
            session_token VARCHAR(64) NOT NULL UNIQUE,
            session_type ENUM('web', 'mobile') NOT NULL,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_employee_id (employee_id),
            INDEX idx_session_token (session_token),
            INDEX idx_expires_at (expires_at)
        )";
        $this->conn->exec($sql);
    }
    
    /**
     * Authenticate employee with PIN (for mobile app)
     */
    public function authenticateWithPIN(string $employeeId, string $pin, string $ipAddress = '', string $userAgent = ''): array {
        $employeeId = trim(strtoupper($employeeId));
        $pin = trim($pin);
        
        // Check if account is locked
        if ($this->isAccountLocked($employeeId, 'pin')) {
            $this->logLoginAttempt($employeeId, 'pin', false, $ipAddress, $userAgent, 'Account locked due to multiple failed attempts');
            return [
                'success' => false,
                'message' => 'Account is temporarily locked due to multiple failed login attempts. Please try again later.',
                'locked' => true
            ];
        }
        
        // Validate employee exists
        $employee = $this->getEmployee($employeeId);
        if (!$employee) {
            $this->logLoginAttempt($employeeId, 'pin', false, $ipAddress, $userAgent, 'Employee not found');
            return ['success' => false, 'message' => 'Invalid employee ID'];
        }
        
        // Check PIN
        $pinValid = false;
        $isDefaultPin = false;
        $requiresPinChange = false;
        
        // First check if employee has custom PIN
        if (!empty($employee['CustomPIN'])) {
            $pinValid = ($pin === $employee['CustomPIN']);
        } else {
            // Check employee_pins table
            $stmt = $this->conn->prepare("SELECT pin FROM employee_pins WHERE EmployeeID = ?");
            $stmt->execute([$employeeId]);
            $customPin = $stmt->fetchColumn();
            
            if ($customPin) {
                $pinValid = password_verify($pin, $customPin);
            } else {
                // Allow default PIN
                $pinValid = ($pin === $this->config['default_pin']);
                $isDefaultPin = true;
                $requiresPinChange = $this->config['require_pin_change'];
            }
        }
        
        if ($pinValid) {
            $this->logLoginAttempt($employeeId, 'pin', true, $ipAddress, $userAgent);
            $this->clearFailedAttempts($employeeId, 'pin');
            
            // Generate session token
            $sessionToken = $this->generateSessionToken();
            $this->createSession($employeeId, $sessionToken, 'mobile', $ipAddress);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'employee_id' => $employeeId,
                'employee_name' => $employee['FullName'],
                'session_token' => $sessionToken,
                'is_default_pin' => $isDefaultPin,
                'requires_pin_change' => $requiresPinChange,
                'pin_setup_complete' => (bool)$employee['PINSetupComplete']
            ];
        } else {
            $this->logLoginAttempt($employeeId, 'pin', false, $ipAddress, $userAgent, 'Invalid PIN');
            return ['success' => false, 'message' => 'Invalid PIN'];
        }
    }
    
    /**
     * Authenticate employee with password (for web interface)
     */
    public function authenticateWithPassword(string $username, string $password, string $ipAddress = '', string $userAgent = ''): array {
        $username = trim($username);
        
        // Get employee by username
        $stmt = $this->conn->prepare("
            SELECT e.EmployeeID, e.FullName, e.Username, e.Password, r.RoleName, e.ResetToken
            FROM tbl_employees e
            LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
            WHERE e.Username = ?
        ");
        $stmt->execute([$username]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            $this->logLoginAttempt($username, 'password', false, $ipAddress, $userAgent, 'Username not found');
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        $employeeId = $employee['EmployeeID'];
        
        // Check if account is locked
        if ($this->isAccountLocked($employeeId, 'password')) {
            $this->logLoginAttempt($employeeId, 'password', false, $ipAddress, $userAgent, 'Account locked');
            return [
                'success' => false,
                'message' => 'Account is temporarily locked due to multiple failed login attempts.',
                'locked' => true
            ];
        }
        
        // Verify password
        $passwordValid = false;
        $requiresPasswordChange = false;
        
        if (empty($employee['Password'])) {
            // No password set - require setup
            $requiresPasswordChange = true;
        } else {
            $passwordValid = password_verify($password, $employee['Password']);
        }
        
        if ($passwordValid) {
            $this->logLoginAttempt($employeeId, 'password', true, $ipAddress, $userAgent);
            $this->clearFailedAttempts($employeeId, 'password');
            
            // Generate session token
            $sessionToken = $this->generateSessionToken();
            $this->createSession($employeeId, $sessionToken, 'web', $ipAddress);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'employee_id' => $employeeId,
                'employee_name' => $employee['FullName'],
                'username' => $employee['Username'],
                'role' => $employee['RoleName'],
                'session_token' => $sessionToken,
                'requires_password_change' => $requiresPasswordChange
            ];
        } else {
            $this->logLoginAttempt($employeeId, 'password', false, $ipAddress, $userAgent, 'Invalid password');
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
    }
    
    /**
     * Change employee PIN
     */
    public function changePIN(string $employeeId, string $oldPin, string $newPin): array {
        $employeeId = trim(strtoupper($employeeId));
        
        // Validate new PIN
        if (!$this->isValidPIN($newPin)) {
            return ['success' => false, 'message' => 'PIN must be 4 digits'];
        }
        
        // Verify old PIN first
        $employee = $this->getEmployee($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }
        
        // Verify old PIN
        $oldPinValid = false;
        if (!empty($employee['CustomPIN'])) {
            $oldPinValid = ($oldPin === $employee['CustomPIN']);
        } else {
            $oldPinValid = ($oldPin === $this->config['default_pin']);
        }
        
        if (!$oldPinValid) {
            return ['success' => false, 'message' => 'Current PIN is incorrect'];
        }
        
        // Update PIN in both tables
        try {
            $this->conn->beginTransaction();
            
            // Update tbl_employees
            $stmt = $this->conn->prepare("UPDATE tbl_employees SET CustomPIN = ?, PINSetupComplete = 1 WHERE EmployeeID = ?");
            $stmt->execute([$newPin, $employeeId]);
            
            // Update or insert into employee_pins with hashed PIN
            $hashedPin = password_hash($newPin, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("
                INSERT INTO employee_pins (EmployeeID, pin, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE pin = VALUES(pin), updated_at = NOW()
            ");
            $stmt->execute([$employeeId, $hashedPin]);
            
            $this->conn->commit();
            
            return ['success' => true, 'message' => 'PIN changed successfully'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => 'Failed to update PIN: ' . $e->getMessage()];
        }
    }
    
    /**
     * Change employee password
     */
    public function changePassword(string $employeeId, string $oldPassword, string $newPassword): array {
        $employeeId = trim(strtoupper($employeeId));
        
        // Validate new password
        if (!$this->isValidPassword($newPassword)) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        $employee = $this->getEmployee($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }
        
        // Verify old password if one exists
        if (!empty($employee['Password'])) {
            if (!password_verify($oldPassword, $employee['Password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("UPDATE tbl_employees SET Password = ?, ResetToken = NULL, ResetTokenExpires = NULL WHERE EmployeeID = ?");
        $stmt->execute([$hashedPassword, $employeeId]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    
    /**
     * Generate secure credentials for employees who don't have them
     */
    public function ensureEmployeeCredentials(string $employeeId): array {
        $employee = $this->getEmployee($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }
        
        $generated = [];
        
        // Generate password if missing
        if (empty($employee['Password'])) {
            $tempPassword = $this->generateSecurePassword();
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->conn->prepare("UPDATE tbl_employees SET Password = ? WHERE EmployeeID = ?");
            $stmt->execute([$hashedPassword, $employeeId]);
            
            $generated['password'] = $tempPassword;
        }
        
        // Generate PIN if missing
        if (empty($employee['CustomPIN'])) {
            $tempPin = $this->generateSecurePIN();
            
            $stmt = $this->conn->prepare("UPDATE tbl_employees SET CustomPIN = ?, PINSetupComplete = 0 WHERE EmployeeID = ?");
            $stmt->execute([$tempPin, $employeeId]);
            
            // Also add to employee_pins table
            $hashedPin = password_hash($tempPin, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("
                INSERT INTO employee_pins (EmployeeID, pin, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE pin = VALUES(pin), updated_at = NOW()
            ");
            $stmt->execute([$employeeId, $hashedPin]);
            
            $generated['pin'] = $tempPin;
        }
        
        return ['success' => true, 'generated' => $generated];
    }
    
    /**
     * Get authentication status for all employees
     */
    public function getAuthenticationStatus(): array {
        $stmt = $this->conn->query("
            SELECT 
                e.EmployeeID,
                e.FullName,
                e.Username,
                CASE WHEN e.Password IS NOT NULL AND e.Password != '' THEN 'SET' ELSE 'MISSING' END as password_status,
                CASE WHEN e.CustomPIN IS NOT NULL AND e.CustomPIN != '' THEN e.CustomPIN ELSE 'DEFAULT(1234)' END as pin_status,
                e.PINSetupComplete,
                ep.pin as custom_pin_hash,
                (SELECT COUNT(*) FROM tbl_login_attempts la WHERE la.employee_id = e.EmployeeID AND la.attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND la.success = 0) as failed_attempts_last_hour
            FROM tbl_employees e
            LEFT JOIN employee_pins ep ON e.EmployeeID = ep.EmployeeID
            ORDER BY e.EmployeeID
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Reset employee credentials
     */
    public function resetEmployeeCredentials(string $employeeId, bool $resetPassword = true, bool $resetPin = true): array {
        $employee = $this->getEmployee($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }
        
        $newCredentials = [];
        
        try {
            $this->conn->beginTransaction();
            
            if ($resetPassword) {
                $tempPassword = $this->generateSecurePassword();
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                
                $stmt = $this->conn->prepare("UPDATE tbl_employees SET Password = ?, ResetToken = NULL, ResetTokenExpires = NULL WHERE EmployeeID = ?");
                $stmt->execute([$hashedPassword, $employeeId]);
                
                $newCredentials['password'] = $tempPassword;
            }
            
            if ($resetPin) {
                $tempPin = $this->generateSecurePIN();
                
                $stmt = $this->conn->prepare("UPDATE tbl_employees SET CustomPIN = ?, PINSetupComplete = 0 WHERE EmployeeID = ?");
                $stmt->execute([$tempPin, $employeeId]);
                
                $hashedPin = password_hash($tempPin, PASSWORD_DEFAULT);
                $stmt = $this->conn->prepare("
                    INSERT INTO employee_pins (EmployeeID, pin, updated_at) 
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE pin = VALUES(pin), updated_at = NOW()
                ");
                $stmt->execute([$employeeId, $hashedPin]);
                
                $newCredentials['pin'] = $tempPin;
            }
            
            // Clear all active sessions
            $stmt = $this->conn->prepare("UPDATE tbl_authentication_sessions SET is_active = 0 WHERE employee_id = ?");
            $stmt->execute([$employeeId]);
            
            // Clear failed login attempts
            $this->clearFailedAttempts($employeeId, 'both');
            
            $this->conn->commit();
            
            return ['success' => true, 'new_credentials' => $newCredentials];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => 'Failed to reset credentials: ' . $e->getMessage()];
        }
    }
    
    // Private helper methods
    
    private function getEmployee(string $employeeId): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function isAccountLocked(string $employeeId, string $loginType): bool {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as failed_attempts
            FROM tbl_login_attempts 
            WHERE employee_id = ? 
            AND login_type = ? 
            AND success = 0 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$employeeId, $loginType, $this->config['lockout_duration']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['failed_attempts'] >= $this->config['max_login_attempts'];
    }
    
    private function logLoginAttempt(string $employeeId, string $loginType, bool $success, string $ipAddress = '', string $userAgent = '', string $failureReason = ''): void {
        $stmt = $this->conn->prepare("
            INSERT INTO tbl_login_attempts (employee_id, login_type, ip_address, user_agent, success, failure_reason)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$employeeId, $loginType, $ipAddress, $userAgent, $success ? 1 : 0, $failureReason]);
    }
    
    private function clearFailedAttempts(string $employeeId, string $loginType): void {
        if ($loginType === 'both') {
            $stmt = $this->conn->prepare("DELETE FROM tbl_login_attempts WHERE employee_id = ? AND success = 0");
            $stmt->execute([$employeeId]);
        } else {
            $stmt = $this->conn->prepare("DELETE FROM tbl_login_attempts WHERE employee_id = ? AND login_type = ? AND success = 0");
            $stmt->execute([$employeeId, $loginType]);
        }
    }
    
    private function generateSessionToken(): string {
        return bin2hex(random_bytes(32));
    }
    
    private function createSession(string $employeeId, string $sessionToken, string $sessionType, string $ipAddress): void {
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['session_timeout']);
        
        $stmt = $this->conn->prepare("
            INSERT INTO tbl_authentication_sessions (employee_id, session_token, session_type, ip_address, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$employeeId, $sessionToken, $sessionType, $ipAddress, $expiresAt]);
    }
    
    private function isValidPIN(string $pin): bool {
        return preg_match('/^\d{4}$/', $pin);
    }
    
    private function isValidPassword(string $password): bool {
        return strlen($password) >= $this->config['min_password_length'];
    }
    
    private function generateSecurePassword(): string {
        $chars = 'ABCDEFGHJKLMNOPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz023456789';
        $password = '';
        for ($i = 0; $i < 8; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    private function generateSecurePIN(): string {
        return str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Validate session token
     */
    public function validateSession(string $sessionToken): ?array {
        $stmt = $this->conn->prepare("
            SELECT s.*, e.FullName, e.Username
            FROM tbl_authentication_sessions s
            JOIN tbl_employees e ON s.employee_id = e.EmployeeID
            WHERE s.session_token = ? AND s.is_active = 1 AND s.expires_at > NOW()
        ");
        $stmt->execute([$sessionToken]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Logout (invalidate session)
     */
    public function logout(string $sessionToken): bool {
        $stmt = $this->conn->prepare("UPDATE tbl_authentication_sessions SET is_active = 0 WHERE session_token = ?");
        $stmt->execute([$sessionToken]);
        return $stmt->rowCount() > 0;
    }
}
?>
