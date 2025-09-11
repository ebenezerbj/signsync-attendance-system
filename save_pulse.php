<?php
// filepath: c:\laragon\www\attendance_register\save_pulse.php
session_start();
include 'db.php';

$employee_id = $_SESSION['employee_id'] ?? '';
$mood = $_POST['mood'] ?? '';
$comment = $_POST['comment'] ?? '';
$date = date('Y-m-d');

if ($employee_id && $mood) {
    $stmt = $conn->prepare("INSERT INTO tbl_pulse_surveys (employee_id, date, mood, comment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$employee_id, $date, $mood, $comment]);
    header('Location: employee_portal.php');
    exit;
} else {
    exit('Missing required fields.');
}