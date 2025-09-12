<?php
session_start();
include 'db.php';

// Ensure user is logged in and is an admin/security
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'security', 'hr'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// Handle manual camera trigger
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    if ($_POST['action'] === 'acknowledge_alert') {
        $alertID = $_POST['alert_id'];
        $userID = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("
            UPDATE tbl_biometric_alerts 
            SET IsAcknowledged = 1, AcknowledgedBy = ?, AcknowledgedAt = NOW()
            WHERE AlertID = ?
        ");
        
        header('Content-Type: application/json');
        if ($stmt->execute([$userID, $alertID])) {
            echo json_encode(['success' => true, 'message' => 'Alert acknowledged.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to acknowledge alert.']);
        }
        exit;
    }

    if ($_POST['action'] === 'trigger_camera') {
        $employeeID = $_POST['employee_id'];
        $reason = $_POST['reason'] ?? 'Manual monitoring request';
        
        // Create a manual alert
        $stmt = $conn->prepare("
            INSERT INTO tbl_biometric_alerts (
                EmployeeID, AlertType, Severity, AlertMessage, 
                CreatedAt, IsAcknowledged, CameraTriggered
            ) VALUES (?, 'manual_check', 'medium', ?, NOW(), 0, 1)
        ");
        $stmt->execute([$employeeID, 'Manual camera check: ' . $reason]);
        $alertID = $conn->lastInsertId();
        
        // Get nearby cameras for this employee
        $cameras = $conn->query("
            SELECT ecm.*, c.DeviceName, c.Location, c.Identifier
            FROM tbl_employee_camera_mapping ecm
            JOIN tbl_devices c ON ecm.CameraID = c.DeviceID
            WHERE ecm.EmployeeID = '$employeeID' AND ecm.IsActive = 1
            ORDER BY ecm.ProximityScore DESC
            LIMIT 3
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Create camera sessions
        foreach ($cameras as $camera) {
            $sessionToken = bin2hex(random_bytes(16));
            $stmt = $conn->prepare("
                INSERT INTO tbl_camera_sessions (
                    EmployeeID, CameraID, AlertID, SessionToken, 
                    StartTime, ExpiresAt, IsActive, ViewerUserID
                ) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE), 1, ?)
            ");
            $stmt->execute([$employeeID, $camera['CameraID'], $alertID, $sessionToken, $_SESSION['user_id']]);
        }
        
        $message = "Camera monitoring activated for employee. " . count($cameras) . " cameras available.";
        $messageType = "success";
    }
}

// Get current active stress alerts with camera capabilities
$activeStressAlerts = $conn->query("
    SELECT ba.*, e.FullName, e.EmployeeID,
           COUNT(ecm.CameraID) as available_cameras,
           COUNT(cs.SessionID) as active_sessions
    FROM tbl_biometric_alerts ba
    JOIN tbl_employees e ON ba.EmployeeID = e.EmployeeID
    LEFT JOIN tbl_employee_camera_mapping ecm ON e.EmployeeID = ecm.EmployeeID AND ecm.IsActive = 1
    LEFT JOIN tbl_camera_sessions cs ON ba.AlertID = cs.AlertID AND cs.IsActive = 1 AND cs.ExpiresAt > NOW()
    WHERE ba.AlertType IN ('stress', 'manual_check')
    AND ba.Severity IN ('high', 'critical', 'moderate')
    AND ba.IsAcknowledged = 0
    GROUP BY ba.AlertID
    ORDER BY ba.CreatedAt DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get active camera sessions for monitoring
$activeSessions = $conn->query("
    SELECT cs.*, c.DeviceName, c.Location, e.FullName, ba.Severity, ba.AlertType,
           cc.StreamURL, cc.Username, cc.Port, cc.StreamType
    FROM tbl_camera_sessions cs
    JOIN tbl_devices c ON cs.CameraID = c.DeviceID
    JOIN tbl_employees e ON cs.EmployeeID = e.EmployeeID
    LEFT JOIN tbl_biometric_alerts ba ON cs.AlertID = ba.AlertID
    LEFT JOIN tbl_camera_config cc ON c.DeviceID = cc.CameraID
    WHERE cs.IsActive = 1 AND cs.ExpiresAt > NOW()
    ORDER BY cs.StartTime DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get employees with wearables for manual triggers
$employeesWithWearables = $conn->query("
    SELECT e.EmployeeID, e.FullName, dept.DepartmentName, b.BranchName,
           COUNT(ecm.CameraID) as camera_count
    FROM tbl_employees e
    JOIN tbl_employee_wearables ew ON e.EmployeeID = ew.EmployeeID
    LEFT JOIN tbl_departments dept ON e.DepartmentID = dept.DepartmentID
    LEFT JOIN tbl_branches b ON e.BranchID = b.BranchID
    LEFT JOIN tbl_employee_camera_mapping ecm ON e.EmployeeID = ecm.EmployeeID AND ecm.IsActive = 1
    WHERE ew.IsActive = 1
    GROUP BY e.EmployeeID
    ORDER BY e.FullName
")->fetchAll(PDO::FETCH_ASSOC);

// Camera statistics
$cameraStats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM tbl_devices WHERE DeviceType = 'camera' AND IsActive = 1) as total_cameras,
        (SELECT COUNT(DISTINCT cs.CameraID) FROM tbl_camera_sessions cs WHERE cs.IsActive = 1 AND cs.ExpiresAt > NOW()) as active_cameras,
        (SELECT COUNT(*) FROM tbl_biometric_alerts WHERE AlertType = 'stress' AND Severity IN ('high', 'critical') AND IsAcknowledged = 0) as stress_alerts,
        (SELECT COUNT(*) FROM tbl_camera_triggers WHERE Status = 'active') as active_triggers
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCTV Stress Monitoring Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .video-container {
            position: relative;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        .video-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        .stress-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .stress-low { background-color: #28a745; }
        .stress-moderate { background-color: #ffc107; }
        .stress-high { background-color: #fd7e14; }
        .stress-critical { background-color: #dc3545; }
        .camera-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .alert-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .alert-card.critical { border-left-color: #dc3545; }
        .alert-card.high { border-left-color: #fd7e14; }
        .alert-card.moderate { border-left-color: #ffc107; }
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-camera-video text-danger me-2"></i>
                    CCTV Stress Monitoring Dashboard
                </h2>
                <p class="text-muted mb-0">Real-time camera feeds triggered by IoT stress alerts</p>
            </div>
            <div>
                <a href="wellness_dashboard.php" class="btn btn-outline-info me-2">
                    <i class="bi bi-heart-pulse"></i> Wellness Dashboard
                </a>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-camera display-6 text-primary me-3"></i>
                            <div>
                                <p class="mb-0 text-muted small">Total Cameras</p>
                                <h3 class="mb-0"><?= $cameraStats['total_cameras'] ?></h3>
                                <small class="text-muted"><?= $cameraStats['active_cameras'] ?> active feeds</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle display-6 text-warning me-3"></i>
                            <div>
                                <p class="mb-0 text-muted small">Stress Alerts</p>
                                <h3 class="mb-0"><?= $cameraStats['stress_alerts'] ?></h3>
                                <small class="text-muted">requiring attention</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-record-circle display-6 text-danger me-3"></i>
                            <div>
                                <p class="mb-0 text-muted small">Active Triggers</p>
                                <h3 class="mb-0"><?= $cameraStats['active_triggers'] ?></h3>
                                <small class="text-muted">camera sessions</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-people display-6 text-success me-3"></i>
                            <div>
                                <p class="mb-0 text-muted small">Monitored Staff</p>
                                <h3 class="mb-0"><?= count($employeesWithWearables) ?></h3>
                                <small class="text-muted">with wearables</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Active Camera Feeds -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-camera-video me-2"></i>
                            Live Camera Feeds
                            <?php if (count($activeSessions) > 0): ?>
                                <span class="badge bg-danger pulse-animation ms-2"><?= count($activeSessions) ?> Active</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0" style="min-height: 400px;">
                        <?php if (empty($activeSessions)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-camera-video-off display-1 text-muted"></i>
                                <h5 class="mt-3 text-muted">No Active Camera Sessions</h5>
                                <p class="text-muted">Camera feeds will appear here when stress alerts are triggered</p>
                            </div>
                        <?php else: ?>
                            <div class="camera-grid p-3">
                                <?php foreach ($activeSessions as $session): ?>
                                <div class="video-container">
                                    <div class="video-overlay">
                                        <div>
                                            <strong><?= htmlspecialchars($session['FullName']) ?></strong>
                                            <span class="stress-indicator stress-<?= $session['Severity'] ?? 'moderate' ?>"></span>
                                        </div>
                                        <div><?= htmlspecialchars($session['DeviceName']) ?> - <?= htmlspecialchars($session['Location']) ?></div>
                                        <small><?= date('H:i:s', strtotime($session['StartTime'])) ?></small>
                                    </div>
                                    <!-- Camera feed placeholder - integrate with your camera system -->
                                    <div class="bg-dark text-white text-center py-5" style="height: 200px;">
                                        <i class="bi bi-camera display-4"></i>
                                        <div class="mt-2">
                                            <strong><?= htmlspecialchars($session['DeviceName']) ?></strong><br>
                                            <small>RTSP: <?= htmlspecialchars($session['StreamType'] ?? 'rtsp') ?>://<?= htmlspecialchars(substr($session['SessionToken'], 0, 8)) ?>...</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-light mt-2" onclick="openCameraFeed('<?= $session['SessionToken'] ?>')">
                                            <i class="bi bi-play-fill"></i> View Feed
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="col-lg-4">
                <!-- Manual Camera Trigger -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-camera-fill me-2"></i>Manual Camera Trigger</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="trigger_camera">
                            <div class="mb-3">
                                <label for="employee_id" class="form-label">Employee</label>
                                <select name="employee_id" id="employee_id" class="form-select" required>
                                    <option value="">Select Employee...</option>
                                    <?php foreach ($employeesWithWearables as $emp): ?>
                                    <option value="<?= $emp['EmployeeID'] ?>">
                                        <?= htmlspecialchars($emp['FullName']) ?> 
                                        (<?= htmlspecialchars($emp['DepartmentName'] ?? 'No Dept') ?>) 
                                        - <?= $emp['camera_count'] ?> cams
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason</label>
                                <select name="reason" id="reason" class="form-select" required>
                                    <option value="">Select Reason...</option>
                                    <option value="Wellness check">Wellness Check</option>
                                    <option value="Security concern">Security Concern</option>
                                    <option value="Unusual behavior reported">Unusual Behavior</option>
                                    <option value="Manager request">Manager Request</option>
                                    <option value="Health emergency">Health Emergency</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-camera"></i> Activate Cameras
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Active Stress Alerts -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Stress Alerts</h5>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($activeStressAlerts)): ?>
                            <div class="p-3 text-center text-muted">
                                <i class="bi bi-check-circle display-6"></i>
                                <p class="mt-2 mb-0">No active stress alerts</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activeStressAlerts as $alert): ?>
                            <div class="alert-card <?= $alert['Severity'] ?> card-body border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <span class="stress-indicator stress-<?= $alert['Severity'] ?>"></span>
                                            <?= htmlspecialchars($alert['FullName']) ?>
                                        </h6>
                                        <p class="mb-1 small text-muted"><?= htmlspecialchars($alert['Message'] ?? 'No message available') ?></p>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> <?= date('M j, H:i', strtotime($alert['CreatedAt'])) ?>
                                            | <i class="bi bi-camera"></i> <?= $alert['available_cameras'] ?> cameras
                                            <?php if ($alert['active_sessions'] > 0): ?>
                                                | <span class="text-success">Live monitoring</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="btn-group">
                                        <?php if ($alert['active_sessions'] == 0): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="triggerCamera('<?= $alert['EmployeeID'] ?>', 'stress_alert')" title="Activate Camera">
                                            <i class="bi bi-camera"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-success" disabled>
                                            <i class="bi bi-record-circle"></i> Live
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="acknowledgeAlert(<?= $alert['AlertID'] ?>)" title="Acknowledge Alert">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function acknowledgeAlert(alertId) {
        if (confirm('Are you sure you want to acknowledge this alert?')) {
            $.post(window.location.href, { action: 'acknowledge_alert', alert_id: alertId }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to acknowledge alert: ' + response.message);
                }
            }, 'json');
        }
    }

    function triggerCamera(employeeId, reason) {
        if (confirm('Activate camera monitoring for this employee?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'trigger_camera';
            form.appendChild(actionInput);
            
            const employeeInput = document.createElement('input');
            employeeInput.name = 'employee_id';
            employeeInput.value = employeeId;
            form.appendChild(employeeInput);
            
            const reasonInput = document.createElement('input');
            reasonInput.name = 'reason';
            reasonInput.value = reason;
            form.appendChild(reasonInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }

    function openCameraFeed(sessionToken) {
        // Open camera feed in new window - integrate with your camera viewer
        const width = 800;
        const height = 600;
        const left = (screen.width - width) / 2;
        const top = (screen.height - height) / 2;
        
        window.open(
            `camera_viewer.php?session=${sessionToken}`, 
            'CameraFeed',
            `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
        );
    }

    // Auto-refresh every 30 seconds
    setInterval(function() {
        location.reload();
    }, 30000);
    </script>
</body>
</html>
