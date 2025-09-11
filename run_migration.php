<?php
// Run the indoor presence migration via PDO without stored procedures/DELIMITER parsing
header('Content-Type: application/json');
include __DIR__ . '/db.php';

$file = __DIR__ . '/migrations/20250910_add_indoor_presence.sql';
if (!file_exists($file)) {
    http_response_code(404);
    echo json_encode(['error' => 'Migration file not found', 'path' => $file]);
    exit;
}

$sql = file_get_contents($file);
if ($sql === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to read migration file']);
    exit;
}

// Only execute statements before any DELIMITER usage
$parts = preg_split('/^\s*DELIMITER\b/mi', $sql);
$main = $parts[0];

// Split main by ; and execute
$stmts = array_filter(array_map('trim', explode(';', $main)), function($s){ return $s !== '' && strpos($s, 'START TRANSACTION') === false && strpos($s, 'COMMIT') === false; });

$executed = [];

try {
    $conn->beginTransaction();
    foreach ($stmts as $stmt) {
        $conn->exec($stmt);
        $executed[] = substr($stmt, 0, 100) . (strlen($stmt) > 100 ? '...' : '');
    }

    // Dynamic alignment and FKs (in PHP)

    // Helper: check constraint exists
    $constraintExists = function(PDO $c, string $name): bool {
        $q = $c->prepare("SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?");
        $q->execute([$name]);
        return (bool)$q->fetchColumn();
    };

    // Helper: add column if missing
    $ensureColumn = function(PDO $c, string $table, string $column, string $definition) use (&$executed) {
        $q = $c->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $q->execute([$table, $column]);
        if (!$q->fetchColumn()) {
            $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
            $c->exec($sql);
            $executed[] = $sql;
        }
    };

    // Align BranchID children to parent type/charset/collation
    $parent = $conn->query("SELECT COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_branches' AND COLUMN_NAME = 'BranchID'")->fetch(PDO::FETCH_ASSOC);
    if ($parent) {
        $type = $parent['COLUMN_TYPE'];
        $charset = $parent['CHARACTER_SET_NAME'];
        $collation = $parent['COLLATION_NAME'];
        foreach (['tbl_branch_beacons','tbl_branch_wifi'] as $child) {
            $def = $type;
            if ($charset) $def .= " CHARACTER SET $charset";
            if ($collation) $def .= " COLLATE $collation";
            $sqlMod = "ALTER TABLE `$child` MODIFY COLUMN `BranchID` $def NOT NULL";
            $conn->exec($sqlMod);
            $executed[] = $sqlMod;
        }
        if (!$constraintExists($conn, 'fk_branch_beacons_branch')) {
            $sqlFk = "ALTER TABLE `tbl_branch_beacons` ADD CONSTRAINT `fk_branch_beacons_branch` FOREIGN KEY (`BranchID`) REFERENCES `tbl_branches` (`BranchID`) ON DELETE CASCADE ON UPDATE CASCADE";
            $conn->exec($sqlFk);
            $executed[] = $sqlFk;
        }
        if (!$constraintExists($conn, 'fk_branch_wifi_branch')) {
            $sqlFk = "ALTER TABLE `tbl_branch_wifi` ADD CONSTRAINT `fk_branch_wifi_branch` FOREIGN KEY (`BranchID`) REFERENCES `tbl_branches` (`BranchID`) ON DELETE CASCADE ON UPDATE CASCADE";
            $conn->exec($sqlFk);
            $executed[] = $sqlFk;
        }
    }

    // Align EmployeeID in wearable_devices and add FK
    $emp = $conn->query("SELECT COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_employees' AND COLUMN_NAME = 'EmployeeID'")->fetch(PDO::FETCH_ASSOC);
    if ($emp) {
        $type = $emp['COLUMN_TYPE'];
        $charset = $emp['CHARACTER_SET_NAME'];
        $collation = $emp['COLLATION_NAME'];
    $def = $type;
    if ($charset) $def .= " CHARACTER SET $charset";
    if ($collation) $def .= " COLLATE $collation";
    $sqlMod = "ALTER TABLE `tbl_wearable_devices` MODIFY COLUMN `EmployeeID` $def NOT NULL";
        $conn->exec($sqlMod);
        $executed[] = $sqlMod;

        if (!$constraintExists($conn, 'fk_wd_employee')) {
            $sqlFk = "ALTER TABLE `tbl_wearable_devices` ADD CONSTRAINT `fk_wd_employee` FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employees` (`EmployeeID`) ON DELETE CASCADE ON UPDATE CASCADE";
            $conn->exec($sqlFk);
            $executed[] = $sqlFk;
        }
    }

    // Attendance method columns
    $ensureColumn($conn, 'tbl_attendance', 'ClockInMethod', 'VARCHAR(20) NULL AFTER `Remarks`');
    // Place ClockOutMethod after ClockInMethod if present
    $hasClockIn = (bool)$conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_attendance' AND COLUMN_NAME = 'ClockInMethod'")->fetchColumn();
    if ($hasClockIn) {
        $ensureColumn($conn, 'tbl_attendance', 'ClockOutMethod', 'VARCHAR(20) NULL AFTER `ClockInMethod`');
    } else {
        $ensureColumn($conn, 'tbl_attendance', 'ClockOutMethod', 'VARCHAR(20) NULL AFTER `Remarks`');
    }

    $conn->commit();
    echo json_encode(['ok' => true, 'executed' => $executed]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'executed' => $executed]);
}
?>