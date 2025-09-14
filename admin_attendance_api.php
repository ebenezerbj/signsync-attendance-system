<?php
include 'db.php';
include 'AttendanceManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action is required']);
    exit;
}

try {
    $attendanceManager = new AttendanceManager($conn);
    
    switch ($action) {
        case 'get_attendance_records':
            handleGetAttendanceRecords($conn);
            break;
            
        case 'get_clockinout_logs':
            handleGetClockinoutLogs($conn);
            break;
            
        case 'get_employee_status':
            handleGetEmployeeStatus($conn, $attendanceManager);
            break;
            
        case 'get_employees':
            handleGetEmployees($conn);
            break;
            
        case 'get_attendance_details':
            handleGetAttendanceDetails($conn);
            break;
            
        case 'get_clockinout_details':
            handleGetClockinoutDetails($conn);
            break;
            
        case 'update_attendance':
            handleUpdateAttendance($conn);
            break;
            
        case 'export_attendance':
            handleExportAttendance($conn);
            break;
            
        case 'export_clockinout':
            handleExportClockinout($conn);
            break;
            
        case 'save_settings':
            handleSaveSettings($conn);
            break;
            
        case 'cleanup_old_data':
            handleCleanupOldData($conn);
            break;
            
        case 'sync_tables':
            handleSyncTables($conn);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Admin attendance API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function handleGetAttendanceRecords($conn) {
    $dateFrom = $_POST['date_from'] ?? date('Y-m-d');
    $dateTo = $_POST['date_to'] ?? date('Y-m-d');
    $employeeId = $_POST['employee_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    $whereConditions = [];
    $params = [];
    
    $whereConditions[] = "a.AttendanceDate BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
    
    if (!empty($employeeId)) {
        $whereConditions[] = "a.EmployeeID = ?";
        $params[] = $employeeId;
    }
    
    if (!empty($status)) {
        $whereConditions[] = "a.Status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "
        SELECT 
            a.*,
            e.FullName as EmployeeName,
            e.PhoneNumber,
            b.BranchName
        FROM tbl_attendance a
        LEFT JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
        LEFT JOIN tbl_branches b ON a.BranchID = b.BranchID
        {$whereClause}
        ORDER BY a.AttendanceDate DESC, a.ClockIn DESC
        LIMIT 1000
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $records,
        'total' => count($records)
    ]);
}

function handleGetClockinoutLogs($conn) {
    $dateFrom = $_POST['date_from'] ?? date('Y-m-d');
    $dateTo = $_POST['date_to'] ?? date('Y-m-d');
    $employeeId = $_POST['employee_id'] ?? '';
    $source = $_POST['source'] ?? '';
    
    $whereConditions = [];
    $params = [];
    
    $whereConditions[] = "DATE(c.ClockIn) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
    
    if (!empty($employeeId)) {
        $whereConditions[] = "c.EmployeeID = ?";
        $params[] = $employeeId;
    }
    
    if (!empty($source)) {
        $whereConditions[] = "c.ClockInSource = ?";
        $params[] = $source;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "
        SELECT 
            c.*,
            e.FullName as EmployeeName,
            e.PhoneNumber
        FROM clockinout c
        LEFT JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID
        {$whereClause}
        ORDER BY c.ClockIn DESC
        LIMIT 1000
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $records,
        'total' => count($records)
    ]);
}

function handleGetEmployeeStatus($conn, $attendanceManager) {
    $sql = "
        SELECT 
            e.EmployeeID,
            e.FullName,
            e.PhoneNumber,
            e.BranchID,
            b.BranchName
        FROM tbl_employees e
        LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
        WHERE e.IsActive = 1 OR e.IsActive IS NULL
        ORDER BY e.FullName
    ";
    
    $stmt = $conn->query($sql);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $employeeStatuses = [];
    $today = date('Y-m-d');
    
    foreach ($employees as $employee) {
        $status = $attendanceManager->getAttendanceStatus($employee['EmployeeID']);
        
        // Get today's attendance record
        $attendanceStmt = $conn->prepare("
            SELECT ClockIn, ClockOut, Status
            FROM tbl_attendance 
            WHERE EmployeeID = ? AND AttendanceDate = ?
        ");
        $attendanceStmt->execute([$employee['EmployeeID'], $today]);
        $todayRecord = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate work hours
        $hoursWorked = 0;
        if ($todayRecord && $todayRecord['ClockIn']) {
            $clockIn = new DateTime($today . ' ' . $todayRecord['ClockIn']);
            $clockOut = $todayRecord['ClockOut'] ? 
                new DateTime($today . ' ' . $todayRecord['ClockOut']) : 
                new DateTime();
            $interval = $clockOut->diff($clockIn);
            $hoursWorked = $interval->h + ($interval->i / 60);
        }
        
        $employeeStatuses[] = [
            'EmployeeID' => $employee['EmployeeID'],
            'FullName' => $employee['FullName'],
            'PhoneNumber' => $employee['PhoneNumber'],
            'BranchName' => $employee['BranchName'],
            'current_status' => $status['current_status'] ?? 'Unknown',
            'clock_in_time' => $todayRecord['ClockIn'] ?? null,
            'hours_worked' => round($hoursWorked, 1),
            'last_activity' => $todayRecord ? $today . ' ' . ($todayRecord['ClockOut'] ?? $todayRecord['ClockIn']) : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $employeeStatuses
    ]);
}

function handleGetEmployees($conn) {
    $sql = "
        SELECT EmployeeID, FullName, PhoneNumber
        FROM tbl_employees
        WHERE IsActive = 1 OR IsActive IS NULL
        ORDER BY FullName
    ";
    
    $stmt = $conn->query($sql);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $employees
    ]);
}

