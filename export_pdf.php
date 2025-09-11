<?php
include 'db.php';

// Fetch data with employee full name
$stmt = $conn->prepare("
    SELECT 
        a.AttendanceID, 
        a.EmployeeID, 
        e.FullName, 
        a.Date, 
        a.ClockIn, 
        a.ClockOut, 
        a.ClockInPhoto, 
        a.ClockOutPhoto, 
        a.ClockInStatus,
        a.ClockOutStatus,
        a.Status, 
        a.Remarks
    FROM tbl_attendance a
    LEFT JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
    ORDER BY a.Date DESC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for status badge color
function badgeColor($status) {
    switch ($status) {
        case 'Early': return '#388e3c'; // green
        case 'On Time': return '#1976d2'; // blue
        case 'Late': return '#d32f2f'; // red
        case 'Left Early': return '#fbc02d'; // yellow
        case 'Overtime': return '#7b1fa2'; // purple
        case "Didn't Clock Out": return '#757575'; // gray
        default: return '#888';
    }
}

// Build HTML table
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Attendance Register</title>
    <style>
        body { font-family: Arial, sans-serif; }
        h2 { text-align: center; }
        table { border-collapse: collapse; width: 100%; margin: 0 auto; }
        th, td { border: 1px solid #333; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #fafafa; }
        img { max-width: 60px; max-height: 60px; border-radius: 4px; }
        .badge { padding: 4px 8px; border-radius: 4px; color: #fff; font-size: 12px; display: inline-block; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
    <button onclick="window.print()">Download PDF</button>
    <h2>Attendance Register</h2>
    <table>
        <tr>
            <th>Attendance ID</th>
            <th>Employee</th>
            <th>Date</th>
            <th>Clock In</th>
            <th>Clock In Photo</th>
            <th>Clock In Status</th>
            <th>Clock Out</th>
            <th>Clock Out Photo</th>
            <th>Clock Out Status</th>
            <th>Status</th>
            <th>Remarks</th>
        </tr>';

foreach ($rows as $row) {
    // Clock In Status badge
    $ci_status = $row['ClockInStatus'] ?? '';
    $ci_color = badgeColor($ci_status);
    $ci_badge = $ci_status ? "<span class='badge' style='background:$ci_color;'>$ci_status</span>" : '';

    // Clock Out Status badge
    if (empty($row['ClockOut'])) {
        $co_status = "Didn't Clock Out";
        $co_color = badgeColor($co_status);
    } else {
        $co_status = $row['ClockOutStatus'] ?? '';
        $co_color = badgeColor($co_status);
    }
    $co_badge = $co_status ? "<span class='badge' style='background:$co_color;'>$co_status</span>" : '';

    // Images
    $clockInPhoto = !empty($row['ClockInPhoto'])
        ? '<img src="uploads/' . htmlspecialchars($row['ClockInPhoto']) . '" alt="Clock In Photo">'
        : 'No Image';
    $clockOutPhoto = !empty($row['ClockOutPhoto'])
        ? '<img src="uploads/' . htmlspecialchars($row['ClockOutPhoto']) . '" alt="Clock Out Photo">'
        : 'No Image';

    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($row['AttendanceID'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['FullName'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['Date'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['ClockIn'] ?? '') . '</td>';
    $html .= '<td>' . $clockInPhoto . '</td>';
    $html .= '<td>' . $ci_badge . '</td>';
    $html .= '<td>' . htmlspecialchars($row['ClockOut'] ?? '') . '</td>';
    $html .= '<td>' . $clockOutPhoto . '</td>';
    $html .= '<td>' . $co_badge . '</td>';
    $html .= '<td>' . htmlspecialchars($row['Status'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['Remarks'] ?? '') . '</td>';
    $html .= '</tr>';
}

$html .= '
    </table>
    <script>window.print();</script>
</body>
</html>';

echo $html;
exit;