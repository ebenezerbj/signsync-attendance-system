<?php
// filepath: c:\laragon\www\attendance_register\process_leave.php
include 'db.php';
$id = $_POST['id'] ?? '';
$action = $_POST['action'] ?? '';
$manager_comment = $_POST['manager_comment'] ?? '';
if ($id && in_array($action, ['approve','reject'])) {
    $status = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $conn->prepare("UPDATE tbl_leave_requests SET status=?, manager_comment=? WHERE id=?");
    $stmt->execute([$status, $manager_comment, $id]);
}
header('Location: admin_requests.php');
exit;