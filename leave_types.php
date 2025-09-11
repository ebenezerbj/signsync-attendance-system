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
$editMode = false;
$editData = [];

// Handle Add or Update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $leave_name = trim($_POST['leave_name']);
    $is_rank_based = isset($_POST['is_rank_based']) ? 1 : 0;
    $default_days = !empty($_POST['default_days']) ? intval($_POST['default_days']) : null;

    // Validation
    $errors = [];
    if (empty($leave_name)) $errors[] = 'Leave type name is required';
    if (strlen($leave_name) > 100) $errors[] = 'Leave type name must be 100 characters or less';
    if ($default_days !== null && $default_days < 0) $errors[] = 'Default days cannot be negative';

    if (empty($errors)) {
        try {
            if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
                // Update
                $stmt = $conn->prepare("UPDATE tbl_leave_types SET LeaveTypeName=?, IsRankBased=?, DefaultDays=? WHERE LeaveTypeID=?");
                $stmt->execute([$leave_name, $is_rank_based, $default_days, $_POST['edit_id']]);
                $message = 'Leave type updated successfully!';
            } else {
                // Add
                $stmt = $conn->prepare("INSERT INTO tbl_leave_types (LeaveTypeName, IsRankBased, DefaultDays) VALUES (?, ?, ?)");
                $stmt->execute([$leave_name, $is_rank_based, $default_days]);
                $message = 'Leave type added successfully!';
            }
            $messageType = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = 'A leave type with this name already exists.';
            } else {
                $message = 'Database error: ' . $e->getMessage();
            }
            $messageType = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
        // Keep form data for correction
        $editData = [
            'LeaveTypeID' => $_POST['edit_id'] ?? '',
            'LeaveTypeName' => $leave_name,
            'IsRankBased' => $is_rank_based,
            'DefaultDays' => $_POST['default_days']
        ];
        $editMode = !empty($_POST['edit_id']);
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        // Check if leave type is in use (by name matching)
        $leaveTypeStmt = $conn->prepare("SELECT LeaveTypeName FROM tbl_leave_types WHERE LeaveTypeID = ?");
        $leaveTypeStmt->execute([$id]);
        $leaveTypeName = $leaveTypeStmt->fetchColumn();
        
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM tbl_leave_requests WHERE type = ?");
        $checkStmt->execute([$leaveTypeName]);
        $inUse = $checkStmt->fetchColumn();
        
        if ($inUse > 0) {
            $message = 'Cannot delete this leave type as it is currently being used in leave requests.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("DELETE FROM tbl_leave_types WHERE LeaveTypeID=?");
            $stmt->execute([$id]);
            $message = 'Leave type deleted successfully!';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Error deleting leave type: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle Edit Mode
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editStmt = $conn->prepare("SELECT * FROM tbl_leave_types WHERE LeaveTypeID = ?");
    $editStmt->execute([$editId]);
    $editData = $editStmt->fetch(PDO::FETCH_ASSOC);
    if ($editData) {
        $editMode = true;
    }
}

