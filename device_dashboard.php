<?php
session_start();
include 'db.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check user role
if (!isset($_SESSION['user_role']) || !in_array(strtolower($_SESSION['user_role']), ['administrator', 'hr'])) {
    header("Location: employee_portal.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Dashboard - SignSync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #16a34a;
            --warning-color: #d97706;
            --danger-color: #dc2626;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        
        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .device-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .device-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        
        .device-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-online {
            background-color: #dcfce7;
            color: var(--success-color);
        }
        
        .status-offline {
            background-color: #fee2e2;
            color: var(--danger-color);
        }
        
        .activity-feed {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-network-wired me-2"></i>Device Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user-circle me-1"></i>
                    Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </span>
                <a class="btn btn-outline-light btn-sm" href="admin_dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Admin
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Stats Overview -->
        <div class="row mb-4" id="stats-overview">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background: var(--primary-color);">
                            <i class="fas fa-microchip"></i>
                        </div>
                        <div class="ms-3">
                            <div class="text-muted small">Total Devices</div>
                            <div class="fs-4 fw-bold" id="total-devices">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background: var(--success-color);">
                            <i class="fas fa-wifi"></i>
                        </div>
                        <div class="ms-3">
                            <div class="text-muted small">Online Now</div>
                            <div class="fs-4 fw-bold" id="online-devices">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background: var(--warning-color);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="ms-3">
                            <div class="text-muted small">Activity (24h)</div>
                            <div class="fs-4 fw-bold" id="activity-count">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon" style="background: var(--secondary-color);">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="ms-3">
                            <div class="text-muted small">Branches</div>
                            <div class="fs-4 fw-bold" id="branch-count">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-sliders-h me-2"></i>Device Management
                            </h5>
                            <div>
                                <button class="btn btn-outline-primary btn-sm me-2" onclick="discoverDevices()">
                                    <i class="fas fa-search me-1"></i>Discover Devices
                                </button>
                                <a href="device_registry.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Register Device
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <select class="form-select" id="device-type-filter">
                                    <option value="">All Device Types</option>
                                    <option value="wifi">WiFi Access Points</option>
                                    <option value="bluetooth">Bluetooth Devices</option>
                                    <option value="beacon">BLE Beacons</option>
                                    <option value="rfid">RFID Readers</option>
                                    <option value="camera">IP Cameras</option>
                                    <option value="sensor">IoT Sensors</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="branch-filter">
                                    <option value="">All Branches</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="status-filter">
                                    <option value="">All Status</option>
                                    <option value="1">Active Only</option>
                                    <option value="0">Inactive Only</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-secondary w-100" onclick="refreshDevices()">
                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <!-- Devices List -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Registered Devices
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="devices-list">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="mt-2 text-muted">Loading devices...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Feed -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Recent Activity
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="activity-feed" id="activity-feed">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="mt-2 text-muted">Loading activity...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Device Type Distribution Chart -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2"></i>Device Distribution by Type
                    </h5>
                    <div id="chart-loading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading chart...</span>
                        </div>
                        <div class="mt-2 text-muted">Loading device data...</div>
                    </div>
                    <canvas id="deviceTypeChart" width="400" height="100" style="display: none;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Device Details Modal -->
    <div class="modal fade" id="deviceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Device Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="device-details">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let deviceTypeChart = null;
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Chart.js is loaded
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded!');
                document.getElementById('chart-loading').innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Chart library failed to load. Please refresh the page.
                    </div>
                `;
                return;
            }
            
            loadStats();
            loadDevices();
            loadActivity();
            loadBranches();
            
            // Set up filters
            document.getElementById('device-type-filter').addEventListener('change', loadDevices);
            document.getElementById('branch-filter').addEventListener('change', loadDevices);
            document.getElementById('status-filter').addEventListener('change', loadDevices);
            
            // Auto-refresh every 30 seconds
            setInterval(() => {
                loadStats();
                loadActivity();
            }, 30000);
        });
        
        async function loadStats() {
            try {
                const response = await fetch('device_api.php?action=stats');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                // Handle API errors
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Update stats cards
                let totalDevices = 0;
                let activeDevices = 0;
                
                if (data.device_types && Array.isArray(data.device_types)) {
                    data.device_types.forEach(type => {
                        totalDevices += parseInt(type.count || 0);
                        activeDevices += parseInt(type.active_count || 0);
                    });
                }
                
                document.getElementById('total-devices').textContent = totalDevices;
                document.getElementById('online-devices').textContent = data.recently_active || 0;
                document.getElementById('activity-count').textContent = data.recent_activity_24h || 0;
                document.getElementById('branch-count').textContent = (data.branch_distribution && data.branch_distribution.length) || 0;
                
                // Update chart (will handle empty data gracefully)
                updateDeviceTypeChart(data.device_types || []);
                
            } catch (error) {
                console.error('Error loading stats:', error);
                
                // Show error state in stats
                document.getElementById('total-devices').textContent = '0';
                document.getElementById('online-devices').textContent = '0';
                document.getElementById('activity-count').textContent = '0';
                document.getElementById('branch-count').textContent = '0';
                
                // Update chart with empty data
                updateDeviceTypeChart([]);
            }
        }
        
        async function loadDevices() {
            const devicesList = document.getElementById('devices-list');
            devicesList.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            `;
            
            try {
                const params = new URLSearchParams({
                    action: 'devices',
                    type: document.getElementById('device-type-filter').value,
                    branch: document.getElementById('branch-filter').value,
                    active: document.getElementById('status-filter').value
                });
                
                const response = await fetch(`device_api.php?${params}`);
                const data = await response.json();
                
                if (data.devices.length === 0) {
                    devicesList.innerHTML = `
                        <div class="text-center py-5">
                            <i class="fas fa-microchip fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No devices found</h5>
                            <p class="text-muted">Try adjusting your filters or register new devices.</p>
                        </div>
                    `;
                    return;
                }
                
                const devicesHtml = data.devices.map(device => {
                    const isOnline = device.MinutesSinceLastSeen !== null && device.MinutesSinceLastSeen < 60;
                    const statusClass = isOnline ? 'status-online' : 'status-offline';
                    const statusText = isOnline ? 'Online' : 'Offline';
                    const statusIcon = isOnline ? 'fa-circle' : 'fa-circle-dot';
                    
                    const deviceIcon = getDeviceIcon(device.DeviceType);
                    
                    return `
                        <div class="device-card p-3 mb-3" onclick="showDeviceDetails('${device.DeviceID}')">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="${deviceIcon} fa-2x text-primary"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${device.DeviceName}</h6>
                                    <small class="text-muted">
                                        ${device.DeviceType.toUpperCase()} | ${device.Identifier}
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        ${device.BranchName || 'No Branch'} | ${device.Location || 'No Location'}
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="device-status ${statusClass} mb-2">
                                        <i class="fas ${statusIcon} me-1"></i>${statusText}
                                    </div>
                                    <small class="text-muted">
                                        ${device.LastSeenAt ? 'Last seen: ' + formatDateTime(device.LastSeenAt) : 'Never seen'}
                                    </small>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                devicesList.innerHTML = devicesHtml;
                
            } catch (error) {
                console.error('Error loading devices:', error);
                devicesList.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading devices. Please try again.
                    </div>
                `;
            }
        }
        
        async function loadActivity() {
            const activityFeed = document.getElementById('activity-feed');
            
            try {
                const response = await fetch('device_api.php?action=activity&hours=24');
                const data = await response.json();
                
                if (data.activity.length === 0) {
                    activityFeed.innerHTML = `
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-2x text-muted mb-2"></i>
                            <div class="text-muted">No recent activity</div>
                        </div>
                    `;
                    return;
                }
                
                const activityHtml = data.activity.map(activity => {
                    const deviceIcon = getDeviceIcon(activity.DeviceType);
                    return `
                        <div class="activity-item">
                            <div class="d-flex align-items-start">
                                <i class="${deviceIcon} text-primary me-2 mt-1"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-medium">${activity.DeviceName}</div>
                                    <small class="text-muted">${activity.ActivityType}</small>
                                    <div class="text-muted small mt-1">
                                        ${formatDateTime(activity.Timestamp)}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                activityFeed.innerHTML = activityHtml;
                
            } catch (error) {
                console.error('Error loading activity:', error);
                activityFeed.innerHTML = `
                    <div class="alert alert-danger m-3">Error loading activity</div>
                `;
            }
        }
        
        async function loadBranches() {
            try {
                const response = await fetch('fetch_data.php');
                const data = await response.json();
                
                const branchFilter = document.getElementById('branch-filter');
                data.branches.forEach(branch => {
                    const option = document.createElement('option');
                    option.value = branch.BranchID;
                    option.textContent = branch.BranchName;
                    branchFilter.appendChild(option);
                });
                
            } catch (error) {
                console.error('Error loading branches:', error);
            }
        }
        
        function updateDeviceTypeChart(deviceTypes) {
            try {
                // Check if Chart.js is available
                if (typeof Chart === 'undefined') {
                    document.getElementById('chart-loading').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Chart library not available
                        </div>
                    `;
                    return;
                }
                
                // Hide loading and show chart
                document.getElementById('chart-loading').style.display = 'none';
                document.getElementById('deviceTypeChart').style.display = 'block';
                
                const ctx = document.getElementById('deviceTypeChart').getContext('2d');
                
                if (deviceTypeChart) {
                    deviceTypeChart.destroy();
                }
            } catch (error) {
                console.error('Error initializing chart:', error);
                document.getElementById('chart-loading').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading chart: ${error.message}
                    </div>
                `;
                return;
            }
            
            // Handle empty or null data
            if (!deviceTypes || deviceTypes.length === 0) {
                deviceTypeChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['No Devices Registered'],
                        datasets: [{
                            data: [1],
                            backgroundColor: ['#e2e8f0'],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function() {
                                        return 'No devices registered yet';
                                    }
                                }
                            }
                        }
                    }
                });
                return;
            }
            
            const labels = deviceTypes.map(type => type.DeviceType.toUpperCase());
            const data = deviceTypes.map(type => parseInt(type.count));
            const colors = [
                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', 
                '#8b5cf6', '#06b6d4', '#f97316', '#84cc16'
            ];
            
            deviceTypeChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors.slice(0, data.length),
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        }
        
        async function showDeviceDetails(deviceId) {
            const modal = new bootstrap.Modal(document.getElementById('deviceModal'));
            const modalBody = document.getElementById('device-details');
            
            modalBody.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            `;
            
            modal.show();
            
            try {
                const response = await fetch(`device_api.php?action=device&id=${deviceId}`);
                const data = await response.json();
                
                const device = data.device;
                const deviceIcon = getDeviceIcon(device.DeviceType);
                
                modalBody.innerHTML = `
                    <div class="row">
                        <div class="col-md-8">
                            <h4><i class="${deviceIcon} me-2"></i>${device.DeviceName}</h4>
                            <table class="table table-sm">
                                <tr><td><strong>Device Type:</strong></td><td>${device.DeviceType.toUpperCase()}</td></tr>
                                <tr><td><strong>Identifier:</strong></td><td><code>${device.Identifier}</code></td></tr>
                                <tr><td><strong>Branch:</strong></td><td>${device.BranchName || 'Not assigned'}</td></tr>
                                <tr><td><strong>Location:</strong></td><td>${device.Location || 'Not specified'}</td></tr>
                                <tr><td><strong>Manufacturer:</strong></td><td>${device.Manufacturer || 'Unknown'}</td></tr>
                                <tr><td><strong>Model:</strong></td><td>${device.Model || 'Unknown'}</td></tr>
                                <tr><td><strong>Status:</strong></td><td>${device.IsActive ? 'Active' : 'Inactive'}</td></tr>
                                <tr><td><strong>Created:</strong></td><td>${formatDateTime(device.CreatedAt)}</td></tr>
                                <tr><td><strong>Last Seen:</strong></td><td>${device.LastSeenAt ? formatDateTime(device.LastSeenAt) : 'Never'}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <h6>Recent Activity</h6>
                            <div class="activity-list" style="max-height: 300px; overflow-y: auto;">
                                ${data.recent_activity.map(activity => `
                                    <div class="mb-2 p-2 bg-light rounded">
                                        <strong>${activity.ActivityType}</strong><br>
                                        <small class="text-muted">${formatDateTime(activity.Timestamp)}</small>
                                    </div>
                                `).join('') || '<p class="text-muted">No recent activity</p>'}
                            </div>
                        </div>
                    </div>
                `;
                
            } catch (error) {
                console.error('Error loading device details:', error);
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        Error loading device details. Please try again.
                    </div>
                `;
            }
        }
        
        async function discoverDevices() {
            try {
                const response = await fetch('device_api.php?action=discover');
                const data = await response.json();
                
                alert(`Discovery scan completed!\n\nFound ${data.discovered_devices.length} devices:\n` +
                      data.discovered_devices.map(d => `• ${d.name} (${d.type})`).join('\n'));
                
            } catch (error) {
                console.error('Error discovering devices:', error);
                alert('Error during device discovery. Please try again.');
            }
        }
        
        function refreshDevices() {
            loadStats();
            loadDevices();
            loadActivity();
        }
        
        function getDeviceIcon(deviceType) {
            const icons = {
                'wifi': 'fas fa-wifi',
                'bluetooth': 'fab fa-bluetooth',
                'beacon': 'fas fa-broadcast-tower',
                'rfid': 'fas fa-id-card',
                'camera': 'fas fa-video',
                'sensor': 'fas fa-thermometer-half'
            };
            return icons[deviceType.toLowerCase()] || 'fas fa-microchip';
        }
        
        function formatDateTime(dateTime) {
            if (!dateTime) return 'Never';
            const date = new Date(dateTime);
            return date.toLocaleString();
        }
    </script>
</body>
</html>
