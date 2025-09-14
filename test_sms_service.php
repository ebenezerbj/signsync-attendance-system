<?php
/**
 * SMS Service Test Runner
 * 
 * This script performs comprehensive testing of the SIGNSYNC SMS service
 * including database setup, configuration, and basic functionality.
 */

echo "🚀 SIGNSYNC SMS Service Test Runner\n";
echo str_repeat("=", 50) . "\n\n";

$tests = [];
$errors = [];

// Test 1: Database Connection
echo "📊 Testing Database Connection...\n";
try {
    require_once 'db.php';
    if ($conn) {
        $tests['database'] = '✅ PASS';
        echo "   ✅ Database connection successful\n";
    } else {
        $tests['database'] = '❌ FAIL';
        $errors[] = "Database connection failed";
        echo "   ❌ Database connection failed\n";
    }
} catch (Exception $e) {
    $tests['database'] = '❌ FAIL';
    $errors[] = "Database error: " . $e->getMessage();
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: SMS Service Files
echo "📁 Testing SMS Service Files...\n";
$requiredFiles = [
    'SignSyncSMSService.php' => 'SMS Service Class',
    'sms_config.php' => 'Configuration Management',
    'sms_admin.php' => 'Admin Dashboard',
    'sms_test.php' => 'Testing Interface',
    'sms_health.php' => 'Health Check API',
    'sms_pin_reset.php' => 'PIN Reset System'
];

$allFilesExist = true;
foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "   ✅ $file ($description)\n";
    } else {
        echo "   ❌ $file ($description) - NOT FOUND\n";
        $allFilesExist = false;
        $errors[] = "Missing file: $file";
    }
}

$tests['files'] = $allFilesExist ? '✅ PASS' : '❌ FAIL';
echo "\n";

// Test 3: SMS Service Class Loading
echo "🔧 Testing SMS Service Class...\n";
try {
    require_once 'SignSyncSMSService.php';
    require_once 'sms_config.php';
    
    if (class_exists('SignSyncSMSService')) {
        $tests['class_loading'] = '✅ PASS';
        echo "   ✅ SignSyncSMSService class loaded successfully\n";
    } else {
        $tests['class_loading'] = '❌ FAIL';
        $errors[] = "SignSyncSMSService class not found";
        echo "   ❌ SignSyncSMSService class not found\n";
    }
} catch (Exception $e) {
    $tests['class_loading'] = '❌ FAIL';
    $errors[] = "Class loading error: " . $e->getMessage();
    echo "   ❌ Class loading error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Database Tables Creation
echo "🗄️ Testing Database Tables...\n";
if (isset($conn)) {
    $requiredTables = [
        'tbl_sms_queue' => 'SMS Queue',
        'tbl_sms_logs' => 'SMS Logs',
        'tbl_sms_config' => 'SMS Configuration',
        'tbl_sms_templates' => 'SMS Templates',
        'tbl_sms_rate_limits' => 'Rate Limiting'
    ];
    
    $allTablesExist = true;
    foreach ($requiredTables as $table => $description) {
        try {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "   ✅ $table ($description)\n";
            } else {
                echo "   ❌ $table ($description) - NOT FOUND\n";
                $allTablesExist = false;
                $errors[] = "Missing table: $table";
            }
        } catch (Exception $e) {
            echo "   ❌ Error checking $table: " . $e->getMessage() . "\n";
            $allTablesExist = false;
            $errors[] = "Table check error for $table: " . $e->getMessage();
        }
    }
    
    $tests['tables'] = $allTablesExist ? '✅ PASS' : '❌ FAIL';
} else {
    $tests['tables'] = '❌ FAIL';
    echo "   ❌ Cannot check tables - no database connection\n";
}
echo "\n";

