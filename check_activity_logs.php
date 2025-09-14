<?php
include 'db.php';

echo "Checking activity_logs table...\n";

try {
    // Check if activity_logs table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'activity_logs'");
    
    if ($stmt->rowCount() > 0) {
        echo "✅ activity_logs table exists\n";
        
        // Check table structure
        $stmt = $conn->query("DESCRIBE activity_logs");
        echo "Table structure:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "❌ activity_logs table does NOT exist\n";
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
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
