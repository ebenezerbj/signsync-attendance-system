-- Device Registry Migration
-- Create table for tracking all types of devices (IoT, WiFi, Bluetooth, etc.)

CREATE TABLE IF NOT EXISTS tbl_devices (
    DeviceID INT AUTO_INCREMENT PRIMARY KEY,
    DeviceName VARCHAR(255) NOT NULL,
    DeviceType ENUM('wifi', 'bluetooth', 'beacon', 'iot', 'rfid', 'camera', 'sensor', 'other') NOT NULL,
    Identifier VARCHAR(255) NOT NULL,
    BranchID VARCHAR(15) NULL,
    Location VARCHAR(255) NULL,
    Manufacturer VARCHAR(255) NULL,
    Model VARCHAR(255) NULL,
    Description TEXT NULL,
    IsActive BOOLEAN DEFAULT TRUE,
    CreatedBy VARCHAR(15) NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    LastSeenAt TIMESTAMP NULL,
    Metadata JSON NULL, -- For storing device-specific data
    
    UNIQUE KEY unique_device_per_type (DeviceType, Identifier),
    KEY idx_device_type (DeviceType),
    KEY idx_branch (BranchID),
    KEY idx_active (IsActive),
    KEY idx_created_at (CreatedAt),
    
    FOREIGN KEY (BranchID) REFERENCES tbl_branches(BranchID) ON DELETE SET NULL,
    FOREIGN KEY (CreatedBy) REFERENCES tbl_employees(EmployeeID) ON DELETE SET NULL
);

-- Device Activity Log table for tracking device status changes
CREATE TABLE IF NOT EXISTS tbl_device_activity (
    ActivityID INT AUTO_INCREMENT PRIMARY KEY,
    DeviceID INT NOT NULL,
    ActivityType ENUM('detected', 'lost', 'status_change', 'config_change', 'error') NOT NULL,
    ActivityData JSON NULL,
    Timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    DetectedBy VARCHAR(15) NULL, -- Employee who detected/reported
    
    KEY idx_device_activity (DeviceID, Timestamp),
    KEY idx_activity_type (ActivityType),
    
    FOREIGN KEY (DeviceID) REFERENCES tbl_devices(DeviceID) ON DELETE CASCADE,
    FOREIGN KEY (DetectedBy) REFERENCES tbl_employees(EmployeeID) ON DELETE SET NULL
);

-- Device Groups table for organizing devices
CREATE TABLE IF NOT EXISTS tbl_device_groups (
    GroupID INT AUTO_INCREMENT PRIMARY KEY,
    GroupName VARCHAR(255) NOT NULL UNIQUE,
    Description TEXT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Device Group Assignments
CREATE TABLE IF NOT EXISTS tbl_device_group_assignments (
    DeviceID INT NOT NULL,
    GroupID INT NOT NULL,
    AssignedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (DeviceID, GroupID),
    FOREIGN KEY (DeviceID) REFERENCES tbl_devices(DeviceID) ON DELETE CASCADE,
    FOREIGN KEY (GroupID) REFERENCES tbl_device_groups(GroupID) ON DELETE CASCADE
);

-- Insert some default device groups
INSERT IGNORE INTO tbl_device_groups (GroupName, Description) VALUES
('Access Control', 'Devices used for access control and attendance'),
('Network Infrastructure', 'WiFi access points and network devices'),
('IoT Sensors', 'Temperature, humidity, and other environmental sensors'),
('Security Cameras', 'IP cameras and surveillance equipment'),
('Employee Tracking', 'Beacons and devices for employee location tracking');

-- Create indexes for better performance (MySQL doesn't support IF NOT EXISTS for indexes)
-- These will be created if they don't exist, or fail silently if they do
