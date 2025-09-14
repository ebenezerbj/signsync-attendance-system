<?php
include 'db.php';

echo "📊 Employee Data Check\n";
echo str_repeat("=", 40) . "\n\n";

try {
    // Check employees
    $stmt = $conn->query("SELECT EmployeeID, FullName, PhoneNumber FROM tbl_employees LIMIT 10");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($employees) > 0) {
        echo "Found " . count($employees) . " employees:\n\n";
        
        foreach ($employees as $emp) {
            echo "Employee: {$emp['EmployeeID']}\n";
            echo "  Name: {$emp['FullName']}\n";
            echo "  Phone: {$emp['PhoneNumber']}\n";
            
            // Calculate PINs
            $phonePIN = !empty($emp['PhoneNumber']) && strlen($emp['PhoneNumber']) >= 4 
                ? substr($emp['PhoneNumber'], -4) 
                : 'N/A';
            
            preg_match('/(\d+)$/', $emp['EmployeeID'], $matches);
            $idPIN = !empty($matches[1]) 
                ? str_pad($matches[1], 4, '0', STR_PAD_LEFT) 
                : 'N/A';
            
            echo "  Phone PIN: {$phonePIN}\n";
            echo "  ID PIN: {$idPIN}\n";
            echo "  Default PIN: 1234\n";
            echo "  ---\n";
        }
    } else {
        echo "❌ No employees found in database\n";
        echo "Creating test employee...\n";
        
        // Create test employee if none exist
        $stmt = $conn->prepare("
            INSERT INTO tbl_employees (EmployeeID, FullName, Username, Password, PhoneNumber, BranchID, DepartmentID, RoleID, CategoryID, IsSpecial) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            'EMP001',
            'John Test Employee',
            'testuser',
            password_hash('1234', PASSWORD_DEFAULT),
            '+1234567890',
            'GH1510010',
            1,
            1,
            1,
            0
        ]);
        
        echo "✅ Created test employee EMP001\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
