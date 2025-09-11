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

// Handle Delete
if (isset($_GET['delete'])) {
    $branchId = trim($_GET['delete']);
    try {
        // Check if branch is in use by employees
        $checkEmp = $conn->prepare("SELECT COUNT(*) FROM tbl_employees WHERE BranchID = ?");
        $checkEmp->execute([$branchId]);
        $empCount = $checkEmp->fetchColumn();
        
        // Check if branch is in use by attendance records
        $checkAtt = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance WHERE BranchID = ?");
        $checkAtt->execute([$branchId]);
        $attCount = $checkAtt->fetchColumn();
        
        if ($empCount > 0 || $attCount > 0) {
            $message = "Cannot delete branch '{$branchId}' as it has {$empCount} employees and {$attCount} attendance records.";
            $messageType = 'error';
        } else {
            // Delete branch beacons and wifi first (if tables exist)
            try {
                $conn->prepare("DELETE FROM tbl_branch_beacons WHERE BranchID = ?")->execute([$branchId]);
                $conn->prepare("DELETE FROM tbl_branch_wifi WHERE BranchID = ?")->execute([$branchId]);
            } catch (PDOException $e) {
                // Tables might not exist, continue
            }
            
            // Delete the branch
            $stmt = $conn->prepare("DELETE FROM tbl_branches WHERE BranchID = ?");
            $stmt->execute([$branchId]);
            $message = "Branch '{$branchId}' deleted successfully!";
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Error deleting branch: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'BranchName';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Build query with search and sorting
$whereClause = '';
$params = [];
if ($search) {
    $whereClause = "WHERE BranchID LIKE ? OR BranchName LIKE ? OR BranchLocation LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$validSortColumns = ['BranchID', 'BranchName', 'BranchLocation', 'AllowedRadius'];
if (!in_array($sortBy, $validSortColumns)) {
    $sortBy = 'BranchName';
}

// Fetch branches with employee counts
$sql = "
    SELECT b.*, 
           COALESCE(emp.employee_count, 0) as employee_count,
           COALESCE(att.attendance_count, 0) as attendance_count
    FROM tbl_branches b
    LEFT JOIN (
        SELECT BranchID, COUNT(*) as employee_count 
        FROM tbl_employees 
        GROUP BY BranchID
    ) emp ON b.BranchID = emp.BranchID
    LEFT JOIN (
        SELECT BranchID, COUNT(*) as attendance_count 
        FROM tbl_attendance 
        GROUP BY BranchID
    ) att ON b.BranchID = att.BranchID
    $whereClause
    ORDER BY $sortBy $sortOrder
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total statistics
$totalBranches = $conn->query("SELECT COUNT(*) FROM tbl_branches")->fetchColumn();
$totalEmployees = $conn->query("SELECT COUNT(*) FROM tbl_employees")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Branches - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border: 0; }
        .form-label { font-weight: 600; color: #374151; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .table th { background-color: #f8f9fa; font-weight: 600; border-top: 0; }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .branch-card { transition: transform 0.2s, box-shadow 0.2s; }
        .branch-card:hover { transform: translateY(-2px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .coordinate-text { font-family: 'Courier New', monospace; font-size: 0.9em; }
        .sortable { cursor: pointer; user-select: none; }
        .sortable:hover { background-color: #e9ecef; }
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
                            <a href="add_branch.php" class="nav-link">
                                <i class="bi bi-plus-circle me-2"></i>Add Branch
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="view_branches.php" class="nav-link active">
                                <i class="bi bi-list me-2"></i>View All Branches
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="bi bi-buildings-fill me-2 text-primary"></i>Branch Management</h2>
                        <p class="text-muted mb-0">Manage all branch locations and their settings</p>
                    </div>
                    <a href="add_branch.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Add New Branch
                    </a>
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
                                        <h6 class="card-title">Total Branches</h6>
                                        <h3 class="mb-0"><?= $totalBranches ?></h3>
                                        <small>Active Locations</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-buildings display-4 opacity-50"></i>
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
                                        <h6 class="card-title">Total Employees</h6>
                                        <h3 class="mb-0"><?= $totalEmployees ?></h3>
                                        <small>Across All Branches</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-people display-4 opacity-50"></i>
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
                                        <h6 class="card-title">Showing Results</h6>
                                        <h3 class="mb-0"><?= count($branches) ?></h3>
                                        <small><?= $search ? "Filtered Results" : "All Branches" ?></small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-funnel display-4 opacity-50"></i>
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
                                        <h6 class="card-title">Avg Radius</h6>
                                        <h3 class="mb-0">
                                            <?php 
                                            $avgRadius = $branches ? round(array_sum(array_column($branches, 'AllowedRadius')) / count($branches), 2) : 0;
                                            echo $avgRadius;
                                            ?> km
                                        </h3>
                                        <small>Geofence Range</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-geo display-4 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter Controls -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search Branches</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           value="<?= htmlspecialchars($search) ?>" 
                                           placeholder="Search by ID, name, or location...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="sort" class="form-label">Sort By</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="BranchName" <?= $sortBy === 'BranchName' ? 'selected' : '' ?>>Branch Name</option>
                                    <option value="BranchID" <?= $sortBy === 'BranchID' ? 'selected' : '' ?>>Branch ID</option>
                                    <option value="BranchLocation" <?= $sortBy === 'BranchLocation' ? 'selected' : '' ?>>Location</option>
                                    <option value="AllowedRadius" <?= $sortBy === 'AllowedRadius' ? 'selected' : '' ?>>Radius</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="order" class="form-label">Order</label>
                                <select class="form-select" id="order" name="order">
                                    <option value="asc" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                                    <option value="desc" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>Descending</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel"></i>
                                </button>
                            </div>
                        </form>
                        <?php if ($search): ?>
                        <div class="mt-2">
                            <a href="view_branches.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-circle me-1"></i>Clear Search
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Branches List -->
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Branches List</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="toggleView('table')" id="tableViewBtn">
                                <i class="bi bi-table"></i> Table
                            </button>
                            <button class="btn btn-primary" onclick="toggleView('cards')" id="cardsViewBtn">
                                <i class="bi bi-grid"></i> Cards
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($branches): ?>
                        <!-- Table View -->
                        <div id="tableView" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th class="sortable" onclick="sortTable('BranchID')">
                                                Branch ID 
                                                <?= $sortBy === 'BranchID' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?>
                                            </th>
                                            <th class="sortable" onclick="sortTable('BranchName')">
                                                Branch Name
                                                <?= $sortBy === 'BranchName' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?>
                                            </th>
                                            <th class="sortable" onclick="sortTable('BranchLocation')">
                                                Location
                                                <?= $sortBy === 'BranchLocation' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?>
                                            </th>
                                            <th>Coordinates</th>
                                            <th class="sortable" onclick="sortTable('AllowedRadius')">
                                                Radius
                                                <?= $sortBy === 'AllowedRadius' ? ($sortOrder === 'ASC' ? '↑' : '↓') : '' ?>
                                            </th>
                                            <th>Employees</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($branches as $branch): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($branch['BranchID']) ?></td>
                                            <td><?= htmlspecialchars($branch['BranchName']) ?></td>
                                            <td><?= htmlspecialchars($branch['BranchLocation']) ?></td>
                                            <td class="coordinate-text">
                                                <small>
                                                    <?= $branch['Latitude'] ?>, <?= $branch['Longitude'] ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= $branch['AllowedRadius'] ?> km</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $branch['employee_count'] ?> employees</span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-info btn-sm" 
                                                            onclick="showOnMap(<?= $branch['Latitude'] ?>, <?= $branch['Longitude'] ?>, '<?= htmlspecialchars($branch['BranchName'], ENT_QUOTES) ?>')"
                                                            title="View on Map">
                                                        <i class="bi bi-geo-alt"></i>
                                                    </button>
                                                    <a href="edit_branch.php?id=<?= urlencode($branch['BranchID']) ?>" 
                                                       class="btn btn-outline-primary btn-sm" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if ($branch['employee_count'] == 0 && $branch['attendance_count'] == 0): ?>
                                                    <a href="?delete=<?= urlencode($branch['BranchID']) ?>" 
                                                       class="btn btn-outline-danger btn-sm" 
                                                       title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this branch?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                    <?php else: ?>
                                                    <button class="btn btn-outline-secondary btn-sm" 
                                                            title="Cannot delete - has employees or attendance records" disabled>
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
                        </div>

                        <!-- Cards View -->
                        <div id="cardsView" class="p-3">
                            <div class="row">
                                <?php foreach ($branches as $branch): ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card branch-card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0 fw-bold"><?= htmlspecialchars($branch['BranchName']) ?></h6>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($branch['BranchID']) ?></span>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted mb-2">
                                                <i class="bi bi-geo-alt me-2"></i>
                                                <?= htmlspecialchars($branch['BranchLocation']) ?>
                                            </p>
                                            <div class="row text-center mb-3">
                                                <div class="col-6">
                                                    <div class="border-end">
                                                        <h5 class="text-primary mb-0"><?= $branch['employee_count'] ?></h5>
                                                        <small class="text-muted">Employees</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <h5 class="text-info mb-0"><?= $branch['AllowedRadius'] ?> km</h5>
                                                    <small class="text-muted">Radius</small>
                                                </div>
                                            </div>
                                            <div class="coordinate-text text-muted small mb-3">
                                                <i class="bi bi-crosshair me-1"></i>
                                                <?= $branch['Latitude'] ?>, <?= $branch['Longitude'] ?>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="btn-group w-100">
                                                <button class="btn btn-outline-info btn-sm" 
                                                        onclick="showOnMap(<?= $branch['Latitude'] ?>, <?= $branch['Longitude'] ?>, '<?= htmlspecialchars($branch['BranchName'], ENT_QUOTES) ?>')"
                                                        title="View on Map">
                                                    <i class="bi bi-geo-alt me-1"></i>Map
                                                </button>
                                                <a href="edit_branch.php?id=<?= urlencode($branch['BranchID']) ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-pencil me-1"></i>Edit
                                                </a>
                                                <?php if ($branch['employee_count'] == 0 && $branch['attendance_count'] == 0): ?>
                                                <a href="?delete=<?= urlencode($branch['BranchID']) ?>" 
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirm('Are you sure you want to delete this branch?')">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center p-5">
                            <i class="bi bi-building-x display-4 text-muted"></i>
                            <h5 class="mt-3 text-muted">No Branches Found</h5>
                            <p class="text-muted">
                                <?= $search ? 'No branches match your search criteria.' : 'No branches have been added yet.' ?>
                            </p>
                            <a href="add_branch.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Add First Branch
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Modal -->
    <div class="modal fade" id="mapModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-geo-alt me-2"></i>Branch Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="mapContainer" style="height: 400px; border-radius: 0.375rem;">
                    </div>
                    <div class="mt-3">
                        <small class="text-muted" id="mapInfo"></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View toggle functionality
        function toggleView(viewType) {
            const tableView = document.getElementById('tableView');
            const cardsView = document.getElementById('cardsView');
            const tableBtn = document.getElementById('tableViewBtn');
            const cardsBtn = document.getElementById('cardsViewBtn');
            
            if (viewType === 'table') {
                tableView.style.display = 'block';
                cardsView.style.display = 'none';
                tableBtn.classList.add('btn-primary');
                tableBtn.classList.remove('btn-outline-primary');
                cardsBtn.classList.add('btn-outline-primary');
                cardsBtn.classList.remove('btn-primary');
                localStorage.setItem('branchView', 'table');
            } else {
                tableView.style.display = 'none';
                cardsView.style.display = 'block';
                cardsBtn.classList.add('btn-primary');
                cardsBtn.classList.remove('btn-outline-primary');
                tableBtn.classList.add('btn-outline-primary');
                tableBtn.classList.remove('btn-primary');
                localStorage.setItem('branchView', 'cards');
            }
        }

        // Initialize view based on user preference
        document.addEventListener('DOMContentLoaded', function() {
            const savedView = localStorage.getItem('branchView') || 'cards';
            toggleView(savedView);
        });

        // Sort table functionality
        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentOrder = urlParams.get('order') || 'asc';
            
            let newOrder = 'asc';
            if (currentSort === column && currentOrder === 'asc') {
                newOrder = 'desc';
            }
            
            urlParams.set('sort', column);
            urlParams.set('order', newOrder);
            
            window.location.search = urlParams.toString();
        }

        // Show branch on map
        function showOnMap(lat, lng, branchName) {
            const mapContainer = document.getElementById('mapContainer');
            const mapInfo = document.getElementById('mapInfo');
            
            mapContainer.innerHTML = `
                <iframe 
                    width="100%" 
                    height="400" 
                    frameborder="0" 
                    style="border:0; border-radius: 0.375rem;" 
                    src="https://www.openstreetmap.org/export/embed.html?bbox=${lng-0.01},${lat-0.01},${lng+0.01},${lat+0.01}&layer=mapnik&marker=${lat},${lng}" 
                    allowfullscreen>
                </iframe>
            `;
            
            mapInfo.textContent = `${branchName} - Location: ${lat}, ${lng}`;
            
            const modal = new bootstrap.Modal(document.getElementById('mapModal'));
            modal.show();
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

        // Auto-submit search form on Enter
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
