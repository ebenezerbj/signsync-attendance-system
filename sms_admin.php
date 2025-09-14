<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Service Administration - SIGNSYNC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.15s ease-in-out;
        }
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .metric-card.success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        .metric-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .metric-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .template-preview {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 1rem;
            margin: 0.5rem 0;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .nav-pills .nav-link.active {
            background-color: #007bff;
        }
        .sms-log {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            background-color: #f8f9fa;
            border-radius: 0.25rem;
            padding: 0.5rem;
            margin: 0.25rem 0;
        }
    </style>
</head>
<body class="bg-light">
    <?php
    require_once 'db.php';
    require_once 'SignSyncSMSService.php';
    require_once 'sms_config.php';
    
    // Initialize SMS service
    try {
        $smsService = createSMSService($conn);
        $health = getSMSServiceHealth($conn);
        $stats = $smsService->getStatistics('24h');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'send_test_sms':
                        $phone = $_POST['test_phone'];
                        $message = $_POST['test_message'];
                        $result = $smsService->sendMessage($phone, $message);
                        $success = "Test SMS sent successfully!";
                        break;
                        
                    case 'send_template_sms':
                        $template = $_POST['template_name'];
                        $phone = $_POST['template_phone'];
                        $data = [];
                        if (isset($_POST['template_data'])) {
                            parse_str($_POST['template_data'], $data);
                        }
                        $result = $smsService->sendTemplateMessage($template, $phone, $data);
                        $success = "Template SMS sent successfully!";
                        break;
                        
                    case 'update_config':
                        $key = $_POST['config_key'];
                        $value = $_POST['config_value'];
                        updateSMSConfig($conn, $key, $value);
                        $success = "Configuration updated successfully!";
                        break;
                        
                    case 'process_queue':
                        $processed = $smsService->processQueue($_POST['batch_size'] ?? 10);
                        $success = "Processed $processed messages from queue.";
                        break;
                        
                    case 'cleanup_logs':
                        $cleaned = $smsService->cleanup();
                        $success = "Cleaned up {$cleaned['deleted_logs']} logs and {$cleaned['deleted_queue']} queue items.";
                        break;
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    $activeTab = $_GET['tab'] ?? 'dashboard';
    ?>
    
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-chat-dots"></i> SIGNSYNC SMS Administration
            </a>
            <div class="navbar-nav">
                <a class="nav-link text-white" href="admin_dashboard.php">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-pills nav-justified mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>" href="?tab=dashboard">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'send' ? 'active' : '' ?>" href="?tab=send">
                    <i class="bi bi-send"></i> Send SMS
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'templates' ? 'active' : '' ?>" href="?tab=templates">
                    <i class="bi bi-file-text"></i> Templates
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'logs' ? 'active' : '' ?>" href="?tab=logs">
                    <i class="bi bi-journal-text"></i> Logs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'config' ? 'active' : '' ?>" href="?tab=config">
                    <i class="bi bi-gear"></i> Configuration
                </a>
            </li>
        </ul>
        
        <?php if ($activeTab === 'dashboard'): ?>
            <!-- Dashboard Tab -->
            <div class="row">
                <!-- Statistics Cards -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card metric-card success">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?= $stats['sent'] ?></h3>
                            <p class="card-text">SMS Sent (24h)</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card metric-card warning">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?= $stats['failed'] ?></h3>
                            <p class="card-text">Failed (24h)</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card metric-card info">
                        <div class="card-body text-center">
                            <h3 class="card-title"><?= $stats['success_rate'] ?>%</h3>
                            <p class="card-text">Success Rate</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="card metric-card">
                        <div class="card-body text-center">
                            <h3 class="card-title">$<?= number_format($stats['total_cost'], 2) ?></h3>
                            <p class="card-text">Total Cost (24h)</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- System Health -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-heart-pulse"></i> System Health
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <span class="badge bg-<?= $health['status'] === 'healthy' ? 'success' : 'danger' ?> status-badge">
                                    <?= strtoupper($health['status']) ?>
                                </span>
                            </div>
                            <?php foreach ($health['checks'] as $check => $status): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-capitalize"><?= str_replace('_', ' ', $check) ?></span>
                                    <span class="badge bg-<?= strpos($status, 'OK') !== false ? 'success' : 'danger' ?> status-badge">
                                        <?= $status ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-lightning"></i> Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="process_queue">
                                <div class="input-group">
                                    <input type="number" name="batch_size" class="form-control" placeholder="Batch Size" value="10" min="1" max="100">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-play"></i> Process Queue
                                    </button>
                                </div>
                            </form>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="cleanup_logs">
                                <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Are you sure you want to clean up old logs?')">
                                    <i class="bi bi-trash"></i> Cleanup Old Logs
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($activeTab === 'send'): ?>
            <!-- Send SMS Tab -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-send"></i> Send Test SMS
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="send_test_sms">
                                <div class="mb-3">
                                    <label for="test_phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="test_phone" name="test_phone" 
                                           placeholder="233XXXXXXXXX" required>
                                    <div class="form-text">Enter phone number in international format</div>
                                </div>
                                <div class="mb-3">
                                    <label for="test_message" class="form-label">Message</label>
                                    <textarea class="form-control" id="test_message" name="test_message" 
                                              rows="3" maxlength="160" required>Hello from SIGNSYNC! This is a test message.</textarea>
                                    <div class="form-text">Maximum 160 characters</div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send"></i> Send Test SMS
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-file-text"></i> Send Template SMS
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="send_template_sms">
                                <div class="mb-3">
                                    <label for="template_name" class="form-label">Template</label>
                                    <select class="form-select" id="template_name" name="template_name" required>
                                        <option value="">Select a template...</option>
                                        <option value="attendance_clockin">Clock In Notification</option>
                                        <option value="attendance_clockout">Clock Out Notification</option>
                                        <option value="pin_reset">PIN Reset</option>
                                        <option value="pin_setup">PIN Setup</option>
                                        <option value="late_arrival">Late Arrival</option>
                                        <option value="missed_clockout">Missed Clock Out</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="template_phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="template_phone" name="template_phone" 
                                           placeholder="233XXXXXXXXX" required>
                                </div>
                                <div class="mb-3">
                                    <label for="template_data" class="form-label">Template Data</label>
                                    <textarea class="form-control" id="template_data" name="template_data" 
                                              rows="3" placeholder="name=John Doe&time=09:00&status=On Time"></textarea>
                                    <div class="form-text">Enter data as key=value pairs separated by &</div>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-send"></i> Send Template SMS
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($activeTab === 'templates'): ?>
            <!-- Templates Tab -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-text"></i> SMS Templates
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $templates = $conn->query("SELECT * FROM tbl_sms_templates ORDER BY category, template_name")->fetchAll(PDO::FETCH_ASSOC);
                    $currentCategory = '';
                    ?>
                    
                    <?php foreach ($templates as $template): ?>
                        <?php if ($template['category'] !== $currentCategory): ?>
                            <?php $currentCategory = $template['category']; ?>
                            <h6 class="text-uppercase text-muted mt-4 mb-3"><?= ucfirst($currentCategory) ?></h6>
                        <?php endif; ?>
                        
                        <div class="template-preview">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0"><?= htmlspecialchars($template['template_name']) ?></h6>
                                <span class="badge bg-<?= $template['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $template['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                            <p class="mb-2 text-muted small"><?= htmlspecialchars($template['description']) ?></p>
                            <div class="mb-2">
                                <code><?= htmlspecialchars($template['template_content']) ?></code>
                            </div>
                            <?php if ($template['variables']): ?>
                                <div class="small text-muted">
                                    <strong>Variables:</strong> 
                                    <?php
                                    $variables = json_decode($template['variables'], true);
                                    echo implode(', ', array_map(function($v) { return '{' . $v . '}'; }, $variables));
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        <?php elseif ($activeTab === 'logs'): ?>
            <!-- Logs Tab -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-journal-text"></i> SMS Logs
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $logs = $smsService->getDeliveryReport(null, null, null, 50);
                    ?>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Message</th>
                                    <th>Cost</th>
                                    <th>Sent At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= $log['id'] ?></td>
                                        <td><?= htmlspecialchars($log['phone_number']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $log['status'] === 'sent' ? 'success' : ($log['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                                <?= ucfirst($log['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($log['message']) ?>">
                                                <?= htmlspecialchars($log['message']) ?>
                                            </span>
                                        </td>
                                        <td><?= $log['cost'] ? '$' . number_format($log['cost'], 4) : '-' ?></td>
                                        <td><?= $log['sent_at'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        <?php elseif ($activeTab === 'config'): ?>
            <!-- Configuration Tab -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-gear"></i> SMS Configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $configs = $conn->query("SELECT * FROM tbl_sms_config ORDER BY setting_key")->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="update_config">
                                
                                <?php foreach ($configs as $config): ?>
                                    <div class="mb-3">
                                        <label for="config_<?= $config['id'] ?>" class="form-label">
                                            <?= htmlspecialchars($config['setting_key']) ?>
                                        </label>
                                        <input type="hidden" name="config_key" value="<?= htmlspecialchars($config['setting_key']) ?>">
                                        <input type="text" class="form-control" id="config_<?= $config['id'] ?>" 
                                               name="config_value" value="<?= htmlspecialchars($config['setting_value']) ?>">
                                        <?php if ($config['description']): ?>
                                            <div class="form-text"><?= htmlspecialchars($config['description']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Configuration
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle"></i> Configuration Help
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6>Environment Variables</h6>
                            <p class="small text-muted">For security, store sensitive values like API keys in environment variables:</p>
                            <ul class="small">
                                <li><code>SMS_SMSONLINEGH_API_KEY</code></li>
                                <li><code>SMS_SENDER_ID</code></li>
                                <li><code>SMS_ENVIRONMENT</code></li>
                            </ul>
                            
                            <h6 class="mt-3">Rate Limiting</h6>
                            <p class="small text-muted">Protects against SMS spam by limiting the number of messages per time window.</p>
                            
                            <h6 class="mt-3">Queue Settings</h6>
                            <p class="small text-muted">Controls batch processing and retry behavior for failed messages.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counter for SMS message
        document.getElementById('test_message')?.addEventListener('input', function() {
            const maxLength = 160;
            const currentLength = this.value.length;
            const remaining = maxLength - currentLength;
            
            // Update form text
            const formText = this.nextElementSibling;
            formText.textContent = `${remaining} characters remaining`;
            formText.className = remaining < 20 ? 'form-text text-danger' : 'form-text';
        });
        
        // Auto-refresh dashboard every 30 seconds
        if (window.location.search.includes('tab=dashboard') || !window.location.search.includes('tab=')) {
            setTimeout(() => {
                if (!document.hidden) {
                    window.location.reload();
                }
            }, 30000);
        }
    </script>
</body>
</html>
