<?php
require_once 'db.php';

echo "Creating test employee for WearOS testing...\n";

try {
    // Check if EMP001 already exists
    $stmt = $conn->prepare("SELECT EmployeeID FROM tbl_employees WHERE EmployeeID = ?");
    $stmt->execute(['EMP001']);
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Create test employee
        $stmt = $conn->prepare("
            INSERT INTO tbl_employees (EmployeeID, FullName, Username, Password, PhoneNumber, BranchID, DepartmentID, RoleID, CategoryID, IsSpecial) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            'EMP001',
            'John Doe (Test Employee)',
            'johndoe',
            password_hash('1234', PASSWORD_DEFAULT),
            '+1234567890',
            'GH1510010', // HEAD OFFICE
            1, // Human Resources/Administrator
            1,
            1,
            0
        ]);
        
        echo "✓ Created test employee EMP001 - John Doe\n";
    } else {
        echo "✓ Test employee EMP001 already exists\n";
    }
    
    // List existing employees
    echo "\nExisting employees:\n";
    $stmt = $conn->query("SELECT EmployeeID, FullName FROM tbl_employees LIMIT 10");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($employees as $emp) {
        echo "  {$emp['EmployeeID']} - {$emp['FullName']}\n";
    }
    
    echo "\nTest employee ready for WearOS clock in/out testing!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
