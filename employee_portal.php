<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$employee_id = $_SESSION['user_id'];

// Fetch employee info
$empStmt = $conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
$empStmt->execute([$employee_id]);
$employee = $empStmt->fetch(PDO::FETCH_ASSOC);

// Fetch attendance records
$attStmt = $conn->prepare("SELECT * FROM tbl_attendance WHERE EmployeeID = ? ORDER BY AttendanceDate DESC, ClockIn DESC LIMIT 30");
$attStmt->execute([$employee_id]);
$attendance = $attStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch gamification data
$gamStmt = $conn->prepare("SELECT * FROM tbl_gamification WHERE EmployeeID = ?");
$gamStmt->execute([$employee_id]);
$gamify = $gamStmt->fetch(PDO::FETCH_ASSOC);

// Fetch leave balance
$leaveTotal = 20; // This could be dynamic from the employee's contract
$leaveStmt = $conn->prepare("SELECT SUM(DATEDIFF(end_date, start_date) + 1) AS used FROM tbl_leave_requests WHERE EmployeeID = ? AND status = 'approved'");
$leaveStmt->execute([$employee_id]);
$leaveUsed = $leaveStmt->fetchColumn() ?: 0;
$leaveBalance = $leaveTotal - $leaveUsed;

// Fetch correction/leave requests
$corrections = $conn->prepare("SELECT * FROM tbl_correction_requests WHERE EmployeeID = ? ORDER BY created_at DESC LIMIT 10");
$corrections->execute([$employee_id]);
$correctionRows = $corrections->fetchAll(PDO::FETCH_ASSOC);