// Fetch leave types with usage statistics
$stmt = $conn->query("
    SELECT lt.*, 
           COALESCE(lr.usage_count, 0) as usage_count
    FROM tbl_leave_types lt
    LEFT JOIN (
        SELECT type as leave_type_name, COUNT(*) as usage_count 
        FROM tbl_leave_requests 
        GROUP BY type
    ) lr ON lt.LeaveTypeName = lr.leave_type_name
    ORDER BY lt.LeaveTypeName
");
$leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total leave requests for statistics
$totalRequests = $conn->query("SELECT COUNT(*) FROM tbl_leave_requests")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Types - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border: 0; }
        .form-label { font-weight: 600; color: #374151; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .table th { background-color: #f8f9fa; font-weight: 600; }
        .usage-badge { font-size: 0.75rem; }
        .rank-badge { font-size: 0.8rem; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
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
                            <a href="leave_types.php" class="nav-link active">
                                <i class="bi bi-calendar-check me-2"></i>Leave Types
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin_requests.php" class="nav-link">
                                <i class="bi bi-inbox me-2"></i>Leave Requests
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="bi bi-calendar-check-fill me-2 text-primary"></i>Manage Leave Types</h2>
                        <p class="text-muted mb-0">Configure different types of leave available to employees</p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Total Leave Types: <strong><?= count($leave_types) ?></strong></small>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Form Column -->
                    <div class="col-lg-4">
                        <!-- Statistics Card -->
                        <div class="card stats-card text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Leave Statistics</h6>
                                        <h3 class="mb-0"><?= $totalRequests ?></h3>
                                        <small>Total Requests</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-graph-up display-4 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Card -->
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-<?= $editMode ? 'pencil' : 'plus-circle' ?> me-2"></i>
                                    <?= $editMode ? 'Edit Leave Type' : 'Add New Leave Type' ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="leaveTypeForm">
                                    <?php if ($editMode): ?>
                                    <input type="hidden" name="edit_id" value="<?= htmlspecialchars($editData['LeaveTypeID']) ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="leave_name" class="form-label">Leave Type Name *</label>
                                        <input type="text" class="form-control" id="leave_name" name="leave_name" 
                                               value="<?= htmlspecialchars($editData['LeaveTypeName'] ?? '') ?>" 
                                               required maxlength="100" placeholder="e.g., Annual Leave, Sick Leave">
                                        <div class="form-text">Descriptive name for this leave type</div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="is_rank_based" name="is_rank_based"
                                                   <?= ($editData['IsRankBased'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_rank_based">
                                                <strong>Rank-Based Allocation</strong>
                                            </label>
                                        </div>
                                        <div class="form-text">
                                            <i class="bi bi-info-circle me-1"></i>
                                            When enabled, leave days vary by employee rank
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="default_days" class="form-label">Default Days</label>
                                        <input type="number" class="form-control" id="default_days" name="default_days" 
                                               value="<?= htmlspecialchars($editData['DefaultDays'] ?? '') ?>" 
                                               min="0" placeholder="e.g., 21">
                                        <div class="form-text">Base allocation (leave empty for rank-based types)</div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <?php if ($editMode): ?>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle me-2"></i>Update Leave Type
                                        </button>
                                        <a href="leave_types.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-circle me-2"></i>Cancel
                                        </a>
                                        <?php else: ?>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-plus-circle me-2"></i>Add Leave Type
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Help Card -->
                        <div class="card mt-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-question-circle me-2"></i>Leave Types Guide</h6>
                            </div>
                            <div class="card-body">
                                <div class="small">
                                    <div class="mb-2">
                                        <strong>Rank-Based:</strong> Different allocations based on employee level
                                    </div>
                                    <div class="mb-2">
                                        <strong>Fixed Days:</strong> Same allocation for all employees
                                    </div>
                                    <div class="text-muted">
                                        <i class="bi bi-lightbulb me-1"></i>
                                        Common types: Annual, Sick, Maternity, Study Leave
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Column -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Leave Types List</h5>
                                <span class="badge bg-primary"><?= count($leave_types) ?> types</span>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($leave_types): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th width="5%">#</th>
                                                <th width="35%">Leave Type</th>
                                                <th width="15%">Type</th>
                                                <th width="15%">Default Days</th>
                                                <th width="15%">Usage</th>
                                                <th width="15%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($leave_types as $index => $type): ?>
                                            <tr>
                                                <td class="text-muted"><?= $index + 1 ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($type['LeaveTypeName']) ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($type['IsRankBased']): ?>
                                                    <span class="badge bg-warning rank-badge">
                                                        <i class="bi bi-star me-1"></i>Rank-Based
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="badge bg-success rank-badge">
                                                        <i class="bi bi-check-circle me-1"></i>Fixed
                                                    </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($type['DefaultDays']): ?>
                                                    <span class="badge bg-primary"><?= $type['DefaultDays'] ?> days</span>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info usage-badge">
                                                        <?= $type['usage_count'] ?> requests
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?edit=<?= $type['LeaveTypeID'] ?>" 
                                                           class="btn btn-outline-primary btn-sm" 
                                                           title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <?php if ($type['usage_count'] == 0): ?>
                                                        <a href="?delete=<?= $type['LeaveTypeID'] ?>" 
                                                           class="btn btn-outline-danger btn-sm" 
                                                           title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete this leave type?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                        <?php else: ?>
                                                        <button class="btn btn-outline-secondary btn-sm" 
                                                                title="Cannot delete - in use" disabled>
                                                            <i class="bi bi-lock"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center p-5">
                                    <i class="bi bi-calendar-x display-4 text-muted"></i>
                                    <h5 class="mt-3 text-muted">No Leave Types Found</h5>
                                    <p class="text-muted">Start by adding your first leave type using the form on the left.</p>
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
        // Auto-clear default days when rank-based is enabled
        document.getElementById('is_rank_based').addEventListener('change', function() {
            const defaultDaysField = document.getElementById('default_days');
            if (this.checked) {
                defaultDaysField.value = '';
                defaultDaysField.setAttribute('placeholder', 'Not applicable for rank-based');
            } else {
                defaultDaysField.setAttribute('placeholder', 'e.g., 21');
            }
        });

        // Form validation
        document.getElementById('leaveTypeForm').addEventListener('submit', function(e) {
            const leaveName = document.getElementById('leave_name').value.trim();
            const isRankBased = document.getElementById('is_rank_based').checked;
            const defaultDays = document.getElementById('default_days').value;

            if (!leaveName) {
                e.preventDefault();
                alert('Please enter a leave type name.');
                return;
            }

            if (!isRankBased && !defaultDays) {
                if (!confirm('No default days specified for this fixed leave type. Continue anyway?')) {
                    e.preventDefault();
                    return;
                }
            }

            if (isRankBased && defaultDays) {
                if (!confirm('Default days will be ignored for rank-based leave types. Continue?')) {
                    e.preventDefault();
                    return;
                }
            }
        });

        // Auto-dismiss alerts after 5 seconds
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
