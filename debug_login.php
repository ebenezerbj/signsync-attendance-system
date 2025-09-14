<?php
// Debug login API issue
echo "=== LOGIN API DEBUG ===\n";

// Set up proper environment
$_POST['employee_id'] = 'EMP001';
$_POST['pin'] = '1234';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Check if employee exists first
try {
    include 'db.php';
    
    echo "1. Database Connection: ✅\n";
    
    // Check if employee EMP001 exists
    $stmt = $conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
    $stmt->execute(['EMP001']);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        echo "2. Employee EMP001: ✅ EXISTS\n";
        echo "   - Name: " . ($employee['FirstName'] ?? 'N/A') . " " . ($employee['LastName'] ?? 'N/A') . "\n";
        echo "   - Status: " . ($employee['Status'] ?? 'N/A') . "\n";
    } else {
        echo "2. Employee EMP001: ❌ NOT FOUND\n";
        echo "   Creating test employee...\n";
        
        // Create test employee
        $stmt = $conn->prepare("INSERT INTO tbl_employees (EmployeeID, FirstName, LastName, Status, BranchID) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['EMP001', 'Test', 'Employee', 'Active', 1]);
        echo "   ✅ Test employee created\n";
    }
    
    // Check employee_pins table
    $stmt = $conn->prepare("SELECT * FROM employee_pins WHERE EmployeeID = ?");
    $stmt->execute(['EMP001']);
    $pin_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pin_record) {
        echo "3. Custom PIN: ✅ EXISTS (PIN: " . $pin_record['pin'] . ")\n";
    } else {
        echo "3. Custom PIN: ❌ NONE (will use default 1234)\n";
    }
    
    echo "\n4. Testing Login Logic:\n";
    
    // Test default PIN logic
    if ($_POST['pin'] === '1234') {
        echo "   ✅ PIN 1234 matches default\n";
        
        // Check employee exists and is active
        $stmt = $conn->prepare("SELECT EmployeeID FROM tbl_employees WHERE EmployeeID = ? AND Status = 'Active'");
        $stmt->execute([$_POST['employee_id']]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($emp) {
            echo "   ✅ Employee is active\n";
            echo "   ✅ LOGIN SHOULD SUCCEED\n";
        } else {
            echo "   ❌ Employee not found or inactive\n";
        }
    } else {
        echo "   ❌ PIN " . $_POST['pin'] . " is not default PIN\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n5. Testing Actual Login API:\n";

// Create a clean environment for login_api.php
unset($_POST);
$_POST['employee_id'] = 'EMP001';
$_POST['pin'] = '1234';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Capture and test login API
ob_start();
try {
    // Include login API without headers
    $login_code = file_get_contents('login_api.php');
    // Remove header() calls for testing
    $login_code = preg_replace('/header\([^)]+\);/', '// header removed for testing', $login_code);
    $login_code = str_replace('<?php', '', $login_code);
    eval($login_code);
} catch (Exception $e) {
    echo "Error in login API: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();

echo "API Output: " . $output . "\n";

// Try to parse JSON
$json = json_decode($output, true);
if ($json) {
    echo "Parsed Response:\n";
    echo "- Success: " . ($json['success'] ? 'true' : 'false') . "\n";
    echo "- Message: " . $json['message'] . "\n";
} else {
    echo "❌ Invalid JSON response\n";
}
?>
