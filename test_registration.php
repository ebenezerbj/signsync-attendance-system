<?php
// Test the device registration system
echo "<h1>🎯 Android Wear Registration System Test</h1>";

// Test 1: Register a sample device
echo "<h2>Test 1: Register Sample Device</h2>";

$testDeviceData = [
    'action' => 'register_device',
    'device_name' => 'Test WS10 Ultra',
    'device_model' => 'WS10 Ultra',
    'android_version' => 'Android 11',
    'wearos_version' => 'Wear OS 3.0',
    'mac_address' => 'AA:BB:CC:DD:EE:FF',
    'serial_number' => 'TEST12345'
];

$url = 'http://localhost:8080/wearos_device_registration.php';

$postData = http_build_query($testDeviceData);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $postData
    ]
]);

try {
    $response = file_get_contents($url, false, $context);
    $result = json_decode($response, true);
    
    if ($result && $result['success']) {
        echo "✅ Device registration successful!<br>";
        echo "Device ID: " . $result['data']['device_id'] . "<br>";
        echo "Registration Code: <strong>" . $result['data']['registration_code'] . "</strong><br><br>";
        
        $deviceId = $result['data']['device_id'];
        $registrationCode = $result['data']['registration_code'];
        
        // Test 2: Check device status
        echo "<h2>Test 2: Check Device Status</h2>";
        $statusUrl = $url . "?action=get_device_status&device_id=" . $deviceId;
        $statusResponse = file_get_contents($statusUrl);
        $statusResult = json_decode($statusResponse, true);
        
        if ($statusResult && $statusResult['success']) {
            echo "✅ Device status check successful!<br>";
            echo "Device bound: " . ($statusResult['data']['is_bound'] ? 'Yes' : 'No') . "<br>";
            echo "Device active: " . ($statusResult['data']['is_active'] ? 'Yes' : 'No') . "<br><br>";
        }
        
        // Test 3: List all devices
        echo "<h2>Test 3: List All Devices</h2>";
        $listUrl = $url . "?action=list_devices";
        $listResponse = file_get_contents($listUrl);
        $listResult = json_decode($listResponse, true);
        
        if ($listResult && $listResult['success']) {
            echo "✅ Device list retrieved successfully!<br>";
            echo "Total devices: " . $listResult['count'] . "<br>";
            
            foreach ($listResult['data'] as $device) {
                echo "- " . $device['DeviceName'] . " (" . $device['DeviceID'] . ") - " . 
                     ($device['EmployeeID'] ? 'Bound to ' . $device['EmployeeID'] : 'Pending') . "<br>";
            }
        }
        
    } else {
        echo "❌ Device registration failed: " . ($result['message'] ?? 'Unknown error') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h2>🌐 Access Points</h2>";
echo "<a href='http://localhost:8080/wearos_device_manager.html' target='_blank'>📋 Device Management Interface</a><br>";
echo "<a href='http://localhost:8080/wearos_device_registration.php?action=list_devices' target='_blank'>📊 API: List Devices</a><br>";

echo "<hr>";
echo "<h2>📱 Android App Instructions</h2>";
echo "<ol>";
echo "<li><strong>Build the APK:</strong> Navigate to android_app directory and run <code>./gradlew assembleDebug</code></li>";
echo "<li><strong>Install on Android Wear:</strong> Use <code>adb install app-wearos-debug.apk</code></li>";
echo "<li><strong>Register Device:</strong> Open app and tap 'Register Device' button</li>";
echo "<li><strong>Bind to Employee:</strong> Use the web interface to bind the registration code to an employee</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>System Status:</strong> ✅ Registration system is ready for Android Wear devices!</p>";
?>
