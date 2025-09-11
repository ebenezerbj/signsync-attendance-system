<?php
session_start();
include 'db.php';

// Get session token
$sessionToken = $_GET['session'] ?? '';

if (!$sessionToken) {
    die('Invalid session token');
}

// Validate session and get camera details
$stmt = $conn->prepare("
    SELECT cs.*, c.DeviceName, c.Identifier, c.Location, c.Metadata,
           e.FullName, ba.Severity, ba.AlertType,
           cc.StreamURL, cc.Username, cc.Password, cc.Port, cc.StreamType
    FROM tbl_camera_sessions cs
    JOIN tbl_devices c ON cs.CameraID = c.DeviceID
    JOIN tbl_employees e ON cs.EmployeeID = e.EmployeeID
    LEFT JOIN tbl_biometric_alerts ba ON cs.AlertID = ba.AlertID
    LEFT JOIN tbl_camera_config cc ON c.DeviceID = cc.CameraID
    WHERE cs.SessionToken = ? AND cs.IsActive = 1 AND cs.ExpiresAt > NOW()
");
$stmt->execute([$sessionToken]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die('Session expired or invalid');
}

// Generate stream URL
function generateStreamURL($session) {
    $baseIP = $session['Identifier'];
    $username = $session['Username'] ?? 'admin';
    $password = $session['Password'] ?? 'admin123';
    $port = $session['Port'] ?? 554;
    
    // For demo purposes, return a placeholder or webcam test URL
    // Replace with actual RTSP URL for production
    return "rtsp://{$username}:{$password}@{$baseIP}:{$port}/live";
}

$streamURL = generateStreamURL($session);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camera Feed - <?= htmlspecialchars($session['DeviceName']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #000; margin: 0; padding: 0; }
        .video-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            background: #000;
            display: flex;
            flex-direction: column;
        }
        .video-header {
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: between;
            align-items: center;
            z-index: 10;
        }
        .video-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
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
        .video-placeholder {
            text-align: center;
            color: white;
            padding: 40px;
        }
        .control-panel {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            padding: 15px;
            border-radius: 8px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pulse {
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
    <div class="video-container">
        <!-- Header -->
        <div class="video-header">
            <div>
                <h5 class="mb-0">
                    <i class="bi bi-camera-video me-2"></i>
                    <?= htmlspecialchars($session['DeviceName']) ?>
                    <span class="stress-indicator stress-<?= $session['Severity'] ?? 'moderate' ?>"></span>
                </h5>
                <small>
                    Monitoring: <strong><?= htmlspecialchars($session['FullName']) ?></strong> 
                    | Location: <?= htmlspecialchars($session['Location']) ?>
                    | Alert: <?= ucfirst($session['AlertType'] ?? 'Unknown') ?>
                </small>
            </div>
            <div>
                <button class="btn btn-sm btn-outline-light me-2" onclick="toggleFullscreen()">
                    <i class="bi bi-fullscreen"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="window.close()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <!-- Video Content -->
        <div class="video-content">
            <!-- For production, replace this with actual video player -->
            <div class="video-placeholder">
                <div class="pulse">
                    <i class="bi bi-camera display-1 mb-4"></i>
                    <h4>Live Camera Feed</h4>
                    <p class="mb-4"><?= htmlspecialchars($session['DeviceName']) ?></p>
                    
                    <!-- Demo: Show connection details -->
                    <div class="alert alert-info d-inline-block">
                        <strong>Stream URL:</strong><br>
                        <code><?= htmlspecialchars($streamURL) ?></code>
                    </div>
                    
                    <!-- For actual implementation, use video.js or similar -->
                    <div class="mt-4">
                        <p><strong>Integration Instructions:</strong></p>
                        <ul class="text-start" style="max-width: 400px; margin: 0 auto;">
                            <li>Replace this placeholder with video.js or WebRTC player</li>
                            <li>Connect to RTSP stream: <code><?= htmlspecialchars($streamURL) ?></code></li>
                            <li>Add PTZ controls if camera supports it</li>
                            <li>Implement recording capabilities</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="control-panel">
            <div>
                <strong>Session:</strong> 
                Expires at <?= date('H:i:s', strtotime($session['ExpiresAt'])) ?>
                <span class="badge bg-success ms-2">Live</span>
            </div>
            
            <div>
                <button class="btn btn-sm btn-outline-light me-2" onclick="takeSnapshot()">
                    <i class="bi bi-camera"></i> Snapshot
                </button>
                <button class="btn btn-sm btn-outline-warning me-2" onclick="startRecording()">
                    <i class="bi bi-record-circle"></i> Record
                </button>
                <button class="btn btn-sm btn-outline-info" onclick="showEmployeeDetails()">
                    <i class="bi bi-person"></i> Employee Info
                </button>
            </div>
        </div>
    </div>

    <!-- Employee Details Modal -->
    <div class="modal fade" id="employeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Name:</strong><br>
                            <?= htmlspecialchars($session['FullName']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Alert Type:</strong><br>
                            <span class="stress-indicator stress-<?= $session['Severity'] ?? 'moderate' ?>"></span>
                            <?= ucfirst($session['AlertType'] ?? 'Unknown') ?>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Camera:</strong><br>
                            <?= htmlspecialchars($session['DeviceName']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Location:</strong><br>
                            <?= htmlspecialchars($session['Location']) ?>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Session Started:</strong><br>
                            <?= date('M j, Y H:i:s', strtotime($session['StartTime'])) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Expires:</strong><br>
                            <?= date('M j, Y H:i:s', strtotime($session['ExpiresAt'])) ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" onclick="acknowledgeAlert()">
                        Acknowledge Alert
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    }

    function takeSnapshot() {
        // Implement snapshot functionality
        alert('Snapshot taken! (Implement actual capture functionality)');
    }

    function startRecording() {
        // Implement recording functionality
        alert('Recording started! (Implement actual recording functionality)');
    }

    function showEmployeeDetails() {
        $('#employeeModal').modal('show');
    }

    function acknowledgeAlert() {
        if (confirm('Mark this stress alert as acknowledged?')) {
            // Send AJAX request to acknowledge alert
            $.post('camera_stress_api.php', {
                action: 'acknowledge_alert',
                session_token: '<?= $sessionToken ?>'
            }, function(response) {
                if (response.success) {
                    alert('Alert acknowledged');
                    window.close();
                }
            }, 'json');
        }
    }

    // Auto-close when session expires
    setTimeout(function() {
        alert('Camera session expired');
        window.close();
    }, <?= (strtotime($session['ExpiresAt']) - time()) * 1000 ?>);

    // Keep session alive with periodic heartbeat
    setInterval(function() {
        $.get('camera_stress_api.php?action=heartbeat&session=<?= $sessionToken ?>');
    }, 60000); // Every minute
    </script>
</body>
</html>
