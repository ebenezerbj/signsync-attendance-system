<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    // Get POST data
    $employee_id = $_POST['employee_id'] ?? '';
    
    if (empty($employee_id)) {
        throw new Exception('Employee ID is required');
    }
    
    // Get employee profile information
    $sql = "
        SELECT 
            e.id,
            e.employee_id,
            e.name,
            e.email,
            e.phone,
            e.address,
            e.department_id,
            e.branch_id,
            e.shift_id,
            e.role,
            e.hire_date,
            e.profile_picture,
            e.created_at,
            e.updated_at,
            d.name as department_name,
            b.name as branch_name,
            s.name as shift_name,
            s.start_time,
            s.end_time
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN branches b ON e.branch_id = b.id
        LEFT JOIN shifts s ON e.shift_id = s.id
        WHERE e.employee_id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        throw new Exception('Employee not found');
    }
    
    // Format the response data
    $profile_data = [
        'id' => (int)$employee['id'],
        'employee_id' => $employee['employee_id'],
        'name' => $employee['name'],
        'email' => $employee['email'],
        'phone' => $employee['phone'],
        'address' => $employee['address'],
        'department_id' => (int)$employee['department_id'],
        'department_name' => $employee['department_name'],
        'branch_id' => (int)$employee['branch_id'],
        'branch_name' => $employee['branch_name'],
        'shift_id' => (int)$employee['shift_id'],
        'shift_name' => $employee['shift_name'],
        'shift_start' => $employee['start_time'],
        'shift_end' => $employee['end_time'],
        'role' => $employee['role'],
        'hire_date' => $employee['hire_date'],
        'profile_picture' => $employee['profile_picture'],
        'created_at' => $employee['created_at'],
        'updated_at' => $employee['updated_at']
    ];
    
    $response = [
        'success' => true,
        'message' => 'Employee profile retrieved successfully',
        'data' => $profile_data
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ]);
}
?>
