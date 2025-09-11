<?php
/**
 * Biometric Data API for IoT Wearables
 * Handles stress and fatigue monitoring data from smartwatches and fitness trackers
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-ID, X-Employee-ID');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db.php';

// Function to calculate stress level from biometric data
function calculateStressLevel($heartRate, $hrv, $skinTemp = null) {
    $stressScore = 0;
    
    // Heart rate contribution (40% weight)
    if ($heartRate > 120) $stressScore += 4;
    elseif ($heartRate > 100) $stressScore += 3;
    elseif ($heartRate > 80) $stressScore += 2;
    else $stressScore += 1;
    
    // HRV contribution (40% weight) - lower HRV indicates higher stress
    if ($hrv < 20) $stressScore += 4;
    elseif ($hrv < 30) $stressScore += 3;
    elseif ($hrv < 40) $stressScore += 2;
    else $stressScore += 1;
    
    // Skin temperature contribution (20% weight)
    if ($skinTemp !== null) {
        if ($skinTemp > 37.5) $stressScore += 2;
        elseif ($skinTemp > 37.0) $stressScore += 1;
    }
    
    $avgScore = $stressScore / (($skinTemp !== null) ? 3 : 2);
    
    if ($avgScore >= 3.5) return 'critical';
    elseif ($avgScore >= 2.5) return 'high';
    elseif ($avgScore >= 1.5) return 'moderate';
    else return 'low';
}

// Function to calculate fatigue level
function calculateFatigueLevel($heartRate, $stepCount, $sleepQuality, $activityLevel) {
    $fatigueScore = 0;
    
    // Resting heart rate indicator
    if ($heartRate > 80) $fatigueScore += 3;
    elseif ($heartRate > 70) $fatigueScore += 2;
    elseif ($heartRate > 60) $fatigueScore += 1;
    
    // Activity level
    switch($activityLevel) {
        case 'sedentary': $fatigueScore += 3; break;
        case 'light': $fatigueScore += 2; break;
        case 'moderate': $fatigueScore += 1; break;
        case 'vigorous': $fatigueScore += 0; break;
    }
    
    // Sleep quality
    switch($sleepQuality) {
        case 'poor': $fatigueScore += 4; break;
        case 'fair': $fatigueScore += 2; break;
        case 'good': $fatigueScore += 1; break;
        case 'excellent': $fatigueScore += 0; break;
    }
    
    // Step count (daily context)
    if ($stepCount < 2000) $fatigueScore += 2;
    elseif ($stepCount < 5000) $fatigueScore += 1;
    
    $avgScore = $fatigueScore / 4;
    
    if ($avgScore >= 3) return 'severe';
    elseif ($avgScore >= 2) return 'moderate';
    elseif ($avgScore >= 1) return 'mild';
    else return 'rested';
}

// Function to check thresholds and create alerts
function checkBiometricAlerts($conn, $employeeId, $biometricData) {
    $alerts = [];
    
    // Get employee-specific or global thresholds
    $thresholds = $conn->prepare("
        SELECT MetricType, LowThreshold, MediumThreshold, HighThreshold, CriticalThreshold 
        FROM tbl_biometric_thresholds 
        WHERE (EmployeeID = ? OR EmployeeID IS NULL) AND IsActive = 1
        ORDER BY EmployeeID DESC
    ");
    $thresholds->execute([$employeeId]);
    $thresholdRows = $thresholds->fetchAll(PDO::FETCH_ASSOC);
    
    $thresholdData = [];
    foreach($thresholdRows as $row) {
        $thresholdData[$row['MetricType']] = $row;
    }
    
    // Check heart rate
    if ($biometricData['HeartRate'] && isset($thresholdData['heart_rate'])) {
        $hr = $biometricData['HeartRate'];
        $threshold = $thresholdData['heart_rate'];
        
        if ($hr >= $threshold['CriticalThreshold']) {
            $alerts[] = ['type' => 'health', 'severity' => 'critical', 'message' => "Critical heart rate detected: {$hr} bpm"];
        } elseif ($hr >= $threshold['HighThreshold']) {
            $alerts[] = ['type' => 'health', 'severity' => 'high', 'message' => "Elevated heart rate: {$hr} bpm"];
        }
    }
    
    // Check stress level
    if ($biometricData['StressLevel'] && in_array($biometricData['StressLevel'], ['high', 'critical'])) {
        $severity = $biometricData['StressLevel'] === 'critical' ? 'critical' : 'high';
        $alerts[] = ['type' => 'stress', 'severity' => $severity, 'message' => "High stress level detected"];
    }
    
    // Check fatigue level
    if ($biometricData['FatigueLevel'] && in_array($biometricData['FatigueLevel'], ['moderate', 'severe'])) {
        $severity = $biometricData['FatigueLevel'] === 'severe' ? 'high' : 'medium';
        $alerts[] = ['type' => 'fatigue', 'severity' => $severity, 'message' => "High fatigue level detected"];
    }
    
    return $alerts;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch($method) {
        case 'POST':
            $action = $input['action'] ?? 'add_biometric';
            
            if ($action === 'acknowledge_alert') {
                $alertId = $input['alert_id'] ?? null;
                if (!$alertId) {
                    throw new Exception("Alert ID required");
                }
                
                $acknowledgeStmt = $conn->prepare("
                    UPDATE tbl_biometric_alerts 
                    SET IsAcknowledged = 1, AcknowledgedAt = NOW()
                    WHERE AlertID = ?
                ");
                $acknowledgeStmt->execute([$alertId]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Alert acknowledged successfully'
                ]);
                break;
            }
            
            // Original biometric data insertion code
            // Receive biometric data from wearable device
            $requiredFields = ['employee_id', 'device_id'];
            foreach($requiredFields as $field) {
                if (!isset($input[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            $employeeId = $input['employee_id'];
            $deviceId = $input['device_id'];
            
            // Verify device assignment
            $deviceCheck = $conn->prepare("
                SELECT COUNT(*) FROM tbl_employee_wearables 
                WHERE EmployeeID = ? AND DeviceID = ? AND IsActive = 1
            ");
            $deviceCheck->execute([$employeeId, $deviceId]);
            
            if ($deviceCheck->fetchColumn() == 0) {
                throw new Exception("Device not assigned to employee or inactive");
            }
            
            // Extract biometric data
            $heartRate = $input['heart_rate'] ?? null;
            $hrv = $input['heart_rate_variability'] ?? null;
            $skinTemp = $input['skin_temperature'] ?? null;
            $bloodOxygen = $input['blood_oxygen'] ?? null;
            $stepCount = $input['step_count'] ?? null;
            $sleepQuality = $input['sleep_quality'] ?? null;
            $activityLevel = $input['activity_level'] ?? null;
            $rawData = $input['raw_data'] ?? null;
            
            // Calculate stress and fatigue levels
            $stressLevel = null;
            $fatigueLevel = null;
            
            if ($heartRate && $hrv) {
                $stressLevel = calculateStressLevel($heartRate, $hrv, $skinTemp);
            }
            
            if ($heartRate && $stepCount && $sleepQuality && $activityLevel) {
                $fatigueLevel = calculateFatigueLevel($heartRate, $stepCount, $sleepQuality, $activityLevel);
            }
            
            // Insert biometric data
            $insertBiometric = $conn->prepare("
                INSERT INTO tbl_biometric_data 
                (EmployeeID, DeviceID, HeartRate, HeartRateVariability, StressLevel, FatigueLevel, 
                 SkinTemperature, BloodOxygen, StepCount, SleepQuality, ActivityLevel, RawData)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insertBiometric->execute([
                $employeeId, $deviceId, $heartRate, $hrv, $stressLevel, $fatigueLevel,
                $skinTemp, $bloodOxygen, $stepCount, $sleepQuality, $activityLevel,
                $rawData ? json_encode($rawData) : null
            ]);
            
            $biometricId = $conn->lastInsertId();
            
            // Check for alerts
            $biometricData = [
                'HeartRate' => $heartRate,
                'StressLevel' => $stressLevel,
                'FatigueLevel' => $fatigueLevel,
                'SkinTemperature' => $skinTemp,
                'BloodOxygen' => $bloodOxygen
            ];
            
            $alerts = checkBiometricAlerts($conn, $employeeId, $biometricData);
            
            // Insert alerts
            foreach($alerts as $alert) {
                $insertAlert = $conn->prepare("
                    INSERT INTO tbl_biometric_alerts 
                    (EmployeeID, AlertType, Severity, AlertMessage, BiometricData)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertAlert->execute([
                    $employeeId, $alert['type'], $alert['severity'], 
                    $alert['message'], json_encode($biometricData)
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'biometric_id' => $biometricId,
                'stress_level' => $stressLevel,
                'fatigue_level' => $fatigueLevel,
                'alerts_generated' => count($alerts),
                'message' => 'Biometric data recorded successfully'
            ]);
            break;
            
        case 'GET':
            // Get biometric data for an employee
            $action = $_GET['action'] ?? 'employee_data';
            
            if ($action === 'stress_distribution') {
                // Get stress/fatigue distribution for dashboard
                $date = $_GET['date'] ?? date('Y-m-d');
                
                $stressQuery = "
                    SELECT 
                        StressLevel,
                        COUNT(*) as count
                    FROM tbl_biometric_data 
                    WHERE DATE(Timestamp) = ? AND StressLevel IS NOT NULL
                    GROUP BY StressLevel
                ";
                $stmt = $conn->prepare($stressQuery);
                $stmt->execute([$date]);
                $stressData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $fatigueQuery = "
                    SELECT 
                        FatigueLevel,
                        COUNT(*) as count
                    FROM tbl_biometric_data 
                    WHERE DATE(Timestamp) = ? AND FatigueLevel IS NOT NULL
                    GROUP BY FatigueLevel
                ";
                $stmt = $conn->prepare($fatigueQuery);
                $stmt->execute([$date]);
                $fatigueData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                // Calculate wellness score
                $wellnessQuery = "
                    SELECT AVG(
                        CASE 
                            WHEN StressLevel = 'low' AND FatigueLevel = 'rested' THEN 100
                            WHEN StressLevel = 'moderate' OR FatigueLevel = 'mild' THEN 75
                            WHEN StressLevel = 'high' OR FatigueLevel = 'moderate' THEN 50
                            WHEN StressLevel = 'critical' OR FatigueLevel = 'severe' THEN 25
                            ELSE 60
                        END
                    ) as wellness_score
                    FROM tbl_biometric_data 
                    WHERE DATE(Timestamp) = ?
                ";
                $stmt = $conn->prepare($wellnessQuery);
                $stmt->execute([$date]);
                $wellnessScore = round($stmt->fetchColumn());
                
                echo json_encode([
                    'success' => true,
                    'stress_data' => $stressData,
                    'fatigue_data' => $fatigueData,
                    'wellness_score' => $wellnessScore
                ]);
                break;
            }
            
            $employeeId = $_GET['employee_id'] ?? null;
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $limit = min((int)($_GET['limit'] ?? 100), 1000);
            
            if (!$employeeId) {
                throw new Exception("Employee ID required");
            }
            
            $query = "
                SELECT bd.*, d.DeviceName, d.DeviceType
                FROM tbl_biometric_data bd
                JOIN tbl_devices d ON bd.DeviceID = d.DeviceID
                WHERE bd.EmployeeID = ?
                AND DATE(bd.Timestamp) BETWEEN ? AND ?
                ORDER BY bd.Timestamp DESC
                LIMIT ?
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$employeeId, $startDate, $endDate, $limit]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get alerts for the same period
            $alertQuery = "
                SELECT * FROM tbl_biometric_alerts
                WHERE EmployeeID = ?
                AND DATE(CreatedAt) BETWEEN ? AND ?
                ORDER BY CreatedAt DESC
                LIMIT 50
            ";
            
            $alertStmt = $conn->prepare($alertQuery);
            $alertStmt->execute([$employeeId, $startDate, $endDate]);
            $alerts = $alertStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'alerts' => $alerts,
                'period' => ['start' => $startDate, 'end' => $endDate]
            ]);
            break;
            
        case 'PUT':
            // Assign wearable device to employee
            $employeeId = $input['employee_id'] ?? null;
            $deviceId = $input['device_id'] ?? null;
            
            if (!$employeeId || !$deviceId) {
                throw new Exception("Employee ID and Device ID required");
            }
            
            // Deactivate any existing assignments for this employee
            $deactivate = $conn->prepare("
                UPDATE tbl_employee_wearables 
                SET IsActive = 0 
                WHERE EmployeeID = ?
            ");
            $deactivate->execute([$employeeId]);
            
            // Create new assignment
            $assign = $conn->prepare("
                INSERT INTO tbl_employee_wearables (EmployeeID, DeviceID, IsActive)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE IsActive = 1, AssignedDate = CURRENT_TIMESTAMP
            ");
            $assign->execute([$employeeId, $deviceId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Wearable device assigned successfully'
            ]);
            break;
            
        default:
            throw new Exception("Method not allowed");
    }
    
} catch(Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
