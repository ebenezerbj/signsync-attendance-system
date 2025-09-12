<?php
// Test WearOS API endpoint on dev server
$testData = json_encode(['action' => 'ping', 'device_type' => 'android_watch']);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/wearos_api.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $testData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Testing WearOS API Endpoint (Dev Server)\n";
echo "========================================\n";
echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}
echo "Response: $response\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "\n✓ API endpoint is working correctly!\n";
        echo "Server time: " . date('Y-m-d H:i:s', $data['data']['server_time']) . "\n";
        echo "API version: " . $data['data']['api_version'] . "\n";
    } else {
        echo "\n✗ API returned unsuccessful response\n";
    }
} else {
    echo "\n✗ API endpoint error\n";
}

// Test health data submission
echo "\n\nTesting Health Data Submission:\n";
echo "==============================\n";

$healthData = json_encode([
    'action' => 'submit_health_data',
    'employee_id' => 'EMP001',
    'heart_rate' => 85,
    'stress_level' => 6.5,
    'temperature' => 36.8,
    'steps' => 5000,
    'timestamp' => time(),
    'device_type' => 'android_watch'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/wearos_api.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $healthData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$healthResponse = curl_exec($ch);
$healthHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Health Data HTTP Code: $healthHttpCode\n";
echo "Health Data Response: $healthResponse\n";

// Test stress alert
echo "\n\nTesting Stress Alert:\n";
echo "====================\n";

$stressData = json_encode([
    'action' => 'stress_alert',
    'employee_id' => 'EMP001',
    'heart_rate' => 105,
    'stress_level' => 8.5,
    'alert_type' => 'high_stress',
    'urgent' => true,
    'timestamp' => time()
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/wearos_api.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $stressData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$stressResponse = curl_exec($ch);
$stressHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Stress Alert HTTP Code: $stressHttpCode\n";
echo "Stress Alert Response: $stressResponse\n";

echo "\nWearOS API comprehensive testing completed!\n";
?>
