<?php
include 'db.php';

echo "=== Current Registered Devices ===\n";
$devices = $conn->query('
    SELECT DeviceID, DeviceName, DeviceType, Identifier, Manufacturer, Model, Location, IsActive, CreatedAt 
    FROM tbl_devices 
    ORDER BY DeviceType, DeviceName
')->fetchAll(PDO::FETCH_ASSOC);

foreach($devices as $device) {
    echo "[{$device['DeviceID']}] {$device['DeviceType']} - {$device['DeviceName']} ({$device['Identifier']}) - {$device['Manufacturer']} {$device['Model']} - Active: {$device['IsActive']} - Created: {$device['CreatedAt']}\n";
}

echo "\n=== Device Summary ===\n";
$summary = $conn->query('
    SELECT DeviceType, COUNT(*) as count, SUM(IsActive) as active_count 
    FROM tbl_devices 
    GROUP BY DeviceType
')->fetchAll(PDO::FETCH_ASSOC);

foreach($summary as $type) {
    echo "{$type['DeviceType']}: {$type['count']} total ({$type['active_count']} active)\n";
}

echo "\nTotal devices: " . count($devices) . "\n";
?>
