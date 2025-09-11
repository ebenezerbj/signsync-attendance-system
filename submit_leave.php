<?php
// filepath: c:\laragon\www\attendance_register\submit_leave.php
session_start();
include 'db.php';

$employee_id = $_SESSION['employee_id'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$type = $_POST['type'] ?? '';
$reason = $_POST['reason'] ?? '';

if ($employee_id && $start_date && $end_date && $type && $reason) {
    $stmt = $conn->prepare("INSERT INTO tbl_leave_requests (employee_id, start_date, end_date, type, reason) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$employee_id, $start_date, $end_date, $type, $reason]);
    header('Location: employee_portal.php');
    exit;
} else {
    exit('Missing required fields.');
}