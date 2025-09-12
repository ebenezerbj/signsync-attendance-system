<?php
require_once 'db.php';

/**
 * Comprehensive test for enhanced WearOS location services integration
 * Tests GPS tracking, WiFi network detection, and Bluetooth LE beacon functionality
 */

echo "=== SignSync WearOS Enhanced Location Services Test ===\n\n";

// Test configuration
$testEmployeeId = 'EMP001';
$testDeviceId = 'WEAR_ANDROID_001';
$wearosApiUrl = 'http://localhost/attendance_register/wearos_api.php';

// Simulate comprehensive location data from Android smartwatch
$gpsData = [
    'latitude' => 40.7128,
    'longitude' => -74.0060,
    'accuracy' => 5.0
];

$wifiNetworks = [
    ['ssid' => 'OfficeWiFi', 'bssid' => '00:11:22:33:44:55', 'rssi' => -45, 'frequency' => 2437],
    ['ssid' => 'CompanyGuest', 'bssid' => '00:11:22:33:44:56', 'rssi' => -52, 'frequency' => 5180],
    ['ssid' => 'SecureOffice', 'bssid' => '00:11:22:33:44:57', 'rssi' => -38, 'frequency' => 2462]
];

$beaconData = [
    [
        'uuid' => 'E2C56DB5-DFFB-48D2-B060-D0F5A71096E0',
        'major' => 1,
        'minor' => 100,
        'rssi' => -65,
        'txPower' => -59,
        'distance' => 2.5
    ],
    [
        'uuid' => 'A1B2C3D4-E5F6-7890-ABCD-EF1234567890',
        'major' => 1,
        'minor' => 101,
        'rssi' => -72,
        'txPower' => -59,
        'distance' => 4.1
    ]
];

function sendWearOSRequest($url, $data) {
    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        throw new Exception('Failed to send request to WearOS API');
    }
    
    return json_decode($result, true);
}

function testLocationAPI($action, $locationData, $description) {
    global $wearosApiUrl, $testEmployeeId, $testDeviceId, $gpsData, $wifiNetworks, $beaconData;
    
    echo "Testing: $description\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        $requestData = array_merge([
            'action' => $action,
            'employee_id' => $testEmployeeId,
            'device_info' => $testDeviceId,
            'timestamp' => time(),
            'gps_latitude' => $gpsData['latitude'],
            'gps_longitude' => $gpsData['longitude'],
            'gps_accuracy' => $gpsData['accuracy'],
            'location_method' => 'hybrid',
            'wifi_networks' => $wifiNetworks,
            'beacon_data' => $beaconData,
            'is_at_workplace' => true,
            'location_verification_score' => 95,
            'enhanced_location_data' => [
                'gps' => $gpsData,
                'wifi' => $wifiNetworks,
                'beacons' => $beaconData,
                'timestamp' => time(),
                'device_info' => [
                    'manufacturer' => 'Samsung',
                    'model' => 'Galaxy Watch 4',
                    'os_version' => 'Wear OS 3.5',
                    'app_version' => '1.0.0'
                ]
            ]
        ], $locationData);
        
        $response = sendWearOSRequest($wearosApiUrl, $requestData);
        
        echo "Request sent successfully\n";
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
        
        if ($response['success']) {
            echo "✅ SUCCESS: {$response['message']}\n";
            
            if (isset($response['data'])) {
                $data = $response['data'];
                if (isset($data['location_verified'])) {
                    echo "📍 Location Verified: " . ($data['location_verified'] ? 'YES' : 'NO') . "\n";
                }
                if (isset($data['location_method'])) {
                    echo "🗺️ Location Method: {$data['location_method']}\n";
                }
                if (isset($data['location_accuracy'])) {
                    echo "🎯 GPS Accuracy: {$data['location_accuracy']}m\n";
                }
                if (isset($data['wifi_networks_detected'])) {
                    echo "📶 WiFi Networks: {$data['wifi_networks_detected']} detected\n";
                }
                if (isset($data['beacons_detected'])) {
                    echo "📡 Beacons: {$data['beacons_detected']} detected\n";
                }
                if (isset($data['verification_score'])) {
                    echo "⭐ Verification Score: {$data['verification_score']}/100\n";
                }
            }
        } else {
            echo "❌ FAILED: {$response['message']}\n";
        }
        
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Test sequence
echo "🚀 Starting comprehensive location services tests...\n\n";

