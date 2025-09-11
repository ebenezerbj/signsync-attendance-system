<?php
// filepath: c:\laragon\www\attendance_register\admin_requests.php
include 'db.php';

// Fetch pending correction and leave requests
$corrections = $conn->query("SELECT cr.*, e.FullName FROM tbl_correction_requests cr JOIN tbl_employees e ON cr.EmployeeID = e.EmployeeID WHERE cr.status = 'pending' ORDER BY cr.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$leaves = $conn->query("SELECT lr.*, e.FullName FROM tbl_leave_requests lr JOIN tbl_employees e ON lr.EmployeeID = e.EmployeeID WHERE lr.status = 'pending' ORDER BY lr.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Requests - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4 text-primary">Pending Correction Requests</h2>
    <div class="table-responsive mb-5">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($corrections as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['FullName']) ?></td>
                    <td><?= htmlspecialchars($row['date']) ?></td>
                    <td><?= htmlspecialchars($row['type']) ?></td>
                    <td><?= htmlspecialchars($row['reason']) ?></td>
                    <td>
                        <form method="post" action="process_correction.php" class="d-flex gap-1">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="text" name="manager_comment" class="form-control form-control-sm" placeholder="Comment" required>
                            <button name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                            <button name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 class="mb-4 text-primary">Pending Leave Requests</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>Employee</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($leaves as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['FullName']) ?></td>
                    <td><?= htmlspecialchars($row['start_date']) ?></td>
                    <td><?= htmlspecialchars($row['end_date']) ?></td>
                    <td><?= htmlspecialchars($row['type']) ?></td>
                    <td><?= htmlspecialchars($row['reason']) ?></td>
                    <td>
                        <form method="post" action="process_leave.php" class="d-flex gap-1">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="text" name="manager_comment" class="form-control form-control-sm" placeholder="Comment" required>
                            <button name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                            <button name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <a href="admin_dashboard.php" class="btn btn-link mt-4">&larr; Back to Dashboard</a>
</div>
</body>
</html>