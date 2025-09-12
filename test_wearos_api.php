<?php
// Test WearOS API endpoint
$testData = json_encode(['action' => 'ping', 'device_type' => 'android_watch']);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/attendance_register/wearos_api.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $testData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Testing WearOS API Endpoint\n";
echo "==========================\n";
echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}
echo "Response: $response\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        echo "\n✓ API endpoint is working correctly!\n";
        echo "Server time: " . date('Y-m-d H:i:s', $data['data']['server_time']) . "\n";
        echo "API version: " . $data['data']['api_version'] . "\n";
    } else {
        echo "\n✗ API returned unsuccessful response\n";
    }
} else {
    echo "\n✗ API endpoint error\n";
}

// Test authentication endpoint
echo "\n\nTesting Authentication (mock data):\n";
echo "===================================\n";

$authData = json_encode([
    'action' => 'authenticate_employee',
    'employee_id' => 'EMP001',
    'pin' => '1234'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/attendance_register/wearos_api.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $authData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$authResponse = curl_exec($ch);
$authHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Auth HTTP Code: $authHttpCode\n";
echo "Auth Response: $authResponse\n";

echo "\nWearOS API testing completed!\n";
?>
