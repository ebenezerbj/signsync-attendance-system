<?php
include 'db.php';

echo "=== Employee Schedules Structure ===\n";
try {
    $stmt = $conn->prepare('DESCRIBE tbl_employee_schedules');
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . ' (' . $row['Type'] . ")\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Employees Table Structure (relevant fields) ===\n";
try {
    $stmt = $conn->prepare('DESCRIBE tbl_employees');
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        if (in_array($row['Field'], ['EmployeeID', 'BranchID', 'ShiftID', 'DepartmentID'])) {
            echo $row['Field'] . ' (' . $row['Type'] . ")\n";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
