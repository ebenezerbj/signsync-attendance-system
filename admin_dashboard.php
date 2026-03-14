<?php
// filepath: c:\laragon\www\attendance_register\admin_dashboard.php
session_start();
include 'db.php';
include_once 'AttendanceManager.php';
include_once 'LocationVerificationManager.php';

// Ensure user is logged in and is an admin/hr
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    header('Location: login.php');
    exit;
}

// Initialize managers
$attendanceManager = new AttendanceManager($conn);
$locationManager = new LocationVerificationManager($conn);

// --- ENHANCED DASHBOARD WIDGET QUERIES ---
$today = date('Y-m-d');
$currentTime = date('Y-m-d H:i:s');

// KPI: Total Employees (no Status column in tbl_employees; count all employees)
$totalEmployees = $conn->query("SELECT COUNT(*) FROM tbl_employees")->fetchColumn();

// KPI: Present Today (from clockinout table for real-time data)
$presentToday = $conn->prepare("
    SELECT COUNT(DISTINCT EmployeeID) 
    FROM clockinout 
    WHERE DATE(ClockIn) = ? AND ClockIn IS NOT NULL AND ClockOut IS NULL
");
$presentToday->execute([$today]);
$presentTodayCount = $presentToday->fetchColumn();

// KPI: Late Today (enhanced with clock in status)
$lateToday = $conn->prepare("
    SELECT COUNT(*) 
    FROM tbl_attendance 
    WHERE AttendanceDate = ? AND (ClockInStatus = 'Late' OR Status LIKE '%Late%')
");
$lateToday->execute([$today]);
$lateTodayCount = $lateToday->fetchColumn();

// KPI: Active Devices (from existing device tables)
// Count active tbl_devices seen in last 5 minutes + registered WearOS devices synced in last 5 minutes
$activeDevices = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM tbl_devices 
         WHERE IsActive = 1 AND LastSeenAt >= DATE_SUB(NOW(), INTERVAL 5 MINUTE))
      + (SELECT COUNT(*) FROM wearos_devices 
         WHERE is_registered = 1 AND last_sync >= DATE_SUB(NOW(), INTERVAL 5 MINUTE))
    AS total
")->fetchColumn() ?: 0;

// KPI: Location Violations Today (from location_verification_history)
$locationViolations = $conn->prepare("
    SELECT COUNT(*) FROM location_verification_history 
    WHERE DATE(`timestamp`) = ? AND verification_score < 60
");
$locationViolations->execute([$today]);
$locationViolationsCount = $locationViolations->fetchColumn();

// KPI: Pending Requests (enhanced)
// Device approvals mapped to: inactive devices in tbl_devices + unregistered wearos_devices
$pendingRequests = $conn->query("
    SELECT (
        (SELECT COUNT(*) FROM tbl_leave_requests WHERE status = 'pending') + 
        (SELECT COUNT(*) FROM tbl_correction_requests WHERE status = 'pending') +
        (SELECT COUNT(*) FROM tbl_devices WHERE IsActive = 0) +
        (SELECT COUNT(*) FROM wearos_devices WHERE is_registered = 0)
    ) as total
")->fetchColumn();

// Enhanced Leaderboard: Top 5 Attendance Streaks with Points
$topStreaks = $conn->query("
    SELECT e.FullName, g.streak, g.points, g.level,
           CASE 
               WHEN g.points >= 1000 THEN 'Gold'
               WHEN g.points >= 500 THEN 'Silver'
               WHEN g.points >= 200 THEN 'Bronze'
               ELSE 'Standard'
           END as badge_level
    FROM tbl_gamification g 
    JOIN tbl_employees e ON g.EmployeeID = e.EmployeeID 
    ORDER BY g.points DESC, g.streak DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Real-time Activity Feed (Enhanced)
$recentActivity = $conn->query("
    (SELECT e.FullName, 'clock_in' as type, c.ClockIn as timestamp, 
            CASE WHEN a.ClockInStatus = 'Late' THEN 'warning' ELSE 'success' END as status_class,
            'Clock In' as description
     FROM clockinout c 
     JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID 
     LEFT JOIN tbl_attendance a ON c.EmployeeID = a.EmployeeID AND DATE(c.ClockIn) = a.AttendanceDate
     WHERE DATE(c.ClockIn) = CURDATE() AND c.ClockIn IS NOT NULL)
    UNION ALL
    (SELECT e.FullName, 'clock_out' as type, c.ClockOut as timestamp, 'info' as status_class,
            'Clock Out' as description
     FROM clockinout c 
     JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID 
     WHERE DATE(c.ClockOut) = CURDATE() AND c.ClockOut IS NOT NULL)
    UNION ALL
    (SELECT e.FullName, 'leave_request' as type, lr.created_at as timestamp,
            CASE lr.status WHEN 'approved' THEN 'success' WHEN 'rejected' THEN 'danger' ELSE 'warning' END as status_class,
            CONCAT('Leave Request - ', lr.status) as description
     FROM tbl_leave_requests lr
     JOIN tbl_employees e ON lr.EmployeeID = e.EmployeeID 
     WHERE DATE(lr.created_at) = CURDATE())
    UNION ALL
    (SELECT e.FullName, 'device_registration' as type, d.CreatedAt as timestamp, 'info' as status_class,
            CONCAT('Device Registration - ', d.DeviceType) as description
     FROM tbl_devices d
     JOIN tbl_employees e ON d.CreatedBy = e.EmployeeID 
     WHERE DATE(d.CreatedAt) = CURDATE())
    ORDER BY timestamp DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Branch Performance Analytics
$branchPerformance = $conn->query("
    SELECT b.BranchName, 
           COUNT(DISTINCT e.EmployeeID) as total_employees,
           COUNT(DISTINCT CASE WHEN DATE(c.ClockIn) = CURDATE() THEN c.EmployeeID END) as present_today,
           ROUND(AVG(CASE 
               WHEN a.ClockIn IS NOT NULL AND a.ClockOut IS NOT NULL 
               THEN TIMESTAMPDIFF(HOUR, 
                   CONCAT(a.AttendanceDate, ' ', a.ClockIn), 
                   CONCAT(a.AttendanceDate, ' ', a.ClockOut)
               ) 
               ELSE NULL 
           END), 2) as avg_work_hours,
           COUNT(CASE WHEN a.ClockInStatus = 'On Time' THEN 1 END) as on_time_count
    FROM tbl_branches b
    LEFT JOIN tbl_employees e ON b.BranchID = e.BranchID
    LEFT JOIN clockinout c ON e.EmployeeID = c.EmployeeID AND DATE(c.ClockIn) = CURDATE()
    LEFT JOIN tbl_attendance a ON e.EmployeeID = a.EmployeeID AND a.AttendanceDate = CURDATE()
    GROUP BY b.BranchID, b.BranchName
    ORDER BY present_today DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Location Verification Summary
$locationStats = $conn->query("
    SELECT 
        COUNT(*) as total_verifications,
        COUNT(CASE WHEN verification_score >= 80 THEN 1 END) as high_accuracy,
        COUNT(CASE WHEN verification_score < 60 THEN 1 END) as low_accuracy,
        ROUND(AVG(verification_score), 1) as avg_score
    FROM location_verification_history 
    WHERE DATE(`timestamp`) = CURDATE()
")->fetch(PDO::FETCH_ASSOC) ?: ['total_verifications' => 0, 'high_accuracy' => 0, 'low_accuracy' => 0, 'avg_score' => 0];

// System Health Metrics
$systemHealth = [
    'database_size' => $conn->query("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) as size_mb 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ")->fetchColumn() ?: 0,
    'active_sessions' => count(glob(session_save_path() . '/sess_*')),
    'total_attendance_records' => $conn->query("SELECT COUNT(*) FROM tbl_attendance")->fetchColumn(),
    'total_clockinout_records' => $conn->query("SELECT COUNT(*) FROM clockinout")->fetchColumn()
];

// Prepare KPIs array for template usage
$kpis = [
    'total_employees' => $totalEmployees,
    'currently_clocked_in' => $presentTodayCount,
    'present_today' => $presentTodayCount,
    'late_today' => $lateTodayCount,
    'late_arrivals' => $lateTodayCount, // Alias for template consistency
    'absent_today' => max(0, $totalEmployees - $presentTodayCount),
    'leave_requests' => $conn->query("SELECT COUNT(*) FROM tbl_leave_requests WHERE status = 'pending'")->fetchColumn() ?: 0,
    'active_devices' => $activeDevices,
    'online_devices' => $activeDevices, // Same as active for now
    'location_violations' => $locationViolationsCount,
    'pending_requests' => $pendingRequests,
    'system_health' => 95 // Default system health percentage
];

// Prepare gamification leaderboard variable (rename from topStreaks for clarity)
$gamificationLeaderboard = $topStreaks;

// Get branches for filters
$branches = $conn->query("SELECT BranchID, BranchName FROM tbl_branches ORDER BY BranchName")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SignSync Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { 
            --bs-body-bg: #f8f9fa; 
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .sidebar { 
            height: 100vh; 
            width: 280px; 
            position: fixed; 
            top: 0; 
            left: 0; 
            background: var(--primary-gradient);
            box-shadow: 0 4px 6px rgba(0,0,0,.1); 
            transition: all .3s; 
            z-index: 1020; 
            overflow-y: auto;
        }
        
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.9); 
            font-weight: 500; 
            padding: 12px 20px;
            margin: 4px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover { 
            color: white; 
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active { 
            color: white; 
            background: rgba(255,255,255,0.2); 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link i { 
            min-width: 2rem; 
            font-size: 1.1rem; 
            margin-right: 10px;
        }
        
        .sidebar.collapsed { width: 80px; }
        .sidebar.collapsed .nav-link span { display: none; }
        .sidebar.collapsed .sidebar-heading { display: none; }
        
        .sidebar-heading { 
            font-size: .9rem; 
            text-transform: uppercase; 
            color: rgba(255,255,255,0.7); 
            margin: 20px 0 10px 0;
            padding: 0 20px;
        }
        
        .content { 
            margin-left: 280px; 
            transition: all .3s; 
            min-height: 100vh;
        }
        
        .collapsed + .content { margin-left: 80px; }
        
        .toggle-btn { 
            background: none; 
            border: none; 
            font-size: 1.5rem; 
            color: white;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .toggle-btn:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .kpi-card { 
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .kpi-card .card-body {
            padding: 25px;
        }
        
        .kpi-card i { 
            font-size: 3rem; 
            opacity: 0.8;
        }
        
        .kpi-card .display-4 { 
            font-weight: 700; 
            margin: 0;
        }
        
        .kpi-card.primary { background: var(--primary-gradient); color: white; }
        .kpi-card.success { background: var(--success-gradient); color: white; }
        .kpi-card.warning { background: var(--warning-gradient); color: white; }
        .kpi-card.info { background: var(--info-gradient); color: white; }
        
        .activity-feed {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            padding: 15px;
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
            background: white;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }
        
        .activity-item.success { border-left-color: #28a745; }
        .activity-item.warning { border-left-color: #ffc107; }
        .activity-item.danger { border-left-color: #dc3545; }
        .activity-item.info { border-left-color: #17a2b8; }
        
        .badge-gold { background: linear-gradient(45deg, #f7b733, #fc4a1a); }
        .badge-silver { background: linear-gradient(45deg, #c0c0c0, #8e8e8e); }
        .badge-bronze { background: linear-gradient(45deg, #cd7f32, #b8860b); }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .stats-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .refresh-btn {
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-container {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .logo-text {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0;
        }
        
        .nav-section {
            margin-bottom: 30px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="d-flex">
        <!-- Enhanced Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="logo-container">
                <h5 class="logo-text"><i class="fas fa-fingerprint me-2"></i>SignSync</h5>
                <small class="text-white-50">Attendance Management</small>
            </div>
            
            <ul class="nav flex-column">
                <div class="nav-section">
                    <div class="sidebar-heading">Dashboard</div>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <span>Overview</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_attendance_management.html">
                            <i class="bi bi-clock-history"></i>
                            <span>Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="wellness_dashboard.php">
                            <i class="bi bi-heart-pulse"></i>
                            <span>Wellness</span>
                        </a>
                    </li>
                </div>
                
                <div class="nav-section">
                    <div class="sidebar-heading">Management</div>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#employeeModal">
                            <i class="bi bi-people"></i>
                            <span>Employees</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#branchModal">
                            <i class="bi bi-building"></i>
                            <span>Branches</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#shiftModal">
                            <i class="bi bi-calendar-week"></i>
                            <span>Shifts</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#holidayModal">
                            <i class="bi bi-calendar-x"></i>
                            <span>Holidays</span>
                        </a>
                    </li>
                </div>
                
                <div class="nav-section">
                    <div class="sidebar-heading">System</div>
                    <li class="nav-item">
                        <a class="nav-link" href="device_dashboard.php">
                            <i class="bi bi-phone"></i>
                            <span>Device Monitor</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="device_registry.php">
                            <i class="bi bi-plus-circle"></i>
                            <span>Register Devices</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="wearable_assignments.php">
                            <i class="bi bi-smartwatch"></i>
                            <span>Device Assignments</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="location_verification_admin.php">
                            <i class="bi bi-geo-alt"></i>
                            <span>Location</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_pin_management.html">
                            <i class="bi bi-shield-lock"></i>
                            <span>PIN Management</span>
                        </a>
                    </li>
                </div>
                
                <div class="nav-section">
                    <div class="sidebar-heading">Tools</div>
                    <li class="nav-item">
                        <a class="nav-link" href="ai_chat.php">
                            <i class="bi bi-chat-dots"></i>
                            <span>AI Assistant</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="attendance_map.php">
                            <i class="bi bi-map"></i>
                            <span>Attendance Map</span>
                        </a>
                    </li>
                </div>
            </ul>
        </nav>

        <!-- Main Content -->
        <div id="content" class="content">
            <!-- Top Bar -->
            <div class="bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button id="sidebarCollapse" class="toggle-btn me-3">
                        <i class="bi bi-list"></i>
                    </button>
                    <h4 class="mb-0 text-dark fw-bold">Dashboard Overview</h4>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-primary refresh-btn" onclick="refreshDashboard()" title="Refresh Data">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <span class="text-muted">Last updated: <span id="lastUpdate"><?= date('M d, Y H:i') ?></span></span>
                    <a href="logout.php" class="btn btn-outline-danger" title="Logout">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="container-fluid py-4">
                <!-- KPI Cards Row -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card kpi-card primary">
                            <div class="card-body d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="card-title text-white-50 mb-2">Active Employees</h6>
                                    <h2 class="display-4 mb-0"><?= $kpis['total_employees'] ?></h2>
                                    <small class="text-white-50">Currently Clocked In: <?= $kpis['currently_clocked_in'] ?></small>
                                </div>
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card kpi-card success">
                            <div class="card-body d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="card-title text-white-50 mb-2">Active Devices</h6>
                                    <h2 class="display-4 mb-0"><?= $kpis['active_devices'] ?></h2>
                                    <small class="text-white-50">Online: <?= $kpis['online_devices'] ?></small>
                                </div>
                                <i class="bi bi-phone-fill"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card kpi-card warning">
                            <div class="card-body d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="card-title text-white-50 mb-2">Location Violations</h6>
                                    <h2 class="display-4 mb-0"><?= $kpis['location_violations'] ?></h2>
                                    <small class="text-white-50">Today</small>
                                </div>
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card kpi-card info">
                            <div class="card-body d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="card-title text-white-50 mb-2">System Health</h6>
                                    <h2 class="display-4 mb-0"><?= $kpis['system_health'] ?>%</h2>
                                    <small class="text-white-50">All Systems Operational</small>
                                </div>
                                <i class="bi bi-heart-pulse-fill"></i>
                            </div>
                        </div>
                    </div>
                
                <!-- Second Row - Enhanced KPIs -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Present Today</h6>
                                        <h3 class="text-success mb-0"><?= $kpis['present_today'] ?></h3>
                                        <small class="text-muted">Attendance Rate: <?= round(($kpis['present_today'] / max($kpis['total_employees'], 1)) * 100, 1) ?>%</small>
                                    </div>
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 2.5rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Late Arrivals</h6>
                                        <h3 class="text-warning mb-0"><?= $kpis['late_arrivals'] ?></h3>
                                        <small class="text-muted">Today</small>
                                    </div>
                                    <i class="bi bi-clock-fill text-warning" style="font-size: 2.5rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Absent Today</h6>
                                        <h3 class="text-danger mb-0"><?= $kpis['absent_today'] ?></h3>
                                        <small class="text-muted">Leave Requests: <?= $kpis['leave_requests'] ?></small>
                                    </div>
                                    <i class="bi bi-x-circle-fill text-danger" style="font-size: 2.5rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Pending Requests</h6>
                                        <h3 class="text-info mb-0"><?= $kpis['pending_requests'] ?></h3>
                                        <small class="text-muted">Require Review</small>
                                    </div>
                                    <i class="bi bi-envelope-paper-fill text-info" style="font-size: 2.5rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Dashboard Grid -->
                <div class="row">
                    <!-- Left Column -->
                    <div class="col-lg-8 mb-4">
                        <!-- Real-time Activity Feed -->
                        <div class="card stats-card mb-4">
                            <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
                                <h5 class="mb-0"><i class="bi bi-activity text-primary me-2"></i>Real-time Activity</h5>
                                <span class="badge bg-primary">Live</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="activity-feed">
                                    <?php if (empty($recentActivity)): ?>
                                        <div class="text-center py-4">
                                            <i class="bi bi-clock-history text-muted" style="font-size: 3rem;"></i>
                                            <p class="text-muted mt-2">No recent activity</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recentActivity as $activity): ?>
                                            <div class="activity-item <?= $activity['status_class'] ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?= htmlspecialchars($activity['FullName']) ?></h6>
                                                        <p class="mb-0 text-muted"><?= htmlspecialchars($activity['description']) ?></p>
                                                    </div>
                                                    <small class="text-muted"><?= date('H:i', strtotime($activity['timestamp'])) ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Gamification Leaderboard -->
                        <div class="card stats-card">
                            <div class="card-header bg-transparent border-0">
                                <h5 class="mb-0"><i class="bi bi-trophy text-warning me-2"></i>Top Performers</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($gamificationLeaderboard as $index => $employee): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-center p-3 rounded <?= $index === 0 ? 'bg-warning-subtle' : ($index === 1 ? 'bg-light' : 'bg-secondary-subtle') ?>">
                                                <div class="mb-2">
                                                    <?php if ($index === 0): ?>
                                                        <span class="badge badge-gold text-white fs-6"><i class="bi bi-trophy"></i> #1</span>
                                                    <?php elseif ($index === 1): ?>
                                                        <span class="badge badge-silver text-white fs-6"><i class="bi bi-award"></i> #2</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-bronze text-white fs-6"><i class="bi bi-patch-check"></i> #3</span>
                                                    <?php endif; ?>
                                                </div>
                                                <h6 class="mb-1"><?= htmlspecialchars($employee['FullName']) ?></h6>
                                                <p class="small text-muted mb-0"><?= $employee['points'] ?> points</p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="col-lg-4 mb-4">
                        <!-- Location Verification Stats -->
                        <div class="card stats-card mb-4">
                            <div class="card-header bg-transparent border-0">
                                <h6 class="mb-0"><i class="bi bi-geo-alt text-danger me-2"></i>Location Verification</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>High Accuracy</span>
                                        <span class="text-success"><?= $locationStats['high_accuracy'] ?></span>
                                    </div>
                                    <div class="progress mb-2" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: <?= ($locationStats['total_verifications'] > 0) ? ($locationStats['high_accuracy'] / $locationStats['total_verifications']) * 100 : 0 ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Low Accuracy</span>
                                        <span class="text-warning"><?= $locationStats['low_accuracy'] ?></span>
                                    </div>
                                    <div class="progress mb-2" style="height: 8px;">
                                        <div class="progress-bar bg-warning" style="width: <?= ($locationStats['total_verifications'] > 0) ? ($locationStats['low_accuracy'] / $locationStats['total_verifications']) * 100 : 0 ?>%"></div>
                                    </div>
                                </div>
                                <hr>
                                <div class="text-center">
                                    <h4 class="text-primary"><?= $locationStats['avg_score'] ?>%</h4>
                                    <small class="text-muted">Average Accuracy Score</small>
                                </div>
                            </div>
                        </div>

                        <!-- Branch Performance Summary -->
                        <div class="card stats-card mb-4">
                            <div class="card-header bg-transparent border-0">
                                <h6 class="mb-0"><i class="bi bi-building text-info me-2"></i>Branch Performance</h6>
                            </div>
                            <div class="card-body">
                                <?php foreach (array_slice($branchPerformance, 0, 3) as $branch): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($branch['BranchName']) ?></h6>
                                            <small class="text-muted"><?= $branch['present_today'] ?>/<?= $branch['total_employees'] ?> present</small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary"><?= round(($branch['present_today'] / max($branch['total_employees'], 1)) * 100, 1) ?>%</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- System Health -->
                        <div class="card stats-card">
                            <div class="card-header bg-transparent border-0">
                                <h6 class="mb-0"><i class="bi bi-cpu text-success me-2"></i>System Status</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Database Size</small>
                                        <small><?= $systemHealth['database_size'] ?> MB</small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Active Sessions</small>
                                        <small><?= $systemHealth['active_sessions'] ?></small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Attendance Records</small>
                                        <small><?= number_format($systemHealth['total_attendance_records']) ?></small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Clock Records</small>
                                        <small><?= number_format($systemHealth['total_clockinout_records']) ?></small>
                                    </div>
                                </div>
                                <hr>
                                <div class="text-center">
                                    <span class="badge bg-success-subtle text-success-emphasis fs-6">
                                        <i class="bi bi-check-circle me-1"></i>All Systems Operational
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    // Enhanced Dashboard Functions
    $(document).ready(function() {
        // Sidebar toggle
        $('#sidebarCollapse').on('click', function() {
            $('#sidebar').toggleClass('collapsed');
            $('#content').toggleClass('collapsed');
        });

        // Auto-refresh data every 30 seconds
        setInterval(function() {
            refreshDashboard();
        }, 30000);

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Image modal functionality
        $('.clickable-image').on('click', function() {
            $('#modalImage').attr('src', $(this).attr('src'));
            new bootstrap.Modal(document.getElementById('imgModal')).show();
        });
    });

    // Refresh dashboard function
    function refreshDashboard() {
        // Update timestamp
        $('#lastUpdate').text(new Date().toLocaleString());
        
        // You can add AJAX calls here to refresh specific sections
        // For now, we'll just reload the page to get fresh data
        // location.reload();
    }

    // Responsive sidebar for mobile
    $(window).on('resize', function() {
        if ($(window).width() < 768) {
            $('#sidebar').addClass('collapsed');
            $('#content').addClass('collapsed');
        }
    });
    </script>

    <!-- Management Modals -->
    
    <!-- Employee Modal -->
    <div class="modal fade" id="employeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <a href="add_employee.php" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-person-plus"></i> Add Employee
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="view_employees.php" class="btn btn-outline-primary w-100 mb-3">
                                <i class="bi bi-people"></i> View All Employees
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Branch Modal -->
    <div class="modal fade" id="branchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Branch Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <a href="add_branch.php" class="btn btn-primary w-100 mb-3">
                        <i class="bi bi-building-add"></i> Add New Branch
                    </a>
                    <a href="view_branches.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-building"></i> Manage Branches
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Shift Modal -->
    <div class="modal fade" id="shiftModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Shift Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <a href="add_shift.php" class="btn btn-primary w-100 mb-3">
                        <i class="bi bi-clock-history"></i> Add New Shift
                    </a>
                    <a href="manage_shifts.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-calendar-week"></i> Manage Shifts
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Holiday Modal -->
    <div class="modal fade" id="holidayModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Holiday Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <a href="add_holiday.php" class="btn btn-primary w-100 mb-3">
                        <i class="bi bi-calendar-plus"></i> Add Holiday
                    </a>
                    <a href="add_special_day.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-calendar-event"></i> Add Special Day
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>