// Test 5: SMS Service Initialization
echo "🚀 Testing SMS Service Initialization...\n";
try {
    if (isset($conn) && class_exists('SignSyncSMSService')) {
        $smsService = createSMSService($conn);
        if ($smsService instanceof SignSyncSMSService) {
            $tests['initialization'] = '✅ PASS';
            echo "   ✅ SMS Service initialized successfully\n";
            
            // Test basic methods
            try {
                $stats = $smsService->getStatistics('24h');
                echo "   ✅ Statistics method working\n";
            } catch (Exception $e) {
                echo "   ⚠️  Statistics method warning: " . $e->getMessage() . "\n";
            }
            
        } else {
            $tests['initialization'] = '❌ FAIL';
            $errors[] = "SMS Service initialization returned invalid object";
            echo "   ❌ SMS Service initialization returned invalid object\n";
        }
    } else {
        $tests['initialization'] = '❌ FAIL';
        $errors[] = "Cannot initialize SMS Service - missing dependencies";
        echo "   ❌ Cannot initialize SMS Service - missing dependencies\n";
    }
} catch (Exception $e) {
    $tests['initialization'] = '❌ FAIL';
    $errors[] = "SMS Service initialization error: " . $e->getMessage();
    echo "   ❌ SMS Service initialization error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Configuration Loading
echo "⚙️ Testing Configuration...\n";
try {
    if (function_exists('loadSMSConfig') && isset($conn)) {
        $config = loadSMSConfig($conn);
        if (is_array($config) && isset($config['providers'])) {
            $tests['configuration'] = '✅ PASS';
            echo "   ✅ Configuration loaded successfully\n";
            echo "   📋 Providers configured: " . implode(', ', array_keys($config['providers'])) . "\n";
        } else {
            $tests['configuration'] = '❌ FAIL';
            $errors[] = "Invalid configuration structure";
            echo "   ❌ Invalid configuration structure\n";
        }
    } else {
        $tests['configuration'] = '❌ FAIL';
        $errors[] = "Configuration loading function not available";
        echo "   ❌ Configuration loading function not available\n";
    }
} catch (Exception $e) {
    $tests['configuration'] = '❌ FAIL';
    $errors[] = "Configuration loading error: " . $e->getMessage();
    echo "   ❌ Configuration loading error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Template System
echo "📝 Testing Template System...\n";
try {
    if (isset($smsService)) {
        // Try to access templates through reflection
        $reflection = new ReflectionClass($smsService);
        $templatesProperty = $reflection->getProperty('templates');
        $templatesProperty->setAccessible(true);
        $templates = $templatesProperty->getValue($smsService);
        
        if (is_array($templates) && count($templates) > 0) {
            $tests['templates'] = '✅ PASS';
            echo "   ✅ Templates loaded: " . count($templates) . " templates\n";
            echo "   📋 Available templates: " . implode(', ', array_keys($templates)) . "\n";
        } else {
            $tests['templates'] = '❌ FAIL';
            $errors[] = "No templates loaded";
            echo "   ❌ No templates loaded\n";
        }
    } else {
        $tests['templates'] = '❌ FAIL';
        $errors[] = "SMS Service not available for template testing";
        echo "   ❌ SMS Service not available for template testing\n";
    }
} catch (Exception $e) {
    $tests['templates'] = '❌ FAIL';
    $errors[] = "Template system error: " . $e->getMessage();
    echo "   ❌ Template system error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 8: Web Interface Accessibility
echo "🌐 Testing Web Interfaces...\n";
$webInterfaces = [
    'sms_admin.php' => 'Admin Dashboard',
    'sms_test.php' => 'Testing Interface',
    'sms_health.php' => 'Health Check API',
    'sms_pin_reset.php' => 'PIN Reset System'
];

$allInterfacesAccessible = true;
foreach ($webInterfaces as $interface => $description) {
    if (file_exists($interface)) {
        // Simple syntax check
        $syntaxCheck = shell_exec("php -l $interface 2>&1");
        if (strpos($syntaxCheck, 'No syntax errors') !== false) {
            echo "   ✅ $interface ($description) - Syntax OK\n";
        } else {
            echo "   ❌ $interface ($description) - Syntax Error\n";
            $allInterfacesAccessible = false;
            $errors[] = "Syntax error in $interface";
        }
    } else {
        echo "   ❌ $interface ($description) - File Missing\n";
        $allInterfacesAccessible = false;
        $errors[] = "Missing interface: $interface";
    }
}

$tests['web_interfaces'] = $allInterfacesAccessible ? '✅ PASS' : '❌ FAIL';
echo "\n";

// Test Summary
echo str_repeat("=", 50) . "\n";
echo "📊 TEST SUMMARY\n";
echo str_repeat("=", 50) . "\n";

$passCount = 0;
$totalTests = count($tests);

foreach ($tests as $testName => $result) {
    echo sprintf("%-20s: %s\n", ucwords(str_replace('_', ' ', $testName)), $result);
    if ($result === '✅ PASS') {
        $passCount++;
    }
}

echo "\n";
echo "🎯 OVERALL RESULT: $passCount/$totalTests tests passed\n";

if ($passCount === $totalTests) {
    echo "🎉 SUCCESS! SMS Service is ready for use!\n\n";
    echo "🔗 Access Points:\n";
    echo "   • Admin Dashboard: http://localhost:8080/sms_admin.php\n";
    echo "   • Testing Interface: http://localhost:8080/sms_test.php\n";
    echo "   • Health Check: http://localhost:8080/sms_health.php?action=health\n";
    echo "   • PIN Reset: http://localhost:8080/sms_pin_reset.php\n";
} else {
    echo "⚠️  ISSUES FOUND! Please review the following errors:\n\n";
    foreach ($errors as $error) {
        echo "   ❌ $error\n";
    }
    
    echo "\n💡 SUGGESTED ACTIONS:\n";
    echo "   1. Run SMS migration: php run_sms_migration.php\n";
    echo "   2. Check database connection in db.php\n";
    echo "   3. Verify all SMS files are present\n";
    echo "   4. Check PHP error logs for detailed information\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
?>
