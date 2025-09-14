<?php
// Test script for admin interface functionality
include 'db.php';

echo "<h2>Admin Interface Functionality Test</h2>\n";

// Test 1: Dashboard Stats API
echo "<h3>Test 1: Dashboard Stats API</h3>\n";
try {
    $today = date('Y-m-d');
    
    // Total employees
    $stmt = $conn->query("SELECT COUNT(*) as total FROM tbl_employees WHERE IsActive = 1 OR IsActive IS NULL");
    $totalEmployees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "✅ Total employees: $totalEmployees<br>\n";
    
    // Present today (employees who have clocked in)
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT EmployeeID) as present FROM tbl_attendance WHERE AttendanceDate = ? AND ClockIn IS NOT NULL");
    $stmt->execute([$today]);
    $presentToday = $stmt->fetch(PDO::FETCH_ASSOC)['present'];
    echo "✅ Present today: $presentToday<br>\n";
    
    // Absent today
    $absentToday = $totalEmployees - $presentToday;
    echo "✅ Absent today: $absentToday<br>\n";
    
    // Late arrivals
    $stmt = $conn->prepare("SELECT COUNT(*) as late FROM tbl_attendance WHERE AttendanceDate = ? AND (Status = 'Late' OR ClockInStatus = 'Late')");
    $stmt->execute([$today]);
    $lateArrivals = $stmt->fetch(PDO::FETCH_ASSOC)['late'];
    echo "✅ Late arrivals: $lateArrivals<br>\n";
    
} catch (Exception $e) {
    echo "❌ Dashboard stats test failed: " . $e->getMessage() . "<br>\n";
}

// Test 2: Recent Activity
echo "<h3>Test 2: Recent Activity</h3>\n";
try {
    $sql = "
        (SELECT 
            'clock_in' as action,
            c.EmployeeID,
            e.FullName as employee_name,
            c.ClockIn as timestamp,
            'clockinout' as source
        FROM clockinout c
        LEFT JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID
        WHERE DATE(c.ClockIn) = ?
        AND c.ClockIn IS NOT NULL
        ORDER BY c.ClockIn DESC
        LIMIT 5)
        
        UNION ALL
        
        (SELECT 
            'clock_out' as action,
            c.EmployeeID,
            e.FullName as employee_name,
            c.ClockOut as timestamp,
            'clockinout' as source
        FROM clockinout c
        LEFT JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID
        WHERE DATE(c.ClockOut) = ?
        AND c.ClockOut IS NOT NULL
        ORDER BY c.ClockOut DESC
        LIMIT 5)
        
        ORDER BY timestamp DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$today, $today]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Recent activities found: " . count($activities) . "<br>\n";
    foreach ($activities as $activity) {
        echo "  - {$activity['employee_name']}: {$activity['action']} at " . date('H:i', strtotime($activity['timestamp'])) . "<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Recent activity test failed: " . $e->getMessage() . "<br>\n";
}

// Test 3: Location Alerts
echo "<h3>Test 3: Location Alerts</h3>\n";
try {
    $sql = "
        SELECT 
            c.EmployeeID,
            e.FullName as employee_name,
            c.ClockIn as timestamp,
            c.location_verification_score,
            c.is_at_workplace
        FROM clockinout c
        LEFT JOIN tbl_employees e ON c.EmployeeID = e.EmployeeID
        WHERE DATE(c.ClockIn) = ?
        AND (c.location_verification_score < 70 OR c.is_at_workplace = 0)
        ORDER BY c.ClockIn DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$today]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Location alerts found: " . count($alerts) . "<br>\n";
    foreach ($alerts as $alert) {
        echo "  - {$alert['employee_name']}: Score {$alert['location_verification_score']}, At workplace: " . ($alert['is_at_workplace'] ? 'Yes' : 'No') . "<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Location alerts test failed: " . $e->getMessage() . "<br>\n";
}

// Test 4: Admin Attendance API Test
echo "<h3>Test 4: Admin Attendance API Test</h3>\n";

$testActions = [
    'get_attendance_records',
    'get_clockinout_logs',
    'get_employee_status'
];

foreach ($testActions as $action) {
    try {
        $url = 'http://localhost/attendance_register/admin_attendance_api.php';
        $postData = http_build_query(['action' => $action, 'date' => $today]);
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => $postData
            ]
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result !== false) {
            $data = json_decode($result, true);
            if ($data && isset($data['success']) && $data['success']) {
                echo "✅ $action API working - returned " . (isset($data['data']) ? count($data['data']) : 0) . " records<br>\n";
            } else {
                echo "⚠️ $action API responded but with error: " . ($data['message'] ?? 'Unknown error') . "<br>\n";
            }
        } else {
            echo "❌ $action API failed to respond<br>\n";
        }
        
    } catch (Exception $e) {
        echo "❌ $action API test failed: " . $e->getMessage() . "<br>\n";
    }
}

// Test 5: Database Tables Structure
echo "<h3>Test 5: Database Tables Structure</h3>\n";

$requiredTables = ['tbl_employees', 'tbl_attendance', 'clockinout'];

foreach ($requiredTables as $table) {
    try {
        $stmt = $conn->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ Table $table exists with " . count($columns) . " columns<br>\n";
    } catch (Exception $e) {
        echo "❌ Table $table error: " . $e->getMessage() . "<br>\n";
    }
}

echo "<h3>Test Summary</h3>\n";
echo "Admin interface components are ready for testing. If all tests show ✅, the interface should work correctly.<br>\n";
echo "Next step: Access the admin interface at http://localhost/attendance_register/admin_attendance_management.html<br>\n";
?>
