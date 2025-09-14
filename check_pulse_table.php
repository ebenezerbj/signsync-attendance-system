<?php
include 'db.php';
try {
    $stmt = $conn->query('DESCRIBE tbl_pulse_surveys');
    echo "tbl_pulse_surveys columns:\n";
    while($row = $stmt->fetch()) {
        echo "- " . $row[0] . "\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
