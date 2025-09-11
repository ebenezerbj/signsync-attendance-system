<?php
include 'db.php';

echo "Checking tbl_leave_types table structure:\n";
$stmt = $conn->query('DESCRIBE tbl_leave_types');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\nChecking if tbl_leave_requests table exists:\n";
try {
    $stmt = $conn->query('DESCRIBE tbl_leave_requests');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} catch (PDOException $e) {
    echo "Table tbl_leave_requests does not exist: " . $e->getMessage() . "\n";
}
?>
