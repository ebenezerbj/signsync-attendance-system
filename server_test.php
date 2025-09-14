<?php
echo "PHP Server Test - Time: " . date('Y-m-d H:i:s') . "\n";
echo "Server Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "PHP Version: " . phpversion() . "\n";

// Test database connection
include 'db.php';
echo "Database connection: OK\n";

// Test if we can query employees table
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_employees");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Employees table count: " . $result['count'] . "\n";
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
