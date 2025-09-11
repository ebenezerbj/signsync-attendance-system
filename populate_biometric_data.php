<?php
/**
 * Populate Sample Biometric Data for Testing
 * This script creates sample wearable assignments and biometric readings
 */

include 'db.php';

echo "Populating sample biometric data...\n";

try {
    // First, let's create some sample IoT wearable devices if they don't exist
    $sampleDevices = [
        ['Apple Watch Series 9', 'AW-001-001', 'Apple', 'Series 9'],
        ['Fitbit Versa 4', 'FB-004-001', 'Fitbit', 'Versa 4'],
        ['Samsung Galaxy Watch 6', 'SW-006-001', 'Samsung', 'Galaxy Watch 6'],
        ['Garmin Vivosmart 5', 'GV-005-001', 'Garmin', 'Vivosmart 5'],
        ['Amazfit GTR 4', 'AM-004-001', 'Amazfit', 'GTR 4']
    ];
    
    foreach ($sampleDevices as $device) {
        $checkDevice = $conn->prepare("SELECT COUNT(*) FROM tbl_devices WHERE Identifier = ?");
        $checkDevice->execute([$device[1]]);
        
        if ($checkDevice->fetchColumn() == 0) {
            $insertDevice = $conn->prepare("
                INSERT INTO tbl_devices 
                (DeviceName, DeviceType, Identifier, Manufacturer, Model, Description, IsActive, CreatedAt)
                VALUES (?, 'iot', ?, ?, ?, 'Biometric monitoring wearable device', 1, NOW())
            ");
            $insertDevice->execute([$device[0], $device[1], $device[2], $device[3]]);
            echo "✓ Created wearable device: {$device[0]}\n";
        }
    }
    
    // Get some employees to assign wearables to
    $employees = $conn->query("SELECT EmployeeID FROM tbl_employees LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    $devices = $conn->query("SELECT DeviceID FROM tbl_devices WHERE DeviceType = 'iot' LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($employees) || empty($devices)) {
        echo "❌ No employees or devices found. Please add employees and devices first.\n";
        exit;
    }
    
    // Assign wearables to employees
    for ($i = 0; $i < min(count($employees), count($devices)); $i++) {
        $assignWearable = $conn->prepare("
            INSERT INTO tbl_employee_wearables (EmployeeID, DeviceID, IsActive)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE IsActive = 1, AssignedDate = NOW()
        ");
        $assignWearable->execute([$employees[$i], $devices[$i]]);
        echo "✓ Assigned wearable to employee: {$employees[$i]}\n";
    }
    
    // Generate sample biometric data for the last 7 days
    $stressLevels = ['low', 'moderate', 'high', 'critical'];
    $fatigueLevels = ['rested', 'mild', 'moderate', 'severe'];
    $sleepQualities = ['poor', 'fair', 'good', 'excellent'];
    $activityLevels = ['sedentary', 'light', 'moderate', 'vigorous'];
    
    for ($day = 7; $day >= 0; $day--) {
        $date = date('Y-m-d H:i:s', strtotime("-$day days"));
        
        foreach ($employees as $index => $employeeId) {
            if ($index >= count($devices)) break;
            
            $deviceId = $devices[$index];
            
            // Generate 3-5 readings per day per employee
            $readingsPerDay = rand(3, 5);
            
            for ($reading = 0; $reading < $readingsPerDay; $reading++) {
                $hour = rand(8, 18); // Business hours
                $minute = rand(0, 59);
                $timestamp = date('Y-m-d H:i:s', strtotime("-$day days $hour:$minute"));
                
                // Generate realistic biometric data
                $baseHeartRate = 70;
                $stressModifier = 0;
                
                // Higher stress during mid-day
                if ($hour >= 12 && $hour <= 14) {
                    $stressModifier = rand(5, 15);
                }
                
                $heartRate = $baseHeartRate + rand(-10, 20) + $stressModifier;
                $hrv = rand(25, 55) - ($stressModifier * 0.5); // Lower HRV = higher stress
                $skinTemp = 36.5 + (rand(0, 10) / 10);
                $bloodOxygen = rand(95, 100);
                $stepCount = rand(1000, 12000);
                
                // Determine stress and fatigue based on data
                if ($heartRate > 100 || $hrv < 30) {
                    $stressLevel = $heartRate > 120 ? 'critical' : 'high';
                } elseif ($heartRate > 85 || $hrv < 40) {
                    $stressLevel = 'moderate';
                } else {
                    $stressLevel = 'low';
                }
                
                if ($stepCount < 3000 || $heartRate > 90) {
                    $fatigueLevel = 'moderate';
                } elseif ($stepCount < 5000) {
                    $fatigueLevel = 'mild';
                } else {
                    $fatigueLevel = 'rested';
                }
                
                $sleepQuality = $sleepQualities[array_rand($sleepQualities)];
                $activityLevel = $stepCount > 8000 ? 'vigorous' : ($stepCount > 5000 ? 'moderate' : 'light');
                
                $insertBiometric = $conn->prepare("
                    INSERT INTO tbl_biometric_data 
                    (EmployeeID, DeviceID, Timestamp, HeartRate, HeartRateVariability, 
                     StressLevel, FatigueLevel, SkinTemperature, BloodOxygen, StepCount, 
                     SleepQuality, ActivityLevel, DataSource)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sample_data')
                ");
                
                $insertBiometric->execute([
                    $employeeId, $deviceId, $timestamp, $heartRate, $hrv,
                    $stressLevel, $fatigueLevel, $skinTemp, $bloodOxygen, $stepCount,
                    $sleepQuality, $activityLevel
                ]);
                
                // Generate alerts for high stress/fatigue
                if ($stressLevel === 'critical' || $stressLevel === 'high') {
                    $alertMessage = "High stress level detected: $stressLevel (HR: $heartRate, HRV: $hrv)";
                    $severity = $stressLevel === 'critical' ? 'critical' : 'high';
                    
                    $insertAlert = $conn->prepare("
                        INSERT INTO tbl_biometric_alerts 
                        (EmployeeID, AlertType, Severity, AlertMessage, CreatedAt)
                        VALUES (?, 'stress', ?, ?, ?)
                    ");
                    $insertAlert->execute([$employeeId, $severity, $alertMessage, $timestamp]);
                }
                
                if ($fatigueLevel === 'moderate' && rand(1, 100) <= 30) { // 30% chance for moderate fatigue alert
                    $alertMessage = "Moderate fatigue detected (Steps: $stepCount, HR: $heartRate)";
                    
                    $insertAlert = $conn->prepare("
                        INSERT INTO tbl_biometric_alerts 
                        (EmployeeID, AlertType, Severity, AlertMessage, CreatedAt)
                        VALUES (?, 'fatigue', 'medium', ?, ?)
                    ");
                    $insertAlert->execute([$employeeId, $alertMessage, $timestamp]);
                }
            }
        }
    }
    
    // Generate some wellness reports
    for ($day = 7; $day >= 1; $day--) {
        $reportDate = date('Y-m-d', strtotime("-$day days"));
        
        foreach ($employees as $employeeId) {
            // Calculate daily averages
            $dailyAvg = $conn->prepare("
                SELECT 
                    AVG(CASE StressLevel 
                        WHEN 'low' THEN 1 
                        WHEN 'moderate' THEN 2 
                        WHEN 'high' THEN 3 
                        WHEN 'critical' THEN 4 
                    END) as avg_stress,
                    AVG(CASE FatigueLevel 
                        WHEN 'rested' THEN 1 
                        WHEN 'mild' THEN 2 
                        WHEN 'moderate' THEN 3 
                        WHEN 'severe' THEN 4 
                    END) as avg_fatigue,
                    AVG(HeartRate) as avg_hr,
                    MAX(StepCount) as total_steps
                FROM tbl_biometric_data 
                WHERE EmployeeID = ? AND DATE(Timestamp) = ?
            ");
            $dailyAvg->execute([$employeeId, $reportDate]);
            $avgData = $dailyAvg->fetch(PDO::FETCH_ASSOC);
            
            if ($avgData['avg_stress']) {
                $wellnessScore = 100 - (($avgData['avg_stress'] - 1) * 25) - (($avgData['avg_fatigue'] - 1) * 15);
                $wellnessScore = max(0, min(100, $wellnessScore));
                
                $insertReport = $conn->prepare("
                    INSERT INTO tbl_wellness_reports 
                    (EmployeeID, ReportDate, AvgStressLevel, AvgFatigueLevel, 
                     AvgHeartRate, TotalSteps, WellnessScore)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    AvgStressLevel = VALUES(AvgStressLevel),
                    AvgFatigueLevel = VALUES(AvgFatigueLevel),
                    WellnessScore = VALUES(WellnessScore)
                ");
                $insertReport->execute([
                    $employeeId, $reportDate, $avgData['avg_stress'], $avgData['avg_fatigue'],
                    $avgData['avg_hr'], $avgData['total_steps'], $wellnessScore
                ]);
            }
        }
    }
    
    echo "\n✅ Sample biometric data population completed!\n";
    echo "📊 Generated data:\n";
    
    $stats = $conn->query("
        SELECT 
            COUNT(*) as total_readings,
            COUNT(DISTINCT EmployeeID) as employees_with_data,
            COUNT(DISTINCT DeviceID) as active_devices
        FROM tbl_biometric_data 
        WHERE DataSource = 'sample_data'
    ")->fetch(PDO::FETCH_ASSOC);
    
    $alerts = $conn->query("SELECT COUNT(*) FROM tbl_biometric_alerts")->fetchColumn();
    $reports = $conn->query("SELECT COUNT(*) FROM tbl_wellness_reports")->fetchColumn();
    
    echo "- {$stats['total_readings']} biometric readings\n";
    echo "- {$stats['employees_with_data']} employees with wearables\n";
    echo "- {$stats['active_devices']} active devices\n";
    echo "- $alerts alerts generated\n";
    echo "- $reports wellness reports created\n";
    echo "\n🎯 Ready to test the wellness dashboard!\n";
    
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
