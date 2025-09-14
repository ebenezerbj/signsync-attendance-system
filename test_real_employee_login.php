<?php
// Test login API with real employee data
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo "=== TESTING LOGIN WITH REAL EMPLOYEES ===\n\n";

$test_cases = [
    ['employee_id' => 'AKCBSTF0005', 'pin' => '1234', 'description' => 'STEPHEN SARFO with default PIN'],
    ['employee_id' => 'AKCBSTFADMIN', 'pin' => '1234', 'description' => 'System Admin with default PIN'],
    ['employee_id' => 'EMP001', 'pin' => '1234', 'description' => 'EMP001 with default PIN'],
    ['employee_id' => 'EMP001', 'pin' => '5678', 'description' => 'EMP001 with custom PIN'],
    ['employee_id' => 'INVALID123', 'pin' => '1234', 'description' => 'Invalid employee ID'],
    ['employee_id' => 'AKCBSTF0005', 'pin' => '9999', 'description' => 'Valid employee with wrong PIN']
];

include 'db.php';

foreach ($test_cases as $test) {
    echo "Testing: " . $test['description'] . "\n";
    echo "Employee ID: " . $test['employee_id'] . ", PIN: " . $test['pin'] . "\n";
    
    // Simulate the login API call
    $_POST = [
        'employee_id' => $test['employee_id'],
        'pin' => $test['pin']
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Capture output from login API
    ob_start();
    try {
        // Execute the same logic as login_api.php
        $employee_id = $_POST['employee_id'];
        $pin = $_POST['pin'];
        
        if ($pin === '1234') {
            // Default PIN - check if employee exists
            $stmt = $conn->prepare("SELECT EmployeeID, FullName, PhoneNumber, BranchID FROM tbl_employees WHERE EmployeeID = ?");
            $stmt->execute([$employee_id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful with default PIN',
                    'is_first_login' => true,
                    'employee_id' => $employee['EmployeeID']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
            }
        } else {
            // Custom PIN - check employee_pins table
            $stmt = $conn->prepare("
                SELECT e.EmployeeID, e.FullName, e.PhoneNumber, e.BranchID 
                FROM tbl_employees e 
                INNER JOIN employee_pins ep ON e.EmployeeID = ep.EmployeeID 
                WHERE e.EmployeeID = ? AND ep.pin = ?
            ");
            $stmt->execute([$employee_id, $pin]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'is_first_login' => false,
                    'employee_id' => $employee['EmployeeID']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid employee ID or PIN']);
            }
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    
    $output = ob_get_clean();
    $response = json_decode($output, true);
    
    if ($response) {
        if ($response['success']) {
            echo "✅ SUCCESS: " . $response['message'] . "\n";
            if (isset($response['is_first_login'])) {
                echo "   First login: " . ($response['is_first_login'] ? 'YES' : 'NO') . "\n";
            }
        } else {
            echo "❌ FAILED: " . $response['message'] . "\n";
        }
    } else {
        echo "❌ INVALID RESPONSE: " . $output . "\n";
    }
    
    echo str_repeat("-", 60) . "\n\n";
}

echo "\n=== RECOMMENDED LOGIN CREDENTIALS FOR ANDROID APP ===\n";
echo "Real Employee: AKCBSTF0005 / 1234 (Stephen Sarfo)\n";
echo "Admin Account: AKCBSTFADMIN / 1234 (System Admin)\n";
echo "Test Account:  EMP001 / 5678 (Custom PIN) or EMP001 / 1234 (Default)\n";
?>
