-- CCTV-IoT Stress Monitoring Integration Tables
-- Adds camera integration capabilities to the biometric monitoring system

-- Table to track camera viewing sessions during stress alerts
CREATE TABLE IF NOT EXISTS tbl_camera_sessions (
    SessionID INT AUTO_INCREMENT PRIMARY KEY,
    EmployeeID VARCHAR(15) NOT NULL,
    CameraID INT NOT NULL,
    AlertID INT,
    SessionToken VARCHAR(64) UNIQUE NOT NULL,
    StartTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ExpiresAt TIMESTAMP,
    IsActive BOOLEAN DEFAULT 1,
    ViewerUserID VARCHAR(15),
    
    FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID),
    FOREIGN KEY (CameraID) REFERENCES tbl_devices(DeviceID),
    FOREIGN KEY (AlertID) REFERENCES tbl_biometric_alerts(AlertID),
    
    INDEX idx_session_token (SessionToken),
    INDEX idx_employee_camera (EmployeeID, CameraID),
    INDEX idx_active_sessions (IsActive, ExpiresAt)
);

-- Table to log camera triggers for stress events
CREATE TABLE IF NOT EXISTS tbl_camera_triggers (
    TriggerID INT AUTO_INCREMENT PRIMARY KEY,
    EmployeeID VARCHAR(15) NOT NULL,
    AlertID INT,
    TriggerTime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    StressLevel ENUM('high', 'critical') NOT NULL,
    CamerasActivated INT DEFAULT 0,
    Status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    Duration INT, -- Minutes of monitoring
    Notes TEXT,
    
    FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID),
    FOREIGN KEY (AlertID) REFERENCES tbl_biometric_alerts(AlertID),
    
    INDEX idx_employee_triggers (EmployeeID, TriggerTime),
    INDEX idx_alert_triggers (AlertID),
    INDEX idx_status_time (Status, TriggerTime)
);

-- Table to map employees to nearby cameras based on location/department
CREATE TABLE IF NOT EXISTS tbl_employee_camera_mapping (
    MappingID INT AUTO_INCREMENT PRIMARY KEY,
    EmployeeID VARCHAR(15) NOT NULL,
    CameraID INT NOT NULL,
    ProximityScore INT DEFAULT 5, -- 1-10 scale for camera relevance
    IsPreferred BOOLEAN DEFAULT 0,
    MappingReason VARCHAR(100), -- 'department', 'location', 'manual'
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    IsActive BOOLEAN DEFAULT 1,
    
    FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID),
    FOREIGN KEY (CameraID) REFERENCES tbl_devices(DeviceID),
    
    UNIQUE KEY unique_employee_camera (EmployeeID, CameraID),
    INDEX idx_employee_mapping (EmployeeID, IsActive),
    INDEX idx_camera_mapping (CameraID, IsActive),
    INDEX idx_proximity (ProximityScore DESC)
);

-- Table to store camera metadata and streaming configuration
CREATE TABLE IF NOT EXISTS tbl_camera_config (
    ConfigID INT AUTO_INCREMENT PRIMARY KEY,
    CameraID INT NOT NULL,
    StreamType ENUM('rtsp', 'http', 'rtmp') DEFAULT 'rtsp',
    StreamURL VARCHAR(500),
    Username VARCHAR(100),
    Password VARCHAR(100), -- Should be encrypted in production
    Port INT DEFAULT 554,
    Resolution VARCHAR(20) DEFAULT '1920x1080',
    FPS INT DEFAULT 30,
    HasAudio BOOLEAN DEFAULT 1,
    HasPTZ BOOLEAN DEFAULT 0, -- Pan/Tilt/Zoom capability
    RecordingEnabled BOOLEAN DEFAULT 1,
    MotionDetection BOOLEAN DEFAULT 1,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (CameraID) REFERENCES tbl_devices(DeviceID),
    
    UNIQUE KEY unique_camera_config (CameraID),
    INDEX idx_camera_type (StreamType)
);

-- Insert sample camera configurations
INSERT IGNORE INTO tbl_camera_config (CameraID, StreamType, Username, Password, Port, HasPTZ) 
SELECT 
    DeviceID, 
    'rtsp' as StreamType,
    'admin' as Username,
    'admin123' as Password,
    554 as Port,
    CASE WHEN DeviceName LIKE '%PTZ%' THEN 1 ELSE 0 END as HasPTZ
FROM tbl_devices 
WHERE DeviceType = 'camera' AND IsActive = 1;

-- Create automatic employee-camera mappings based on location/department
INSERT IGNORE INTO tbl_employee_camera_mapping (EmployeeID, CameraID, ProximityScore, MappingReason)
SELECT 
    e.EmployeeID,
    c.DeviceID as CameraID,
    CASE 
        WHEN dept.DepartmentName LIKE '%IT%' AND c.Location LIKE '%IT%' THEN 10
        WHEN dept.DepartmentName LIKE '%Credit%' AND c.Location LIKE '%Office%' THEN 9
        WHEN c.Location LIKE '%Entrance%' THEN 8
        WHEN c.Location LIKE '%Main%' THEN 7
        ELSE 5
    END as ProximityScore,
    'auto_location' as MappingReason
FROM tbl_employees e
CROSS JOIN tbl_devices c
LEFT JOIN tbl_departments dept ON e.DepartmentID = dept.DepartmentID
WHERE c.DeviceType = 'camera' AND c.IsActive = 1;

-- Add camera integration settings to existing alerts (MySQL compatible)
SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_biometric_alerts' AND COLUMN_NAME='CameraTriggered');
SET @sqlstmt := IF(@exist=0, 'ALTER TABLE tbl_biometric_alerts ADD COLUMN CameraTriggered BOOLEAN DEFAULT 0', 'SELECT "Column CameraTriggered already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_biometric_alerts' AND COLUMN_NAME='CameraSessionID');
SET @sqlstmt := IF(@exist=0, 'ALTER TABLE tbl_biometric_alerts ADD COLUMN CameraSessionID INT', 'SELECT "Column CameraSessionID already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exist := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_biometric_alerts' AND COLUMN_NAME='MonitoringDuration');
SET @sqlstmt := IF(@exist=0, 'ALTER TABLE tbl_biometric_alerts ADD COLUMN MonitoringDuration INT', 'SELECT "Column MonitoringDuration already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create indexes for better performance (MySQL compatible)
CREATE INDEX idx_alerts_camera ON tbl_biometric_alerts(CameraTriggered, CreatedAt);

COMMIT;
