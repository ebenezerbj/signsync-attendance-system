<?php
include 'db.php';
echo "Checking tbl_attendance table structure:\n";
$stmt = $conn->query('DESCRIBE tbl_attendance');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
