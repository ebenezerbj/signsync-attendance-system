<?php
include 'db.php';

$employee_id = $_GET['employee_id'] ?? '';
$latitude = $_GET['latitude'] ?? '';
$longitude = $_GET['longitude'] ?? '';

if (!$employee_id || !$latitude || !$longitude) {
    echo json_encode(['in_range' => false, 'branch' => null]);
    exit;
}

// Fetch all branches assigned to the employee
$stmt = $conn->prepare("
    SELECT b.* FROM tbl_branches b
    JOIN employee_branches eb ON eb.BranchID = b.BranchID
    WHERE eb.EmployeeID = ?
    UNION
    SELECT b.* FROM tbl_branches b
    JOIN tbl_employees e ON e.BranchID = b.BranchID
    WHERE e.EmployeeID = ?
");
$stmt->execute([$employee_id, $employee_id]);
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

function haversine($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c * 1000; // meters
}

foreach ($branches as $b) {
    $distance = haversine($latitude, $longitude, $b['Latitude'], $b['Longitude']);
    if ($distance <= $b['AllowedRadius']) {
        echo json_encode([
            'in_range' => true,
            'branch' => $b['BranchName'],
            'distance' => round($distance),
            'branch_lat' => $b['Latitude'],
            'branch_lng' => $b['Longitude'],
            'allowed_radius' => $b['AllowedRadius']
        ]);
        exit;
    }
}

// If not in range, still return the nearest branch for map display
$nearest = null;
$minDist = PHP_INT_MAX;
foreach ($branches as $b) {
    $distance = haversine($latitude, $longitude, $b['Latitude'], $b['Longitude']);
    if ($distance < $minDist) {
        $minDist = $distance;
        $nearest = $b;
    }
}
if ($nearest) {
    echo json_encode([
        'in_range' => false,
        'branch' => $nearest['BranchName'],
        'distance' => round($minDist),
        'branch_lat' => $nearest['Latitude'],
        'branch_lng' => $nearest['Longitude'],
        'allowed_radius' => $nearest['AllowedRadius']
    ]);
} else {
    echo json_encode(['in_range' => false, 'branch' => null]);
}