-- WearOS Integration Migration
-- Date: 2025-09-12
-- Description: Update biometric tables for WearOS Android smartwatch integration

-- Update biometric_data table structure for WearOS compatibility
ALTER TABLE tbl_biometric_data 
ADD COLUMN IF NOT EXISTS stress_level_numeric DECIMAL(4,1) NULL COMMENT 'Numeric stress level (0.0-10.0)',
ADD COLUMN IF NOT EXISTS device_type VARCHAR(50) DEFAULT 'iot_wearable' COMMENT 'Type of device (iot_wearable, android_watch)',
ADD COLUMN IF NOT EXISTS data_source VARCHAR(50) DEFAULT 'wearable' COMMENT 'Source of the data (wearable, wearos_api)',
ADD COLUMN IF NOT EXISTS employee_id VARCHAR(15) NULL COMMENT 'Employee ID for WearOS compatibility',
ADD INDEX IF NOT EXISTS idx_employee_id_timestamp (employee_id, Timestamp),
ADD INDEX IF NOT EXISTS idx_stress_numeric (stress_level_numeric),
ADD INDEX IF NOT EXISTS idx_device_type (device_type);

-- Add foreign key for employee_id if it doesn't exist
SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_biometric_data' 
    AND CONSTRAINT_NAME = 'fk_biometric_employee_id');

SET @sql = IF(@constraint_exists = 0, 
    'ALTER TABLE tbl_biometric_data ADD CONSTRAINT fk_biometric_employee_id FOREIGN KEY (employee_id) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE', 
    'SELECT "Foreign key already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create biometric_data view for WearOS API compatibility
CREATE OR REPLACE VIEW biometric_data AS
SELECT 
    BiometricID as id,
    COALESCE(employee_id, EmployeeID) as employee_id,
    COALESCE(HeartRate, 0) as heart_rate,
    COALESCE(stress_level_numeric, 
        CASE StressLevel 
            WHEN 'low' THEN 2.0
            WHEN 'moderate' THEN 5.0
            WHEN 'high' THEN 8.0
            WHEN 'critical' THEN 10.0
            ELSE 0.0
        END
    ) as stress_level,
    SkinTemperature as skin_temperature,
    StepCount as step_count,
    Timestamp as timestamp,
    COALESCE(device_type, 'iot_wearable') as device_type,
    COALESCE(data_source, 'wearable') as data_source,
    Timestamp as created_at
FROM tbl_biometric_data;

-- Update biometric_alerts table for WearOS compatibility
ALTER TABLE tbl_biometric_alerts 
ADD COLUMN IF NOT EXISTS employee_id VARCHAR(15) NULL COMMENT 'Employee ID for WearOS compatibility',
ADD COLUMN IF NOT EXISTS heart_rate INT NULL COMMENT 'Heart rate at time of alert',
ADD COLUMN IF NOT EXISTS stress_level DECIMAL(4,1) NULL COMMENT 'Stress level at time of alert',
ADD COLUMN IF NOT EXISTS skin_temperature DECIMAL(5,2) NULL COMMENT 'Temperature at time of alert',
ADD COLUMN IF NOT EXISTS is_urgent BOOLEAN DEFAULT FALSE COMMENT 'Urgent alert flag',
ADD COLUMN IF NOT EXISTS timestamp TIMESTAMP NULL COMMENT 'Alert timestamp',
ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'ACTIVE' COMMENT 'Alert status',
ADD INDEX IF NOT EXISTS idx_employee_id_alerts (employee_id, CreatedAt),
ADD INDEX IF NOT EXISTS idx_urgent_alerts (is_urgent, status),
ADD INDEX IF NOT EXISTS idx_alert_timestamp (timestamp);

-- Add foreign key for employee_id in alerts if it doesn't exist
SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_biometric_alerts' 
    AND CONSTRAINT_NAME = 'fk_alerts_employee_id');

