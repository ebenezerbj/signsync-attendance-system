<?php
include 'db.php';

echo "Checking for attendance/clock tables...\n";

try {
    // Check for existing attendance tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_NUM);
    
    $attendanceTables = [];
    foreach ($tables as $table) {
        $tableName = $table[0];
        if (stripos($tableName, 'attendance') !== false || 
            stripos($tableName, 'clock') !== false || 
            stripos($tableName, 'checkin') !== false) {
            $attendanceTables[] = $tableName;
        }
    }
    
    if (empty($attendanceTables)) {
        echo "No attendance tables found. Creating clockinout table...\n";
        
        // Create clockinout table
        $createTable = "
        CREATE TABLE clockinout (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            EmployeeID VARCHAR(15) NOT NULL,
            ClockIn DATETIME NOT NULL,
            ClockOut DATETIME NULL,
            ClockInSource VARCHAR(50) DEFAULT 'Manual',
            ClockOutSource VARCHAR(50) DEFAULT 'Manual',
            ClockInLocation VARCHAR(255) NULL,
            ClockOutLocation VARCHAR(255) NULL,
            ClockInDevice VARCHAR(100) NULL,
            ClockOutDevice VARCHAR(100) NULL,
            WorkDuration DECIMAL(5,2) NULL,
            Notes TEXT NULL,
            CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee (EmployeeID),
            INDEX idx_date (ClockIn),
            FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID)
        )";
        
        $conn->exec($createTable);
        echo "✓ Created clockinout table\n";
        
    } else {
        echo "Found attendance tables:\n";
        foreach ($attendanceTables as $table) {
            echo "  $table\n";
        }
    }
    
    echo "\nTesting clockinout table...\n";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM clockinout");
    $result = $stmt->fetch();
    echo "✓ clockinout table has {$result['count']} records\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
