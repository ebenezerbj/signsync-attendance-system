<?php
/**
 * Enhanced SMS Notification Service for SignSync Attendance System
 * Builds on existing SMSOnlineGH integration with advanced templating and scheduling
 */

class EnhancedSMSService {
    private $conn;
    private $apiKey;
    private $baseUrl = 'https://api.smsonlinegh.com/v5/message/sms/send';
    private $senderId = 'SIGNSYNC';
    
    // SMS Templates with placeholders
    private $templates = [
        'morning_reminder' => [
            'message' => 'Good morning {name}! 🌅 Don\'t forget to clock in at {branch} by 8:00 AM. Have a productive day! - SignSync',
            'priority' => 'high',
            'schedule_time' => '07:30'
        ],
        'late_arrival_alert' => [
            'message' => 'Hi {name}, you clocked in late at {time}. Your current streak: {streak} days. Let\'s get back on track! 💪 - SignSync',
            'priority' => 'normal'
        ],
        'perfect_attendance_streak' => [
            'message' => 'Congratulations {name}! 🎉 You\'ve achieved {streak} consecutive days of perfect attendance! Keep it up! - SignSync',
            'priority' => 'normal'
        ],
        'forgot_clockout_reminder' => [
            'message' => 'Hi {name}, you forgot to clock out today at {branch}. Please contact HR or use the mobile app to correct this. - SignSync',
            'priority' => 'high'
        ],
        'achievement_unlock' => [
            'message' => 'Amazing {name}! 🏆 You\'ve unlocked "{achievement}" badge! Check your employee portal to see your new achievement. - SignSync',
            'priority' => 'normal'
        ],
        'weekly_summary' => [
            'message' => 'Weekly Summary for {name}: {days_present}/{total_days} days present, Current streak: {streak} days. Rank: #{rank} in your department! 📊 - SignSync',
            'priority' => 'low'
        ],
        'wellness_check' => [
            'message' => 'Hi {name}, we noticed some elevated stress levels. Take a moment to breathe and consider our wellness resources. Your health matters! 💚 - SignSync',
            'priority' => 'high'
        ],
        'team_challenge' => [
            'message' => 'Team Challenge Alert! 🚀 {team_name} is currently #{rank} in the attendance challenge. Help your team climb higher! - SignSync',
            'priority' => 'normal'
        ]
    ];
    
    public function __construct($conn, $apiKey) {
        $this->conn = $conn;
        $this->apiKey = $apiKey;
    }
    
    /**
     * Send templated SMS with dynamic data
     */
    public function sendTemplateMessage($templateName, $phoneNumber, $data = [], $priority = 'normal') {
        if (!isset($this->templates[$templateName])) {
            throw new Exception("SMS template '$templateName' not found");
        }
        
        $template = $this->templates[$templateName];
        $message = $this->populateTemplate($template['message'], $data);
        
        return $this->sendSMS($phoneNumber, $message, $priority);
    }
    
