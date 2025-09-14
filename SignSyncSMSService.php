<?php
/**
 * SIGNSYNC SMS Service - Comprehensive SMS Management System
 * 
 * This class provides a centralized SMS service that wraps the SMSOnlineGH API
 * with additional features including templates, queuing, logging, and secure configuration.
 * 
 * Features:
 * - Secure API key management
 * - Template-based messaging
 * - SMS queue and batch sending
 * - Delivery tracking and logging
 * - Phone number validation and formatting
 * - Rate limiting and error handling
 * - Multiple provider support (extensible)
 * 
 * @author SIGNSYNC Development Team
 * @version 1.0.0
 * @since 2025-09-13
 */

class SignSyncSMSService {
    private $pdo;
    private $config;
    private $templates;
    private $defaultProvider = 'smsonlinegh';
    
    // SMS status constants
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_QUEUED = 'queued';
    
    // SMS priority levels
    const PRIORITY_LOW = 1;
    const PRIORITY_NORMAL = 2;
    const PRIORITY_HIGH = 3;
    const PRIORITY_URGENT = 4;
    
    // Rate limiting constants
    const RATE_LIMIT_WINDOW = 60; // seconds
    const RATE_LIMIT_MAX = 100; // max SMS per window
    
    public function __construct($pdo, $config = []) {
        $this->pdo = $pdo;
        $this->loadConfig($config);
        $this->loadTemplates();
        $this->initializeDatabase();
    }
    
    /**
     * Load SMS configuration from database and environment
     */
    private function loadConfig($overrides = []) {
        // Default configuration
        $defaults = [
            'smsonlinegh' => [
                'api_key' => $this->getSecureConfig('SMS_API_KEY', 'aefc1848ebc7baaa90e71bfb6072287cc2cc197882e73631a1bdc27135a51abb'),
                'sender_id' => $this->getSecureConfig('SMS_SENDER_ID', 'SIGNSYNC'),
                'endpoint' => 'https://api.smsonlinegh.com/v5/message/sms/send',
                'enabled' => true
            ],
            'rate_limiting' => [
                'enabled' => true,
                'max_per_window' => self::RATE_LIMIT_MAX,
                'window_seconds' => self::RATE_LIMIT_WINDOW
            ],
            'queue' => [
                'enabled' => true,
                'batch_size' => 10,
                'retry_attempts' => 3,
                'retry_delay' => 300 // 5 minutes
            ],
            'logging' => [
                'enabled' => true,
                'log_level' => 'info',
                'retention_days' => 90
            ]
        ];
        
        // Load from database
        try {
            $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM tbl_sms_config");
            $stmt->execute();
            $dbConfig = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dbConfig[$row['setting_key']] = json_decode($row['setting_value'], true) ?: $row['setting_value'];
            }
            $defaults = array_merge_recursive($defaults, $dbConfig);
        } catch (Exception $e) {
            error_log("SMS Config load warning: " . $e->getMessage());
        }
        
