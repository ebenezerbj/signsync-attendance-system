<?php
include 'db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$employee_id = $_POST['employee_id'] ?? '';
$current_pin = $_POST['current_pin'] ?? '';
$new_pin = $_POST['new_pin'] ?? '';

if (empty($employee_id) || empty($current_pin) || empty($new_pin)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (strlen($new_pin) < 4) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'PIN must be at least 4 digits']);
    exit;
}

if ($new_pin === '1234') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot use default PIN']);
    exit;
}

try {
    // Verify employee exists
    $stmt = $conn->prepare("SELECT EmployeeID FROM tbl_employees WHERE EmployeeID = ?");
    $stmt->execute([$employee_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }
    
    // Create employee_pins table if it doesn't exist
    $conn->exec("
        CREATE TABLE IF NOT EXISTS employee_pins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            EmployeeID VARCHAR(50) UNIQUE NOT NULL,
            pin VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE
        )
    ");
    
    // Verify current PIN
    if ($current_pin === '1234') {
        // First time setup from default PIN
        $stmt = $conn->prepare("
            INSERT INTO employee_pins (EmployeeID, pin) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE pin = ?, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$employee_id, $new_pin, $new_pin]);
        
        echo json_encode([
            'success' => true,
            'message' => 'PIN changed successfully',
            'data' => 'PIN updated from default'
        ]);
    } else {
        // Verify existing PIN
        $stmt = $conn->prepare("SELECT pin FROM employee_pins WHERE EmployeeID = ?");
        $stmt->execute([$employee_id]);
        $stored_pin = $stmt->fetchColumn();
        
        if ($stored_pin && $stored_pin === $current_pin) {
            // Update existing PIN
            $stmt = $conn->prepare("UPDATE employee_pins SET pin = ?, updated_at = CURRENT_TIMESTAMP WHERE EmployeeID = ?");
            $stmt->execute([$new_pin, $employee_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'PIN changed successfully',
                'data' => 'PIN updated'
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Current PIN is incorrect']);
        }
    }
} catch (PDOException $e) {
    error_log("Change PIN API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
