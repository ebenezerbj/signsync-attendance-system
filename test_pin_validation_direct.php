<?php
echo "Direct PIN API Test (No HTTP)\n";
echo "=============================\n";

// Simulate the PIN API directly
include 'db.php';

// Test data
$testCases = [
    ['employee_id' => 'AKCBSTF0005', 'pin' => '1602'],
    ['employee_id' => 'AKCBSTF0005', 'pin' => '1234'],
    ['employee_id' => 'EMP001', 'pin' => '1234'],
];

foreach ($testCases as $test) {
    echo "\nTesting Employee: {$test['employee_id']} with PIN: {$test['pin']}\n";
    
    try {
        $employeeId = trim($test['employee_id']);
        $pin = trim($test['pin']);
        
        // Query employee data
        $stmt = $conn->prepare("
            SELECT e.EmployeeID, e.FullName, e.DepartmentID, d.DepartmentName,
                   e.Username, e.PhoneNumber, e.BranchID, b.BranchName,
                   e.Password
            FROM tbl_employees e
            LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
            WHERE e.EmployeeID = ?
        ");
        
        $stmt->execute([$employeeId]);
        
        if ($stmt->rowCount() === 0) {
            echo "  ❌ Employee not found\n";
            continue;
        }
        
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  📋 Found employee: " . $employee['FullName'] . "\n";
        echo "  📞 Phone: " . ($employee['PhoneNumber'] ?? 'N/A') . "\n";
        
        // PIN Validation
        $validPin = false;
        $pinSource = '';
        
        // Strategy 1: Last 4 digits of phone number
        if (!empty($employee['PhoneNumber']) && strlen($employee['PhoneNumber']) >= 4) {
            $phonePin = substr($employee['PhoneNumber'], -4);
            echo "  🔢 Phone PIN would be: " . $phonePin . "\n";
            if ($pin === $phonePin) {
                $validPin = true;
                $pinSource = 'phone';
            }
        }
        
        // Strategy 2: Default PIN "1234"
        if (!$validPin && $pin === '1234') {
            $validPin = true;
            $pinSource = 'default';
            echo "  🔑 Default PIN accepted\n";
        }
        
        // Strategy 3: Check if PIN matches the actual password
        if (!$validPin && password_verify($pin, $employee['Password'])) {
            $validPin = true;
            $pinSource = 'password';
            echo "  🔒 Password PIN accepted\n";
        }
        
        // Strategy 4: Simple PIN based on employee ID suffix
        if (!$validPin) {
            preg_match('/(\d+)$/', $employeeId, $matches);
            if (!empty($matches[1])) {
                $numericPart = str_pad($matches[1], 4, '0', STR_PAD_LEFT);
                echo "  🆔 ID PIN would be: " . $numericPart . "\n";
                if ($pin === $numericPart) {
                    $validPin = true;
                    $pinSource = 'employee_id';
                }
            }
        }
        
        if ($validPin) {
            echo "  ✅ SUCCESS! PIN validated via: " . $pinSource . "\n";
        } else {
            echo "  ❌ PIN validation failed\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Direct validation test complete!\n";
?>
