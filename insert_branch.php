<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $BranchID       = trim($_POST['BranchID']);
    $BranchName     = trim($_POST['BranchName']);
    $BranchLocation = trim($_POST['BranchLocation']);
    $Latitude       = trim($_POST['Latitude']);
    $Longitude      = trim($_POST['Longitude']);
    $AllowedRadius  = trim($_POST['AllowedRadius']);

    try {
        $sql = "INSERT INTO tbl_branches (BranchID, BranchName, BranchLocation, Latitude, Longitude, AllowedRadius)
                VALUES (:BranchID, :BranchName, :BranchLocation, :Latitude, :Longitude, :AllowedRadius)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':BranchID'       => $BranchID,
            ':BranchName'     => $BranchName,
            ':BranchLocation' => $BranchLocation,
            ':Latitude'       => $Latitude,
            ':Longitude'      => $Longitude,
            ':AllowedRadius'  => $AllowedRadius
        ]);

        echo "✅ Branch added successfully!";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
} else {
    echo "Invalid request.";
}
?>
