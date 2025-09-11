<?php
error_log("AI chat request received at " . date('Y-m-d H:i:s'));

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $messages = $input['messages'] ?? [];

    // Get the latest user question (case-insensitive)
    $userQuestion = '';
    if (!empty($messages)) {
        $lastMessage = end($messages);
        if (isset($lastMessage['role']) && $lastMessage['role'] === 'user' && isset($lastMessage['content'])) {
            $userQuestion = strtolower($lastMessage['content']);
        }
    }

    include 'db.php';

    // Detect role
    $role = isset($_SESSION['user_role']) ? strtolower($_SESSION['user_role']) : 'employee';

    $dynamicInfo = '';
    $knowledgeBase = '';
    if ($role === 'administrator' || $role === 'hr') {
        $knowledgeBase = "You are an HR/admin assistant for the Attendance Register system.
- You have access to all employee, attendance, leave, correction, branch, geolocation, and analytics data.
- You can answer and perform actions for: attendance monitoring, employee reports, clock-in/out exceptions, correction and leave management, branch monitoring, policy enforcement, user account management, analytics, and smart suggestions.
- Employees must clock in by 8:00 AM. Late arrivals are after 8:15 AM.
- Leave requests must be approved by HR.
- You can generate, compare, and export reports, manage accounts, and enforce policies.
- Use information from the entire system to answer any query or perform any action.";

        // Attendance Monitoring
        if (strpos($userQuestion, "who hasn't clocked in") !== false) {
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT EmployeeID FROM tbl_attendance WHERE AttendanceDate = ?");
            $stmt->execute([$today]);
            $presentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $in = $presentIds ? str_repeat('?,', count($presentIds) - 1) . '?' : '';
            if ($in) {
                $stmt2 = $conn->prepare("SELECT FullName FROM tbl_employees WHERE EmployeeID NOT IN ($in)");
                $stmt2->execute($presentIds);
            } else {
                $stmt2 = $conn->query("SELECT FullName FROM tbl_employees");
            }
            $absentNames = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            $dynamicInfo .= $absentNames ? "\nEmployees who haven't clocked in today: " . implode(", ", $absentNames) . "." : "\nAll employees have clocked in today.";
        } elseif (strpos($userQuestion, "late employees this week") !== false) {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            $stmt = $conn->prepare("SELECT DISTINCT e.FullName FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID WHERE a.AttendanceDate BETWEEN ? AND ? AND a.ClockInStatus = 'Late'");
            $stmt->execute([$weekStart, $weekEnd]);
            $lateNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dynamicInfo .= $lateNames ? "\nLate employees this week: " . implode(", ", $lateNames) . "." : "\nNo late employees this week.";
        } elseif (strpos($userQuestion, "absentees today") !== false) {
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT EmployeeID FROM tbl_attendance WHERE AttendanceDate = ?");
            $stmt->execute([$today]);
            $presentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $in = $presentIds ? str_repeat('?,', count($presentIds) - 1) . '?' : '';
            if ($in) {
                $stmt2 = $conn->prepare("SELECT FullName FROM tbl_employees WHERE EmployeeID NOT IN ($in)");
                $stmt2->execute($presentIds);
            } else {
                $stmt2 = $conn->query("SELECT FullName FROM tbl_employees");
            }
            $absentNames = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            $dynamicInfo .= $absentNames ? "\nAbsentees today: " . implode(", ", $absentNames) . "." : "\nNo absentees today.";
        } elseif (strpos($userQuestion, "attendance summary") !== false) {
            $today = date('Y-m-d');
            $total = $conn->query("SELECT COUNT(*) FROM tbl_employees")->fetchColumn();
            $present = $conn->prepare("SELECT COUNT(DISTINCT EmployeeID) FROM tbl_attendance WHERE AttendanceDate = ?");
            $present->execute([$today]);
            $presentCount = $present->fetchColumn();
            $late = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance WHERE AttendanceDate = ? AND ClockInStatus = 'Late'");
            $late->execute([$today]);
            $lateCount = $late->fetchColumn();
            $dynamicInfo .= "\nToday's Attendance Summary: Total Employees: $total, Present: $presentCount, Late: $lateCount.";
        }

        // Employee Attendance Reports
        if (preg_match('/show (.+)\'s attendance for (.+)/', $userQuestion, $matches)) {
            $employeeName = $matches[1];
            $month = $matches[2];
            $stmt = $conn->prepare("SELECT e.FullName, a.AttendanceDate, a.ClockInStatus FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID WHERE e.FullName = ? AND MONTH(a.AttendanceDate) = MONTH(?)");
            $stmt->execute([$employeeName, "$month-01"]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $summary = [];
                foreach ($rows as $row) {
                    $summary[] = $row['AttendanceDate'] . " (" . $row['ClockInStatus'] . ")";
                }
                $dynamicInfo .= "\nAttendance for $employeeName in $month: " . implode(", ", $summary) . ".";
            } else {
                $dynamicInfo .= "\nNo attendance records found for $employeeName in $month.";
            }
        } elseif (strpos($userQuestion, "download attendance logs") !== false) {
            $dynamicInfo .= "\nYou can download attendance logs from the Reports section.";
        } elseif (strpos($userQuestion, "compare attendance between") !== false) {
            preg_match('/compare attendance between ([\w\s]+) and ([\w\s]+)/', $userQuestion, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $names = [$matches[1], $matches[2]];
                $result = [];
                foreach ($names as $name) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID WHERE e.FullName = ? AND a.ClockInStatus = 'Present'");
                    $stmt->execute([$name]);
                    $present = $stmt->fetchColumn();
                    $result[] = "$name: $present days present";
                }
                $dynamicInfo .= "\nAttendance comparison: " . implode(" vs ", $result) . ".";
            }
        } elseif (strpos($userQuestion, "branch-wise attendance report") !== false) {
            $stmt = $conn->query("SELECT b.BranchName, COUNT(a.EmployeeID) as PresentCount FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID JOIN tbl_branches b ON e.BranchID = b.BranchID WHERE a.AttendanceDate = CURDATE() GROUP BY b.BranchName");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $report = [];
            foreach ($rows as $row) {
                $report[] = $row['BranchName'] . ": " . $row['PresentCount'] . " present";
            }
            $dynamicInfo .= "\nBranch-wise attendance: " . implode(", ", $report) . ".";
        }

        // Clock-In/Out Exceptions
        if (strpos($userQuestion, "missing clock-outs") !== false) {
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT e.FullName FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID WHERE a.AttendanceDate = ? AND a.ClockOutTime IS NULL");
            $stmt->execute([$today]);
            $missing = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dynamicInfo .= $missing ? "\nEmployees with missing clock-outs: " . implode(", ", $missing) . "." : "\nNo missing clock-outs today.";
        } elseif (strpos($userQuestion, "forgot to clock in today") !== false) {
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT EmployeeID FROM tbl_attendance WHERE AttendanceDate = ?");
            $stmt->execute([$today]);
            $presentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $in = $presentIds ? str_repeat('?,', count($presentIds) - 1) . '?' : '';
            if ($in) {
                $stmt2 = $conn->prepare("SELECT FullName FROM tbl_employees WHERE EmployeeID NOT IN ($in)");
                $stmt2->execute($presentIds);
            } else {
                $stmt2 = $conn->query("SELECT FullName FROM tbl_employees");
            }
            $absentNames = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            $dynamicInfo .= $absentNames ? "\nAbsentees today: " . implode(", ", $absentNames) . "." : "\nNo absentees today.";
        }

        // Correction Request Management
        if (preg_match('/approve correction request from (.+)/', $userQuestion, $matches)) {
            $dynamicInfo .= "\nCorrection request for {$matches[1]} has been approved (simulated).";
        } elseif (preg_match('/reject pending correction for (.+)/', $userQuestion, $matches)) {
            $dynamicInfo .= "\nPending correction for {$matches[1]} has been rejected (simulated).";
        } elseif (strpos($userQuestion, "pending correction requests") !== false) {
            $stmt = $conn->prepare("SELECT e.FullName FROM tbl_correction_requests c JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID WHERE c.status = 'pending'");
            $stmt->execute();
            $pending = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dynamicInfo .= $pending ? "\nPending correction requests: " . implode(", ", $pending) . "." : "\nNo pending correction requests.";
        } elseif (strpos($userQuestion, "most corrections this month") !== false) {
            $stmt = $conn->query("SELECT e.FullName, COUNT(*) as cnt FROM tbl_correction_requests c JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID WHERE MONTH(c.created_at) = MONTH(CURDATE()) GROUP BY c.EmployeeID ORDER BY cnt DESC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $dynamicInfo .= "\nMost corrections this month: {$row['FullName']} ({$row['cnt']} corrections).";
            } else {
                $dynamicInfo .= "\nNo corrections submitted this month.";
            }
        }

        // Leave Management
        if (preg_match('/approve leave request from (.+)/', $userQuestion, $matches)) {
            $dynamicInfo .= "\nLeave request for {$matches[1]} has been approved (simulated).";
        } elseif (strpos($userQuestion, "employees on leave this week") !== false) {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            $stmt = $conn->prepare("SELECT DISTINCT e.FullName FROM tbl_leave_requests l JOIN tbl_employees e ON l.EmployeeID = e.EmployeeID WHERE l.start_date <= ? AND l.end_date >= ? AND l.status = 'approved'");
            $stmt->execute([$weekEnd, $weekStart]);
            $onLeave = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dynamicInfo .= $onLeave ? "\nEmployees on leave this week: " . implode(", ", $onLeave) . "." : "\nNo employees on leave this week.";
        } elseif (strpos($userQuestion, "sick leaves taken this month") !== false) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_leave_requests WHERE type = 'sick' AND MONTH(start_date) = MONTH(CURDATE()) AND status = 'approved'");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            $dynamicInfo .= "\nSick leaves taken this month: $count.";
        } elseif (strpos($userQuestion, "emergency leave") !== false) {
            $stmt = $conn->prepare("SELECT e.FullName FROM tbl_leave_requests l JOIN tbl_employees e ON l.EmployeeID = e.EmployeeID WHERE l.type = 'emergency' AND l.status = 'approved'");
            $stmt->execute();
            $emergency = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dynamicInfo .= $emergency ? "\nEmployees who applied for emergency leave: " . implode(", ", $emergency) . "." : "\nNo emergency leave requests.";
        }

        // Geolocation & Branch Monitoring
        if (strpos($userQuestion, "branches have late check-ins") !== false) {
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT DISTINCT b.BranchName FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID JOIN tbl_branches b ON e.BranchID = b.BranchID WHERE a.AttendanceDate = ? AND a.ClockInStatus = 'Late'");
            $stmt->execute([$today]);
            $branches = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $dynamicInfo .= $branches ? "\nBranches with late check-ins today: " . implode(", ", $branches) . "." : "\nNo branches have late check-ins today.";
        } elseif (strpos($userQuestion, "branch geofences") !== false) {
            $stmt = $conn->query("SELECT BranchName, AllowedRadius FROM tbl_branches");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $info = [];
            foreach ($rows as $row) {
                $info[] = $row['BranchName'] . " (Radius: " . $row['AllowedRadius'] . "m)";
            }
            $dynamicInfo .= $info ? "\nBranch geofences: " . implode(", ", $info) . "." : "\nNo branch geofences found.";
        } elseif (strpos($userQuestion, "what is my allowed clock-in area") !== false && isset($_SESSION['user_id'])) {
            $empId = $_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT b.AllowedRadius FROM tbl_employees e JOIN tbl_branches b ON e.BranchID = b.BranchID WHERE e.EmployeeID = ?");
            $stmt->execute([$empId]);
            $radius = $stmt->fetchColumn();
            $dynamicInfo .= $radius ? "\nYour allowed clock-in area radius is $radius meters." : "\nNo geofence info found for your branch.";
        }

        // User Account & Role Management
        if (strpos($userQuestion, "add new employee account") !== false) {
            $dynamicInfo .= "\nNew employee account added (simulated).";
        } elseif (strpos($userQuestion, "deactivate an employee") !== false) {
            $dynamicInfo .= "\nEmployee account deactivated (simulated).";
        } elseif (preg_match('/change (.+)\'s role/', $userQuestion, $matches)) {
            $dynamicInfo .= "\nRole for {$matches[1]} has been changed (simulated).";
        }

        // Analytics & Reports
        if (strpos($userQuestion, "monthly attendance chart") !== false) {
            $dynamicInfo .= "\nMonthly attendance chart generated (simulated).";
        } elseif (strpos($userQuestion, "attendance trends between branches") !== false) {
            // Query for attendance trends
        } elseif (strpos($userQuestion, "average lateness by department") !== false) {
            $stmt = $conn->query("SELECT d.DepartmentName, AVG(TIMESTAMPDIFF(MINUTE, '08:00:00', a.ClockInTime)) as AvgLate FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID WHERE a.ClockInStatus = 'Late' GROUP BY d.DepartmentName");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $info = [];
            foreach ($rows as $row) {
                $info[] = $row['DepartmentName'] . ": " . round($row['AvgLate'], 1) . " min";
            }
            $dynamicInfo .= $info ? "\nAverage lateness by department: " . implode(", ", $info) . "." : "\nNo lateness data by department.";
        } elseif (strpos($userQuestion, "absenteeism report for finance department") !== false) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID WHERE d.DepartmentName = 'Finance' AND a.ClockInStatus = 'Absent'");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            $dynamicInfo .= "\nAbsenteeism report for Finance department: $count absences.";
        }

        // Bonus Smart Suggestions
        if (strpos($userQuestion, "how do i submit a correction request") !== false) {
            $dynamicInfo .= "\nTo submit a correction request, go to the Requests section and fill out the correction form.";
        } elseif (strpos($userQuestion, "forget to clock out") !== false) {
            $dynamicInfo .= "\nIf you forget to clock out, submit a correction request for your attendance.";
        } elseif (strpos($userQuestion, "explain my attendance summary") !== false) {
            $dynamicInfo .= "\nYour attendance summary includes present days, late arrivals, absences, and approved leaves.";
        } elseif (strpos($userQuestion, "most common attendance issue") !== false) {
            $dynamicInfo .= "\nThe most common attendance issue is late clock-ins.";
        }
    } else {
        $knowledgeBase = "You are an assistant for the Attendance Register employee portal.
- You have access to my attendance, leave balance, correction requests, gamification, and personal records.
- You can answer and assist with: personal attendance status, attendance history, clock-in/out issues, correction requests, leave management, location rules, self-service requests, reminders, notifications, gamification, rewards, and smart suggestions.
- Employees must clock in by 8:00 AM. Late arrivals are after 8:15 AM.
- Leave requests must be approved by HR.
- Use information from my records and general system rules to answer any query or guide me.";

        // For non-admin employees, allow personalized queries using session info
        if ($role !== 'administrator' && $role !== 'hr') {
            // Always get employee info from session for personalized queries
            if (isset($_SESSION['user_id'])) {
                $empId = $_SESSION['user_id'];
                // All personalized queries below use $empId
                // Example:
                $present = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance WHERE EmployeeID = ? AND ClockInStatus = 'Present'");
                $present->execute([$empId]);
                $presentCount = $present->fetchColumn();

                $late = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance WHERE EmployeeID = ? AND ClockInStatus = 'Late'");
                $late->execute([$empId]);
                $lateCount = $late->fetchColumn();

                $absent = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance WHERE EmployeeID = ? AND ClockInStatus = 'Absent'");
                $absent->execute([$empId]);
                $absentCount = $absent->fetchColumn();

                $leave = $conn->prepare("SELECT SUM(UsedDays) FROM tbl_leave_balance WHERE EmployeeID = ?");
                $leave->execute([$empId]);
                $leaveBalance = $leave->fetchColumn();

                $corrections = $conn->prepare("SELECT COUNT(*) FROM tbl_correction_requests WHERE EmployeeID = ? AND status = 'pending'");
                $corrections->execute([$empId]);
                $pendingCorrections = $corrections->fetchColumn();

                $dynamicInfo .= "\nYour stats: Present days: $presentCount, Late: $lateCount, Absent: $absentCount, Leave balance: $leaveBalance, Pending corrections: $pendingCorrections.";

                // All other personalized triggers should use $empId and check isset($empId) before running queries
                // 1. Personal Attendance Status
                if (strpos($userQuestion, "did i clock in today") !== false && isset($empId)) {
                    $today = date('Y-m-d');
                    $stmt = $conn->prepare("SELECT ClockIn FROM tbl_attendance WHERE EmployeeID = ? AND AttendanceDate = ?");
                    $stmt->execute([$empId, $today]);
                    $clockIn = $stmt->fetchColumn();
                    $dynamicInfo .= $clockIn ? "\nYou clocked in today at $clockIn." : "\nYou have not clocked in today.";
                } elseif (strpos($userQuestion, "what time did i clock out yesterday") !== false && isset($empId)) {
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $stmt = $conn->prepare("SELECT ClockOut FROM tbl_attendance WHERE EmployeeID = ? AND AttendanceDate = ?");
                    $stmt->execute([$empId, $yesterday]);
                    $clockOut = $stmt->fetchColumn();
                    $dynamicInfo .= $clockOut ? "\nYou clocked out yesterday at $clockOut." : "\nNo clock-out recorded for yesterday.";
                } elseif (strpos($userQuestion, "was i late today") !== false && isset($empId)) {
                    $today = date('Y-m-d');
                    $stmt = $conn->prepare("SELECT ClockInStatus FROM tbl_attendance WHERE EmployeeID = ? AND AttendanceDate = ?");
                    $stmt->execute([$empId, $today]);
                    $status = $stmt->fetchColumn();
                    $dynamicInfo .= ($status === 'Late') ? "\nYou were late today." : "\nYou were not late today.";
                } elseif (strpos($userQuestion, "how many hours have i worked this week") !== false && isset($empId)) {
                    $weekStart = date('Y-m-d', strtotime('monday this week'));
                    $weekEnd = date('Y-m-d', strtotime('sunday this week'));
                    $stmt = $conn->prepare("SELECT SUM(TIMESTAMPDIFF(HOUR, ClockIn, ClockOut)) FROM tbl_attendance WHERE EmployeeID = ? AND AttendanceDate BETWEEN ? AND ?");
                    $stmt->execute([$empId, $weekStart, $weekEnd]);
                    $hours = $stmt->fetchColumn();
                    $dynamicInfo .= "\nYou have worked $hours hours this week.";
                } elseif (strpos($userQuestion, "when was my last attendance") !== false && isset($empId)) {
                    $stmt = $conn->prepare("SELECT AttendanceDate FROM tbl_attendance WHERE EmployeeID = ? ORDER BY AttendanceDate DESC LIMIT 1");
                    $stmt->execute([$empId]);
                    $last = $stmt->fetchColumn();
                    $dynamicInfo .= $last ? "\nYour last attendance was on $last." : "\nNo attendance record found.";
                }

                // 2. Attendance History
                if (preg_match('/show my attendance report for (\w+)/', $userQuestion, $matches) && isset($empId)) {
                    $month = $matches[1];
                    $stmt = $conn->prepare("SELECT AttendanceDate, ClockInStatus FROM tbl_attendance WHERE EmployeeID = ? AND MONTH(AttendanceDate) = MONTH(?)");
                    $stmt->execute([$empId, "$month-01"]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($rows) {
                        $summary = [];
                        foreach ($rows as $row) {
                            $summary[] = $row['AttendanceDate'] . " (" . $row['ClockInStatus'] . ")";
                        }
                        $dynamicInfo .= "\nYour attendance in $month: " . implode(", ", $summary) . ".";
                    } else {
                        $dynamicInfo .= "\nNo attendance records found for $month.";
                    }
                } elseif (strpos($userQuestion, "how many times have i been late this month") !== false && isset($empId)) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance WHERE EmployeeID = ? AND ClockInStatus = 'Late' AND MONTH(AttendanceDate) = MONTH(CURDATE())");
                    $stmt->execute([$empId]);
                    $count = $stmt->fetchColumn();
                    $dynamicInfo .= "\nYou have been late $count times this month.";
                } elseif (preg_match('/was i absent last (\w+)/', $userQuestion, $matches) && isset($empId)) {
                    $day = $matches[1];
                    $date = date('Y-m-d', strtotime("last $day"));
                    $stmt = $conn->prepare("SELECT ClockInStatus FROM tbl_attendance WHERE EmployeeID = ? AND AttendanceDate = ?");
                    $stmt->execute([$empId, $date]);
                    $status = $stmt->fetchColumn();
                    $dynamicInfo .= ($status === 'Absent') ? "\nYou were absent last $day." : "\nYou were not absent last $day.";
                }

                // 3. Clock-In/Out Issues
                if (strpos($userQuestion, "forgot to clock in today") !== false) {
                    $dynamicInfo .= "\nIf you forgot to clock in, please submit a correction request.";
                } elseif (strpos($userQuestion, "system isn’t letting me clock in") !== false) {
                    $dynamicInfo .= "\nIf you are unable to clock in, check your location and network. Contact HR if the issue persists.";
                } elseif (strpos($userQuestion, "couldn't clock out") !== false) {
                    $dynamicInfo .= "\nIf you couldn't clock out, submit a correction request for your attendance.";
                } elseif (strpos($userQuestion, "why is my clock-in not recorded") !== false) {
                    $dynamicInfo .= "\nPossible reasons: network issues, location outside allowed area, or system error. Please contact HR or submit a correction request.";
                }

                // 4. Correction Requests
                if (preg_match('/submit a correction request for ([\d\-]+)/', $userQuestion, $matches)) {
                    $date = $matches[1];
                    $dynamicInfo .= "\nCorrection request for $date submitted (simulated).";
                } elseif (preg_match('/edit my clock-in time for (.+)/', $userQuestion, $matches)) {
                    $day = $matches[1];
                    $dynamicInfo .= "\nClock-in time for $day edited (simulated).";
                } elseif (strpos($userQuestion, "cancel my correction request") !== false) {
                    $dynamicInfo .= "\nYour correction request has been cancelled (simulated).";
                } elseif (strpos($userQuestion, "has my correction request been approved") !== false && isset($empId)) {
                    $stmt = $conn->prepare("SELECT status FROM tbl_correction_requests WHERE EmployeeID = ? ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$empId]);
                    $status = $stmt->fetchColumn();
                    $dynamicInfo .= $status ? "\nYour latest correction request status: $status." : "\nNo correction requests found.";
                }

                // 5. Leave Management
                if (strpos($userQuestion, "how many leave days do i have left") !== false && isset($empId)) {
                    $stmt = $conn->prepare("SELECT SUM(UsedDays) FROM tbl_leave_balance WHERE EmployeeID = ?");
                    $stmt->execute([$empId]);
                    $leaveBalance = $stmt->fetchColumn();
                    $dynamicInfo .= "\nYou have $leaveBalance leave days left.";
                } elseif (preg_match('/apply for leave from ([\d\-]+) to ([\d\-]+)/', $userQuestion, $matches)) {
                    $from = $matches[1];
                    $to = $matches[2];
                    $dynamicInfo .= "\nLeave request from $from to $to submitted (simulated).";
                } elseif ((strpos($userQuestion, "has my leave been approved") !== false || strpos($userQuestion, "status of my leave request") !== false) && isset($empId)) {
                    $stmt = $conn->prepare("SELECT status FROM tbl_leave_requests WHERE EmployeeID = ? ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$empId]);
                    $status = $stmt->fetchColumn();
                    $dynamicInfo .= $status ? "\nYour latest leave request status: $status." : "\nNo leave requests found.";
                }

                // Geolocation & Branch Monitoring
                if (strpos($userQuestion, "branches have late check-ins") !== false) {
                    $today = date('Y-m-d');
                    $stmt = $conn->prepare("SELECT DISTINCT b.BranchName FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID JOIN tbl_branches b ON e.BranchID = b.BranchID WHERE a.AttendanceDate = ? AND a.ClockInStatus = 'Late'");
                    $stmt->execute([$today]);
                    $branches = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $dynamicInfo .= $branches ? "\nBranches with late check-ins today: " . implode(", ", $branches) . "." : "\nNo branches have late check-ins today.";
                } elseif (strpos($userQuestion, "branch geofences") !== false) {
                    $stmt = $conn->query("SELECT BranchName, AllowedRadius FROM tbl_branches");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $info = [];
                    foreach ($rows as $row) {
                        $info[] = $row['BranchName'] . " (Radius: " . $row['AllowedRadius'] . "m)";
                    }
                    $dynamicInfo .= $info ? "\nBranch geofences: " . implode(", ", $info) . "." : "\nNo branch geofences found.";
                } elseif (strpos($userQuestion, "what is my allowed clock-in area") !== false && isset($empId)) {
                    $stmt = $conn->prepare("SELECT b.AllowedRadius FROM tbl_employees e JOIN tbl_branches b ON e.BranchID = b.BranchID WHERE e.EmployeeID = ?");
                    $stmt->execute([$empId]);
                    $radius = $stmt->fetchColumn();
                    $dynamicInfo .= $radius ? "\nYour allowed clock-in area radius is $radius meters." : "\nNo geofence info found for your branch.";
                }

                // User Account & Role Management
                if (strpos($userQuestion, "add new employee account") !== false) {
                    $dynamicInfo .= "\nNew employee account added (simulated).";
                } elseif (strpos($userQuestion, "deactivate an employee") !== false) {
                    $dynamicInfo .= "\nEmployee account deactivated (simulated).";
                } elseif (preg_match('/change (.+)\'s role/', $userQuestion, $matches)) {
                    $dynamicInfo .= "\nRole for {$matches[1]} has been changed (simulated).";
                }

                // Analytics & Reports
                if (strpos($userQuestion, "monthly attendance chart") !== false) {
                    $dynamicInfo .= "\nMonthly attendance chart generated (simulated).";
                } elseif (strpos($userQuestion, "attendance trends between branches") !== false) {
                    // Query for attendance trends
                } elseif (strpos($userQuestion, "average lateness by department") !== false) {
                    $stmt = $conn->query("SELECT d.DepartmentName, AVG(TIMESTAMPDIFF(MINUTE, '08:00:00', a.ClockInTime)) as AvgLate FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID WHERE a.ClockInStatus = 'Late' GROUP BY d.DepartmentName");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $info = [];
                    foreach ($rows as $row) {
                        $info[] = $row['DepartmentName'] . ": " . round($row['AvgLate'], 1) . " min";
                    }
                    $dynamicInfo .= $info ? "\nAverage lateness by department: " . implode(", ", $info) . "." : "\nNo lateness data by department.";
                } elseif (strpos($userQuestion, "absenteeism report for finance department") !== false) {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance a JOIN tbl_employees e ON a.EmployeeID = e.EmployeeID JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID WHERE d.DepartmentName = 'Finance' AND a.ClockInStatus = 'Absent'");
                    $stmt->execute();
                    $count = $stmt->fetchColumn();
                    $dynamicInfo .= "\nAbsenteeism report for Finance department: $count absences.";
                }

                // Bonus Smart Suggestions
                if (strpos($userQuestion, "how do i submit a correction request") !== false) {
                    $dynamicInfo .= "\nGo to the Correction Requests section and fill out the form for the relevant date.";
                } elseif (strpos($userQuestion, "forget to clock out") !== false) {
                    $dynamicInfo .= "\nIf you forget to clock out, submit a correction request for your attendance.";
                } elseif (strpos($userQuestion, "explain my attendance summary") !== false) {
                    $dynamicInfo .= "\nYour attendance summary includes present days, late arrivals, absences, and approved leaves.";
                } elseif (strpos($userQuestion, "most common attendance issue") !== false) {
                    $dynamicInfo .= "\nThe most common attendance issue is late clock-ins.";
                }

                // Last clock in/out (alternative phrasing)
                if ((strpos($userQuestion, "last clock in") !== false || strpos($userQuestion, "my last attendance") !== false || strpos($userQuestion, "recent check-in") !== false) && isset($empId)) {
                    $stmt = $conn->prepare("SELECT AttendanceDate, ClockInTime, ClockOutTime FROM tbl_attendance WHERE EmployeeID = ? ORDER BY AttendanceDate DESC LIMIT 1");
                    $stmt->execute([$empId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $dynamicInfo .= "\nLast attendance: " . $row['AttendanceDate'] . " | Clock-in: " . $row['ClockInTime'] . " | Clock-out: " . $row['ClockOutTime'];
                    } else {
                        $dynamicInfo .= "\nNo attendance records found.";
                    }
                }

                // Late arrivals this week (alternative phrasing)
                if ((strpos($userQuestion, "late this week") !== false || strpos($userQuestion, "was i late today") !== false || strpos($userQuestion, "any late marks") !== false) && isset($empId)) {
                    $weekStart = date('Y-m-d', strtotime('monday this week'));
                    $weekEnd = date('Y-m-d', strtotime('sunday this week'));
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_attendance WHERE EmployeeID = ? AND ClockInStatus = 'Late' AND AttendanceDate BETWEEN ? AND ?");
                    $stmt->execute([$empId, $weekStart, $weekEnd]);
                    $count = $stmt->fetchColumn();
                    $dynamicInfo .= "\nLate arrivals this week: $count.";
                }

                // Monthly worked hours (alternative phrasing)
                if ((strpos($userQuestion, "hours worked") !== false || strpos($userQuestion, "worked this month") !== false || strpos($userQuestion, "monthly time") !== false) && isset($empId)) {
                    $monthStart = date('Y-m-01');
                    $monthEnd = date('Y-m-t');
                    $stmt = $conn->prepare("SELECT SUM(TIMESTAMPDIFF(HOUR, ClockInTime, ClockOutTime)) FROM tbl_attendance WHERE EmployeeID = ? AND AttendanceDate BETWEEN ? AND ?");
                    $stmt->execute([$empId, $monthStart, $monthEnd]);
                    $hours = $stmt->fetchColumn();
                    $dynamicInfo .= "\nTotal hours worked this month: $hours.";
                }

                // Absences last month (alternative phrasing)
                if ((strpos($userQuestion, "days absent") !== false || strpos($userQuestion, "missed days") !== false || strpos($userQuestion, "absent records") !== false) && isset($empId)) {
                    $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
                    $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
                    $stmt = $conn->prepare("SELECT AttendanceDate FROM tbl_attendance WHERE EmployeeID = ? AND ClockInStatus = 'Absent' AND AttendanceDate BETWEEN ? AND ?");
                    $stmt->execute([$empId, $lastMonthStart, $lastMonthEnd]);
                    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $dynamicInfo .= $rows ? "\nAbsences last month: " . implode(", ", $rows) : "\nNo absences last month.";
                }

                // Correction request status (alternative phrasing)
                if ((strpos($userQuestion, "correction approved") !== false || strpos($userQuestion, "status of request") !== false || strpos($userQuestion, "correction outcome") !== false) && isset($empId)) {
                    $stmt = $conn->prepare("SELECT status FROM tbl_correction_requests WHERE EmployeeID = ? ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$empId]);
                    $status = $stmt->fetchColumn();
                    $dynamicInfo .= $status ? "\nYour latest correction request status: $status." : "\nNo correction requests found.";
                }

                // 9. Leave balance (Intent: leave_balance_check)
                if ((strpos($userQuestion, "leave days left") !== false || strpos($userQuestion, "my leave balance") !== false || strpos($userQuestion, "remaining vacation") !== false) && isset($empId)) {
                    $stmt = $conn->prepare("SELECT SUM(RemainingDays) FROM tbl_leave_balance WHERE EmployeeID = ?");
                    $stmt->execute([$empId]);
                    $leaveBalance = $stmt->fetchColumn() ?: 0;
                    $dynamicInfo .= "\nYou have $leaveBalance leave days left.";
                }

                // 10. Attendance points (alternative phrasing)
                if ((strpos($userQuestion, "attendance score") !== false || strpos($userQuestion, "points") !== false || strpos($userQuestion, "my rewards") !== false) && isset($empId)) {
                    $stmt = $conn->prepare("SELECT points FROM tbl_gamification WHERE EmployeeID = ?");
                    $stmt->execute([$empId]);
                    $points = $stmt->fetchColumn();
                    $dynamicInfo .= $points ? "\nYou have $points attendance points." : "\nNo attendance points found.";
                }
            } else {
                // If not logged in, return an error or generic message
                $dynamicInfo .= "\nYou are not logged in. Please log in to view your personal information.";
            }
        }

        // Inject both static and dynamic info
        $systemPrompt = $knowledgeBase . ($dynamicInfo ? "\n" . $dynamicInfo : "");
        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemPrompt
        ]);

        // Limit prompt to last 6 messages (excluding system)
        $messages = array_slice($messages, 0, 7);

        // Build prompt for Ollama
        $prompt = '';
        foreach ($messages as $msg) {
            $prompt .= strtoupper($msg['role']) . ': ' . $msg['content'] . "\n";
        }

        $data = [
            'model' => 'phi3',
            'prompt' => $prompt,
            'options' => [
                'num_predict' => 128
            ]
        ];

        $ch = curl_init('http://localhost:11434/api/generate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        // Aggregate all "response" chunks from Ollama's stream
        $final = '';
        $ollamaError = false;
        foreach (explode("\n", $response) as $line) {
            $line = trim($line);
            if ($line) {
                $json = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($json['response'])) {
                    $final .= $json['response'];
                } else {
                    $ollamaError = true;
                }
            }
        }
        if ($ollamaError || !$final) {
            $final = "Sorry, I couldn't process your request. Please try again later.";
        }
        try {
            header('Content-Type: application/json');
            echo json_encode(['response' => $final]);
            exit;
        } catch (Exception $e) {
            error_log("AI Chat PHP Error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['response' => "Sorry, there was a server error."]);
            exit;
        }
    }
}