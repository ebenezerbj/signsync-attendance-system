<?php
include 'db.php';

echo "SIGNSYNC PIN Reset Utility\n";
echo "==========================\n";

if (isset($argv[1])) {
    $employeeId = $argv[1];
    
    try {
        // Reset PIN for specific employee
        $stmt = $conn->prepare("
            UPDATE tbl_employees 
            SET CustomPIN = NULL, PINSetupComplete = 0 
            WHERE EmployeeID = ?
        ");
        $stmt->execute([$employeeId]);
        
        if ($stmt->rowCount() > 0) {
            echo "✅ Reset PIN for employee: $employeeId\n";
            echo "They can now login with default PIN '1234' and set up a new custom PIN.\n";
        } else {
            echo "❌ Employee not found: $employeeId\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "Usage: php reset_employee_pin.php EMPLOYEE_ID\n";
    echo "Example: php reset_employee_pin.php AKCBSTF0005\n\n";
    
    echo "Available employees:\n";
    try {
        $stmt = $conn->query("
            SELECT EmployeeID, FullName, 
                   CASE WHEN CustomPIN IS NOT NULL THEN 'Has Custom PIN' ELSE 'Using Default' END as PINStatus
            FROM tbl_employees 
            ORDER BY EmployeeID
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - " . $row['EmployeeID'] . " (" . $row['FullName'] . ") - " . $row['PINStatus'] . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error listing employees: " . $e->getMessage() . "\n";
    }
}
?>
