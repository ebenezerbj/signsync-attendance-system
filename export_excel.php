<?php
include 'db.php';

// Fetch data with employee full name
$stmt = $conn->prepare("
    SELECT 
        a.AttendanceID, 
        a.AttendanceDate, 
        a.ClockIn, 
        a.ClockOut, 
        e.FullName, 
        e.PhoneNumber,         -- Add this line
        d.DepartmentName, 
        r.RoleName, 
        b.BranchName
    FROM tbl_attendance a
    LEFT JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
    LEFT JOIN tbl_branches b ON a.BranchID = b.BranchID
    LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
    LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
    ORDER BY a.AttendanceDate DESC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data array
$data = [];
$data[] = [
    'Attendance ID', 'Attendance Date', 'Clock In', 'Clock Out', 'Full Name', 'Phone Number', 'Department', 'Role', 'Branch'
];
foreach ($rows as $row) {
    $phone = $row['PhoneNumber'] ?? '';
    if ($phone !== '') {
        $phone = '="' . $phone . '"';
    }
    $data[] = [
        $row['AttendanceID'] ?? '',
        $row['AttendanceDate'] ?? '',
        $row['ClockIn'] ?? '',
        $row['ClockOut'] ?? '',
        $row['FullName'] ?? '',
        $phone,
        $row['DepartmentName'] ?? '',
        $row['RoleName'] ?? '',
        $row['BranchName'] ?? ''
    ];
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="attendance.csv"');

$output = fopen('php://output', 'w');
foreach ($data as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;