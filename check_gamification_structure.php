<?php
include 'db.php';

try {
    echo "Checking tbl_gamification table structure:\n";
    
    $stmt = $conn->query("DESCRIBE tbl_gamification");
    printf("%-20s %-15s %-10s %-5s %-10s %-10s\n", 
        'Field', 'Type', 'Null', 'Key', 'Default', 'Extra'
    );
    echo str_repeat('-', 80) . "\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
