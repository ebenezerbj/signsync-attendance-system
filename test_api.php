<?php
// Test login API using cURL with Laragon
$url = 'http://localhost/attendance_register/login_api.php';
$data = array(
    'employee_id' => 'EMP001',
    'pin' => '1234'
);

// Use cURL for POST request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/x-www-form-urlencoded'
));

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "=== LOGIN API TEST ===\n";
echo "URL: " . $url . "\n";
echo "Data: employee_id=EMP001, pin=1234\n";
echo "HTTP Code: " . $httpCode . "\n";

if ($error) {
    echo "cURL Error: " . $error . "\n";
} else {
    echo "Response: " . $result . "\n";
    
    // Try to decode JSON
    $json = json_decode($result, true);
    if ($json) {
        echo "\nParsed JSON:\n";
        echo "- Success: " . ($json['success'] ? 'true' : 'false') . "\n";
        echo "- Message: " . $json['message'] . "\n";
        if (isset($json['is_first_login'])) {
            echo "- First Login: " . ($json['is_first_login'] ? 'true' : 'false') . "\n";
        }
        if (isset($json['employee_id'])) {
            echo "- Employee ID: " . $json['employee_id'] . "\n";
        }
    }
}

echo "\n=== TESTING WITH WRONG PIN ===\n";
$data2 = array(
    'employee_id' => 'EMP001',
    'pin' => '9999'
);

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $url);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($data2));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/x-www-form-urlencoded'
));

$result2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP Code: " . $httpCode2 . "\n";
echo "Response: " . $result2 . "\n";
?>
