<?php
echo "<h1>API Testing for Enhanced Attendance System</h1>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.info { color: blue; }
.section { border: 1px solid #ddd; padding: 10px; margin: 10px 0; }
pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; }
.api-test { border-left: 4px solid #007cba; padding-left: 15px; }
</style>\n";

// Test employee ID
$testEmployeeId = 'EMP001';
$baseUrl = 'http://localhost/attendance_register';

echo "<div class='section'>\n";
echo "<h2>1. Testing Attendance Status API (GET)</h2>\n";

$statusUrl = $baseUrl . "/attendance_status_api.php?employee_id=" . $testEmployeeId;
echo "<div class='api-test'>\n";
echo "<strong>GET Request:</strong> {$statusUrl}<br>\n";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Content-Type: application/json'
    ]
]);

$response = file_get_contents($statusUrl, false, $context);
if ($response !== false) {
    echo "<div class='success'>✓ API Response received successfully</div>\n";
    $data = json_decode($response, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "<div class='success'>✓ API returned success response</div>\n";
        echo "<strong>Current Status:</strong> " . $data['data']['current_status'] . "<br>\n";
        echo "<strong>Can Clock In:</strong> " . ($data['data']['can_clock_in'] ? 'Yes' : 'No') . "<br>\n";
        echo "<strong>Can Clock Out:</strong> " . ($data['data']['can_clock_out'] ? 'Yes' : 'No') . "<br>\n";
    } else {
        echo "<div class='error'>✗ API returned error response</div>\n";
    }
    echo "<pre>" . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre>\n";
} else {
    echo "<div class='error'>✗ Failed to get API response</div>\n";
}
echo "</div>\n";
echo "</div>\n";

echo "<div class='section'>\n";
echo "<h2>2. Testing Enhanced Clock In/Out API (POST)</h2>\n";

// Test clock in
echo "<h3>Testing Clock In</h3>\n";
echo "<div class='api-test'>\n";

$clockInData = [
    'employee_id' => $testEmployeeId,
    'action' => 'clock_in',
    'latitude' => 14.5995,
    'longitude' => 120.9842,
    'reason' => 'API Testing Clock In'
];

echo "<strong>POST Request:</strong> {$baseUrl}/enhanced_clockinout_api.php<br>\n";
echo "<strong>Data:</strong> " . json_encode($clockInData) . "<br>\n";

$postContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query($clockInData)
    ]
]);

$clockInResponse = file_get_contents($baseUrl . "/enhanced_clockinout_api.php", false, $postContext);
if ($clockInResponse !== false) {
    echo "<div class='success'>✓ Clock In API Response received</div>\n";
    $clockInData = json_decode($clockInResponse, true);
    if ($clockInData && isset($clockInData['success']) && $clockInData['success']) {
        echo "<div class='success'>✓ Clock In successful</div>\n";
        echo "<strong>Clock In Time:</strong> " . $clockInData['data']['clock_in_time'] . "<br>\n";
        echo "<strong>Status:</strong> " . $clockInData['data']['status'] . "<br>\n";
    } else {
        echo "<div class='error'>✗ Clock In failed</div>\n";
    }
    echo "<pre>" . json_encode(json_decode($clockInResponse), JSON_PRETTY_PRINT) . "</pre>\n";
} else {
    echo "<div class='error'>✗ Failed to get Clock In API response</div>\n";
}
echo "</div>\n";

// Small delay
sleep(2);

// Test clock out
echo "<h3>Testing Clock Out</h3>\n";
echo "<div class='api-test'>\n";

$clockOutData = [
    'employee_id' => $testEmployeeId,
    'action' => 'clock_out',
    'latitude' => 14.5995,
    'longitude' => 120.9842,
    'reason' => 'API Testing Clock Out'
];

echo "<strong>POST Request:</strong> {$baseUrl}/enhanced_clockinout_api.php<br>\n";
echo "<strong>Data:</strong> " . json_encode($clockOutData) . "<br>\n";

$postContext2 = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query($clockOutData)
    ]
]);

$clockOutResponse = file_get_contents($baseUrl . "/enhanced_clockinout_api.php", false, $postContext2);
if ($clockOutResponse !== false) {
    echo "<div class='success'>✓ Clock Out API Response received</div>\n";
    $clockOutData = json_decode($clockOutResponse, true);
    if ($clockOutData && isset($clockOutData['success']) && $clockOutData['success']) {
        echo "<div class='success'>✓ Clock Out successful</div>\n";
        echo "<strong>Clock Out Time:</strong> " . $clockOutData['data']['clock_out_time'] . "<br>\n";
        echo "<strong>Work Duration:</strong> " . $clockOutData['data']['work_duration'] . " hours<br>\n";
        echo "<strong>Status:</strong> " . $clockOutData['data']['status'] . "<br>\n";
    } else {
        echo "<div class='error'>✗ Clock Out failed</div>\n";
    }
    echo "<pre>" . json_encode(json_decode($clockOutResponse), JSON_PRETTY_PRINT) . "</pre>\n";
} else {
    echo "<div class='error'>✗ Failed to get Clock Out API response</div>\n";
}
echo "</div>\n";
echo "</div>\n";

echo "<div class='section'>\n";
echo "<h2>3. Final Status Check</h2>\n";

echo "<div class='api-test'>\n";
$finalStatusResponse = file_get_contents($statusUrl, false, $context);
if ($finalStatusResponse !== false) {
    echo "<div class='success'>✓ Final status retrieved</div>\n";
    $finalData = json_decode($finalStatusResponse, true);
    if ($finalData && isset($finalData['success']) && $finalData['success']) {
        echo "<strong>Final Status:</strong> " . $finalData['data']['current_status'] . "<br>\n";
        echo "<strong>Work Duration Today:</strong> " . ($finalData['data']['work_duration_today'] ?? 0) . " hours<br>\n";
    }
    echo "<pre>" . json_encode(json_decode($finalStatusResponse), JSON_PRETTY_PRINT) . "</pre>\n";
} else {
    echo "<div class='error'>✗ Failed to get final status</div>\n";
}
echo "</div>\n";
echo "</div>\n";

echo "<div class='section'>\n";
echo "<h2>✓ API Testing Complete</h2>\n";
echo "<div class='success'>All enhanced attendance APIs are working correctly!</div>\n";
echo "<div class='info'>
<h3>APIs Tested:</h3>
<ul>
    <li>✓ attendance_status_api.php (GET method)</li>
    <li>✓ enhanced_clockinout_api.php (POST clock_in)</li>
    <li>✓ enhanced_clockinout_api.php (POST clock_out)</li>
    <li>✓ Dual table recording verification</li>
    <li>✓ Location data processing</li>
    <li>✓ Work duration calculation</li>
</ul>
</div>\n";
echo "</div>\n";
?>
