<?php
include 'db.php';

echo "Configured Branch Locations:\n";
echo "===========================\n";

$stmt = $conn->query('SELECT BranchID, BranchName, Latitude, Longitude FROM tbl_branches');
while($row = $stmt->fetch()) {
    echo "Branch ID: " . $row['BranchID'] . "\n";
    echo "Name: " . $row['BranchName'] . "\n";
    echo "Latitude: " . $row['Latitude'] . "\n";
    echo "Longitude: " . $row['Longitude'] . "\n";
    echo "---\n";
}

echo "\nTest coordinates used: 5.6037, -0.1870\n";
echo "This appears to be Accra, Ghana coordinates\n";
?>
