<?php
// filepath: c:\laragon\www\attendance_register\admin_dashboard.php
session_start();
include 'db.php';

// Ensure user is logged in and is an admin/hr
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    header('Location: login.php');
    exit;
}

// --- DASHBOARD WIDGET QUERIES ---
$today = date('Y-m-d');

// KPI: Total Employees
$totalEmployees = $conn->query("SELECT COUNT(*) FROM tbl_employees")->fetchColumn();

// KPI: Present Today
$presentToday = $conn->prepare("SELECT COUNT(DISTINCT EmployeeID) FROM tbl_attendance WHERE AttendanceDate = ?");
$presentToday->execute([$today]);
$presentTodayCount = $presentToday->fetchColumn();

// KPI: Late Today
$lateToday = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance WHERE AttendanceDate = ? AND ClockInStatus = 'Late'");
$lateToday->execute([$today]);
$lateTodayCount = $lateToday->fetchColumn();

// KPI: Pending Requests
$pendingRequests = $conn->query("
    SELECT (SELECT COUNT(*) FROM tbl_leave_requests WHERE status = 'pending') + 
           (SELECT COUNT(*) FROM tbl_correction_requests WHERE status = 'pending')
")->fetchColumn();

// Leaderboard: Top 5 Attendance Streaks
$topStreaks = $conn->query("
    SELECT e.FullName, g.streak 
    FROM tbl_gamification g 
    JOIN tbl_employees e ON g.EmployeeID = e.EmployeeID 
    ORDER BY g.streak DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Recent Activity Feed (Leaves & Corrections)
$recentActivity = $conn->query("
    (SELECT EmployeeID, 'leave' as type, start_date as date, status, created_at FROM tbl_leave_requests)
    UNION ALL
    (SELECT EmployeeID, 'correction' as type, date, status, created_at FROM tbl_correction_requests)
    ORDER BY created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch employee names for the activity feed
$employeeIds = array_unique(array_column($recentActivity, 'EmployeeID'));
$employeeNames = [];
if ($employeeIds) {
    $in = str_repeat('?,', count($employeeIds) - 1) . '?';
    $stmt = $conn->prepare("SELECT EmployeeID, FullName FROM tbl_employees WHERE EmployeeID IN ($in)");
    $stmt->execute($employeeIds);
    $employeeNames = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Get branches for the filter dropdown
$branches = $conn->query("SELECT BranchID, BranchName FROM tbl_branches ORDER BY BranchName")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .sidebar { height: 100vh; width: 260px; position: fixed; top: 0; left: 0; background: #fff; box-shadow: 0 0 2rem 0 rgba(0,0,0,.05); transition: all .3s; z-index: 1020; }
        .sidebar .nav-link { color: #5a6e88; font-weight: 500; }
        .sidebar .nav-link:hover { color: #0d6efd; }
        .sidebar .nav-link.active { color: #0d6efd; background: #eef5ff; border-radius: .375rem; }
        .sidebar .nav-link i { min-width: 2rem; font-size: 1.1rem; }
        .sidebar.collapsed { width: 80px; }
        .sidebar.collapsed .nav-link span, .sidebar.collapsed .sidebar-heading { display: none; }
        .sidebar-heading { font-size: .8rem; text-transform: uppercase; color: #a0aec0; }
        .content { margin-left: 260px; transition: all .3s; }
        .collapsed + .content { margin-left: 80px; }
        .toggle-btn { background: none; border: none; font-size: 1.5rem; color: #5a6e88; }
        .kpi-card i { font-size: 2.5rem; }
        .kpi-card .display-4 { font-weight: 700; }
    </style>
</head>
<body>
    <div class="sidebar p-3" id="sidebar">
        <div class="d-flex justify-content-between align-items-center">
            <a href="#" class="text-decoration-none">
                <h4 class="sidebar-heading m-0"><span>Admin Panel</span></h4>
            </a>
            <button class="toggle-btn" id="sidebar-toggle"><i class="bi bi-list"></i></button>
        </div>
        <hr>
        <ul class="nav flex-column">
            <li class="nav-item mb-2"><a href="#" class="nav-link active"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></li>
            <li class="nav-item mb-2">
                <a class="nav-link" data-bs-toggle="collapse" href="#empMenu"><i class="bi bi-people-fill"></i><span>Employees</span></a>
                <div class="collapse ps-4" id="empMenu">
                    <a href="add_employee.php" class="nav-link">Add Employee</a>
                    <a href="view_employees.php" class="nav-link">View All</a>
                </div>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link" data-bs-toggle="collapse" href="#shiftsMenu"><i class="bi bi-clock-history"></i><span>Shifts</span></a>
                <div class="collapse ps-4" id="shiftsMenu">
                    <a href="manage_shifts.php" class="nav-link">Manage Shifts</a>
                    <a href="add_shift.php" class="nav-link">Add Shift</a>
                </div>
            </li>
            <li class="nav-item mb-2"><a href="employee_rank.php" class="nav-link"><i class="bi bi-award-fill"></i><span>Ranks & Leave</span></a></li>
            <li class="nav-item mb-2"><a href="leave_types.php" class="nav-link"><i class="bi bi-card-list"></i><span>Leave Types</span></a></li>
            <li class="nav-item mb-2"><a href="add_branch.php" class="nav-link"><i class="bi bi-geo-alt-fill"></i><span>Branches</span></a></li>
            <li class="nav-item mb-2">
                <a class="nav-link" data-bs-toggle="collapse" href="#deviceMenu"><i class="bi bi-router"></i><span>Device Management</span></a>
                <div class="collapse ps-4" id="deviceMenu">
                    <a href="device_dashboard.php" class="nav-link">Device Dashboard</a>
                    <a href="device_registry.php" class="nav-link">Register Device</a>
                </div>
            </li>
            <li class="nav-item mb-2"></li><a href="#indoorTab" onclick="showIndoorPresence()" class="nav-link"><i class="bi bi-broadcast"></i><span>Indoor Presence</span></a></li>
            <li class="nav-item mb-2"><a href="attendance_map.php" class="nav-link"><i class="bi bi-map-fill"></i><span>Attendance Map</span></a></li>
            <li class="nav-item mb-2"><a href="reports.php" class="nav-link"><i class="bi bi-file-earmark-text-fill"></i><span>Reports</span></a></li>
            <li class="nav-item mb-2"><a href="admin_requests.php" class="nav-link"><i class="bi bi-bell-fill"></i><span>Requests</span></a></li>
            <li class="nav-item mt-auto"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-left"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <main class="content p-4">
        <div class="container-fluid">
            <h2 class="mb-4">Dashboard</h2>
            <!-- KPI Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card kpi-card shadow-sm border-0 h-100">
                        <div class="card-body d-flex align-items-center">
                            <i class="bi bi-people text-primary me-3"></i>
                            <div>
                                <p class="mb-0 text-muted">Total Employees</p>
                                <p class="display-4 mb-0"><?= $totalEmployees ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card kpi-card shadow-sm border-0 h-100">
                        <div class="card-body d-flex align-items-center">
                            <i class="bi bi-person-check text-success me-3"></i>
                            <div>
                                <p class="mb-0 text-muted">Present Today</p>
                                <p class="display-4 mb-0"><?= $presentTodayCount ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card kpi-card shadow-sm border-0 h-100">
                        <div class="card-body d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle text-warning me-3"></i>
                            <div>
                                <p class="mb-0 text-muted">Late Today</p>
                                <p class="display-4 mb-0"><?= $lateTodayCount ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card kpi-card shadow-sm border-0 h-100">
                        <div class="card-body d-flex align-items-center">
                            <i class="bi bi-envelope-paper text-danger me-3"></i>
                            <div>
                                <p class="mb-0 text-muted">Pending Requests</p>
                                <p class="display-4 mb-0"><?= $pendingRequests ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Main Column -->
                <div class="col-lg-8">
                    <!-- Attendance Records Link -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body d-flex flex-column flex-md-row align-items-center justify-content-between">
                            <div>
                                <h5 class="mb-1">Attendance Records</h5>
                                <p class="mb-0 text-muted small">View and export all attendance data in the Reports section.</p>
                            </div>
                            <a href="reports.php" class="btn btn-outline-primary mt-3 mt-md-0">
                                <i class="bi bi-file-earmark-text"></i> Go to Reports
                            </a>
                        </div>
                    </div>
                    <!-- Recent Activity -->
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Recent Activity</h5>
                            <ul class="list-group list-group-flush">
                                <?php foreach($recentActivity as $activity): ?>
                                <li class="list-group-item px-0">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($employeeNames[$activity['EmployeeID']] ?? 'Unknown') ?></h6>
                                        <small class="text-muted"><?= date('M j', strtotime($activity['date'])) ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <?php if($activity['type'] === 'leave'): ?>
                                            <span class="badge bg-info-subtle text-info-emphasis">Leave Request</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis">Correction Request</span>
                                        <?php endif; ?>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= ucfirst($activity['status']) ?></span>
                                    </p>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="admin_requests.php" class="btn btn-link p-0 float-end mt-2">Manage All &rarr;</a>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar Column -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Quick Actions</h5>
                            <div class="d-grid gap-2">
                                <a href="add_employee.php" class="btn btn-primary">Add Employee</a>
                                <a href="add_branch.php" class="btn btn-secondary">Add Branch</a>
                            </div>
                        </div>
                    </div>
                    <!-- Leaderboard -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Top Attendance Streaks 🔥</h5>
                            <ul class="list-group list-group-flush">
                                <?php foreach($topStreaks as $row): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <?= htmlspecialchars($row['FullName']) ?>
                                    <span class="badge bg-primary rounded-pill"><?= $row['streak'] ?> days</span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Indoor Presence Management (initially hidden) -->
            <div id="indoorPresenceSection" class="row g-4" style="display: none;">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-broadcast me-2"></i>Indoor Presence Management</h5>
                                <a href="device_dashboard.php" class="btn btn-light btn-sm">
                                    <i class="bi bi-grid-3x3-gap me-1"></i>Device Dashboard
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="branchSelect" class="form-label">Select Branch:</label>
                                    <select id="branchSelect" class="form-select" onchange="loadIndoorData()">
                                        <option value="">Choose a branch...</option>
                                        <?php foreach($branches as $branch): ?>
                                        <option value="<?= $branch['BranchID'] ?>"><?= htmlspecialchars($branch['BranchName']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="indoorData" class="mt-4" style="display: none;">
                                <div class="row">
                                    <!-- BLE Beacons -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0"><i class="bi bi-bluetooth me-2"></i>BLE Beacons</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="input-group mb-3">
                                                    <input type="text" id="newBeaconMAC" class="form-control" placeholder="MAC Address (e.g., AA:BB:CC:DD:EE:FF)">
                                                    <input type="text" id="newBeaconLabel" class="form-control" placeholder="Label (optional)">
                                                    <button class="btn btn-primary" onclick="addBeacon()">Add</button>
                                                </div>
                                                <div id="beaconsList" class="list-group"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Wi-Fi APs -->
                                    <div class="col-md-6">
                                        <div class="card h-100">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0"><i class="bi bi-wifi me-2"></i>Wi-Fi Access Points</h6>
                                            </div>
                                            <div class="card-body">
                                                <div class="input-group mb-3">
                                                    <input type="text" id="newWifiBSSID" class="form-control" placeholder="BSSID (e.g., AA:BB:CC:DD:EE:FF)">
                                                    <input type="text" id="newWifiSSID" class="form-control" placeholder="SSID (optional)">
                                                    <button class="btn btn-primary" onclick="addWifi()">Add</button>
                                                </div>
                                                <div id="wifiList" class="list-group"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imgModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-transparent border-0"><div class="modal-body text-center p-0"><img src="" id="modalImage" class="img-fluid rounded"></div></div></div></div>

    <!-- Enhanced Chatbot Widget -->
    <button id="open-chat-btn" style="position:fixed;bottom:20px;right:20px;z-index:10000;" class="btn btn-primary rounded-circle">
        <i class="bi bi-chat-dots"></i>
    </button>
    <div id="ai-chat-widget" style="display:none;position:fixed;bottom:80px;right:20px;width:320px;background:#f8f9fa;border:2px solid #0d6efd;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,0.12);padding:16px;z-index:9999;">
        <div style="font-weight:bold;font-size:16px;margin-bottom:8px;color:#0d6efd;">AI Assistant</div>
        <div id="chat-log" style="height:180px;overflow-y:auto;margin-bottom:12px;font-size:15px;"></div>
        <input type="text" id="chat-input" class="form-control mb-2" placeholder="Type your question..." />
        <button onclick="sendAIChat()" class="btn btn-primary w-100">Send</button>
    </div>
    <script>
    document.getElementById('open-chat-btn').onclick = function() {
        var widget = document.getElementById('ai-chat-widget');
        widget.style.display = (widget.style.display === 'none' ? 'block' : 'none');
        document.getElementById('chat-log').innerHTML = '<div><b>AI:</b> Hello! How can I assist you today?</div>';
    };

    let chatHistory = [
        {role: "system", content: "You are an HR assistant for the Attendance Register system. Employees must clock in by 8:00 AM. Late arrivals are marked after 8:15 AM. Leave requests must be approved by HR. The system tracks attendance, latecomers, and pending requests. Use this information to answer questions about attendance, leave, and employee management."}
    ];

    function sendAIChat() {
        var input = document.getElementById('chat-input');
        var log = document.getElementById('chat-log');
        var question = input.value;
        if (!question) return;
        log.innerHTML += '<div><b>You:</b> ' + question + '</div>';
        log.innerHTML += '<div id="typing-indicator"><b>AI:</b> <em>Typing...</em></div>';
        input.value = '';

        // Add user message to history
        chatHistory.push({role: "user", content: question});

        // Action trigger: check for keywords
        if (question.toLowerCase().includes("show today's attendance") || question.toLowerCase().includes("attendance today")) {
            // Fetch today's attendance from PHP
            fetch('chat_action.php?action=today_attendance')
            .then(res => res.json())
            .then(data => {
                // Add attendance data to chatHistory as system message
                chatHistory.push({role: "system", content: "Today's attendance summary: " + data.summary});
                sendToAI(chatHistory, log);
            });
        } else {
            sendToAI(chatHistory, log);
        }
    }

    // Add this event listener
    document.getElementById('chat-input').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // Prevents default action (like form submission)
            sendAIChat();
        }
    });

    function sendToAI(history, log) {
        fetch('ai_chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({messages: history})
        })
        .then(res => res.json())
        .then(data => {
            if (!data.response) {
                log.innerHTML += '<div><b>AI:</b> Sorry, I could not process your request.</div>';
                document.getElementById('typing-indicator').remove();
                return;
            }
            var answer = data.response;
            document.getElementById('typing-indicator').remove();
            log.innerHTML += '<div><b>AI:</b> ' + answer + '</div>';
            log.scrollTop = log.scrollHeight;
            chatHistory.push({role: "assistant", content: answer});
        })
        .catch(err => {
            document.getElementById('typing-indicator').remove();
            log.innerHTML += '<div><b>AI:</b> Error connecting to AI server.</div>';
        });
    }
    </script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        loadDashboardData();

        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            loadDashboardData();
        });

        $('#sidebar-toggle').on('click', function() {
            $('#sidebar, .content').toggleClass('collapsed');
        });

        $(document).on('click', '.zoomable', function() {
            $('#modalImage').attr('src', $(this).attr('src'));
            new bootstrap.Modal(document.getElementById('imgModal')).show();
        });

        function loadDashboardData() {
            $('#tableData').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
            $.ajax({
                url: 'fetch_data.php',
                type: 'GET',
                data: $('#filterForm').serialize(),
                dataType: 'json',
                success: function(data) {
                    $('#tableData').html(data.table);
                },
                error: function() {
                    $('#tableData').html('<p class="text-danger text-center">Failed to load data. Please try again.</p>');
                }
            });
        }
    });
    
    // Indoor Presence Management Functions
    function showIndoorPresence() {
        // Hide dashboard, show indoor presence
        $('.row.g-4').not('#indoorPresenceSection').hide();
        $('#indoorPresenceSection').show();
        $('h2').text('Indoor Presence Management');
        
        // Update nav active state
        $('.nav-link').removeClass('active');
        $('[onclick="showIndoorPresence()"]').addClass('active');
    }
    
    function showDashboard() {
        // Show dashboard, hide indoor presence
        $('.row.g-4').not('#indoorPresenceSection').show();
        $('#indoorPresenceSection').hide();
        $('h2').text('Dashboard');
        
        // Update nav active state
        $('.nav-link').removeClass('active');
        $('.nav-link').first().addClass('active');
    }
    
    function loadIndoorData() {
        const branchId = $('#branchSelect').val();
        if (!branchId) {
            $('#indoorData').hide();
            return;
        }
        
        $('#indoorData').show();
        
        // Load beacons and wifi for this branch
        $.ajax({
            url: 'indoor_whitelist_api.php',
            type: 'GET',
            data: { branch_id: branchId },
            dataType: 'json',
            success: function(data) {
                updateBeaconsList(data.beacons || []);
                updateWifiList(data.wifi || []);
            },
            error: function() {
                alert('Failed to load indoor presence data');
            }
        });
    }
    
    function updateBeaconsList(beacons) {
        let html = '';
        beacons.forEach(function(beacon) {
            html += `<div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${beacon.MAC}</strong>
                    ${beacon.Label ? `<br><small class="text-muted">${beacon.Label}</small>` : ''}
                </div>
                <button class="btn btn-sm btn-outline-danger" onclick="removeBeacon(${beacon.BeaconID})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`;
        });
        $('#beaconsList').html(html || '<div class="text-muted text-center p-3">No beacons configured</div>');
    }
    
    function updateWifiList(wifiAPs) {
        let html = '';
        wifiAPs.forEach(function(ap) {
            html += `<div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${ap.BSSID}</strong>
                    ${ap.SSID ? `<br><small class="text-muted">${ap.SSID}</small>` : ''}
                </div>
                <button class="btn btn-sm btn-outline-danger" onclick="removeWifi(${ap.WifiID})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`;
        });
        $('#wifiList').html(html || '<div class="text-muted text-center p-3">No Wi-Fi APs configured</div>');
    }
    
    function addBeacon() {
        const branchId = $('#branchSelect').val();
        const mac = $('#newBeaconMAC').val().trim().toUpperCase();
        const label = $('#newBeaconLabel').val().trim();
        
        if (!branchId || !mac) {
            alert('Please select a branch and enter a MAC address');
            return;
        }
        
        // Basic MAC validation
        if (!/^[A-F0-9]{2}(:[A-F0-9]{2}){5}$/.test(mac) && !/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}$/.test(mac)) {
            alert('Please enter a valid MAC address or UUID');
            return;
        }
        
        $.ajax({
            url: 'indoor_whitelist_api.php',
            type: 'POST',
            data: { 
                action: 'add_beacon',
                branch_id: branchId,
                mac: mac,
                label: label
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#newBeaconMAC, #newBeaconLabel').val('');
                    loadIndoorData();
                } else {
                    alert(data.error || 'Failed to add beacon');
                }
            },
            error: function() {
                alert('Failed to add beacon');
            }
        });
    }
    
    function addWifi() {
        const branchId = $('#branchSelect').val();
        const bssid = $('#newWifiBSSID').val().trim().toUpperCase();
        const ssid = $('#newWifiSSID').val().trim();
        
        if (!branchId || !bssid) {
            alert('Please select a branch and enter a BSSID');
            return;
        }
        
        // Basic BSSID validation
        if (!/^[A-F0-9]{2}(:[A-F0-9]{2}){5}$/.test(bssid)) {
            alert('Please enter a valid BSSID (MAC address format)');
            return;
        }
        
        $.ajax({
            url: 'indoor_whitelist_api.php',
            type: 'POST',
            data: { 
                action: 'add_wifi',
                branch_id: branchId,
                bssid: bssid,
                ssid: ssid
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    $('#newWifiBSSID, #newWifiSSID').val('');
                    loadIndoorData();
                } else {
                    alert(data.error || 'Failed to add Wi-Fi AP');
                }
            },
            error: function() {
                alert('Failed to add Wi-Fi AP');
            }
        });
    }
    
    function removeBeacon(beaconId) {
        if (confirm('Are you sure you want to remove this beacon?')) {
            $.ajax({
                url: 'indoor_whitelist_api.php',
                type: 'POST',
                data: { 
                    action: 'remove_beacon',
                    beacon_id: beaconId
                },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        loadIndoorData();
                    } else {
                        alert(data.error || 'Failed to remove beacon');
                    }
                },
                error: function() {
                    alert('Failed to remove beacon');
                }
            });
        }
    }
    
    function removeWifi(wifiId) {
        if (confirm('Are you sure you want to remove this Wi-Fi AP?')) {
            $.ajax({
                url: 'indoor_whitelist_api.php',
                type: 'POST',
                data: { 
                    action: 'remove_wifi',
                    wifi_id: wifiId
                },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        loadIndoorData();
                    } else {
                        alert(data.error || 'Failed to remove Wi-Fi AP');
                    }
                },
                error: function() {
                    alert('Failed to remove Wi-Fi AP');
                }
            });
        }
    }
    </script>
</body>
</html>