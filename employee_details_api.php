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

if (empty($employee_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit;
}

try {
    // Get employee details with branch information
    $stmt = $conn->prepare("
        SELECT 
            e.EmployeeID,
            e.FirstName,
            e.LastName,
            e.ContactNumber,
            e.Email,
            e.Department,
            e.Position,
            e.BranchID,
            e.Status,
            e.HireDate,
            b.BranchName,
            b.Location as BranchLocation
        FROM tbl_employees e
        LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
        WHERE e.EmployeeID = ? AND e.Status = 'Active'
    ");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        echo json_encode(['success' => false, 'message' => 'Employee not found or inactive']);
        exit;
    }
    
    // Get current shift information if available
    $stmt = $conn->prepare("
        SELECT ShiftName, StartTime, EndTime 
        FROM tbl_shifts 
        WHERE ShiftID = (
            SELECT ShiftID FROM tbl_employee_shifts 
            WHERE EmployeeID = ? 
            ORDER BY AssignedDate DESC 
            LIMIT 1
        )
    ");
    $stmt->execute([$employee_id]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Employee details retrieved',
        'data' => [
            'employee_id' => $employee['EmployeeID'],
            'first_name' => $employee['FirstName'],
            'last_name' => $employee['LastName'],
            'full_name' => trim($employee['FirstName'] . ' ' . $employee['LastName']),
            'contact_number' => $employee['ContactNumber'],
            'email' => $employee['Email'],
            'department' => $employee['Department'],
            'position' => $employee['Position'],
            'branch_id' => $employee['BranchID'],
            'branch_name' => $employee['BranchName'],
            'branch_location' => $employee['BranchLocation'],
            'status' => $employee['Status'],
            'hire_date' => $employee['HireDate'],
            'shift' => $shift ? [
                'name' => $shift['ShiftName'],
                'start_time' => $shift['StartTime'],
                'end_time' => $shift['EndTime']
            ] : null
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Employee details API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
