<?php
/**
 * Test script for watch removal and reapplication API endpoints.
 */

// API endpoint URL
$apiUrl = 'http://localhost/attendance_register/wearos_api.php';

// Test data
$employeeId = '123'; // Replace with a valid employee ID
$deviceId = 'wearos_device_1'; // Replace with a valid device ID

// --- Test Case 1: Watch Removed ---
echo "--- Testing Watch Removal ---\n";

$data_removed = [
    'action' => 'watch_removed',
    'employee_id' => $employeeId,
    'device_id' => $deviceId,
    'timestamp' => date('Y-m-d H:i:s')
];

$options_removed = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data_removed),
    ],
];

$context_removed = stream_context_create($options_removed);
$result_removed = file_get_contents($apiUrl, false, $context_removed);

echo "Request (Removed):\n";
print_r($data_removed);
echo "Response (Removed):\n";
print_r(json_decode($result_removed, true));
echo "\n";

// Wait for a few seconds to simulate time off wrist
sleep(5);

// --- Test Case 2: Watch Reapplied ---
echo "--- Testing Watch Reapplication ---\n";

$data_reapplied = [
    'action' => 'watch_reapplied',
    'employee_id' => $employeeId,
    'device_id' => $deviceId,
    'timestamp' => date('Y-m-d H:i:s')
];

$options_reapplied = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data_reapplied),
    ],
];

$context_reapplied = stream_context_create($options_reapplied);
$result_reapplied = file_get_contents($apiUrl, false, $context_reapplied);

echo "Request (Reapplied):\n";
print_r($data_reapplied);
echo "Response (Reapplied):\n";
print_r(json_decode($result_reapplied, true));
echo "\n";

?>
