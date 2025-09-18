<?php
// Test the enhanced_clockinout_api.php with test mode

$postData = [
    'employee_id' => 'EMP001',
    'action' => 'clock_out',  // Test clock out since we just clocked in
    'latitude' => '5.6037',
    'longitude' => '-0.1870',
    'test_mode' => 'true'
];

$url = 'http://localhost:8080/enhanced_clockinout_api.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

echo "Testing enhanced_clockinout_api.php with test mode:\n";
echo "URL: $url\n";
echo "Data: " . json_encode($postData, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_error($ch)) {
    echo "CURL Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
}

curl_close($ch);
?>
