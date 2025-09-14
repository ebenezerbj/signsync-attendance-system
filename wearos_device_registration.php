<?php
require_once 'db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function generateDeviceId() {
    return 'WOS_' . strtoupper(bin2hex(random_bytes(8)));
}

function generateRegistrationCode() {
    return strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6));
}

try {
    $host = 'localhost';
    $dbname = 'attendance_register_db';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create device registration table if not exists
    $createTable = "
    CREATE TABLE IF NOT EXISTS tbl_wearos_devices (
        DeviceID VARCHAR(20) PRIMARY KEY,
        DeviceName VARCHAR(100) NOT NULL,
        DeviceModel VARCHAR(50),
        AndroidVersion VARCHAR(20),
        WearOSVersion VARCHAR(20),
        MACAddress VARCHAR(17),
        SerialNumber VARCHAR(50),
        RegistrationCode VARCHAR(6) UNIQUE,
        EmployeeID VARCHAR(20) NULL,
        RegisteredAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        LastSeen TIMESTAMP NULL,
        IsActive BOOLEAN DEFAULT TRUE,
        BatteryLevel INT DEFAULT NULL,
        HealthSensorsEnabled BOOLEAN DEFAULT TRUE,
        LocationEnabled BOOLEAN DEFAULT TRUE,
        CreatedBy VARCHAR(20) DEFAULT 'SYSTEM',
        UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (EmployeeID) REFERENCES tbl_employees(EmployeeID) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($createTable);
    
    // Handle JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'register_device':
            // Register a new Android Wear device
            $deviceName = $input['device_name'] ?? 'Unknown Wear Device';
            $deviceModel = $input['device_model'] ?? 'Android Wear';
            $androidVersion = $input['android_version'] ?? '';
            $wearOSVersion = $input['wearos_version'] ?? '';
            $macAddress = $input['mac_address'] ?? '';
            $serialNumber = $input['serial_number'] ?? '';
            
            $deviceId = generateDeviceId();
            $registrationCode = generateRegistrationCode();
            
            // Check if device already exists by MAC or Serial
            $existsQuery = "SELECT DeviceID FROM tbl_wearos_devices WHERE MACAddress = :mac OR SerialNumber = :serial";
            $existsStmt = $pdo->prepare($existsQuery);
            $existsStmt->execute([':mac' => $macAddress, ':serial' => $serialNumber]);
            
            if ($existsStmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Device already registered',
                    'error_code' => 'DEVICE_EXISTS'
                ]);
                break;
            }
            
            $insertQuery = "
                INSERT INTO tbl_wearos_devices 
                (DeviceID, DeviceName, DeviceModel, AndroidVersion, WearOSVersion, MACAddress, SerialNumber, RegistrationCode)
                VALUES (:device_id, :device_name, :device_model, :android_version, :wearos_version, :mac_address, :serial_number, :registration_code)
            ";
            
            $stmt = $pdo->prepare($insertQuery);
            $result = $stmt->execute([
                ':device_id' => $deviceId,
                ':device_name' => $deviceName,
                ':device_model' => $deviceModel,
                ':android_version' => $androidVersion,
                ':wearos_version' => $wearOSVersion,
                ':mac_address' => $macAddress,
                ':serial_number' => $serialNumber,
                ':registration_code' => $registrationCode
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Device registered successfully',
                    'data' => [
                        'device_id' => $deviceId,
                        'registration_code' => $registrationCode,
                        'device_name' => $deviceName,
                        'status' => 'registered_pending_binding'
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to register device'
                ]);
            }
            break;
            
        case 'bind_employee':
            // Bind device to employee using registration code
            $registrationCode = $input['registration_code'] ?? '';
            $employeeId = $input['employee_id'] ?? '';
            $adminUser = $input['admin_user'] ?? 'ADMIN';
            
            if (empty($registrationCode) || empty($employeeId)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Registration code and Employee ID are required'
                ]);
                break;
            }
            
            // Verify employee exists
            $empCheck = "SELECT EmployeeID, FullName FROM tbl_employees WHERE EmployeeID = :emp_id";
            $empStmt = $pdo->prepare($empCheck);
            $empStmt->execute([':emp_id' => $employeeId]);
            $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Employee not found'
                ]);
                break;
            }
            
            // Update device with employee binding
            $bindQuery = "
                UPDATE tbl_wearos_devices 
                SET EmployeeID = :employee_id, CreatedBy = :admin_user, UpdatedAt = CURRENT_TIMESTAMP
                WHERE RegistrationCode = :registration_code AND EmployeeID IS NULL
            ";
            
            $bindStmt = $pdo->prepare($bindQuery);
            $result = $bindStmt->execute([
                ':employee_id' => $employeeId,
                ':admin_user' => $adminUser,
                ':registration_code' => $registrationCode
            ]);
            
            if ($result && $bindStmt->rowCount() > 0) {
                // Get device info
                $deviceQuery = "SELECT DeviceID, DeviceName FROM tbl_wearos_devices WHERE RegistrationCode = :code";
                $deviceStmt = $pdo->prepare($deviceQuery);
                $deviceStmt->execute([':code' => $registrationCode]);
                $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Device successfully bound to employee',
                    'data' => [
                        'device_id' => $device['DeviceID'],
                        'device_name' => $device['DeviceName'],
                        'employee_id' => $employeeId,
                        'employee_name' => $employee['FullName'],
                        'status' => 'active'
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid registration code or device already bound'
                ]);
            }
            break;
            
        case 'get_device_status':
            // Get device registration status
            $deviceId = $input['device_id'] ?? $_GET['device_id'] ?? '';
            $registrationCode = $input['registration_code'] ?? $_GET['registration_code'] ?? '';
            
            $query = "
                SELECT * FROM tbl_wearos_devices 
                WHERE DeviceID = :device_id OR RegistrationCode = :registration_code
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([':device_id' => $deviceId, ':registration_code' => $registrationCode]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($device) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'device_id' => $device['DeviceID'],
                        'device_name' => $device['DeviceName'],
                        'registration_code' => $device['RegistrationCode'],
                        'employee_id' => $device['EmployeeID'],
                        'status' => $device['Status'],
                        'is_bound' => !empty($device['EmployeeID']),
                        'is_active' => (bool)$device['IsActive'],
                        'last_seen' => $device['LastSeen'],
                        'battery_level' => $device['BatteryLevel'],
                        'registered_at' => $device['RegisteredAt']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Device not found'
                ]);
            }
            break;
            
        case 'list_devices':
            // List all registered devices
            $query = "
                SELECT * FROM tbl_wearos_devices 
                ORDER BY RegisteredAt DESC
            ";
            
            $stmt = $pdo->query($query);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $devices,
                'count' => count($devices)
            ]);
            break;
            
        case 'update_device_status':
            // Update device last seen and battery level
            $deviceId = $input['device_id'] ?? '';
            $batteryLevel = $input['battery_level'] ?? null;
            $healthSensors = $input['health_sensors_enabled'] ?? true;
            $locationEnabled = $input['location_enabled'] ?? true;
            
            $updateQuery = "
                UPDATE tbl_wearos_devices 
                SET LastSeen = CURRENT_TIMESTAMP, 
                    BatteryLevel = :battery_level,
                    HealthSensorsEnabled = :health_sensors,
                    LocationEnabled = :location_enabled,
                    UpdatedAt = CURRENT_TIMESTAMP
                WHERE DeviceID = :device_id
            ";
            
            $stmt = $pdo->prepare($updateQuery);
            $result = $stmt->execute([
                ':device_id' => $deviceId,
                ':battery_level' => $batteryLevel,
                ':health_sensors' => $healthSensors ? 1 : 0,
                ':location_enabled' => $locationEnabled ? 1 : 0
            ]);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Device status updated' : 'Failed to update device status'
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
