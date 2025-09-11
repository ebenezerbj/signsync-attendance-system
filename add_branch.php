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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $BranchID = trim($_POST['BranchID']);
    $BranchName = trim($_POST['BranchName']);
    $BranchLocation = trim($_POST['BranchLocation']);
    $Latitude = trim($_POST['Latitude']);
    $Longitude = trim($_POST['Longitude']);
    $AllowedRadius = trim($_POST['AllowedRadius']);

    // Validation
    $errors = [];
    if (empty($BranchID)) $errors[] = 'Branch ID is required';
    if (empty($BranchName)) $errors[] = 'Branch Name is required';
    if (empty($BranchLocation)) $errors[] = 'Branch Location is required';
    if (!is_numeric($Latitude) || $Latitude < -90 || $Latitude > 90) $errors[] = 'Valid Latitude (-90 to 90) is required';
    if (!is_numeric($Longitude) || $Longitude < -180 || $Longitude > 180) $errors[] = 'Valid Longitude (-180 to 180) is required';
    if (!is_numeric($AllowedRadius) || $AllowedRadius <= 0) $errors[] = 'Allowed Radius must be a positive number';

    if (empty($errors)) {
        try {
            $sql = "INSERT INTO tbl_branches (BranchID, BranchName, BranchLocation, Latitude, Longitude, AllowedRadius)
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$BranchID, $BranchName, $BranchLocation, $Latitude, $Longitude, $AllowedRadius]);

            $message = 'Branch added successfully!';
            $messageType = 'success';
            
            // Clear form values on success
            $BranchID = $BranchName = $BranchLocation = $Latitude = $Longitude = $AllowedRadius = '';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = 'Branch ID already exists. Please use a different ID.';
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

