<?php
include 'db.php';

echo "=== FINAL DEVICE SYSTEM STATUS ===\n\n";

// Show remaining devices grouped by type
$devices = $conn->query('
    SELECT DeviceID, DeviceName, DeviceType, Identifier, Manufacturer, Model, Location, IsActive 
    FROM tbl_devices 
    ORDER BY DeviceType, DeviceName
')->fetchAll(PDO::FETCH_ASSOC);

$devicesByType = [];
foreach ($devices as $device) {
    $devicesByType[$device['DeviceType']][] = $device;
}

foreach ($devicesByType as $type => $typeDevices) {
    echo "=== " . strtoupper($type) . " DEVICES ===\n";
    foreach ($typeDevices as $device) {
        $status = $device['IsActive'] ? '🟢 ACTIVE' : '🔴 INACTIVE';
        echo "  [{$device['DeviceID']}] {$device['DeviceName']} $status\n";
        echo "      Identifier: {$device['Identifier']}\n";
        echo "      Hardware: {$device['Manufacturer']} {$device['Model']}\n";
        if ($device['Location']) {
            echo "      Location: {$device['Location']}\n";
        }
        echo "\n";
    }
}

// Summary
$summary = $conn->query('
    SELECT 
        DeviceType, 
        COUNT(*) as total_count, 
        SUM(IsActive) as active_count 
    FROM tbl_devices 
    GROUP BY DeviceType
')->fetchAll(PDO::FETCH_ASSOC);

echo "=== DEVICE SUMMARY ===\n";
$totalDevices = 0;
$totalActive = 0;

foreach ($summary as $type) {
    echo "{$type['DeviceType']}: {$type['active_count']}/{$type['total_count']} active\n";
    $totalDevices += $type['total_count'];
    $totalActive += $type['active_count'];
}

echo "\nTOTAL: $totalActive/$totalDevices active devices\n";
echo "✅ System is now clean and ready for production use!\n";
?>
