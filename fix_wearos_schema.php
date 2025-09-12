<?php
require_once 'db.php';
echo "MySQL Version: " . $conn->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";

// Fix the column issues manually
echo "Fixing database schema issues...\n";

try {
    // Add columns to tbl_biometric_data without IF NOT EXISTS
    $conn->exec("ALTER TABLE tbl_biometric_data ADD COLUMN stress_level_numeric DECIMAL(4,1) NULL");
    echo "✓ Added stress_level_numeric column\n";
} catch (Exception $e) {
    echo "- stress_level_numeric column may already exist\n";
}

try {
    $conn->exec("ALTER TABLE tbl_biometric_data ADD COLUMN device_type VARCHAR(50) DEFAULT 'iot_wearable'");
    echo "✓ Added device_type column\n";
} catch (Exception $e) {
    echo "- device_type column may already exist\n";
}

try {
    $conn->exec("ALTER TABLE tbl_biometric_data ADD COLUMN data_source VARCHAR(50) DEFAULT 'wearable'");
    echo "✓ Added data_source column\n";
} catch (Exception $e) {
    echo "- data_source column may already exist\n";
}

try {
    $conn->exec("ALTER TABLE tbl_biometric_data ADD COLUMN employee_id VARCHAR(15) NULL");
    echo "✓ Added employee_id column\n";
} catch (Exception $e) {
    echo "- employee_id column may already exist\n";
}

// Add columns to tbl_biometric_alerts
try {
    $conn->exec("ALTER TABLE tbl_biometric_alerts ADD COLUMN employee_id VARCHAR(15) NULL");
    echo "✓ Added employee_id to alerts\n";
} catch (Exception $e) {
    echo "- employee_id in alerts may already exist\n";
}

try {
    $conn->exec("ALTER TABLE tbl_biometric_alerts ADD COLUMN heart_rate INT NULL");
    echo "✓ Added heart_rate to alerts\n";
} catch (Exception $e) {
    echo "- heart_rate in alerts may already exist\n";
}

try {
    $conn->exec("ALTER TABLE tbl_biometric_alerts ADD COLUMN stress_level DECIMAL(4,1) NULL");
    echo "✓ Added stress_level to alerts\n";
} catch (Exception $e) {
    echo "- stress_level in alerts may already exist\n";
}

try {
    $conn->exec("ALTER TABLE tbl_biometric_alerts ADD COLUMN is_urgent BOOLEAN DEFAULT FALSE");
    echo "✓ Added is_urgent to alerts\n";
} catch (Exception $e) {
    echo "- is_urgent in alerts may already exist\n";
}

try {
    $conn->exec("ALTER TABLE tbl_biometric_alerts ADD COLUMN timestamp TIMESTAMP NULL");
    echo "✓ Added timestamp to alerts\n";
} catch (Exception $e) {
    echo "- timestamp in alerts may already exist\n";
}

try {
    $conn->exec("ALTER TABLE tbl_biometric_alerts ADD COLUMN status VARCHAR(20) DEFAULT 'ACTIVE'");
    echo "✓ Added status to alerts\n";
} catch (Exception $e) {
    echo "- status in alerts may already exist\n";
}

// Add indexes
try {
    $conn->exec("ALTER TABLE tbl_biometric_data ADD INDEX idx_employee_id_timestamp (employee_id, Timestamp)");
    echo "✓ Added employee_id index\n";
} catch (Exception $e) {
    echo "- employee_id index may already exist\n";
}

// Check tables created
$tables = ['employee_activity', 'wearos_sessions', 'wearos_devices', 'system_config'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->rowCount() > 0) {
        echo "✓ Table exists: $table\n";
    } else {
        echo "✗ Table missing: $table\n";
    }
}

echo "\nDatabase schema fixes completed!\n";
?>
