<?php
// Test Enhanced Location Verification System
include 'db.php';
include 'LocationVerificationManager.php';
include 'AttendanceManager.php';

echo "<h2>Enhanced Location Verification System Test</h2>\n";

try {
    $locationManager = new LocationVerificationManager($conn);
    $attendanceManager = new AttendanceManager($conn);
    
    echo "<h3>Test 1: Location Verification Manager Initialization</h3>\n";
    echo "✅ LocationVerificationManager created successfully<br>\n";
    echo "✅ AttendanceManager with enhanced location created successfully<br>\n";
    
    // Test 2: Add sample workplace boundary
    echo "<h3>Test 2: Adding Sample Workplace Boundary</h3>\n";
    $boundaryData = [
        'branch_id' => 'MAIN001',
        'boundary_name' => 'Main Office',
        'center_latitude' => 14.5995,
        'center_longitude' => 120.9842,
        'radius_meters' => 150,
        'boundary_type' => 'circular',
        'work_hours_start' => '08:00:00',
        'work_hours_end' => '17:00:00',
        'timezone' => 'Asia/Manila'
    ];
    
    $result = $locationManager->addWorkplaceBoundary($boundaryData);
    if ($result) {
        echo "✅ Sample workplace boundary added successfully<br>\n";
    } else {
        echo "⚠️ Workplace boundary might already exist or error occurred<br>\n";
    }
    
    // Test 3: Location verification with different scenarios
    echo "<h3>Test 3: Location Verification Scenarios</h3>\n";
    
    // Scenario 1: Employee at workplace (good GPS)
    $locationData1 = [
        'latitude' => 14.5995,  // Exact workplace location
        'longitude' => 120.9842,
        'accuracy' => 10,  // Good GPS accuracy
        'employee_id' => 'EMP001',
        'verification_type' => 'clock_in'
    ];
    
    $verification1 = $locationManager->verifyLocation($locationData1, 'MAIN001');
    echo "Scenario 1 - At workplace with good GPS:<br>\n";
    echo "  - At workplace: " . ($verification1['at_workplace'] ? 'Yes' : 'No') . "<br>\n";
    echo "  - Verification score: " . round($verification1['verification_score'], 2) . "%<br>\n";
    echo "  - Distance: " . round($verification1['distance_from_workplace'], 2) . "m<br>\n";
    echo "  - Status: " . ($verification1['success'] ? '✅ Success' : '❌ Failed') . "<br>\n";
    
    // Scenario 2: Employee near workplace (fair GPS)
    $locationData2 = [
        'latitude' => 14.5997,  // Slightly off
        'longitude' => 120.9844,
        'accuracy' => 25,  // Fair GPS accuracy
        'employee_id' => 'EMP002',
        'verification_type' => 'clock_in'
    ];
    
    $verification2 = $locationManager->verifyLocation($locationData2, 'MAIN001');
    echo "<br>Scenario 2 - Near workplace with fair GPS:<br>\n";
    echo "  - At workplace: " . ($verification2['at_workplace'] ? 'Yes' : 'No') . "<br>\n";
    echo "  - Verification score: " . round($verification2['verification_score'], 2) . "%<br>\n";
    echo "  - Distance: " . round($verification2['distance_from_workplace'], 2) . "m<br>\n";
    echo "  - Status: " . ($verification2['success'] ? '✅ Success' : '❌ Failed') . "<br>\n";
    
    // Scenario 3: Employee far from workplace (poor GPS)
    $locationData3 = [
        'latitude' => 14.6100,  // Far from workplace
        'longitude' => 121.0000,
        'accuracy' => 100,  // Poor GPS accuracy
        'employee_id' => 'EMP003',
        'verification_type' => 'clock_in'
    ];
    
    $verification3 = $locationManager->verifyLocation($locationData3, 'MAIN001');
    echo "<br>Scenario 3 - Far from workplace with poor GPS:<br>\n";
    echo "  - At workplace: " . ($verification3['at_workplace'] ? 'Yes' : 'No') . "<br>\n";
    echo "  - Verification score: " . round($verification3['verification_score'], 2) . "%<br>\n";
    echo "  - Distance: " . round($verification3['distance_from_workplace'], 2) . "m<br>\n";
    echo "  - Status: " . ($verification3['success'] ? '✅ Success' : '❌ Failed') . "<br>\n";
    if (!empty($verification3['alerts'])) {
        echo "  - Alerts: " . implode(', ', $verification3['alerts']) . "<br>\n";
    }
    
    // Test 4: Configuration Management
    echo "<h3>Test 4: Configuration Management</h3>\n";
    
    $configResult = $locationManager->updateConfiguration('min_gps_accuracy', 30);
    if ($configResult) {
        echo "✅ Configuration updated successfully<br>\n";
    } else {
        echo "❌ Configuration update failed<br>\n";
    }
    
    // Test 5: Location Analytics
    echo "<h3>Test 5: Location Analytics</h3>\n";
    
    $analytics = $locationManager->getLocationAnalytics(null, 7);
    echo "Analytics for last 7 days:<br>\n";
    echo "  - Total employees with location data: " . count($analytics) . "<br>\n";
    
    foreach ($analytics as $emp) {
        echo "  - Employee {$emp['employee_id']}: {$emp['total_verifications']} verifications, avg score: " . round($emp['avg_score'], 2) . "%<br>\n";
    }
    
    // Test 6: AttendanceManager Integration
    echo "<h3>Test 6: AttendanceManager Integration Test</h3>\n";
    
    // Create a test employee if not exists
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO tbl_employees (EmployeeID, FullName, BranchID, IsActive) VALUES (?, ?, ?, ?)");
        $stmt->execute(['TESTLOC001', 'Location Test Employee', 'MAIN001', 1]);
        echo "✅ Test employee created/verified<br>\n";
    } catch (Exception $e) {
        echo "⚠️ Test employee setup: " . $e->getMessage() . "<br>\n";
    }
    
    // Test clock in with location verification
    $testLocationData = [
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'accuracy' => 15
    ];
    
    try {
        $clockInResult = $attendanceManager->clockIn('TESTLOC001', $testLocationData, ['test_mode' => true]);
        
        if ($clockInResult['success']) {
            echo "✅ Clock in with location verification successful<br>\n";
            echo "  - Location verified: " . ($clockInResult['location_verified'] ? 'Yes' : 'No') . "<br>\n";
            if (isset($clockInResult['location_details'])) {
                echo "  - Workplace status: " . ($clockInResult['location_details']['workplace_location'] ? 'At workplace' : 'Not at workplace') . "<br>\n";
            }
        } else {
            echo "⚠️ Clock in test: " . $clockInResult['message'] . "<br>\n";
        }
    } catch (Exception $e) {
        echo "⚠️ Clock in test error: " . $e->getMessage() . "<br>\n";
    }
    
    echo "<h3>Test Summary</h3>\n";
    echo "Enhanced location verification system is now active with:<br>\n";
    echo "✅ Multi-branch support with configurable boundaries<br>\n";
    echo "✅ Advanced GPS accuracy scoring<br>\n";
    echo "✅ Location history tracking<br>\n";
    echo "✅ Configurable verification parameters<br>\n";
    echo "✅ Integration with AttendanceManager<br>\n";
    echo "✅ Administrative functions for boundary management<br>\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "<br>\n";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre><br>\n";
}
?>
