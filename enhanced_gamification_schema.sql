-- Enhanced SMS Service Database Tables
-- Creates tables for SMS queue, logs, and gamification enhancements

-- SMS Queue for scheduled messages
CREATE TABLE IF NOT EXISTS `tbl_sms_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `phone_number` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `template_name` VARCHAR(50),
    `schedule_time` TIME,
    `priority` ENUM('low', 'normal', 'high') DEFAULT 'normal',
    `status` ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    `data_json` JSON,
    `attempts` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `processed_at` TIMESTAMP NULL,
    INDEX `idx_status_schedule` (`status`, `schedule_time`),
    INDEX `idx_priority` (`priority`)
);

-- SMS Logs for tracking all sent messages
CREATE TABLE IF NOT EXISTS `tbl_sms_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `phone_number` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `priority` ENUM('low', 'normal', 'high') DEFAULT 'normal',
    `status` ENUM('sending', 'sent', 'failed') NOT NULL,
    `response` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_phone_date` (`phone_number`, `created_at`),
    INDEX `idx_status` (`status`)
);

-- Enhanced Gamification table (if not exists, or alter existing)
CREATE TABLE IF NOT EXISTS `tbl_gamification` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `EmployeeID` VARCHAR(50) NOT NULL,
    `points` INT DEFAULT 0,
    `streak` INT DEFAULT 0,
    `longest_streak` INT DEFAULT 0,
    `weekly_streak` INT DEFAULT 0,
    `monthly_streak` INT DEFAULT 0,
    `level` INT DEFAULT 1,
    `badges` JSON,
    `achievements` JSON,
    `last_attendance_date` DATE,
    `perfect_months` INT DEFAULT 0,
    `early_arrivals` INT DEFAULT 0,
    `overtime_hours` DECIMAL(5,2) DEFAULT 0,
    `team_contributions` INT DEFAULT 0,
    `wellness_score` DECIMAL(3,1) DEFAULT 0,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_employee` (`EmployeeID`),
    FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees`(`EmployeeID`) ON DELETE CASCADE
);

-- Achievement definitions
CREATE TABLE IF NOT EXISTS `tbl_achievements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `icon` VARCHAR(50),
    `category` ENUM('attendance', 'punctuality', 'streak', 'wellness', 'team', 'milestone') NOT NULL,
    `unlock_condition` JSON,
    `points_reward` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Employee achievements tracking
CREATE TABLE IF NOT EXISTS `tbl_employee_achievements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `EmployeeID` VARCHAR(50) NOT NULL,
    `achievement_id` INT NOT NULL,
    `unlocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notified` BOOLEAN DEFAULT FALSE,
    UNIQUE KEY `unique_employee_achievement` (`EmployeeID`, `achievement_id`),
    FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees`(`EmployeeID`) ON DELETE CASCADE,
    FOREIGN KEY (`achievement_id`) REFERENCES `tbl_achievements`(`id`) ON DELETE CASCADE
);

-- Team challenges
CREATE TABLE IF NOT EXISTS `tbl_team_challenges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `challenge_type` ENUM('attendance', 'punctuality', 'streak', 'wellness') NOT NULL,
    `target_value` DECIMAL(10,2),
    `department_id` INT,
    `branch_id` INT,
    `is_active` BOOLEAN DEFAULT TRUE,
    `rewards` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Team challenge participation
CREATE TABLE IF NOT EXISTS `tbl_team_challenge_participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `challenge_id` INT NOT NULL,
    `EmployeeID` VARCHAR(50) NOT NULL,
    `current_score` DECIMAL(10,2) DEFAULT 0,
    `progress` JSON,
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_challenge_employee` (`challenge_id`, `EmployeeID`),
    FOREIGN KEY (`challenge_id`) REFERENCES `tbl_team_challenges`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees`(`EmployeeID`) ON DELETE CASCADE
);

-- Wellness data integration
CREATE TABLE IF NOT EXISTS `tbl_wellness_data` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `EmployeeID` VARCHAR(50) NOT NULL,
    `date` DATE NOT NULL,
    `stress_level` DECIMAL(3,1),
    `heart_rate_avg` INT,
    `steps` INT,
    `sleep_hours` DECIMAL(3,1),
    `mood_score` INT,
    `wellness_score` DECIMAL(3,1),
    `source` ENUM('wearable', 'manual', 'survey') DEFAULT 'manual',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_employee_date` (`EmployeeID`, `date`),
    FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees`(`EmployeeID`) ON DELETE CASCADE
);

-- Pulse surveys enhancement
CREATE TABLE IF NOT EXISTS `tbl_pulse_surveys` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` VARCHAR(50) NOT NULL,
    `date` DATE NOT NULL,
    `mood` ENUM('very_bad', 'bad', 'neutral', 'good', 'excellent') NOT NULL,
    `stress_level` INT DEFAULT NULL,
    `workload` ENUM('very_light', 'light', 'balanced', 'heavy', 'overwhelming') DEFAULT NULL,
    `satisfaction` INT DEFAULT NULL,
    `comment` TEXT,
    `anonymous` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `tbl_employees`(`EmployeeID`) ON DELETE CASCADE
);

-- Insert default achievements
INSERT IGNORE INTO `tbl_achievements` (`name`, `description`, `icon`, `category`, `unlock_condition`, `points_reward`) VALUES
('First Day', 'Welcome! You''ve completed your first day at work', '🎉', 'milestone', '{"type": "attendance_days", "value": 1}', 10),
('Early Bird', 'Arrived 15 minutes early', '🐦', 'punctuality', '{"type": "early_arrival", "minutes": 15}', 5),
('Perfect Week', 'Perfect attendance for 5 consecutive days', '📅', 'streak', '{"type": "consecutive_days", "value": 5}', 25),
('Perfect Month', 'Perfect attendance for 30 consecutive days', '🏆', 'streak', '{"type": "consecutive_days", "value": 30}', 100),
('Century Club', 'Perfect attendance for 100 consecutive days', '💯', 'streak', '{"type": "consecutive_days", "value": 100}', 500),
('Wellness Warrior', 'Maintained excellent wellness score for a week', '💪', 'wellness', '{"type": "wellness_streak", "value": 7}', 50),
('Team Player', 'Helped 5 colleagues with attendance corrections', '🤝', 'team', '{"type": "team_help", "value": 5}', 30),
('Punctuality Pro', 'On time for 50 consecutive days', '⏰', 'punctuality', '{"type": "on_time_streak", "value": 50}', 200),
('Dedication Master', 'Worked overtime 10 times this month', '⚡', 'milestone', '{"type": "overtime_count", "value": 10}', 75),
('Mood Booster', 'Consistently positive mood ratings for 2 weeks', '😊', 'wellness', '{"type": "positive_mood", "days": 14}', 40);

-- Insert sample team challenges
INSERT IGNORE INTO `tbl_team_challenges` (`name`, `description`, `start_date`, `end_date`, `challenge_type`, `target_value`) VALUES
('December Perfect Attendance', 'Can your department achieve 95% attendance this month?', '2025-12-01', '2025-12-31', 'attendance', 95.0),
('New Year Punctuality Challenge', 'Start the year right with perfect punctuality!', '2026-01-01', '2026-01-31', 'punctuality', 90.0),
('Wellness Warriors', 'Maintain high wellness scores throughout the month', '2025-12-01', '2025-12-31', 'wellness', 8.0);
