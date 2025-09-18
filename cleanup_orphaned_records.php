<?php
include 'db.php';

echo "=== Checking for Orphaned Records ===\n";

// Check for orphaned biometric data
try {
    $orphanedBiometric = $conn->query('
        SELECT COUNT(*) as count FROM tbl_biometric_data bd 
        LEFT JOIN tbl_devices d ON bd.DeviceID = d.DeviceID 
        WHERE d.DeviceID IS NULL
    ')->fetchColumn();
    echo "Orphaned biometric data records: $orphanedBiometric\n";
} catch (Exception $e) {
    echo "Biometric data table check: " . $e->getMessage() . "\n";
}

// Check for orphaned assignments
try {
    $orphanedAssignments = $conn->query('
        SELECT COUNT(*) as count FROM tbl_employee_wearables ew 
        LEFT JOIN tbl_devices d ON ew.DeviceID = d.DeviceID 
        WHERE d.DeviceID IS NULL
    ')->fetchColumn();
    echo "Orphaned assignment records: $orphanedAssignments\n";
} catch (Exception $e) {
    echo "Assignment table check: " . $e->getMessage() . "\n";
}

// Clean up orphaned records if any exist
if (isset($orphanedBiometric) && $orphanedBiometric > 0) {
    echo "\n=== Cleaning Orphaned Biometric Data ===\n";
    $cleanBiometric = $conn->exec('
        DELETE bd FROM tbl_biometric_data bd 
        LEFT JOIN tbl_devices d ON bd.DeviceID = d.DeviceID 
        WHERE d.DeviceID IS NULL
    ');
    echo "✅ Removed $cleanBiometric orphaned biometric data records\n";
}

if (isset($orphanedAssignments) && $orphanedAssignments > 0) {
    echo "\n=== Cleaning Orphaned Assignments ===\n";
    $cleanAssignments = $conn->exec('
        DELETE ew FROM tbl_employee_wearables ew 
        LEFT JOIN tbl_devices d ON ew.DeviceID = d.DeviceID 
        WHERE d.DeviceID IS NULL
    ');
    echo "✅ Removed $cleanAssignments orphaned assignment records\n";
}

echo "\n✅ Orphaned record cleanup completed!\n";
?>
