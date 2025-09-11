<?php
include 'db.php';

echo "Running biometric monitoring migration...\n";

$sql = file_get_contents('migrations/20250911_biometric_monitoring.sql');

try {
    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach($statements as $statement) {
        if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
            $conn->exec($statement);
            echo "✓ Executed statement\n";
        }
    }
    
    echo "\n✅ Biometric monitoring migration completed successfully!\n";
    echo "Created tables:\n";
    echo "- tbl_employee_wearables\n";
    echo "- tbl_biometric_data\n";
    echo "- tbl_biometric_alerts\n";
    echo "- tbl_wellness_reports\n";
    echo "- tbl_biometric_thresholds\n";
    
} catch(Exception $e) {
    echo "❌ Migration error: " . $e->getMessage() . "\n";
}
?>