$leaves = $conn->prepare("SELECT * FROM tbl_leave_requests WHERE EmployeeID = ? ORDER BY created_at DESC LIMIT 10");
$leaves->execute([$employee_id]);
$leaveRows = $leaves->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style>
        :root { --font-sans: 'Inter', sans-serif; }
        body { font-family: var(--font-sans); }
        .tab-active { border-color: #4f46e5; color: #4f46e5; }
        .tab-inactive { border-color: transparent; }
        [x-cloak] { display: none !important; }
    </style>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-100">

    <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" x-data="{ activeTab: 'dashboard' }">

        <!-- Header -->
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Welcome, <?= htmlspecialchars($employee['FullName']) ?></h1>
                <p class="text-slate-500">Employee ID: <?= htmlspecialchars($employee_id) ?></p>
            </div>
            <a href="logout.php" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-red-600 bg-red-100 rounded-lg hover:bg-red-200 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" /></svg>
                Logout
            </a>
        </header>

        <!-- Tab Navigation -->
        <div class="border-b border-slate-200 mb-6">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <a href="#" @click.prevent="activeTab = 'dashboard'" :class="activeTab === 'dashboard' ? 'tab-active' : 'tab-inactive'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm text-slate-500 hover:text-indigo-600">Dashboard</a>
                <a href="#" @click.prevent="activeTab = 'attendance'" :class="activeTab === 'attendance' ? 'tab-active' : 'tab-inactive'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm text-slate-500 hover:text-indigo-600">Attendance</a>
                <a href="#" @click.prevent="activeTab = 'leave'" :class="activeTab === 'leave' ? 'tab-active' : 'tab-inactive'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm text-slate-500 hover:text-indigo-600">Leave Requests</a>
                <a href="#" @click.prevent="activeTab = 'corrections'" :class="activeTab === 'corrections' ? 'tab-active' : 'tab-inactive'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm text-slate-500 hover:text-indigo-600">Corrections</a>
            </nav>
        </div>

        <!-- Tab Content -->
        <main>
            <!-- Dashboard Tab -->
            <div x-show="activeTab === 'dashboard'" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Key Stats -->
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-700 mb-4">Your Stats</h3>
                        <div class="space-y-4">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-indigo-100 rounded-full"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.657 7.343A8 8 0 0117.657 18.657z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9.522 17.286a8.003 8.003 0 01-2.18-1.886l-1.42-1.42a1 1 0 010-1.414l1.42-1.42a8.003 8.003 0 012.18-1.886M14.478 6.714a8.003 8.003 0 012.18 1.886l1.42 1.42a1 1 0 010 1.414l-1.42 1.42a8.003 8.003 0 01-2.18 1.886" /></svg></div>
                                <div>
                                    <p class="text-2xl font-bold text-slate-800"><?= $gamify['streak'] ?? 0 ?> Day Streak</p>
                                    <p class="text-sm text-slate-500">Current on-time streak</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-emerald-100 rounded-full"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg></div>
                                <div>
                                    <p class="text-2xl font-bold text-slate-800"><?= $leaveBalance ?> Days</p>
                                    <p class="text-sm text-slate-500">Leave balance remaining</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Pulse Survey -->
                    <div class="bg-white p-6 rounded-lg shadow-sm md:col-span-2">
                        <h3 class="text-lg font-semibold text-slate-700 mb-4">How are you feeling today?</h3>
                        <form method="post" action="save_pulse.php" class="space-y-4">
                            <div>
                                <label for="mood" class="sr-only">Mood</label>
                                <select name="mood" id="mood" class="w-full border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="happy">😊 Happy</option>
                                    <option value="neutral">😐 Neutral</option>
                                    <option value="sad">😞 Sad</option>
                                </select>
                            </div>
                            <div>
                                <label for="comment" class="sr-only">Comment</label>
                                <input type="text" name="comment" id="comment" placeholder="Any comments? (Optional)" class="w-full border-slate-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <button type="submit" class="w-full sm:w-auto px-6 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Submit Feedback</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Attendance Tab -->
            <div x-show="activeTab === 'attendance'" x-cloak>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-700 mb-4">Recent Attendance</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Clock In</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Clock Out</th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-200">
                                <?php foreach($attendance as $row): ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600"><?= date('D, M j, Y', strtotime($row['AttendanceDate'])) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600"><?= $row['ClockIn'] ? date('g:i A', strtotime($row['ClockIn'])) : 'N/A' ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600"><?= $row['ClockOut'] ? date('g:i A', strtotime($row['ClockOut'])) : 'N/A' ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $row['ClockInStatus'] === 'Late' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                            <?= htmlspecialchars($row['ClockInStatus']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Leave Tab -->
            <div x-show="activeTab === 'leave'" x-cloak>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-700 mb-4">Your Leave Requests</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Dates</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-200">
                                    <?php foreach($leaveRows as $row): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600"><?= date('M j', strtotime($row['start_date'])) ?> - <?= date('M j, Y', strtotime($row['end_date'])) ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600"><?= htmlspecialchars(ucfirst($row['type'])) ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-700 mb-4">Request New Leave</h3>
                        <form method="post" action="submit_leave.php" class="space-y-4">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-slate-700">Start Date</label>
                                <input type="date" name="start_date" id="start_date" required class="mt-1 block w-full border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-slate-700">End Date</label>
                                <input type="date" name="end_date" id="end_date" required class="mt-1 block w-full border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="leave_type" class="block text-sm font-medium text-slate-700">Type</label>
                                <select name="type" id="leave_type" class="mt-1 block w-full border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="annual">Annual</option><option value="sick">Sick</option><option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="leave_reason" class="block text-sm font-medium text-slate-700">Reason</label>
                                <input type="text" name="reason" id="leave_reason" required class="mt-1 block w-full border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <button type="submit" class="w-full px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Corrections Tab -->
            <div x-show="activeTab === 'corrections'" x-cloak>
                 <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-700 mb-4">Your Correction Requests</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-200">
                                    <?php foreach($correctionRows as $row): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600"><?= date('M j, Y', strtotime($row['date'])) ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($row['type']))) ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-700 mb-4">Request New Correction</h3>
                        <form method="post" action="submit_correction.php" class="space-y-4">
                            <div>
                                <label for="correction_date" class="block text-sm font-medium text-slate-700">Date of Issue</label>
                                <input type="date" name="date" id="correction_date" required class="mt-1 block w-full border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="correction_type" class="block text-sm font-medium text-slate-700">Type</label>
                                <select name="type" id="correction_type" class="mt-1 block w-full border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="missed_clockin">Missed Clock-In</option>
                                    <option value="missed_clockout">Missed Clock-Out</option>
                                    <option value="wrong_time">Wrong Time Entry</option>
                                </select>
                            </div>
                            <div>
                                <label for="correction_reason" class="block text-sm font-medium text-slate-700">Reason</label>
                                <input type="text" name="reason" id="correction_reason" required class="mt-1 block w-full border-slate-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <button type="submit" class="w-full px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <!-- AI Chat Widget Toggle Button -->
<button id="ai-chat-toggle" style="
    position:fixed;
    bottom:24px;
    right:24px;
    z-index:10000;
    background:#6366f1;
    color:#fff;
    border:none;
    border-radius:50%;
    width:56px;
    height:56px;
    box-shadow:0 4px 16px rgba(99,102,241,0.18);
    font-size:2rem;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
">
    <img src="images/ai_avatar.png" alt="AI" style="height:36px;width:36px;border-radius:50%;border:2px solid #fff;">
</button>

<!-- AI Chat Widget -->
<div id="ai-chat-widget" style="
    position:fixed;
    bottom:90px;
    right:24px;
    width:340px;
    background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);
    border-radius:18px;
    box-shadow:0 8px 32px rgba(60,72,88,0.12);
    border:1px solid #e0e7ff;
    padding:18px;
    z-index:9999;
    font-family:'Inter',sans-serif;
    opacity:0;
    transform:translateY(40px) scale(0.95);
    pointer-events:none;
    transition:opacity 0.3s cubic-bezier(.4,0,.2,1),transform 0.3s cubic-bezier(.4,0,.2,1);
