<?php
// Enhanced location data schema for WearOS comprehensive location tracking

// Database connection
$host = 'localhost';
$dbname = 'attendance_register_db';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting enhanced location schema migration...\n";
    
    // Check if clockinout table exists first
    $result = $conn->query("SHOW TABLES LIKE 'clockinout'");
    if ($result->rowCount() == 0) {
        echo "Creating clockinout table first...\n";
        $conn->exec("
            CREATE TABLE clockinout (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                action ENUM('clock_in', 'clock_out') NOT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                device_id VARCHAR(100) NULL,
                location VARCHAR(255) NULL,
                notes TEXT NULL,
                INDEX idx_employee_timestamp (employee_id, timestamp)
            ) ENGINE=InnoDB
        ");
        echo "✓ Created clockinout table\n";
    }
    
    // Add location columns one by one to avoid MySQL version issues
    $locationColumns = [
        "ADD COLUMN gps_latitude DECIMAL(10, 8) NULL COMMENT 'GPS latitude coordinate'",
        "ADD COLUMN gps_longitude DECIMAL(11, 8) NULL COMMENT 'GPS longitude coordinate'",
        "ADD COLUMN gps_accuracy FLOAT NULL COMMENT 'GPS accuracy in meters'",
        "ADD COLUMN location_method VARCHAR(50) NULL COMMENT 'Location detection method'",
        "ADD COLUMN wifi_networks JSON NULL COMMENT 'WiFi networks detected'",
        "ADD COLUMN beacon_data JSON NULL COMMENT 'Bluetooth LE beacon data'",
        "ADD COLUMN is_at_workplace BOOLEAN DEFAULT 0 COMMENT 'Whether at workplace'",
        "ADD COLUMN location_verification_score INT DEFAULT 0 COMMENT 'Location confidence score'",
        "ADD COLUMN enhanced_location_data JSON NULL COMMENT 'Complete location data'"
    ];
    
    foreach ($locationColumns as $column) {
        try {
            $conn->exec("ALTER TABLE clockinout $column");
            echo "✓ Added column: " . substr($column, 11, 30) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠ Column already exists: " . substr($column, 11, 30) . "...\n";
            } else {
                echo "✗ Error adding column: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Create indexes
    $indexes = [
        "CREATE INDEX idx_clockinout_location ON clockinout (gps_latitude, gps_longitude)",
        "CREATE INDEX idx_clockinout_workplace ON clockinout (is_at_workplace)",
        "CREATE INDEX idx_clockinout_location_method ON clockinout (location_method)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $conn->exec($index);
            echo "✓ Created index\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "⚠ Index already exists\n";
            } else {
                echo "⚠ Index creation warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Create workplace locations table (without foreign key constraint for now)
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS workplace_locations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                branch_id INT NULL,
                location_name VARCHAR(100) NOT NULL,
                center_latitude DECIMAL(10, 8) NOT NULL,
                center_longitude DECIMAL(11, 8) NOT NULL,
                radius_meters INT DEFAULT 100 COMMENT 'Verification radius in meters',
                wifi_ssids JSON NULL COMMENT 'Authorized WiFi network SSIDs',
                beacon_uuids JSON NULL COMMENT 'Authorized beacon UUIDs',
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");
        echo "✓ Created workplace_locations table\n";
        
        // Insert sample data
        $conn->exec("
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
                '[\"OfficeWiFi\", \"CompanyGuest\", \"SecureOffice\"]',
                '[\"E2C56DB5-DFFB-48D2-B060-D0F5A71096E0\", \"A1B2C3D4-E5F6-7890-ABCD-EF1234567890\"]'
            )
        ");
        echo "✓ Inserted sample workplace location\n";
        
    } catch (PDOException $e) {
        echo "⚠ Workplace locations table warning: " . $e->getMessage() . "\n";
    }
    
    // Create location tracking table
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS location_tracking (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NULL,
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
                INDEX idx_location_tracking_employee (employee_id),
                INDEX idx_location_tracking_time (tracked_at),
                INDEX idx_location_tracking_workplace (is_at_workplace)
            ) ENGINE=InnoDB
        ");
        echo "✓ Created location_tracking table\n";
        
    } catch (PDOException $e) {
        echo "⚠ Location tracking table warning: " . $e->getMessage() . "\n";
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
    
    $foundColumns = 0;
    foreach ($expectedColumns as $col) {
        if (in_array($col, $columns)) {
            echo "✓ Column '$col' exists in clockinout table\n";
            $foundColumns++;
        } else {
            echo "✗ Column '$col' missing in clockinout table\n";
        }
    }
    
    // Check tables
    $result = $conn->query("SHOW TABLES LIKE 'workplace_locations'");
    if ($result->rowCount() > 0) {
        echo "✓ workplace_locations table created successfully\n";
        
        $result = $conn->query("SELECT COUNT(*) FROM workplace_locations");
        $count = $result->fetchColumn();
        echo "✓ workplace_locations table has $count records\n";
    } else {
        echo "✗ workplace_locations table not found\n";
    }
    
    $result = $conn->query("SHOW TABLES LIKE 'location_tracking'");
    if ($result->rowCount() > 0) {
        echo "✓ location_tracking table created successfully\n";
    } else {
        echo "✗ location_tracking table not found\n";
    }
    
    echo "\n✅ Enhanced location schema migration completed!\n";
    echo "Found $foundColumns out of " . count($expectedColumns) . " expected columns\n\n";
    
    echo "New capabilities added:\n";
    echo "- GPS coordinate storage (latitude, longitude, accuracy)\n";
    echo "- WiFi network detection and verification\n";
    echo "- Bluetooth LE beacon detection and tracking\n";
    echo "- Workplace location verification with configurable radius\n";
    echo "- Continuous location tracking for monitoring\n";
    echo "- Multiple location detection methods (GPS, WiFi, beacon, hybrid)\n";
    echo "- JSON storage for comprehensive location data\n";
    echo "- Location verification scoring system\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
