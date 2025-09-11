<?php
include 'db.php';

echo "Checking available tables:\n";
$stmt = $conn->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_NUM);
foreach($tables as $table) {
    echo $table[0] . "\n";
}

echo "\nChecking specific tables used in report_viewer.php:\n";
$checkTables = ['tbl_departments', 'tbl_roles', 'tbl_shifts', 'employee_categories', 'employee_branches'];
foreach($checkTables as $tableName) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM $tableName");
        echo "$tableName: EXISTS (". $stmt->fetchColumn() ." records)\n";
    } catch(PDOException $e) {
        echo "$tableName: MISSING\n";
    }
}
