<?php
// WearOS Integration Migration Runner
// Date: 2025-09-12

// Database connection
require_once 'db.php';

echo "Running WearOS Integration Migration...\n";

// Read and execute migration
$migration = file_get_contents('migrations/20250912_wearos_integration.sql');

// Remove comments and split into statements
$lines = explode("\n", $migration);
$cleanLines = [];

foreach ($lines as $line) {
    $line = trim($line);
    // Skip empty lines and comments
    if (empty($line) || strpos($line, '--') === 0) {
        continue;
    }
    $cleanLines[] = $line;
}

$cleanSQL = implode("\n", $cleanLines);

// Split by statement delimiters, handling DELIMITER changes
$statements = [];
$currentStatement = '';
$delimiter = ';';

foreach ($cleanLines as $line) {
    if (strpos($line, 'DELIMITER') === 0) {
        // Change delimiter
        $parts = explode(' ', $line);
        if (isset($parts[1])) {
            $delimiter = trim($parts[1]);
        }
        continue;
    }
    
    $currentStatement .= $line . "\n";
    
    if (substr($line, -strlen($delimiter)) === $delimiter) {
        // Remove the delimiter and add to statements
        $statement = substr($currentStatement, 0, -strlen($delimiter));
        $statement = trim($statement);
        
        if (!empty($statement)) {
            $statements[] = $statement;
        }
        $currentStatement = '';
    }
}

// Add any remaining statement
if (!empty(trim($currentStatement))) {
    $statements[] = trim($currentStatement);
}

$success = 0;
$errors = 0;

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    try {
        if ($conn->query($statement) !== false) {
            $success++;
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } else {
            $errors++;
            $errorInfo = $conn->errorInfo();
            echo "✗ Error: " . $errorInfo[2] . "\n";
            echo "  Statement: " . substr($statement, 0, 100) . "...\n";
        }
    } catch (Exception $e) {
        $errors++;
        echo "✗ Exception: " . $e->getMessage() . "\n";
        echo "  Statement: " . substr($statement, 0, 100) . "...\n";
    }
}

echo "\nMigration completed:\n";
echo "Success: $success\n";
echo "Errors: $errors\n";

// Verify key tables exist
$tables = ['biometric_data', 'biometric_alerts', 'employee_activity', 'wearos_sessions', 'wearos_devices'];
echo "\nVerifying tables:\n";

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->rowCount() > 0) {
        echo "✓ Table exists: $table\n";
    } else {
        // Try with tbl_ prefix
        $result = $conn->query("SHOW TABLES LIKE 'tbl_$table'");
        if ($result && $result->rowCount() > 0) {
            echo "✓ Table exists: tbl_$table\n";
        } else {
            echo "✗ Table missing: $table\n";
        }
    }
}

// Test API endpoint
echo "\nTesting WearOS API endpoint:\n";
$testData = [
    'action' => 'ping',
    'device_type' => 'android_watch'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/attendance_register/wearos_api.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✓ API endpoint responding correctly\n";
    echo "  Response: $response\n";
} else {
    echo "✗ API endpoint error (HTTP $httpCode)\n";
    if ($response) {
        echo "  Response: $response\n";
    }
}

echo "\nWearOS integration setup complete!\n";
?>
