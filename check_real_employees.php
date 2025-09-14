<?php
include 'db.php';

echo "=== REAL EMPLOYEE DATA ANALYSIS ===\n";

try {
    // Get all employees
    $stmt = $conn->prepare("SELECT * FROM tbl_employees ORDER BY EmployeeID");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total employees in database: " . count($employees) . "\n\n";
    
    echo "Employee Details:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-12s %-25s %-15s %-12s %-10s\n", "EmployeeID", "FullName", "PhoneNumber", "BranchID", "CustomPIN");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($employees as $emp) {
        printf("%-12s %-25s %-15s %-12s %-10s\n", 
            $emp['EmployeeID'] ?? 'N/A',
            substr($emp['FullName'] ?? 'N/A', 0, 24),
            $emp['PhoneNumber'] ?? 'N/A',
            $emp['BranchID'] ?? 'N/A',
            $emp['CustomPIN'] ?? 'None'
        );
    }
    
    echo "\n=== CUSTOM PIN STATUS ===\n";
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employee_pins");
    $stmt->execute();
    $pin_count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Custom PINs in employee_pins table: " . $pin_count['count'] . "\n";
    
    if ($pin_count['count'] > 0) {
        $stmt = $conn->prepare("SELECT EmployeeID, pin FROM employee_pins");
        $stmt->execute();
        $pins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Custom PIN Details:\n";
        foreach ($pins as $pin_record) {
            echo "- " . $pin_record['EmployeeID'] . ": PIN " . $pin_record['pin'] . "\n";
        }
    }
    
    echo "\n=== ANDROID APP LOGIN OPTIONS ===\n";
    foreach ($employees as $emp) {
        $emp_id = $emp['EmployeeID'];
        $name = $emp['FullName'] ?? 'Unknown';
        
        // Check if they have a custom PIN
        $stmt = $conn->prepare("SELECT pin FROM employee_pins WHERE EmployeeID = ?");
        $stmt->execute([$emp_id]);
        $custom_pin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($custom_pin) {
            echo "✅ $emp_id ($name) - Use custom PIN: " . $custom_pin['pin'] . "\n";
        } else {
            echo "🔑 $emp_id ($name) - Use default PIN: 1234 (will prompt to change)\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
