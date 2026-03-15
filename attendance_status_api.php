<?php
include 'db.php';
include 'AttendanceManager.php';
include 'EmployeeAuthenticationManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-ID, X-Employee-ID');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $attendanceManager = new AttendanceManager($conn);
    $authManager = new EmployeeAuthenticationManager($conn);
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get employee_id from query parameter
        $employee_id = $_GET['employee_id'] ?? '';
        $date = $_GET['date'] ?? date('Y-m-d');
        
        if (empty($employee_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
            exit;
        }
        
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get employee_id from POST data or session token
        $session_token = $_POST['session_token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $employee_id = $_POST['employee_id'] ?? '';
        $date = $_POST['date'] ?? date('Y-m-d');
        
        // If session token provided, validate and get employee
        if (!empty($session_token)) {
            $session_token = str_replace('Bearer ', '', $session_token);
            $session = $authManager->validateSession($session_token);
            
            if (!$session['valid']) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Invalid or expired session']);
                exit;
            }
            
            $employee_id = $session['employee_id'];
        } else if (empty($employee_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Employee ID or session token is required']);
            exit;
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Get current attendance status using AttendanceManager
    $status = $attendanceManager->getAttendanceStatus($employee_id);
    
    if (!$status['success']) {
        http_response_code(404);
        echo json_encode($status);
        exit;
    }
    
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');
    
    // Get employee details
    $stmt = $conn->prepare("SELECT EmployeeID, FullName, PhoneNumber, BranchID FROM tbl_employees WHERE EmployeeID = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }
    
    // Get branch details
    $branchStmt = $conn->prepare("SELECT BranchName, Latitude, Longitude, AllowedRadius FROM tbl_branches WHERE BranchID = ?");
    $branchStmt->execute([$employee['BranchID']]);
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get employee's schedule for the specified date
    $dayName = date('l', strtotime($date));
    $scheduleStmt = $conn->prepare("
        SELECT es.DayOfWeek, es.StartTime, es.EndTime, es.IsHoliday
        FROM tbl_employee_schedules es
        WHERE es.EmployeeID = ? AND es.DayOfWeek = ?
    ");
    $scheduleStmt->execute([$employee_id, $dayName]);
    $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);
    
    // Check for holidays
    $holidayStmt = $conn->prepare("SELECT Name FROM tbl_holidays WHERE HolidayDate = ?");
    $holidayStmt->execute([$date]);
    $holiday = $holidayStmt->fetch();
    
    // Get attendance records for the specified date from both tables
    $attendanceStmt = $conn->prepare("
        SELECT AttendanceID, ClockIn, ClockOut, ClockInStatus, ClockOutStatus, 
               ClockInPhoto, ClockOutPhoto, Latitude, Longitude, Remarks
        FROM tbl_attendance 
        WHERE EmployeeID = ? AND AttendanceDate = ?
        ORDER BY AttendanceID DESC LIMIT 1
    ");
    $attendanceStmt->execute([$employee_id, $date]);
    $attendanceRecord = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
    
    $clockinoutStmt = $conn->prepare("
        SELECT ID, ClockIn, ClockOut, ClockInSource, ClockOutSource,
               ClockInLocation, ClockOutLocation, gps_latitude, gps_longitude
        FROM clockinout 
        WHERE EmployeeID = ? AND DATE(ClockIn) = ?
        ORDER BY ClockIn DESC LIMIT 1
    ");
    $clockinoutStmt->execute([$employee_id, $date]);
    $clockinoutRecord = $clockinoutStmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate work duration if clocked in
    $workDuration = null;
    $hoursWorked = 0;
    
    if ($attendanceRecord && $attendanceRecord['ClockIn']) {
        $clockInTime = new DateTime($attendanceRecord['ClockIn']);
        $endTime = $attendanceRecord['ClockOut'] ? new DateTime($attendanceRecord['ClockOut']) : new DateTime($current_datetime);
        $workDuration = $endTime->diff($clockInTime);
        $hoursWorked = $workDuration->h + ($workDuration->i / 60) + ($workDuration->d * 24);
    }
    
    // Determine current status
    $currentStatus = 'Not Clocked In';
    $statusColor = '#gray';
    $canClockIn = true;
    $canClockOut = false;
    $nextAction = 'clock_in';
    
    if ($holiday) {
        $currentStatus = 'Holiday';
        $statusColor = '#blue';
        $canClockIn = false;
        $nextAction = 'none';
    } else if ($schedule && $schedule['IsHoliday']) {
        $currentStatus = 'Scheduled Holiday';
        $statusColor = '#blue';
        $canClockIn = false;
        $nextAction = 'none';
    } else if ($attendanceRecord && $attendanceRecord['ClockIn'] && !$attendanceRecord['ClockOut']) {
        $currentStatus = 'Clocked In';
        $statusColor = '#green';
        $canClockIn = false;
        $canClockOut = true;
        $nextAction = 'clock_out';
    } else if ($attendanceRecord && $attendanceRecord['ClockOut']) {
        $currentStatus = 'Clocked Out';
        $statusColor = '#orange';
        $canClockIn = false;
        $canClockOut = false;
        $nextAction = 'none';
    }
    
    // Build response
    $response = [
        'success' => true,
        'data' => [
            'employee' => [
                'id' => $employee['EmployeeID'],
                'name' => $employee['FullName'],
                'phone' => $employee['PhoneNumber'],
                'branch_id' => $employee['BranchID']
            ],
            'branch' => $branch,
            'current_status' => [
                'status' => $currentStatus,
                'status_color' => $statusColor,
                'can_clock_in' => $canClockIn,
                'can_clock_out' => $canClockOut,
                'next_action' => $nextAction,
                'is_holiday' => !empty($holiday) || ($schedule && $schedule['IsHoliday']),
                'holiday_name' => $holiday ? $holiday['Name'] : null
            ],
            'schedule' => $schedule ?: [
                'DayOfWeek' => $dayName,
                'StartTime' => null,
                'EndTime' => null,
                'IsHoliday' => false
            ],
            'today_attendance' => [
                'tbl_attendance' => $attendanceRecord,
                'clockinout' => $clockinoutRecord
            ],
            'work_summary' => [
                'hours_worked' => round($hoursWorked, 2),
                'work_duration_formatted' => $workDuration ? 
                    sprintf('%d hours %d minutes', $workDuration->h + ($workDuration->d * 24), $workDuration->i) : 
                    null,
                'status_timeline' => []
            ],
            'status_details' => $status
        ],
        'timestamp' => $current_datetime
    ];
    
    // Add status timeline
    if ($attendanceRecord) {
        if ($attendanceRecord['ClockIn']) {
            $response['data']['work_summary']['status_timeline'][] = [
                'action' => 'clock_in',
                'time' => $attendanceRecord['ClockIn'],
                'status' => $attendanceRecord['ClockInStatus'],
                'has_photo' => !empty($attendanceRecord['ClockInPhoto'])
            ];
        }
        
        if ($attendanceRecord['ClockOut']) {
            $response['data']['work_summary']['status_timeline'][] = [
                'action' => 'clock_out',
                'time' => $attendanceRecord['ClockOut'],
                'status' => $attendanceRecord['ClockOutStatus'],
                'has_photo' => !empty($attendanceRecord['ClockOutPhoto'])
            ];
        }
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    error_log("Attendance status API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Attendance status API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
