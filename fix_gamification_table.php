<?php
include 'db.php';

try {
    echo "Adding LastActivity column to tbl_gamification table...\n";
    
    // Check if column already exists
    $checkStmt = $conn->query("SHOW COLUMNS FROM tbl_gamification LIKE 'LastActivity'");
    if ($checkStmt->rowCount() > 0) {
        echo "LastActivity column already exists.\n";
    } else {
        // Add the column after points column
        $stmt = $conn->exec("ALTER TABLE tbl_gamification ADD COLUMN LastActivity DATETIME NULL AFTER points");
        echo "Successfully added LastActivity column to tbl_gamification table.\n";
    }
    
    // Verify the table structure
    echo "\nUpdated tbl_gamification structure:\n";
    printf("%-20s %-15s %-10s %-5s %-10s %-10s\n", 
        'Field', 'Type', 'Null', 'Key', 'Default', 'Extra'
    );
    echo str_repeat('-', 80) . "\n";
    
    $descStmt = $conn->query("DESCRIBE tbl_gamification");
    while ($row = $descStmt->fetch(PDO::FETCH_ASSOC)) {
        printf("%-20s %-15s %-10s %-5s %-10s %-10s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'], 
            $row['Default'] ?? 'NULL', 
            $row['Extra']
        );
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
