<?php
// filepath: c:\laragon\www\attendance_register\chat_action.php
session_start();
include 'db.php';

if ($_GET['action'] === 'today_attendance') {
    $today = date('Y-m-d');
    $present = $conn->prepare("SELECT COUNT(DISTINCT EmployeeID) FROM tbl_attendance WHERE AttendanceDate = ?");
    $present->execute([$today]);
    $presentCount = $present->fetchColumn();

    $late = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance WHERE AttendanceDate = ? AND ClockInStatus = 'Late'");
    $late->execute([$today]);
    $lateCount = $late->fetchColumn();

    $total = $conn->query("SELECT COUNT(*) FROM tbl_employees")->fetchColumn();

    $summary = "Total Employees: $total, Present: $presentCount, Late: $lateCount.";
    echo json_encode(['summary' => $summary]);
    exit;
}
?>