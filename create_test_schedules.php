<?php
include 'db.php';

echo "=== Creating Test Schedule ===\n";

// Create a schedule for AKCBSTF0005 for today (Sunday)
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

try {
    // First check existing schedules
    $stmt = $conn->prepare('SELECT EmployeeID, DayOfWeek, StartTime, EndTime FROM tbl_employee_schedules WHERE EmployeeID = "AKCBSTF0005"');
    $stmt->execute();
    $existing = $stmt->fetchAll();
    
    echo "Existing schedules for AKCBSTF0005:\n";
    foreach ($existing as $row) {
        echo "- " . $row['DayOfWeek'] . " " . $row['StartTime'] . "-" . $row['EndTime'] . "\n";
    }
    
    // Add Sunday schedule if it doesn't exist
    $stmt = $conn->prepare('SELECT COUNT(*) FROM tbl_employee_schedules WHERE EmployeeID = "AKCBSTF0005" AND DayOfWeek = "Sunday"');
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $stmt = $conn->prepare('INSERT INTO tbl_employee_schedules (EmployeeID, DayOfWeek, StartTime, EndTime, IsHoliday) VALUES ("AKCBSTF0005", "Sunday", "09:00:00", "17:00:00", 0)');
        $stmt->execute();
        echo "✓ Added Sunday schedule for AKCBSTF0005 (9:00-17:00)\n";
    } else {
        echo "✓ Sunday schedule already exists for AKCBSTF0005\n";
    }
    
    // Also add schedule for EMP001 (Monday to Friday)
    foreach ($days as $day) {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM tbl_employee_schedules WHERE EmployeeID = "EMP001" AND DayOfWeek = ?');
        $stmt->execute([$day]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $stmt = $conn->prepare('INSERT INTO tbl_employee_schedules (EmployeeID, DayOfWeek, StartTime, EndTime, IsHoliday) VALUES ("EMP001", ?, "08:00:00", "16:00:00", 0)');
            $stmt->execute([$day]);
            echo "✓ Added {$day} schedule for EMP001 (8:00-16:00)\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