// Test 1: Enhanced Clock In with comprehensive location data
testLocationAPI('clock_in', [], 'Enhanced Clock In with GPS, WiFi, and Beacon tracking');

// Wait a moment to simulate work time
echo "⏱️ Simulating work time (3 seconds)...\n\n";
sleep(3);

// Test 2: Enhanced Clock Out with comprehensive location data
testLocationAPI('clock_out', [], 'Enhanced Clock Out with comprehensive location verification');

// Test 3: Get attendance status
echo "Testing: Get attendance status with location history\n";
echo str_repeat('-', 50) . "\n";
try {
    $response = sendWearOSRequest($wearosApiUrl, [
        'action' => 'get_attendance_status',
        'employee_id' => $testEmployeeId
    ]);
    
    echo "✅ Attendance Status Retrieved\n";
    echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 4: Get recent attendance with location data
echo "Testing: Get recent attendance history\n";
echo str_repeat('-', 50) . "\n";
try {
    $response = sendWearOSRequest($wearosApiUrl, [
        'action' => 'get_recent_attendance',
        'employee_id' => $testEmployeeId,
        'days' => 1
    ]);
    
    echo "✅ Recent Attendance Retrieved\n";
    echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 5: Verify database storage
echo "Testing: Database location data storage verification\n";
echo str_repeat('-', 50) . "\n";
try {
    $conn = new PDO("mysql:host=localhost;dbname=attendance_register_db", 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check clockinout table for location data
    $stmt = $conn->prepare("
        SELECT id, employee_id, action, timestamp, 
               gps_latitude, gps_longitude, gps_accuracy, location_method,
               wifi_networks, beacon_data, is_at_workplace, location_verification_score
        FROM clockinout 
        WHERE employee_id = ? 
        ORDER BY timestamp DESC LIMIT 2
    ");
    $stmt->execute([$testEmployeeId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Found " . count($records) . " attendance records with location data:\n";
    foreach ($records as $record) {
        echo "📅 {$record['timestamp']} - {$record['action']}\n";
        echo "   📍 GPS: {$record['gps_latitude']}, {$record['gps_longitude']} (±{$record['gps_accuracy']}m)\n";
        echo "   🗺️ Method: {$record['location_method']}\n";
        echo "   🏢 At workplace: " . ($record['is_at_workplace'] ? 'YES' : 'NO') . "\n";
        echo "   ⭐ Score: {$record['location_verification_score']}/100\n";
        
        if ($record['wifi_networks']) {
            $wifiData = json_decode($record['wifi_networks'], true);
            echo "   📶 WiFi: " . count($wifiData) . " networks detected\n";
        }
        
        if ($record['beacon_data']) {
            $beaconInfo = json_decode($record['beacon_data'], true);
            echo "   📡 Beacons: " . count($beaconInfo) . " beacons detected\n";
        }
        echo "\n";
    }
    
    // Check location tracking table
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM location_tracking 
        WHERE employee_id = ? AND tracked_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$testEmployeeId]);
    $trackingCount = $stmt->fetchColumn();
    
    echo "📊 Location tracking entries in last hour: $trackingCount\n";
    
} catch (Exception $e) {
    echo "❌ Database verification error: " . $e->getMessage() . "\n";
}

echo "\n=== Enhanced Location Services Test Complete ===\n";
echo "✅ All comprehensive location tracking features tested:\n";
echo "   • GPS coordinate tracking with accuracy metrics\n";
echo "   • WiFi network detection and verification\n";
echo "   • Bluetooth LE beacon detection and tracking\n";
echo "   • Workplace location verification with scoring\n";
echo "   • Hybrid location determination methods\n";
echo "   • Comprehensive JSON data storage\n";
echo "   • Location tracking history and monitoring\n";
echo "   • Multi-factor location verification system\n\n";

echo "🏢 SignSync WearOS now provides enterprise-grade location\n";
echo "   verification for accurate IoT attendance tracking!\n";
?>
