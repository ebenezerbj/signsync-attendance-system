<?php
include 'db.php';
include 'AttendanceManager.php';
include 'EmployeeAuthenticationManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $attendanceManager = new AttendanceManager($conn);
    $authManager = new EmployeeAuthenticationManager($conn);
    
    // Input validation and collection
    $session_token = $_POST['session_token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $snapshot = $_POST['snapshot'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $test_mode = $_POST['test_mode'] ?? 'false';
    
    // Validate session token if provided
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
    
    if (empty($action)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action is required']);
        exit;
    }

    if (!in_array($action, ['clock_in', 'clock_out'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action. Use clock_in or clock_out']);
        exit;
    }

    if ($latitude === null || $longitude === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Location data is required']);
        exit;
    }
    
    // Handle photo processing
    $photoPath = null;
    if (!empty($snapshot)) {
        // Validate and save photo
        $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $snapshot);
        $data = base64_decode($imageData);

        if ($data === false || empty($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid image data provided']);
            exit;
        }

        // Ensure uploads directory exists
        $uploadDir = 'uploads';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Cannot create upload directory']);
            exit;
        }

        $photoPath = $uploadDir . "/{$employee_id}_" . time() . ".png";
        if (!file_put_contents($photoPath, $data)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save photo']);
            exit;
        }
    }
    
    // Prepare location and additional data
    $locationData = [
        'latitude' => $latitude,
        'longitude' => $longitude
    ];
    $additionalData = [
        'photo_path' => $photoPath,
        'reason' => $reason
    ];
    
    // Add bypass mode for testing if requested
    if ($test_mode === 'true') {
        $additionalData['bypass_location_verification'] = true;
        $additionalData['test_mode'] = true;
    }
    
    if ($action === 'clock_in') {
        $result = $attendanceManager->clockIn($employee_id, $locationData, $additionalData);
    } else {
        $result = $attendanceManager->clockOut($employee_id, $locationData, $additionalData);
    }
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }

} catch (PDOException $e) {
    error_log("Enhanced clockinout API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Enhanced clockinout API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

try {
    $current_time = date('H:i:s');
    $current_datetime = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $dayName = date('l'); // Monday, Tuesday, etc.

    // --- 1. VALIDATE EMPLOYEE ---
    $stmt = $conn->prepare("SELECT EmployeeID, FullName, PhoneNumber, BranchID FROM tbl_employees WHERE EmployeeID = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid Employee ID']);
        exit;
    }

    // --- 2. CHECK FOR HOLIDAYS ---
    $holidayStmt = $conn->prepare("SELECT Name FROM tbl_holidays WHERE HolidayDate = ?");
    $holidayStmt->execute([$today]);
    $holiday = $holidayStmt->fetch();
    
    if ($holiday) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Today is a public holiday (' . $holiday['Name'] . '). Clock-in is not required.'
        ]);
        exit;
    }

    // --- 3. GET EMPLOYEE'S SCHEDULE ---
    $scheduleStmt = $conn->prepare("
        SELECT es.DayOfWeek, es.StartTime, es.EndTime, es.IsHoliday
        FROM tbl_employee_schedules es
        WHERE es.EmployeeID = ? AND es.DayOfWeek = ?
    ");
    $scheduleStmt->execute([$employee_id, $dayName]);
    $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No schedule found for today (' . $dayName . ')']);
        exit;
    }

    if ($schedule['IsHoliday']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Today is marked as a holiday in your schedule']);
        exit;
    }

    // Use schedule times instead of shift times
    $shiftStart = $schedule['StartTime'];
    $shiftEnd = $schedule['EndTime'];
    $gracePeriod = 15; // Default grace period

    // --- 4. GET EMPLOYEE'S BRANCH AND VALIDATE LOCATION ---
    $branchStmt = $conn->prepare("
        SELECT b.BranchID, b.BranchName, b.Latitude, b.Longitude, b.AllowedRadius 
        FROM tbl_branches b
        JOIN tbl_employees e ON e.BranchID = b.BranchID
        WHERE e.EmployeeID = ?
    ");
    $branchStmt->execute([$employee_id]);
    $branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$branches) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No branch assigned to this employee']);
        exit;
    }

    // Haversine distance calculation function
    function haversine($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371000; // Earth radius in meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earth_radius * $c;
    }

    // Validate location against branches
    $matchedBranch = null;
    $closestDistance = PHP_FLOAT_MAX;
    $locationDetails = [];

    foreach ($branches as $branch) {
        $branchLat = (float)$branch['Latitude'];
        $branchLon = (float)$branch['Longitude'];
        $allowedRadius = (float)$branch['AllowedRadius'];
        
        $distance = haversine($latitude, $longitude, $branchLat, $branchLon);
        $locationDetails[] = [
            'branch' => $branch['BranchName'],
            'distance' => round($distance, 2),
            'allowed_radius' => $allowedRadius,
            'within_range' => $distance <= $allowedRadius
        ];

        if ($distance <= $allowedRadius && $distance < $closestDistance) {
            $matchedBranch = $branch;
            $closestDistance = $distance;
        }
    }

    if (!$matchedBranch) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Location validation failed: Not within any assigned branch geofence',
            'location_details' => $locationDetails
        ]);
        exit;
    }

    // --- 6. HANDLE PHOTO PROCESSING ---
    $filename = null;
    if (!empty($snapshot)) {
        // Validate and save photo
        $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $snapshot);
        $data = base64_decode($imageData);

        if ($data === false || empty($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid image data provided']);
            exit;
        }

        // Ensure uploads directory exists
        $uploadDir = 'uploads';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Cannot create upload directory']);
            exit;
        }

        $filename = $uploadDir . "/{$employee_id}_" . time() . ".png";
        if (!file_put_contents($filename, $data)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save photo']);
            exit;
        }
    }

    // --- 6. CLOCK IN/OUT LOGIC ---
    // Use schedule times instead of shift times
    $gracePeriod = 15; // Default grace period

    // Check for existing attendance record today
    $existingStmt = $conn->prepare("
        SELECT AttendanceID, ClockIn, ClockOut, ClockInStatus, BranchID
        FROM tbl_attendance 
        WHERE EmployeeID = ? AND AttendanceDate = ?
        ORDER BY AttendanceID DESC LIMIT 1
    ");
    $existingStmt->execute([$employee_id, $today]);
    $existingRecord = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($action === 'clock_in') {
        // CLOCK IN LOGIC
        if ($existingRecord && !$existingRecord['ClockOut']) {
            echo json_encode(['success' => false, 'message' => 'Already clocked in today']);
            exit;
        }

        // Determine status based on shift time
        $startTimeWithGrace = date('H:i:s', strtotime($shiftStart . " +{$gracePeriod} minutes"));
        $status = ($current_time <= $startTimeWithGrace) ? 'On Time' : 'Late';

        // Insert attendance record
        $insertStmt = $conn->prepare("
            INSERT INTO tbl_attendance 
            (EmployeeID, BranchID, AttendanceDate, ClockIn, ClockInPhoto, ClockInStatus, Latitude, Longitude, Remarks, ClockInMethod)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'photo')
        ");
        $insertStmt->execute([
            $employee_id, 
            $matchedBranch['BranchID'], 
            $today, 
            $current_datetime, 
            $filename, 
            $status, 
            $latitude, 
            $longitude, 
            $reason
        ]);

        $response = [
            'success' => true,
            'message' => 'Successfully clocked in',
            'data' => [
                'employee_id' => $employee_id,
                'employee_name' => $employee['FullName'],
                'action' => 'clock_in',
                'clock_in_time' => $current_datetime,
                'status' => $status,
                'branch' => $matchedBranch['BranchName'],
                'schedule_start' => $shiftStart,
                'schedule_end' => $shiftEnd,
                'location_distance' => round($closestDistance, 2) . 'm',
                'is_late' => $status === 'Late'
            ]
        ];

    } else {
        // CLOCK OUT LOGIC
        if (!$existingRecord || $existingRecord['ClockOut']) {
            echo json_encode(['success' => false, 'message' => 'No clock in record found for today or already clocked out']);
            exit;
        }

        // Validate same branch for clock out
        if ($existingRecord['BranchID'] != $matchedBranch['BranchID']) {
            echo json_encode(['success' => false, 'message' => 'You must clock out from the same branch you clocked in']);
            exit;
        }

        // Determine status based on shift end time
        $status = ($current_time >= $shiftEnd) ? 'On Time' : 'Left Early';

        // Update attendance record
        $updateStmt = $conn->prepare("
            UPDATE tbl_attendance 
            SET ClockOut = ?, ClockOutPhoto = ?, ClockOutStatus = ?, Latitude = ?, Longitude = ?, Remarks = CONCAT(COALESCE(Remarks, ''), ?, ' | Clock Out: ', ?), ClockOutMethod = 'photo'
            WHERE AttendanceID = ?
        ");
        $updateStmt->execute([
            $current_datetime, 
            $filename, 
            $status, 
            $latitude, 
            $longitude, 
            $reason ? " | " . $reason : "",
            $reason ?: "No reason provided",
            $existingRecord['AttendanceID']
        ]);

        // Calculate work duration
        $clockInTime = new DateTime($existingRecord['ClockIn']);
        $clockOutTime = new DateTime($current_datetime);
        $workDuration = $clockOutTime->diff($clockInTime);
        $hoursWorked = $workDuration->h + ($workDuration->i / 60);

        $response = [
            'success' => true,
            'message' => 'Successfully clocked out',
            'data' => [
                'employee_id' => $employee_id,
                'employee_name' => $employee['FullName'],
                'action' => 'clock_out',
                'clock_out_time' => $current_datetime,
                'status' => $status,
                'branch' => $matchedBranch['BranchName'],
                'schedule_start' => $shiftStart,
                'schedule_end' => $shiftEnd,
                'hours_worked' => round($hoursWorked, 2),
                'location_distance' => round($closestDistance, 2) . 'm',
                'is_early_departure' => $status === 'Left Early'
            ]
        ];
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Enhanced clockinout API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
