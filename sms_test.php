<?php
/**
 * SMS Testing Interface
 * 
 * A comprehensive testing interface for the SIGNSYNC SMS service
 * with real-time monitoring and diagnostic capabilities.
 */

require_once 'db.php';
require_once 'SignSyncSMSService.php';
require_once 'sms_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Testing & Monitoring - SIGNSYNC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-healthy { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
        
        .metric-card {
            transition: transform 0.2s ease-in-out;
        }
        .metric-card:hover {
            transform: translateY(-2px);
        }
        
        .log-entry {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            background-color: #f8f9fa;
            border-radius: 0.25rem;
            padding: 0.5rem;
            margin: 0.25rem 0;
            border-left: 4px solid #007bff;
        }
        
        .log-success { border-left-color: #28a745; }
        .log-error { border-left-color: #dc3545; }
        .log-warning { border-left-color: #ffc107; }
        
        .test-result {
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 0.5rem 0;
        }
        
        .auto-refresh {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-chat-square-dots"></i> SMS Testing & Monitoring
            </a>
            <div class="navbar-nav">
                <a class="nav-link text-white" href="sms_admin.php">
                    <i class="bi bi-gear"></i> SMS Admin
                </a>
                <a class="nav-link text-white" href="admin_dashboard.php">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Auto-refresh controls -->
    <div class="auto-refresh">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="autoRefresh">
            <label class="form-check-label" for="autoRefresh">
                <small>Auto-refresh</small>
            </label>
        </div>
    </div>
    
    <div class="container-fluid mt-4">
        <!-- System Status Row -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-activity"></i> System Status
                            <span id="lastUpdate" class="badge bg-secondary ms-2"></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="systemStatus" class="row">
                            <!-- Status indicators will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Metrics Row -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 id="sentToday">-</h3>
                        <p class="mb-0">Sent Today</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 id="successRate">-</h3>
                        <p class="mb-0">Success Rate</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card bg-warning text-white">
                    <div class="card-body text-center">
                        <h3 id="queueSize">-</h3>
                        <p class="mb-0">Queue Size</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card metric-card bg-info text-white">
                    <div class="card-body text-center">
                        <h3 id="totalCost">-</h3>
                        <p class="mb-0">Cost (24h)</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Testing Tabs -->
        <ul class="nav nav-tabs" id="testingTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="direct-tab" data-bs-toggle="tab" data-bs-target="#direct" type="button" role="tab">
                    <i class="bi bi-send"></i> Direct Send
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="template-tab" data-bs-toggle="tab" data-bs-target="#template" type="button" role="tab">
                    <i class="bi bi-file-text"></i> Template Test
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="provider-tab" data-bs-toggle="tab" data-bs-target="#provider" type="button" role="tab">
                    <i class="bi bi-cloud"></i> Provider Test
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                    <i class="bi bi-journal-text"></i> Recent Logs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="queue-tab" data-bs-toggle="tab" data-bs-target="#queue" type="button" role="tab">
                    <i class="bi bi-list-ul"></i> Queue Management
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="testingTabContent">
            <!-- Direct Send Tab -->
            <div class="tab-pane fade show active" id="direct" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Direct SMS Test</h5>
                        <form id="directSendForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="directPhone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="directPhone" placeholder="233XXXXXXXXX" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="directPriority" class="form-label">Priority</label>
                                        <select class="form-select" id="directPriority">
                                            <option value="2">Normal</option>
                                            <option value="1">Low</option>
                                            <option value="3">High</option>
                                            <option value="4">Urgent</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="directMessage" class="form-label">Message</label>
                                <textarea class="form-control" id="directMessage" rows="3" maxlength="160" required>SIGNSYNC SMS Test - System is working correctly!</textarea>
                                <div class="form-text">
                                    <span id="charCount">51</span>/160 characters
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Send Test SMS
                            </button>
                        </form>
                        <div id="directResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
            
            <!-- Template Test Tab -->
            <div class="tab-pane fade" id="template" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Template SMS Test</h5>
                        <form id="templateTestForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="templatePhone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="templatePhone" placeholder="233XXXXXXXXX" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="templateName" class="form-label">Template</label>
                                        <select class="form-select" id="templateName" required>
                                            <option value="">Select template...</option>
                                            <option value="attendance_clockin">Clock In Notification</option>
                                            <option value="attendance_clockout">Clock Out Notification</option>
                                            <option value="pin_reset">PIN Reset</option>
                                            <option value="pin_setup">PIN Setup</option>
                                            <option value="pin_changed">PIN Changed</option>
                                            <option value="late_arrival">Late Arrival</option>
                                            <option value="missed_clockout">Missed Clock Out</option>
                                            <option value="stress_alert">Stress Alert</option>
                                            <option value="emergency_alert">Emergency Alert</option>
                                            <option value="shift_reminder">Shift Reminder</option>
                                            <option value="leave_approved">Leave Approved</option>
                                            <option value="leave_rejected">Leave Rejected</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="templateData" class="form-label">Template Data (JSON)</label>
                                <textarea class="form-control" id="templateData" rows="4" placeholder='{"name": "Test User", "branch": "Main Office"}'>{}</textarea>
                                <div class="form-text">Provide data as JSON object. Default test values will be used for missing fields.</div>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-file-text"></i> Send Template SMS
                            </button>
                        </form>
                        <div id="templateResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
            
            <!-- Provider Test Tab -->
            <div class="tab-pane fade" id="provider" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Provider Configuration Test</h5>
                        <div class="mb-3">
                            <label for="providerSelect" class="form-label">Provider</label>
                            <select class="form-select" id="providerSelect">
                                <option value="smsonlinegh">SMS Online GH</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-info" onclick="testProvider()">
                            <i class="bi bi-cloud-check"></i> Test Provider
                        </button>
                        <div id="providerResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
            
            <!-- Logs Tab -->
            <div class="tab-pane fade" id="logs" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent SMS Logs</h5>
                        <button class="btn btn-outline-primary btn-sm" onclick="loadLogs()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="recentLogs">
                            <!-- Logs will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Queue Management Tab -->
            <div class="tab-pane fade" id="queue" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Queue Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <button class="btn btn-primary" onclick="processQueue()">
                                    <i class="bi bi-play"></i> Process Queue
                                </button>
                                <button class="btn btn-warning" onclick="clearOldLogs()">
                                    <i class="bi bi-trash"></i> Cleanup Old Logs
                                </button>
                            </div>
                        </div>
                        <div id="queueStatus">
                            <!-- Queue status will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let autoRefreshInterval;
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSystemStatus();
            loadStats();
            loadLogs();
            loadQueueStatus();
            updateCharCount();
            
            // Character counter
            document.getElementById('directMessage').addEventListener('input', updateCharCount);
            
            // Auto-refresh toggle
            document.getElementById('autoRefresh').addEventListener('change', function() {
                if (this.checked) {
                    autoRefreshInterval = setInterval(() => {
                        loadSystemStatus();
                        loadStats();
                        if (document.getElementById('logs-tab').classList.contains('active')) {
                            loadLogs();
                        }
                        if (document.getElementById('queue-tab').classList.contains('active')) {
                            loadQueueStatus();
                        }
                    }, 10000); // Refresh every 10 seconds
                } else {
                    clearInterval(autoRefreshInterval);
                }
            });
        });
        
        function updateCharCount() {
            const message = document.getElementById('directMessage').value;
            const count = message.length;
            const counter = document.getElementById('charCount');
            counter.textContent = count;
            counter.className = count > 150 ? 'text-danger' : count > 120 ? 'text-warning' : '';
        }
        
        async function loadSystemStatus() {
            try {
                const response = await fetch('sms_health.php?action=health');
                const health = await response.json();
                
                const statusDiv = document.getElementById('systemStatus');
                statusDiv.innerHTML = '';
                
                // Overall status
                const overallStatus = document.createElement('div');
                overallStatus.className = 'col-md-3 mb-2';
                overallStatus.innerHTML = `
                    <div class="d-flex align-items-center">
                        <span class="status-indicator status-${health.status === 'healthy' ? 'healthy' : 'error'}"></span>
                        <strong>Overall: ${health.status.toUpperCase()}</strong>
                    </div>
                `;
                statusDiv.appendChild(overallStatus);
                
                // Individual checks
                Object.entries(health.checks).forEach(([check, status]) => {
                    const checkDiv = document.createElement('div');
                    checkDiv.className = 'col-md-3 mb-2';
                    const isOk = status === 'OK' || status.includes('OK');
                    checkDiv.innerHTML = `
                        <div class="d-flex align-items-center">
                            <span class="status-indicator status-${isOk ? 'healthy' : 'error'}"></span>
                            <span class="text-capitalize">${check.replace('_', ' ')}: ${status}</span>
                        </div>
                    `;
                    statusDiv.appendChild(checkDiv);
                });
                
                document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
                
            } catch (error) {
                console.error('Failed to load system status:', error);
            }
        }
        
        async function loadStats() {
            try {
                const response = await fetch('sms_health.php?action=stats&timeframe=24h');
                const stats = await response.json();
                
                document.getElementById('sentToday').textContent = stats.sent || 0;
                document.getElementById('successRate').textContent = (stats.success_rate || 0) + '%';
                document.getElementById('totalCost').textContent = '$' + (stats.total_cost || 0).toFixed(2);
                
                // Get queue size
                const queueResponse = await fetch('sms_health.php?action=queue_status');
                const queueData = await queueResponse.json();
                const pendingCount = queueData.find(q => q.status === 'pending')?.count || 0;
                document.getElementById('queueSize').textContent = pendingCount;
                
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }
        
        async function loadLogs() {
            try {
                const response = await fetch('sms_health.php?action=recent_logs&limit=20');
                const logs = await response.json();
                
                const logsDiv = document.getElementById('recentLogs');
                logsDiv.innerHTML = '';
                
                if (logs.length === 0) {
                    logsDiv.innerHTML = '<p class="text-muted">No recent logs found.</p>';
                    return;
                }
                
                logs.forEach(log => {
                    const logDiv = document.createElement('div');
                    logDiv.className = `log-entry log-${log.status === 'sent' ? 'success' : 'error'}`;
                    logDiv.innerHTML = `
                        <div class="d-flex justify-content-between">
                            <span><strong>${log.phone_number}</strong> - ${log.status.toUpperCase()}</span>
                            <span class="text-muted">${log.sent_at}</span>
                        </div>
                        <div class="mt-1">${log.message.substring(0, 100)}${log.message.length > 100 ? '...' : ''}</div>
                    `;
                    logsDiv.appendChild(logDiv);
                });
                
            } catch (error) {
                console.error('Failed to load logs:', error);
            }
        }
        
        async function loadQueueStatus() {
            try {
                const response = await fetch('sms_health.php?action=queue_status');
                const queueData = await response.json();
                
                const statusDiv = document.getElementById('queueStatus');
                statusDiv.innerHTML = '';
                
                if (queueData.length === 0) {
                    statusDiv.innerHTML = '<p class="text-muted">Queue is empty.</p>';
                    return;
                }
                
                queueData.forEach(item => {
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'alert alert-info';
                    itemDiv.innerHTML = `
                        <strong>${item.status.toUpperCase()}</strong>: ${item.count} messages
                        <br><small>Oldest: ${item.oldest} | Newest: ${item.newest}</small>
                    `;
                    statusDiv.appendChild(itemDiv);
                });
                
            } catch (error) {
                console.error('Failed to load queue status:', error);
            }
        }
        
        // Direct SMS send
        document.getElementById('directSendForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const phone = document.getElementById('directPhone').value;
            const message = document.getElementById('directMessage').value;
            
            try {
                const response = await fetch('sms_health.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'test_send',
                        phone: phone,
                        message: message
                    })
                });
                
                const result = await response.json();
                showResult('directResult', result);
                
            } catch (error) {
                showResult('directResult', {success: false, message: 'Network error'});
            }
        });
        
        // Template SMS send
        document.getElementById('templateTestForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const phone = document.getElementById('templatePhone').value;
            const template = document.getElementById('templateName').value;
            const data = document.getElementById('templateData').value;
            
            try {
                const response = await fetch('sms_health.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'template_test',
                        phone: phone,
                        template: template,
                        data: data
                    })
                });
                
                const result = await response.json();
                showResult('templateResult', result);
                
            } catch (error) {
                showResult('templateResult', {success: false, message: 'Network error'});
            }
        });
        
        async function testProvider() {
            const provider = document.getElementById('providerSelect').value;
            
            try {
                const response = await fetch('sms_health.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'provider_test',
                        provider: provider
                    })
                });
                
                const result = await response.json();
                showResult('providerResult', result);
                
            } catch (error) {
                showResult('providerResult', {success: false, message: 'Network error'});
            }
        }
        
        async function processQueue() {
            try {
                const response = await fetch('sms_health.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'process_queue',
                        batch_size: 10
                    })
                });
                
                const result = await response.json();
                alert(result.message);
                loadQueueStatus();
                loadStats();
                
            } catch (error) {
                alert('Failed to process queue');
            }
        }
        
        async function clearOldLogs() {
            if (!confirm('Are you sure you want to clean up old logs?')) return;
            
            try {
                const response = await fetch('sms_health.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'cleanup'
                    })
                });
                
                const result = await response.json();
                alert(result.message);
                loadLogs();
                
            } catch (error) {
                alert('Failed to cleanup logs');
            }
        }
        
        function showResult(containerId, result) {
            const container = document.getElementById(containerId);
            const alertClass = result.success ? 'alert-success' : 'alert-danger';
            
            container.innerHTML = `
                <div class="alert ${alertClass} test-result">
                    <h6>${result.success ? 'Success' : 'Error'}</h6>
                    <p>${result.message}</p>
                    ${result.details ? `<details><summary>Details</summary><pre>${JSON.stringify(result.details, null, 2)}</pre></details>` : ''}
                </div>
            `;
        }
    </script>
</body>
</html>
