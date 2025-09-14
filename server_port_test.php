<?php
// Simple server test for port 8080
echo "=== PORT 8080 SERVER TEST ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Port: " . ($_SERVER['SERVER_PORT'] ?? 'Unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "\n";

// Test if this is the PHP built-in server or Laragon
if (strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'PHP') !== false) {
    echo "Server Type: PHP Built-in Development Server\n";
} else {
    echo "Server Type: Apache/Laragon\n";
}

// Test file access
echo "\n=== FILE ACCESS TEST ===\n";
$test_files = ['login_api.php', 'db.php', 'android_connection_test.php'];
foreach ($test_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file - EXISTS\n";
    } else {
        echo "❌ $file - NOT FOUND\n";
    }
}

// Test database
echo "\n=== DATABASE TEST ===\n";
try {
    include 'db.php';
    echo "✅ Database connection successful\n";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_employees");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Employees: " . $result['count'] . "\n";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== URL ENDPOINTS ===\n";
echo "Base URL: http://localhost:8080/\n";
echo "Login API: http://localhost:8080/login_api.php\n";
echo "For Android Emulator: http://10.0.2.2:8080/login_api.php\n";
echo "For Real Device: http://192.168.0.189:8080/login_api.php\n";
?>
