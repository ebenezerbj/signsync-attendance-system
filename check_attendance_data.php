<?php
include 'db.php';

echo "🕐 SIGNSYNC Clock In/Out Database Analysis\n";
echo str_repeat("=", 50) . "\n\n";

// Check attendance-related tables
echo "1. Finding attendance-related tables:\n";
$stmt = $conn->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_NUM);
foreach($tables as $table) {
    $tableName = $table[0];
    if (stripos($tableName, 'attendance') !== false || stripos($tableName, 'clock') !== false) {
        echo "   ✅ Found table: " . $tableName . "\n";
    }
}

// Sample data from both tables
echo "\n2. Sample data from tbl_attendance (last 5 records):\n";
try {
    $stmt = $conn->query('SELECT * FROM tbl_attendance ORDER BY AttendanceID DESC LIMIT 5');
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($records)) {
        echo "   No records found\n";
    } else {
        foreach($records as $record) {
            echo "   ID: " . $record['AttendanceID'] . 
                 " | Employee: " . $record['EmployeeID'] . 
                 " | Date: " . $record['AttendanceDate'] . 
                 " | ClockIn: " . $record['ClockIn'] . 
                 " | ClockOut: " . $record['ClockOut'] . 
                 " | Status: " . $record['Status'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n3. Sample data from clockinout (last 5 records):\n";
try {
    $stmt = $conn->query('SELECT * FROM clockinout ORDER BY ID DESC LIMIT 5');
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($records)) {
        echo "   No records found\n";
    } else {
        foreach($records as $record) {
            echo "   ID: " . $record['ID'] . 
                 " | Employee: " . $record['EmployeeID'] . 
                 " | ClockIn: " . $record['ClockIn'] . 
                 " | ClockOut: " . ($record['ClockOut'] ?? 'NULL') . 
                 " | Duration: " . ($record['WorkDuration'] ?? 'NULL') . " hrs\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Analysis complete!\n";
?>
