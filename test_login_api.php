<?php
/**
 * Quick Authentication API Test
 * Test the enhanced login_api.php with real examples
 */
echo "🧪 Testing Enhanced Login API\n";
echo str_repeat("=", 40) . "\n\n";

// Test 1: Valid PIN login
echo "Test 1: Valid PIN login (AKCBSTF0005 with PIN 5678)\n";
$postData = [
    'employee_id' => 'AKCBSTF0005',
    'pin' => '5678'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/login_api.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 2: Invalid PIN
echo "Test 2: Invalid PIN login (AKCBSTF0005 with PIN 1111)\n";
$postData = [
    'employee_id' => 'AKCBSTF0005',
    'pin' => '1111'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/login_api.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 3: Custom PIN for employee who got one generated
echo "Test 3: Custom PIN login (EMP001 with PIN 8218)\n";
$postData = [
    'employee_id' => 'EMP001',
    'pin' => '8218'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/login_api.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

echo "✅ Login API tests completed!\n";
?>
