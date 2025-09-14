<?php
include 'db.php';
$stmt = $conn->prepare('DELETE FROM tbl_login_attempts WHERE employee_id = ? AND success = 0');
$stmt->execute(['AKCBSTF0005']);
echo "✅ Cleared failed attempts for AKCBSTF0005\n";
?>
