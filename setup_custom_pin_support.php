<?php
include 'db.php';

echo "Adding Custom PIN Support to SIGNSYNC...\n";
echo "========================================\n";

try {
    // Check if PIN column already exists
    $stmt = $conn->query("SHOW COLUMNS FROM tbl_employees LIKE 'CustomPIN'");
    
    if ($stmt->rowCount() > 0) {
        echo "✅ CustomPIN column already exists\n";
    } else {
        echo "Adding CustomPIN column to tbl_employees...\n";
        $conn->exec("ALTER TABLE tbl_employees ADD COLUMN CustomPIN VARCHAR(10) DEFAULT NULL");
        echo "✅ CustomPIN column added successfully\n";
    }
    
    // Check if PIN setup status column exists
    $stmt = $conn->query("SHOW COLUMNS FROM tbl_employees LIKE 'PINSetupComplete'");
    
    if ($stmt->rowCount() > 0) {
        echo "✅ PINSetupComplete column already exists\n";
    } else {
        echo "Adding PINSetupComplete column to tbl_employees...\n";
        $conn->exec("ALTER TABLE tbl_employees ADD COLUMN PINSetupComplete TINYINT(1) DEFAULT 0");
        echo "✅ PINSetupComplete column added successfully\n";
    }
    
    // Create activity_logs table if it doesn't exist
    $stmt = $conn->query("SHOW TABLES LIKE 'activity_logs'");
    
    if ($stmt->rowCount() == 0) {
        echo "Creating activity_logs table...\n";
        $createTable = "
            CREATE TABLE activity_logs (
                LogID INT AUTO_INCREMENT PRIMARY KEY,
                EmployeeID VARCHAR(50) NOT NULL,
                ActivityType VARCHAR(50) NOT NULL,
                ActivityDescription TEXT,
                Timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_employee (EmployeeID),
                INDEX idx_timestamp (Timestamp)
            )
        ";
        $conn->exec($createTable);
        echo "✅ activity_logs table created successfully\n";
    } else {
        echo "✅ activity_logs table already exists\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Database setup complete!\n";
    echo "Now employees can:\n";
    echo "1. First login with default PIN '1234'\n";
    echo "2. Set up their custom PIN after first login\n";
    echo "3. Use their custom PIN for future logins\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