// Get existing branches for reference
$branches = $conn->query("SELECT BranchID, BranchName, BranchLocation FROM tbl_branches ORDER BY BranchName")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Branch - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --bs-body-bg: #f4f7fc; }
        .card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border: 0; }
        .form-label { font-weight: 600; color: #374151; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); }
        .map-container { height: 300px; background: #e9ecef; border-radius: 0.375rem; }
        .branch-list { max-height: 300px; overflow-y: auto; }
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
                            <a href="add_branch.php" class="nav-link active">
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
                    <h2><i class="bi bi-geo-alt-fill me-2 text-primary"></i>Add New Branch</h2>
                    <small class="text-muted">Fields marked with * are required</small>
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
                                            <label for="BranchID" class="form-label">Branch ID *</label>
                                            <input type="text" class="form-control" id="BranchID" name="BranchID" 
                                                   value="<?= htmlspecialchars($BranchID ?? '') ?>" required 
                                                   placeholder="e.g., BR001">
                                            <div class="form-text">Unique identifier for the branch</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="BranchName" class="form-label">Branch Name *</label>
                                            <input type="text" class="form-control" id="BranchName" name="BranchName" 
                                                   value="<?= htmlspecialchars($BranchName ?? '') ?>" required 
                                                   placeholder="e.g., Main Office">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="BranchLocation" class="form-label">Branch Location *</label>
                                        <input type="text" class="form-control" id="BranchLocation" name="BranchLocation" 
                                               value="<?= htmlspecialchars($BranchLocation ?? '') ?>" required 
                                               placeholder="e.g., 123 Business St, City, Country">
                                        <div class="form-text">Full address of the branch</div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="Latitude" class="form-label">Latitude *</label>
                                            <input type="number" step="any" class="form-control" id="Latitude" name="Latitude" 
                                                   value="<?= htmlspecialchars($Latitude ?? '') ?>" required 
                                                   placeholder="e.g., 40.7128" min="-90" max="90">
                                            <div class="form-text">-90 to 90</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="Longitude" class="form-label">Longitude *</label>
                                            <input type="number" step="any" class="form-control" id="Longitude" name="Longitude" 
                                                   value="<?= htmlspecialchars($Longitude ?? '') ?>" required 
                                                   placeholder="e.g., -74.0060" min="-180" max="180">
                                            <div class="form-text">-180 to 180</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="AllowedRadius" class="form-label">Allowed Radius (KM) *</label>
                                            <input type="number" step="0.01" class="form-control" id="AllowedRadius" name="AllowedRadius" 
                                                   value="<?= htmlspecialchars($AllowedRadius ?? '') ?>" required 
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
                                            <i class="bi bi-plus-circle me-2"></i>Add Branch
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
                                <div class="mt-3">
                                    <small class="text-muted" id="locationInfo"></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Branches Column -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-buildings me-2"></i>Existing Branches (<?= count($branches) ?>)</h6>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($branches): ?>
                                <div class="branch-list">
                                    <?php foreach ($branches as $branch): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?= htmlspecialchars($branch['BranchName']) ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($branch['BranchID']) ?></small>
                                        </div>
                                        <p class="mb-1 small text-muted"><?= htmlspecialchars($branch['BranchLocation']) ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-center p-4 text-muted">
                                    <i class="bi bi-building display-4"></i>
                                    <p class="mt-2">No branches added yet</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tips Card -->
                        <div class="card mt-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Tips</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0 small">
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Use GPS coordinates for accurate location</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Set appropriate radius for attendance verification</li>
                                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Branch ID should be unique and descriptive</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Test location accuracy before finalizing</li>
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
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Getting location...';
                button.disabled = true;
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        document.getElementById('Latitude').value = position.coords.latitude.toFixed(6);
                        document.getElementById('Longitude').value = position.coords.longitude.toFixed(6);
                        button.innerHTML = originalText;
                        button.disabled = false;
                        
                        // Show success message
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success alert-dismissible fade show mt-2';
                        alert.innerHTML = `
                            <i class="bi bi-check-circle me-2"></i>Location captured successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.getElementById('branchForm').insertBefore(alert, document.getElementById('branchForm').firstChild);
                        
                        // Auto-dismiss after 3 seconds
                        setTimeout(() => {
                            if (alert.parentNode) {
                                alert.remove();
                            }
                        }, 3000);
                    },
                    function(error) {
                        button.innerHTML = originalText;
                        button.disabled = false;
                        alert('Error getting location: ' + error.message);
                    },
                    { timeout: 10000, enableHighAccuracy: true }
                );
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }
        
        function previewLocation() {
            const lat = document.getElementById('Latitude').value;
            const lng = document.getElementById('Longitude').value;
            const radius = document.getElementById('AllowedRadius').value;
            
            if (!lat || !lng) {
                alert('Please enter latitude and longitude first.');
                return;
            }
            
            // Show map card
            document.getElementById('mapCard').style.display = 'block';
            
            // Update location info
            const info = `Location: ${lat}, ${lng}${radius ? ` | Radius: ${radius} km` : ''}`;
            document.getElementById('locationInfo').textContent = info;
            
            // Replace map container with actual embedded map
            const mapContainer = document.getElementById('mapContainer');
            mapContainer.innerHTML = `
                <iframe 
                    width="100%" 
                    height="300" 
                    frameborder="0" 
                    style="border:0; border-radius: 0.375rem;" 
                    src="https://www.openstreetmap.org/export/embed.html?bbox=${lng-0.01},${lat-0.01},${lng+0.01},${lat+0.01}&layer=mapnik&marker=${lat},${lng}" 
                    allowfullscreen>
                </iframe>
            `;
            
            // Scroll to map
            document.getElementById('mapCard').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Form validation
        document.getElementById('branchForm').addEventListener('submit', function(e) {
            const lat = parseFloat(document.getElementById('Latitude').value);
            const lng = parseFloat(document.getElementById('Longitude').value);
            const radius = parseFloat(document.getElementById('AllowedRadius').value);
            
            if (lat < -90 || lat > 90) {
                e.preventDefault();
                alert('Latitude must be between -90 and 90 degrees.');
                return;
            }
            
            if (lng < -180 || lng > 180) {
                e.preventDefault();
                alert('Longitude must be between -180 and 180 degrees.');
                return;
            }
            
            if (radius <= 0) {
                e.preventDefault();
                alert('Allowed radius must be greater than 0.');
                return;
            }
        });
    </script>
</body>
</html>
