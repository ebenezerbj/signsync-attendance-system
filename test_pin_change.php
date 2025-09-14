<?php
echo "=== CHANGE PIN API TEST ===\n";

// Test changing PIN from default 1234 to custom PIN
$_POST['employee_id'] = 'EMP001';
$_POST['current_pin'] = '1234';
$_POST['new_pin'] = '5678';
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
include 'change_pin_api.php';
$output = ob_get_clean();

echo "Change PIN Result:\n";
echo $output . "\n";

// Now test login with new PIN
echo "\n=== TESTING LOGIN WITH NEW PIN ===\n";
$_POST['employee_id'] = 'EMP001';
$_POST['pin'] = '5678';

ob_start();
include 'login_api.php';
$output2 = ob_get_clean();

echo "Login with new PIN:\n";
echo $output2 . "\n";

// Test login with old PIN (should fail)
echo "\n=== TESTING LOGIN WITH OLD PIN (should fail) ===\n";
$_POST['employee_id'] = 'EMP001';
$_POST['pin'] = '1234';

ob_start();
include 'login_api.php';
$output3 = ob_get_clean();

echo "Login with old PIN:\n";
echo $output3 . "\n";
?>