    /**
     * Send immediate SMS
     */
    public function sendSMS($phoneNumber, $message, $priority = 'normal') {
        // Format phone number
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        
        // Log SMS attempt
        $this->logSMS($phoneNumber, $message, $priority, 'sending');
        
        $messageData = [
            'text' => $message,
            'type' => 0,
            'sender' => $this->senderId,
            'destinations' => [$phoneNumber]
        ];
        
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($messageData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: key ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $success = ($httpCode == 200);
        $this->logSMS($phoneNumber, $message, $priority, $success ? 'sent' : 'failed', $response);
        
        return $success;
    }
    
    /**
     * Schedule SMS for later delivery
     */
    public function scheduleSMS($templateName, $phoneNumber, $data = [], $scheduleTime = null) {
        if (!$scheduleTime && isset($this->templates[$templateName]['schedule_time'])) {
            $scheduleTime = $this->templates[$templateName]['schedule_time'];
        }
        
        $message = $this->populateTemplate($this->templates[$templateName]['message'], $data);
        
        $stmt = $this->conn->prepare("
            INSERT INTO tbl_sms_queue (phone_number, message, template_name, schedule_time, priority, status, data_json)
            VALUES (?, ?, ?, ?, ?, 'pending', ?)
        ");
        
        return $stmt->execute([
            $this->formatPhoneNumber($phoneNumber),
            $message,
            $templateName,
            $scheduleTime,
            $this->templates[$templateName]['priority'] ?? 'normal',
            json_encode($data)
        ]);
    }
    
    /**
     * Send morning reminders to all active employees
     */
    public function sendMorningReminders() {
        $stmt = $this->conn->prepare("
            SELECT e.EmployeeID, e.FullName, e.PhoneNumber, b.BranchName, g.streak
            FROM tbl_employees e
            LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
            LEFT JOIN tbl_gamification g ON e.EmployeeID = g.EmployeeID
            WHERE e.Status = 'Active' AND e.PhoneNumber IS NOT NULL
            AND e.EmployeeID NOT IN (
                SELECT EmployeeID FROM tbl_attendance 
                WHERE AttendanceDate = CURDATE() AND ClockIn IS NOT NULL
            )
        ");
        
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sentCount = 0;
        foreach ($employees as $emp) {
            if (empty($emp['PhoneNumber'])) continue;
            
            $data = [
                'name' => $emp['FullName'],
                'branch' => $emp['BranchName'] ?? 'your branch',
                'streak' => $emp['streak'] ?? 0
            ];
            
            try {
                if ($this->sendTemplateMessage('morning_reminder', $emp['PhoneNumber'], $data)) {
                    $sentCount++;
                }
            } catch (Exception $e) {
                error_log("Failed to send morning reminder to {$emp['EmployeeID']}: " . $e->getMessage());
            }
        }
        
        return $sentCount;
    }
    
    /**
     * Check for employees who forgot to clock out and send reminders
     */
    public function sendForgotClockoutReminders() {
        $stmt = $this->conn->prepare("
            SELECT DISTINCT a.EmployeeID, e.FullName, e.PhoneNumber, b.BranchName
            FROM tbl_attendance a
            JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
            LEFT JOIN tbl_branches b ON a.BranchID = b.BranchID
            WHERE a.AttendanceDate = CURDATE()
            AND a.ClockIn IS NOT NULL
            AND a.ClockOut IS NULL
            AND e.PhoneNumber IS NOT NULL
            AND TIME(NOW()) > '18:00:00'
        ");
        
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sentCount = 0;
        foreach ($employees as $emp) {
            $data = [
                'name' => $emp['FullName'],
                'branch' => $emp['BranchName'] ?? 'the office'
            ];
            
            try {
                if ($this->sendTemplateMessage('forgot_clockout_reminder', $emp['PhoneNumber'], $data)) {
                    $sentCount++;
                }
            } catch (Exception $e) {
                error_log("Failed to send clockout reminder to {$emp['EmployeeID']}: " . $e->getMessage());
            }
        }
        
        return $sentCount;
    }
    
    /**
     * Send achievement notifications
     */
    public function notifyAchievement($employeeId, $achievementName) {
        $stmt = $this->conn->prepare("
            SELECT e.FullName, e.PhoneNumber
            FROM tbl_employees e
            WHERE e.EmployeeID = ? AND e.PhoneNumber IS NOT NULL
        ");
        
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) return false;
        
        $data = [
            'name' => $employee['FullName'],
            'achievement' => $achievementName
        ];
        
        return $this->sendTemplateMessage('achievement_unlock', $employee['PhoneNumber'], $data);
    }
    
    /**
     * Send weekly attendance summary
     */
    public function sendWeeklySummary($employeeId) {
        $stmt = $this->conn->prepare("
            SELECT 
                e.FullName, e.PhoneNumber, e.DepartmentID,
                COUNT(a.AttendanceDate) as days_present,
                g.streak,
                (SELECT COUNT(*) FROM tbl_attendance a2 
                 WHERE a2.EmployeeID = e.EmployeeID 
                 AND a2.AttendanceDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                 AND a2.ClockInStatus = 'On Time') as on_time_days
            FROM tbl_employees e
            LEFT JOIN tbl_attendance a ON e.EmployeeID = a.EmployeeID 
                AND a.AttendanceDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            LEFT JOIN tbl_gamification g ON e.EmployeeID = g.EmployeeID
            WHERE e.EmployeeID = ?
            GROUP BY e.EmployeeID
        ");
        
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee || !$employee['PhoneNumber']) return false;
        
        // Get department ranking
        $rankStmt = $this->conn->prepare("
            SELECT COUNT(*) + 1 as rank
            FROM tbl_gamification g
            JOIN tbl_employees e ON g.EmployeeID = e.EmployeeID
            WHERE e.DepartmentID = ? AND g.streak > ?
        ");
        $rankStmt->execute([$employee['DepartmentID'], $employee['streak'] ?? 0]);
        $rank = $rankStmt->fetchColumn();
        
        $data = [
            'name' => $employee['FullName'],
            'days_present' => $employee['days_present'],
            'total_days' => 5, // Assuming 5 work days
            'streak' => $employee['streak'] ?? 0,
            'rank' => $rank
        ];
        
        return $this->sendTemplateMessage('weekly_summary', $employee['PhoneNumber'], $data);
    }
    
    /**
     * Process scheduled SMS queue
     */
    public function processScheduledSMS() {
        $stmt = $this->conn->prepare("
            SELECT * FROM tbl_sms_queue 
            WHERE status = 'pending' 
            AND (schedule_time IS NULL OR TIME(NOW()) >= schedule_time)
            ORDER BY priority DESC, created_at ASC
            LIMIT 50
        ");
        
        $stmt->execute();
        $queuedMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processedCount = 0;
        foreach ($queuedMessages as $sms) {
            try {
                $success = $this->sendSMS($sms['phone_number'], $sms['message'], $sms['priority']);
                
                $updateStmt = $this->conn->prepare("
                    UPDATE tbl_sms_queue 
                    SET status = ?, processed_at = NOW(), attempts = attempts + 1
                    WHERE id = ?
                ");
                $updateStmt->execute([$success ? 'sent' : 'failed', $sms['id']]);
                
                if ($success) $processedCount++;
                
            } catch (Exception $e) {
                error_log("Failed to process queued SMS {$sms['id']}: " . $e->getMessage());
            }
        }
        
        return $processedCount;
    }
    
    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 3) === '233') {
            return $phone;
        } elseif (substr($phone, 0, 1) === '0') {
            return '233' . substr($phone, 1);
        } else {
            return '233' . $phone;
        }
    }
    
    /**
     * Populate template with data
     */
    private function populateTemplate($template, $data) {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }
    
    /**
     * Log SMS activity
     */
    private function logSMS($phoneNumber, $message, $priority, $status, $response = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO tbl_sms_logs (phone_number, message, priority, status, response, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$phoneNumber, $message, $priority, $status, $response]);
        } catch (Exception $e) {
            error_log("Failed to log SMS: " . $e->getMessage());
        }
    }
}

// Example usage:
/*
$smsService = new EnhancedSMSService($conn, 'your-api-key');

// Send morning reminders
$smsService->sendMorningReminders();

// Send achievement notification
$smsService->notifyAchievement('AKCBSTF0005', 'Perfect Attendance - 30 Days');

// Send weekly summary
$smsService->sendWeeklySummary('AKCBSTF0005');

// Process queued messages
$smsService->processScheduledSMS();
*/
?>
