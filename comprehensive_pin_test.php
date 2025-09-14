<?php
/**
 * Comprehensive PIN Testing Script
 * Tests all PIN validation scenarios
 */

echo "🔐 Comprehensive PIN System Test\n";
echo str_repeat("=", 60) . "\n\n";

include 'db.php';

// Function to test PIN via API
function testPinAPI($employeeId, $pin, $testName) {
    $url = 'http://localhost:8080/signsync_pin_api.php';
    $data = json_encode(['employee_id' => $employeeId, 'pin' => $pin]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "🧪 $testName\n";
    echo "   Employee: $employeeId, PIN: $pin\n";
    
    if ($response && $httpCode == 200) {
        $result = json_decode($response, true);
        if ($result && isset($result['success'])) {
            if ($result['success']) {
                echo "   ✅ SUCCESS - {$result['message']}\n";
                echo "   👤 Employee: {$result['data']['name']}\n";
                echo "   🏢 Department: " . ($result['data']['department'] ?? 'N/A') . "\n";
                echo "   🔍 PIN Source: {$result['data']['pin_source']}\n";
            } else {
                echo "   ❌ FAILED - {$result['message']}\n";
            }
        } else {
            echo "   ❌ Invalid response format\n";
        }
    } else {
        echo "   ❌ API Error (HTTP: $httpCode)\n";
        if ($response) echo "   📋 Response: $response\n";
    }
    echo "\n";
}

// Get employees from database
echo "📋 Getting employee data...\n";
try {
    $stmt = $conn->query("SELECT EmployeeID, FullName, PhoneNumber FROM tbl_employees ORDER BY EmployeeID LIMIT 5");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($employees) > 0) {
        echo "✅ Found " . count($employees) . " employees\n\n";
        
        foreach ($employees as $index => $emp) {
            echo "Employee " . ($index + 1) . ": {$emp['EmployeeID']} - {$emp['FullName']}\n";
            echo "Phone: {$emp['PhoneNumber']}\n";
            
            // Calculate expected PINs
            $phonePIN = !empty($emp['PhoneNumber']) && strlen($emp['PhoneNumber']) >= 4 
                ? substr($emp['PhoneNumber'], -4) 
                : null;
            
            preg_match('/(\d+)$/', $emp['EmployeeID'], $matches);
            $idPIN = !empty($matches[1]) 
                ? str_pad($matches[1], 4, '0', STR_PAD_LEFT) 
                : null;
            
            echo "Expected Phone PIN: " . ($phonePIN ?? 'N/A') . "\n";
            echo "Expected ID PIN: " . ($idPIN ?? 'N/A') . "\n";
            echo str_repeat("-", 40) . "\n";
        }
        echo "\n";
        
        // Test each employee with different PIN methods
        foreach ($employees as $emp) {
            echo "🔬 Testing Employee: {$emp['EmployeeID']}\n";
            echo str_repeat("=", 50) . "\n";
            
            // Test 1: Default PIN (1234)
            testPinAPI($emp['EmployeeID'], '1234', 'Default PIN Test');
            
            // Test 2: Phone-based PIN
            if (!empty($emp['PhoneNumber']) && strlen($emp['PhoneNumber']) >= 4) {
                $phonePIN = substr($emp['PhoneNumber'], -4);
                testPinAPI($emp['EmployeeID'], $phonePIN, 'Phone-based PIN Test');
            }
            
            // Test 3: ID-based PIN
            preg_match('/(\d+)$/', $emp['EmployeeID'], $matches);
            if (!empty($matches[1])) {
                $idPIN = str_pad($matches[1], 4, '0', STR_PAD_LEFT);
                testPinAPI($emp['EmployeeID'], $idPIN, 'ID-based PIN Test');
            }
            
            // Test 4: Invalid PIN
            testPinAPI($emp['EmployeeID'], '9999', 'Invalid PIN Test (should fail)');
            
            echo str_repeat("=", 50) . "\n\n";
        }
        
    } else {
        echo "❌ No employees found. Creating test employee...\n";
        
        // Create test employee
        $stmt = $conn->prepare("
            INSERT INTO tbl_employees (EmployeeID, FullName, Username, Password, PhoneNumber, BranchID, DepartmentID, RoleID, CategoryID, IsSpecial) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([
                'TEST001',
                'Test Employee',
                'testuser',
                password_hash('test123', PASSWORD_DEFAULT),
                '+1234567890',
                'GH1510010',
                1,
                1,
                1,
                0
            ]);
            
            echo "✅ Created test employee TEST001\n";
            echo "📱 Phone: +1234567890 (PIN: 7890)\n";
            echo "🆔 ID: TEST001 (PIN: 0001)\n\n";
            
            // Test the new employee
            testPinAPI('TEST001', '1234', 'Test Employee - Default PIN');
            testPinAPI('TEST001', '7890', 'Test Employee - Phone PIN');
            testPinAPI('TEST001', '0001', 'Test Employee - ID PIN');
            
        } catch (Exception $e) {
            echo "❌ Error creating test employee: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo str_repeat("=", 60) . "\n";
echo "🏁 PIN System Test Complete\n";
echo str_repeat("=", 60) . "\n";
?>
