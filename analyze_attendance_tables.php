<?php
include 'db.php';

echo "🕐 SIGNSYNC Clock In/Out Database Analysis\n";
echo str_repeat("=", 50) . "\n\n";

// Check tbl_attendance structure
echo "1. Checking tbl_attendance table structure:\n";
try {
    $stmt = $conn->query('DESCRIBE tbl_attendance');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   " . $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . $row['Default'] . "\n";
    }
    
    $stmt = $conn->query('SELECT COUNT(*) FROM tbl_attendance');
    $count = $stmt->fetchColumn();
    echo "   Records: $count\n\n";
} catch (Exception $e) {
    echo "   ❌ tbl_attendance: " . $e->getMessage() . "\n\n";
}

// Check clockinout table structure
echo "2. Checking clockinout table structure:\n";
try {
    $stmt = $conn->query('DESCRIBE clockinout');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   " . $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . $row['Default'] . "\n";
    }
    
    $stmt = $conn->query('SELECT COUNT(*) FROM clockinout');
    $count = $stmt->fetchColumn();
    echo "   Records: $count\n\n";
} catch (Exception $e) {
    echo "   ❌ clockinout: " . $e->getMessage() . "\n\n";
}

// Check if there are other attendance-related tables
echo "3. Finding other attendance-related tables:\n";
$stmt = $conn->query("SHOW TABLES LIKE '%attendance%' OR SHOW TABLES LIKE '%clock%'");
while($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "   ✅ Found table: " . $row[0] . "\n";
}

// Sample data from both tables
echo "\n4. Sample data from tbl_attendance (last 5 records):\n";
try {
    $stmt = $conn->query('SELECT * FROM tbl_attendance ORDER BY id DESC LIMIT 5');
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($records)) {
        echo "   No records found\n";
    } else {
        foreach($records as $record) {
            echo "   ID: " . ($record['id'] ?? 'N/A') . 
                 " | Employee: " . ($record['EmployeeID'] ?? $record['employee_id'] ?? 'N/A') . 
                 " | Date: " . ($record['date'] ?? $record['attendance_date'] ?? 'N/A') . 
                 " | Time: " . ($record['time'] ?? $record['clock_in'] ?? 'N/A') . "\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n5. Sample data from clockinout (last 5 records):\n";
try {
    $stmt = $conn->query('SELECT * FROM clockinout ORDER BY id DESC LIMIT 5');
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($records)) {
        echo "   No records found\n";
    } else {
        foreach($records as $record) {
            echo "   ID: " . ($record['id'] ?? 'N/A') . 
                 " | Employee: " . ($record['EmployeeID'] ?? $record['employee_id'] ?? 'N/A') . 
                 " | Type: " . ($record['type'] ?? $record['action'] ?? 'N/A') . 
                 " | Time: " . ($record['timestamp'] ?? $record['time'] ?? 'N/A') . "\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Analysis complete!\n";
?>
