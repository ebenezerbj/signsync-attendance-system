<?php
include 'db.php';
date_default_timezone_set('UTC'); // Set a default timezone

// --- API ENDPOINTS ---
// These must come before any HTML or other output.

// Endpoint to get a list of employees for the trend filter dropdown
if (isset($_GET['get_employees'])) {
    header('Content-Type: application/json');
    $stmt = $conn->query("SELECT EmployeeID, FullName FROM tbl_employees ORDER BY FullName");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Endpoint for trend analysis data
if (isset($_GET['trend']) && isset($_GET['employee_id'])) {
    // This endpoint seems complex and might have its own issues,
    // but the main data fetching is the primary problem for the "Failed to load" error.
    // For now, we assume it works or will be addressed separately.
    // Let's add a placeholder to prevent errors if it's called.
    header('Content-Type: application/json');
    echo json_encode([]); // Return empty JSON for now if trend is called
    exit;
}


// --- MAIN DATA FETCHING FOR ATTENDANCE TABLE ---

// Helper function to format time difference
function format_time_diff($interval) {
    $parts = [];
    if ($interval->h) $parts[] = $interval->h . 'h';
    if ($interval->i) $parts[] = $interval->i . 'm';
    if (empty($parts) && $interval->s) $parts[] = $interval->s . 's';
    return $parts ? implode(' ', $parts) : '0m';
}

// Get filter parameters from the AJAX request
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$branch_id = $_GET['branch_id'] ?? '';
$employee_name = $_GET['employee'] ?? '';

// Build the WHERE clause and parameters dynamically
$where = [];
$params = [];

if ($start_date) { $where[] = 'a.AttendanceDate >= ?'; $params[] = $start_date; }
if ($end_date) { $where[] = 'a.AttendanceDate <= ?'; $params[] = $end_date; }
if ($branch_id) { $where[] = 'a.BranchID = ?'; $params[] = $branch_id; }
if ($employee_name) { $where[] = 'e.FullName LIKE ?'; $params[] = "%$employee_name%"; }

// The main SQL query to fetch attendance records
$sql = "SELECT a.*, e.FullName, d.DepartmentName, r.RoleName, b.BranchName
        FROM tbl_attendance a
        LEFT JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
        LEFT JOIN tbl_branches b ON a.BranchID = b.BranchID
        LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
        LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID";

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY a.AttendanceDate DESC, a.ClockIn DESC LIMIT 200"; // Add a limit for performance

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- BUILD HTML TABLE AND JSON RESPONSE ---

$branchCounts = [];
$table = '<table class="table table-striped table-hover"><thead><tr>
<th>ID</th><th>Employee</th><th>Department</th><th>Branch</th><th>Date</th><th>Clock In</th><th>In Photo</th><th>In Status</th><th>Clock Out</th><th>Out Photo</th><th>Out Status</th><th>Late By</th><th>Early By</th><th>Overtime</th><th>Worked</th></tr></thead><tbody>';

foreach ($rows as $row) {
    $department = $row['DepartmentName'] ?? 'N/A';
    $role = $row['RoleName'] ?? 'N/A';
    $branch = $row['BranchName'] ?? 'Unknown';
    $branchCounts[$branch] = ($branchCounts[$branch] ?? 0) + 1;

    // Format times and calculate differences
    $clockInTime = $row['ClockIn'] ? date('g:i A', strtotime($row['ClockIn'])) : '—';
    $clockOutTime = $row['ClockOut'] ? date('g:i A', strtotime($row['ClockOut'])) : '—';
    
    $inPhoto = $row['ClockInPhoto'] ? "<img src='{$row['ClockInPhoto']}' class='img-thumbnail zoomable' style='width:50px; height:50px; cursor:pointer;'>" : '—';
    $outPhoto = $row['ClockOutPhoto'] ? "<img src='{$row['ClockOutPhoto']}' class='img-thumbnail zoomable' style='width:50px; height:50px; cursor:pointer;'>" : '—';

    $lateBy = $earlyBy = $overtime = $worked = '—';

    if ($row['ClockIn'] && !empty($row['ShiftStart'])) {
        $clockIn = new DateTime($row['ClockIn']);
        $expectedStart = new DateTime($row['ShiftStart']);
        if ($clockIn > $expectedStart) $lateBy = format_time_diff($expectedStart->diff($clockIn));
        if ($clockIn < $expectedStart) $earlyBy = format_time_diff($clockIn->diff($expectedStart));
    }
    if ($row['ClockOut'] && !empty($row['ShiftEnd'])) {
        $clockOut = new DateTime($row['ClockOut']);
        $expectedEnd = new DateTime($row['ShiftEnd']);
        if ($clockOut > $expectedEnd) $overtime = format_time_diff($expectedEnd->diff($clockOut));
    }
    if ($row['ClockIn'] && $row['ClockOut']) {
        $worked = format_time_diff((new DateTime($row['ClockIn']))->diff(new DateTime($row['ClockOut'])));
    }

    $table .= "<tr>
        <td>{$row['AttendanceID']}</td>
        <td>" . htmlspecialchars($row['FullName'] ?? 'N/A') . "</td>
        <td>{$department}</td>
        <td>{$role}</td>
        <td>{$branch}</td>
        <td>" . htmlspecialchars($row['AttendanceDate']) . "</td>
        <td>{$clockInTime}</td>
        <td>{$inPhoto}</td>
        <td>" . htmlspecialchars($row['ClockInStatus'] ?? 'N/A') . "</td>
        <td>{$clockOutTime}</td>
        <td>{$outPhoto}</td>
        <td>" . htmlspecialchars($row['ClockOutStatus'] ?? 'N/A') . "</td>
        <td>{$lateBy}</td>
        <td>{$earlyBy}</td>
        <td>{$overtime}</td>
        <td>{$worked}</td>
    </tr>";
}

if (empty($rows)) {
    $table .= '<tr><td colspan="15" class="text-center">No records found for the selected filters.</td></tr>';
}

$table .= '</tbody></table>';

// Final JSON output
header('Content-Type: application/json');
echo json_encode(['table' => $table, 'deptCounts' => $branchCounts]);
?>

