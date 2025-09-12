<?php
include 'db.php';
$sql = file_get_contents('create_clockinout.sql');
$conn->exec($sql);
echo "clockinout table created successfully\n";
?>
