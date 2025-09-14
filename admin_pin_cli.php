<?php
/**
 * SIGNSYNC PIN Management CLI Tool
 * Command-line interface for admin PIN management
 */

include 'db.php';

function showHelp() {
    echo "\n🔐 SIGNSYNC PIN Management CLI\n";
    echo "================================\n\n";
    echo "Commands:\n";
    echo "  list                    - List all employees and their PIN status\n";
    echo "  reset <EMPLOYEE_ID>     - Reset specific employee's PIN\n";
    echo "  reset-all               - Reset ALL employee PINs (use with caution)\n";
    echo "  details <EMPLOYEE_ID>   - Show detailed employee information\n";
    echo "  stats                   - Show PIN usage statistics\n";
    echo "  help                    - Show this help message\n\n";
    echo "Examples:\n";
    echo "  php admin_pin_cli.php list\n";
    echo "  php admin_pin_cli.php reset AKCBSTF0005\n";
    echo "  php admin_pin_cli.php details EMP001\n\n";
}

function listEmployees($conn) {
    echo "\n📋 Employee PIN Status\n";
    echo str_repeat("=", 80) . "\n";
    
    $stmt = $conn->query("
        SELECT e.EmployeeID, e.FullName, e.CustomPIN, e.PINSetupComplete,
               d.DepartmentName, b.BranchName
        FROM tbl_employees e
        LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
        LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
        ORDER BY e.EmployeeID
    ");
    
    $customCount = 0;
    $defaultCount = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hasCustomPin = !empty($row['CustomPIN']);
        $status = $hasCustomPin ? "✅ Custom PIN" : "⚠️  Default PIN";
        $indicator = $hasCustomPin ? "🔐" : "🔓";
        
        if ($hasCustomPin) $customCount++;
        else $defaultCount++;
        
        printf("%-20s │ %-25s │ %-15s │ %s %s\n", 
            $row['EmployeeID'],
            substr($row['FullName'], 0, 25),
            substr($row['DepartmentName'] ?: 'N/A', 0, 15),
            $indicator,
            $status
        );
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "📊 Summary: {$customCount} with custom PIN, {$defaultCount} using default PIN\n\n";
}

function resetEmployeePin($conn, $employeeId) {
    // Check if employee exists
    $checkStmt = $conn->prepare("SELECT EmployeeID, FullName FROM tbl_employees WHERE EmployeeID = ?");
    $checkStmt->execute([$employeeId]);
    
    if ($checkStmt->rowCount() === 0) {
        echo "❌ Employee not found: {$employeeId}\n\n";
        return;
    }
    
    $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    // Confirm reset
    echo "⚠️  You are about to reset PIN for:\n";
    echo "   Employee: {$employee['FullName']} ({$employee['EmployeeID']})\n";
    echo "   They will need to use default PIN '1234' and set up a new custom PIN.\n\n";
    echo "Continue? (y/N): ";
    
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) !== 'y') {
        echo "❌ PIN reset cancelled.\n\n";
        return;
    }
    
    // Reset PIN
    $resetStmt = $conn->prepare("
        UPDATE tbl_employees 
        SET CustomPIN = NULL, PINSetupComplete = 0 
        WHERE EmployeeID = ?
    ");
    $resetStmt->execute([$employeeId]);
    
    // Log the action
    try {
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (EmployeeID, ActivityType, ActivityDescription, Timestamp)
            VALUES (?, 'PIN_RESET', 'PIN reset via CLI by admin', NOW())
        ");
        $logStmt->execute([$employeeId]);
    } catch (Exception $e) {
        // Continue even if logging fails
    }
    
    echo "✅ PIN reset successful for {$employee['FullName']}!\n";
    echo "   They can now login with default PIN '1234'\n\n";
}

function resetAllPins($conn) {
    echo "⚠️  WARNING: This will reset ALL employee PINs!\n";
    echo "   All employees will need to use default PIN '1234' and set up new custom PINs.\n\n";
    echo "Type 'RESET ALL' to confirm: ";
    
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) !== 'RESET ALL') {
        echo "❌ Bulk reset cancelled.\n\n";
        return;
    }
    
    // Count affected employees
    $countStmt = $conn->query("SELECT COUNT(*) as count FROM tbl_employees WHERE CustomPIN IS NOT NULL");
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Reset all PINs
    $conn->exec("UPDATE tbl_employees SET CustomPIN = NULL, PINSetupComplete = 0");
    
    // Log the action
    try {
        $logStmt = $conn->prepare("
            INSERT INTO activity_logs (EmployeeID, ActivityType, ActivityDescription, Timestamp)
            VALUES ('ADMIN', 'BULK_PIN_RESET', 'All PINs reset via CLI by admin', NOW())
        ");
        $logStmt->execute();
    } catch (Exception $e) {
        // Continue even if logging fails
    }
    
    echo "✅ All employee PINs reset successfully!\n";
    echo "   {$count} employees affected\n\n";
}

