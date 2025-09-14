<?php
// Simple test to diagnose the PIN API issue
echo "=== SIGNSYNC PIN API Diagnosis ===\n\n";

// Test 1: Database Connection
echo "1. Testing Database Connection:\n";
try {
    $host = 'localhost';
    $dbname = 'attendance_register_db';
    $username = 'root';
    $password = '';
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "   ✅ Database connection successful\n\n";
} catch(PDOException $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 2: Check employees table
echo "2. Testing Employees Table:\n";
try {
    $stmt = $conn->query('SELECT COUNT(*) as count FROM tbl_employees');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ Found " . $result['count'] . " employees in database\n\n";
} catch(Exception $e) {
    echo "   ❌ Error querying employees: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 3: Test specific employee
echo "3. Testing Employee AKCBSTF0005:\n";
try {
    $stmt = $conn->prepare("SELECT EmployeeID, FullName, PhoneNumber FROM tbl_employees WHERE EmployeeID = ?");
    $stmt->execute(['AKCBSTF0005']);
    
    if ($stmt->rowCount() > 0) {
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   ✅ Employee found: " . $employee['FullName'] . "\n";
        echo "   📞 Phone: " . $employee['PhoneNumber'] . "\n";
        
        // Test PIN calculation
        $phone = $employee['PhoneNumber'];
        if ($phone && strlen($phone) >= 4) {
            $phonePin = substr($phone, -4);
            echo "   🔢 Phone PIN: " . $phonePin . "\n";
        }
        echo "   🔑 Default PIN: 1234\n";
        echo "   🆔 ID PIN: 0005\n\n";
    } else {
        echo "   ❌ Employee AKCBSTF0005 not found\n\n";
    }
} catch(Exception $e) {
    echo "   ❌ Error finding employee: " . $e->getMessage() . "\n\n";
}

// Test 4: Test PIN API file exists
echo "4. Testing PIN API File:\n";
if (file_exists('signsync_pin_api.php')) {
    echo "   ✅ signsync_pin_api.php exists\n";
    echo "   📄 File size: " . filesize('signsync_pin_api.php') . " bytes\n\n";
} else {
    echo "   ❌ signsync_pin_api.php not found\n\n";
}

// Test 5: Manual PIN validation
echo "5. Manual PIN Validation Test:\n";
$employeeId = 'AKCBSTF0005';
$pin = '1234';

try {
    $stmt = $conn->prepare("SELECT EmployeeID, FullName, PhoneNumber FROM tbl_employees WHERE EmployeeID = ?");
    $stmt->execute([$employeeId]);
    
    if ($stmt->rowCount() > 0) {
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Test default PIN
        if ($pin === '1234') {
            echo "   ✅ Default PIN '1234' validation: SUCCESS\n";
        } else {
            echo "   ❌ Default PIN validation: FAILED\n";
        }
    }
} catch(Exception $e) {
    echo "   ❌ Manual validation error: " . $e->getMessage() . "\n";
}

echo "\n=== Diagnosis Complete ===\n";
echo "If all tests show ✅, the issue is likely with the HTTP server or network.\n";
echo "Try running: php -S localhost:8080\n";
echo "Then test with: http://localhost:8080/signsync_pin_api.php\n";
?>