function handleGetAttendanceDetails($conn) {
    $attendanceId = $_POST['attendance_id'] ?? '';
    
    if (empty($attendanceId)) {
        echo json_encode(['success' => false, 'message' => 'Attendance ID is required']);
        return;
    }
    
    $sql = "
        SELECT 
            a.*,
            e.FullName as EmployeeName,
            e.PhoneNumber,
            b.BranchName
        FROM tbl_attendance a
        LEFT JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
        LEFT JOIN tbl_branches b ON a.BranchID = b.BranchID
        WHERE a.AttendanceID = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$attendanceId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo json_encode([
            'success' => true,
            'data' => $record
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Attendance record not found']);
    }
}

function handleGetClockinoutDetails($conn) {
    $clockinoutId = $_POST['clockinout_id'] ?? '';
    
    if (empty($clockinoutId)) {
        echo json_encode(['success' => false, 'message' => 'Clock in/out ID is required']);
        return;
    }
    
    $sql = "
        SELECT 
            c.*,
            e.FullName as EmployeeName,
            e.PhoneNumber
        FROM clockinout c
        LEFT JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID
        WHERE c.ID = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$clockinoutId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo json_encode([
            'success' => true,
            'data' => $record
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Clock in/out record not found']);
    }
}

function handleUpdateAttendance($conn) {
    $attendanceId = $_POST['attendance_id'] ?? '';
    $attendanceDate = $_POST['attendance_date'] ?? '';
    $clockIn = $_POST['clock_in'] ?? '';
    $clockOut = $_POST['clock_out'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    if (empty($attendanceId)) {
        echo json_encode(['success' => false, 'message' => 'Attendance ID is required']);
        return;
    }
    
    try {
        $conn->beginTransaction();
        
        $sql = "
            UPDATE tbl_attendance 
            SET AttendanceDate = ?, 
                ClockIn = ?, 
                ClockOut = ?, 
                Status = ?, 
                Remarks = ?
            WHERE AttendanceID = ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $attendanceDate,
            $clockIn ?: null,
            $clockOut ?: null,
            $status,
            $remarks,
            $attendanceId
        ]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance record updated successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update record: ' . $e->getMessage()]);
    }
}

function handleExportAttendance($conn) {
    $dateFrom = $_POST['date_from'] ?? date('Y-m-d');
    $dateTo = $_POST['date_to'] ?? date('Y-m-d');
    $employeeId = $_POST['employee_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    $whereConditions = [];
    $params = [];
    
    $whereConditions[] = "a.AttendanceDate BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
    
    if (!empty($employeeId)) {
        $whereConditions[] = "a.EmployeeID = ?";
        $params[] = $employeeId;
    }
    
    if (!empty($status)) {
        $whereConditions[] = "a.Status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "
        SELECT 
            a.AttendanceID,
            a.EmployeeID,
            e.FullName as EmployeeName,
            a.AttendanceDate,
            a.ClockIn,
            a.ClockOut,
            a.Status,
            a.Remarks,
            b.BranchName,
            CASE 
                WHEN a.ClockIn IS NOT NULL AND a.ClockOut IS NOT NULL THEN
                    ROUND(TIMESTAMPDIFF(MINUTE, 
                        CONCAT(a.AttendanceDate, ' ', a.ClockIn), 
                        CONCAT(a.AttendanceDate, ' ', a.ClockOut)
                    ) / 60.0, 2)
                ELSE 0
            END as HoursWorked
        FROM tbl_attendance a
        LEFT JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
        LEFT JOIN tbl_branches b ON a.BranchID = b.BranchID
        {$whereClause}
        ORDER BY a.AttendanceDate DESC, a.ClockIn DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
}

function handleExportClockinout($conn) {
    $dateFrom = $_POST['date_from'] ?? date('Y-m-d');
    $dateTo = $_POST['date_to'] ?? date('Y-m-d');
    $employeeId = $_POST['employee_id'] ?? '';
    $source = $_POST['source'] ?? '';
    
    $whereConditions = [];
    $params = [];
    
    $whereConditions[] = "DATE(c.ClockIn) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
    
    if (!empty($employeeId)) {
        $whereConditions[] = "c.EmployeeID = ?";
        $params[] = $employeeId;
    }
    
    if (!empty($source)) {
        $whereConditions[] = "c.ClockInSource = ?";
        $params[] = $source;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $sql = "
        SELECT 
            c.ID,
            c.EmployeeID,
            e.FullName as EmployeeName,
            c.ClockIn,
            c.ClockOut,
            c.WorkDuration,
            c.ClockInSource,
            c.ClockOutSource,
            c.ClockInDevice,
            c.gps_latitude,
            c.gps_longitude,
            c.is_at_workplace,
            c.location_verification_score
        FROM clockinout c
        LEFT JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID
        {$whereClause}
        ORDER BY c.ClockIn DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
}

function handleSaveSettings($conn) {
    $gracePeriod = $_POST['grace_period'] ?? 15;
    $overtimeThreshold = $_POST['overtime_threshold'] ?? 8;
    $locationRadius = $_POST['location_radius'] ?? 100;
    
    // Here you would typically save these settings to a configuration table
    // For now, we'll just return success
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings saved successfully',
        'data' => [
            'grace_period' => $gracePeriod,
            'overtime_threshold' => $overtimeThreshold,
            'location_radius' => $locationRadius
        ]
    ]);
}

function handleCleanupOldData($conn) {
    try {
        $conn->beginTransaction();
        
        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));
        
        // Delete old attendance records
        $stmt1 = $conn->prepare("DELETE FROM tbl_attendance WHERE AttendanceDate < ?");
        $stmt1->execute([$oneYearAgo]);
        $attendanceDeleted = $stmt1->rowCount();
        
        // Delete old clockinout records
        $stmt2 = $conn->prepare("DELETE FROM clockinout WHERE DATE(ClockIn) < ?");
        $stmt2->execute([$oneYearAgo]);
        $clockinoutDeleted = $stmt2->rowCount();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Old data cleaned up successfully',
            'data' => [
                'attendance_deleted' => $attendanceDeleted,
                'clockinout_deleted' => $clockinoutDeleted
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to cleanup data: ' . $e->getMessage()]);
    }
}

function handleSyncTables($conn) {
    try {
        $conn->beginTransaction();
        
        // Find clockinout records without corresponding tbl_attendance records
        $sql = "
            SELECT c.*, DATE(c.ClockIn) as AttendanceDate
            FROM clockinout c
            LEFT JOIN tbl_attendance a ON c.EmployeeID = a.EmployeeID 
                AND DATE(c.ClockIn) = a.AttendanceDate
            WHERE a.AttendanceID IS NULL
            AND c.ClockIn >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        
        $stmt = $conn->query($sql);
        $orphanedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $syncedCount = 0;
        
        foreach ($orphanedRecords as $record) {
            // Get employee's branch
            $branchStmt = $conn->prepare("SELECT BranchID FROM tbl_employees WHERE EmployeeID = ?");
            $branchStmt->execute([$record['EmployeeID']]);
            $employee = $branchStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                // Create attendance record
                $insertStmt = $conn->prepare("
                    INSERT INTO tbl_attendance 
                    (EmployeeID, BranchID, AttendanceDate, ClockIn, ClockOut, Status, ClockInMethod, ClockOutMethod)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $status = 'Present'; // Default status
                if ($record['ClockOut']) {
                    $workDuration = (strtotime($record['ClockOut']) - strtotime($record['ClockIn'])) / 3600;
                    if ($workDuration < 4) {
                        $status = 'Early Leave';
                    }
                }
                
                $insertStmt->execute([
                    $record['EmployeeID'],
                    $employee['BranchID'],
                    $record['AttendanceDate'],
                    date('H:i:s', strtotime($record['ClockIn'])),
                    $record['ClockOut'] ? date('H:i:s', strtotime($record['ClockOut'])) : null,
                    $status,
                    'phone',
                    $record['ClockOut'] ? 'phone' : null
                ]);
                
                $syncedCount++;
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Tables synchronized successfully',
            'data' => [
                'synced_records' => $syncedCount,
                'orphaned_found' => count($orphanedRecords)
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to sync tables: ' . $e->getMessage()]);
    }
}
?>
