<?php
// Test login exactly as Android app would call it
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

echo "=== ANDROID LOGIN TEST ===\n";
echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Employee ID: " . ($_POST['employee_id'] ?? 'NOT SET') . "\n";
echo "PIN: " . ($_POST['pin'] ?? 'NOT SET') . "\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "❌ This endpoint requires POST method\n";
    echo "Test with: curl -X POST -d 'employee_id=EMP001&pin=1234' http://192.168.0.189:8080/login_test.php\n";
    exit;
}

$employee_id = $_POST['employee_id'] ?? '';
$pin = $_POST['pin'] ?? '';

if (empty($employee_id) || empty($pin)) {
    echo "❌ Missing employee_id or pin\n";
    echo json_encode(['success' => false, 'message' => 'Employee ID and PIN are required']);
    exit;
}

echo "Testing login for: $employee_id with PIN: $pin\n";

try {
    include 'db.php';
    echo "✅ Database connected\n";
    
    // Check if it's default PIN (1234)
    if ($pin === '1234') {
        echo "✅ Using default PIN\n";
        
        // Validate employee exists
        $stmt = $conn->prepare("SELECT EmployeeID, FullName, PhoneNumber, BranchID FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            echo "✅ Employee found: " . $employee['FullName'] . "\n";
            echo "✅ LOGIN SUCCESS\n\n";
            echo json_encode([
                'success' => true,
                'message' => 'Login successful with default PIN',
                'is_first_login' => true,
                'employee_id' => $employee['EmployeeID']
            ]);
        } else {
            echo "❌ Employee not found\n";
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
        }
    } else {
        echo "✅ Using custom PIN\n";
        
        // Check custom PIN
        $stmt = $conn->prepare("
            SELECT e.EmployeeID, e.FullName, e.PhoneNumber, e.BranchID 
            FROM tbl_employees e 
            LEFT JOIN employee_pins ep ON e.EmployeeID = ep.EmployeeID 
            WHERE e.EmployeeID = ? AND ep.pin = ?
        ");
        $stmt->execute([$employee_id, $pin]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            echo "✅ Employee found with custom PIN\n";
            echo "✅ LOGIN SUCCESS\n\n";
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'is_first_login' => false,
                'employee_id' => $employee['EmployeeID']
            ]);
        } else {
            echo "❌ Invalid custom PIN\n";
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID or PIN']);
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
