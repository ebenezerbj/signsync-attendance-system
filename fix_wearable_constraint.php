<?php
include 'db.php';

echo "Fixing wearable assignment constraint...\n";

try {
    // First, check if the problematic constraint exists
    $result = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'tbl_employee_wearables' 
        AND CONSTRAINT_NAME = 'unique_active_assignment'
    ");
    
    if ($result->rowCount() > 0) {
        echo "Dropping problematic unique constraint...\n";
        $conn->exec("ALTER TABLE tbl_employee_wearables DROP INDEX unique_active_assignment");
        echo "✓ Dropped unique_active_assignment constraint\n";
    }
    
    // Create a better constraint that only prevents duplicate ACTIVE assignments
    echo "Creating improved constraints...\n";
    
    // Add a partial unique index that only applies when IsActive = 1
    // This allows multiple inactive records but only one active assignment per employee
    $conn->exec("
        CREATE UNIQUE INDEX unique_active_employee_assignment 
        ON tbl_employee_wearables (EmployeeID) 
        WHERE IsActive = 1
    ");
    echo "✓ Created unique_active_employee_assignment index\n";
    
    // Add another partial unique index for devices
    $conn->exec("
        CREATE UNIQUE INDEX unique_active_device_assignment 
        ON tbl_employee_wearables (DeviceID) 
        WHERE IsActive = 1
    ");
    echo "✓ Created unique_active_device_assignment index\n";
    
    echo "\n✅ Constraint fix completed successfully!\n";
    echo "Now employees can have multiple device history records, but only one active assignment at a time.\n";
    
} catch (Exception $e) {
    // If the partial indexes aren't supported, create a trigger-based solution
    echo "Partial indexes not supported, creating alternative solution...\n";
    
    try {
        // Drop the problematic constraint if it exists
        $conn->exec("ALTER TABLE tbl_employee_wearables DROP INDEX unique_active_assignment");
        echo "✓ Dropped problematic constraint\n";
    } catch (Exception $e2) {
        echo "Constraint may not exist, continuing...\n";
    }
    
    // Create a procedure to handle assignments properly
    $conn->exec("
        DROP PROCEDURE IF EXISTS AssignWearableDevice
    ");
    
    $conn->exec("
        CREATE PROCEDURE AssignWearableDevice(
            IN p_employee_id VARCHAR(15),
            IN p_device_id INT
        )
        BEGIN
            DECLARE EXIT HANDLER FOR SQLEXCEPTION
            BEGIN
                ROLLBACK;
                RESIGNAL;
            END;
            
            START TRANSACTION;
            
            -- Deactivate any existing assignments for this employee
            UPDATE tbl_employee_wearables 
            SET IsActive = 0 
            WHERE EmployeeID = p_employee_id AND IsActive = 1;
            
            -- Deactivate any existing assignments for this device
            UPDATE tbl_employee_wearables 
            SET IsActive = 0 
            WHERE DeviceID = p_device_id AND IsActive = 1;
            
            -- Insert new assignment
            INSERT INTO tbl_employee_wearables (EmployeeID, DeviceID, IsActive, AssignedDate)
            VALUES (p_employee_id, p_device_id, 1, NOW());
            
            COMMIT;
        END
    ");
    
    echo "✓ Created AssignWearableDevice stored procedure\n";
    echo "\n✅ Alternative solution implemented!\n";
    echo "Use the stored procedure for assignments to avoid constraint issues.\n";
}
?>
