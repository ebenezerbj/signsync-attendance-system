<?php
include 'db.php';

echo "=== Device Assignments ===\n";
$assignments = $conn->query('
    SELECT ew.WearableID, ew.EmployeeID, ew.DeviceID, d.DeviceName, d.DeviceType, ew.IsActive
    FROM tbl_employee_wearables ew 
    JOIN tbl_devices d ON ew.DeviceID = d.DeviceID 
    ORDER BY ew.IsActive DESC, d.DeviceType
')->fetchAll(PDO::FETCH_ASSOC);

foreach($assignments as $assignment) {
    $status = $assignment['IsActive'] ? 'ACTIVE' : 'inactive';
    echo "[$status] Employee: {$assignment['EmployeeID']} -> Device: {$assignment['DeviceName']} ({$assignment['DeviceType']})\n";
}

echo "\nTotal assignments: " . count($assignments) . "\n";

// Check for devices with no assignments
echo "\n=== Unassigned Devices ===\n";
$unassigned = $conn->query('
    SELECT d.DeviceID, d.DeviceName, d.DeviceType, d.Identifier
    FROM tbl_devices d 
    LEFT JOIN tbl_employee_wearables ew ON d.DeviceID = ew.DeviceID AND ew.IsActive = 1
    WHERE ew.DeviceID IS NULL AND d.IsActive = 1
    ORDER BY d.DeviceType, d.DeviceName
')->fetchAll(PDO::FETCH_ASSOC);

foreach($unassigned as $device) {
    echo "[{$device['DeviceID']}] {$device['DeviceType']} - {$device['DeviceName']} ({$device['Identifier']})\n";
}

echo "\nUnassigned devices: " . count($unassigned) . "\n";
?>
