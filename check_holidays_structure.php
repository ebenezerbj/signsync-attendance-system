<?php
include 'db.php';

echo "=== Holidays Table Structure ===\n";
try {
    $stmt = $conn->prepare('DESCRIBE tbl_holidays');
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . ' (' . $row['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Sample Holiday Data ===\n";
try {
    $stmt = $conn->prepare('SELECT * FROM tbl_holidays LIMIT 3');
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        print_r($row);
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
