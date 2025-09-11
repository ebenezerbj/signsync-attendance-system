<?php
include 'db.php';

echo "Employee wearable assignments:\n";
$assignments = $conn->query('SELECT ew.*, d.DeviceName FROM tbl_employee_wearables ew JOIN tbl_devices d ON ew.DeviceID = d.DeviceID WHERE ew.IsActive = 1')->fetchAll(PDO::FETCH_ASSOC);

foreach($assignments as $assign) {
    echo "Employee: {$assign['EmployeeID']} -> Device: {$assign['DeviceID']} ({$assign['DeviceName']})\n";
}

echo "\nAvailable IoT devices:\n";
$devices = $conn->query('SELECT DeviceID, DeviceName, Identifier FROM tbl_devices WHERE DeviceType = "iot"')->fetchAll(PDO::FETCH_ASSOC);
foreach($devices as $device) {
    echo "Device ID: {$device['DeviceID']} - {$device['DeviceName']} ({$device['Identifier']})\n";
}
?>
