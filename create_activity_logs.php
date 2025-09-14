<?php
include 'db.php';

echo "Creating activity_logs table for SIGNSYNC...\n";

try {
    $createTable = "
        CREATE TABLE IF NOT EXISTS activity_logs (
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
    echo "✅ activity_logs table created successfully!\n";
    echo "Now your original PIN API should work.\n";
    
} catch (Exception $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
}
?>
