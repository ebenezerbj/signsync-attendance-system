<?php
/**
 * Employee Authentication Management Interface
 * Comprehensive admin panel for managing employee PINs and passwords
 */
session_start();
include 'db.php';
include 'EmployeeAuthenticationManager.php';

// Check if user is admin
if (!isset($_SESSION['user_role']) || strtolower($_SESSION['user_role']) !== 'administrator') {
    header('Location: login.php');
    exit;
}

$authManager = new EmployeeAuthenticationManager($conn);
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'reset_credentials':
            $employeeId = $_POST['employee_id'] ?? '';
            $resetPassword = isset($_POST['reset_password']);
            $resetPin = isset($_POST['reset_pin']);
            
            if (!empty($employeeId)) {
                $result = $authManager->resetEmployeeCredentials($employeeId, $resetPassword, $resetPin);
                if ($result['success']) {
                    $message = "Credentials reset successfully for $employeeId. ";
                    if (isset($result['new_credentials']['password'])) {
                        $message .= "New Password: " . $result['new_credentials']['password'] . ". ";
                    }
                    if (isset($result['new_credentials']['pin'])) {
                        $message .= "New PIN: " . $result['new_credentials']['pin'] . ". ";
                    }
                    $messageType = 'success';
                } else {
                    $message = "Failed to reset credentials: " . $result['message'];
                    $messageType = 'error';
                }
            }
            break;
            
        case 'generate_credentials':
            $employeeId = $_POST['employee_id'] ?? '';
            if (!empty($employeeId)) {
                $result = $authManager->ensureEmployeeCredentials($employeeId);
                if ($result['success'] && !empty($result['generated'])) {
                    $message = "Generated credentials for $employeeId. ";
                    if (isset($result['generated']['password'])) {
                        $message .= "Password: " . $result['generated']['password'] . ". ";
                    }
                    if (isset($result['generated']['pin'])) {
                        $message .= "PIN: " . $result['generated']['pin'] . ". ";
                    }
                    $messageType = 'success';
                } else {
                    $message = "Employee already has all credentials.";
                    $messageType = 'info';
                }
            }
            break;
            
        case 'clear_login_attempts':
            $employeeId = $_POST['employee_id'] ?? '';
            if (!empty($employeeId)) {
                $stmt = $conn->prepare("DELETE FROM tbl_login_attempts WHERE employee_id = ? AND success = 0");
                $stmt->execute([$employeeId]);
                $message = "Cleared failed login attempts for $employeeId";
                $messageType = 'success';
            }
            break;
    }
}

// Get authentication status for all employees
$authStatus = $authManager->getAuthenticationStatus();

// Get recent login attempts
$stmt = $conn->query("
    SELECT la.*, e.FullName
    FROM tbl_login_attempts la
    LEFT JOIN tbl_employees e ON la.employee_id = e.EmployeeID
    ORDER BY la.attempt_time DESC
    LIMIT 50
");
$recentAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Authentication Management - SIGNSYNC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center">
                        <h1 class="text-3xl font-bold text-gray-900">Employee Authentication Management</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <a href="admin_dashboard.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Message Display -->
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : ($messageType === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <?php
                $totalEmployees = count($authStatus);
                $withPasswords = count(array_filter($authStatus, fn($emp) => $emp['password_status'] === 'SET'));
                $withCustomPins = count(array_filter($authStatus, fn($emp) => !str_contains($emp['pin_status'], 'DEFAULT')));
                $recentFailures = count(array_filter($authStatus, fn($emp) => $emp['failed_attempts_last_hour'] > 0));
                ?>
                
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <span class="text-white font-bold">👥</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Employees</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $totalEmployees; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <span class="text-white font-bold">🔐</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">With Passwords</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $withPasswords; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                    <span class="text-white font-bold">📱</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Custom PINs</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $withCustomPins; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                    <span class="text-white font-bold">⚠️</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Recent Failures</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo $recentFailures; ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee Authentication Status Table -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md mb-8">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Employee Authentication Status</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Manage employee passwords and PINs</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Password Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PIN Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PIN Setup</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed Attempts</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($authStatus as $emp): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($emp['EmployeeID']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($emp['FullName']); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $emp['password_status'] === 'SET' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $emp['password_status']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo str_contains($emp['pin_status'], 'DEFAULT') ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo htmlspecialchars($emp['pin_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $emp['PINSetupComplete'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $emp['PINSetupComplete'] ? 'Complete' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900 <?php echo $emp['failed_attempts_last_hour'] > 0 ? 'font-bold text-red-600' : ''; ?>">
                                            <?php echo $emp['failed_attempts_last_hour']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <!-- Reset Credentials -->
                                            <button onclick="openResetModal('<?php echo $emp['EmployeeID']; ?>', '<?php echo htmlspecialchars($emp['FullName']); ?>')" 
                                                    class="text-indigo-600 hover:text-indigo-900">Reset</button>
                                            
                                            <!-- Generate Missing -->
                                            <?php if ($emp['password_status'] === 'MISSING' || str_contains($emp['pin_status'], 'DEFAULT')): ?>
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="action" value="generate_credentials">
                                                    <input type="hidden" name="employee_id" value="<?php echo $emp['EmployeeID']; ?>">
                                                    <button type="submit" class="text-green-600 hover:text-green-900">Generate</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Clear Attempts -->
                                            <?php if ($emp['failed_attempts_last_hour'] > 0): ?>
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="action" value="clear_login_attempts">
                                                    <input type="hidden" name="employee_id" value="<?php echo $emp['EmployeeID']; ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900">Clear</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Login Attempts -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Login Attempts</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Last 50 login attempts across all employees</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentAttempts as $attempt): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M j, Y H:i:s', strtotime($attempt['attempt_time'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($attempt['employee_id']); ?></div>
                                            <?php if ($attempt['FullName']): ?>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($attempt['FullName']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $attempt['login_type'] === 'pin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo strtoupper($attempt['login_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $attempt['success'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $attempt['success'] ? 'SUCCESS' : 'FAILED'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($attempt['ip_address']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($attempt['failure_reason']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Credentials Modal -->
    <div id="resetModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900" id="resetModalTitle">Reset Employee Credentials</h3>
                <form method="post" class="mt-4">
                    <input type="hidden" name="action" value="reset_credentials">
                    <input type="hidden" name="employee_id" id="resetEmployeeId">
                    
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="reset_password" class="mr-2">
                            <span class="text-sm">Reset Password (for web login)</span>
                        </label>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="reset_pin" class="mr-2">
                            <span class="text-sm">Reset PIN (for mobile app)</span>
                        </label>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeResetModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                            Reset Credentials
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openResetModal(employeeId, employeeName) {
            document.getElementById('resetEmployeeId').value = employeeId;
            document.getElementById('resetModalTitle').textContent = `Reset Credentials for ${employeeName}`;
            document.getElementById('resetModal').classList.remove('hidden');
        }
        
        function closeResetModal() {
            document.getElementById('resetModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('resetModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeResetModal();
            }
        });
    </script>
</body>
</html>
