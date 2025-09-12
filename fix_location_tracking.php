<?php
try {
    $conn = new PDO('mysql:host=localhost;dbname=attendance_register_db', 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Fixing location_tracking table to use varchar employee_id...\n";
    $conn->exec('ALTER TABLE location_tracking MODIFY COLUMN employee_id VARCHAR(15)');
    echo "Fixed successfully\n";
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
