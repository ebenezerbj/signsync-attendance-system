<?php
/**
 * Sample Device Heartbeat Script
 * This simulates IoT devices sending heartbeat signals to the system
 * In a real deployment, this would be integrated into device firmware
 */

// Configuration
$serverUrl = 'http://localhost/attendance_register/device_api.php';
$devices = [
    [
        'identifier' => '00:11:22:33:44:55',
        'device_type' => 'wifi',
        'metadata' => [
            'signal_strength' => -42,
            'connected_clients' => 12,
            'uptime' => 86400,
            'temperature' => 35.2
        ]
    ],
    [
        'identifier' => '550e8400-e29b-41d4-a716-446655440000',
        'device_type' => 'beacon',
        'metadata' => [
            'battery_level' => 85,
            'signal_strength' => -65,
            'transmission_power' => -4,
            'advertising_interval' => 100
        ]
    ],
    [
        'identifier' => 'CAM001-MAIN-ENTRANCE',
        'device_type' => 'camera',
        'metadata' => [
            'recording' => true,
            'motion_detected' => false,
            'storage_used' => 45.2,
            'resolution' => '1920x1080'
        ]
    ],
    [
        'identifier' => 'TEMP001-OFFICE-FLOOR2',
        'device_type' => 'sensor',
        'metadata' => [
            'temperature' => 22.5,
            'humidity' => 45.8,
            'battery_level' => 92,
            'last_reading' => date('Y-m-d H:i:s')
        ]
    ]
];

// Function to send heartbeat
function sendHeartbeat($device, $serverUrl) {
    $data = [
        'action' => 'heartbeat',
        'identifier' => $device['identifier'],
        'device_type' => $device['device_type'],
        'metadata' => $device['metadata']
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $serverUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($data))
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

// Send heartbeats for all devices
echo "=== Device Heartbeat Simulation ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($devices as $device) {
    echo "Sending heartbeat for {$device['device_type']} device: {$device['identifier']}... ";
    
    $result = sendHeartbeat($device, $serverUrl);
    
    if ($result['http_code'] === 200) {
        if ($result['response']['success']) {
            echo "✓ SUCCESS - Heartbeat recorded\n";
        } else {
            echo "⚠ WARNING - " . ($result['response']['message'] ?? 'Unknown response') . "\n";
            if (isset($result['response']['suggest_registration']) && $result['response']['suggest_registration']) {
                echo "  → Device needs to be registered in the system\n";
            }
        }
    } else {
        echo "✗ ERROR - HTTP {$result['http_code']}\n";
        if ($result['response'] && isset($result['response']['error'])) {
            echo "  → " . $result['response']['error'] . "\n";
        }
    }
}

echo "\n=== Heartbeat simulation completed ===\n";

// Optional: Log device activity
function logActivity($deviceId, $activityType, $activityData, $serverUrl) {
    $data = [
        'action' => 'log_activity',
        'device_id' => $deviceId,
        'activity_type' => $activityType,
        'activity_data' => $activityData
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $serverUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Sample usage for logging activity (uncomment to use):
/*
echo "\n=== Logging sample activities ===\n";
logActivity('1', 'motion_detected', ['zone' => 'entrance', 'confidence' => 0.95], $serverUrl);
logActivity('2', 'beacon_range_changed', ['new_range' => 'medium'], $serverUrl);
echo "Activities logged.\n";
*/
?>
