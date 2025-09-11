<?php
// filepath: c:\laragon\www\attendance_register\report_full_view.php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    exit('Access Denied.');
}

$reportType = $_GET['report_type'] ?? '';
$title = 'Report';

if ($reportType === 'daily_attendance') {
    $title = 'Daily Attendance Report';
    $date = $_GET['date'] ?? date('Y-m-d');
    $sql = "SELECT e.FullName, d.DepartmentName, r.RoleName, a.AttendanceDate, a.ClockIn, a.ClockOut, a.ClockInStatus,
                   TIMEDIFF(a.ClockOut, a.ClockIn) AS WorkedHours, a.ClockInPhoto
            FROM tbl_attendance a
            JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
            LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
            WHERE a.AttendanceDate = ?
            ORDER BY e.FullName";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($reportType === 'date_range_attendance') {
    $title = 'Date Range Attendance Report';
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    if (!$startDate || !$endDate) {
        die('Start and End dates are required.');
    }
    $sql = "SELECT e.FullName, d.DepartmentName, r.RoleName, a.AttendanceDate, a.ClockIn, a.ClockOut, a.ClockInStatus,
                   TIMEDIFF(a.ClockOut, a.ClockIn) AS WorkedHours
            FROM tbl_attendance a
            JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
            LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
            WHERE a.AttendanceDate BETWEEN ? AND ?
            ORDER BY a.AttendanceDate, e.FullName";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($reportType === 'latecomers') {
    $title = 'Latecomers Report';
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    if (!$startDate || !$endDate) {
        die('Start and End dates are required.');
    }
    $sql = "SELECT e.FullName, d.DepartmentName, a.AttendanceDate, a.ClockIn, a.ClockInStatus,
                   TIMESTAMPDIFF(MINUTE, s.StartTime, a.ClockIn) AS LateByMinutes
            FROM tbl_attendance a
            JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
            LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN employee_categories c ON e.CategoryID = c.CategoryID
            LEFT JOIN tbl_shifts s ON c.DefaultShiftID = s.ShiftID
            WHERE a.ClockInStatus = 'Late' AND a.AttendanceDate BETWEEN ? AND ?
            ORDER BY a.AttendanceDate, e.FullName";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($reportType === 'full_employee_list') {
    $title = 'Full Employee List';
    $deptId = $_GET['department_id'] ?? null;
    $sql = "SELECT e.EmployeeID, e.FullName, e.Username, e.PhoneNumber, d.DepartmentName, r.RoleName,
                   COALESCE(GROUP_CONCAT(b.BranchName SEPARATOR ', '), 'N/A') AS Branches
            FROM tbl_employees e
            LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
            LEFT JOIN employee_branches eb ON e.EmployeeID = eb.EmployeeID
            LEFT JOIN tbl_branches b ON eb.BranchID = b.BranchID";
    $params = [];
    if ($deptId) {
        $sql .= " WHERE e.DepartmentID = ?";
        $params[] = $deptId;
    }
    $sql .= " GROUP BY e.EmployeeID ORDER BY e.FullName";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($reportType === 'contact_list') {
    $title = 'Employee Contact List';
    $sql = "SELECT FullName, Username, PhoneNumber FROM tbl_employees ORDER BY FullName";
    $stmt = $conn->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {
    die('Invalid report type.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fc; }
        .table th, .table td { vertical-align: middle; }
        .table img { box-shadow: 0 2px 8px rgba(0,0,0,0.08);}
    </style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-2"><?= htmlspecialchars($title) ?></h2>
    <?php if ($reportType === 'daily_attendance'): ?>
        <p class="text-muted mb-4">Date: <?= htmlspecialchars($date) ?></p>
    <?php elseif ($reportType === 'date_range_attendance' || $reportType === 'latecomers'): ?>
        <p class="text-muted mb-4">From <?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?></p>
    <?php endif; ?>
    <?php if (empty($rows)): ?>
        <div class="alert alert-info">No records found for the selected filters.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm table-striped table-hover">
                <thead>
                <tr>
                    <?php foreach (array_keys($rows[0]) as $col): ?>
                        <th><?= $col === 'ClockInPhoto' ? 'Clock In Photo' : htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $col => $cell): ?>
                            <?php if ($col === 'ClockInPhoto' && $cell): ?>
                                <?php
                                $imgPath = $cell;
                                if (strpos($cell, 'uploads/') === 0) {
                                    $imgPath = $cell; // already has uploads/
                                } else {
                                    $imgPath = 'uploads/' . $cell;
                                }
                                ?>
                                <td>
                                    <img src="<?= htmlspecialchars($imgPath) ?>" alt="Clock In Photo"
                                         style="max-width:60px;max-height:60px;border-radius:6px;cursor:pointer;"
                                         data-enlargeable>
                                </td>
                            <?php else: ?>
                                <td><?= htmlspecialchars($cell ?? 'N/A') ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="mt-4">
        <a href="javascript:window.print()" class="btn btn-outline-secondary">Print</a>
        <button type="button" class="btn btn-primary" onclick="window.close();">Back to Reports</button>
    </div>
</div>

<!-- Modal for image preview -->
<div class="modal fade" id="imgModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-transparent border-0">
      <img id="modalImg" src="" class="img-fluid rounded shadow" style="max-width:400px;">
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('img[data-enlargeable]').forEach(img => {
    img.onclick = function() {
        document.getElementById('modalImg').src = this.src;
        new bootstrap.Modal(document.getElementById('imgModal')).show();
    }
});
</script>
</body>
</html>