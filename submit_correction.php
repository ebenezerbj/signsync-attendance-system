<?php
// filepath: c:\laragon\www\attendance_register\submit_correction.php
session_start();
include 'db.php';

$employee_id = $_SESSION['employee_id'] ?? '';
$date = $_POST['date'] ?? '';
$type = $_POST['type'] ?? '';
$reason = $_POST['reason'] ?? '';

if ($employee_id && $date && $type && $reason) {
    $stmt = $conn->prepare("INSERT INTO tbl_correction_requests (employee_id, date, type, reason) VALUES (?, ?, ?, ?)");
    $stmt->execute([$employee_id, $date, $type, $reason]);
    header('Location: employee_portal.php');
    exit;
} else {
    exit('Missing required fields.');
}