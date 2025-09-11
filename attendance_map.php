<?php
session_start();
include 'db.php';

// Ensure user is logged in and has appropriate access
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr', 'manager'])) {
    header('Location: login.php');
    exit;
}

// Get filters from GET parameters
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$branch_id = isset($_GET['branch_id']) ? $_GET['branch_id'] : '';
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
$attendance_type = isset($_GET['attendance_type']) ? $_GET['attendance_type'] : 'all';
$method_filter = isset($_GET['method_filter']) ? $_GET['method_filter'] : '';

// Fetch filter options
$branches = $conn->query("SELECT BranchID, BranchName FROM tbl_branches ORDER BY BranchName")->fetchAll(PDO::FETCH_ASSOC);

// Get employees based on branch filter
$employeeSql = "SELECT EmployeeID, FullName FROM tbl_employees";
$employeeParams = [];
if ($branch_id) {
    $employeeSql .= " WHERE BranchID = ?";
    $employeeParams[] = $branch_id;
}
$employeeSql .= " ORDER BY FullName";
$employees = $conn->prepare($employeeSql);
$employees->execute($employeeParams);
$employees = $employees->fetchAll(PDO::FETCH_ASSOC);

// Build main query
$where = ["a.AttendanceDate = ?"];
$params = [$date];

if ($branch_id) {
    $where[] = "a.BranchID = ?";
    $params[] = $branch_id;
}
if ($employee_id) {
    $where[] = "a.EmployeeID = ?";
    $params[] = $employee_id;
}

// Filter by attendance method if indoor presence tables exist
if ($method_filter) {
    try {
        if ($method_filter === 'gps') {
            $where[] = "(a.ClockInMethod IS NULL OR a.ClockInMethod = 'GPS')";
        } elseif ($method_filter === 'indoor') {
            $where[] = "(a.ClockInMethod = 'Indoor' OR a.ClockInMethod = 'Wearable+Indoor')";
        }
    } catch (PDOException $e) {
        // Ignore if columns don't exist
    }
}

$where_sql = implode(" AND ", $where);

// Main query with additional fields
$sql = "
    SELECT a.*, 
           e.FullName, e.Username, e.PhoneNumber,
           b.BranchName, b.Latitude AS BranchLat, b.Longitude AS BranchLng, b.AllowedRadius,
           TIME(a.ClockIn) as ClockInTime,
           TIME(a.ClockOut) as ClockOutTime,
           CASE 
               WHEN a.ClockOut IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, a.ClockIn, a.ClockOut) 
               ELSE NULL 
           END as WorkingMinutes
    FROM tbl_attendance a
    LEFT JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID
    LEFT JOIN tbl_branches b ON a.BranchID = b.BranchID
    WHERE $where_sql
    ORDER BY a.ClockIn
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalRecords = count($records);
$clockedInCount = array_filter($records, function($r) { return !empty($r['ClockIn']); });
$clockedOutCount = array_filter($records, function($r) { return !empty($r['ClockOut']); });
$avgWorkingTime = 0;
if ($clockedOutCount) {
    $totalMinutes = array_sum(array_column(array_filter($records, function($r) { return $r['WorkingMinutes']; }), 'WorkingMinutes'));
    $avgWorkingTime = round($totalMinutes / count($clockedOutCount) / 60, 1);
}

// Check if indoor presence columns exist
$hasIndoorColumns = false;
try {
    $conn->query("SELECT ClockInMethod FROM tbl_attendance LIMIT 1");
    $hasIndoorColumns = true;
} catch (PDOException $e) {
    // Columns don't exist
}

