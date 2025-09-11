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
    
    if ($action === 'assign_wearable') {
        $employeeId = $_POST['employee_id'];
        $deviceId = $_POST['device_id'];
        
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Deactivate any existing assignments for this employee
            $deactivateStmt = $conn->prepare("
                UPDATE tbl_employee_wearables 
                SET IsActive = 0 
                WHERE EmployeeID = ?
            ");
            $deactivateStmt->execute([$employeeId]);
            
            // Deactivate any existing assignments for this device
            $deactivateDeviceStmt = $conn->prepare("
                UPDATE tbl_employee_wearables 
                SET IsActive = 0 
                WHERE DeviceID = ?
            ");
            $deactivateDeviceStmt->execute([$deviceId]);
            
            // Create new assignment
            $assignStmt = $conn->prepare("
                INSERT INTO tbl_employee_wearables (EmployeeID, DeviceID, IsActive)
                VALUES (?, ?, 1)
            ");
            $assignStmt->execute([$employeeId, $deviceId]);
            
            $conn->commit();
            $message = "Wearable device assigned successfully!";
            $messageType = "success";
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error assigning wearable: " . $e->getMessage();
            $messageType = "danger";
        }
    }
    
    if ($action === 'unassign_wearable') {
        $wearableId = $_POST['wearable_id'];
        
        try {
            $unassignStmt = $conn->prepare("
                UPDATE tbl_employee_wearables 
                SET IsActive = 0 
                WHERE WearableID = ?
            ");
            $unassignStmt->execute([$wearableId]);
            
            $message = "Wearable device unassigned successfully!";
            $messageType = "success";
            
        } catch (Exception $e) {
            $message = "Error unassigning wearable: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Get current assignments
$currentAssignments = $conn->query("
    SELECT ew.*, e.FullName, e.Department, d.DeviceName, d.Identifier, d.Manufacturer, d.Model,
           DATE(ew.AssignedDate) as AssignedDate
    FROM tbl_employee_wearables ew
    JOIN tbl_employees e ON ew.EmployeeID = e.EmployeeID
    JOIN tbl_devices d ON ew.DeviceID = d.DeviceID
    WHERE ew.IsActive = 1
    ORDER BY ew.AssignedDate DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get available employees (those without active wearable assignments)
$availableEmployees = $conn->query("
    SELECT e.EmployeeID, e.FullName, e.Department, e.BranchID
    FROM tbl_employees e
    LEFT JOIN tbl_employee_wearables ew ON e.EmployeeID = ew.EmployeeID AND ew.IsActive = 1
    WHERE ew.EmployeeID IS NULL
    ORDER BY e.FullName
")->fetchAll(PDO::FETCH_ASSOC);

// Get available wearable devices (IoT devices not currently assigned)
$availableDevices = $conn->query("
    SELECT d.DeviceID, d.DeviceName, d.Identifier, d.Manufacturer, d.Model, d.Location
    FROM tbl_devices d
    LEFT JOIN tbl_employee_wearables ew ON d.DeviceID = ew.DeviceID AND ew.IsActive = 1
    WHERE d.DeviceType = 'iot' AND d.IsActive = 1 AND ew.DeviceID IS NULL
    ORDER BY d.DeviceName
")->fetchAll(PDO::FETCH_ASSOC);

// Get all employees for reassignment
$allEmployees = $conn->query("
    SELECT EmployeeID, FullName, Department 
    FROM tbl_employees 
    ORDER BY FullName
")->fetchAll(PDO::FETCH_ASSOC);

// Get assignment statistics
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM tbl_employee_wearables WHERE IsActive = 1) as active_assignments,
        (SELECT COUNT(*) FROM tbl_devices WHERE DeviceType = 'iot' AND IsActive = 1) as total_wearables,
        (SELECT COUNT(*) FROM tbl_employees) as total_employees
")->fetch(PDO::FETCH_ASSOC);

$assignmentRate = $stats['total_employees'] > 0 ? round(($stats['active_assignments'] / $stats['total_employees']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wearable Device Assignment Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .assignment-card { 
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .assignment-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .device-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .stats-card {
            border-left: 4px solid;
        }
        .stats-card.assignments { border-left-color: #0d6efd; }
        .stats-card.devices { border-left-color: #198754; }
        .stats-card.employees { border-left-color: #6f42c1; }
        .stats-card.rate { border-left-color: #fd7e14; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-smartwatch text-primary me-2"></i>Wearable Device Assignments</h2>
                <p class="text-muted mb-0">Manage IoT wearable device assignments for employee wellness monitoring</p>
            </div>
            <div>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
                <a href="wellness_dashboard.php" class="btn btn-outline-info me-2">
                    <i class="bi bi-heart-pulse"></i> Wellness Dashboard
                </a>
                <a href="device_registry.php" class="btn btn-outline-success">
                    <i class="bi bi-plus-lg"></i> Register Device
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
                <div class="card stats-card assignments shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-link-45deg display-6 text-primary me-3"></i>
                            <div>
                                <p class="mb-0 text-muted small">Active Assignments</p>
                                <h3 class="mb-0"><?= $stats['active_assignments'] ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card devices shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-smartwatch display-6 text-success me-3"></i>
                            <div>
                                <p class="mb-0 text-muted small">Available Wearables</p>
                                <h3 class="mb-0"><?= count($availableDevices) ?></h3>
                                <small class="text-muted">of <?= $stats['total_wearables'] ?> total</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card employees shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-people display-6 text-purple me-3"></i>
                            <div>
                                <p class="mb-0 text-muted small">Unassigned Employees</p>
                                <h3 class="mb-0"><?= count($availableEmployees) ?></h3>
                                <small class="text-muted">of <?= $stats['total_employees'] ?> total</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card rate shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-graph-up display-6 text-warning me-3"></i>
                            <div>
                                <p class="mb-0 text-muted small">Assignment Rate</p>
                                <h3 class="mb-0"><?= $assignmentRate ?>%</h3>
                                <small class="text-muted">coverage</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Assignment Form -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 sticky-top">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>New Assignment</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($availableEmployees) || empty($availableDevices)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-info-circle display-6 text-info"></i>
                                <p class="mt-3 mb-0">
                                    <?php if (empty($availableEmployees) && empty($availableDevices)): ?>
                                        No available employees or devices for assignment.
                                    <?php elseif (empty($availableEmployees)): ?>
                                        All employees have been assigned wearable devices.
                                    <?php else: ?>
                                        No available wearable devices. Please register new IoT devices first.
                                    <?php endif; ?>
                                </p>
                                <?php if (empty($availableDevices)): ?>
                                    <a href="device_registry.php" class="btn btn-primary mt-2">
                                        <i class="bi bi-plus"></i> Register New Device
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_wearable">
                                
                                <div class="mb-3">
                                    <label for="employee_id" class="form-label">Employee</label>
                                    <select name="employee_id" id="employee_id" class="form-select" required>
                                        <option value="">Select Employee...</option>
                                        <?php foreach ($availableEmployees as $emp): ?>
                                        <option value="<?= $emp['EmployeeID'] ?>">
                                            <?= htmlspecialchars($emp['FullName']) ?> 
                                            (<?= htmlspecialchars($emp['Department'] ?? 'No Dept') ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="device_id" class="form-label">Wearable Device</label>
                                    <select name="device_id" id="device_id" class="form-select" required>
                                        <option value="">Select Device...</option>
                                        <?php foreach ($availableDevices as $device): ?>
                                        <option value="<?= $device['DeviceID'] ?>">
                                            <?= htmlspecialchars($device['DeviceName']) ?>
                                            <?php if ($device['Manufacturer']): ?>
                                                (<?= htmlspecialchars($device['Manufacturer']) ?> <?= htmlspecialchars($device['Model']) ?>)
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i> 
                                            Only unassigned IoT devices are shown
                                        </small>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-link"></i> Assign Wearable
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Current Assignments -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Current Assignments (<?= count($currentAssignments) ?>)</h5>
                            <div class="input-group" style="width: 300px;">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="searchAssignments" placeholder="Search assignments...">
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($currentAssignments)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-smartwatch display-1 text-muted"></i>
                                <h4 class="mt-3 text-muted">No Active Assignments</h4>
                                <p class="text-muted">Start by assigning wearable devices to employees for wellness monitoring.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="assignmentsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Wearable Device</th>
                                            <th>Device Details</th>
                                            <th>Assigned Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($currentAssignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-primary text-white me-2">
                                                        <?= strtoupper(substr($assignment['FullName'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($assignment['FullName']) ?></strong><br>
                                                        <small class="text-muted"><?= $assignment['EmployeeID'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($assignment['Department'] ?? 'No Dept') ?></span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($assignment['DeviceName']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($assignment['Identifier']) ?></small>
                                            </td>
                                            <td>
                                                <span class="device-badge badge bg-info">
                                                    <?= htmlspecialchars($assignment['Manufacturer']) ?>
                                                </span><br>
                                                <small class="text-muted"><?= htmlspecialchars($assignment['Model']) ?></small>
                                            </td>
                                            <td>
                                                <i class="bi bi-calendar3 text-muted"></i>
                                                <?= date('M j, Y', strtotime($assignment['AssignedDate'])) ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="reassignDevice(<?= $assignment['WearableID'] ?>, '<?= htmlspecialchars($assignment['FullName']) ?>', '<?= htmlspecialchars($assignment['DeviceName']) ?>')">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="unassignDevice(<?= $assignment['WearableID'] ?>, '<?= htmlspecialchars($assignment['FullName']) ?>', '<?= htmlspecialchars($assignment['DeviceName']) ?>')">
                                                        <i class="bi bi-unlink"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reassign Modal -->
    <div class="modal fade" id="reassignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reassign Wearable Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="reassignForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_wearable">
                        <input type="hidden" id="reassign_device_id" name="device_id">
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Current Assignment:</strong> <span id="current_assignment"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reassign_employee_id" class="form-label">Reassign to Employee</label>
                            <select name="employee_id" id="reassign_employee_id" class="form-select" required>
                                <option value="">Select Employee...</option>
                                <?php foreach ($allEmployees as $emp): ?>
                                <option value="<?= $emp['EmployeeID'] ?>">
                                    <?= htmlspecialchars($emp['FullName']) ?> 
                                    (<?= htmlspecialchars($emp['Department'] ?? 'No Dept') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-arrow-repeat"></i> Reassign Device
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Unassign Confirmation Modal -->
    <div class="modal fade" id="unassignModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Unassignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="unassignForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="unassign_wearable">
                        <input type="hidden" id="unassign_wearable_id" name="wearable_id">
                        
                        <div class="text-center">
                            <i class="bi bi-exclamation-triangle display-1 text-warning"></i>
                            <p class="mt-3" id="unassign_message"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-unlink"></i> Unassign
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Search functionality
        $('#searchAssignments').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('#assignmentsTable tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
    });

    function reassignDevice(wearableId, employeeName, deviceName) {
        // Find the device ID from the assignment
        <?php foreach ($currentAssignments as $assignment): ?>
        if (<?= $assignment['WearableID'] ?> === wearableId) {
            $('#reassign_device_id').val(<?= $assignment['DeviceID'] ?>);
            $('#current_assignment').text('<?= htmlspecialchars($assignment['FullName']) ?> → <?= htmlspecialchars($assignment['DeviceName']) ?>');
        }
        <?php endforeach; ?>
        
        $('#reassignModal').modal('show');
    }

    function unassignDevice(wearableId, employeeName, deviceName) {
        $('#unassign_wearable_id').val(wearableId);
        $('#unassign_message').html(`Are you sure you want to unassign <strong>${deviceName}</strong> from <strong>${employeeName}</strong>?`);
        $('#unassignModal').modal('show');
    }
    </script>

    <style>
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 16px;
    }
    .text-purple { color: #6f42c1 !important; }
    </style>
</body>
</html>
