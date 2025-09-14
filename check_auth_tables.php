<?php
include 'db.php';

echo "Checking tbl_login_attempts table structure:\n";
try {
    $stmt = $conn->query('DESCRIBE tbl_login_attempts');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . $row['Default'] . "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    
    // Table doesn't exist, let's create it properly
    echo "Creating tbl_login_attempts table...\n";
    $sql = "CREATE TABLE tbl_login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(15) NOT NULL,
        login_type ENUM('pin', 'password') NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        success TINYINT(1) NOT NULL DEFAULT 0,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        failure_reason VARCHAR(255),
        INDEX idx_employee_id (employee_id),
        INDEX idx_attempt_time (attempt_time)
    )";
    $conn->exec($sql);
    echo "Table created successfully!\n";
}

// Check authentication sessions table
echo "\nChecking tbl_authentication_sessions table structure:\n";
try {
    $stmt = $conn->query('DESCRIBE tbl_authentication_sessions');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . $row['Default'] . "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    
    echo "Creating tbl_authentication_sessions table...\n";
    $sql = "CREATE TABLE tbl_authentication_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(15) NOT NULL,
        session_token VARCHAR(64) NOT NULL UNIQUE,
        session_type ENUM('web', 'mobile') NOT NULL,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_employee_id (employee_id),
        INDEX idx_session_token (session_token),
        INDEX idx_expires_at (expires_at)
    )";
    $conn->exec($sql);
    echo "Table created successfully!\n";
}
?>
