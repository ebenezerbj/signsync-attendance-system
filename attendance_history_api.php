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
    $month = (int)($_POST['month'] ?? date('n'));
    $year = (int)($_POST['year'] ?? date('Y'));
    
    if (empty($employee_id)) {
        throw new Exception('Employee ID is required');
    }
    
    // Validate employee exists
    $stmt = $conn->prepare("SELECT id, name, employee_id FROM employees WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        throw new Exception('Employee not found');
    }
    
    // Build date range for the specified month/year
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date)); // Last day of the month
    
    // Get attendance records for the month
    $sql = "
        SELECT 
            c.id,
            c.employee_id,
            c.action,
            c.timestamp,
            c.latitude,
            c.longitude,
            c.reason,
            c.verification_score,
            DATE(c.timestamp) as attendance_date,
            TIME(c.timestamp) as attendance_time,
            CASE 
                WHEN c.action = 'clock_in' THEN 'Clock In'
                WHEN c.action = 'clock_out' THEN 'Clock Out'
                ELSE c.action
            END as action_display
        FROM clockinout c
        WHERE c.employee_id = ? 
        AND DATE(c.timestamp) BETWEEN ? AND ?
        ORDER BY c.timestamp DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$employee_id, $start_date, $end_date]);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process records to calculate daily summaries
    $daily_summaries = [];
    $total_present_days = 0;
    $total_hours = 0;
    
    foreach ($attendance_records as $record) {
        $date = $record['attendance_date'];
        
        if (!isset($daily_summaries[$date])) {
            $daily_summaries[$date] = [
                'date' => $date,
                'clock_in' => null,
                'clock_out' => null,
                'status' => 'Absent',
                'total_hours' => '0:00',
                'verification_score' => 0
            ];
        }
        
        if ($record['action'] === 'clock_in') {
            $daily_summaries[$date]['clock_in'] = $record['attendance_time'];
            $daily_summaries[$date]['status'] = 'Present';
            $daily_summaries[$date]['verification_score'] = max(
                $daily_summaries[$date]['verification_score'], 
                (int)$record['verification_score']
            );
        } elseif ($record['action'] === 'clock_out') {
            $daily_summaries[$date]['clock_out'] = $record['attendance_time'];
        }
    }
    
    // Calculate working hours for each day
    foreach ($daily_summaries as $date => &$summary) {
        if ($summary['clock_in'] && $summary['clock_out']) {
            $clock_in_time = strtotime($date . ' ' . $summary['clock_in']);
            $clock_out_time = strtotime($date . ' ' . $summary['clock_out']);
            
            if ($clock_out_time > $clock_in_time) {
                $working_seconds = $clock_out_time - $clock_in_time;
                $hours = floor($working_seconds / 3600);
                $minutes = floor(($working_seconds % 3600) / 60);
                $summary['total_hours'] = sprintf('%d:%02d', $hours, $minutes);
                $total_hours += $working_seconds / 3600;
                $total_present_days++;
            }
        } elseif ($summary['clock_in']) {
            $summary['status'] = 'Incomplete';
            $total_present_days++;
        }
    }
    
    // Convert daily summaries to array and sort by date
    $attendance_list = array_values($daily_summaries);
    usort($attendance_list, function($a, $b) {
        return strcmp($b['date'], $a['date']); // Sort descending
    });
    
    // Calculate summary statistics
    $total_working_days = count($daily_summaries);
    $absent_days = $total_working_days - $total_present_days;
    $attendance_percentage = $total_working_days > 0 ? 
        round(($total_present_days / $total_working_days) * 100, 1) : 0;
    
    // Format total hours
    $total_hours_formatted = sprintf('%d:%02d', 
        floor($total_hours), 
        ($total_hours - floor($total_hours)) * 60
    );
    
    $response = [
        'success' => true,
        'message' => 'Attendance history retrieved successfully',
        'data' => $attendance_list,
        'summary' => [
            'month' => $month,
            'year' => $year,
            'total_working_days' => $total_working_days,
            'present_days' => $total_present_days,
            'absent_days' => $absent_days,
            'attendance_percentage' => $attendance_percentage,
            'total_hours' => $total_hours_formatted,
            'employee_name' => $employee['name'],
            'employee_id' => $employee['employee_id']
        ]
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
