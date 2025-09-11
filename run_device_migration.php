<?php
include 'db.php';

echo "Running Device Registry Migration...\n";

try {
    // Read and execute the migration file
    $migrationFile = __DIR__ . '/migrations/20250910_device_registry.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    $statements = explode(';', $sql);
    
    $conn->beginTransaction();
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !str_starts_with($statement, '--')) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            try {
                $conn->exec($statement);
            } catch (PDOException $e) {
                // Skip duplicate key errors and continue
                if ($e->getCode() != 23000) {
                    throw $e;
                }
            }
        }
    }
    
    $conn->commit();
    echo "✅ Device Registry Migration completed successfully!\n";
    
    // Verify tables were created
    echo "\nVerifying tables...\n";
    $tables = ['tbl_devices', 'tbl_device_activity', 'tbl_device_groups', 'tbl_device_group_assignments'];
    foreach ($tables as $table) {
        $stmt = $conn->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "✅ $table: $count records\n";
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
