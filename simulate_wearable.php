<?php
/**
 * IoT Wearable Data Simulator
 * Simulates a smartwatch sending biometric data to the system
 */

// API endpoint
$apiUrl = 'http://localhost:8080/attendance_register/biometric_api.php';

// Sample wearable data that would come from a smartwatch
$wearableData = [
    'employee_id' => 'AKCBSTFADMIN', // Replace with actual employee ID
    'device_id' => 13, // Apple Watch Series 9 assigned to AKCBSTFADMIN
    'heart_rate' => rand(65, 120),
    'heart_rate_variability' => rand(25, 55),
    'skin_temperature' => 36.5 + (rand(0, 20) / 10),
    'blood_oxygen' => rand(95, 100),
    'step_count' => rand(2000, 15000),
    'sleep_quality' => ['poor', 'fair', 'good', 'excellent'][rand(0, 3)],
    'activity_level' => ['sedentary', 'light', 'moderate', 'vigorous'][rand(0, 3)],
    'raw_data' => [
        'accelerometer' => ['x' => rand(-100, 100), 'y' => rand(-100, 100), 'z' => rand(-100, 100)],
        'gyroscope' => ['x' => rand(-50, 50), 'y' => rand(-50, 50), 'z' => rand(-50, 50)],
        'ambient_light' => rand(0, 1000),
        'battery_level' => rand(20, 100)
    ]
];

echo "🔗 IoT Wearable Data Simulator\n";
echo "=============================\n";
echo "Sending biometric data to: $apiUrl\n\n";

echo "📊 Sample Data Being Sent:\n";
echo "Employee ID: {$wearableData['employee_id']}\n";
echo "Device ID: {$wearableData['device_id']}\n";
echo "Heart Rate: {$wearableData['heart_rate']} bpm\n";
echo "HRV: {$wearableData['heart_rate_variability']} ms\n";
echo "Skin Temperature: {$wearableData['skin_temperature']}°C\n";
echo "Blood Oxygen: {$wearableData['blood_oxygen']}%\n";
echo "Step Count: {$wearableData['step_count']}\n";
echo "Sleep Quality: {$wearableData['sleep_quality']}\n";
echo "Activity Level: {$wearableData['activity_level']}\n\n";

// Send data via cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($wearableData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Device-ID: WATCH-001',
    'X-Employee-ID: ' . $wearableData['employee_id']
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "🔄 Sending data...\n";
echo "HTTP Status: $httpCode\n";
echo "Response: $response\n\n";

if ($httpCode === 200) {
    $responseData = json_decode($response, true);
    if ($responseData['success']) {
        echo "✅ Data sent successfully!\n";
        echo "📈 Calculated Stress Level: " . ($responseData['stress_level'] ?? 'N/A') . "\n";
        echo "😴 Calculated Fatigue Level: " . ($responseData['fatigue_level'] ?? 'N/A') . "\n";
        echo "⚠️  Alerts Generated: " . ($responseData['alerts_generated'] ?? 0) . "\n";
    } else {
        echo "❌ API Error: " . ($responseData['error'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "❌ HTTP Error: $httpCode\n";
}

echo "\n💡 Tip: Check the wellness dashboard to see the real-time data!\n";
echo "🌐 Access: http://localhost:8080/attendance_register/wellness_dashboard.php\n";
?>
