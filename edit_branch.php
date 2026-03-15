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

// Get Branch ID from URL
$branchId = isset($_GET['id']) ? trim($_GET['id']) : '';
if (empty($branchId)) {
    header('Location: view_branches.php');
    exit;
}

// Load branch data
try {
    $stmt = $conn->prepare("SELECT * FROM tbl_branches WHERE BranchID = ?");
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch) {
        header('Location: view_branches.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: view_branches.php');
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $BranchName = trim($_POST['BranchName']);
    $BranchLocation = trim($_POST['BranchLocation']);
    $Latitude = trim($_POST['Latitude']);
    $Longitude = trim($_POST['Longitude']);
    $AllowedRadius = trim($_POST['AllowedRadius']);

    // Validation
    $errors = [];
    if (empty($BranchName)) $errors[] = 'Branch Name is required';
    if (empty($BranchLocation)) $errors[] = 'Branch Location is required';
    if (!is_numeric($Latitude) || $Latitude < -90 || $Latitude > 90) $errors[] = 'Valid Latitude (-90 to 90) is required';
    if (!is_numeric($Longitude) || $Longitude < -180 || $Longitude > 180) $errors[] = 'Valid Longitude (-180 to 180) is required';
    if (!is_numeric($AllowedRadius) || $AllowedRadius <= 0) $errors[] = 'Allowed Radius must be a positive number';

    if (empty($errors)) {
        try {
            $sql = "UPDATE tbl_branches SET BranchName = ?, BranchLocation = ?, Latitude = ?, Longitude = ?, AllowedRadius = ? WHERE BranchID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$BranchName, $BranchLocation, $Latitude, $Longitude, $AllowedRadius, $branchId]);

            $message = 'Branch updated successfully!';
            $messageType = 'success';
            
            // Reload branch data
            $stmt = $conn->prepare("SELECT * FROM tbl_branches WHERE BranchID = ?");
            $stmt->execute([$branchId]);
            $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
        // Keep submitted values
        $branch['BranchName'] = $BranchName;
        $branch['BranchLocation'] = $BranchLocation;
        $branch['Latitude'] = $Latitude;
        $branch['Longitude'] = $Longitude;
        $branch['AllowedRadius'] = $AllowedRadius;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Branch - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border: 0; }
        .form-label { font-weight: 600; color: #374151; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .map-container { height: 300px; background: #e9ecef; border-radius: 0.375rem; }
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
                            <a href="view_branches.php" class="nav-link">
                                <i class="bi bi-list me-2"></i>View All Branches
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Branch</h2>
                    <a href="view_branches.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Branches
                    </a>
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
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Branch Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="branchForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="BranchID" class="form-label">Branch ID</label>
                                            <input type="text" class="form-control" id="BranchID" 
                                                   value="<?= htmlspecialchars($branch['BranchID']) ?>" readonly disabled>
                                            <div class="form-text">Branch ID cannot be changed</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="BranchName" class="form-label">Branch Name *</label>
                                            <input type="text" class="form-control" id="BranchName" name="BranchName" 
                                                   value="<?= htmlspecialchars($branch['BranchName']) ?>" required 
                                                   placeholder="e.g., Main Office">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="BranchLocation" class="form-label">Branch Location *</label>
                                        <input type="text" class="form-control" id="BranchLocation" name="BranchLocation" 
                                               value="<?= htmlspecialchars($branch['BranchLocation']) ?>" required 
                                               placeholder="e.g., 123 Business St, City, Country">
                                        <div class="form-text">Full address of the branch</div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="Latitude" class="form-label">Latitude *</label>
                                            <input type="number" step="any" class="form-control" id="Latitude" name="Latitude" 
                                                   value="<?= htmlspecialchars($branch['Latitude']) ?>" required 
                                                   placeholder="e.g., 40.7128" min="-90" max="90">
                                            <div class="form-text">-90 to 90</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="Longitude" class="form-label">Longitude *</label>
                                            <input type="number" step="any" class="form-control" id="Longitude" name="Longitude" 
                                                   value="<?= htmlspecialchars($branch['Longitude']) ?>" required 
                                                   placeholder="e.g., -74.0060" min="-180" max="180">
                                            <div class="form-text">-180 to 180</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="AllowedRadius" class="form-label">Allowed Radius (KM) *</label>
                                            <input type="number" step="0.01" class="form-control" id="AllowedRadius" name="AllowedRadius" 
                                                   value="<?= htmlspecialchars($branch['AllowedRadius']) ?>" required 
                                                   placeholder="e.g., 0.5" min="0.01">
                                            <div class="form-text">Geofence radius</div>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="button" class="btn btn-outline-secondary me-md-2" onclick="getCurrentLocation()">
                                            <i class="bi bi-geo me-2"></i>Use My Location
                                        </button>
                                        <button type="button" class="btn btn-outline-primary me-md-2" onclick="previewLocation()">
                                            <i class="bi bi-eye me-2"></i>Preview Location
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-2"></i>Update Branch
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Map Preview -->
                        <div class="card mt-4" id="mapCard" style="display: none;">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-map me-2"></i>Location Preview</h6>
                            </div>
                            <div class="card-body">
                                <div id="mapContainer" class="map-container d-flex align-items-center justify-content-center">
                                    <div class="text-center">
                                        <i class="bi bi-map display-4 text-muted"></i>
                                        <p class="text-muted mt-2">Map will appear here when location is previewed</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Sidebar -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Branch Details</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">You are editing branch <strong><?= htmlspecialchars($branch['BranchID']) ?></strong>.</p>
                                <ul class="list-unstyled small text-muted">
                                    <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>Coordinates define the center of the geofence</li>
                                    <li class="mb-2"><i class="bi bi-circle me-2"></i>Allowed Radius sets the geofence boundary</li>
                                    <li class="mb-2"><i class="bi bi-phone me-2"></i>Employees must be within the radius to clock in</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    document.getElementById('Latitude').value = position.coords.latitude.toFixed(6);
                    document.getElementById('Longitude').value = position.coords.longitude.toFixed(6);
                }, function(error) {
                    alert('Error getting location: ' + error.message);
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }

        function previewLocation() {
            const lat = document.getElementById('Latitude').value;
            const lng = document.getElementById('Longitude').value;
            if (lat && lng) {
                document.getElementById('mapCard').style.display = 'block';
                const container = document.getElementById('mapContainer');
                container.innerHTML = '<iframe width="100%" height="300" frameborder="0" style="border:0; border-radius: 0.375rem;" ' +
                    'src="https://www.openstreetmap.org/export/embed.html?bbox=' + 
                    (parseFloat(lng) - 0.01) + ',' + (parseFloat(lat) - 0.01) + ',' + 
                    (parseFloat(lng) + 0.01) + ',' + (parseFloat(lat) + 0.01) + 
                    '&marker=' + lat + ',' + lng + '&layers=M" allowfullscreen></iframe>';
            } else {
                alert('Please enter Latitude and Longitude first.');
            }
        }
    </script>
</body>
</html>
