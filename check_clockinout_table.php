<?php
try {
    $conn = new PDO('mysql:host=localhost;dbname=attendance_register_db', 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Checking clockinout table structure ===\n";
    $result = $conn->query('DESCRIBE clockinout');
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\n=== Sample clockinout data ===\n";
    $result = $conn->query('SELECT * FROM clockinout LIMIT 3');
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($columns)) {
        echo "No data in clockinout table\n";
    } else {
        foreach ($columns as $row) {
            echo "Record ID {$row['id']}: ";
            foreach ($row as $key => $value) {
                if ($key !== 'id') {
                    echo "$key=$value, ";
                }
            }
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