        $this->config = array_merge_recursive($defaults, $overrides);
    }
    
    /**
     * Get secure configuration value from environment or fallback
     */
    private function getSecureConfig($envVar, $fallback) {
        // Try environment variable first
        $value = getenv($envVar);
        if ($value !== false) {
            return $value;
        }
        
        // Try $_ENV superglobal
        if (isset($_ENV[$envVar])) {
            return $_ENV[$envVar];
        }
        
        // Use fallback
        return $fallback;
    }
    
    /**
     * Load SMS templates from database
     */
    private function loadTemplates() {
        $this->templates = [
            'attendance_clockin' => 'Hi {name}, your clock-in at {branch} was successful at {time}. Status: {status}. Have a productive day!',
            'attendance_clockout' => 'Hi {name}, your clock-out at {branch} was successful at {time}. Status: {status}. Have a great rest of your day!',
            'late_arrival' => 'Hi {name}, our records show you arrived late today at {time}. Please ensure punctuality. Contact HR if needed.',
            'missed_clockout' => 'Hi {name}, our records show you didn\'t clock out today. Please contact HR to correct this.',
            'pin_reset' => 'Hi {name}, your SIGNSYNC PIN has been reset. Your new temporary PIN is: {pin}. Please change it after login.',
            'pin_setup' => 'Welcome to SIGNSYNC! Your Employee ID is {employee_id}. Use PIN "1234" for first login, then create your custom PIN.',
            'pin_changed' => 'Hi {name}, your SIGNSYNC PIN has been successfully changed. If you didn\'t make this change, contact admin immediately.',
            'stress_alert' => 'STRESS ALERT: {name} ({employee_id}) in {department} - HR: {heart_rate}bpm, Stress: {stress_level}. Immediate attention required.',
            'emergency_alert' => 'EMERGENCY: {message}. Employee: {name} ({employee_id}). Location: {location}. Time: {time}.',
            'shift_reminder' => 'Hi {name}, reminder: Your shift at {branch} starts at {shift_start} today. Please arrive on time.',
            'leave_approved' => 'Hi {name}, your leave request from {start_date} to {end_date} has been approved. Enjoy your time off!',
            'leave_rejected' => 'Hi {name}, your leave request from {start_date} to {end_date} has been rejected. Reason: {reason}. Contact HR for details.'
        ];
        
        // Load custom templates from database
        try {
            $stmt = $this->pdo->prepare("SELECT template_name, template_content FROM tbl_sms_templates WHERE is_active = 1");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->templates[$row['template_name']] = $row['template_content'];
            }
        } catch (Exception $e) {
            error_log("SMS Templates load warning: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize database tables for SMS service
     */
    private function initializeDatabase() {
        $tables = [
            'tbl_sms_queue' => "
                CREATE TABLE IF NOT EXISTS tbl_sms_queue (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    phone_number VARCHAR(20) NOT NULL,
                    message TEXT NOT NULL,
                    template_name VARCHAR(100) NULL,
                    template_data JSON NULL,
                    priority TINYINT DEFAULT 2,
                    status ENUM('pending', 'sent', 'delivered', 'failed', 'queued') DEFAULT 'pending',
                    provider VARCHAR(50) DEFAULT 'smsonlinegh',
                    attempts INT DEFAULT 0,
                    max_attempts INT DEFAULT 3,
                    error_message TEXT NULL,
                    scheduled_at TIMESTAMP NULL,
                    sent_at TIMESTAMP NULL,
                    delivered_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_priority (priority),
                    INDEX idx_scheduled (scheduled_at),
                    INDEX idx_phone (phone_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tbl_sms_logs' => "
                CREATE TABLE IF NOT EXISTS tbl_sms_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    queue_id INT NULL,
                    phone_number VARCHAR(20) NOT NULL,
                    message TEXT NOT NULL,
                    provider VARCHAR(50) NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    response_data JSON NULL,
                    delivery_status VARCHAR(50) NULL,
                    message_id VARCHAR(100) NULL,
                    cost DECIMAL(10,4) NULL,
                    employee_id VARCHAR(50) NULL,
                    sent_at TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (queue_id) REFERENCES tbl_sms_queue(id) ON DELETE SET NULL,
                    INDEX idx_phone (phone_number),
                    INDEX idx_status (status),
                    INDEX idx_employee (employee_id),
                    INDEX idx_sent_at (sent_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tbl_sms_config' => "
                CREATE TABLE IF NOT EXISTS tbl_sms_config (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT NOT NULL,
                    description TEXT NULL,
                    is_secure BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_key (setting_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tbl_sms_templates' => "
                CREATE TABLE IF NOT EXISTS tbl_sms_templates (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    template_name VARCHAR(100) UNIQUE NOT NULL,
                    template_content TEXT NOT NULL,
                    description TEXT NULL,
                    variables JSON NULL,
                    category VARCHAR(50) DEFAULT 'general',
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_name (template_name),
                    INDEX idx_category (category),
                    INDEX idx_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tbl_sms_rate_limits' => "
                CREATE TABLE IF NOT EXISTS tbl_sms_rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    identifier VARCHAR(100) NOT NULL,
                    request_count INT DEFAULT 1,
                    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_identifier (identifier),
                    INDEX idx_window (window_start)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        ];
        
        foreach ($tables as $tableName => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (Exception $e) {
                error_log("SMS Table creation warning for $tableName: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Send SMS using template
     */
    public function sendTemplateMessage($templateName, $phoneNumber, $data = [], $priority = self::PRIORITY_NORMAL, $scheduled = null) {
        if (!isset($this->templates[$templateName])) {
            throw new Exception("SMS template '$templateName' not found");
        }
        
        $message = $this->renderTemplate($templateName, $data);
        return $this->sendMessage($phoneNumber, $message, $priority, $scheduled, $templateName, $data);
    }
    
    /**
     * Send direct SMS message
     */
    public function sendMessage($phoneNumber, $message, $priority = self::PRIORITY_NORMAL, $scheduled = null, $templateName = null, $templateData = null) {
        // Validate and format phone number
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        if (!$this->isValidPhoneNumber($phoneNumber)) {
            throw new Exception("Invalid phone number: $phoneNumber");
        }
        
        // Check rate limiting
        if ($this->config['rate_limiting']['enabled'] && !$this->checkRateLimit($phoneNumber)) {
            throw new Exception("Rate limit exceeded for phone number: $phoneNumber");
        }
        
        // Add to queue or send immediately
        if ($this->config['queue']['enabled'] || $scheduled) {
            return $this->queueMessage($phoneNumber, $message, $priority, $scheduled, $templateName, $templateData);
        } else {
            return $this->sendImmediate($phoneNumber, $message, $templateName, $templateData);
        }
    }
    
    /**
     * Send bulk SMS messages
     */
    public function sendBulkMessage($phoneNumbers, $message, $templateName = null, $templateData = null) {
        $results = [];
        $batchSize = $this->config['queue']['batch_size'];
        $batches = array_chunk($phoneNumbers, $batchSize);
        
        foreach ($batches as $batch) {
            foreach ($batch as $phoneNumber) {
                try {
                    $result = $this->sendMessage($phoneNumber, $message, self::PRIORITY_NORMAL, null, $templateName, $templateData);
                    $results[$phoneNumber] = ['success' => true, 'result' => $result];
                } catch (Exception $e) {
                    $results[$phoneNumber] = ['success' => false, 'error' => $e->getMessage()];
                }
            }
            
            // Small delay between batches to avoid overwhelming the API
            if (count($batches) > 1) {
                usleep(100000); // 100ms
            }
        }
        
        return $results;
    }
    
    /**
     * Queue SMS message for later sending
     */
    private function queueMessage($phoneNumber, $message, $priority, $scheduled, $templateName, $templateData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_sms_queue 
            (phone_number, message, template_name, template_data, priority, scheduled_at, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $status = $scheduled ? 'queued' : 'pending';
        $templateDataJson = $templateData ? json_encode($templateData) : null;
        
        $stmt->execute([
            $phoneNumber, 
            $message, 
            $templateName, 
            $templateDataJson, 
            $priority, 
            $scheduled, 
            $status
        ]);
        
        $queueId = $this->pdo->lastInsertId();
        
        // If not scheduled, process immediately
        if (!$scheduled) {
            $this->processQueue(1);
        }
        
        return $queueId;
    }
    
    /**
     * Send SMS immediately without queueing
     */
    private function sendImmediate($phoneNumber, $message, $templateName = null, $templateData = null) {
        $provider = $this->defaultProvider;
        
        try {
            $result = $this->sendViaSMSOnlineGH($phoneNumber, $message);
            
            // Log the result
            $this->logSMS(null, $phoneNumber, $message, $provider, 
                         $result['success'] ? 'sent' : 'failed', 
                         $result, null, $result['message_id'] ?? null);
            
            return $result;
            
        } catch (Exception $e) {
            // Log the error
            $this->logSMS(null, $phoneNumber, $message, $provider, 'failed', 
                         ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Send SMS via SMSOnlineGH API (enhanced version of existing function)
     */
    private function sendViaSMSOnlineGH($phoneNumber, $message) {
        $config = $this->config['smsonlinegh'];
        
        if (!$config['enabled']) {
            throw new Exception("SMSOnlineGH provider is disabled");
        }
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: key ' . $config['api_key']
        ];
        
        $messageData = [
            'text' => $message,
            'type' => 0, // GSM default
            'sender' => $config['sender_id'],
            'destinations' => [$phoneNumber]
        ];
        
        $ch = curl_init($config['endpoint']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // SSL configuration
        $caCertPath = __DIR__ . "/cacert.pem";
        if (file_exists($caCertPath)) {
            curl_setopt($ch, CURLOPT_CAINFO, $caCertPath);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error: $error");
        }
        
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        $result = [
            'success' => false,
            'http_code' => $httpCode,
            'response' => $responseData,
            'raw_response' => $response
        ];
        
        // Check success conditions
        if ($httpCode == 200 && isset($responseData['handshake']) &&
            $responseData['handshake']['id'] === 0 && 
            $responseData['handshake']['label'] === 'HSHK_OK') {
            
            $result['success'] = true;
            
            // Extract message ID and delivery status
            if (isset($responseData['data']['destinations'][0])) {
                $dest = $responseData['data']['destinations'][0];
                $result['message_id'] = $dest['id'] ?? null;
                $result['delivery_status'] = $dest['status']['label'] ?? null;
                $result['cost'] = $dest['cost'] ?? null;
            }
        } else {
            $errorMsg = "SMS API failed. HTTP: $httpCode";
            if (isset($responseData['handshake'])) {
                $errorMsg .= ", Handshake: {$responseData['handshake']['label']}";
            }
            if (isset($responseData['data']['message'])) {
                $errorMsg .= ", Message: {$responseData['data']['message']}";
            }
            $result['error'] = $errorMsg;
        }
        
        return $result;
    }
    
    /**
     * Process SMS queue
     */
    public function processQueue($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tbl_sms_queue 
            WHERE status IN ('pending', 'failed') 
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            AND attempts < max_attempts
            ORDER BY priority DESC, created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        $processed = 0;
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try {
                // Update attempts
                $this->updateQueueItem($item['id'], ['attempts' => $item['attempts'] + 1]);
                
                $result = $this->sendViaSMSOnlineGH($item['phone_number'], $item['message']);
                
                if ($result['success']) {
                    $this->updateQueueItem($item['id'], [
                        'status' => 'sent',
                        'sent_at' => date('Y-m-d H:i:s'),
                        'error_message' => null
                    ]);
                } else {
                    $status = ($item['attempts'] + 1 >= $item['max_attempts']) ? 'failed' : 'pending';
                    $this->updateQueueItem($item['id'], [
                        'status' => $status,
                        'error_message' => $result['error'] ?? 'Unknown error'
                    ]);
                }
                
                // Log the result
                $this->logSMS($item['id'], $item['phone_number'], $item['message'], 
                             $item['provider'], $result['success'] ? 'sent' : 'failed', 
                             $result, null, $result['message_id'] ?? null);
                
                $processed++;
                
            } catch (Exception $e) {
                $this->updateQueueItem($item['id'], [
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
                
                $this->logSMS($item['id'], $item['phone_number'], $item['message'], 
                             $item['provider'], 'failed', ['error' => $e->getMessage()]);
            }
            
            // Small delay between sends
            usleep(100000); // 100ms
        }
        
        return $processed;
    }
    
    /**
     * Update queue item
     */
    private function updateQueueItem($id, $data) {
        $sets = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $sets[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        
        $sql = "UPDATE tbl_sms_queue SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }
    
    /**
     * Log SMS activity
     */
    private function logSMS($queueId, $phoneNumber, $message, $provider, $status, $responseData, $employeeId = null, $messageId = null) {
        if (!$this->config['logging']['enabled']) {
            return;
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO tbl_sms_logs 
            (queue_id, phone_number, message, provider, status, response_data, message_id, employee_id, sent_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $queueId,
            $phoneNumber,
            $message,
            $provider,
            $status,
            json_encode($responseData),
            $messageId,
            $employeeId
        ]);
    }
    
    /**
     * Render template with data
     */
    private function renderTemplate($templateName, $data) {
        $template = $this->templates[$templateName];
        
        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $template = str_replace($placeholder, $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Format phone number for Ghana
     */
    private function formatPhoneNumber($phoneNumber) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Handle Ghanaian numbers
        if (preg_match('/^0(\d{9})$/', $phone, $matches)) {
            // Convert 0XXXXXXXXX to 233XXXXXXXXX
            return '233' . $matches[1];
        } elseif (preg_match('/^233(\d{9})$/', $phone)) {
            // Already in correct format
            return $phone;
        }
        
        // Return as-is for other international formats
        return $phone;
    }
    
    /**
     * Validate phone number
     */
    private function isValidPhoneNumber($phoneNumber) {
        // Basic validation for international format
        return preg_match('/^\d{10,15}$/', $phoneNumber);
    }
    
    /**
     * Check rate limiting
     */
    private function checkRateLimit($identifier) {
        if (!$this->config['rate_limiting']['enabled']) {
            return true;
        }
        
        $windowStart = date('Y-m-d H:i:s', time() - $this->config['rate_limiting']['window_seconds']);
        
        // Clean old records
        $this->pdo->prepare("DELETE FROM tbl_sms_rate_limits WHERE window_start < ?")->execute([$windowStart]);
        
        // Check current count
        $stmt = $this->pdo->prepare("
            SELECT request_count FROM tbl_sms_rate_limits 
            WHERE identifier = ? AND window_start > ?
        ");
        $stmt->execute([$identifier, $windowStart]);
        $currentCount = $stmt->fetchColumn() ?: 0;
        
        if ($currentCount >= $this->config['rate_limiting']['max_per_window']) {
            return false;
        }
        
        // Update count
        $this->pdo->prepare("
            INSERT INTO tbl_sms_rate_limits (identifier, request_count, window_start) 
            VALUES (?, 1, NOW()) 
            ON DUPLICATE KEY UPDATE 
            request_count = request_count + 1, 
            updated_at = NOW()
        ")->execute([$identifier]);
        
        return true;
    }
    
    /**
     * Get SMS statistics
     */
    public function getStatistics($timeframe = '24h') {
        $where = '';
        $params = [];
        
        switch ($timeframe) {
            case '1h':
                $where = 'WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)';
                break;
            case '24h':
                $where = 'WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)';
                break;
            case '7d':
                $where = 'WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case '30d':
                $where = 'WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                SUM(CASE WHEN cost IS NOT NULL THEN cost ELSE 0 END) as total_cost
            FROM tbl_sms_logs 
            $where
            GROUP BY status
        ");
        $stmt->execute($params);
        
        $stats = [
            'sent' => 0,
            'failed' => 0,
            'delivered' => 0,
            'total_cost' => 0
        ];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = $row['count'];
            $stats['total_cost'] += $row['total_cost'];
        }
        
        $stats['total'] = array_sum([$stats['sent'], $stats['failed'], $stats['delivered']]);
        $stats['success_rate'] = $stats['total'] > 0 ? round(($stats['sent'] / $stats['total']) * 100, 2) : 0;
        
        return $stats;
    }
    
    /**
     * Get delivery report
     */
    public function getDeliveryReport($phoneNumber = null, $startDate = null, $endDate = null, $limit = 50) {
        $where = ['1=1'];
        $params = [];
        
        if ($phoneNumber) {
            $where[] = 'phone_number = ?';
            $params[] = $phoneNumber;
        }
        
        if ($startDate) {
            $where[] = 'sent_at >= ?';
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $where[] = 'sent_at <= ?';
            $params[] = $endDate;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $this->pdo->prepare("
            SELECT id, phone_number, message, status, delivery_status, 
                   message_id, cost, employee_id, sent_at
            FROM tbl_sms_logs 
            WHERE $whereClause
            ORDER BY sent_at DESC
            LIMIT ?
        ");
        
        $params[] = $limit;
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Clean old logs and queue items
     */
    public function cleanup() {
        $retentionDays = $this->config['logging']['retention_days'];
        $cutoffDate = date('Y-m-d H:i:s', time() - ($retentionDays * 24 * 60 * 60));
        
        // Clean old logs
        $stmt = $this->pdo->prepare("DELETE FROM tbl_sms_logs WHERE sent_at < ?");
        $deletedLogs = $stmt->execute([$cutoffDate]) ? $stmt->rowCount() : 0;
        
        // Clean old completed queue items
        $stmt = $this->pdo->prepare("DELETE FROM tbl_sms_queue WHERE status IN ('sent', 'delivered') AND updated_at < ?");
        $deletedQueue = $stmt->execute([$cutoffDate]) ? $stmt->rowCount() : 0;
        
        return [
            'deleted_logs' => $deletedLogs,
            'deleted_queue' => $deletedQueue
        ];
    }
}
