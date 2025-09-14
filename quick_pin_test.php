<?php
include 'db.php';

// Quick employee check and PIN test
$stmt = $conn->query("SELECT EmployeeID, FullName, PhoneNumber FROM tbl_employees LIMIT 1");
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if ($employee) {
    echo "Testing with Employee: {$employee['EmployeeID']}\n";
    echo "Name: {$employee['FullName']}\n";
    echo "Phone: {$employee['PhoneNumber']}\n\n";
    
    // Test default PIN
    echo "Testing PIN 1234...\n";
    
    // Include the PIN validation logic directly
    include 'signsync_pin_api.php';
    
} else {
    echo "No employees found. Please add an employee first.\n";
}
?>
