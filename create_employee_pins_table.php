<?php
include 'db.php';

echo "Creating employee_pins table...\n";

try {
    // Create employee_pins table
    $sql = "CREATE TABLE IF NOT EXISTS employee_pins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        EmployeeID VARCHAR(50) NOT NULL,
        pin VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_employee (EmployeeID)
    )";
    
    $conn->exec($sql);
    echo "✅ employee_pins table created successfully!\n";
    
    // Check if table exists and show structure
    $stmt = $conn->prepare("DESCRIBE employee_pins");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nTable structure:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Check current records
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employee_pins");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nCurrent records: " . $result['count'] . "\n";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