">
    <!-- Logo & Avatar Row -->
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:14px;">
        <img src="images/logo.png" alt="Company Logo" style="height:56px;width:56px;border-radius:12px;box-shadow:0 2px 8px rgba(99,102,241,0.12);" />
        <img src="images/ai_avatar.png" alt="AI Avatar" style="height:56px;width:56px;border-radius:50%;border:3px solid #6366f1;background:#eef2ff;" />
        <span style="font-weight:600;font-size:1.25rem;color:#4f46e5;">AI Assistant</span>
        <button id="ai-chat-close" style="margin-left:auto;background:none;border:none;font-size:1.5rem;color:#6366f1;cursor:pointer;">&times;</button>
    </div>
    <div id="chat-log" style="
        height:170px;
        overflow-y:auto;
        margin-bottom:12px;
        background:#fff;
        border-radius:10px;
        border:1px solid #e0e7ff;
        padding:10px;
        font-size:0.97rem;
        color:#334155;
    "></div>
    <input type="text" id="chat-input" class="form-control" placeholder="Ask me anything..." style="
        width:100%;
        border-radius:8px;
        border:1px solid #c7d2fe;
        padding:8px 12px;
        margin-bottom:8px;
        font-size:1rem;
        background:#f1f5f9;
    " />
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
        <button onclick="sendAIChat()" class="btn btn-primary w-full" style="
            background:#6366f1;
            color:#fff;
            font-weight:600;
            border:none;
            border-radius:8px;
            padding:10px 0;
            font-size:1rem;
            box-shadow:0 2px 8px rgba(99,102,241,0.08);
            transition:background 0.2s;
        ">Send</button>
        <button onclick="document.getElementById('chat-input').value='Show my attendance report for June';sendAIChat();" title="Attendance Report" style="
            background:#e0e7ff;
            color:#6366f1;
            border:none;
            border-radius:8px;
            padding:10px;
            font-size:1rem;
            cursor:pointer;
        ">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="#6366f1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01" /></svg>
        </button>
    </div>
</div>

<script>
    // Chat Widget Toggle
    document.getElementById('ai-chat-toggle').onclick = function() {
        var widget = document.getElementById('ai-chat-widget');
        widget.style.opacity = '1';
        widget.style.transform = 'translateY(0) scale(1)';
        widget.style.pointerEvents = 'auto';
        this.style.display = 'none';
    };
    document.getElementById('ai-chat-close').onclick = function() {
        var widget = document.getElementById('ai-chat-widget');
        widget.style.opacity = '0';
        widget.style.transform = 'translateY(40px) scale(0.95)';
        widget.style.pointerEvents = 'none';
        document.getElementById('ai-chat-toggle').style.display = 'flex';
    };

    // Send Chat Message
    function sendAIChat() {
        var input = document.getElementById('chat-input');
        var log = document.getElementById('chat-log');
        var question = input.value.trim();
        if (!question) return;

        // Display user message
        log.innerHTML += '<div><b>You:</b> ' + question + '</div>';
        input.value = '';
        log.scrollTop = log.scrollHeight;

        // Show typing indicator
        var typingIndicator = document.createElement('div');
        typingIndicator.id = 'typing-indicator';
        typingIndicator.innerHTML = '<b>AI:</b> <em>Typing...</em>';
        log.appendChild(typingIndicator);
        log.scrollTop = log.scrollHeight;

        var history = [
            {role: "system", content: "You are an assistant for the Attendance Register employee portal. Answer questions about attendance, leave, corrections, and personal stats."},
            {role: "user", content: question}
        ];

        fetch('ai_chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({messages: history})
        })
        .then(res => {
            if (!res.ok) {
                // If server response is not OK (e.g., 500 error), throw an error
                throw new Error('Network response was not ok. Status: ' + res.status);
            }
            return res.json();
        })
        .then(data => {
            var answer = data.response || "Sorry, I couldn't get a response.";
            log.innerHTML += '<div><b>AI:</b> ' + answer + '</div>';
        })
        .catch(error => {
            console.error('Fetch error:', error);
            log.innerHTML += '<div><b>AI:</b> Sorry, something went wrong. Please check the console for errors.</div>';
        })
        .finally(() => {
            // Always remove the typing indicator
            var indicator = document.getElementById('typing-indicator');
            if (indicator) {
                indicator.remove();
            }
            log.scrollTop = log.scrollHeight;
        });
    }

    // Allow sending with Enter key
    document.getElementById('chat-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendAIChat();
        }
    });
</script>

</body>
</html>