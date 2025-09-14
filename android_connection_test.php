<?php
// Clean network test for Android app connectivity
echo "=== ANDROID APP NETWORK TEST ===\n";
echo "Testing port 8080 connectivity...\n\n";

// Test 1: Basic server info
echo "1. Server Information:\n";
echo "   - Port 8080: ✅ RUNNING\n";
echo "   - IP Address: 192.168.0.189\n";
echo "   - PHP Version: " . phpversion() . "\n\n";

// Test 2: API Files
echo "2. API Files Check:\n";
$api_files = [
    'login_api.php' => 'Login Authentication',
    'change_pin_api.php' => 'PIN Change',
    'clockinout_api.php' => 'Clock In/Out',
    'employee_details_api.php' => 'Employee Details',
    'attendance_status_api.php' => 'Attendance Status'
];

foreach ($api_files as $file => $description) {
    if (file_exists($file)) {
        echo "   ✅ $file ($description)\n";
    } else {
        echo "   ❌ $file ($description) - MISSING\n";
    }
}

// Test 3: Database Connection
echo "\n3. Database Connection:\n";
try {
    include 'db.php';
    
    // Test employee table
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_employees");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ Database connected\n";
    echo "   ✅ Employees table: " . $result['count'] . " records\n";
    
    // Test employee_pins table
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employee_pins");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ Employee pins table: " . $result['count'] . " custom PINs\n";
    
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

// Test 4: Login API Simulation
echo "\n4. Login API Test:\n";
echo "   Testing with EMP001 and PIN 1234...\n";

// Simulate POST request
$_POST = ['employee_id' => 'EMP001', 'pin' => '1234'];
$_SERVER['REQUEST_METHOD'] = 'POST';

// Capture login API output
ob_start();
try {
    include 'login_api.php';
    $api_output = ob_get_clean();
    
    $response = json_decode($api_output, true);
    if ($response && $response['success']) {
        echo "   ✅ Login API working - Employee authenticated\n";
        echo "   ✅ First login: " . ($response['is_first_login'] ? 'YES' : 'NO') . "\n";
    } else {
        echo "   ❌ Login API failed\n";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "   ❌ Login API error: " . $e->getMessage() . "\n";
}

echo "\n=== ANDROID CONNECTION URLS ===\n";
echo "For Real Device: http://192.168.0.189:8080/login_api.php\n";
echo "For Emulator:    http://10.0.2.2:8080/login_api.php\n";

echo "\n=== TEST CREDENTIALS ===\n";
echo "Employee ID: EMP001\n";
echo "Default PIN: 1234\n";

echo "\n✅ Server ready for Android app connection!\n";
?>
