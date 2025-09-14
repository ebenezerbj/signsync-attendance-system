<?php
include_once 'LocationVerificationManager.php';

/**
 * Comprehensive Attendance Management System
 * Handles both tbl_attendance and clockinout tables with proper synchronization
 * Includes enhanced location verification, work duration calculation, and status management
 */
class AttendanceManager {
    private PDO $conn;
    private array $config;
    private LocationVerificationManager $locationManager;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
        $this->locationManager = new LocationVerificationManager($connection);
        $this->config = [
            'work_hours_per_day' => 8,
            'lunch_break_duration' => 1, // hours
            'overtime_threshold' => 8, // hours
            'late_threshold' => 15, // minutes after scheduled start
            'early_leave_threshold' => 15, // minutes before scheduled end
            'location_accuracy_threshold' => 100, // meters
            'workplace_radius' => 200, // meters from workplace center (fallback)
            'timezone' => 'Africa/Accra',
            'require_location_verification' => true,
            'min_location_score' => 60
        ];
        
        date_default_timezone_set($this->config['timezone']);
    }
    
    /**
     * Record clock in for an employee
     */
    public function clockIn(string $employeeId, array $locationData = [], array $additionalData = []): array {
        $employeeId = trim(strtoupper($employeeId));
        $currentTime = new DateTime();
        $today = $currentTime->format('Y-m-d');
        $clockInTime = $currentTime->format('Y-m-d H:i:s');
        
        try {
            $this->conn->beginTransaction();
            
            // 1. Validate employee exists
            $employee = $this->getEmployee($employeeId);
            if (!$employee) {
                throw new Exception('Employee not found');
            }
            
            // 2. Check if already clocked in today
            if ($this->isAlreadyClockedIn($employeeId, $today)) {
                throw new Exception('Already clocked in today');
            }
            
            // 3. Enhanced location verification
            if (!empty($locationData)) {
                $locationData['employee_id'] = $employeeId;
                $locationData['verification_type'] = 'clock_in';
                $locationResult = $this->locationManager->verifyLocation($locationData, $employee['BranchID']);
                
                // Check if location verification meets requirements
                if ($this->config['require_location_verification']) {
                    if ($locationResult['verification_score'] < $this->config['min_location_score']) {
                        return [
                            'success' => false,
                            'message' => 'Location verification failed. Score: ' . round($locationResult['verification_score'], 2) . '%. Please ensure you are at the workplace with good GPS signal.',
                            'location_details' => $locationResult
                        ];
                    }
                }
            } else {
                $locationResult = ['at_workplace' => false, 'verification_score' => 0, 'distance_from_workplace' => null];
            }
            
            // 4. Calculate attendance status
            $attendanceStatus = $this->calculateClockInStatus($currentTime, $employee);
            
            // 5. Insert into clockinout table (detailed tracking)
            $clockinoutId = $this->insertClockInRecord($employeeId, $clockInTime, $locationData, $additionalData, $locationResult);
            
            // 6. Insert/update tbl_attendance (daily summary)
            $attendanceId = $this->insertAttendanceRecord($employeeId, $today, $clockInTime, $attendanceStatus, $locationResult);
            
            // 7. Update gamification points
            $this->updateGamificationPoints($employeeId, 'clock_in', $attendanceStatus);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Successfully clocked in',
                'data' => [
                    'employee_id' => $employeeId,
                    'clock_in_time' => $clockInTime,
                    'status' => $attendanceStatus,
                    'location_verified' => $locationResult['verified'],
                    'clockinout_id' => $clockinoutId,
                    'attendance_id' => $attendanceId,
                    'is_late' => $attendanceStatus === 'Late',
                    'workplace_location' => $locationResult['at_workplace']
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Record clock out for an employee
     */
    public function clockOut(string $employeeId, array $locationData = [], array $additionalData = []): array {
        $employeeId = trim(strtoupper($employeeId));
        $currentTime = new DateTime();
        $today = $currentTime->format('Y-m-d');
        $clockOutTime = $currentTime->format('Y-m-d H:i:s');
        
        try {
            $this->conn->beginTransaction();
            
            // 1. Find today's clock in record
            $clockInRecord = $this->getTodaysClockInRecord($employeeId, $today);
            if (!$clockInRecord) {
                throw new Exception('No clock in record found for today');
            }
            
            // 2. Enhanced location verification for clock out
            $employee = $this->getEmployee($employeeId);
            if (!empty($locationData)) {
                $locationData['employee_id'] = $employeeId;
                $locationData['verification_type'] = 'clock_out';
                $locationResult = $this->locationManager->verifyLocation($locationData, $employee['BranchID']);
            } else {
                $locationResult = ['at_workplace' => false, 'verification_score' => 0, 'distance_from_workplace' => null];
            }
            
            // 3. Calculate work duration and status
            $workDuration = $this->calculateWorkDuration($clockInRecord['ClockIn'], $clockOutTime);
            $attendanceStatus = $this->calculateClockOutStatus($currentTime, $workDuration, $employee);
            
            // 4. Update clockinout record
            $this->updateClockOutRecord($clockInRecord['ID'], $clockOutTime, $workDuration, $locationData, $additionalData, $locationResult);
            
            // 5. Update tbl_attendance record
            $this->updateAttendanceClockOut($employeeId, $today, $clockOutTime, $workDuration, $attendanceStatus);
            
            // 6. Update gamification points
            $this->updateGamificationPoints($employeeId, 'clock_out', $attendanceStatus);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Successfully clocked out',
                'data' => [
                    'employee_id' => $employeeId,
                    'clock_out_time' => $clockOutTime,
                    'work_duration' => $workDuration,
                    'status' => $attendanceStatus,
                    'location_verified' => $locationResult['verified'],
                    'is_overtime' => $workDuration > $this->config['overtime_threshold'],
                    'is_early_leave' => $attendanceStatus === 'Early Leave'
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Get current attendance status for an employee
     */
    public function getAttendanceStatus(string $employeeId): array {
        $employeeId = trim(strtoupper($employeeId));
        $today = date('Y-m-d');
        
        // Get today's clockinout record
        $stmt = $this->conn->prepare("
            SELECT * FROM clockinout 
            WHERE EmployeeID = ? AND DATE(ClockIn) = ?
            ORDER BY ClockIn DESC LIMIT 1
        ");
        $stmt->execute([$employeeId, $today]);
        $clockinoutRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get today's attendance record
        $stmt = $this->conn->prepare("
            SELECT * FROM tbl_attendance 
            WHERE EmployeeID = ? AND AttendanceDate = ?
        ");
        $stmt->execute([$employeeId, $today]);
        $attendanceRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get recent attendance history (last 7 days)
        $stmt = $this->conn->prepare("
            SELECT AttendanceDate, ClockIn, ClockOut, Status 
            FROM tbl_attendance 
            WHERE EmployeeID = ? AND AttendanceDate >= DATE_SUB(?, INTERVAL 7 DAY)
            ORDER BY AttendanceDate DESC
        ");
        $stmt->execute([$employeeId, $today]);
        $recentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $status = 'Not Clocked In';
        $canClockIn = true;
        $canClockOut = false;
        
        if ($clockinoutRecord) {
            if ($clockinoutRecord['ClockOut']) {
                $status = 'Clocked Out';
                $canClockIn = false;
                $canClockOut = false;
            } else {
                $status = 'Clocked In';
                $canClockIn = false;
                $canClockOut = true;
            }
        }
        
        return [
            'success' => true,
            'employee_id' => $employeeId,
            'current_status' => $status,
            'can_clock_in' => $canClockIn,
            'can_clock_out' => $canClockOut,
            'today_record' => [
                'clockinout' => $clockinoutRecord,
                'attendance' => $attendanceRecord
            ],
            'recent_history' => $recentHistory,
            'work_duration_today' => $clockinoutRecord && $clockinoutRecord['ClockOut'] ? 
                $this->calculateWorkDuration($clockinoutRecord['ClockIn'], $clockinoutRecord['ClockOut']) : null
        ];
    }
    
    /**
     * Get attendance records for a date range
     */
    public function getAttendanceRecords(string $employeeId, string $startDate, string $endDate): array {
        $stmt = $this->conn->prepare("
            SELECT a.*, c.ClockInSource, c.ClockOutSource, c.WorkDuration,
                   c.gps_latitude, c.gps_longitude, c.is_at_workplace
            FROM tbl_attendance a
            LEFT JOIN clockinout c ON a.EmployeeID = c.EmployeeID AND a.AttendanceDate = DATE(c.ClockIn)
            WHERE a.EmployeeID = ? AND a.AttendanceDate BETWEEN ? AND ?
            ORDER BY a.AttendanceDate DESC
        ");
        $stmt->execute([$employeeId, $startDate, $endDate]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Private helper methods
    
    private function getEmployee(string $employeeId): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function isAlreadyClockedIn(string $employeeId, string $date): bool {
        $stmt = $this->conn->prepare("
            SELECT ID FROM clockinout 
            WHERE EmployeeID = ? AND DATE(ClockIn) = ? AND ClockOut IS NULL
        ");
        $stmt->execute([$employeeId, $date]);
        return $stmt->fetch() !== false;
    }
    
    private function getTodaysClockInRecord(string $employeeId, string $date): ?array {
        $stmt = $this->conn->prepare("
            SELECT * FROM clockinout 
            WHERE EmployeeID = ? AND DATE(ClockIn) = ? AND ClockOut IS NULL
            ORDER BY ClockIn DESC LIMIT 1
        ");
        $stmt->execute([$employeeId, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Legacy compatibility wrapper for location verification
     * @deprecated Use LocationVerificationManager directly
     */
    private function verifyLocation(array $locationData, string $branchId): array {
        if (empty($locationData)) {
            return [
                'verified' => false,
                'at_workplace' => false,
                'accuracy' => 0,
                'distance_from_workplace' => null
            ];
        }
        
        $result = $this->locationManager->verifyLocation($locationData, $branchId);
        
        // Convert to legacy format for backward compatibility
        return [
            'verified' => $result['verification_score'] > 50,
            'at_workplace' => $result['at_workplace'],
            'accuracy' => $locationData['accuracy'] ?? 0,
            'distance_from_workplace' => $result['distance_from_workplace']
        ];
    }
    
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float {
        $earthRadius = 6371000; // meters
        
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLatRad = deg2rad($lat2 - $lat1);
        $deltaLonRad = deg2rad($lon2 - $lon1);
        
        $a = sin($deltaLatRad / 2) * sin($deltaLatRad / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLonRad / 2) * sin($deltaLonRad / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    private function calculateClockInStatus(DateTime $clockInTime, array $employee): string {
        // TODO: Get scheduled start time from employee/shift data
        $scheduledStart = new DateTime($clockInTime->format('Y-m-d') . ' 08:00:00');
        
        $diffMinutes = ($clockInTime->getTimestamp() - $scheduledStart->getTimestamp()) / 60;
        
        if ($diffMinutes > $this->config['late_threshold']) {
            return 'Late';
        } elseif ($diffMinutes < -30) { // More than 30 minutes early
            return 'Early';
        } else {
            return 'Present';
        }
    }
    
    private function calculateClockOutStatus(DateTime $clockOutTime, float $workDuration, array $employee): string {
        if ($workDuration >= $this->config['overtime_threshold']) {
            return 'Overtime';
        }
        
        // TODO: Get scheduled end time from employee/shift data
        $scheduledEnd = new DateTime($clockOutTime->format('Y-m-d') . ' 17:00:00');
        
        $diffMinutes = ($scheduledEnd->getTimestamp() - $clockOutTime->getTimestamp()) / 60;
        
        if ($diffMinutes > $this->config['early_leave_threshold']) {
            return 'Early Leave';
        } else {
            return 'Complete';
        }
    }
    
    private function calculateWorkDuration(string $clockIn, string $clockOut): float {
        $start = new DateTime($clockIn);
        $end = new DateTime($clockOut);
        $diff = $end->getTimestamp() - $start->getTimestamp();
        
        return round($diff / 3600, 2); // Convert to hours with 2 decimal places
    }
    
    private function insertClockInRecord(string $employeeId, string $clockInTime, array $locationData, array $additionalData, array $locationResult): int {
        $stmt = $this->conn->prepare("
            INSERT INTO clockinout (
                EmployeeID, ClockIn, ClockInSource, ClockInLocation, ClockInDevice,
                gps_latitude, gps_longitude, gps_accuracy, location_method,
                is_at_workplace, location_verification_score, enhanced_location_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $locationString = ($locationData['latitude'] ?? '') . ',' . ($locationData['longitude'] ?? '');
        $enhancedLocationData = json_encode([
            'accuracy' => $locationData['accuracy'] ?? 0,
            'altitude' => $locationData['altitude'] ?? null,
            'speed' => $locationData['speed'] ?? null,
            'bearing' => $locationData['bearing'] ?? null,
            'provider' => $locationData['provider'] ?? 'unknown',
            'timestamp' => time(),
            'distance_from_workplace' => $locationResult['distance_from_workplace']
        ]);
        
        $stmt->execute([
            $employeeId,
            $clockInTime,
            $additionalData['source'] ?? 'Phone App',
            $locationString,
            $additionalData['device'] ?? 'Android Phone',
            $locationData['latitude'] ?? null,
            $locationData['longitude'] ?? null,
            $locationData['accuracy'] ?? null,
            $locationData['provider'] ?? 'gps',
            $locationResult['at_workplace'] ? 1 : 0,
            $locationResult['verified'] ? 100 : 0,
            $enhancedLocationData
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    private function insertAttendanceRecord(string $employeeId, string $date, string $clockInTime, string $status, array $locationResult): int {
        $clockInTimeOnly = date('H:i:s', strtotime($clockInTime));
        
        $stmt = $this->conn->prepare("
            INSERT INTO tbl_attendance (
                EmployeeID, BranchID, AttendanceDate, ClockIn, ClockInStatus, Status,
                Latitude, Longitude, ClockInMethod
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Get employee's branch
        $employee = $this->getEmployee($employeeId);
        $branchId = $employee['BranchID'] ?? 1;
        
        $stmt->execute([
            $employeeId,
            $branchId,
            $date,
            $clockInTimeOnly,
            $status,
            $status,
            $locationResult['verified'] ? ($locationResult['latitude'] ?? null) : null,
            $locationResult['verified'] ? ($locationResult['longitude'] ?? null) : null,
            'photo' // Default method, can be enhanced later
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    private function updateClockOutRecord(int $recordId, string $clockOutTime, float $workDuration, array $locationData, array $additionalData, array $locationResult): void {
        $locationString = ($locationData['latitude'] ?? '') . ',' . ($locationData['longitude'] ?? '');
        
        $stmt = $this->conn->prepare("
            UPDATE clockinout 
            SET ClockOut = ?, ClockOutSource = ?, ClockOutLocation = ?, ClockOutDevice = ?, WorkDuration = ?,
                UpdatedAt = CURRENT_TIMESTAMP
            WHERE ID = ?
        ");
        
        $stmt->execute([
            $clockOutTime,
            $additionalData['source'] ?? 'Phone App',
            $locationString,
            $additionalData['device'] ?? 'Android Phone',
            $workDuration,
            $recordId
        ]);
    }
    
    private function updateAttendanceClockOut(string $employeeId, string $date, string $clockOutTime, float $workDuration, string $finalStatus): void {
        $clockOutTimeOnly = date('H:i:s', strtotime($clockOutTime));
        
        $stmt = $this->conn->prepare("
            UPDATE tbl_attendance 
            SET ClockOut = ?, ClockOutStatus = ?, Status = ?
            WHERE EmployeeID = ? AND AttendanceDate = ?
        ");
        
        $stmt->execute([
            $clockOutTimeOnly,
            $finalStatus,
            $finalStatus,
            $employeeId,
            $date
        ]);
    }
    
    private function updateGamificationPoints(string $employeeId, string $action, string $status): void {
        // Integration with gamification system
        $points = 0;
        
        if ($action === 'clock_in') {
            $points = ($status === 'Present') ? 10 : (($status === 'Late') ? 5 : 8);
        } elseif ($action === 'clock_out') {
            $points = ($status === 'Complete') ? 10 : (($status === 'Overtime') ? 15 : 5);
        }
        
        if ($points > 0) {
            try {
                $stmt = $this->conn->prepare("
                    UPDATE tbl_gamification 
                    SET Points = Points + ?, LastActivity = NOW() 
                    WHERE EmployeeID = ?
                ");
                $stmt->execute([$points, $employeeId]);
            } catch (Exception $e) {
                // Gamification update failed, but don't break attendance recording
                error_log("Gamification update failed: " . $e->getMessage());
            }
        }
    }
}
?>
