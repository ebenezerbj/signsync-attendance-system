<?php
require_once 'db.php';

echo "Employee table structure:\n";
$stmt = $conn->query('DESCRIBE tbl_employees');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}

echo "\nBiometric data table structure:\n";
$stmt = $conn->query('DESCRIBE tbl_biometric_data');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}

// Check some sample data
echo "\nSample employees:\n";
$stmt = $conn->query('SELECT EmployeeID, Name FROM tbl_employees LIMIT 5');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['EmployeeID'] . ': ' . $row['Name'] . "\n";
}
?>
