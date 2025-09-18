<?php
session_start();
include 'db.php';

// Handle AJAX request for employee details
if (isset($_GET['action']) && $_GET['action'] === 'get_employee' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                e.EmployeeID, e.FullName, e.Username, e.PhoneNumber, e.IsSpecial,
                d.DepartmentName,
                r.RoleName,
                COALESCE(GROUP_CONCAT(DISTINCT b.BranchName ORDER BY b.BranchName SEPARATOR ', '), '—') AS Branches,
                (SELECT COUNT(*) FROM tbl_attendance a WHERE a.EmployeeID = e.EmployeeID AND a.AttendanceDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as attendance_count,
                (SELECT MAX(a.AttendanceDate) FROM tbl_attendance a WHERE a.EmployeeID = e.EmployeeID) as last_attendance,
                (SELECT COUNT(*) FROM employee_pins ep WHERE ep.EmployeeID = e.EmployeeID) as has_pin
            FROM tbl_employees e
            LEFT JOIN employee_branches eb ON e.EmployeeID = eb.EmployeeID
            LEFT JOIN tbl_branches b ON eb.BranchID = b.BranchID
            LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
            WHERE e.EmployeeID = ?
            GROUP BY e.EmployeeID
        ");
        
        $stmt->execute([$_GET['id']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            echo json_encode(['success' => true, 'employee' => $employee]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Ensure user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    header('Location: login.php');
    exit;
}

// Enhanced search and pagination
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Fetch filter options with counts
$branchOptions = $conn->query("
    SELECT b.BranchID, b.BranchName, COUNT(eb.EmployeeID) as employee_count
    FROM tbl_branches b 
    LEFT JOIN employee_branches eb ON b.BranchID = eb.BranchID 
    GROUP BY b.BranchID, b.BranchName 
    ORDER BY b.BranchName
")->fetchAll(PDO::FETCH_ASSOC);

$departmentOptions = $conn->query("
    SELECT d.DepartmentID, d.DepartmentName, COUNT(e.EmployeeID) as employee_count
    FROM tbl_departments d 
    LEFT JOIN tbl_employees e ON d.DepartmentID = e.DepartmentID 
    GROUP BY d.DepartmentID, d.DepartmentName 
    ORDER BY d.DepartmentName
")->fetchAll(PDO::FETCH_ASSOC);

$roleOptions = $conn->query("
    SELECT r.RoleID, r.RoleName, COUNT(e.EmployeeID) as employee_count
    FROM tbl_roles r 
    LEFT JOIN tbl_employees e ON r.RoleID = e.RoleID 
    GROUP BY r.RoleID, r.RoleName 
    ORDER BY r.RoleName
")->fetchAll(PDO::FETCH_ASSOC);

// Handle filters
$branchFilter = $_GET['branch'] ?? '';
$departmentFilter = $_GET['department'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = ["1=1"];
$params = [];

// Search functionality
if ($search) {
    $where[] = "(e.EmployeeID LIKE ? OR e.FullName LIKE ? OR e.Username LIKE ? OR e.PhoneNumber LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

// Filter conditions
if ($branchFilter) {
    $where[] = "e.EmployeeID IN (SELECT EmployeeID FROM employee_branches WHERE BranchID = ?)";
    $params[] = $branchFilter;
}
if ($departmentFilter) {
    $where[] = "e.DepartmentID = ?";
    $params[] = $departmentFilter;
}
if ($roleFilter) {
    $where[] = "e.RoleID = ?";
    $params[] = $roleFilter;
}
if ($statusFilter) {
    if ($statusFilter === 'special') {
        $where[] = "e.IsSpecial = 1";
    } elseif ($statusFilter === 'normal') {
        $where[] = "e.IsSpecial = 0";
    }
}

$whereSql = "WHERE " . implode(" AND ", $where);

// Get total count for pagination
$countStmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.EmployeeID) as total
    FROM tbl_employees e
    LEFT JOIN employee_branches eb ON e.EmployeeID = eb.EmployeeID
    LEFT JOIN tbl_branches b ON eb.BranchID = b.BranchID
    LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
    LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
    $whereSql
");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch employees with enhanced information
$stmt = $conn->prepare("
    SELECT 
        e.EmployeeID, e.FullName, e.Username, e.PhoneNumber, e.IsSpecial,
        d.DepartmentName,
        r.RoleName,
        COALESCE(GROUP_CONCAT(DISTINCT b.BranchName ORDER BY b.BranchName SEPARATOR ', '), '—') AS Branches,
        (SELECT COUNT(*) FROM tbl_attendance a WHERE a.EmployeeID = e.EmployeeID AND a.AttendanceDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as attendance_count,
        (SELECT MAX(a.AttendanceDate) FROM tbl_attendance a WHERE a.EmployeeID = e.EmployeeID) as last_attendance,
        (SELECT COUNT(*) FROM employee_pins ep WHERE ep.EmployeeID = e.EmployeeID) as has_pin
    FROM tbl_employees e
    LEFT JOIN employee_branches eb ON e.EmployeeID = eb.EmployeeID
    LEFT JOIN tbl_branches b ON eb.BranchID = b.BranchID
    LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
    LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
    $whereSql
    GROUP BY e.EmployeeID, e.FullName, e.Username, e.PhoneNumber, e.IsSpecial, d.DepartmentName, r.RoleName
    ORDER BY e.FullName
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Quick stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_employees,
        COUNT(CASE WHEN IsSpecial = 1 THEN 1 END) as special_employees,
        COUNT(CASE WHEN IsSpecial = 0 THEN 1 END) as normal_employees,
        0 as new_employees
    FROM tbl_employees
")->fetch(PDO::FETCH_ASSOC);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="employees_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Employee ID', 'Full Name', 'Username', 'Phone Number', 
        'Department', 'Role', 'Branches', 'Status', 
        'Attendance (30 days)', 'Last Attendance', 'Has PIN'
    ]);
    
    // Fetch all matching employees for export (no pagination)
    $exportStmt = $conn->prepare("
        SELECT 
            e.EmployeeID, e.FullName, e.Username, e.PhoneNumber, e.IsSpecial,
            d.DepartmentName,
            r.RoleName,
            COALESCE(GROUP_CONCAT(DISTINCT b.BranchName ORDER BY b.BranchName SEPARATOR ', '), '—') AS Branches,
            (SELECT COUNT(*) FROM tbl_attendance a WHERE a.EmployeeID = e.EmployeeID AND a.AttendanceDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as attendance_count,
            (SELECT MAX(a.AttendanceDate) FROM tbl_attendance a WHERE a.EmployeeID = e.EmployeeID) as last_attendance,
            (SELECT COUNT(*) FROM employee_pins ep WHERE ep.EmployeeID = e.EmployeeID) as has_pin
        FROM tbl_employees e
        LEFT JOIN employee_branches eb ON e.EmployeeID = eb.EmployeeID
        LEFT JOIN tbl_branches b ON eb.BranchID = b.BranchID
        LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
        LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
        $whereSql
        GROUP BY e.EmployeeID, e.FullName, e.Username, e.PhoneNumber, e.IsSpecial, d.DepartmentName, r.RoleName
        ORDER BY e.FullName
    ");
    $exportStmt->execute($params);
    $exportEmployees = $exportStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // CSV data
    foreach ($exportEmployees as $emp) {
        fputcsv($output, [
            $emp['EmployeeID'],
            $emp['FullName'],
            $emp['Username'],
            $emp['PhoneNumber'],
            $emp['DepartmentName'] ?? 'N/A',
            $emp['RoleName'] ?? 'N/A',
            $emp['Branches'],
            $emp['IsSpecial'] ? 'Special' : 'Normal',
            $emp['attendance_count'],
            $emp['last_attendance'] ?? 'Never',
            $emp['has_pin'] ? 'Yes' : 'No'
        ]);
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - SignSync Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-header { 
            background: var(--primary-gradient); 
            color: white; 
            padding: 30px 0; 
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,.1);
        }
        
        .stat-card { 
            background: white; 
            border-radius: 15px; 
            padding: 25px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            transition: all 0.3s;
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-number { 
            font-size: 2.2rem; 
            font-weight: 700; 
            color: #667eea; 
            margin: 0;
        }
        
        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        
        .employee-table {
            margin-bottom: 0;
        }
        
        .employee-table thead {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .employee-table th {
            border: none;
            padding: 15px 12px;
            font-weight: 600;
            color: #495057;
            white-space: nowrap;
        }
        
        .employee-table td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f5;
        }
        
        .employee-table tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.002);
            transition: all 0.2s;
        }
        
        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .status-special {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .status-normal {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .attendance-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .attendance-high { background-color: #28a745; }
        .attendance-medium { background-color: #ffc107; }
        .attendance-low { background-color: #dc3545; }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            margin: 0 2px;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .pagination-wrapper {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .form-control {
            padding-left: 40px;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 5;
        }
        
        @media (max-width: 768px) {
            .container-fluid { padding: 15px; }
            .main-header { padding: 20px 0; }
            .stat-card { padding: 20px; margin-bottom: 15px; }
            .filters-section { padding: 20px; }
            .table-container { padding: 15px; }
            .employee-table { font-size: 0.9rem; }
            .action-btn { width: 28px; height: 28px; }
        }
        
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <!-- Back to Dashboard Button -->
    <a href="admin_dashboard.php" class="btn btn-primary back-btn" title="Back to Dashboard">
        <i class="bi bi-arrow-left"></i>
    </a>

    <!-- Main Header -->
    <div class="main-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-users me-3"></i>Employee Management
                    </h1>
                    <p class="mb-0 opacity-75">Manage and monitor all employees across your organization</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="add_employee.php" class="btn btn-light btn-lg me-2">
                        <i class="bi bi-person-plus me-2"></i>Add Employee
                    </a>
                    <button class="btn btn-outline-light" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Total Employees</h6>
                            <h2 class="stat-number"><?= number_format($stats['total_employees']) ?></h2>
                            <small class="text-muted">Active Users</small>
                        </div>
                        <i class="bi bi-people-fill text-primary" style="font-size: 3rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Special Employees</h6>
                            <h2 class="stat-number text-warning"><?= number_format($stats['special_employees']) ?></h2>
                            <small class="text-muted">Privileged Access</small>
                        </div>
                        <i class="bi bi-star-fill text-warning" style="font-size: 3rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Normal Employees</h6>
                            <h2 class="stat-number text-success"><?= number_format($stats['normal_employees']) ?></h2>
                            <small class="text-muted">Standard Access</small>
                        </div>
                        <i class="bi bi-person-fill text-success" style="font-size: 3rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">New This Month</h6>
                            <h2 class="stat-number text-info"><?= number_format($stats['new_employees']) ?></h2>
                            <small class="text-muted">Recent Additions</small>
                        </div>
                        <i class="bi bi-person-plus-fill text-info" style="font-size: 3rem; opacity: 0.7;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filters-section">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">
                        <i class="bi bi-search me-2"></i>Search Employees
                    </label>
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" id="search" class="form-control" 
                               placeholder="Search by ID, name, username..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <label for="branch" class="form-label">Branch</label>
                    <select name="branch" id="branch" class="form-select">
                        <option value="">All Branches</option>
                        <?php foreach ($branchOptions as $b): ?>
                            <option value="<?= $b['BranchID'] ?>" <?= $branchFilter == $b['BranchID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['BranchName']) ?> (<?= $b['employee_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="department" class="form-label">Department</label>
                    <select name="department" id="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departmentOptions as $d): ?>
                            <option value="<?= $d['DepartmentID'] ?>" <?= $departmentFilter == $d['DepartmentID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['DepartmentName']) ?> (<?= $d['employee_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="role" class="form-label">Role</label>
                    <select name="role" id="role" class="form-select">
                        <option value="">All Roles</option>
                        <?php foreach ($roleOptions as $r): ?>
                            <option value="<?= $r['RoleID'] ?>" <?= $roleFilter == $r['RoleID'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['RoleName']) ?> (<?= $r['employee_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="special" <?= $statusFilter === 'special' ? 'selected' : '' ?>>Special</option>
                        <option value="normal" <?= $statusFilter === 'normal' ? 'selected' : '' ?>>Normal</option>
                    </select>
                </div>
                
                <div class="col-md-1 d-flex align-items-end">
                    <div class="btn-group w-100" role="group">
                        <button type="submit" class="btn btn-primary" title="Apply Filters">
                            <i class="bi bi-funnel"></i>
                        </button>
                        <a href="view_employees.php" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0">
                    <i class="bi bi-list-ul me-2"></i>Employee Directory
                    <span class="badge bg-primary ms-2"><?= number_format($totalRecords) ?> found</span>
                </h5>
                <?php if ($search || $branchFilter || $departmentFilter || $roleFilter || $statusFilter): ?>
                    <small class="text-muted">
                        Filtered results 
                        <?php if ($search): ?>- Search: "<?= htmlspecialchars($search) ?>"<?php endif; ?>
                    </small>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="exportEmployees()">
                    <i class="bi bi-download me-1"></i>Export CSV
                </button>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="printEmployees()">
                            <i class="bi bi-printer me-2"></i>Print List
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="bulkActions()">
                            <i class="bi bi-check2-square me-2"></i>Bulk Actions
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- Employee Table -->
        <div class="table-container">
            <?php if (empty($employees)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-muted">No Employees Found</h4>
                    <p class="text-muted">No employees match your search criteria.</p>
                    <a href="add_employee.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>Add First Employee
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table employee-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Employee Info</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Contact</th>
                                <th>Branches</th>
                                <th>Attendance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($employees as $emp): ?>
                                <?php
                                    $initials = strtoupper(substr($emp['FullName'], 0, 1) . substr(strstr($emp['FullName'], ' '), 1, 1));
                                    $attendanceRate = $emp['attendance_count'];
                                    $attendanceClass = $attendanceRate >= 20 ? 'attendance-high' : 
                                                     ($attendanceRate >= 10 ? 'attendance-medium' : 'attendance-low');
                                    $lastAttendance = $emp['last_attendance'] ? date('M d', strtotime($emp['last_attendance'])) : 'Never';
                                ?>
                                <tr>
                                    <td>
                                        <div class="employee-avatar">
                                            <?= $initials ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($emp['FullName']) ?></h6>
                                            <small class="text-muted">
                                                ID: <?= htmlspecialchars($emp['EmployeeID']) ?><br>
                                                @<?= htmlspecialchars($emp['Username']) ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?= htmlspecialchars($emp['DepartmentName'] ?? 'No Department') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-white">
                                            <?= htmlspecialchars($emp['RoleName'] ?? 'No Role') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <small class="text-muted">
                                                <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($emp['PhoneNumber']) ?>
                                            </small>
                                            <?php if ($emp['has_pin']): ?>
                                                <br><small class="text-success">
                                                    <i class="bi bi-shield-check me-1"></i>PIN Set
                                                </small>
                                            <?php else: ?>
                                                <br><small class="text-warning">
                                                    <i class="bi bi-shield-exclamation me-1"></i>No PIN
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($emp['Branches']) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="attendance-indicator <?= $attendanceClass ?>"></span>
                                            <div>
                                                <small><?= $attendanceRate ?> days</small><br>
                                                <small class="text-muted">Last: <?= $lastAttendance ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($emp['IsSpecial']): ?>
                                            <span class="status-badge status-special">
                                                <i class="bi bi-star-fill me-1"></i>Special
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-normal">
                                                <i class="bi bi-person me-1"></i>Normal
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <button class="action-btn btn btn-outline-info btn-sm" 
                                                    title="View Details" 
                                                    onclick="viewEmployee('<?= $emp['EmployeeID'] ?>')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="edit_employee.php?id=<?= $emp['EmployeeID'] ?>" 
                                               class="action-btn btn btn-outline-primary btn-sm" 
                                               title="Edit Employee">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button class="action-btn btn btn-outline-danger btn-sm" 
                                                    title="Delete Employee" 
                                                    onclick="deleteEmployee('<?= $emp['EmployeeID'] ?>', '<?= htmlspecialchars($emp['FullName']) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-wrapper">
                        <div>
                            <small class="text-muted">
                                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $totalRecords) ?> of <?= number_format($totalRecords) ?> entries
                            </small>
                        </div>
                        <nav aria-label="Employee pagination">
                            <ul class="pagination mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php 
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- Employee Detail Modal -->
    <div class="modal fade" id="employeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-person-circle me-2"></i>Employee Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="employeeModalBody">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="editEmployeeBtn">
                        <i class="bi bi-pencil me-1"></i>Edit Employee
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit form on filter change
        document.addEventListener('DOMContentLoaded', function() {
            const filterInputs = ['branch', 'department', 'role', 'status'];
            filterInputs.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('change', function() {
                        this.form.submit();
                    });
                }
            });

            // Real-time search with debounce
            const searchInput = document.getElementById('search');
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });
        });

        // View employee details
        async function viewEmployee(employeeId) {
            const modal = new bootstrap.Modal(document.getElementById('employeeModal'));
            const modalBody = document.getElementById('employeeModalBody');
            const editBtn = document.getElementById('editEmployeeBtn');
            
            modalBody.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading employee details...</p>
                </div>
            `;
            
            modal.show();
            
            try {
                const response = await fetch(`view_employees.php?action=get_employee&id=${employeeId}`);
                const data = await response.json();
                
                if (data.success) {
                    const emp = data.employee;
                    modalBody.innerHTML = `
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="employee-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 1.5rem;">
                                    ${emp.FullName.charAt(0).toUpperCase()}${emp.FullName.split(' ')[1]?.charAt(0).toUpperCase() || ''}
                                </div>
                                <h5>${escapeHtml(emp.FullName)}</h5>
                                <p class="text-muted">${escapeHtml(emp.EmployeeID)}</p>
                            </div>
                            <div class="col-md-8">
                                <table class="table table-borderless">
                                    <tr><td><strong>Username:</strong></td><td>${escapeHtml(emp.Username)}</td></tr>
                                    <tr><td><strong>Phone:</strong></td><td>${escapeHtml(emp.PhoneNumber)}</td></tr>
                                    <tr><td><strong>Department:</strong></td><td>${escapeHtml(emp.DepartmentName || 'N/A')}</td></tr>
                                    <tr><td><strong>Role:</strong></td><td>${escapeHtml(emp.RoleName || 'N/A')}</td></tr>
                                    <tr><td><strong>Branches:</strong></td><td>${escapeHtml(emp.Branches || 'N/A')}</td></tr>
                                    <tr><td><strong>Status:</strong></td><td>
                                        ${emp.IsSpecial ? '<span class="status-badge status-special">Special</span>' : '<span class="status-badge status-normal">Normal</span>'}
                                    </td></tr>
                                    <tr><td><strong>PIN Status:</strong></td><td>
                                        ${emp.has_pin ? '<span class="badge bg-success">PIN Set</span>' : '<span class="badge bg-warning">No PIN</span>'}
                                    </td></tr>
                                </table>
                            </div>
                        </div>
                    `;
                    
                    editBtn.onclick = () => {
                        window.location.href = `edit_employee.php?id=${employeeId}`;
                    };
                } else {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Failed to load employee details: ${escapeHtml(data.message)}
                        </div>
                    `;
                }
            } catch (error) {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error loading employee details. Please try again.
                    </div>
                `;
            }
        }

        // Delete employee with confirmation
        function deleteEmployee(employeeId, employeeName) {
            if (confirm(`Are you sure you want to delete ${employeeName}?\n\nThis action cannot be undone and will remove all associated data.`)) {
                window.location.href = `delete_employee.php?id=${employeeId}`;
            }
        }

        // Export employees to CSV
        function exportEmployees() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('export', 'csv');
            window.location.href = currentUrl.toString();
        }

        // Print employee list
        function printEmployees() {
            window.print();
        }

        // Bulk actions placeholder
        function bulkActions() {
            alert('Bulk actions feature coming soon!');
        }

        // Utility function to escape HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Add print styles
        const printStyles = `
            @media print {
                .back-btn, .btn, .pagination-wrapper, .filters-section { display: none !important; }
                .main-header { background: #333 !important; -webkit-print-color-adjust: exact; }
                .table { font-size: 12px; }
                .employee-avatar { background: #333 !important; -webkit-print-color-adjust: exact; }
            }
        `;
        const styleSheet = document.createElement('style');
        styleSheet.textContent = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>