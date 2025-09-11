<?php
/**
 * Sample Device Populator Script
 * This script adds sample devices to the device registry for testing purposes
 */

session_start();
include 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    die("Access denied. Please log in as an administrator.");
}

// Sample devices to add
$sampleDevices = [
    [
        'device_name' => 'Main Office WiFi AP',
        'device_type' => 'wifi',
        'identifier' => '00:11:22:33:44:55',
        'location' => 'Main Office',
        'manufacturer' => 'Cisco',
        'model' => 'WAP121',
        'description' => 'Primary WiFi access point for main office area'
    ],
    [
        'device_name' => 'Reception Area WiFi',
        'device_type' => 'wifi',
        'identifier' => 'AA:BB:CC:DD:EE:FF',
        'location' => 'Reception',
        'manufacturer' => 'Ubiquiti',
        'model' => 'UniFi AP AC Pro',
        'description' => 'WiFi coverage for reception and waiting area'
    ],
    [
        'device_name' => 'Entrance Beacon',
        'device_type' => 'beacon',
        'identifier' => '550e8400-e29b-41d4-a716-446655440000',
        'location' => 'Main Entrance',
        'manufacturer' => 'Estimote',
        'model' => 'Proximity Beacon',
        'description' => 'BLE beacon for entrance attendance tracking'
    ],
    [
        'device_name' => 'Conference Room Beacon',
        'device_type' => 'beacon',
        'identifier' => '550e8400-e29b-41d4-a716-446655440001',
        'location' => 'Conference Room A',
        'manufacturer' => 'Estimote',
        'model' => 'Proximity Beacon',
        'description' => 'BLE beacon for conference room presence detection'
    ],
    [
        'device_name' => 'Main Entrance Camera',
        'device_type' => 'camera',
        'identifier' => 'CAM001-ENTRANCE',
        'location' => 'Main Entrance',
        'manufacturer' => 'Hikvision',
        'model' => 'DS-2CD2185FWD-I',
        'description' => '8MP IP camera with facial recognition capability'
    ],
    [
        'device_name' => 'Parking Lot Camera',
        'device_type' => 'camera',
        'identifier' => 'CAM002-PARKING',
        'location' => 'Parking Lot',
        'manufacturer' => 'Dahua',
        'model' => 'IPC-HFW4431R-Z',
        'description' => 'Outdoor security camera for parking area'
    ],
    [
        'device_name' => 'Office Temperature Sensor',
        'device_type' => 'sensor',
        'identifier' => 'TEMP001-OFFICE',
        'location' => 'Office Floor 1',
        'manufacturer' => 'Xiaomi',
        'model' => 'Mi Temperature Sensor',
        'description' => 'Wireless temperature and humidity sensor'
    ],
    [
        'device_name' => 'Server Room Environmental Monitor',
        'device_type' => 'sensor',
        'identifier' => 'ENV001-SERVER',
        'location' => 'Server Room',
        'manufacturer' => 'SensorPush',
        'model' => 'HT1',
        'description' => 'Temperature, humidity, and air quality monitoring'
    ],
    [
        'device_name' => 'Main Door RFID Reader',
        'device_type' => 'rfid',
        'identifier' => 'RFID001-MAIN',
        'location' => 'Main Entrance',
        'manufacturer' => 'HID Global',
        'model' => 'ProxPoint Plus',
        'description' => 'Card reader for employee access control'
    ],
    [
        'device_name' => 'Employee Lounge RFID',
        'device_type' => 'rfid',
        'identifier' => 'RFID002-LOUNGE',
        'location' => 'Employee Lounge',
        'manufacturer' => 'HID Global',
        'model' => 'ProxPoint Plus',
        'description' => 'Secondary access control for employee areas'
    ],
    [
        'device_name' => 'Break Room Bluetooth Speaker',
        'device_type' => 'bluetooth',
        'identifier' => '12:34:56:78:9A:BC',
        'location' => 'Break Room',
        'manufacturer' => 'JBL',
        'model' => 'Flip 5',
        'description' => 'Bluetooth device for ambient presence detection'
    ],
    [
        'device_name' => 'Meeting Room Bluetooth Hub',
        'device_type' => 'bluetooth',
        'identifier' => '98:76:54:32:10:FE',
        'location' => 'Meeting Room B',
        'manufacturer' => 'Raspberry Pi',
        'model' => 'Pi 4 with BLE',
        'description' => 'Custom Bluetooth hub for meeting room automation'
    ]
];

// Get first branch ID if available
$branchStmt = $conn->query("SELECT BranchID FROM tbl_branches LIMIT 1");
$defaultBranch = $branchStmt ? $branchStmt->fetchColumn() : null;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Device Population Script</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
<div class='container mt-4'>
    <div class='row justify-content-center'>
        <div class='col-md-8'>
            <div class='card'>
                <div class='card-header bg-primary text-white'>
                    <h4 class='mb-0'>🚀 Sample Device Population</h4>
                </div>
                <div class='card-body'>";

