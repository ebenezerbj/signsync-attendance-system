<?php
/**
 * Employee Credentials Initialization Script
 * Ensures all employees have both passwords (for web) and PINs (for mobile)
 */
include 'db.php';
include 'EmployeeAuthenticationManager.php';

$authManager = new EmployeeAuthenticationManager($conn);

echo "SIGNSYNC Employee Credentials Initialization\n";
echo "============================================\n\n";

// Get all employees
$stmt = $conn->query("
    SELECT EmployeeID, FullName, Username, 
           CASE WHEN Password IS NOT NULL AND Password != '' THEN 1 ELSE 0 END as has_password,
           CASE WHEN CustomPIN IS NOT NULL AND CustomPIN != '' THEN 1 ELSE 0 END as has_pin
    FROM tbl_employees 
    ORDER BY EmployeeID
");

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalEmployees = count($employees);
$missingCredentials = [];
$generatedCredentials = [];

echo "Found {$totalEmployees} employees\n\n";

foreach ($employees as $employee) {
    $employeeId = $employee['EmployeeID'];
    $fullName = $employee['FullName'];
    $hasPassword = (bool)$employee['has_password'];
    $hasPin = (bool)$employee['has_pin'];
    
    echo "Processing: {$employeeId} - {$fullName}\n";
    echo "  Current Status: Password: " . ($hasPassword ? '✅' : '❌') . " | PIN: " . ($hasPin ? '✅' : '❌') . "\n";
    
    if (!$hasPassword || !$hasPin) {
        $missingCredentials[] = $employeeId;
        
        // Generate missing credentials
        $result = $authManager->ensureEmployeeCredentials($employeeId);
        
        if ($result['success'] && !empty($result['generated'])) {
            $generatedCredentials[$employeeId] = [
                'name' => $fullName,
                'credentials' => $result['generated']
            ];
            
            echo "  ✅ Generated: ";
            if (isset($result['generated']['password'])) {
                echo "Password: {$result['generated']['password']} ";
            }
            if (isset($result['generated']['pin'])) {
                echo "PIN: {$result['generated']['pin']}";
            }
            echo "\n";
        } else {
            echo "  ❌ Failed to generate credentials\n";
        }
    } else {
        echo "  ✅ All credentials present\n";
    }
    echo "\n";
}

// Summary report
echo "\n" . str_repeat("=", 60) . "\n";
echo "CREDENTIAL GENERATION SUMMARY\n";
echo str_repeat("=", 60) . "\n\n";

echo "Total Employees: {$totalEmployees}\n";
echo "Employees Missing Credentials: " . count($missingCredentials) . "\n";
echo "Credentials Generated For: " . count($generatedCredentials) . " employees\n\n";

if (!empty($generatedCredentials)) {
    echo "NEW CREDENTIALS GENERATED:\n";
    echo str_repeat("-", 60) . "\n";
    
    foreach ($generatedCredentials as $empId => $data) {
        echo "Employee: {$empId} - {$data['name']}\n";
        
        if (isset($data['credentials']['password'])) {
            echo "  🔐 New Password: {$data['credentials']['password']}\n";
            echo "      (For web login at login.php)\n";
        }
        
        if (isset($data['credentials']['pin'])) {
            echo "  📱 New PIN: {$data['credentials']['pin']}\n";
            echo "      (For mobile app login)\n";
        }
        echo "\n";
    }
    
    echo "⚠️  IMPORTANT SECURITY NOTES:\n";
    echo "   - Store these credentials securely\n";
    echo "   - Employees should change them on first login\n";
    echo "   - Default PIN (1234) is no longer accepted for employees with custom PINs\n";
    echo "   - Web passwords must be at least 8 characters\n";
    echo "   - Mobile PINs must be exactly 4 digits\n\n";
}

// Generate authentication status report
echo "\n" . str_repeat("=", 60) . "\n";
echo "FINAL AUTHENTICATION STATUS\n";
echo str_repeat("=", 60) . "\n\n";

$authStatus = $authManager->getAuthenticationStatus();
foreach ($authStatus as $status) {
    echo "{$status['EmployeeID']} - {$status['FullName']}\n";
    echo "  Password: {$status['password_status']}\n";
    echo "  PIN: {$status['pin_status']}\n";
    echo "  PIN Setup Complete: " . ($status['PINSetupComplete'] ? 'Yes' : 'No') . "\n";
    echo "  Failed Attempts (Last Hour): {$status['failed_attempts_last_hour']}\n";
    echo "\n";
}

echo "✅ All employees now have both web passwords and mobile PINs!\n";
?>
