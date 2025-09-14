<?php

// --- SETUP AND DATABASE CONNECTION ---
// Assuming 'db.php' contains your PDO database connection setup, e.g.:
// $dsn = 'mysql:host=localhost;dbname=your_database;charset=utf8mb4';
// $username = 'your_username';
// $password = 'your_password';
// try {
//     $conn = new PDO($dsn, $username, $password);
//     $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// } catch (PDOException $e) {
//     http_response_code(500);
//     exit(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
// }
include 'db.php';
require_once 'SignSyncSMSService.php';
require_once 'sms_config.php';
date_default_timezone_set('UTC'); // Set a consistent timezone, e.g., UTC

// Initialize SMS service
try {
    $smsService = createSMSService($conn);
} catch (Exception $e) {
    error_log("SMS Service initialization failed: " . $e->getMessage());
    $smsService = null;
}

// Set content type for JSON response
header('Content-Type: application/json');

// --- 1. GATHER & VALIDATE INPUT ---
$employee_id = isset($_POST['employee_id']) ? trim($_POST['employee_id']) : '';
$snapshot = isset($_POST['snapshot']) ? trim($_POST['snapshot']) : '';
$latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;

// --- Wearable fields (NEW) ---
$device_id = isset($_POST['device_id']) ? trim($_POST['device_id']) : '';
$wearable_token = isset($_POST['wearable_token']) ? trim($_POST['wearable_token']) : '';
$ts = isset($_POST['timestamp']) ? (int)$_POST['timestamp'] : 0;
$signature = isset($_POST['signature']) ? trim($_POST['signature']) : '';
// Indoor presence evidence (optional)
$beacons_json = isset($_POST['beacons']) ? $_POST['beacons'] : '[]';
$wifi_json = isset($_POST['wifi']) ? $_POST['wifi'] : '[]';
$beacons_hash_posted = isset($_POST['beacons_hash']) ? strtolower(trim($_POST['beacons_hash'])) : '';
$wifi_hash_posted = isset($_POST['wifi_hash']) ? strtolower(trim($_POST['wifi_hash'])) : '';
$connected_bssid = isset($_POST['connected_bssid']) ? strtoupper(trim($_POST['connected_bssid'])) : '';
$connected_ssid = isset($_POST['connected_ssid']) ? trim($_POST['connected_ssid']) : '';
$isWearable = ($device_id && $wearable_token && $ts && $signature);

// Parse indoor evidence early and compute hashes for signature binding
$beacons_list = canonicalizeBeacons(safeJsonDecode($beacons_json));
$wifi_list = canonicalizeWifi(safeJsonDecode($wifi_json));
$beacons_hash_calc = computeListHash($beacons_list);
$wifi_hash_calc = computeListHash($wifi_list);

// Basic validation for required fields
if (!$employee_id) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing employee_id.']));
}
if (!$isWearable && ($snapshot === '' || $latitude === null || $longitude === null)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing required data (snapshot / location) for photo method or wearable credentials.']));
}
// For wearable, allow indoor-only evidence (BLE/Wi‑Fi) when GPS is absent
if ($isWearable && ($latitude === null || $longitude === null) && empty($beacons_list) && empty($wifi_list)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Provide GPS or indoor evidence (BLE/Wi‑Fi) for wearable clock.']));
}

