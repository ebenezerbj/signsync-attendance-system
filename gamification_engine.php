<?php
/**
 * Enhanced Gamification Engine for SignSync Attendance System
 * Handles achievements, streaks, points, and team challenges
 */

class GamificationEngine {
    private $conn;
    private $smsService;
    
    public function __construct($conn, $smsService = null) {
        $this->conn = $conn;
        $this->smsService = $smsService;
    }
    
    /**
     * Process attendance record for gamification
     */
    public function processAttendance($employeeId, $attendanceDate, $clockInStatus, $clockInTime = null, $clockOutTime = null) {
        // Initialize or get gamification record
        $gamification = $this->getOrCreateGamificationRecord($employeeId);
        
        $points = 0;
        $achievements = [];
        
        // Base attendance points
        $points += 10;
        
        // Punctuality bonus
        if ($clockInStatus === 'On Time') {
            $points += 5;
            $this->updateStreaks($employeeId, $attendanceDate, true);
            
            // Check for early arrival bonus
            if ($clockInTime && $this->isEarlyArrival($clockInTime)) {
                $points += 3;
                $this->incrementCounter($employeeId, 'early_arrivals');
            }
        } else {
            // Break streak for late arrival
            $this->updateStreaks($employeeId, $attendanceDate, false);
        }
        
        // Overtime bonus
        if ($clockOutTime && $this->isOvertime($clockOutTime)) {
            $overtimeHours = $this->calculateOvertimeHours($clockOutTime);
            $points += $overtimeHours * 2;
            $this->incrementCounter($employeeId, 'overtime_hours', $overtimeHours);
        }
        
        // Update points
        $this->addPoints($employeeId, $points);
        
        // Check for achievements
        $achievements = $this->checkAchievements($employeeId);
        
        // Send notifications for new achievements
        foreach ($achievements as $achievement) {
            $this->notifyAchievement($employeeId, $achievement);
        }
        
        return [
            'points_earned' => $points,
            'achievements_unlocked' => $achievements,
            'current_streak' => $this->getCurrentStreak($employeeId)
        ];
    }
    
