<?php
session_start();
include 'db.php';

// Ensure user is logged in and is an admin/hr
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_device') {
        $deviceName = trim($_POST['device_name']);
        $deviceType = $_POST['device_type'];
        $identifier = trim($_POST['identifier']);
        $branchId = $_POST['branch_id'] ?: null;
        $location = trim($_POST['location']);
        $manufacturer = trim($_POST['manufacturer']);
        $model = trim($_POST['model']);
        $description = trim($_POST['description']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        $errors = [];
        if (empty($deviceName)) $errors[] = 'Device name is required';
        if (empty($deviceType)) $errors[] = 'Device type is required';
        if (empty($identifier)) $errors[] = 'Device identifier is required';
        
        // Validate identifier format based on device type
        switch($deviceType) {
            case 'wifi':
                if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $identifier)) {
                    $errors[] = 'WiFi devices require MAC address format (XX:XX:XX:XX:XX:XX)';
                }
                break;
            case 'bluetooth':
                if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $identifier)) {
                    $errors[] = 'Bluetooth devices require MAC address format (XX:XX:XX:XX:XX:XX)';
                }
                break;
            case 'beacon':
                if (!preg_match('/^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/', $identifier)) {
                    $errors[] = 'Beacon devices require UUID format (XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX)';
                }
                break;
        }
        
        if (empty($errors)) {
            try {
                $sql = "INSERT INTO tbl_devices (DeviceName, DeviceType, Identifier, BranchID, Location, Manufacturer, Model, Description, IsActive, CreatedBy, CreatedAt) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$deviceName, $deviceType, $identifier, $branchId, $location, $manufacturer, $model, $description, $isActive, $_SESSION['user_id']]);
                
                $message = 'Device registered successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = 'Device identifier already exists for this type.';
                } else {
                    $message = 'Database error: ' . $e->getMessage();
                }
                $messageType = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'update_status') {
        $deviceId = intval($_POST['device_id']);
        $isActive = intval($_POST['is_active']);
        
        try {
            $stmt = $conn->prepare("UPDATE tbl_devices SET IsActive = ?, UpdatedAt = NOW() WHERE DeviceID = ?");
            $stmt->execute([$isActive, $deviceId]);
            $message = 'Device status updated successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error updating device status: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'delete_device') {
        $deviceId = intval($_POST['device_id']);
        
        try {
            $stmt = $conn->prepare("DELETE FROM tbl_devices WHERE DeviceID = ?");
            $stmt->execute([$deviceId]);
            $message = 'Device deleted successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error deleting device: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get filter parameters
$filterType = $_GET['type'] ?? '';
$filterBranch = $_GET['branch'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = ['1=1'];
$params = [];

if ($filterType) {
    $where[] = "d.DeviceType = ?";
    $params[] = $filterType;
}
if ($filterBranch) {
    $where[] = "d.BranchID = ?";
    $params[] = $filterBranch;
}
if ($filterStatus !== '') {
    $where[] = "d.IsActive = ?";
    $params[] = $filterStatus;
}
if ($search) {
    $where[] = "(d.DeviceName LIKE ? OR d.Identifier LIKE ? OR d.Manufacturer LIKE ? OR d.Model LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = implode(' AND ', $where);

// Fetch devices with branch information
$sql = "
    SELECT d.*, 
           b.BranchName,
           u.FullName as CreatedByName,
           CASE 
               WHEN d.UpdatedAt IS NOT NULL THEN TIMESTAMPDIFF(DAY, d.UpdatedAt, NOW())
               ELSE TIMESTAMPDIFF(DAY, d.CreatedAt, NOW())
           END as DaysOld
    FROM tbl_devices d
    LEFT JOIN tbl_branches b ON d.BranchID = b.BranchID
    LEFT JOIN tbl_employees u ON d.CreatedBy = u.EmployeeID
    WHERE $whereClause
    ORDER BY d.CreatedAt DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$totalDevices = $conn->query("SELECT COUNT(*) FROM tbl_devices")->fetchColumn();
$activeDevices = $conn->query("SELECT COUNT(*) FROM tbl_devices WHERE IsActive = 1")->fetchColumn();
$deviceTypes = $conn->query("SELECT DeviceType, COUNT(*) as count FROM tbl_devices GROUP BY DeviceType")->fetchAll(PDO::FETCH_ASSOC);

// Get branches for filters
$branches = $conn->query("SELECT BranchID, BranchName FROM tbl_branches ORDER BY BranchName")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Registry - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border: 0; }
        .form-label { font-weight: 600; color: #374151; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .device-card { transition: all 0.2s; border-left: 4px solid; }
        .device-card:hover { transform: translateY(-2px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .device-wifi { border-left-color: #17a2b8; }
        .device-bluetooth { border-left-color: #007bff; }
        .device-beacon { border-left-color: #28a745; }
        .device-iot { border-left-color: #ffc107; }
        .device-rfid { border-left-color: #dc3545; }
        .device-camera { border-left-color: #6f42c1; }
        .device-sensor { border-left-color: #fd7e14; }
        .identifier-text { font-family: 'Courier New', monospace; font-size: 0.9em; }
        .table th { background-color: #f8f9fa; font-weight: 600; }
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
                            <a href="device_registry.php" class="nav-link active">
                                <i class="bi bi-router me-2"></i>Device Registry
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_dashboard.php" class="nav-link">
                                <i class="bi bi-grid me-2"></i>Dashboard
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="bi bi-router-fill me-2 text-primary"></i>Device Registry</h2>
                        <p class="text-muted mb-0">Manage and track all IoT, WiFi, Bluetooth, and other devices</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                        <i class="bi bi-plus-circle me-2"></i>Register New Device
                    </button>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Devices</h6>
                                        <h3 class="mb-0"><?= $totalDevices ?></h3>
                                        <small>Registered</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-router display-4 opacity-50"></i>
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
                                        <h6 class="card-title">Active Devices</h6>
                                        <h3 class="mb-0"><?= $activeDevices ?></h3>
                                        <small>Online & Monitored</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-wifi display-4 opacity-50"></i>
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
                                        <h6 class="card-title">Device Types</h6>
                                        <h3 class="mb-0"><?= count($deviceTypes) ?></h3>
                                        <small>Categories</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-diagram-3 display-4 opacity-50"></i>
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
                                        <h6 class="card-title">Inactive</h6>
                                        <h3 class="mb-0"><?= $totalDevices - $activeDevices ?></h3>
                                        <small>Offline/Disabled</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-wifi-off display-4 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Device Types Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Device Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($deviceTypes as $type): ?>
                                    <div class="col-md-2 text-center">
                                        <div class="p-3">
                                            <i class="bi bi-<?= getDeviceIcon($type['DeviceType']) ?> display-6 text-primary"></i>
                                            <h4 class="mt-2"><?= $type['count'] ?></h4>
                                            <small class="text-muted text-capitalize"><?= $type['DeviceType'] ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters & Search</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Name, identifier, manufacturer...">
                            </div>
                            <div class="col-md-2">
                                <label for="type" class="form-label">Device Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="wifi" <?= $filterType === 'wifi' ? 'selected' : '' ?>>WiFi</option>
                                    <option value="bluetooth" <?= $filterType === 'bluetooth' ? 'selected' : '' ?>>Bluetooth</option>
                                    <option value="beacon" <?= $filterType === 'beacon' ? 'selected' : '' ?>>Beacon</option>
                                    <option value="iot" <?= $filterType === 'iot' ? 'selected' : '' ?>>IoT Device</option>
                                    <option value="rfid" <?= $filterType === 'rfid' ? 'selected' : '' ?>>RFID</option>
                                    <option value="camera" <?= $filterType === 'camera' ? 'selected' : '' ?>>Camera</option>
                                    <option value="sensor" <?= $filterType === 'sensor' ? 'selected' : '' ?>>Sensor</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="branch" class="form-label">Branch</label>
                                <select class="form-select" id="branch" name="branch">
                                    <option value="">All Branches</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= htmlspecialchars($branch['BranchID']) ?>" 
                                                <?= $filterBranch === $branch['BranchID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($branch['BranchName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-2"></i>Filter
                                </button>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <a href="device_registry.php" class="btn btn-outline-secondary w-100" title="Clear">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Devices List -->
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list me-2"></i>Registered Devices</h5>
                        <span class="badge bg-primary"><?= count($devices) ?> devices</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($devices): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Device</th>
                                        <th>Type</th>
                                        <th>Identifier</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Branch</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($devices as $device): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-<?= getDeviceIcon($device['DeviceType']) ?> me-2 text-primary"></i>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($device['DeviceName']) ?></div>
                                                    <?php if ($device['Manufacturer']): ?>
                                                    <small class="text-muted"><?= htmlspecialchars($device['Manufacturer']) ?> <?= htmlspecialchars($device['Model']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= getDeviceColor($device['DeviceType']) ?> text-capitalize">
                                                <?= $device['DeviceType'] ?>
                                            </span>
                                        </td>
                                        <td class="identifier-text"><?= htmlspecialchars($device['Identifier']) ?></td>
                                        <td><?= htmlspecialchars($device['Location'] ?: '-') ?></td>
                                        <td>
                                            <?php if ($device['IsActive']): ?>
                                            <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($device['BranchName'] ?: 'All Branches') ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('M j, Y', strtotime($device['CreatedAt'])) ?><br>
                                                by <?= htmlspecialchars($device['CreatedByName']) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info btn-sm" 
                                                        onclick="viewDevice(<?= $device['DeviceID'] ?>)" 
                                                        title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-<?= $device['IsActive'] ? 'warning' : 'success' ?> btn-sm" 
                                                        onclick="toggleDeviceStatus(<?= $device['DeviceID'] ?>, <?= $device['IsActive'] ? 0 : 1 ?>)" 
                                                        title="<?= $device['IsActive'] ? 'Deactivate' : 'Activate' ?>">
                                                    <i class="bi bi-<?= $device['IsActive'] ? 'pause' : 'play' ?>"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" 
                                                        onclick="deleteDevice(<?= $device['DeviceID'] ?>, '<?= htmlspecialchars($device['DeviceName'], ENT_QUOTES) ?>')" 
                                                        title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center p-5">
                            <i class="bi bi-router-fill display-4 text-muted"></i>
                            <h5 class="mt-3 text-muted">No Devices Found</h5>
                            <p class="text-muted">
                                <?= $search || $filterType || $filterBranch || $filterStatus !== '' ? 'No devices match your search criteria.' : 'No devices have been registered yet.' ?>
                            </p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                                <i class="bi bi-plus-circle me-2"></i>Register First Device
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Device Modal -->
    <div class="modal fade" id="addDeviceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Register New Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_device">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="device_name" class="form-label">Device Name *</label>
                                <input type="text" class="form-control" id="device_name" name="device_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="device_type" class="form-label">Device Type *</label>
                                <select class="form-select" id="device_type" name="device_type" required onchange="updateIdentifierPlaceholder()">
                                    <option value="">Select Type</option>
                                    <option value="wifi">WiFi Access Point</option>
                                    <option value="bluetooth">Bluetooth Device</option>
                                    <option value="beacon">BLE Beacon</option>
                                    <option value="iot">IoT Device</option>
                                    <option value="rfid">RFID Reader</option>
                                    <option value="camera">IP Camera</option>
                                    <option value="sensor">Sensor</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="identifier" class="form-label">Device Identifier *</label>
                                <input type="text" class="form-control" id="identifier" name="identifier" required>
                                <div class="form-text" id="identifier_help">Unique identifier for this device</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="branch_id" class="form-label">Branch</label>
                                <select class="form-select" id="branch_id" name="branch_id">
                                    <option value="">All Branches</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= htmlspecialchars($branch['BranchID']) ?>">
                                            <?= htmlspecialchars($branch['BranchName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="manufacturer" class="form-label">Manufacturer</label>
                                <input type="text" class="form-control" id="manufacturer" name="manufacturer">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="model" class="form-label">Model</label>
                                <input type="text" class="form-control" id="model" name="model">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="location" class="form-label">Physical Location</label>
                            <input type="text" class="form-control" id="location" name="location" placeholder="e.g., Main Entrance, Conference Room A">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Device is active and should be monitored
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Register Device</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateIdentifierPlaceholder() {
            const deviceType = document.getElementById('device_type').value;
            const identifierInput = document.getElementById('identifier');
            const helpText = document.getElementById('identifier_help');
            
            switch(deviceType) {
                case 'wifi':
                case 'bluetooth':
                    identifierInput.placeholder = 'XX:XX:XX:XX:XX:XX';
                    helpText.textContent = 'MAC address format (e.g., 00:11:22:33:44:55)';
                    break;
                case 'beacon':
                    identifierInput.placeholder = 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX';
                    helpText.textContent = 'UUID format (e.g., 550e8400-e29b-41d4-a716-446655440000)';
                    break;
                case 'iot':
                    identifierInput.placeholder = 'Device ID or Serial Number';
                    helpText.textContent = 'Unique device identifier or serial number';
                    break;
                case 'rfid':
                    identifierInput.placeholder = 'Reader ID';
                    helpText.textContent = 'RFID reader identifier';
                    break;
                case 'camera':
                    identifierInput.placeholder = 'IP Address or Serial';
                    helpText.textContent = 'IP address or camera serial number';
                    break;
                case 'sensor':
                    identifierInput.placeholder = 'Sensor ID';
                    helpText.textContent = 'Sensor identifier or address';
                    break;
                default:
                    identifierInput.placeholder = 'Device identifier';
                    helpText.textContent = 'Unique identifier for this device';
            }
        }
        
        function toggleDeviceStatus(deviceId, newStatus) {
            if (confirm('Are you sure you want to ' + (newStatus ? 'activate' : 'deactivate') + ' this device?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="device_id" value="${deviceId}">
                    <input type="hidden" name="is_active" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteDevice(deviceId, deviceName) {
            if (confirm(`Are you sure you want to delete "${deviceName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_device">
                    <input type="hidden" name="device_id" value="${deviceId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function viewDevice(deviceId) {
            // Implement device details view
            alert('Device details view would be implemented here');
        }
        
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('show')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    </script>
</body>
</html>

<?php
function getDeviceIcon($type) {
    switch($type) {
        case 'wifi': return 'wifi';
        case 'bluetooth': return 'bluetooth';
        case 'beacon': return 'broadcast';
        case 'iot': return 'cpu';
        case 'rfid': return 'credit-card';
        case 'camera': return 'camera-video';
        case 'sensor': return 'thermometer-half';
        default: return 'router';
    }
}

function getDeviceColor($type) {
    switch($type) {
        case 'wifi': return 'info';
        case 'bluetooth': return 'primary';
        case 'beacon': return 'success';
        case 'iot': return 'warning';
        case 'rfid': return 'danger';
        case 'camera': return 'secondary';
        case 'sensor': return 'dark';
        default: return 'secondary';
    }
}
?>
