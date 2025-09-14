<?php
include 'db.php';

$sql = file_get_contents('enhanced_gamification_schema.sql');

try {
    $conn->exec($sql);
    echo "Enhanced gamification tables created successfully!\n";
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}
?>
