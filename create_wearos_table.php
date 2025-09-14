<?php
require_once 'db.php';

try {
    $host = 'localhost';
    $dbname = 'attendance_register_db';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Drop table if exists to recreate with correct schema
    $dropTable = "DROP TABLE IF EXISTS tbl_wearos_devices";
    $pdo->exec($dropTable);
    
    // Create the WearOS devices table
    $createTable = "
    CREATE TABLE tbl_wearos_devices (
        DeviceID VARCHAR(32) PRIMARY KEY,
        DeviceName VARCHAR(100) NOT NULL DEFAULT 'Android Wear Device',
        DeviceModel VARCHAR(50) DEFAULT 'Unknown',
        AndroidVersion VARCHAR(20) DEFAULT '',
        WearOSVersion VARCHAR(20) DEFAULT '',
        MACAddress VARCHAR(17) UNIQUE,
        SerialNumber VARCHAR(50) UNIQUE,
        RegistrationCode VARCHAR(6) UNIQUE NOT NULL,
        Status ENUM('registered_pending_binding', 'bound_to_employee', 'suspended', 'deactivated') DEFAULT 'registered_pending_binding',
        EmployeeID VARCHAR(20) NULL,
        RegisteredAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        LastSeen TIMESTAMP NULL,
        IsActive BOOLEAN DEFAULT TRUE,
        BatteryLevel INT DEFAULT NULL,
        HealthSensorsEnabled BOOLEAN DEFAULT TRUE,
        LocationEnabled BOOLEAN DEFAULT TRUE,
        CreatedBy VARCHAR(20) DEFAULT 'SYSTEM',
        UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_registration_code (RegistrationCode),
        INDEX idx_employee_id (EmployeeID),
        INDEX idx_status (Status),
        INDEX idx_mac_address (MACAddress),
        INDEX idx_serial_number (SerialNumber)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createTable);
    
    echo "WearOS devices table created successfully!<br>";
    echo "Table: tbl_wearos_devices<br>";
    echo "Status: Ready for device registration<br>";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
