<?php
include 'db.php';

try {
    $stmt = $conn->query('DESCRIBE tbl_gamification');
    echo "tbl_gamification table columns:\n";
    while($row = $stmt->fetch()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch(Exception $e) {
    echo "Table does not exist or error: " . $e->getMessage() . "\n";
    
    // Check if table exists in a different way
    $stmt = $conn->query("SHOW TABLES LIKE 'tbl_gamification'");
    if ($stmt->rowCount() == 0) {
        echo "tbl_gamification table does not exist. Need to create it.\n";
    }
}
?>
