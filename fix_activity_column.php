<?php
/**
 * Fix Device Activity Column
 * Updates the ActivityType column to allow more activity types
 */

include 'db.php';

try {
    echo "Updating device activity table schema...\n";
    
    // Update ActivityType from ENUM to VARCHAR to allow flexible activity types
    $sql = "ALTER TABLE tbl_device_activity MODIFY COLUMN ActivityType VARCHAR(50) NOT NULL";
    $conn->exec($sql);
    
    echo "✅ Successfully updated ActivityType column to VARCHAR(50)\n";
    echo "✅ Database schema is now ready for flexible activity logging\n";
    
} catch (Exception $e) {
    echo "❌ Error updating schema: " . $e->getMessage() . "\n";
}
?>
