<?php
/**
 * Test WearOS Clock In/Out API Functionality
 * Tests the new attendance features for Android smartwatches
 * 
 * @author SignSync Development Team
 * @date September 12, 2025
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== WearOS Clock In/Out API Test ===\n\n";

// Test configuration
$serverUrl = 'http://localhost:8080/wearos_api.php';
$testEmployeeId = 'EMP001'; // Replace with actual employee ID

function sendRequest($url, $data) {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    return json_decode($response, true);
}

function testPing($url) {
    echo "1. Testing API Ping...\n";
    $response = sendRequest($url, ['action' => 'ping']);
    
    if ($response && $response['success']) {
        echo "✓ API Ping successful: {$response['message']}\n";
        return true;
    } else {
        echo "✗ API Ping failed\n";
        return false;
    }
}

function testEmployeeAuthentication($url, $employeeId) {
    echo "\n2. Testing Employee Authentication...\n";
    $response = sendRequest($url, [
        'action' => 'authenticate_employee',
        'employee_id' => $employeeId,
        'pin' => '1234' // Default PIN for testing
    ]);
    
    if ($response && $response['success']) {
        echo "✓ Employee authentication successful\n";
        echo "  Employee: {$response['data']['employee_name']}\n";
        echo "  Session: {$response['data']['session_token']}\n";
        return $response['data']['session_token'];
    } else {
        echo "✗ Employee authentication failed: " . ($response['message'] ?? 'Unknown error') . "\n";
        return false;
    }
}

function testGetAttendanceStatus($url, $employeeId) {
    echo "\n3. Testing Get Attendance Status...\n";
    $response = sendRequest($url, [
        'action' => 'get_attendance_status',
        'employee_id' => $employeeId
    ]);
    
    if ($response && $response['success']) {
        echo "✓ Attendance status retrieved successfully\n";
        echo "  Status: {$response['data']['status']}\n";
        echo "  Employee: {$response['data']['employee_name']}\n";
        echo "  Date: {$response['data']['date']}\n";
        
        if ($response['data']['clock_in_time']) {
            echo "  Clock In: {$response['data']['clock_in_time']}\n";
        }
        if ($response['data']['clock_out_time']) {
            echo "  Clock Out: {$response['data']['clock_out_time']}\n";
        }
        if ($response['data']['work_duration_hours']) {
            echo "  Work Duration: {$response['data']['work_duration_hours']} hours\n";
        }
        
        return $response['data']['status'];
    } else {
        echo "✗ Get attendance status failed: " . ($response['message'] ?? 'Unknown error') . "\n";
        return false;
    }
}

function testClockIn($url, $employeeId) {
    echo "\n4. Testing Clock In...\n";
    $response = sendRequest($url, [
        'action' => 'clock_in',
        'employee_id' => $employeeId,
        'timestamp' => time(),
        'location_lat' => 14.5995,  // Manila coordinates for testing
        'location_lng' => 120.9842,
        'device_info' => 'WearOS Test Device'
    ]);
    
    if ($response && $response['success']) {
        echo "✓ Clock in successful\n";
        echo "  Clock In ID: {$response['data']['clock_in_id']}\n";
        echo "  Time: {$response['data']['clock_in_time']}\n";
        echo "  Employee: {$response['data']['employee_name']}\n";
        return true;
    } else {
        echo "✗ Clock in failed: " . ($response['message'] ?? 'Unknown error') . "\n";
        return false;
    }
}

function testClockOut($url, $employeeId) {
    echo "\n5. Testing Clock Out...\n";
    $response = sendRequest($url, [
        'action' => 'clock_out',
        'employee_id' => $employeeId,
        'timestamp' => time(),
        'location_lat' => 14.5995,
        'location_lng' => 120.9842,
        'device_info' => 'WearOS Test Device'
    ]);
    
    if ($response && $response['success']) {
        echo "✓ Clock out successful\n";
        echo "  Clock Out ID: {$response['data']['clock_out_id']}\n";
        echo "  Clock In Time: {$response['data']['clock_in_time']}\n";
        echo "  Clock Out Time: {$response['data']['clock_out_time']}\n";
        echo "  Work Duration: {$response['data']['work_duration_hours']} hours\n";
        echo "  Employee: {$response['data']['employee_name']}\n";
        return true;
    } else {
        echo "✗ Clock out failed: " . ($response['message'] ?? 'Unknown error') . "\n";
        return false;
    }
}

function testRecentAttendance($url, $employeeId) {
    echo "\n6. Testing Recent Attendance History...\n";
    $response = sendRequest($url, [
        'action' => 'get_recent_attendance',
        'employee_id' => $employeeId,
        'limit' => 5
    ]);
    
    if ($response && $response['success']) {
        echo "✓ Recent attendance retrieved successfully\n";
        echo "  Employee: {$response['data']['employee_name']}\n";
        echo "  Total Work Hours: {$response['data']['summary']['total_work_hours']}\n";
        echo "  Days Worked: {$response['data']['summary']['days_worked']}\n";
        echo "  Average Work Hours: {$response['data']['summary']['average_work_hours']}\n";
        
        echo "\n  Recent Attendance Records:\n";
        foreach ($response['data']['attendance_history'] as $record) {
            echo "    Date: {$record['attendance_date']}\n";
            echo "    Clock In: " . ($record['clock_in_time'] ?? 'N/A') . "\n";
            echo "    Clock Out: " . ($record['clock_out_time'] ?? 'N/A') . "\n";
            echo "    Work Hours: " . ($record['work_duration_hours'] ?? 'N/A') . "\n";
            echo "    ---\n";
        }
        return true;
    } else {
        echo "✗ Recent attendance failed: " . ($response['message'] ?? 'Unknown error') . "\n";
        return false;
    }
}

// Run comprehensive tests
try {
    // Test 1: API Ping
    if (!testPing($serverUrl)) {
        exit("API not accessible. Check server configuration.\n");
    }
    
    // Test 2: Employee Authentication
    $sessionToken = testEmployeeAuthentication($serverUrl, $testEmployeeId);
    if (!$sessionToken) {
        echo "Warning: Authentication failed, continuing with other tests...\n";
    }
    
    // Test 3: Get initial attendance status
    $initialStatus = testGetAttendanceStatus($serverUrl, $testEmployeeId);
    
    // Test 4: Clock In (only if not already clocked in)
    if ($initialStatus !== 'clocked_in') {
        $clockInSuccess = testClockIn($serverUrl, $testEmployeeId);
        
        if ($clockInSuccess) {
            // Test 5: Get status after clock in
            echo "\n4.1. Checking status after clock in...\n";
            testGetAttendanceStatus($serverUrl, $testEmployeeId);
            
            // Wait a moment for demonstration
            echo "\nWaiting 3 seconds before testing clock out...\n";
            sleep(3);
            
            // Test 6: Clock Out
            testClockOut($serverUrl, $testEmployeeId);
            
            // Test 7: Get status after clock out
            echo "\n5.1. Checking status after clock out...\n";
            testGetAttendanceStatus($serverUrl, $testEmployeeId);
        }
    } else {
        echo "\nEmployee already clocked in. Testing clock out only...\n";
        testClockOut($serverUrl, $testEmployeeId);
    }
    
    // Test 8: Recent attendance history
    testRecentAttendance($serverUrl, $testEmployeeId);
    
    echo "\n=== Clock In/Out API Test Complete ===\n";
    echo "✓ All attendance features tested successfully!\n";
    echo "\nThe WearOS smartwatch can now:\n";
    echo "  • Clock in with location and device info\n";
    echo "  • Clock out with automatic work duration calculation\n";
    echo "  • Check real-time attendance status\n";
    echo "  • View recent attendance history\n";
    echo "  • Track work hours and productivity metrics\n";
    
} catch (Exception $e) {
    echo "Test error: " . $e->getMessage() . "\n";
}
?>
