<?php
/**
 * Enhanced Location Verification Manager
 * Provides advanced location verification features including:
 * - Multi-branch support with custom boundaries
 * - Configurable verification parameters
 * - GPS accuracy scoring
 * - Location history tracking
 * - Workplace boundary management
 */

class LocationVerificationManager {
    private $conn;
    private $config;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->loadConfiguration();
        $this->ensureLocationTablesExist();
    }
    
    private function loadConfiguration() {
        $this->config = [
            // Default verification settings
            'default_workplace_radius' => 200, // meters
            'min_gps_accuracy' => 50, // meters
            'location_verification_timeout' => 30, // seconds
            'require_location_for_clockin' => true,
            'allow_manual_override' => false,
            
            // Scoring parameters
            'accuracy_weight' => 0.4,
            'distance_weight' => 0.4,
            'time_consistency_weight' => 0.2,
            
            // Alert thresholds
            'low_accuracy_threshold' => 30, // meters
            'distance_alert_threshold' => 300, // meters
            'suspicious_movement_threshold' => 1000, // meters in short time
            
            // Multi-branch settings
            'enable_multi_branch' => true,
            'auto_detect_branch' => true,
            'branch_overlap_tolerance' => 50 // meters
        ];
        
        // Load custom configuration from database if exists
        try {
            $stmt = $this->conn->query("SELECT config_key, config_value FROM location_verification_config WHERE is_active = 1");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->config[$row['config_key']] = json_decode($row['config_value'], true) ?? $row['config_value'];
            }
        } catch (Exception $e) {
            // Configuration table doesn't exist yet, use defaults
        }
    }
    
    private function ensureLocationTablesExist() {
        // Create location verification config table
        $sql = "CREATE TABLE IF NOT EXISTS location_verification_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(100) NOT NULL UNIQUE,
            config_value TEXT NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->conn->exec($sql);
        
        // Create workplace boundaries table
        $sql = "CREATE TABLE IF NOT EXISTS workplace_boundaries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            branch_id VARCHAR(50) NOT NULL,
            boundary_name VARCHAR(100) NOT NULL,
            center_latitude DECIMAL(10, 8) NOT NULL,
            center_longitude DECIMAL(11, 8) NOT NULL,
            radius_meters INT NOT NULL DEFAULT 200,
            boundary_type ENUM('circular', 'polygon') DEFAULT 'circular',
            polygon_points JSON NULL,
            is_active BOOLEAN DEFAULT 1,
            work_hours_start TIME DEFAULT '08:00:00',
            work_hours_end TIME DEFAULT '17:00:00',
            timezone VARCHAR(50) DEFAULT 'Asia/Manila',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_branch_active (branch_id, is_active)
        )";
        $this->conn->exec($sql);
        
        // Create location verification history table
        $sql = "CREATE TABLE IF NOT EXISTS location_verification_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(50) NOT NULL,
            verification_type ENUM('clock_in', 'clock_out', 'manual_check') NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            accuracy_meters FLOAT,
            detected_branch_id VARCHAR(50),
            assigned_branch_id VARCHAR(50),
            distance_from_workplace FLOAT,
            is_at_workplace BOOLEAN,
            verification_score FLOAT,
            verification_details JSON,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_employee_timestamp (employee_id, timestamp),
            INDEX idx_branch_timestamp (detected_branch_id, timestamp)
        )";
        $this->conn->exec($sql);
    }
    
    /**
     * Enhanced location verification with multi-branch support
     */
    public function verifyLocation(array $locationData, string $employeeBranchId = null): array {
        $result = [
            'success' => false,
            'at_workplace' => false,
            'verification_score' => 0,
            'distance_from_workplace' => null,
            'detected_branch' => null,
            'accuracy_status' => 'unknown',
            'alerts' => [],
            'details' => []
        ];
        
        // Validate input
        if (!isset($locationData['latitude'], $locationData['longitude'])) {
            $result['alerts'][] = 'Invalid location data provided';
            return $result;
        }
        
        $lat = floatval($locationData['latitude']);
        $lng = floatval($locationData['longitude']);
        $accuracy = floatval($locationData['accuracy'] ?? 999);
        
        // Check GPS accuracy
        $result['accuracy_status'] = $this->getAccuracyStatus($accuracy);
        if ($accuracy > $this->config['min_gps_accuracy']) {
            $result['alerts'][] = "GPS accuracy too low: {$accuracy}m (required: {$this->config['min_gps_accuracy']}m)";
        }
        
        // Get workplace boundaries
        $workplaces = $this->getWorkplaceBoundaries($employeeBranchId);
        
        $closestWorkplace = null;
        $minDistance = PHP_FLOAT_MAX;
        
        foreach ($workplaces as $workplace) {
            $distance = $this->calculateDistance(
                $lat, $lng,
                $workplace['center_latitude'], $workplace['center_longitude']
            );
            
            if ($distance < $minDistance) {
                $minDistance = $distance;
                $closestWorkplace = $workplace;
            }
            
            // Check if within workplace boundary
            if ($workplace['boundary_type'] === 'circular') {
                if ($distance <= $workplace['radius_meters']) {
                    $result['at_workplace'] = true;
                    $result['detected_branch'] = $workplace['branch_id'];
                    break;
                }
            } elseif ($workplace['boundary_type'] === 'polygon') {
                if ($this->isPointInPolygon($lat, $lng, $workplace['polygon_points'])) {
                    $result['at_workplace'] = true;
                    $result['detected_branch'] = $workplace['branch_id'];
                    break;
                }
            }
        }
        
        $result['distance_from_workplace'] = $minDistance;
        
        // Calculate verification score
        $result['verification_score'] = $this->calculateVerificationScore([
            'accuracy' => $accuracy,
            'distance' => $minDistance,
            'at_workplace' => $result['at_workplace'],
            'employee_id' => $locationData['employee_id'] ?? null
        ]);
        
        // Add distance alerts
        if ($minDistance > $this->config['distance_alert_threshold']) {
            $result['alerts'][] = "Employee is {$minDistance}m from nearest workplace";
        }
        
        // Check for branch mismatch
        if ($employeeBranchId && $result['detected_branch'] && $result['detected_branch'] !== $employeeBranchId) {
            $result['alerts'][] = "Employee at different branch: detected {$result['detected_branch']}, assigned to {$employeeBranchId}";
        }
        
        // Store verification history
        $this->storeVerificationHistory($locationData, $result);
        
        $result['success'] = true;
        $result['details'] = [
            'closest_workplace' => $closestWorkplace['boundary_name'] ?? 'Unknown',
            'verification_time' => date('Y-m-d H:i:s'),
            'config_used' => [
                'min_accuracy' => $this->config['min_gps_accuracy'],
                'workplace_radius' => $closestWorkplace['radius_meters'] ?? $this->config['default_workplace_radius']
            ]
        ];
        
        return $result;
    }
    
    private function getWorkplaceBoundaries(string $branchId = null): array {
        $sql = "SELECT * FROM workplace_boundaries WHERE is_active = 1";
        $params = [];
        
        if ($branchId) {
            $sql .= " AND branch_id = ?";
            $params[] = $branchId;
        }
        
        $sql .= " ORDER BY radius_meters ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $workplaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no specific workplaces found, create default from branches table
        if (empty($workplaces) && $branchId) {
            $workplaces = $this->createDefaultWorkplaceBoundary($branchId);
        }
        
        return $workplaces;
    }
    
    private function createDefaultWorkplaceBoundary(string $branchId): array {
        // Try to get branch location from tbl_branches
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tbl_branches WHERE BranchID = ?");
            $stmt->execute([$branchId]);
            $branch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($branch && isset($branch['Latitude'], $branch['Longitude'])) {
                return [[
                    'branch_id' => $branchId,
                    'boundary_name' => $branch['BranchName'] ?? "Branch {$branchId}",
                    'center_latitude' => $branch['Latitude'],
                    'center_longitude' => $branch['Longitude'],
                    'radius_meters' => $this->config['default_workplace_radius'],
                    'boundary_type' => 'circular'
                ]];
            }
        } catch (Exception $e) {
            // Branch table might not exist or have location data
        }
        
        // Default fallback location (you should configure this for your organization)
        return [[
            'branch_id' => $branchId,
            'boundary_name' => "Default Office",
            'center_latitude' => 14.5995,  // Manila coordinates as example
            'center_longitude' => 120.9842,
            'radius_meters' => $this->config['default_workplace_radius'],
            'boundary_type' => 'circular'
        ]];
    }
    
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earthRadius = 6371000; // Earth radius in meters
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    private function isPointInPolygon(float $lat, float $lng, array $polygonPoints): bool {
        // Ray casting algorithm for point-in-polygon test
        $inside = false;
        $count = count($polygonPoints);
        
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $polygonPoints[$i]['lat'];
            $yi = $polygonPoints[$i]['lng'];
            $xj = $polygonPoints[$j]['lat'];
            $yj = $polygonPoints[$j]['lng'];
            
            if ((($yi > $lng) != ($yj > $lng)) &&
                ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }
        
        return $inside;
    }
    
    private function calculateVerificationScore(array $data): float {
        $score = 0;
        
        // Accuracy score (0-100)
        $accuracyScore = max(0, 100 - ($data['accuracy'] * 2));
        $score += $accuracyScore * $this->config['accuracy_weight'];
        
        // Distance score (0-100)
        $maxDistance = $this->config['default_workplace_radius'] * 2;
        $distanceScore = max(0, 100 - (($data['distance'] / $maxDistance) * 100));
        $score += $distanceScore * $this->config['distance_weight'];
        
        // Time consistency score (0-100)
        $timeScore = $this->calculateTimeConsistencyScore($data['employee_id']);
        $score += $timeScore * $this->config['time_consistency_weight'];
        
        // Bonus for being at workplace
        if ($data['at_workplace']) {
            $score += 10;
        }
        
        return min(100, max(0, $score));
    }
    
    private function calculateTimeConsistencyScore(string $employeeId = null): float {
        if (!$employeeId) return 50; // Neutral score
        
        try {
            // Check location consistency over past few clock-ins
            $sql = "SELECT latitude, longitude, timestamp 
                    FROM location_verification_history 
                    WHERE employee_id = ? AND verification_type = 'clock_in'
                    ORDER BY timestamp DESC LIMIT 5";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$employeeId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($history) < 2) return 50; // Not enough data
            
            $avgVariance = 0;
            $count = 0;
            
            for ($i = 0; $i < count($history) - 1; $i++) {
                $distance = $this->calculateDistance(
                    $history[$i]['latitude'], $history[$i]['longitude'],
                    $history[$i+1]['latitude'], $history[$i+1]['longitude']
                );
                $avgVariance += $distance;
                $count++;
            }
            
            $avgVariance = $count > 0 ? $avgVariance / $count : 0;
            
            // Lower variance = higher consistency score
            return max(0, 100 - ($avgVariance / 10));
            
        } catch (Exception $e) {
            return 50; // Neutral score on error
        }
    }
    
    private function getAccuracyStatus(float $accuracy): string {
        if ($accuracy <= 5) return 'excellent';
        if ($accuracy <= 15) return 'good';
        if ($accuracy <= 30) return 'fair';
        if ($accuracy <= 50) return 'poor';
        return 'very_poor';
    }
    
    private function storeVerificationHistory(array $locationData, array $result): void {
        try {
            $sql = "INSERT INTO location_verification_history 
                    (employee_id, verification_type, latitude, longitude, accuracy_meters,
                     detected_branch_id, distance_from_workplace, is_at_workplace,
                     verification_score, verification_details)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $locationData['employee_id'] ?? 'unknown',
                $locationData['verification_type'] ?? 'clock_in',
                $locationData['latitude'],
                $locationData['longitude'],
                $locationData['accuracy'] ?? null,
                $result['detected_branch'],
                $result['distance_from_workplace'],
                $result['at_workplace'] ? 1 : 0,
                $result['verification_score'],
                json_encode($result['details'])
            ]);
        } catch (Exception $e) {
            error_log("Failed to store location verification history: " . $e->getMessage());
        }
    }
    
    /**
     * Administrative functions
     */
    
    public function addWorkplaceBoundary(array $boundaryData): bool {
        try {
            $sql = "INSERT INTO workplace_boundaries 
                    (branch_id, boundary_name, center_latitude, center_longitude,
                     radius_meters, boundary_type, polygon_points, work_hours_start,
                     work_hours_end, timezone)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                $boundaryData['branch_id'],
                $boundaryData['boundary_name'],
                $boundaryData['center_latitude'],
                $boundaryData['center_longitude'],
                $boundaryData['radius_meters'] ?? $this->config['default_workplace_radius'],
                $boundaryData['boundary_type'] ?? 'circular',
                isset($boundaryData['polygon_points']) ? json_encode($boundaryData['polygon_points']) : null,
                $boundaryData['work_hours_start'] ?? '08:00:00',
                $boundaryData['work_hours_end'] ?? '17:00:00',
                $boundaryData['timezone'] ?? 'Asia/Manila'
            ]);
        } catch (Exception $e) {
            error_log("Failed to add workplace boundary: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateConfiguration(string $key, $value): bool {
        try {
            $sql = "INSERT INTO location_verification_config (config_key, config_value) 
                    VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?";
            
            $valueJson = is_array($value) ? json_encode($value) : $value;
            
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$key, $valueJson, $valueJson]);
        } catch (Exception $e) {
            error_log("Failed to update location configuration: " . $e->getMessage());
            return false;
        }
    }
    
    public function getLocationAnalytics(string $employeeId = null, int $days = 7): array {
        try {
            $sql = "SELECT 
                        employee_id,
                        COUNT(*) as total_verifications,
                        AVG(verification_score) as avg_score,
                        AVG(distance_from_workplace) as avg_distance,
                        SUM(CASE WHEN is_at_workplace = 1 THEN 1 ELSE 0 END) as at_workplace_count,
                        MIN(verification_score) as min_score,
                        MAX(verification_score) as max_score
                    FROM location_verification_history 
                    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $params = [$days];
            
            if ($employeeId) {
                $sql .= " AND employee_id = ?";
                $params[] = $employeeId;
            }
            
            $sql .= " GROUP BY employee_id ORDER BY avg_score DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get location analytics: " . $e->getMessage());
            return [];
        }
    }
}
?>