$successCount = 0;
$errorCount = 0;

try {
    $sql = "INSERT INTO tbl_devices (DeviceName, DeviceType, Identifier, BranchID, Location, Manufacturer, Model, Description, IsActive, CreatedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)";
    $stmt = $conn->prepare($sql);
    
    echo "<div class='mb-3'><strong>Adding sample devices...</strong></div>";
    echo "<div class='progress mb-3'><div class='progress-bar' role='progressbar' style='width: 0%'></div></div>";
    echo "<div id='device-list'>";
    
    foreach ($sampleDevices as $index => $device) {
        echo "<div class='d-flex align-items-center mb-2'>";
        
        try {
            // Check if device already exists
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM tbl_devices WHERE Identifier = ? AND DeviceType = ?");
            $checkStmt->execute([$device['identifier'], $device['device_type']]);
            
            if ($checkStmt->fetchColumn() > 0) {
                echo "<i class='fas fa-exclamation-triangle text-warning me-2'></i>";
                echo "<span class='text-muted'>Skipped: {$device['device_name']} (already exists)</span>";
            } else {
                $result = $stmt->execute([
                    $device['device_name'],
                    $device['device_type'],
                    $device['identifier'],
                    $defaultBranch,
                    $device['location'],
                    $device['manufacturer'],
                    $device['model'],
                    $device['description'],
                    $_SESSION['user_id']
                ]);
                
                if ($result) {
                    echo "<i class='fas fa-check-circle text-success me-2'></i>";
                    echo "<span>Added: {$device['device_name']} ({$device['device_type']})</span>";
                    $successCount++;
                } else {
                    echo "<i class='fas fa-times-circle text-danger me-2'></i>";
                    echo "<span class='text-danger'>Failed: {$device['device_name']}</span>";
                    $errorCount++;
                }
            }
        } catch (Exception $e) {
            echo "<i class='fas fa-times-circle text-danger me-2'></i>";
            echo "<span class='text-danger'>Error: {$device['device_name']} - {$e->getMessage()}</span>";
            $errorCount++;
        }
        
        echo "</div>";
        
        // Update progress
        $progress = (($index + 1) / count($sampleDevices)) * 100;
        echo "<script>document.querySelector('.progress-bar').style.width = '{$progress}%';</script>";
        flush();
        usleep(200000); // Small delay for visual effect
    }
    
    echo "</div>";
    
    // Summary
    echo "<div class='alert alert-success mt-4'>";
    echo "<h5><i class='fas fa-chart-bar me-2'></i>Population Complete!</h5>";
    echo "<ul class='mb-0'>";
    echo "<li><strong>Devices Added:</strong> {$successCount}</li>";
    echo "<li><strong>Errors:</strong> {$errorCount}</li>";
    echo "<li><strong>Total Processed:</strong> " . count($sampleDevices) . "</li>";
    echo "</ul>";
    echo "</div>";
    
    // Generate some sample activity
    if ($successCount > 0) {
        echo "<div class='alert alert-info'>";
        echo "<h6><i class='fas fa-clock me-2'></i>Generating Sample Activity...</h6>";
        
        $activityStmt = $conn->prepare("INSERT INTO tbl_device_activity (DeviceID, ActivityType, ActivityData, DetectedBy) VALUES (?, ?, ?, ?)");
        
        // Get some device IDs
        $deviceIds = $conn->query("SELECT DeviceID FROM tbl_devices ORDER BY CreatedAt DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
        
        $activities = [
            ['heartbeat', ['status' => 'online', 'signal_strength' => -45]],
            ['motion_detected', ['zone' => 'entrance', 'confidence' => 0.95]],
            ['temperature_reading', ['temperature' => 22.5, 'humidity' => 45.8]],
            ['access_granted', ['card_id' => 'EMP001', 'access_time' => date('H:i:s')]],
            ['beacon_detected', ['rssi' => -65, 'proximity' => 'immediate']]
        ];
        
        foreach ($deviceIds as $deviceId) {
            $activity = $activities[array_rand($activities)];
            $activityStmt->execute([
                $deviceId,
                $activity[0],
                json_encode($activity[1]),
                $_SESSION['user_id']
            ]);
        }
        
        echo "Sample activity logs generated for testing.";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h5><i class='fas fa-exclamation-triangle me-2'></i>Error</h5>";
    echo "<p>Database error: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "                </div>
                <div class='card-footer text-center'>
                    <a href='device_dashboard.php' class='btn btn-primary me-2'>
                        <i class='fas fa-tachometer-alt me-1'></i>View Device Dashboard
                    </a>
                    <a href='device_registry.php' class='btn btn-outline-secondary me-2'>
                        <i class='fas fa-plus me-1'></i>Register More Devices
                    </a>
                    <a href='admin_dashboard.php' class='btn btn-outline-dark'>
                        <i class='fas fa-arrow-left me-1'></i>Back to Admin
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>
