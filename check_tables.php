<?php
include 'db.php';
$tables = $conn->query('SHOW TABLES LIKE "tbl_%"')->fetchAll(PDO::FETCH_COLUMN);
echo "Current tables:\n";
foreach($tables as $table) {
    echo "- $table\n";
}

// Check if biometric tables exist
$biometricTables = ['tbl_employee_wearables', 'tbl_biometric_data', 'tbl_biometric_alerts', 'tbl_wellness_reports', 'tbl_biometric_thresholds'];
echo "\nBiometric tables status:\n";
foreach($biometricTables as $table) {
    $exists = in_array($table, $tables) ? '✓' : '✗';
    echo "$exists $table\n";
}
?>
