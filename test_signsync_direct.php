<?php
echo "SIGNSYNC PIN API Direct Test (PHP Dev Server)\n";
echo "=============================================\n";

// Test PIN validation for the employees we found
$testCases = [
    ['employee_id' => 'AKCBSTF0005', 'pin' => '1602', 'description' => 'STEPHEN SARFO with phone PIN'],
    ['employee_id' => 'AKCBSTF0005', 'pin' => '1234', 'description' => 'STEPHEN SARFO with default PIN'],
    ['employee_id' => 'EMP001', 'pin' => '1234', 'description' => 'John Doe with default PIN'],
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
            'content' => $data,
            'timeout' => 10
        ]
    ]);
    
    $result = @file_get_contents('http://localhost:8080/signsync_pin_api.php', false, $context);
    
    if ($result === false) {
        echo "  ❌ API call failed - checking error\n";
        $error = error_get_last();
        echo "  Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    } else {
        $response = json_decode($result, true);
        if ($response && isset($response['success'])) {
            if ($response['success']) {
                echo "  ✅ SUCCESS: " . $response['message'] . "\n";
                echo "     Employee: " . $response['data']['name'] . "\n";
                echo "     PIN Source: " . $response['data']['pin_source'] . "\n";
            } else {
                echo "  ❌ FAILED: " . $response['message'] . "\n";
            }
        } else {
            echo "  ❌ Invalid response format\n";
            echo "  Raw response: " . substr($result, 0, 200) . "\n";
        }
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Direct PHP API Test Complete!\n";
?>
