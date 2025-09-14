<?php
include 'db.php';

// Fix AKCBSTF0005's PIN
$stmt = $conn->prepare('UPDATE tbl_employees SET CustomPIN = ? WHERE EmployeeID = ?');
$stmt->execute(['5678', 'AKCBSTF0005']);
echo "✅ Fixed AKCBSTF0005 PIN to 5678\n";

// Verify
$stmt = $conn->prepare('SELECT EmployeeID, CustomPIN FROM tbl_employees WHERE EmployeeID = ?');
$stmt->execute(['AKCBSTF0005']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Verified: AKCBSTF0005 PIN is now " . $row['CustomPIN'] . "\n";
?>
