<?php
include 'db.php';

echo "Creating biometric monitoring tables...\n";

try {
    // Employee Wearable Assignments Table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS tbl_employee_wearables (
            WearableID INT AUTO_INCREMENT PRIMARY KEY,
            EmployeeID VARCHAR(15) NOT NULL,
            DeviceID INT NOT NULL,
            AssignedDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            IsActive BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE,
            FOREIGN KEY (DeviceID) REFERENCES tbl_devices(DeviceID) ON DELETE CASCADE,
            UNIQUE KEY unique_active_assignment (EmployeeID, DeviceID, IsActive)
        )
    ");
    echo "✓ Created tbl_employee_wearables\n";

    // Biometric Data Table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS tbl_biometric_data (
            BiometricID INT AUTO_INCREMENT PRIMARY KEY,
            EmployeeID VARCHAR(15) NOT NULL,
            DeviceID INT NOT NULL,
            Timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            HeartRate INT NULL,
            HeartRateVariability DECIMAL(10,2) NULL,
            StressLevel ENUM('low', 'moderate', 'high', 'critical') NULL,
            FatigueLevel ENUM('rested', 'mild', 'moderate', 'severe') NULL,
            SkinTemperature DECIMAL(5,2) NULL,
            BloodOxygen DECIMAL(5,2) NULL,
            StepCount INT NULL,
            SleepQuality ENUM('poor', 'fair', 'good', 'excellent') NULL,
            ActivityLevel ENUM('sedentary', 'light', 'moderate', 'vigorous') NULL,
            RawData JSON NULL COMMENT 'Store additional sensor data',
            DataSource VARCHAR(50) DEFAULT 'wearable' COMMENT 'Source of the data',
            FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE,
            FOREIGN KEY (DeviceID) REFERENCES tbl_devices(DeviceID) ON DELETE CASCADE,
            INDEX idx_employee_timestamp (EmployeeID, Timestamp),
            INDEX idx_device_timestamp (DeviceID, Timestamp),
            INDEX idx_stress_level (StressLevel),
            INDEX idx_fatigue_level (FatigueLevel)
        )
    ");
    echo "✓ Created tbl_biometric_data\n";

    // Biometric Alerts Table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS tbl_biometric_alerts (
            AlertID INT AUTO_INCREMENT PRIMARY KEY,
            EmployeeID VARCHAR(15) NOT NULL,
            AlertType ENUM('stress', 'fatigue', 'health', 'inactivity') NOT NULL,
            Severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            AlertMessage TEXT NOT NULL,
            BiometricData JSON NULL COMMENT 'Related biometric readings',
            IsAcknowledged BOOLEAN DEFAULT FALSE,
            AcknowledgedBy VARCHAR(15) NULL,
            AcknowledgedAt TIMESTAMP NULL,
            CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE,
            INDEX idx_employee_alerts (EmployeeID, CreatedAt),
            INDEX idx_alert_type (AlertType),
            INDEX idx_severity (Severity),
            INDEX idx_unacknowledged (IsAcknowledged, CreatedAt)
        )
    ");
    echo "✓ Created tbl_biometric_alerts\n";

    // Wellness Reports Table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS tbl_wellness_reports (
            ReportID INT AUTO_INCREMENT PRIMARY KEY,
            EmployeeID VARCHAR(15) NOT NULL,
            ReportDate DATE NOT NULL,
            AvgStressLevel DECIMAL(3,2) NULL COMMENT 'Average stress score (1-5)',
            AvgFatigueLevel DECIMAL(3,2) NULL COMMENT 'Average fatigue score (1-5)',
            AvgHeartRate DECIMAL(6,2) NULL,
            TotalSteps INT NULL,
            ActiveMinutes INT NULL,
            SleepHours DECIMAL(4,2) NULL,
            WellnessScore DECIMAL(5,2) NULL COMMENT 'Overall wellness score (0-100)',
            Recommendations JSON NULL COMMENT 'AI-generated wellness recommendations',
            CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE,
            UNIQUE KEY unique_employee_date (EmployeeID, ReportDate),
            INDEX idx_report_date (ReportDate),
            INDEX idx_wellness_score (WellnessScore)
        )
    ");
    echo "✓ Created tbl_wellness_reports\n";

    // Biometric Thresholds Table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS tbl_biometric_thresholds (
            ThresholdID INT AUTO_INCREMENT PRIMARY KEY,
            EmployeeID VARCHAR(15) NULL COMMENT 'NULL for global thresholds',
            BranchID VARCHAR(15) NULL COMMENT 'Branch-specific thresholds',
            MetricType ENUM('heart_rate', 'stress', 'fatigue', 'temperature', 'oxygen', 'inactivity') NOT NULL,
            LowThreshold DECIMAL(10,2) NULL,
            MediumThreshold DECIMAL(10,2) NULL,
            HighThreshold DECIMAL(10,2) NULL,
            CriticalThreshold DECIMAL(10,2) NULL,
            IsActive BOOLEAN DEFAULT TRUE,
            CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID) ON DELETE CASCADE,
            FOREIGN KEY (BranchID) REFERENCES tbl_branches(BranchID) ON DELETE CASCADE,
            INDEX idx_employee_metric (EmployeeID, MetricType),
            INDEX idx_branch_metric (BranchID, MetricType)
        )
    ");
    echo "✓ Created tbl_biometric_thresholds\n";

    // Insert default global thresholds
    $conn->exec("
        INSERT INTO tbl_biometric_thresholds (MetricType, LowThreshold, MediumThreshold, HighThreshold, CriticalThreshold) VALUES
        ('heart_rate', 60, 100, 120, 150),
        ('stress', 1, 2, 3, 4),
        ('fatigue', 1, 2, 3, 4),
        ('temperature', 36.0, 37.0, 38.0, 39.0),
        ('oxygen', 95, 90, 85, 80),
        ('inactivity', 60, 120, 180, 240)
        ON DUPLICATE KEY UPDATE UpdatedAt = CURRENT_TIMESTAMP
    ");
    echo "✓ Inserted default thresholds\n";

    echo "\n✅ All biometric monitoring tables created successfully!\n";

} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
