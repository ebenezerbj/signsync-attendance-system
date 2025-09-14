<?php
include 'db.php';

echo "SIGNSYNC Employee List for PIN Testing:\n";
echo "==========================================\n";

$stmt = $conn->query('SELECT EmployeeID, FullName, PhoneNumber FROM tbl_employees LIMIT 10');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $phone = $row['PhoneNumber'];
    $employeeId = $row['EmployeeID'];
    
    // Calculate possible PINs
    $phonePIN = $phone ? substr($phone, -4) : 'N/A';
    preg_match('/(\d+)$/', $employeeId, $matches);
    $idPIN = !empty($matches[1]) ? str_pad($matches[1], 4, '0', STR_PAD_LEFT) : 'N/A';
    
    echo "Employee: " . $row['EmployeeID'] . " - " . $row['FullName'] . "\n";
    echo "  Phone: " . ($phone ?: 'Not set') . "\n";
    echo "  Possible PINs:\n";
    echo "    - Phone PIN: " . $phonePIN . "\n";
    echo "    - Default PIN: 1234\n";
    echo "    - ID PIN: " . $idPIN . "\n";
    echo "----------------------------------------\n";
}

if ($stmt->rowCount() == 0) {
    echo "No employees found. Create some employees first.\n";
}
?>