SET @sql = IF(@constraint_exists = 0, 
    'ALTER TABLE tbl_biometric_alerts ADD CONSTRAINT fk_alerts_employee_id FOREIGN KEY (employee_id) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE', 
    'SELECT "Foreign key already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create biometric_alerts view for WearOS API compatibility
CREATE OR REPLACE VIEW biometric_alerts AS
SELECT 
    AlertID as alert_id,
    COALESCE(employee_id, EmployeeID) as employee_id,
    AlertType as alert_type,
    Severity as severity,
    heart_rate,
    stress_level,
    skin_temperature,
    COALESCE(is_urgent, FALSE) as is_urgent,
    COALESCE(timestamp, CreatedAt) as timestamp,
    COALESCE(status, 'ACTIVE') as status,
    IsAcknowledged as is_acknowledged,
    AcknowledgedBy as acknowledged_by,
    AcknowledgedAt as acknowledged_at,
    CreatedAt as created_at
FROM tbl_biometric_alerts;

-- Create employee_activity table for tracking last activity
CREATE TABLE IF NOT EXISTS employee_activity (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(15) NOT NULL,
    activity_type VARCHAR(50) NOT NULL DEFAULT 'health_data',
    activity_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_activity (employee_id, activity_type),
    INDEX idx_activity_time (activity_time),
    INDEX idx_employee_activity (employee_id, activity_type)
);

-- Update tbl_employee_wearables for better WearOS compatibility
ALTER TABLE tbl_employee_wearables 
ADD COLUMN IF NOT EXISTS AssignmentID INT NULL COMMENT 'Assignment ID for API compatibility',
ADD COLUMN IF NOT EXISTS IsActive BOOLEAN DEFAULT TRUE COMMENT 'Active assignment status';

-- Update assignment ID to match WearableID for existing records
UPDATE tbl_employee_wearables SET AssignmentID = WearableID WHERE AssignmentID IS NULL;

-- Add index for faster lookups
ALTER TABLE tbl_employee_wearables 
ADD INDEX IF NOT EXISTS idx_employee_active (EmployeeID, IsActive),
ADD INDEX IF NOT EXISTS idx_device_active (DeviceID, IsActive);

-- Insert Android watch device types
INSERT IGNORE INTO tbl_devices (DeviceName, DeviceType, Description, IsActive) VALUES
('Android Smartwatch - Generic', 'android_watch', 'Generic Android Wear OS smartwatch with health sensors', 1),
('Samsung Galaxy Watch', 'android_watch', 'Samsung Galaxy Watch series with advanced health monitoring', 1),
('Fossil Gen 5', 'android_watch', 'Fossil Gen 5 Wear OS smartwatch', 1),
('TicWatch Pro', 'android_watch', 'Mobvoi TicWatch Pro with dual display', 1),
('Garmin Venu', 'android_watch', 'Garmin Venu series with health and fitness tracking', 1);

-- Update biometric thresholds for numeric stress levels
INSERT IGNORE INTO tbl_biometric_thresholds (MetricType, LowThreshold, MediumThreshold, HighThreshold, CriticalThreshold) VALUES
('stress_numeric', 3.0, 5.0, 7.0, 9.0) -- Numeric stress levels 0.0-10.0
ON DUPLICATE KEY UPDATE UpdatedAt = CURRENT_TIMESTAMP;

-- Create WearOS session tokens table for authentication
CREATE TABLE IF NOT EXISTS wearos_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(15) NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    device_id VARCHAR(100) NULL COMMENT 'Android device identifier',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE,
    UNIQUE KEY unique_session_token (session_token),
    INDEX idx_employee_sessions (employee_id, is_active),
    INDEX idx_session_expiry (expires_at, is_active)
);

-- Create WearOS device registry for Android watches
CREATE TABLE IF NOT EXISTS wearos_devices (
    device_registry_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(15) NOT NULL,
    device_id VARCHAR(100) NOT NULL COMMENT 'Android device identifier',
    device_name VARCHAR(255) NULL COMMENT 'User-friendly device name',
    device_model VARCHAR(100) NULL COMMENT 'Watch model',
    android_version VARCHAR(20) NULL COMMENT 'Android/Wear OS version',
    app_version VARCHAR(20) NULL COMMENT 'SignSync app version',
    last_sync TIMESTAMP NULL COMMENT 'Last successful data sync',
    is_registered BOOLEAN DEFAULT TRUE,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE,
    UNIQUE KEY unique_device_employee (device_id, employee_id),
    INDEX idx_employee_devices (employee_id, is_registered),
    INDEX idx_last_sync (last_sync)
);

-- Add trigger to update camera monitoring when stress alerts are created
DELIMITER //

CREATE TRIGGER IF NOT EXISTS trigger_stress_camera_monitoring
AFTER INSERT ON tbl_biometric_alerts
FOR EACH ROW
BEGIN
    -- Only trigger for high/critical stress alerts
    IF NEW.AlertType = 'stress' AND NEW.Severity IN ('high', 'critical') THEN
        -- Check if employee has assigned camera
        IF EXISTS (
            SELECT 1 FROM employee_camera_mapping ecm 
            WHERE ecm.employee_id = COALESCE(NEW.employee_id, NEW.EmployeeID) 
            AND ecm.is_active = 1
        ) THEN
            -- Insert camera trigger (the camera system will handle activation)
            INSERT IGNORE INTO camera_triggers (
                employee_id, trigger_type, heart_rate, stress_level, 
                trigger_source, created_at
            ) VALUES (
                COALESCE(NEW.employee_id, NEW.EmployeeID), 
                'automatic', 
                NEW.heart_rate, 
                NEW.stress_level, 
                'biometric_alert', 
                NOW()
            );
        END IF;
    END IF;
END//

DELIMITER ;

-- Insert sample WearOS configuration
INSERT IGNORE INTO system_config (config_key, config_value, description) VALUES
('wearos_api_enabled', '1', 'Enable WearOS API endpoint'),
('wearos_session_timeout', '7200', 'WearOS session timeout in seconds (2 hours)'),
('wearos_stress_threshold', '7.0', 'Stress level threshold for automatic alerts'),
('wearos_heart_rate_threshold', '100', 'Heart rate threshold for automatic alerts'),
('wearos_data_retention_days', '90', 'Days to retain WearOS health data'),
('wearos_alert_cooldown', '300', 'Minimum seconds between stress alerts for same employee');

-- Create system_config table if it doesn't exist
CREATE TABLE IF NOT EXISTS system_config (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
);

-- Update existing devices to include Android watch capability
UPDATE tbl_devices 
SET DeviceType = 'android_watch' 
WHERE DeviceName LIKE '%watch%' 
AND DeviceType IN ('other', 'iot');

COMMIT;
