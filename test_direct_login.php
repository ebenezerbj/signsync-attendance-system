<?php
/**
 * Direct Login API Test (without curl)
 */
echo "🧪 Direct Login API Test\n";
echo str_repeat("=", 40) . "\n\n";

// Simulate POST data
$_POST = [
    'employee_id' => 'AKCBSTF0005',
    'pin' => '5678'
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestAgent';

echo "Test 1: Valid PIN login (AKCBSTF0005 with PIN 5678)\n";
echo "POST data: " . json_encode($_POST) . "\n";

// Capture output
ob_start();
include 'login_api.php';
$response = ob_get_clean();

echo "Response: $response\n\n";

// Test 2: Invalid PIN
$_POST = [
    'employee_id' => 'AKCBSTF0005',
    'pin' => '1111'
];

echo "Test 2: Invalid PIN login (AKCBSTF0005 with PIN 1111)\n";
echo "POST data: " . json_encode($_POST) . "\n";

ob_start();
include 'login_api.php';
$response = ob_get_clean();

echo "Response: $response\n\n";

// Test 3: Custom PIN
$_POST = [
    'employee_id' => 'EMP001',
    'pin' => '8218'
];

echo "Test 3: Custom PIN login (EMP001 with PIN 8218)\n";
echo "POST data: " . json_encode($_POST) . "\n";

ob_start();
include 'login_api.php';
$response = ob_get_clean();

echo "Response: $response\n\n";

echo "✅ Direct API tests completed!\n";
?>
