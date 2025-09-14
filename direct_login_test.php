<?php
echo "=== DIRECT LOGIN API TEST ===\n";
echo "Testing login_api.php functionality directly...\n\n";

// Simulate POST data
$_POST['employee_id'] = 'EMP001';
$_POST['pin'] = '1234';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Capture output
ob_start();

// Include the login API
include 'login_api.php';

// Get the output
$output = ob_get_clean();

echo "Output from login_api.php:\n";
echo $output . "\n";

// Test with wrong credentials
echo "\n=== TESTING WITH WRONG CREDENTIALS ===\n";
$_POST['employee_id'] = 'EMP001';
$_POST['pin'] = '9999';

ob_start();
include 'login_api.php';
$output2 = ob_get_clean();

echo "Output with wrong PIN:\n";
echo $output2 . "\n";
?>
