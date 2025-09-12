<?php
/**
 * Fixes the AlertType ENUM in tbl_biometric_alerts to include 'manual_check'.
 */

include 'db.php';

try {
    echo "Attempting to modify tbl_biometric_alerts...\n";
    
    $sql = "ALTER TABLE tbl_biometric_alerts 
            MODIFY COLUMN AlertType ENUM('stress', 'fatigue', 'health', 'inactivity', 'manual_check') NOT NULL";
            
    $conn->exec($sql);
    
    echo "Successfully modified AlertType column in tbl_biometric_alerts.\n";
    
} catch (PDOException $e) {
    die("Error modifying table: " . $e->getMessage() . "\n");
}
?>
