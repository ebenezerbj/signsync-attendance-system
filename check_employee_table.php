<?php
include 'db.php';

echo "Checking tbl_employees table structure:\n";
$stmt = $conn->query('DESCRIBE tbl_employees');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