function showEmployeeDetails($conn, $employeeId) {
    $stmt = $conn->prepare("
        SELECT e.EmployeeID, e.FullName, e.PhoneNumber, e.Username,
               d.DepartmentName, b.BranchName,
               e.CustomPIN, e.PINSetupComplete,
               (SELECT COUNT(*) FROM activity_logs al WHERE al.EmployeeID = e.EmployeeID AND al.ActivityType = 'PIN_AUTH') as LoginCount,
               (SELECT MAX(Timestamp) FROM activity_logs al WHERE al.EmployeeID = e.EmployeeID) as LastActivity,
               (SELECT MAX(ClockInTime) FROM tbl_clockinout tc WHERE tc.EmployeeID = e.EmployeeID) as LastClockIn
        FROM tbl_employees e
        LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
        LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
        WHERE e.EmployeeID = ?
    ");
    $stmt->execute([$employeeId]);
    
    if ($stmt->rowCount() === 0) {
        echo "❌ Employee not found: {$employeeId}\n\n";
        return;
    }
    
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n👤 Employee Details\n";
    echo str_repeat("=", 50) . "\n";
    echo "ID: {$emp['EmployeeID']}\n";
    echo "Name: {$emp['FullName']}\n";
    echo "Username: {$emp['Username']}\n";
    echo "Phone: " . ($emp['PhoneNumber'] ?: 'N/A') . "\n";
    echo "Department: " . ($emp['DepartmentName'] ?: 'N/A') . "\n";
    echo "Branch: " . ($emp['BranchName'] ?: 'N/A') . "\n";
    echo str_repeat("-", 50) . "\n";
    echo "PIN Status: " . (!empty($emp['CustomPIN']) ? "🔐 Custom PIN Set" : "🔓 Using Default PIN") . "\n";
    echo "Setup Complete: " . ($emp['PINSetupComplete'] ? "✅ Yes" : "❌ No") . "\n";
    echo "Total Logins: {$emp['LoginCount']}\n";
    echo "Last Activity: " . ($emp['LastActivity'] ?: 'N/A') . "\n";
    echo "Last Clock In: " . ($emp['LastClockIn'] ?: 'N/A') . "\n";
    echo str_repeat("=", 50) . "\n\n";
}

function showStats($conn) {
    echo "\n📊 PIN Usage Statistics\n";
    echo str_repeat("=", 40) . "\n";
    
    $totalStmt = $conn->query("SELECT COUNT(*) as count FROM tbl_employees");
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $customStmt = $conn->query("SELECT COUNT(*) as count FROM tbl_employees WHERE CustomPIN IS NOT NULL");
    $custom = $customStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $default = $total - $custom;
    $customPercent = $total > 0 ? round(($custom / $total) * 100, 1) : 0;
    
    echo "Total Employees: {$total}\n";
    echo "Custom PINs Set: {$custom} ({$customPercent}%)\n";
    echo "Using Default: {$default}\n";
    echo str_repeat("=", 40) . "\n\n";
}

// Main CLI Logic
if ($argc < 2) {
    showHelp();
    exit(1);
}

$command = $argv[1];

try {
    switch ($command) {
        case 'list':
            listEmployees($conn);
            break;
            
        case 'reset':
            if ($argc < 3) {
                echo "❌ Error: Employee ID required\n";
                echo "Usage: php admin_pin_cli.php reset <EMPLOYEE_ID>\n\n";
                exit(1);
            }
            resetEmployeePin($conn, $argv[2]);
            break;
            
        case 'reset-all':
            resetAllPins($conn);
            break;
            
        case 'details':
            if ($argc < 3) {
                echo "❌ Error: Employee ID required\n";
                echo "Usage: php admin_pin_cli.php details <EMPLOYEE_ID>\n\n";
                exit(1);
            }
            showEmployeeDetails($conn, $argv[2]);
            break;
            
        case 'stats':
            showStats($conn);
            break;
            
        case 'help':
        default:
            showHelp();
            break;
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
?>
