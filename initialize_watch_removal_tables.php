<?php
require_once 'db.php';

/**
 * Initializes the database tables required for watch removal detection.
 * - Creates the `watch_removal_log` table to store removal events.
 */

echo "=== Initializing Watch Removal Detection Tables ===\n\n";

try {
    $conn = new PDO("mysql:host=localhost;dbname=attendance_register_db", 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL to create watch_removal_log table
    $sql = "
    CREATE TABLE IF NOT EXISTS watch_removal_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(15) NOT NULL,
        device_id VARCHAR(100) NOT NULL,
        removed_at TIMESTAMP NOT NULL,
        reapplied_at TIMESTAMP NULL,
        duration_seconds INT NULL,
        status ENUM('REMOVED', 'REAPPLIED', 'ACKNOWLEDGED') NOT NULL DEFAULT 'REMOVED',
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE,
        INDEX idx_removal_employee_status (employee_id, status),
        INDEX idx_removal_time (removed_at)
    ) ENGINE=InnoDB;
    ";
    
    $conn->exec($sql);
    
    echo "✅ `watch_removal_log` table created successfully.\n";
    echo "   - Tracks when a watch is removed and reapplied.\n";
    echo "   - Stores duration of removal and status.\n";
    echo "   - Ready to log watch removal events from WearOS devices.\n\n";
    
    // Verify table creation
    $result = $conn->query("DESCRIBE watch_removal_log");
    echo "Table structure for `watch_removal_log`:\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\n=== Watch Removal Detection Setup Complete ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