// Get default map center
$mapCenter = ['lat' => 14.5995, 'lng' => 120.9842]; // Default to Philippines
if ($records && $records[0]['BranchLat'] && $records[0]['BranchLng']) {
    $mapCenter = [
        'lat' => floatval($records[0]['BranchLat']), 
        'lng' => floatval($records[0]['BranchLng'])
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Map - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border: 0; }
        .form-label { font-weight: 600; color: #374151; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        #map { 
            height: 600px; 
            width: 100%; 
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .attendance-item {
            border-left: 4px solid;
            transition: all 0.2s;
        }
        .attendance-item:hover {
            transform: translateX(2px);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        .status-present { border-left-color: #28a745; }
        .status-partial { border-left-color: #ffc107; }
        .status-absent { border-left-color: #dc3545; }
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        .working-time {
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Navigation -->
            <div class="col-md-2">
                <div class="d-flex flex-column p-3 bg-white vh-100">
                    <a href="admin_dashboard.php" class="btn btn-outline-secondary mb-3">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                    <hr>
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a href="attendance_map.php" class="nav-link active">
                                <i class="bi bi-geo-alt me-2"></i>Attendance Map
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="reports.php" class="nav-link">
                                <i class="bi bi-graph-up me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="report_viewer.php" class="nav-link">
                                <i class="bi bi-table me-2"></i>Report Viewer
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="bi bi-geo-alt-fill me-2 text-primary"></i>Attendance Map</h2>
                        <p class="text-muted mb-0">Visual representation of employee attendance locations</p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Date: <strong><?= date('M j, Y', strtotime($date)) ?></strong></small>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Records</h6>
                                        <h3 class="mb-0"><?= $totalRecords ?></h3>
                                        <small>Attendance Entries</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-calendar-check display-4 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Clock-ins</h6>
                                        <h3 class="mb-0"><?= count($clockedInCount) ?></h3>
                                        <small>Employees Present</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-box-arrow-in-right display-4 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Clock-outs</h6>
                                        <h3 class="mb-0"><?= count($clockedOutCount) ?></h3>
                                        <small>Completed Shifts</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-box-arrow-right display-4 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Avg. Hours</h6>
                                        <h3 class="mb-0"><?= $avgWorkingTime ?: '0' ?></h3>
                                        <small>Working Time</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-clock display-4 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters & Controls</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" 
                                       value="<?= htmlspecialchars($date) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="branch_id" class="form-label">Branch</label>
                                <select class="form-select" id="branch_id" name="branch_id">
                                    <option value="">All Branches</option>
                                    <?php foreach ($branches as $b): ?>
                                        <option value="<?= htmlspecialchars($b['BranchID']) ?>" 
                                                <?= $branch_id == $b['BranchID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['BranchName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="employee_id" class="form-label">Employee</label>
                                <select class="form-select" id="employee_id" name="employee_id">
                                    <option value="">All Employees</option>
                                    <?php foreach ($employees as $e): ?>
                                        <option value="<?= htmlspecialchars($e['EmployeeID']) ?>" 
                                                <?= $employee_id == $e['EmployeeID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($e['FullName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($hasIndoorColumns): ?>
                            <div class="col-md-2">
                                <label for="method_filter" class="form-label">Method</label>
                                <select class="form-select" id="method_filter" name="method_filter">
                                    <option value="">All Methods</option>
                                    <option value="gps" <?= $method_filter == 'gps' ? 'selected' : '' ?>>GPS Only</option>
                                    <option value="indoor" <?= $method_filter == 'indoor' ? 'selected' : '' ?>>Indoor</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                            <?php else: ?>
                            <div class="col-md-3 d-flex align-items-end">
                            <?php endif; ?>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-2"></i>Apply
                                </button>
                            </div>
                        </form>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" onclick="centerMapOnBranches()">
                                        <i class="bi bi-bullseye me-1"></i>Center on Branches
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="showAllMarkers()">
                                        <i class="bi bi-zoom-out me-1"></i>Show All
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="toggleHeatmap()">
                                        <i class="bi bi-thermometer-half me-1"></i>Heatmap
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <a href="?date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?>&branch_id=<?= $branch_id ?>&employee_id=<?= $employee_id ?>" 
                                   class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-chevron-left"></i> Previous Day
                                </a>
                                <a href="?date=<?= date('Y-m-d') ?>&branch_id=<?= $branch_id ?>&employee_id=<?= $employee_id ?>" 
                                   class="btn btn-outline-primary btn-sm">Today</a>
                                <a href="?date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?>&branch_id=<?= $branch_id ?>&employee_id=<?= $employee_id ?>" 
                                   class="btn btn-outline-secondary btn-sm">
                                    Next Day <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Map Column -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-map me-2"></i>Attendance Locations</h5>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-light btn-sm" onclick="changeMapType('roadmap')">Road</button>
                                    <button type="button" class="btn btn-light btn-sm" onclick="changeMapType('satellite')">Satellite</button>
                                    <button type="button" class="btn btn-light btn-sm" onclick="changeMapType('hybrid')">Hybrid</button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div id="map"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Details Column -->
                    <div class="col-lg-4">
                        <!-- Legend -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Map Legend</h6>
                            </div>
                            <div class="card-body">
                                <div class="legend-item">
                                    <div class="legend-color bg-primary"></div>
                                    <span>Branch Location</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color bg-success"></div>
                                    <span>Clock-in Location</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color bg-danger"></div>
                                    <span>Clock-out Location</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: rgba(33, 150, 243, 0.3); border: 2px solid #2196F3;"></div>
                                    <span>Allowed Radius</span>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance List -->
                        <div class="card">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-list me-2"></i>Attendance Details</h6>
                                <span class="badge bg-primary"><?= count($records) ?> records</span>
                            </div>
                            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                                <?php if ($records): ?>
                                    <?php foreach ($records as $record): ?>
                                    <div class="attendance-item p-3 border-bottom 
                                         <?= $record['ClockOut'] ? 'status-present' : ($record['ClockIn'] ? 'status-partial' : 'status-absent') ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($record['FullName']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($record['EmployeeID']) ?></small>
                                            </div>
                                            <div class="text-end">
                                                <small class="badge bg-secondary"><?= htmlspecialchars($record['BranchName']) ?></small>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <?php if ($record['ClockIn']): ?>
                                            <div class="small">
                                                <i class="bi bi-box-arrow-in-right text-success me-1"></i>
                                                In: <?= date('H:i', strtotime($record['ClockIn'])) ?>
                                                <?php if ($hasIndoorColumns && $record['ClockInMethod']): ?>
                                                <span class="badge bg-info ms-1"><?= $record['ClockInMethod'] ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($record['ClockOut']): ?>
                                            <div class="small">
                                                <i class="bi bi-box-arrow-right text-danger me-1"></i>
                                                Out: <?= date('H:i', strtotime($record['ClockOut'])) ?>
                                                <?php if ($record['WorkingMinutes']): ?>
                                                <span class="working-time ms-2">(<?= floor($record['WorkingMinutes'] / 60) ?>h <?= $record['WorkingMinutes'] % 60 ?>m)</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($record['Latitude'] && $record['Longitude']): ?>
                                        <div class="mt-2">
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="focusOnEmployee('<?= $record['EmployeeID'] ?>', <?= $record['Latitude'] ?>, <?= $record['Longitude'] ?>)">
                                                <i class="bi bi-geo-alt me-1"></i>Show on Map
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="bi bi-calendar-x display-4 text-muted"></i>
                                    <h6 class="mt-2 text-muted">No Attendance Records</h6>
                                    <p class="small text-muted">No attendance data found for the selected criteria.</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const records = <?= json_encode($records) ?>;
        const mapCenter = <?= json_encode($mapCenter) ?>;
        let map;
        let markers = [];
        let circles = [];
        let heatmap;
        let infoWindows = [];

        function initMap() {
            // Initialize map
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: records.length > 0 ? 13 : 10,
                center: mapCenter,
                mapTypeId: 'roadmap',
                styles: [
                    {
                        featureType: 'poi',
                        elementType: 'labels',
                        stylers: [{ visibility: 'off' }]
                    }
                ]
            });

            plotBranches();
            plotAttendancePoints();
            
            if (records.length > 1) {
                showAllMarkers();
            }
        }

        function plotBranches() {
            const branchMap = new Map();
            
            records.forEach(record => {
                if (!branchMap.has(record.BranchID) && record.BranchLat && record.BranchLng) {
                    branchMap.set(record.BranchID, record);
                    
                    // Branch circle (geofence)
                    const circle = new google.maps.Circle({
                        strokeColor: "#2196F3",
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: "#2196F3",
                        fillOpacity: 0.15,
                        map: map,
                        center: { lat: parseFloat(record.BranchLat), lng: parseFloat(record.BranchLng) },
                        radius: parseFloat(record.AllowedRadius) * 1000 // Convert km to meters
                    });
                    circles.push(circle);
                    
                    // Branch marker
                    const marker = new google.maps.Marker({
                        position: { lat: parseFloat(record.BranchLat), lng: parseFloat(record.BranchLng) },
                        map: map,
                        title: record.BranchName,
                        icon: {
                            url: 'data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="%232196F3"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>',
                            scaledSize: new google.maps.Size(32, 32)
                        }
                    });
                    
                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div class="p-2">
                                <h6 class="mb-1">${record.BranchName}</h6>
                                <small class="text-muted">Geofence: ${record.AllowedRadius} km</small>
                            </div>
                        `
                    });
                    
                    marker.addListener('click', () => {
                        closeAllInfoWindows();
                        infoWindow.open(map, marker);
                        infoWindows.push(infoWindow);
                    });
                    
                    markers.push(marker);
                }
            });
        }

        function plotAttendancePoints() {
            records.forEach(record => {
                if (record.Latitude && record.Longitude) {
                    // Clock-in marker
                    const clockInMarker = new google.maps.Marker({
                        position: { lat: parseFloat(record.Latitude), lng: parseFloat(record.Longitude) },
                        map: map,
                        title: `${record.FullName} - Clock In: ${record.ClockInTime || 'N/A'}`,
                        icon: {
                            url: 'data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="%2328a745"><circle cx="12" cy="12" r="10"/><path fill="white" d="M9 12l2 2 4-4"/></svg>',
                            scaledSize: new google.maps.Size(20, 20)
                        }
                    });
                    
                    // Clock-out marker (if available)
                    if (record.ClockOutLatitude && record.ClockOutLongitude) {
                        const clockOutMarker = new google.maps.Marker({
                            position: { lat: parseFloat(record.ClockOutLatitude), lng: parseFloat(record.ClockOutLongitude) },
                            map: map,
                            title: `${record.FullName} - Clock Out: ${record.ClockOutTime || 'N/A'}`,
                            icon: {
                                url: 'data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="%23dc3545"><circle cx="12" cy="12" r="10"/><path fill="white" d="M8 8l8 8M16 8l-8 8"/></svg>',
                                scaledSize: new google.maps.Size(20, 20)
                            }
                        });
                        markers.push(clockOutMarker);
                    }
                    
                    const workingTime = record.WorkingMinutes ? 
                        `${Math.floor(record.WorkingMinutes / 60)}h ${record.WorkingMinutes % 60}m` : 'Ongoing';
                    
                    const infoContent = `
                        <div class="p-2">
                            <h6 class="mb-1">${record.FullName}</h6>
                            <div class="small">
                                <div><strong>Employee ID:</strong> ${record.EmployeeID}</div>
                                <div><strong>Branch:</strong> ${record.BranchName}</div>
                                <div><strong>Clock In:</strong> ${record.ClockInTime || 'N/A'}</div>
                                <div><strong>Clock Out:</strong> ${record.ClockOutTime || 'N/A'}</div>
                                <div><strong>Working Time:</strong> ${workingTime}</div>
                                ${record.ClockInMethod ? `<div><strong>Method:</strong> ${record.ClockInMethod}</div>` : ''}
                            </div>
                        </div>
                    `;
                    
                    const infoWindow = new google.maps.InfoWindow({
                        content: infoContent
                    });
                    
                    clockInMarker.addListener('click', () => {
                        closeAllInfoWindows();
                        infoWindow.open(map, clockInMarker);
                        infoWindows.push(infoWindow);
                    });
                    
                    markers.push(clockInMarker);
                }
            });
        }

        function closeAllInfoWindows() {
            infoWindows.forEach(infoWindow => infoWindow.close());
            infoWindows = [];
        }

        function showAllMarkers() {
            if (markers.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                markers.forEach(marker => bounds.extend(marker.getPosition()));
                map.fitBounds(bounds);
                if (map.getZoom() > 15) map.setZoom(15);
            }
        }

        function centerMapOnBranches() {
            const branchPositions = [];
            records.forEach(record => {
                if (record.BranchLat && record.BranchLng) {
                    branchPositions.push({ lat: parseFloat(record.BranchLat), lng: parseFloat(record.BranchLng) });
                }
            });
            
            if (branchPositions.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                branchPositions.forEach(pos => bounds.extend(pos));
                map.fitBounds(bounds);
            }
        }

        function focusOnEmployee(employeeId, lat, lng) {
            map.setCenter({ lat: parseFloat(lat), lng: parseFloat(lng) });
            map.setZoom(16);
        }

        function changeMapType(type) {
            map.setMapTypeId(type);
        }

        function toggleHeatmap() {
            // Placeholder for heatmap functionality
            alert('Heatmap functionality would require additional Google Maps API features');
        }

        // Auto-update employee dropdown when branch changes
        document.getElementById('branch_id').addEventListener('change', function() {
            const branchId = this.value;
            const employeeSelect = document.getElementById('employee_id');
            
            // Reset employee selection
            employeeSelect.value = '';
            
            // You could implement AJAX here to dynamically load employees for the selected branch
        });

        // Initialize map when page loads
        window.onload = initMap;
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAWeXcDwE21q90SP_ADvll7D7gEYPN30TU&callback=initMap" async defer></script>
</body>
</html>