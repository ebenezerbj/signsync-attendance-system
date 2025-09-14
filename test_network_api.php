<?php
echo "<h2>Network API Test</h2>";

// Test if files exist
echo "<h3>File Existence Check:</h3>";
echo "signsync_pin_api.php: " . (file_exists('signsync_pin_api.php') ? '✅ EXISTS' : '❌ NOT FOUND') . "<br>";
echo "wearos_api.php: " . (file_exists('wearos_api.php') ? '✅ EXISTS' : '❌ NOT FOUND') . "<br>";

echo "<h3>Current Directory:</h3>";
echo getcwd() . "<br>";

echo "<h3>PIN API Test:</h3>";
// Test PIN API directly
$_POST['employee_id'] = 'EMP001';
$_POST['pin'] = '1234';

// Capture output from PIN API
ob_start();
include 'signsync_pin_api.php';
$pin_result = ob_get_clean();

echo "<strong>PIN API Response:</strong><br>";
echo "<pre>" . htmlspecialchars($pin_result) . "</pre>";

echo "<h3>Network Configuration:</h3>";
echo "Server IP: " . $_SERVER['SERVER_ADDR'] . "<br>";
echo "Server Port: " . $_SERVER['SERVER_PORT'] . "<br>";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "<br>";

echo "<h3>API URLs for WearOS:</h3>";
echo "PIN API: http://192.168.0.189:8080/signsync_pin_api.php<br>";
echo "Clock API: http://192.168.0.189:8080/wearos_api.php<br>";
?>
