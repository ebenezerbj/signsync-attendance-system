<?php
/**
 * SMS Configuration Management
 * 
 * This file provides secure configuration management for the SMS service.
 * It includes functions to manage API keys, templates, and service settings.
 */

// Load SMS service configuration from environment and database
function loadSMSConfig($pdo) {
    $config = [
        'providers' => [
            'smsonlinegh' => [
                'name' => 'SMS Online GH',
                'enabled' => true,
                'api_key' => getenv('SMS_SMSONLINEGH_API_KEY') ?: 'aefc1848ebc7baaa90e71bfb6072287cc2cc197882e73631a1bdc27135a51abb',
                'sender_id' => getenv('SMS_SENDER_ID') ?: 'SIGNSYNC',
                'endpoint' => 'https://api.smsonlinegh.com/v5/message/sms/send',
                'rate_limit' => 100,
                'cost_per_sms' => 0.02
            ]
        ],
        'settings' => [
            'default_provider' => 'smsonlinegh',
            'queue_enabled' => true,
            'logging_enabled' => true,
            'rate_limiting_enabled' => true,
            'max_message_length' => 160,
            'retry_attempts' => 3,
            'retry_delay' => 300,
            'batch_size' => 10,
            'log_retention_days' => 90
        ]
    ];
    
    // Load additional settings from database
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM tbl_sms_config");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $keys = explode('.', $row['setting_key']);
            $current = &$config;
            foreach ($keys as $key) {
                if (!isset($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
            $current = json_decode($row['setting_value'], true) ?: $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Failed to load SMS config from database: " . $e->getMessage());
    }
    
    return $config;
}

// Initialize SMS configuration in database
function initializeSMSConfig($pdo) {
    $defaultSettings = [
        'providers.smsonlinegh.enabled' => 'true',
        'providers.smsonlinegh.sender_id' => 'SIGNSYNC',
        'settings.queue_enabled' => 'true',
        'settings.logging_enabled' => 'true',
        'settings.rate_limiting_enabled' => 'true',
        'settings.max_message_length' => '160',
        'settings.retry_attempts' => '3',
        'settings.retry_delay' => '300',
        'settings.batch_size' => '10',
        'settings.log_retention_days' => '90'
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO tbl_sms_config (setting_key, setting_value, description) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($defaultSettings as $key => $value) {
        $description = getSMSConfigDescription($key);
        $stmt->execute([$key, $value, $description]);
    }
}

// Get configuration description
function getSMSConfigDescription($key) {
    $descriptions = [
        'providers.smsonlinegh.enabled' => 'Enable/disable SMSOnlineGH provider',
        'providers.smsonlinegh.sender_id' => 'Sender ID for SMSOnlineGH messages',
        'settings.queue_enabled' => 'Enable SMS queue for batch processing',
        'settings.logging_enabled' => 'Enable SMS activity logging',
        'settings.rate_limiting_enabled' => 'Enable rate limiting for SMS sending',
        'settings.max_message_length' => 'Maximum SMS message length',
        'settings.retry_attempts' => 'Number of retry attempts for failed SMS',
        'settings.retry_delay' => 'Delay between retry attempts (seconds)',
        'settings.batch_size' => 'Number of SMS to process in each batch',
        'settings.log_retention_days' => 'Number of days to retain SMS logs'
    ];
    
    return $descriptions[$key] ?? '';
}

// Update SMS configuration
function updateSMSConfig($pdo, $key, $value, $description = null) {
    $stmt = $pdo->prepare("
        INSERT INTO tbl_sms_config (setting_key, setting_value, description) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        setting_value = VALUES(setting_value),
        description = COALESCE(VALUES(description), description),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    return $stmt->execute([$key, $value, $description]);
}

// Get SMS configuration value
function getSMSConfigValue($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM tbl_sms_config WHERE setting_key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    
    if ($value !== false) {
        // Try to decode JSON, return as-is if not JSON
        $decoded = json_decode($value, true);
        return $decoded !== null ? $decoded : $value;
    }
    
    return $default;
}

// Create SMS service instance with configuration
function createSMSService($pdo) {
    $config = loadSMSConfig($pdo);
    return new SignSyncSMSService($pdo, $config);
}

// Initialize all SMS-related database tables and configuration
function initializeSMSSystem($pdo) {
    try {
        // Initialize configuration
        initializeSMSConfig($pdo);
        
        // Create SMS service instance to initialize tables
        $smsService = createSMSService($pdo);
        
        // Insert default templates
        initializeSMSTemplates($pdo);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to initialize SMS system: " . $e->getMessage());
        return false;
    }
}

// Initialize default SMS templates
function initializeSMSTemplates($pdo) {
    $templates = [
        [
            'name' => 'attendance_clockin',
            'content' => 'Hi {name}, your clock-in at {branch} was successful at {time}. Status: {status}. Have a productive day!',
            'description' => 'Notification sent when employee clocks in',
            'category' => 'attendance',
            'variables' => '["name", "branch", "time", "status"]'
        ],
        [
            'name' => 'attendance_clockout',
            'content' => 'Hi {name}, your clock-out at {branch} was successful at {time}. Status: {status}. Have a great rest of your day!',
            'description' => 'Notification sent when employee clocks out',
            'category' => 'attendance',
            'variables' => '["name", "branch", "time", "status"]'
        ],
        [
            'name' => 'late_arrival',
            'content' => 'Hi {name}, our records show you arrived late today at {time}. Please ensure punctuality. Contact HR if needed.',
            'description' => 'Notification for late arrivals',
            'category' => 'attendance',
            'variables' => '["name", "time"]'
        ],
        [
            'name' => 'missed_clockout',
            'content' => 'Hi {name}, our records show you didn\'t clock out today. Please contact HR to correct this.',
            'description' => 'Notification for missed clock-out',
            'category' => 'attendance',
            'variables' => '["name"]'
        ],
        [
            'name' => 'pin_reset',
            'content' => 'Hi {name}, your SIGNSYNC PIN has been reset. Your new temporary PIN is: {pin}. Please change it after login.',
            'description' => 'PIN reset notification',
            'category' => 'security',
            'variables' => '["name", "pin"]'
        ],
        [
            'name' => 'pin_setup',
            'content' => 'Welcome to SIGNSYNC! Your Employee ID is {employee_id}. Use PIN "1234" for first login, then create your custom PIN.',
            'description' => 'New employee PIN setup instructions',
            'category' => 'security',
            'variables' => '["employee_id"]'
        ],
        [
            'name' => 'pin_changed',
            'content' => 'Hi {name}, your SIGNSYNC PIN has been successfully changed. If you didn\'t make this change, contact admin immediately.',
            'description' => 'PIN change confirmation',
            'category' => 'security',
            'variables' => '["name"]'
        ],
        [
            'name' => 'stress_alert',
            'content' => 'STRESS ALERT: {name} ({employee_id}) in {department} - HR: {heart_rate}bpm, Stress: {stress_level}. Immediate attention required.',
            'description' => 'High stress level alert for supervisors',
            'category' => 'health',
            'variables' => '["name", "employee_id", "department", "heart_rate", "stress_level"]'
        ],
        [
            'name' => 'emergency_alert',
            'content' => 'EMERGENCY: {message}. Employee: {name} ({employee_id}). Location: {location}. Time: {time}.',
            'description' => 'Emergency alert notification',
            'category' => 'emergency',
            'variables' => '["message", "name", "employee_id", "location", "time"]'
        ],
        [
            'name' => 'shift_reminder',
            'content' => 'Hi {name}, reminder: Your shift at {branch} starts at {shift_start} today. Please arrive on time.',
            'description' => 'Shift start reminder',
            'category' => 'scheduling',
            'variables' => '["name", "branch", "shift_start"]'
        ],
        [
            'name' => 'leave_approved',
            'content' => 'Hi {name}, your leave request from {start_date} to {end_date} has been approved. Enjoy your time off!',
            'description' => 'Leave request approval notification',
            'category' => 'leave',
            'variables' => '["name", "start_date", "end_date"]'
        ],
        [
            'name' => 'leave_rejected',
            'content' => 'Hi {name}, your leave request from {start_date} to {end_date} has been rejected. Reason: {reason}. Contact HR for details.',
            'description' => 'Leave request rejection notification',
            'category' => 'leave',
            'variables' => '["name", "start_date", "end_date", "reason"]'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO tbl_sms_templates 
        (template_name, template_content, description, category, variables) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($templates as $template) {
        $stmt->execute([
            $template['name'],
            $template['content'],
            $template['description'],
            $template['category'],
            $template['variables']
        ]);
    }
}

// Environment variable setup helper
function setupSMSEnvironment() {
    $envVars = [
        'SMS_SMSONLINEGH_API_KEY' => 'Your SMSOnlineGH API key',
        'SMS_SENDER_ID' => 'Your SMS sender ID (e.g., SIGNSYNC)',
        'SMS_ENVIRONMENT' => 'production or development',
        'SMS_DEBUG' => 'true or false'
    ];
    
    $envFile = __DIR__ . '/.env.sms';
    
    if (!file_exists($envFile)) {
        $content = "# SMS Service Environment Variables\n";
        $content .= "# Copy this file to .env and update with your actual values\n\n";
        
        foreach ($envVars as $var => $description) {
            $content .= "# $description\n";
            $content .= "$var=\n\n";
        }
        
        file_put_contents($envFile, $content);
        
        return [
            'created' => true,
            'file' => $envFile,
            'message' => 'Environment template created. Please update with your SMS provider credentials.'
        ];
    }
    
    return [
        'created' => false,
        'file' => $envFile,
        'message' => 'Environment file already exists.'
    ];
}

// Load environment variables from .env file
function loadSMSEnvironment($envFile = null) {
    $envFile = $envFile ?: __DIR__ . '/.env';
    
    if (!file_exists($envFile)) {
        return false;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue; // Skip comments
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^["\'](.*)["\']/s', $value, $matches)) {
                $value = $matches[1];
            }
            
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
    
    return true;
}

// Validate SMS configuration
function validateSMSConfig($config) {
    $errors = [];
    
    // Check provider configuration
    if (!isset($config['providers']) || empty($config['providers'])) {
        $errors[] = 'No SMS providers configured';
    } else {
        foreach ($config['providers'] as $name => $provider) {
            if (empty($provider['api_key'])) {
                $errors[] = "Missing API key for provider: $name";
            }
            if (empty($provider['sender_id'])) {
                $errors[] = "Missing sender ID for provider: $name";
            }
            if (empty($provider['endpoint'])) {
                $errors[] = "Missing endpoint for provider: $name";
            }
        }
    }
    
    // Check settings
    if (!isset($config['settings'])) {
        $errors[] = 'Missing SMS settings configuration';
    } else {
        $settings = $config['settings'];
        
        if (!isset($settings['default_provider'])) {
            $errors[] = 'Default provider not specified';
        }
        
        if (isset($settings['max_message_length']) && $settings['max_message_length'] < 1) {
            $errors[] = 'Invalid max message length';
        }
        
        if (isset($settings['retry_attempts']) && $settings['retry_attempts'] < 0) {
            $errors[] = 'Invalid retry attempts value';
        }
    }
    
    return $errors;
}

// Get SMS service health status
function getSMSServiceHealth($pdo) {
    $health = [
        'status' => 'healthy',
        'checks' => [],
        'statistics' => []
    ];
    
    try {
        // Check database connectivity
        $pdo->query("SELECT 1");
        $health['checks']['database'] = 'OK';
    } catch (Exception $e) {
        $health['checks']['database'] = 'FAILED: ' . $e->getMessage();
        $health['status'] = 'unhealthy';
    }
    
    // Check required tables
    $requiredTables = ['tbl_sms_queue', 'tbl_sms_logs', 'tbl_sms_config', 'tbl_sms_templates'];
    foreach ($requiredTables as $table) {
        try {
            $pdo->query("SELECT 1 FROM $table LIMIT 1");
            $health['checks'][$table] = 'OK';
        } catch (Exception $e) {
            $health['checks'][$table] = 'MISSING';
            $health['status'] = 'unhealthy';
        }
    }
    
    // Check configuration
    $config = loadSMSConfig($pdo);
    $configErrors = validateSMSConfig($config);
    if (empty($configErrors)) {
        $health['checks']['configuration'] = 'OK';
    } else {
        $health['checks']['configuration'] = 'INVALID: ' . implode(', ', $configErrors);
        $health['status'] = 'unhealthy';
    }
    
    // Get statistics
    try {
        $smsService = createSMSService($pdo);
        $health['statistics'] = $smsService->getStatistics('24h');
    } catch (Exception $e) {
        $health['statistics'] = 'Failed to load statistics';
    }
    
    return $health;
}
?>
