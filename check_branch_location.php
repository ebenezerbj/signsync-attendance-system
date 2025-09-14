<?php
include 'db.php';

echo "=== Branch Location Info ===\n";

try {
    $stmt = $conn->prepare('SELECT BranchName, Latitude, Longitude, AllowedRadius FROM tbl_branches WHERE BranchID = (SELECT BranchID FROM tbl_employees WHERE EmployeeID = "AKCBSTF0005")');
    $stmt->execute();
    $branch = $stmt->fetch();
    
    if ($branch) {
        echo "AKCBSTF0005's Branch: " . $branch['BranchName'] . "\n";
        echo "Location: " . $branch['Latitude'] . "," . $branch['Longitude'] . "\n";
        echo "Allowed Radius: " . $branch['AllowedRadius'] . "m\n";
        
        // Test coordinates should be within this radius
        $testLat = $branch['Latitude'] + 0.0001; // Very close to branch
        $testLon = $branch['Longitude'] + 0.0001;
        echo "\nTest coordinates (should be within range): {$testLat},{$testLon}\n";
    } else {
        echo "No branch found for AKCBSTF0005\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
