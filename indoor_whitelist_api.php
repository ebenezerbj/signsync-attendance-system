<?php
// Indoor whitelist management API
session_start();
include 'db.php';
header('Content-Type: application/json');

// Ensure user is logged in and is an admin/hr
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // List beacons and wifi for a branch
    $branchId = $_GET['branch_id'] ?? '';
    if (!$branchId) {
        http_response_code(400);
        echo json_encode(['error' => 'Branch ID required']);
        exit;
    }

    try {
        // Get beacons
        $beacons = $conn->prepare("SELECT BeaconID, MAC, Label FROM tbl_branch_beacons WHERE BranchID = ? ORDER BY MAC");
        $beacons->execute([$branchId]);
        $beaconsData = $beacons->fetchAll(PDO::FETCH_ASSOC);

        // Get wifi APs
        $wifi = $conn->prepare("SELECT WifiID, BSSID, SSID FROM tbl_branch_wifi WHERE BranchID = ? ORDER BY BSSID");
        $wifi->execute([$branchId]);
        $wifiData = $wifi->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'beacons' => $beaconsData,
            'wifi' => $wifiData
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }

} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_beacon':
                $branchId = $_POST['branch_id'] ?? '';
                $mac = strtoupper(trim($_POST['mac'] ?? ''));
                $label = trim($_POST['label'] ?? '');
                
                if (!$branchId || !$mac) {
                    throw new Exception('Branch ID and MAC are required');
                }
                
                // Validate MAC format (either standard MAC or UUID)
                if (!preg_match('/^[A-F0-9]{2}(:[A-F0-9]{2}){5}$/', $mac) && 
                    !preg_match('/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}$/', $mac)) {
                    throw new Exception('Invalid MAC address or UUID format');
                }
                
                $stmt = $conn->prepare("INSERT INTO tbl_branch_beacons (BranchID, MAC, Label) VALUES (?, ?, ?)");
                $stmt->execute([$branchId, $mac, $label ?: null]);
                
                echo json_encode(['success' => true, 'message' => 'Beacon added successfully']);
                break;
                
            case 'add_wifi':
                $branchId = $_POST['branch_id'] ?? '';
                $bssid = strtoupper(trim($_POST['bssid'] ?? ''));
                $ssid = trim($_POST['ssid'] ?? '');
                
                if (!$branchId || !$bssid) {
                    throw new Exception('Branch ID and BSSID are required');
                }
                
                // Validate BSSID format (MAC address)
                if (!preg_match('/^[A-F0-9]{2}(:[A-F0-9]{2}){5}$/', $bssid)) {
                    throw new Exception('Invalid BSSID format');
                }
                
                $stmt = $conn->prepare("INSERT INTO tbl_branch_wifi (BranchID, BSSID, SSID) VALUES (?, ?, ?)");
                $stmt->execute([$branchId, $bssid, $ssid ?: null]);
                
                echo json_encode(['success' => true, 'message' => 'Wi-Fi AP added successfully']);
                break;
                
            case 'remove_beacon':
                $beaconId = $_POST['beacon_id'] ?? '';
                if (!$beaconId) {
                    throw new Exception('Beacon ID required');
                }
                
                $stmt = $conn->prepare("DELETE FROM tbl_branch_beacons WHERE BeaconID = ?");
                $stmt->execute([$beaconId]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Beacon not found');
                }
                
                echo json_encode(['success' => true, 'message' => 'Beacon removed successfully']);
                break;
                
            case 'remove_wifi':
                $wifiId = $_POST['wifi_id'] ?? '';
                if (!$wifiId) {
                    throw new Exception('Wi-Fi ID required');
                }
                
                $stmt = $conn->prepare("DELETE FROM tbl_branch_wifi WHERE WifiID = ?");
                $stmt->execute([$wifiId]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Wi-Fi AP not found');
                }
                
                echo json_encode(['success' => true, 'message' => 'Wi-Fi AP removed successfully']);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