    /**
     * Update employee streaks
     */
    private function updateStreaks($employeeId, $attendanceDate, $isOnTime) {
        $stmt = $this->conn->prepare("
            SELECT * FROM tbl_gamification WHERE EmployeeID = ?
        ");
        $stmt->execute([$employeeId]);
        $gamification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $lastDate = $gamification['last_attendance_date'];
        $currentStreak = $gamification['streak'] ?? 0;
        $longestStreak = $gamification['longest_streak'] ?? 0;
        
        // Check if this is consecutive day
        $isConsecutive = false;
        if ($lastDate) {
            $lastDateTime = new DateTime($lastDate);
            $currentDateTime = new DateTime($attendanceDate);
            $interval = $currentDateTime->diff($lastDateTime);
            $isConsecutive = ($interval->days === 1);
        }
        
        if ($isOnTime) {
            if ($isConsecutive || !$lastDate) {
                $currentStreak++;
            } else {
                $currentStreak = 1; // Reset streak
            }
            
            // Update longest streak
            if ($currentStreak > $longestStreak) {
                $longestStreak = $currentStreak;
            }
        } else {
            $currentStreak = 0; // Break streak
        }
        
        // Update weekly and monthly streaks
        $weeklyStreak = $this->calculateWeeklyStreak($employeeId, $attendanceDate);
        $monthlyStreak = $this->calculateMonthlyStreak($employeeId, $attendanceDate);
        
        $updateStmt = $this->conn->prepare("
            UPDATE tbl_gamification 
            SET streak = ?, longest_streak = ?, weekly_streak = ?, monthly_streak = ?, 
                last_attendance_date = ?, last_updated = NOW()
            WHERE EmployeeID = ?
        ");
        
        $updateStmt->execute([
            $currentStreak, $longestStreak, $weeklyStreak, $monthlyStreak, 
            $attendanceDate, $employeeId
        ]);
    }
    
    /**
     * Check and unlock achievements
     */
    private function checkAchievements($employeeId) {
        $newAchievements = [];
        
        // Get current gamification data
        $stmt = $this->conn->prepare("
            SELECT g.*, e.DepartmentID 
            FROM tbl_gamification g
            JOIN tbl_employees e ON g.EmployeeID = e.EmployeeID
            WHERE g.EmployeeID = ?
        ");
        $stmt->execute([$employeeId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) return [];
        
        // Get all available achievements
        $achievementStmt = $this->conn->prepare("
            SELECT a.* FROM tbl_achievements a
            WHERE a.is_active = 1
            AND a.id NOT IN (
                SELECT ea.achievement_id FROM tbl_employee_achievements ea 
                WHERE ea.EmployeeID = ?
            )
        ");
        $achievementStmt->execute([$employeeId]);
        $availableAchievements = $achievementStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($availableAchievements as $achievement) {
            $condition = json_decode($achievement['unlock_condition'], true);
            $unlocked = false;
            
            switch ($condition['type']) {
                case 'attendance_days':
                    $unlocked = $this->getTotalAttendanceDays($employeeId) >= $condition['value'];
                    break;
                    
                case 'consecutive_days':
                    $unlocked = $data['streak'] >= $condition['value'];
                    break;
                    
                case 'on_time_streak':
                    $unlocked = $data['streak'] >= $condition['value'];
                    break;
                    
                case 'early_arrival':
                    $unlocked = $data['early_arrivals'] >= $condition['value'];
                    break;
                    
                case 'overtime_count':
                    $unlocked = $this->getMonthlyOvertimeCount($employeeId) >= $condition['value'];
                    break;
                    
                case 'wellness_streak':
                    $unlocked = $this->getWellnessStreak($employeeId) >= $condition['value'];
                    break;
                    
                case 'team_help':
                    $unlocked = $data['team_contributions'] >= $condition['value'];
                    break;
                    
                case 'positive_mood':
                    $unlocked = $this->getPositiveMoodStreak($employeeId) >= $condition['days'];
                    break;
            }
            
            if ($unlocked) {
                $this->unlockAchievement($employeeId, $achievement['id']);
                $this->addPoints($employeeId, $achievement['points_reward']);
                $newAchievements[] = $achievement;
            }
        }
        
        return $newAchievements;
    }
    
    /**
     * Get employee leaderboard position
     */
    public function getLeaderboardPosition($employeeId, $scope = 'global', $metric = 'points') {
        $whereClause = '';
        $params = [];
        
        if ($scope === 'department') {
            $stmt = $this->conn->prepare("SELECT DepartmentID FROM tbl_employees WHERE EmployeeID = ?");
            $stmt->execute([$employeeId]);
            $deptId = $stmt->fetchColumn();
            
            $whereClause = 'WHERE e.DepartmentID = ?';
            $params[] = $deptId;
        }
        
        $orderClause = match($metric) {
            'streak' => 'ORDER BY g.streak DESC, g.longest_streak DESC',
            'longest_streak' => 'ORDER BY g.longest_streak DESC, g.streak DESC',
            default => 'ORDER BY g.points DESC'
        };
        
        $stmt = $this->conn->prepare("
            SELECT g.EmployeeID, e.FullName, g.points, g.streak, g.longest_streak,
                   ROW_NUMBER() OVER ($orderClause) as rank
            FROM tbl_gamification g
            JOIN tbl_employees e ON g.EmployeeID = e.EmployeeID
            $whereClause
            $orderClause
        ");
        
        $stmt->execute($params);
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($leaderboard as $position) {
            if ($position['EmployeeID'] === $employeeId) {
                return $position;
            }
        }
        
        return null;
    }
    
    /**
     * Get team challenge progress
     */
    public function getTeamChallengeProgress($challengeId, $employeeId = null) {
        $stmt = $this->conn->prepare("
            SELECT tc.*, 
                   COUNT(tcp.EmployeeID) as participant_count,
                   AVG(tcp.current_score) as avg_score,
                   MAX(tcp.current_score) as top_score
            FROM tbl_team_challenges tc
            LEFT JOIN tbl_team_challenge_participants tcp ON tc.id = tcp.challenge_id
            WHERE tc.id = ?
            GROUP BY tc.id
        ");
        
        $stmt->execute([$challengeId]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employeeId) {
            $participantStmt = $this->conn->prepare("
                SELECT tcp.*, e.FullName,
                       ROW_NUMBER() OVER (ORDER BY tcp.current_score DESC) as rank
                FROM tbl_team_challenge_participants tcp
                JOIN tbl_employees e ON tcp.EmployeeID = e.EmployeeID
                WHERE tcp.challenge_id = ?
                ORDER BY tcp.current_score DESC
            ");
            
            $participantStmt->execute([$challengeId]);
            $participants = $participantStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $challenge['participants'] = $participants;
            
            // Find employee's position
            foreach ($participants as $participant) {
                if ($participant['EmployeeID'] === $employeeId) {
                    $challenge['employee_rank'] = $participant['rank'];
                    $challenge['employee_score'] = $participant['current_score'];
                    break;
                }
            }
        }
        
        return $challenge;
    }
    
    /**
     * Update team challenge progress
     */
    public function updateTeamChallengeProgress($employeeId) {
        // Get active challenges for the employee
        $stmt = $this->conn->prepare("
            SELECT tc.*, tcp.id as participation_id
            FROM tbl_team_challenges tc
            JOIN tbl_team_challenge_participants tcp ON tc.id = tcp.challenge_id
            WHERE tcp.EmployeeID = ? 
            AND tc.is_active = 1 
            AND tc.start_date <= CURDATE() 
            AND tc.end_date >= CURDATE()
        ");
        
        $stmt->execute([$employeeId]);
        $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($challenges as $challenge) {
            $score = $this->calculateChallengeScore($employeeId, $challenge);
            
            $updateStmt = $this->conn->prepare("
                UPDATE tbl_team_challenge_participants 
                SET current_score = ?, progress = ?
                WHERE id = ?
            ");
            
            $progress = json_encode([
                'last_updated' => date('Y-m-d H:i:s'),
                'percentage' => min(100, ($score / $challenge['target_value']) * 100)
            ]);
            
            $updateStmt->execute([$score, $progress, $challenge['participation_id']]);
        }
    }
    
    /**
     * Send weekly summary with gamification data
     */
    public function sendWeeklySummary($employeeId) {
        if (!$this->smsService) return false;
        
        $position = $this->getLeaderboardPosition($employeeId, 'department', 'points');
        $streak = $this->getCurrentStreak($employeeId);
        $weeklyAttendance = $this->getWeeklyAttendanceCount($employeeId);
        
        return $this->smsService->sendWeeklySummary($employeeId);
    }
    
    /**
     * Helper methods
     */
    private function getOrCreateGamificationRecord($employeeId) {
        $stmt = $this->conn->prepare("SELECT * FROM tbl_gamification WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            $insertStmt = $this->conn->prepare("
                INSERT INTO tbl_gamification (EmployeeID, points, streak, level) 
                VALUES (?, 0, 0, 1)
            ");
            $insertStmt->execute([$employeeId]);
            return $this->getOrCreateGamificationRecord($employeeId);
        }
        
        return $record;
    }
    
    private function addPoints($employeeId, $points) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_gamification 
            SET points = points + ?, last_updated = NOW()
            WHERE EmployeeID = ?
        ");
        $stmt->execute([$points, $employeeId]);
    }
    
    private function getCurrentStreak($employeeId) {
        $stmt = $this->conn->prepare("SELECT streak FROM tbl_gamification WHERE EmployeeID = ?");
        $stmt->execute([$employeeId]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    private function unlockAchievement($employeeId, $achievementId) {
        $stmt = $this->conn->prepare("
            INSERT IGNORE INTO tbl_employee_achievements (EmployeeID, achievement_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$employeeId, $achievementId]);
    }
    
    private function notifyAchievement($employeeId, $achievement) {
        if ($this->smsService) {
            $this->smsService->notifyAchievement($employeeId, $achievement['name']);
        }
    }
    
    private function isEarlyArrival($clockInTime) {
        $standardStart = '08:00:00';
        return $clockInTime < date('H:i:s', strtotime($standardStart . ' -15 minutes'));
    }
    
    private function isOvertime($clockOutTime) {
        $standardEnd = '17:00:00';
        return $clockOutTime > $standardEnd;
    }
    
    private function calculateOvertimeHours($clockOutTime) {
        $standardEnd = new DateTime('17:00:00');
        $actualEnd = new DateTime($clockOutTime);
        $diff = $actualEnd->diff($standardEnd);
        return $diff->h + ($diff->i / 60);
    }
    
    private function incrementCounter($employeeId, $field, $amount = 1) {
        $stmt = $this->conn->prepare("
            UPDATE tbl_gamification 
            SET $field = $field + ?, last_updated = NOW()
            WHERE EmployeeID = ?
        ");
        $stmt->execute([$amount, $employeeId]);
    }
    
    private function getTotalAttendanceDays($employeeId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM tbl_attendance 
            WHERE EmployeeID = ? AND ClockIn IS NOT NULL
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchColumn();
    }
    
    private function getMonthlyOvertimeCount($employeeId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM tbl_attendance 
            WHERE EmployeeID = ? 
            AND ClockOut > '17:00:00' 
            AND AttendanceDate >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchColumn();
    }
    
    private function getWellnessStreak($employeeId) {
        // Implementation depends on wellness data structure
        return 0; // Placeholder
    }
    
    private function getPositiveMoodStreak($employeeId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as streak
            FROM (
                SELECT date, mood,
                       ROW_NUMBER() OVER (ORDER BY date DESC) - 
                       ROW_NUMBER() OVER (PARTITION BY mood IN ('good', 'excellent') ORDER BY date DESC) as grp
                FROM tbl_pulse_surveys 
                WHERE EmployeeID = ? AND mood IN ('good', 'excellent')
                ORDER BY date DESC
            ) grouped
            WHERE grp = 0
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    private function calculateWeeklyStreak($employeeId, $date) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM tbl_attendance 
            WHERE EmployeeID = ? 
            AND ClockInStatus = 'On Time'
            AND AttendanceDate >= DATE_SUB(?, INTERVAL WEEKDAY(?) DAY)
            AND AttendanceDate <= ?
        ");
        $stmt->execute([$employeeId, $date, $date, $date]);
        return $stmt->fetchColumn();
    }
    
    private function calculateMonthlyStreak($employeeId, $date) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM tbl_attendance 
            WHERE EmployeeID = ? 
            AND ClockInStatus = 'On Time'
            AND YEAR(AttendanceDate) = YEAR(?)
            AND MONTH(AttendanceDate) = MONTH(?)
        ");
        $stmt->execute([$employeeId, $date, $date]);
        return $stmt->fetchColumn();
    }
    
    private function calculateChallengeScore($employeeId, $challenge) {
        switch ($challenge['challenge_type']) {
            case 'attendance':
                return $this->getAttendancePercentage($employeeId, $challenge['start_date'], $challenge['end_date']);
            case 'punctuality':
                return $this->getPunctualityPercentage($employeeId, $challenge['start_date'], $challenge['end_date']);
            case 'streak':
                return $this->getCurrentStreak($employeeId);
            case 'wellness':
                return $this->getAverageWellnessScore($employeeId, $challenge['start_date'], $challenge['end_date']);
            default:
                return 0;
        }
    }
    
    private function getAttendancePercentage($employeeId, $startDate, $endDate) {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(CASE WHEN ClockIn IS NOT NULL THEN 1 END) * 100.0 / COUNT(*) as percentage
            FROM tbl_attendance 
            WHERE EmployeeID = ? AND AttendanceDate BETWEEN ? AND ?
        ");
        $stmt->execute([$employeeId, $startDate, $endDate]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    private function getPunctualityPercentage($employeeId, $startDate, $endDate) {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(CASE WHEN ClockInStatus = 'On Time' THEN 1 END) * 100.0 / COUNT(*) as percentage
            FROM tbl_attendance 
            WHERE EmployeeID = ? AND AttendanceDate BETWEEN ? AND ? AND ClockIn IS NOT NULL
        ");
        $stmt->execute([$employeeId, $startDate, $endDate]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    private function getAverageWellnessScore($employeeId, $startDate, $endDate) {
        $stmt = $this->conn->prepare("
            SELECT AVG(wellness_score) FROM tbl_wellness_data 
            WHERE EmployeeID = ? AND date BETWEEN ? AND ?
        ");
        $stmt->execute([$employeeId, $startDate, $endDate]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    private function getWeeklyAttendanceCount($employeeId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM tbl_attendance 
            WHERE EmployeeID = ? 
            AND AttendanceDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND ClockIn IS NOT NULL
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchColumn();
    }
}
?>
