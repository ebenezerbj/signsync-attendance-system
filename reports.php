<?php
// filepath: c:\laragon\www\attendance_register\reports.php
session_start();
include 'db.php';

// Ensure user is logged in and is an admin/hr
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    header('Location: login.php');
    exit;
}

// Fetch data for filter dropdowns
$branches = $conn->query("SELECT BranchID, BranchName FROM tbl_branches ORDER BY BranchName")->fetchAll(PDO::FETCH_ASSOC);
$departments = $conn->query("SELECT DepartmentID, DepartmentName FROM tbl_departments ORDER BY DepartmentName")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .sidebar { height: 100vh; width: 260px; position: fixed; top: 0; left: 0; background: #fff; box-shadow: 0 0 2rem 0 rgba(0,0,0,.05); transition: all .3s; z-index: 1020; }
        .sidebar .nav-link { color: #5a6e88; font-weight: 500; }
        .sidebar .nav-link:hover { color: #0d6efd; }
        .sidebar .nav-link.active { color: #0d6efd; background: #eef5ff; border-radius: .375rem; }
        .sidebar .nav-link i { min-width: 2rem; font-size: 1.1rem; }
        .sidebar.collapsed { width: 80px; }
        .sidebar.collapsed .nav-link span, .sidebar.collapsed .sidebar-heading { display: none; }
        .sidebar-heading { font-size: .8rem; text-transform: uppercase; color: #a0aec0; }
        .content { margin-left: 260px; transition: all .3s; }
        .collapsed + .content { margin-left: 80px; }
        .toggle-btn { background: none; border: none; font-size: 1.5rem; color: #5a6e88; }
        .report-card .list-group-item { border-left: 0; border-right: 0; }
    </style>
</head>
<body>
    <div class="sidebar p-3" id="sidebar">
        <div class="d-flex justify-content-between align-items-center">
            <a href="admin_dashboard.php" class="text-decoration-none"><h4 class="sidebar-heading m-0"><span>Admin Panel</span></h4></a>
            <button class="toggle-btn" id="sidebar-toggle"><i class="bi bi-list"></i></button>
        </div>
        <hr>
        <ul class="nav flex-column">
            <li class="nav-item mb-2"><a href="admin_dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></li>
            <li class="nav-item mb-2">
                <a class="nav-link" data-bs-toggle="collapse" href="#empMenu"><i class="bi bi-people-fill"></i><span>Employees</span></a>
                <div class="collapse ps-4" id="empMenu">
                    <a href="add_employee.php" class="nav-link">Add Employee</a>
                    <a href="view_employees.php" class="nav-link">View All</a>
                </div>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link" data-bs-toggle="collapse" href="#shiftsMenu"><i class="bi bi-clock-history"></i><span>Shifts</span></a>
                <div class="collapse ps-4" id="shiftsMenu">
                    <a href="manage_shifts.php" class="nav-link">Manage Shifts</a>
                    <a href="add_shift.php" class="nav-link">Add Shift</a>
                </div>
            </li>
            <li class="nav-item mb-2"><a href="add_branch.php" class="nav-link"><i class="bi bi-geo-alt-fill"></i><span>Branches</span></a></li>
            <li class="nav-item mb-2"><a href="attendance_map.php" class="nav-link"><i class="bi bi-map-fill"></i><span>Attendance Map</span></a></li>
            <li class="nav-item mb-2"><a href="reports.php" class="nav-link active"><i class="bi bi-file-earmark-text-fill"></i><span>Reports</span></a></li>
            <li class="nav-item mb-2"><a href="admin_requests.php" class="nav-link"><i class="bi bi-bell-fill"></i><span>Requests</span></a></li>
            <li class="nav-item mt-auto"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-left"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <main class="content p-4">
        <div class="container-fluid">
            <h2 class="mb-4">Reports Center</h2>
            <div class="row g-4">
                <!-- Attendance Reports -->
                <div class="col-md-6">
                    <div class="card report-card shadow-sm border-0 h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Attendance Reports</h5>
                        </div>
                        <ul class="list-group list-group-flush">
                            <!-- Daily Attendance Report -->
                            <li class="list-group-item">
                                <h6 class="mb-3">Daily Attendance Report</h6>
                                <form id="dailyAttendanceForm" class="row g-3">
                                    <input type="hidden" name="report_type" value="daily_attendance">
                                    <div class="col-sm-6"><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                                    <div class="col-sm-6 d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="openFullReport('dailyAttendanceForm')">View</button>
                                        <button type="submit" formaction="export_handler.php" formmethod="get" name="export" value="pdf" class="btn btn-danger w-50">Export PDF</button>
                                    </div>
                                </form>
                                <div id="dailyAttendanceTable" class="mt-3"></div>
                            </li>
                            <!-- Date Range Attendance Report -->
                            <li class="list-group-item">
                                <h6 class="mb-3">Date Range Attendance Report</h6>
                                <form id="dateRangeAttendanceForm" class="row g-3">
                                    <input type="hidden" name="report_type" value="date_range_attendance">
                                    <div class="col-sm-6"><label class="form-label-sm">Start Date</label><input type="date" name="start_date" class="form-control" required></div>
                                    <div class="col-sm-6"><label class="form-label-sm">End Date</label><input type="date" name="end_date" class="form-control" required></div>
                                    <div class="col-12 d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="openFullReport('dateRangeAttendanceForm')">View</button>
                                        <button type="submit" formaction="export_handler.php" formmethod="get" name="export" value="pdf" class="btn btn-danger w-50">Export PDF</button>
                                    </div>
                                </form>
                                <div id="dateRangeAttendanceTable" class="mt-3"></div>
                            </li>
                            <!-- Latecomers Report -->
                            <li class="list-group-item">
                                <h6 class="mb-3">Latecomers Report</h6>
                                <form id="latecomersForm" class="row g-3">
                                    <input type="hidden" name="report_type" value="latecomers">
                                    <div class="col-sm-6"><label class="form-label-sm">Start Date</label><input type="date" name="start_date" class="form-control" required></div>
                                    <div class="col-sm-6"><label class="form-label-sm">End Date</label><input type="date" name="end_date" class="form-control" required></div>
                                    <div class="col-12 d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="openFullReport('latecomersForm')">View</button>
                                        <button type="submit" formaction="export_handler.php" formmethod="get" name="export" value="pdf" class="btn btn-danger w-50">Export PDF</button>
                                    </div>
                                </form>
                                <div id="latecomersTable" class="mt-3"></div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Employee Reports -->
                <div class="col-md-6">
                    <div class="card report-card shadow-sm border-0 h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Employee Reports</h5>
                        </div>
                        <ul class="list-group list-group-flush">
                            <!-- Full Employee List -->
                            <li class="list-group-item">
                                <h6 class="mb-3">Full Employee List</h6>
                                <form id="employeeListForm" class="row g-3">
                                    <input type="hidden" name="report_type" value="full_employee_list">
                                    <div class="col-sm-6">
                                        <select name="department_id" class="form-select">
                                            <option value="">All Departments</option>
                                            <?php foreach($departments as $dept): ?><option value="<?= $dept['DepartmentID'] ?>"><?= htmlspecialchars($dept['DepartmentName']) ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-6 d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="openFullReport('employeeListForm')">View</button>
                                        <button type="submit" formaction="export_handler.php" formmethod="get" class="btn btn-secondary w-50">Export Excel</button>
                                    </div>
                                </form>
                                <div id="employeeListTable" class="mt-3"></div>
                            </li>
                            <!-- Employee Contact List -->
                            <li class="list-group-item">
                                <h6 class="mb-3">Employee Contact List</h6>
                                <form id="contactListForm" class="row g-3">
                                    <input type="hidden" name="report_type" value="contact_list">
                                    <div class="col-12 d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="openFullReport('contactListForm')">View</button>
                                        <button type="submit" formaction="export_handler.php" formmethod="get" class="btn btn-secondary w-50">Export Excel</button>
                                    </div>
                                </form>
                                <div id="contactListTable" class="mt-3"></div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.querySelector('.content').classList.toggle('collapsed');
        });

        function viewReport(formId, tableId) {
            const form = document.getElementById(formId);
            const formData = new FormData(form);
            fetch('report_viewer.php?' + new URLSearchParams(formData), { method: 'GET' })
                .then(res => res.text())
                .then(html => {
                    document.getElementById(tableId).innerHTML = html;
                })
                .catch(() => {
                    document.getElementById(tableId).innerHTML = '<div class="text-danger">Failed to load report.</div>';
                });
        }

        function openFullReport(formId) {
            const form = document.getElementById(formId);
            const params = new URLSearchParams(new FormData(form)).toString();
            window.open('report_full_view.php?' + params, '_blank');
        }

        document.getElementById('viewDailyAttendance').onclick = function() {
            viewReport('dailyAttendanceForm', 'dailyAttendanceTable');
        };
        document.getElementById('viewDateRangeAttendance').onclick = function() {
            viewReport('dateRangeAttendanceForm', 'dateRangeAttendanceTable');
        };
        document.getElementById('viewLatecomers').onclick = function() {
            viewReport('latecomersForm', 'latecomersTable');
        };
        document.getElementById('viewEmployeeList').onclick = function() {
            viewReport('employeeListForm', 'employeeListTable');
        };
        document.getElementById('viewContactList').onclick = function() {
            viewReport('contactListForm', 'contactListTable');
        };
    </script>
</body>
</html>