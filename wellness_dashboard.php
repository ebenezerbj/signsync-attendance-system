<?php
session_start();
include 'db.php';

// Ensure user is logged in and is an admin/hr
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    header('Location: login.php');
    exit;
}

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
}

// Get current date and time info
$today = date('Y-m-d');
$currentWeek = date('Y-\WW');

// KPI Queries
$totalWearableUsers = $conn->query("
    SELECT COUNT(DISTINCT EmployeeID) 
    FROM tbl_employee_wearables 
    WHERE IsActive = 1
")->fetchColumn();

$activeDevices = $conn->query("
    SELECT COUNT(*) 
    FROM tbl_devices d
    JOIN tbl_employee_wearables ew ON d.DeviceID = ew.DeviceID
    WHERE d.IsActive = 1 AND ew.IsActive = 1 AND d.DeviceType = 'iot'
")->fetchColumn();

$criticalAlerts = $conn->query("
    SELECT COUNT(*) 
    FROM tbl_biometric_alerts 
    WHERE Severity IN ('critical', 'high') 
    AND IsAcknowledged = 0 
    AND DATE(CreatedAt) = CURDATE()
")->fetchColumn();

$avgStressToday = $conn->query("
    SELECT AVG(
        CASE StressLevel 
            WHEN 'low' THEN 1 
            WHEN 'moderate' THEN 2 
            WHEN 'high' THEN 3 
            WHEN 'critical' THEN 4 
        END
    ) as avg_stress
    FROM tbl_biometric_data 
    WHERE DATE(Timestamp) = CURDATE() 
    AND StressLevel IS NOT NULL
")->fetchColumn();

// Recent alerts
$recentAlerts = $conn->query("
    SELECT ba.*, e.FullName
    FROM tbl_biometric_alerts ba
    JOIN tbl_employees e ON ba.EmployeeID = e.EmployeeID
    WHERE ba.IsAcknowledged = 0
    ORDER BY ba.CreatedAt DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// High-risk employees (high stress/fatigue in last 24 hours)
$highRiskEmployees = $conn->query("
    SELECT e.FullName, e.EmployeeID,
           MAX(CASE WHEN bd.StressLevel = 'critical' THEN 4 
                   WHEN bd.StressLevel = 'high' THEN 3 
                   WHEN bd.StressLevel = 'moderate' THEN 2 
                   WHEN bd.StressLevel = 'low' THEN 1 END) as max_stress,
           MAX(CASE WHEN bd.FatigueLevel = 'severe' THEN 4 
                   WHEN bd.FatigueLevel = 'moderate' THEN 3 
                   WHEN bd.FatigueLevel = 'mild' THEN 2 
                   WHEN bd.FatigueLevel = 'rested' THEN 1 END) as max_fatigue
    FROM tbl_employees e
    JOIN tbl_biometric_data bd ON e.EmployeeID = bd.EmployeeID
    WHERE bd.Timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY e.EmployeeID
    HAVING max_stress >= 3 OR max_fatigue >= 3
    ORDER BY GREATEST(max_stress, max_fatigue) DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get employees for dropdown
$employees = $conn->query("
    SELECT e.EmployeeID, e.FullName 
    FROM tbl_employees e
    JOIN tbl_employee_wearables ew ON e.EmployeeID = ew.EmployeeID
    WHERE ew.IsActive = 1
    ORDER BY e.FullName
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Wellness Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .wellness-card { 
            border-left: 4px solid; 
            transition: transform 0.2s;
        }
        .wellness-card:hover { transform: translateY(-2px); }
        .stress-low { border-left-color: #28a745; }
        .stress-moderate { border-left-color: #ffc107; }
        .stress-high { border-left-color: #fd7e14; }
        .stress-critical { border-left-color: #dc3545; }
        .fatigue-rested { border-left-color: #20c997; }
        .fatigue-mild { border-left-color: #6f42c1; }
        .fatigue-moderate { border-left-color: #e83e8c; }
        .fatigue-severe { border-left-color: #dc3545; }
        .metric-icon { font-size: 2.5rem; }
        .alert-badge { 
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .chart-container { 
            position: relative; 
            height: 300px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-heart-pulse text-danger me-2"></i>Employee Wellness Dashboard</h2>
                <p class="text-muted mb-0">Real-time stress and fatigue monitoring via IoT wearables</p>
            </div>
            <div>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
                <a href="wearable_assignments.php" class="btn btn-outline-info me-2">
                    <i class="bi bi-smartwatch"></i> Manage Assignments
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignWearableModal">
                    <i class="bi bi-plus-lg"></i> Assign Wearable
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card wellness-card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="bi bi-smartwatch metric-icon text-primary me-3"></i>
                        <div>
                            <p class="mb-0 text-muted">Active Wearables</p>
                            <h3 class="mb-0"><?= $totalWearableUsers ?></h3>
                            <small class="text-success">
                                <i class="bi bi-check-circle"></i> <?= $activeDevices ?> devices online
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card wellness-card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle metric-icon text-warning me-3"></i>
                        <div>
                            <p class="mb-0 text-muted">Critical Alerts</p>
                            <h3 class="mb-0 <?= $criticalAlerts > 0 ? 'text-danger alert-badge' : '' ?>">
                                <?= $criticalAlerts ?>
                            </h3>
                            <small class="text-muted">Today</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card wellness-card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="bi bi-emoji-frown metric-icon text-info me-3"></i>
                        <div>
                            <p class="mb-0 text-muted">Avg Stress Level</p>
                            <h3 class="mb-0">
                                <?php 
                                if ($avgStressToday) {
                                    echo number_format($avgStressToday, 1) . '/4';
                                    $stressClass = $avgStressToday >= 3 ? 'text-danger' : ($avgStressToday >= 2 ? 'text-warning' : 'text-success');
                                    echo " <span class='$stressClass'>●</span>";
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </h3>
                            <small class="text-muted">Today</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card wellness-card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="bi bi-shield-check metric-icon text-success me-3"></i>
                        <div>
                            <p class="mb-0 text-muted">Wellness Score</p>
                            <h3 class="mb-0" id="overallWellnessScore">--</h3>
                            <small class="text-muted">Organization</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Main Analytics -->
            <div class="col-lg-8">
                <!-- Charts Row -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Stress Levels Today</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="stressChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-battery me-2"></i>Fatigue Levels Today</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="fatigueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Individual Employee Monitor -->
                <div class="card shadow-sm border-0">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="mb-0"><i class="bi bi-person-heart me-2"></i>Individual Employee Monitor</h6>
                            </div>
                            <div class="col-auto">
                                <select id="employeeSelect" class="form-select form-select-sm" onchange="loadEmployeeData()">
                                    <option value="">Select Employee...</option>
                                    <?php foreach($employees as $emp): ?>
                                    <option value="<?= $emp['EmployeeID'] ?>"><?= htmlspecialchars($emp['FullName']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="employeeData" class="text-center text-muted py-4">
                            <i class="bi bi-person-plus display-4"></i>
                            <p class="mt-3 mb-0">Select an employee to view their wellness metrics</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- High-Risk Employees -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-danger text-white">
                        <h6 class="mb-0"><i class="bi bi-exclamation-octagon me-2"></i>High-Risk Employees</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($highRiskEmployees)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-check-circle display-6 text-success"></i>
                                <p class="mt-2 mb-0">No high-risk employees detected</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($highRiskEmployees as $emp): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($emp['FullName']) ?></h6>
                                        <div>
                                            <?php if ($emp['max_stress'] >= 3): ?>
                                                <span class="badge bg-danger">Stress: <?= $emp['max_stress'] ?>/4</span>
                                            <?php endif; ?>
                                            <?php if ($emp['max_fatigue'] >= 3): ?>
                                                <span class="badge bg-warning">Fatigue: <?= $emp['max_fatigue'] ?>/4</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">Last 24 hours</small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Alerts -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="bi bi-bell me-2"></i>Recent Alerts</h6>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($recentAlerts)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-check-all display-6 text-success"></i>
                                <p class="mt-2 mb-0">No pending alerts</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($recentAlerts as $alert): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($alert['FullName']) ?></h6>
                                        <small><?= date('M j, H:i', strtotime($alert['CreatedAt'])) ?></small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($alert['AlertMessage']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-<?= $alert['Severity'] === 'critical' ? 'danger' : ($alert['Severity'] === 'high' ? 'warning' : 'info') ?>">
                                            <?= ucfirst($alert['Severity']) ?> <?= ucfirst($alert['AlertType']) ?>
                                        </span>
                                        <button class="btn btn-sm btn-outline-primary" onclick="acknowledgeAlert(<?= $alert['AlertID'] ?>)">
                                            Acknowledge
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Wearable Modal -->
    <div class="modal fade" id="assignWearableModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Wearable Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="assignWearableForm">
                        <div class="mb-3">
                            <label for="modalEmployeeSelect" class="form-label">Employee</label>
                            <select id="modalEmployeeSelect" name="employee_id" class="form-select" required>
                                <option value="">Select Employee...</option>
                                <?php 
                                $allEmployees = $conn->query("SELECT EmployeeID, FullName FROM tbl_employees ORDER BY FullName")->fetchAll(PDO::FETCH_ASSOC);
                                foreach($allEmployees as $emp): 
                                ?>
                                <option value="<?= $emp['EmployeeID'] ?>"><?= htmlspecialchars($emp['FullName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="modalDeviceSelect" class="form-label">Wearable Device</label>
                            <select id="modalDeviceSelect" name="device_id" class="form-select" required>
                                <option value="">Select Device...</option>
                                <?php 
                                $iotDevices = $conn->query("SELECT DeviceID, DeviceName, Identifier FROM tbl_devices WHERE DeviceType = 'iot' AND IsActive = 1 ORDER BY DeviceName")->fetchAll(PDO::FETCH_ASSOC);
                                foreach($iotDevices as $device): 
                                ?>
                                <option value="<?= $device['DeviceID'] ?>"><?= htmlspecialchars($device['DeviceName']) ?> (<?= $device['Identifier'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="assignWearable()">Assign Device</button>
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

    // Initialize charts
    let stressChart, fatigueChart;

    $(document).ready(function() {
        initializeCharts();
        loadDashboardData();
        
        // Auto-refresh every 30 seconds
        setInterval(loadDashboardData, 30000);
    });

    function initializeCharts() {
        // Stress levels chart
        const stressCtx = document.getElementById('stressChart').getContext('2d');
        stressChart = new Chart(stressCtx, {
            type: 'doughnut',
            data: {
                labels: ['Low', 'Moderate', 'High', 'Critical'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Fatigue levels chart
        const fatigueCtx = document.getElementById('fatigueChart').getContext('2d');
        fatigueChart = new Chart(fatigueCtx, {
            type: 'doughnut',
            data: {
                labels: ['Rested', 'Mild', 'Moderate', 'Severe'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: ['#20c997', '#6f42c1', '#e83e8c', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    function loadDashboardData() {
        // Load stress distribution
        $.ajax({
            url: 'biometric_api.php',
            method: 'GET',
            data: { 
                action: 'stress_distribution',
                date: '<?= date('Y-m-d') ?>'
            },
            success: function(response) {
                if (response.success && response.stress_data) {
                    stressChart.data.datasets[0].data = [
                        response.stress_data.low || 0,
                        response.stress_data.moderate || 0,
                        response.stress_data.high || 0,
                        response.stress_data.critical || 0
                    ];
                    stressChart.update();
                }
                
                if (response.success && response.fatigue_data) {
                    fatigueChart.data.datasets[0].data = [
                        response.fatigue_data.rested || 0,
                        response.fatigue_data.mild || 0,
                        response.fatigue_data.moderate || 0,
                        response.fatigue_data.severe || 0
                    ];
                    fatigueChart.update();
                }
                
                // Update wellness score
                if (response.wellness_score) {
                    $('#overallWellnessScore').text(response.wellness_score + '/100');
                }
            },
            error: function() {
                console.log('Failed to load dashboard data');
            }
        });
    }

    function loadEmployeeData() {
        const employeeId = $('#employeeSelect').val();
        if (!employeeId) {
            $('#employeeData').html(`
                <div class="text-center text-muted py-4">
                    <i class="bi bi-person-plus display-4"></i>
                    <p class="mt-3 mb-0">Select an employee to view their wellness metrics</p>
                </div>
            `);
            return;
        }

        $('#employeeData').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        
        $.ajax({
            url: 'biometric_api.php',
            method: 'GET',
            data: { 
                employee_id: employeeId,
                start_date: '<?= date('Y-m-d', strtotime('-7 days')) ?>',
                end_date: '<?= date('Y-m-d') ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayEmployeeData(response.data, response.alerts);
                } else {
                    $('#employeeData').html('<div class="alert alert-warning">No data found for this employee</div>');
                }
            },
            error: function() {
                $('#employeeData').html('<div class="alert alert-danger">Failed to load employee data</div>');
            }
        });
    }

    function displayEmployeeData(data, alerts) {
        let html = '<div class="row g-3">';
        
        // Latest metrics
        if (data.length > 0) {
            const latest = data[0];
            html += `
                <div class="col-md-6">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Current Stress Level</h6>
                            <h3 class="text-${getStressColor(latest.StressLevel)}">${latest.StressLevel || 'N/A'}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Current Fatigue Level</h6>
                            <h3 class="text-${getFatigueColor(latest.FatigueLevel)}">${latest.FatigueLevel || 'N/A'}</h3>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Recent alerts
        if (alerts.length > 0) {
            html += `
                <div class="col-12">
                    <h6 class="mt-3">Recent Alerts</h6>
                    <div class="list-group list-group-flush">
            `;
            alerts.slice(0, 3).forEach(alert => {
                html += `
                    <div class="list-group-item d-flex justify-content-between">
                        <div>
                            <strong>${alert.AlertMessage}</strong><br>
                            <small class="text-muted">${new Date(alert.CreatedAt).toLocaleString()}</small>
                        </div>
                        <span class="badge bg-${alert.Severity === 'critical' ? 'danger' : 'warning'}">${alert.Severity}</span>
                    </div>
                `;
            });
            html += '</div></div>';
        }
        
        html += '</div>';
        $('#employeeData').html(html);
    }

    function getStressColor(level) {
        switch(level) {
            case 'low': return 'success';
            case 'moderate': return 'warning';
            case 'high': return 'orange';
            case 'critical': return 'danger';
            default: return 'secondary';
        }
    }

    function getFatigueColor(level) {
        switch(level) {
            case 'rested': return 'success';
            case 'mild': return 'info';
            case 'moderate': return 'warning';
            case 'severe': return 'danger';
            default: return 'secondary';
        }
    }

    function acknowledgeAlert(alertId) {
        $.ajax({
            url: 'biometric_api.php',
            method: 'POST',
            data: {
                action: 'acknowledge_alert',
                alert_id: alertId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to acknowledge alert');
                }
            },
            error: function() {
                alert('Failed to acknowledge alert');
            }
        });
    }

    function assignWearable() {
        const formData = {
            employee_id: $('#modalEmployeeSelect').val(),
            device_id: $('#modalDeviceSelect').val()
        };

        if (!formData.employee_id || !formData.device_id) {
            alert('Please select both employee and device');
            return;
        }

        $.ajax({
            url: 'biometric_api.php',
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('assignWearableModal')).hide();
                    location.reload();
                } else {
                    alert('Failed to assign wearable: ' + response.error);
                }
            },
            error: function() {
                alert('Failed to assign wearable device');
            }
        });
    }
    </script>
</body>
</html>
