<?php
require_once 'db.php';

// Enhanced location data schema for WearOS comprehensive location tracking
$sql = "
-- Add comprehensive location columns to clockinout table for GPS, WiFi, and beacon data
ALTER TABLE clockinout 
ADD COLUMN IF NOT EXISTS gps_latitude DECIMAL(10, 8) NULL COMMENT 'GPS latitude coordinate',
ADD COLUMN IF NOT EXISTS gps_longitude DECIMAL(11, 8) NULL COMMENT 'GPS longitude coordinate',
ADD COLUMN IF NOT EXISTS gps_accuracy FLOAT NULL COMMENT 'GPS accuracy in meters',
ADD COLUMN IF NOT EXISTS location_method VARCHAR(50) NULL COMMENT 'Location detection method (gps, wifi, beacon, hybrid)',
ADD COLUMN IF NOT EXISTS wifi_networks JSON NULL COMMENT 'WiFi networks detected during clock in/out',
ADD COLUMN IF NOT EXISTS beacon_data JSON NULL COMMENT 'Bluetooth LE beacon data detected',
ADD COLUMN IF NOT EXISTS is_at_workplace BOOLEAN DEFAULT 0 COMMENT 'Whether employee is verified at workplace location',
ADD COLUMN IF NOT EXISTS location_verification_score INT DEFAULT 0 COMMENT 'Confidence score for location verification (0-100)',
ADD COLUMN IF NOT EXISTS enhanced_location_data JSON NULL COMMENT 'Complete location data from WearOS device';

-- Create index for location-based queries
CREATE INDEX IF NOT EXISTS idx_clockinout_location ON clockinout (gps_latitude, gps_longitude);
CREATE INDEX IF NOT EXISTS idx_clockinout_workplace ON clockinout (is_at_workplace);
CREATE INDEX IF NOT EXISTS idx_clockinout_location_method ON clockinout (location_method);

-- Add workplace configuration table for location-based verification
CREATE TABLE IF NOT EXISTS workplace_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    location_name VARCHAR(100) NOT NULL,
    center_latitude DECIMAL(10, 8) NOT NULL,
    center_longitude DECIMAL(11, 8) NOT NULL,
    radius_meters INT DEFAULT 100 COMMENT 'Verification radius in meters',
    wifi_ssids JSON NULL COMMENT 'Authorized WiFi network SSIDs',
    beacon_uuids JSON NULL COMMENT 'Authorized beacon UUIDs',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insert sample workplace location for testing
INSERT IGNORE INTO workplace_locations (
    branch_id, 
    location_name, 
    center_latitude, 
    center_longitude, 
    radius_meters, 
    wifi_ssids, 
    beacon_uuids
) VALUES (
    1, 
    'Main Office', 
    40.7128, 
    -74.0060, 
    100,
    JSON_ARRAY('OfficeWiFi', 'CompanyGuest', 'SecureOffice'),
    JSON_ARRAY('E2C56DB5-DFFB-48D2-B060-D0F5A71096E0', 'A1B2C3D4-E5F6-7890-ABCD-EF1234567890')
);

-- Add location tracking table for continuous monitoring
CREATE TABLE IF NOT EXISTS location_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    device_id VARCHAR(100) NULL,
    tracked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy FLOAT NULL,
    location_method VARCHAR(50) NULL,
    wifi_networks JSON NULL,
    beacon_data JSON NULL,
    is_at_workplace BOOLEAN DEFAULT 0,
    workplace_location_id INT NULL,
    tracking_type ENUM('manual', 'automatic', 'geofence') DEFAULT 'automatic',
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (workplace_location_id) REFERENCES workplace_locations(id) ON DELETE SET NULL,
    INDEX idx_location_tracking_employee (employee_id),
    INDEX idx_location_tracking_time (tracked_at),
    INDEX idx_location_tracking_workplace (is_at_workplace)
) ENGINE=InnoDB;
";

try {
    // Database connection
    $host = 'localhost';
    $dbname = 'attendance_register_db';
    $username = 'root';
    $password = '';
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting enhanced location schema migration...\n";
    
    // Execute the schema updates
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $conn->exec($statement);
                echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (PDOException $e) {
                echo "⚠ Warning: " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    // Verify the schema updates
    echo "\nVerifying enhanced location schema...\n";
    
    // Check clockinout table columns
    $result = $conn->query("DESCRIBE clockinout");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedColumns = [
        'gps_latitude', 'gps_longitude', 'gps_accuracy', 
        'location_method', 'wifi_networks', 'beacon_data',
        'is_at_workplace', 'location_verification_score', 'enhanced_location_data'
    ];
    
    foreach ($expectedColumns as $col) {
        if (in_array($col, $columns)) {
            echo "✓ Column '$col' exists in clockinout table\n";
        } else {
            echo "✗ Column '$col' missing in clockinout table\n";
        }
    }
    
    // Check workplace_locations table
    $result = $conn->query("SHOW TABLES LIKE 'workplace_locations'");
    if ($result->rowCount() > 0) {
        echo "✓ workplace_locations table created successfully\n";
        
        // Check sample data
        $result = $conn->query("SELECT COUNT(*) FROM workplace_locations");
        $count = $result->fetchColumn();
        echo "✓ workplace_locations table has $count records\n";
    } else {
        echo "✗ workplace_locations table not found\n";
    }
    
    // Check location_tracking table
    $result = $conn->query("SHOW TABLES LIKE 'location_tracking'");
    if ($result->rowCount() > 0) {
        echo "✓ location_tracking table created successfully\n";
    } else {
        echo "✗ location_tracking table not found\n";
    }
    
    echo "\n✅ Enhanced location schema migration completed!\n";
    echo "\nNew capabilities:\n";
    echo "- GPS coordinate storage with accuracy metrics\n";
    echo "- WiFi network detection and verification\n";
    echo "- Bluetooth LE beacon detection and tracking\n";
    echo "- Workplace location verification with configurable radius\n";
    echo "- Continuous location tracking for monitoring\n";
    echo "- Multiple location detection methods (GPS, WiFi, beacon, hybrid)\n";
    echo "- JSON storage for comprehensive location data\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
