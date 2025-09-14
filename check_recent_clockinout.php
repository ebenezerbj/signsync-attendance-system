<?php
include 'db.php';

echo "=== Latest Clock In/Out Records ===\n";

try {
    $stmt = $conn->prepare("SELECT ID, EmployeeID, ClockIn, ClockOut, ClockInSource, ClockOutSource FROM clockinout ORDER BY ID DESC LIMIT 5");
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        echo "ID: " . $row['ID'] . 
             ", Employee: " . $row['EmployeeID'] . 
             ", ClockIn: " . $row['ClockIn'] . 
             ", ClockOut: " . ($row['ClockOut'] ?? 'NULL') . 
             ", InSource: " . ($row['ClockInSource'] ?? 'NULL') . 
             ", OutSource: " . ($row['ClockOutSource'] ?? 'NULL') . "\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
