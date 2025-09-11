<?php
// filepath: c:\laragon\www\attendance_register\export_handler.php
session_start();
include 'db.php';
require 'vendor/autoload.php';
use Dompdf\Dompdf;

// Security: Ensure user is logged in and is an admin/hr
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied.');
}

$reportType = $_GET['report_type'] ?? '';
$exportType = $_GET['export'] ?? 'excel'; // default to excel

// Helper: Output CSV
function outputCsv($filename, $headers, $stmt) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        foreach ($row as $key => &$value) {
            if (is_null($value)) $value = 'N/A';
            // Detect phone number columns by header name
            if (stripos($key, 'phone') !== false && preg_match('/^\d{8,}$/', $value)) {
                // Force Excel to treat as text
                $value = '="' . $value . '"';
            }
        }
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Helper: Output PDF using Dompdf
function outputPdf($filename, $title, $headers, $stmt) {
    $dompdf = new Dompdf();

    $html = '<h2 style="text-align:center;">' . htmlspecialchars($title) . '</h2>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%"><thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell ?? 'N/A') . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}

// Main switch to handle different report types
switch ($reportType) {
    case 'daily_attendance':
        $date = $_GET['date'] ?? date('Y-m-d');
        $sql = "SELECT e.FullName, d.DepartmentName, r.RoleName, e.PhoneNumber, a.AttendanceDate, a.ClockIn, a.ClockOut, a.ClockInStatus,
                TIMEDIFF(a.ClockOut, a.ClockIn) AS WorkedHours
                FROM tbl_attendance a
                JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
                LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
                LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
                WHERE a.AttendanceDate = ?
                ORDER BY e.FullName";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$date]);
        $headers = ['Employee', 'Department', 'Role', 'Phone Number', 'Date', 'Clock In', 'Clock Out', 'Status', 'Worked Hours'];
        $title = "Daily Attendance Report ($date)";
        $filename = "daily_attendance_{$date}." . ($exportType === 'pdf' ? 'pdf' : 'csv');
        break;

    case 'date_range_attendance':
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        if (!$startDate || !$endDate) die("Start and End dates are required.");
        $sql = "SELECT e.FullName, d.DepartmentName, r.RoleName, e.PhoneNumber, a.AttendanceDate, a.ClockIn, a.ClockOut, a.ClockInStatus, TIMEDIFF(a.ClockOut, a.ClockIn) AS WorkedHours
                FROM tbl_attendance a
                JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
                LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
                LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
                WHERE a.AttendanceDate BETWEEN ? AND ?
                ORDER BY a.AttendanceDate, e.FullName";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        $headers = ['Employee', 'Department', 'Role', 'Phone Number', 'Date', 'Clock In', 'Clock Out', 'Status', 'Worked Hours'];
        $title = "Attendance Report ($startDate to $endDate)";
        $filename = "attendance_report_{$startDate}_to_{$endDate}." . ($exportType === 'pdf' ? 'pdf' : 'csv');
        break;

    case 'latecomers':
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        if (!$startDate || !$endDate) die("Start and End dates are required.");
        $sql = "SELECT e.FullName, d.DepartmentName, e.PhoneNumber, a.AttendanceDate, a.ClockIn, a.ClockInStatus,
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
        $headers = ['Employee', 'Department', 'Phone Number', 'Date', 'Clock In Time', 'Status', 'Late By (Minutes)'];
        $title = "Latecomers Report ($startDate to $endDate)";
        $filename = "latecomers_report_{$startDate}_to_{$endDate}." . ($exportType === 'pdf' ? 'pdf' : 'csv');
        break;

    case 'full_employee_list':
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
        $headers = ['Employee ID', 'Full Name', 'Username', 'Phone Number', 'Department', 'Role', 'Assigned Branches'];
        $title = "Full Employee List";
        $filename = "full_employee_list." . ($exportType === 'pdf' ? 'pdf' : 'csv');
        break;

    case 'contact_list':
        $sql = "SELECT FullName, Username, PhoneNumber FROM tbl_employees ORDER BY FullName";
        $stmt = $conn->query($sql);
        $headers = ['Full Name', 'Username', 'Phone Number'];
        $title = "Employee Contact List";
        $filename = "employee_contact_list." . ($exportType === 'pdf' ? 'pdf' : 'csv');
        break;

    default:
        header('HTTP/1.1 400 Bad Request');
        exit('Invalid report type specified.');
}

// Output as PDF or CSV
if ($exportType === 'pdf') {
    outputPdf($filename, $title, $headers, $stmt);
} else {
    outputCsv($filename, $headers, $stmt);
}
?>