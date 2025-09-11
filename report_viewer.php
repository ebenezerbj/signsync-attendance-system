<?php
// filepath: c:\laragon\www\attendance_register\report_viewer.php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    exit('Access Denied.');
}

$reportType = $_GET['report_type'] ?? '';

// Validate report type
$validReportTypes = ['daily_attendance', 'date_range_attendance', 'latecomers', 'full_employee_list', 'contact_list'];
if (empty($reportType)) {
    echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>No report type specified. Please select a report type.</div>';
    exit;
}

if (!in_array($reportType, $validReportTypes)) {
    echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Invalid report type: "' . htmlspecialchars($reportType) . '". Valid types are: ' . implode(', ', $validReportTypes) . '</div>';
    exit;
}

if ($reportType === 'daily_attendance') {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Invalid date format. Please use YYYY-MM-DD format.</div>';
        exit;
    }
    
    try {
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
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }

} elseif ($reportType === 'date_range_attendance') {
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    if (!$startDate || !$endDate) {
        echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Start and End dates are required for date range report.</div>';
        exit;
    }
    
    // Validate date formats
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Invalid date format. Please use YYYY-MM-DD format.</div>';
        exit;
    }
    
    try {
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
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }

} elseif ($reportType === 'latecomers') {
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    if (!$startDate || !$endDate) {
        echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Start and End dates are required for latecomers report.</div>';
        exit;
    }
    
    // Validate date formats
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Invalid date format. Please use YYYY-MM-DD format.</div>';
        exit;
    }
    
    try {
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
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }

} elseif ($reportType === 'full_employee_list') {
    $deptId = $_GET['department_id'] ?? null;
    
    try {
        $sql = "SELECT e.EmployeeID, e.FullName, e.Username, e.PhoneNumber, d.DepartmentName, r.RoleName,
                       COALESCE(GROUP_CONCAT(b.BranchName SEPARATOR ', '), 'N/A') AS Branches
                FROM tbl_employees e
                LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
                LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
                LEFT JOIN employee_branches eb ON e.EmployeeID = eb.EmployeeID
                LEFT JOIN tbl_branches b ON eb.BranchID = b.BranchID";
        $params = [];
        if ($deptId && is_numeric($deptId)) {
            $sql .= " WHERE e.DepartmentID = ?";
            $params[] = $deptId;
        }
        $sql .= " GROUP BY e.EmployeeID ORDER BY e.FullName";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }

} elseif ($reportType === 'contact_list') {
    try {
        $sql = "SELECT FullName, Username, PhoneNumber FROM tbl_employees ORDER BY FullName";
        $stmt = $conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }
}

if (empty($rows)) {
    echo '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No records found for the selected filters.</div>';
    exit;
}

echo '<div class="table-responsive"><table class="table table-hover table-bordered table-sm">';
echo '<thead class="table-primary"><tr>';
foreach (array_keys($rows[0]) as $col) {
    // Clean up column names for display
    $displayName = $col;
    switch($col) {
        case 'ClockInPhoto': $displayName = 'Clock In Photo'; break;
        case 'FullName': $displayName = 'Full Name'; break;
        case 'DepartmentName': $displayName = 'Department'; break;
        case 'RoleName': $displayName = 'Role'; break;
        case 'AttendanceDate': $displayName = 'Date'; break;
        case 'ClockIn': $displayName = 'Clock In'; break;
        case 'ClockOut': $displayName = 'Clock Out'; break;
        case 'ClockInStatus': $displayName = 'Status'; break;
        case 'WorkedHours': $displayName = 'Worked Hours'; break;
        case 'LateByMinutes': $displayName = 'Late By (Minutes)'; break;
        case 'EmployeeID': $displayName = 'Employee ID'; break;
        case 'PhoneNumber': $displayName = 'Phone Number'; break;
    }
    echo '<th class="text-nowrap">' . htmlspecialchars($displayName) . '</th>';
}
echo '</tr></thead><tbody>';
foreach ($rows as $row) {
    echo '<tr>';
    foreach ($row as $col => $cell) {
        if ($col === 'ClockInPhoto' && $cell) {
            echo '<td class="text-center"><img src="uploads/' . htmlspecialchars($cell) . '" alt="Clock In Photo" class="img-thumbnail" style="max-width:60px;max-height:60px;"></td>';
        } elseif ($col === 'ClockInStatus') {
            $badgeClass = '';
            switch(strtolower($cell ?? '')) {
                case 'on time': $badgeClass = 'bg-success'; break;
                case 'late': $badgeClass = 'bg-warning text-dark'; break;
                case 'absent': $badgeClass = 'bg-danger'; break;
                default: $badgeClass = 'bg-secondary';
            }
            echo '<td><span class="badge ' . $badgeClass . '">' . htmlspecialchars($cell ?? 'N/A') . '</span></td>';
        } elseif ($col === 'WorkedHours' && $cell) {
            echo '<td class="text-end font-monospace">' . htmlspecialchars($cell) . '</td>';
        } elseif ($col === 'LateByMinutes') {
            $minutes = intval($cell ?? 0);
            if ($minutes > 0) {
                echo '<td class="text-end"><span class="text-danger fw-bold">' . $minutes . ' min</span></td>';
            } else {
                echo '<td class="text-center text-muted">-</td>';
            }
        } else {
            echo '<td>' . htmlspecialchars($cell ?? 'N/A') . '</td>';
        }
    }
    echo '</tr>';
}
echo '</tbody></table></div>';

// Add record count info
echo '<div class="mt-2"><small class="text-muted"><i class="bi bi-info-circle me-1"></i>Showing ' . count($rows) . ' record(s)</small></div>';
exit;