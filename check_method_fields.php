<?php
include 'db.php';

echo "=== Attendance Table Method Fields ===\n";

try {
    $stmt = $conn->prepare('DESCRIBE tbl_attendance');
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        if (strpos($row['Field'], 'Method') !== false || strpos($row['Field'], 'method') !== false) {
            echo $row['Field'] . ' (' . $row['Type'] . ")\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
