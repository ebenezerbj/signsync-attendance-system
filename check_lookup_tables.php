<?php
include 'db.php';

try {
    echo "Checking branches:\n";
    $stmt = $conn->query('SELECT BranchID, BranchName FROM tbl_branches LIMIT 5');
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($branches as $b) {
        echo "  {$b['BranchID']} - {$b['BranchName']}\n";
    }
    
    echo "\nChecking departments:\n";
    $stmt = $conn->query('SELECT DepartmentID, DepartmentName FROM tbl_departments LIMIT 5');
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($departments as $d) {
        echo "  {$d['DepartmentID']} - {$d['DepartmentName']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
