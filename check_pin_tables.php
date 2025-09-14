<?php
include 'db.php';

echo "Checking authentication system:\n";
echo "================================\n\n";

// Check employee_pins table
try {
    $stmt = $conn->query('DESCRIBE employee_pins');
    echo "employee_pins table exists:\n";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  " . $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    echo "\n";
    
    // Check how many employees have custom pins
    $stmt = $conn->query('SELECT COUNT(*) as count FROM employee_pins');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Custom PINs in employee_pins: " . $result['count'] . "\n\n";
    
} catch (Exception $e) {
    echo "employee_pins table does not exist: " . $e->getMessage() . "\n\n";
}

// Check employees with CustomPIN field
echo "Employees with CustomPIN in tbl_employees:\n";
$stmt = $conn->query('SELECT EmployeeID, FullName, CustomPIN, PINSetupComplete, Password FROM tbl_employees WHERE CustomPIN IS NOT NULL');
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($employees) . " employees with CustomPIN set:\n";
foreach($employees as $emp) {
    echo "  " . $emp['EmployeeID'] . " - " . $emp['FullName'] . " | PIN: " . $emp['CustomPIN'] . " | Setup: " . ($emp['PINSetupComplete'] ? 'Yes' : 'No') . " | Password: " . (empty($emp['Password']) ? 'NONE' : 'SET') . "\n";
}

echo "\n";

// Check all employees authentication status
echo "All employees authentication status:\n";
$stmt = $conn->query('SELECT EmployeeID, FullName, CustomPIN, PINSetupComplete, Password FROM tbl_employees LIMIT 10');
$all_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($all_employees as $emp) {
    $pin_status = empty($emp['CustomPIN']) ? 'DEFAULT(1234)' : $emp['CustomPIN'];
    $password_status = empty($emp['Password']) ? 'NONE' : 'SET';
    echo "  " . $emp['EmployeeID'] . " - " . $emp['FullName'] . "\n";
    echo "    PIN: " . $pin_status . " | Password: " . $password_status . "\n";
}
?>
