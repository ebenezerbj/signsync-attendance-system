<?php
/**
 * PIN System Test Script
 * Tests the PIN validation functionality
 */

echo "🔐 PIN System Test\n";
echo str_repeat("=", 50) . "\n\n";

include 'db.php';

// Test 1: Check if employees exist
echo "1. Checking Employee Data:\n";
try {
    $stmt = $conn->query("SELECT EmployeeID, FullName, PhoneNumber FROM tbl_employees LIMIT 5");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($employees) > 0) {
        echo "   ✅ Found " . count($employees) . " employees\n";
        foreach ($employees as $emp) {
            echo "   - {$emp['EmployeeID']}: {$emp['FullName']} (Phone: {$emp['PhoneNumber']})\n";
        }
    } else {
        echo "   ❌ No employees found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Test PIN generation logic for first employee
if (!empty($employees)) {
    $testEmployee = $employees[0];
    echo "2. Testing PIN Logic for Employee: {$testEmployee['EmployeeID']}\n";
    
    // Test phone-based PIN
    if (!empty($testEmployee['PhoneNumber']) && strlen($testEmployee['PhoneNumber']) >= 4) {
        $phonePin = substr($testEmployee['PhoneNumber'], -4);
        echo "   📱 Phone-based PIN: {$phonePin}\n";
    }
    
    // Test ID-based PIN
    preg_match('/(\d+)$/', $testEmployee['EmployeeID'], $matches);
    if (!empty($matches[1])) {
        $idPin = str_pad($matches[1], 4, '0', STR_PAD_LEFT);
        echo "   🆔 ID-based PIN: {$idPin}\n";
    }
    
    echo "   🔑 Default PIN: 1234\n";
    echo "\n";
}

// Test 3: Test PIN API endpoint
echo "3. Testing PIN API Endpoint:\n";
if (!empty($employees)) {
    $testEmployee = $employees[0];
    $testPin = !empty($testEmployee['PhoneNumber']) ? substr($testEmployee['PhoneNumber'], -4) : '1234';
    
    // Prepare test data
    $testData = [
        'employee_id' => $testEmployee['EmployeeID'],
        'pin' => $testPin
    ];
    
    echo "   Testing with Employee: {$testEmployee['EmployeeID']}, PIN: {$testPin}\n";
    
    // Simulate API call
    $url = 'http://localhost:8080/signsync_pin_api.php';
    $data = json_encode($testData);
    
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
    
    if ($response && $httpCode == 200) {
        $result = json_decode($response, true);
        if ($result && isset($result['success'])) {
            if ($result['success']) {
                echo "   ✅ PIN API Test: SUCCESS\n";
                echo "   📋 Response: {$result['message']}\n";
                if (isset($result['data']['pin_source'])) {
                    echo "   🔍 PIN Source: {$result['data']['pin_source']}\n";
                }
            } else {
                echo "   ❌ PIN API Test: FAILED\n";
                echo "   📋 Error: {$result['message']}\n";
            }
        } else {
            echo "   ❌ Invalid API response format\n";
        }
    } else {
        echo "   ❌ API request failed (HTTP: $httpCode)\n";
        echo "   📋 Response: $response\n";
    }
} else {
    echo "   ⚠️  No employees to test with\n";
}

echo "\n";

// Test 4: Test default PIN
echo "4. Testing Default PIN (1234):\n";
if (!empty($employees)) {
    $testEmployee = $employees[0];
    
    $testData = [
        'employee_id' => $testEmployee['EmployeeID'],
        'pin' => '1234'
    ];
    
    echo "   Testing with Employee: {$testEmployee['EmployeeID']}, PIN: 1234\n";
    
    $url = 'http://localhost:8080/signsync_pin_api.php';
    $data = json_encode($testData);
    
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
    
    if ($response && $httpCode == 200) {
        $result = json_decode($response, true);
        if ($result && isset($result['success'])) {
            if ($result['success']) {
                echo "   ✅ Default PIN Test: SUCCESS\n";
                echo "   📋 Response: {$result['message']}\n";
                echo "   🔍 PIN Source: {$result['data']['pin_source']}\n";
            } else {
                echo "   ❌ Default PIN Test: FAILED\n";
                echo "   📋 Error: {$result['message']}\n";
            }
        } else {
            echo "   ❌ Invalid API response format\n";
        }
    } else {
        echo "   ❌ API request failed (HTTP: $httpCode)\n";
    }
}

echo "\n";
echo str_repeat("=", 50) . "\n";
echo "🔐 PIN System Test Complete\n";
?>
