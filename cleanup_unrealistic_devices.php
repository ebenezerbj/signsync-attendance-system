<?php
include 'db.php';

echo "=== Cleaning Unrealistic Registered Devices ===\n\n";

// Define criteria for unrealistic devices to remove
$unrealisticDevices = [
    // Generic/unnamed devices
    18, // Android Smartwatch - Generic (empty identifier)
    
    // Test/demo wearables with generic identifiers (these look like sample data)
    13, // Apple Watch Series 9 (AW-001-001) - Generic test identifier
    14, // Fitbit Versa 4 (FB-004-001) - Generic test identifier  
    15, // Samsung Galaxy Watch 6 (SW-006-001) - Generic test identifier
    16, // Garmin Vivosmart 5 (GV-005-001) - Generic test identifier
    17, // Amazfit GTR 4 (AM-004-001) - Generic test identifier
    
    // Demo infrastructure devices (placeholder data)
    11, // Break Room Bluetooth Speaker - Not realistic for attendance
    12, // Meeting Room Bluetooth Hub - Not realistic for attendance  
    7,  // Office Temperature Sensor - Not attendance related
    8,  // Server Room Environmental Monitor - Not attendance related
];

try {
    $conn->beginTransaction();
    
    $totalRemoved = 0;
    
    foreach ($unrealisticDevices as $deviceId) {
        // Get device info before deletion
        $deviceInfo = $conn->prepare("
            SELECT DeviceID, DeviceName, DeviceType, Identifier, Manufacturer, Model 
            FROM tbl_devices 
            WHERE DeviceID = ?
        ");
        $deviceInfo->execute([$deviceId]);
        $device = $deviceInfo->fetch(PDO::FETCH_ASSOC);
        
        if ($device) {
            // Check if device has any assignments (active or inactive)
            $assignmentCheck = $conn->prepare("
                SELECT COUNT(*) FROM tbl_employee_wearables WHERE DeviceID = ?
            ");
            $assignmentCheck->execute([$deviceId]);
            $assignmentCount = $assignmentCheck->fetchColumn();
            
            if ($assignmentCount > 0) {
                echo "⚠️  SKIPPING Device ID {$deviceId} - Has {$assignmentCount} assignment(s)\n";
                echo "    {$device['DeviceType']} - {$device['DeviceName']} ({$device['Identifier']})\n\n";
                continue;
            }
            
            // Delete the device
            $deleteStmt = $conn->prepare("DELETE FROM tbl_devices WHERE DeviceID = ?");
            $deleteStmt->execute([$deviceId]);
            
            if ($deleteStmt->rowCount() > 0) {
                echo "✅ REMOVED Device ID {$deviceId}\n";
                echo "    {$device['DeviceType']} - {$device['DeviceName']} ({$device['Identifier']})\n";
                echo "    {$device['Manufacturer']} {$device['Model']}\n\n";
                $totalRemoved++;
            }
        } else {
            echo "ℹ️  Device ID {$deviceId} not found (may have been already removed)\n\n";
        }
    }
    
    $conn->commit();
    
    echo "=== Cleanup Summary ===\n";
    echo "✅ Successfully removed {$totalRemoved} unrealistic devices\n";
    echo "🔒 Kept realistic devices for actual attendance system use\n\n";
    
    // Show remaining devices
    echo "=== Remaining Devices ===\n";
    $remaining = $conn->query("
        SELECT DeviceID, DeviceName, DeviceType, Identifier, Manufacturer, Model 
        FROM tbl_devices 
        WHERE IsActive = 1 
        ORDER BY DeviceType, DeviceName
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($remaining as $device) {
        echo "[{$device['DeviceID']}] {$device['DeviceType']} - {$device['DeviceName']} ({$device['Identifier']})\n";
        echo "    {$device['Manufacturer']} {$device['Model']}\n";
    }
    
    echo "\nRemaining devices: " . count($remaining) . "\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error during cleanup: " . $e->getMessage() . "\n";
}
?>
