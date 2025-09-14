<?php
echo "SIGNSYNC PIN API Test\n";
echo "=====================\n";

// Test PIN validation for the employees we found
$testCases = [
    ['employee_id' => 'AKCBSTF0005', 'pin' => '1602', 'description' => 'STEPHEN SARFO with phone PIN'],
    ['employee_id' => 'AKCBSTF0005', 'pin' => '1234', 'description' => 'STEPHEN SARFO with default PIN'],
    ['employee_id' => 'AKCBSTF0005', 'pin' => '0005', 'description' => 'STEPHEN SARFO with ID PIN'],
    ['employee_id' => 'EMP001', 'pin' => '7890', 'description' => 'John Doe with phone PIN'],
    ['employee_id' => 'EMP001', 'pin' => '1234', 'description' => 'John Doe with default PIN'],
    ['employee_id' => 'EMP001', 'pin' => '0001', 'description' => 'John Doe with ID PIN'],
    ['employee_id' => 'INVALID', 'pin' => '1234', 'description' => 'Invalid employee test'],
    ['employee_id' => 'AKCBSTF0005', 'pin' => '9999', 'description' => 'Valid employee, wrong PIN']
];

foreach ($testCases as $test) {
    echo "\nTesting: " . $test['description'] . "\n";
    
    $data = json_encode([
        'employee_id' => $test['employee_id'],
        'pin' => $test['pin']
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $data
        ]
    ]);
    
    $result = @file_get_contents('http://localhost/attendance_register/signsync_pin_api.php', false, $context);
    
    if ($result === false) {
        echo "  ❌ API call failed\n";
    } else {
        $response = json_decode($result, true);
        if ($response['success']) {
            echo "  ✅ SUCCESS: " . $response['message'] . "\n";
            echo "     Employee: " . $response['data']['name'] . "\n";
            echo "     PIN Source: " . $response['data']['pin_source'] . "\n";
        } else {
            echo "  ❌ FAILED: " . $response['message'] . "\n";
        }
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "API Test Complete!\n";
echo "If all tests show ✅, your PIN system is working.\n";
echo "Update your Android app's IP address to your server IP.\n";
?>
