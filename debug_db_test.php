<?php
include 'db.php';
header('Content-Type: application/json');

try {
    // Test database connection
    echo "Database connection: OK\n";
    
    // Check if required tables exist
    $tables = ['tbl_employees', 'tbl_attendance', 'clockinout', 'tbl_branches'];
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "Table $table: EXISTS\n";
        } else {
            echo "Table $table: MISSING\n";
        }
    }
    
    // Check if AttendanceManager can be instantiated
    include_once 'AttendanceManager.php';
    $attendanceManager = new AttendanceManager($conn);
    echo "AttendanceManager: OK\n";
    
    // Test a simple query
    $stmt = $conn->query("SELECT COUNT(*) as count FROM tbl_employees");
    $result = $stmt->fetch();
    echo "Employee count: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
