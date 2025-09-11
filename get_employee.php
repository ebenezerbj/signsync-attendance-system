<?php
// filepath: c:\laragon\www\attendance_register\get_employee.php
header('Content-Type: application/json');
include 'db.php';

// Get max length dynamically
$maxLengthStmt = $conn->query("
    SELECT CHARACTER_MAXIMUM_LENGTH 
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'attendance_register_db'
      AND TABLE_NAME = 'tbl_employees'
      AND COLUMN_NAME = 'EmployeeID'
");
$maxLength = $maxLengthStmt->fetchColumn();
if (!$maxLength) $maxLength = 15; // fallback

if (!isset($_GET['id']) || strlen($_GET['id']) > $maxLength) {
    echo json_encode([]);
    exit;
}

$id = trim($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($row ? $row : []);