// --- 2. LOOKUP EMPLOYEE ---
try {
    $stmt = $conn->prepare("SELECT EmployeeID, FullName, PhoneNumber, BranchID FROM tbl_employees WHERE EmployeeID = ?");
    $stmt->execute([$employee_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        http_response_code(404); // Not Found
        exit(json_encode(['error' => 'Invalid Employee ID.']));
    }
} catch (PDOException $e) {
    error_log("Employee lookup failed: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Employee lookup failed.']));
}

// --- 3. (Optional) WEARABLE AUTH (before any heavy processing) ---
if ($isWearable) {
    // Validate evidence hashes if provided
    if ($beacons_hash_posted && $beacons_hash_posted !== $beacons_hash_calc) {
        http_response_code(400);
        exit(json_encode(['error' => 'Beacon list hash mismatch. Evidence may be tampered.']));
    }
    if ($wifi_hash_posted && $wifi_hash_posted !== $wifi_hash_calc) {
        http_response_code(400);
        exit(json_encode(['error' => 'Wi‑Fi list hash mismatch. Evidence may be tampered.']));
    }

    $dev = loadWearableDevice($conn, $device_id, $employee_id);
    if (!$dev) {
        http_response_code(403);
        exit(json_encode(['error' => 'Wearable not registered or inactive.']));
    }
    if (abs(time() - $ts) > 120) {
        http_response_code(400);
        exit(json_encode(['error' => 'Stale wearable timestamp.']));
    }
    if (!verifyWearableSignature(
        $dev['SecretHash'],
        $device_id,
        $wearable_token,
        $ts,
        $signature,
        $beacons_hash_posted ?: $beacons_hash_calc,
        $wifi_hash_posted ?: $wifi_hash_calc,
        $connected_bssid
    )) {
        http_response_code(403);
        exit(json_encode(['error' => 'Invalid wearable signature.']));
    }
    if (isReplayToken($conn, $wearable_token)) {
        http_response_code(409);
        exit(json_encode(['error' => 'Replay wearable token.']));
    }
    storeTokenHash($conn, $wearable_token, $device_id, $ts);
    updateDeviceLastSeen($conn, $device_id);
}

// --- 4. LOOKUP ASSIGNED SHIFTS ---
try {
    $shiftStmt = $conn->prepare("
        SELECT s.ShiftID, s.StartTime, s.EndTime, s.WorkingDays FROM tbl_shifts s
        JOIN employee_shifts es ON es.ShiftID = s.ShiftID
        WHERE es.EmployeeID = ?
    ");
    $shiftStmt->execute([$employee_id]);
    $shifts = $shiftStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$shifts) {
        http_response_code(403); // Forbidden
        exit(json_encode(['error' => 'No shift assigned to this employee.']));
    }
} catch (PDOException $e) {
    error_log("Shift lookup failed: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Shift lookup failed.']));
}

// --- 5. CHECK FOR HOLIDAYS ---
$today = date('Y-m-d');
$dayName = date('l'); // e.g., Monday, Tuesday

try {
    $isHoliday = $conn->prepare("SELECT 1 FROM tbl_holidays WHERE HolidayDate = ?");
    $isHoliday->execute([$today]);
    if ($isHoliday->fetchColumn()) {
        http_response_code(403);
        exit(json_encode(['error' => 'Today is a public holiday. Clock-in is not required.']));
    }
} catch (PDOException $e) {
    error_log("Holiday check failed: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Holiday check failed.']));
}

// --- 6. GET EMPLOYEE'S BRANCHES ---
try {
    $branchStmt = $conn->prepare("
        SELECT b.BranchID, b.BranchName, b.Latitude, b.Longitude, b.AllowedRadius FROM tbl_branches b
        JOIN employee_branches eb ON eb.BranchID = b.BranchID
        WHERE eb.EmployeeID = ?
        UNION
        SELECT b.BranchID, b.BranchName, b.Latitude, b.Longitude, b.AllowedRadius FROM tbl_branches b
        JOIN tbl_employees e ON e.BranchID = b.BranchID
        WHERE e.EmployeeID = ?
    ");
    $branchStmt->execute([$employee_id, $employee_id]);
    $branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$branches) {
        http_response_code(403);
        exit(json_encode(['error' => 'No branch has been assigned to this employee.']));
    }
} catch (PDOException $e) {
    error_log("Branch lookup failed: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Branch lookup failed.']));
}

// --- 7. VALIDATE LOCATION AND FIND MATCHED BRANCH ---
$matchedBranchID = null;
$matchedBranch = null; // Will store the full branch details

// 7a. Try indoor fusion if wearable and indoor evidence provided
if ($isWearable && (!empty($beacons_list) || !empty($wifi_list))) {
    $indoor = resolveIndoorBranch($conn, $branches, $beacons_list, $wifi_list, $connected_bssid);
    if ($indoor) {
        $matchedBranchID = $indoor['BranchID'];
        $matchedBranch = $indoor;
    }
}

// 7b. Fallback to GPS geofence if not matched yet and GPS is present
if (!$matchedBranchID && $latitude !== null && $longitude !== null) {
    foreach ($branches as $b) {
        $branchLat = (float)$b['Latitude'];
        $branchLon = (float)$b['Longitude'];
        $allowedRadius = (float)$b['AllowedRadius'];

        if (haversine($latitude, $longitude, $branchLat, $branchLon) <= $allowedRadius) {
            $matchedBranchID = $b['BranchID'];
            $matchedBranch = $b; // Save the matched branch details
            break;
        }
    }
}

if (!$matchedBranchID) {
    http_response_code(403);
    exit(json_encode(['error' => 'Location validation failed: not within branch geofence and no indoor (BLE/Wi‑Fi) match.']));
}

// --- 8. FIND MATCHING SHIFT FOR TODAY ---
$matchedShift = null;
foreach ($shifts as $shift) {
    $days = array_map('trim', explode(',', $shift['WorkingDays']));
    if (in_array($dayName, $days)) {
        $matchedShift = $shift;
        break;
    }
}
if (!$matchedShift) {
    http_response_code(403);
    exit(json_encode(['error' => 'You are not scheduled to work today.']));
}

// --- 9. CLOCK-IN / CLOCK-OUT LOGIC ---
$now = date('H:i:s');
$date = date('Y-m-d');
$shiftStart = $matchedShift['StartTime'];
$shiftEnd = $matchedShift['EndTime'];

// Check for any open attendance record for today
try {
    $openAtt = $conn->prepare("SELECT * FROM tbl_attendance WHERE EmployeeID = ? AND AttendanceDate = ? AND ClockOut IS NULL");
    $openAtt->execute([$employee_id, $date]);
    $openRecord = $openAtt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Open attendance record lookup failed: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Attendance lookup failed.']));
}

$filename = null;
if (!$isWearable) {
    // Save photo snapshot to server
    $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $snapshot);
    $data = base64_decode($imageData);

    // Basic image validation: Check if base64 decode was successful and if it's a valid image
    if ($data === false || empty($data)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid image data provided.']));
    }

    // Ensure uploads directory exists and has correct permissions
    $uploadDir = 'uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        exit(json_encode(['error' => 'Cannot create upload directory.']));
    }

    // Generate a unique filename
    $filename = $uploadDir . "/{$employee_id}_" . time() . ".png";

    // Save the file
    if (!file_put_contents($filename, $data)) {
        http_response_code(500);
        exit(json_encode(['error' => 'Failed saving snapshot.']));
    }
}

$action = '';
$status = '';
$methodIn = $isWearable ? 'wearable' : 'photo';
$methodOut = $methodIn;

if ($openRecord) {
    // There's an open record, so this is a CLOCK-OUT
    if ($openRecord['BranchID'] != $matchedBranchID) {
        // Delete the saved photo if clock-out is rejected due to branch mismatch
        if (!$isWearable && $filename && file_exists($filename)) unlink($filename);
        http_response_code(403);
        exit(json_encode(['error' => 'You must clock out from the same branch you clocked in.']));
    }
    $action = 'clockout';
    $status = ($now >= $shiftEnd) ? 'On Time' : 'Left Early';

    // Update the attendance record in the database
    try {
        $stmt = $conn->prepare("
            UPDATE tbl_attendance
            SET ClockOut = ?, ClockOutPhoto = ?, ClockOutStatus = ?, Latitude = ?, Longitude = ?, Remarks = ?, ClockOutMethod = ?
            WHERE AttendanceID = ?
        ");
        $stmt->execute([$now, $filename, $status, $latitude, $longitude, $reason, $methodOut, $openRecord['AttendanceID']]);

        if ($stmt->rowCount() === 0) {
            // This might happen if the record was deleted or updated by another process
            http_response_code(500);
            exit(json_encode(['error' => 'Clock-out update failed.']));
        }
    } catch (PDOException $e) {
        error_log("Clock-out update failed: " . $e->getMessage());
        http_response_code(500);
        exit(json_encode(['error' => 'Clock-out DB error.']));
    }

} else {
    // No open record, so this is a CLOCK-IN
    $action = 'clockin';
    $status = ($now <= $shiftStart) ? 'On Time' : 'Late';

    // Insert the attendance record in the database
    try {
        $stmt = $conn->prepare("
            INSERT INTO tbl_attendance
            (EmployeeID, BranchID, AttendanceDate, ClockIn, ClockInPhoto, ClockInStatus, Latitude, Longitude, Remarks, ClockInMethod)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$employee_id, $matchedBranchID, $date, $now, $filename, $status, $latitude, $longitude, $reason, $methodIn]);
    } catch (PDOException $e) {
        error_log("Clock-in insert failed: " . $e->getMessage());
        if (!$isWearable && $filename && file_exists($filename)) unlink($filename);
        http_response_code(500);
        exit(json_encode(['error' => 'Clock-in DB error.']));
    }
}

// --- 10. SEND SMS NOTIFICATION ---
$phone = $emp['PhoneNumber'];
if (!empty($phone) && $smsService) {
    try {
        $branchName = $matchedBranch['BranchName'] ?? 'the branch';
        
        // Prepare template data
        $templateData = [
            'name' => $emp['FullName'],
            'branch' => $branchName,
            'time' => $now,
            'status' => $status,
            'employee_id' => $employee_id
        ];
        
        // Choose template based on action
        $templateName = $action === 'clockin' ? 'attendance_clockin' : 'attendance_clockout';
        
        // Send SMS using template
        $smsService->sendTemplateMessage($templateName, $phone, $templateData, SignSyncSMSService::PRIORITY_NORMAL);
        
        // Log success
        error_log("SMS sent successfully to $phone for $action");
        
    } catch (Exception $e) {
        // Log error but don't fail the attendance operation
        error_log("SMS sending failed for $phone: " . $e->getMessage());
    }
}

// --- FINAL SUCCESS RESPONSE ---
http_response_code(200); // OK
echo json_encode(['success' => true, 'action' => $action, 'status' => $status, 'method' => $isWearable ? 'wearable' : 'photo', 'timestamp' => $now]);


// ==================================================================
// HELPER FUNCTIONS
// ==================================================================

/**
 * Calculates the distance between two points on Earth using the Haversine formula.
 * @param float $lat1 Latitude of point 1
 * @param float $lon1 Longitude of point 1
 * @param float $lat2 Latitude of point 2
 * @param float $lon2 Longitude of point 2
 * @return float Distance in kilometers.
 */
function haversine($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Kilometers
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

/**
 * Function to send SMS notifications using SMSOnlineGH API
 * IMPORTANT: The API key should be stored securely (e.g., environment variables)
 * and NOT hardcoded in production code.
 *
 * @param string $message The SMS message content.
 * @param array $destinationNumbers An array of phone number strings (e.g., ['233241234567', '233501234567']).
 * @return bool True if the SMS request was successfully accepted by the API (HTTP 200 and handshake OK), false otherwise.
 */
function sendSMSOnlineGH($message, $destinationNumbers) {
    // !!! CRITICAL SECURITY WARNING !!!
    // This API key should be loaded from a secure source (e.g., environment variables,
    // a configuration file outside the web root) and NOT hardcoded in production.
    // For demonstration purposes only:
    $apiKey = 'aefc1848ebc7baaa90e71bfb6072287cc2cc197882e73631a1bdc27135a51abb'; // Example key from documentation

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: key ' . $apiKey
    ];

    $messageData = [
        'text' => $message,
        'type' => 0, // GSM default
        'sender' => 'AKCBANK LTD', // Ensure this sender ID is registered in your SMSOnlineGH account
        'destinations' => $destinationNumbers // This must be an array of strings like ['233XXXXXXXXX']
    ];

    // Corrected endpoint URL as per documentation: added '/message/'
    $ch = curl_init('https://api.smsonlinegh.com/v5/message/sms/send');

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // --- SSL Verification Setup (CRITICAL FOR PRODUCTION) ---
    // Ensure 'cacert.pem' is in the correct path relative to this script,
    // or provide an absolute path.
    // Download the latest cacert.pem from https://curl.se/docs/caextract.html
    $caCertPath = __DIR__ . "/cacert.pem";
    if (file_exists($caCertPath)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caCertPath);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable peer verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    // Verify hostname against certificate
    } else {
        // WARNING: Disabling SSL verification is insecure. Only for development!
        error_log("CA certificate file not found at: $caCertPath. Disabling SSL verification (NOT RECOMMENDED FOR PRODUCTION!).");
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }
    // --- END SSL Verification Setup ---

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        error_log("cURL error sending SMS: " . curl_error($ch));
        curl_close($ch);
        return false; // cURL error occurred
    }

    curl_close($ch);

    // Log the full response for debugging
    error_log("SMS API Raw Response: HTTP Code $httpCode, Body: $response");

    $responseData = json_decode($response, true);

    // Check HTTP status code AND API's internal handshake status for success
    $success = false;
    if ($httpCode == 200 && isset($responseData['handshake']) &&
        $responseData['handshake']['id'] === 0 && $responseData['handshake']['label'] === 'HSHK_OK') {
        $success = true;
        // Log individual destination statuses if the request was accepted by the API
        if (isset($responseData['data']['destinations'])) {
            foreach ($responseData['data']['destinations'] as $destination) {
                $to = $destination['to'] ?? 'Unknown';
                $status = $destination['status']['label'] ?? 'Unknown';
                $statusId = $destination['status']['id'] ?? 'Unknown';
                $messageId = $destination['id'] ?? 'Unknown';
                error_log("SMS to $to: Status - $status (ID: $statusId), Message ID: $messageId");
            }
        }
    } else {
        // Log detailed error from API response if available
        $errorMsg = "SMS API Request Failed. HTTP Code: $httpCode. ";
        if (isset($responseData['handshake'])) {
            $errorMsg .= "Handshake ID: {$responseData['handshake']['id']}, Label: {$responseData['handshake']['label']}. ";
        }
        // Check for other potential error messages in the response structure
        if (isset($responseData['data']['message'])) {
            $errorMsg .= "API Message: {$responseData['data']['message']}.";
        } elseif (isset($responseData['message'])) { // Some APIs use a top-level 'message' for errors
            $errorMsg .= "API Message: {$responseData['message']}.";
        }
        error_log($errorMsg);
    }

    return $success;
}

// Wearable helpers (NEW)
function loadWearableDevice(PDO $c, string $deviceId, string $empId) {
    $q = $c->prepare("SELECT DeviceID, EmployeeID, SecretHash, Status FROM tbl_wearable_devices WHERE DeviceID=? AND EmployeeID=? AND Status='active'");
    $q->execute([$deviceId, $empId]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

function verifyWearableSignature(string $secretHash, string $deviceId, string $token, int $ts, string $sig, string $beaconsHash = '', string $wifiHash = '', string $connectedBssid = ''): bool {
    $key = hex2bin($secretHash);
    if (!$key) return false;
    $candidates = [];
    // Legacy payload
    $candidates[] = $deviceId . '|' . $token . '|' . $ts;
    // Bound to evidence hashes (beacons+wifi)
    if ($beaconsHash || $wifiHash) {
        $candidates[] = $deviceId . '|' . $token . '|' . $ts . '|' . $beaconsHash . '|' . $wifiHash;
    }
    // Bound to evidence hashes + connected BSSID
    if ($connectedBssid) {
        $candidates[] = $deviceId . '|' . $token . '|' . $ts . '|' . $beaconsHash . '|' . $wifiHash . '|' . $connectedBssid;
    }
    $sig = strtolower($sig);
    foreach ($candidates as $payload) {
        $calc = hash_hmac('sha256', $payload, $key);
        if (($d = base64_decode($sig, true)) !== false) {
            if (hash_equals($calc, bin2hex($d))) return true;
        }
        if (hash_equals($calc, $sig)) return true;
    }
    return false;
}

function isReplayToken(PDO $c, string $token): bool {
    $hash = hash('sha256', $token);
    $q = $c->prepare("SELECT 1 FROM tbl_wearable_tokens WHERE TokenHash=?");
    $q->execute([$hash]);
    return (bool)$q->fetchColumn();
}

function storeTokenHash(PDO $c, string $token, string $deviceId, int $ts): void {
    $hash = hash('sha256', $token);
    $q = $c->prepare("INSERT IGNORE INTO tbl_wearable_tokens (TokenHash, DeviceID, IssuedAt) VALUES (?,?,FROM_UNIXTIME(?))");
    $q->execute([$hash, $deviceId, $ts]);
}

function updateDeviceLastSeen(PDO $c, string $deviceId): void {
    $q = $c->prepare("UPDATE tbl_wearable_devices SET LastSeenAt=NOW() WHERE DeviceID=?");
    $q->execute([$deviceId]);
}

// --- Indoor evidence helpers (BLE/Wi‑Fi) ---
function safeJsonDecode($json) {
    if (is_array($json)) return $json; // already decoded
    $data = json_decode((string)$json, true);
    return is_array($data) ? $data : [];
}

function canonicalizeBeacons(array $beacons): array {
    $macs = [];
    foreach ($beacons as $b) {
        $m = '';
        if (isset($b['mac'])) $m = $b['mac'];
        elseif (isset($b['uuid'])) $m = $b['uuid'];
        $m = strtoupper(trim((string)$m));
        if ($m !== '') $macs[] = $m;
    }
    $macs = array_values(array_unique($macs));
    sort($macs, SORT_STRING);
    return $macs;
}

function canonicalizeWifi(array $aps): array {
    $bssids = [];
    foreach ($aps as $a) {
        $b = isset($a['bssid']) ? $a['bssid'] : (isset($a['BSSID']) ? $a['BSSID'] : '');
        $b = strtoupper(trim((string)$b));
        if ($b !== '') $bssids[] = $b;
    }
    $bssids = array_values(array_unique($bssids));
    sort($bssids, SORT_STRING);
    return $bssids;
}

function computeListHash(array $list): string {
    if (empty($list)) return '';
    $joined = implode(',', $list);
    return hash('sha256', $joined);
}

function resolveIndoorBranch(PDO $c, array $branches, array $beaconsList, array $wifiList, string $connectedBssid = '') {
    if (empty($branches)) return null;
    $branchIds = array_map(function($b){ return $b['BranchID']; }, $branches);
    $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
    $beaconsByBranch = [];
    $wifiByBranch = [];
    try {
        // Prefetch beacon MACs by branch
        $qb = $c->prepare("SELECT BranchID, UPPER(MAC) AS MAC FROM tbl_branch_beacons WHERE BranchID IN ($placeholders)");
        $qb->execute($branchIds);
        while ($row = $qb->fetch(PDO::FETCH_ASSOC)) {
            $beaconsByBranch[$row['BranchID']][] = $row['MAC'];
        }
    } catch (Throwable $e) {
        error_log('Beacon whitelist lookup skipped: ' . $e->getMessage());
    }
    try {
        // Prefetch Wi‑Fi BSSIDs by branch
        $qw = $c->prepare("SELECT BranchID, UPPER(BSSID) AS BSSID FROM tbl_branch_wifi WHERE BranchID IN ($placeholders)");
        $qw->execute($branchIds);
        while ($row = $qw->fetch(PDO::FETCH_ASSOC)) {
            $wifiByBranch[$row['BranchID']][] = $row['BSSID'];
        }
    } catch (Throwable $e) {
        error_log('Wi‑Fi whitelist lookup skipped: ' . $e->getMessage());
    }

    $best = null;
    $bestScore = -1;
    foreach ($branches as $b) {
        $bid = $b['BranchID'];
        $score = 0;
        $branchBeacons = isset($beaconsByBranch[$bid]) ? array_unique($beaconsByBranch[$bid]) : [];
        $branchWifi = isset($wifiByBranch[$bid]) ? array_unique($wifiByBranch[$bid]) : [];

        // Strong signal: connected BSSID matches branch Wi‑Fi
        if ($connectedBssid && in_array($connectedBssid, $branchWifi, true)) {
            $score += 3; // strong tie
        }
        // BLE beacon overlap
        if (!empty($beaconsList) && !empty($branchBeacons)) {
            $overlap = array_intersect($beaconsList, $branchBeacons);
            $score += count($overlap);
        }
        // Wi‑Fi scan overlap
        if (!empty($wifiList) && !empty($branchWifi)) {
            $overlap = array_intersect($wifiList, $branchWifi);
            $score += count($overlap);
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $b;
        }
    }

    // Require a minimal score to accept indoor resolution
    if ($best && $bestScore >= 2) return $best;
    return null;
}

?>