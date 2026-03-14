<?php
/**
 * Database Migration: Merge amakacom_hrm + attendance_register_db → signsync_db
 * 
 * This script:
 * 1. Creates signsync_db
 * 2. Copies ALL tables from attendance_register_db (structure + data)
 * 3. Copies ALL tables from amakacom_hrm (structure + data) — no name conflicts
 * 4. Merges HRM branches into tbl_branches using GH15100xx format
 * 5. Merges HRM departments into tbl_departments
 * 6. Imports HRM active employees into tbl_employees with correct FK references
 */

set_time_limit(300);
error_reporting(E_ALL);

$conn = new PDO('mysql:host=localhost', 'root', '');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "========================================\n";
echo "  SIGNSYNC DATABASE MIGRATION\n";
echo "========================================\n\n";

// ─── Step 1: Create signsync_db ───────────────────────────────
echo "[1/6] Creating signsync_db...\n";
$conn->exec("CREATE DATABASE IF NOT EXISTS signsync_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "  ✓ signsync_db created\n\n";

// ─── Step 2: Copy all tables from attendance_register_db ──────
echo "[2/6] Copying tables from attendance_register_db...\n";
$stmt = $conn->query("SHOW FULL TABLES FROM attendance_register_db");
$arItems = $stmt->fetchAll(PDO::FETCH_NUM);
$copied = 0;
$views = [];
foreach ($arItems as $item) {
    $table = $item[0];
    $type = $item[1]; // BASE TABLE or VIEW
    if ($type === 'VIEW') {
        $views[] = $table;
        continue;
    }
    $conn->exec("DROP TABLE IF EXISTS signsync_db.`$table`");
    $conn->exec("CREATE TABLE signsync_db.`$table` LIKE attendance_register_db.`$table`");
    
    // Check for generated columns - exclude them from INSERT
    $colStmt = $conn->query("SELECT COLUMN_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='attendance_register_db' AND TABLE_NAME='$table'");
    $allCols = [];
    $hasGenerated = false;
    while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
        if (stripos($col['EXTRA'], 'GENERATED') !== false || stripos($col['EXTRA'], 'VIRTUAL') !== false || stripos($col['EXTRA'], 'STORED') !== false) {
            $hasGenerated = true;
        } else {
            $allCols[] = '`' . $col['COLUMN_NAME'] . '`';
        }
    }
    
    if ($hasGenerated && !empty($allCols)) {
        $colList = implode(', ', $allCols);
        $conn->exec("INSERT INTO signsync_db.`$table` ($colList) SELECT $colList FROM attendance_register_db.`$table`");
    } else {
        $conn->exec("INSERT INTO signsync_db.`$table` SELECT * FROM attendance_register_db.`$table`");
    }
    $copied++;
}
// Copy views after all tables exist
foreach ($views as $view) {
    try {
        $vStmt = $conn->query("SHOW CREATE VIEW attendance_register_db.`$view`");
        $vRow = $vStmt->fetch(PDO::FETCH_ASSOC);
        $createSql = $vRow['Create View'];
        $createSql = str_replace('attendance_register_db', 'signsync_db', $createSql);
        $conn->exec("DROP VIEW IF EXISTS signsync_db.`$view`");
        $conn->exec("USE signsync_db");
        if (preg_match('/AS\s+(select .+)$/is', $createSql, $m)) {
            $conn->exec("CREATE OR REPLACE VIEW `$view` AS {$m[1]}");
        }
        $copied++;
        $conn->exec("USE signsync_db");
    } catch (Exception $e) {
        echo "  ⚠ Could not copy view '$view': " . $e->getMessage() . "\n";
    }
}
echo "  ✓ Copied $copied items from attendance_register_db (" . count($views) . " views)\n\n";

// ─── Step 3: Copy all tables from amakacom_hrm ───────────────
echo "[3/6] Copying tables from amakacom_hrm...\n";
$stmt = $conn->query("SHOW TABLES FROM amakacom_hrm");
$hrmItems = $stmt->fetchAll(PDO::FETCH_NUM);
$copied = 0;
$skipped = 0;
$hrmViews = [];
foreach ($hrmItems as $item) {
    $table = $item[0];
    $type = $item[1];
    if ($type === 'VIEW') {
        $hrmViews[] = $table;
        continue;
    }
    // Check if already exists (from attendance_register_db)
    $check = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='signsync_db' AND table_name='$table' AND table_type='BASE TABLE'");
    if ($check->fetchColumn() > 0) {
        echo "  ⚠ Skipped '$table' (already exists from attendance_register_db)\n";
        $skipped++;
        continue;
    }
    $conn->exec("CREATE TABLE signsync_db.`$table` LIKE amakacom_hrm.`$table`");
    
    // Check for generated columns
    $colStmt = $conn->query("SELECT COLUMN_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='amakacom_hrm' AND TABLE_NAME='$table'");
    $allCols = [];
    $hasGenerated = false;
    while ($col = $colStmt->fetch(PDO::FETCH_ASSOC)) {
        if (stripos($col['EXTRA'], 'GENERATED') !== false || stripos($col['EXTRA'], 'VIRTUAL') !== false || stripos($col['EXTRA'], 'STORED') !== false) {
            $hasGenerated = true;
        } else {
            $allCols[] = '`' . $col['COLUMN_NAME'] . '`';
        }
    }
    
    if ($hasGenerated && !empty($allCols)) {
        $colList = implode(', ', $allCols);
        $conn->exec("INSERT INTO signsync_db.`$table` ($colList) SELECT $colList FROM amakacom_hrm.`$table`");
    } else {
        $conn->exec("INSERT INTO signsync_db.`$table` SELECT * FROM amakacom_hrm.`$table`");
    }
    $copied++;
}
// Copy views
foreach ($hrmViews as $view) {
    try {
        $check = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='signsync_db' AND table_name='$view'");
        if ($check->fetchColumn() > 0) {
            $skipped++;
            continue;
        }
        $vStmt = $conn->query("SHOW CREATE VIEW amakacom_hrm.`$view`");
        $vRow = $vStmt->fetch(PDO::FETCH_ASSOC);
        $createSql = $vRow['Create View'];
        $createSql = str_replace('amakacom_hrm', 'signsync_db', $createSql);
        $conn->exec("USE signsync_db");
        if (preg_match('/AS\s+(select .+)$/is', $createSql, $m)) {
            $conn->exec("CREATE OR REPLACE VIEW `$view` AS {$m[1]}");
        }
        $copied++;
    } catch (Exception $e) {
        echo "  ⚠ Could not copy view '$view': " . $e->getMessage() . "\n";
    }
}
echo "  ✓ Copied $copied items from amakacom_hrm ($skipped skipped)\n\n";

// ─── Step 4: Merge HRM branches into tbl_branches ────────────
echo "[4/6] Merging HRM branches into tbl_branches (GH format)...\n";

// Use signsync_db from now on
$conn->exec("USE signsync_db");

// Map existing attendance branches
// GH1510010 = HEAD OFFICE, GH1510013 = EJURA
// HRM branches: AHWIAA, AMANTIN, ATEBUBU, EJURA, HEAD OFFICE, KAJAJI, KEJETIA, KWAME DANSO, YEJI

// Get existing max BranchID number to continue the sequence
// Existing: GH1510010, GH1510013 → next available: GH1510014
$nextBranchNum = 14;

// Build branch mapping: HRM branchid → new GH BranchID
$branchMapping = [];

// First, map HRM branches that match existing tbl_branches by name
$existingBranches = [];
$stmt = $conn->query("SELECT BranchID, BranchName FROM tbl_branches");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingBranches[strtoupper(trim($row['BranchName']))] = $row['BranchID'];
}

// HRM branch data: branchid (numeric code) → branchname
$hrmBranches = [];
$stmt = $conn->query("SELECT branchid, branchname FROM branch");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $hrmBranches[$row['branchid']] = trim($row['branchname']);
}

// Also collect distinct branchid/branchname from actual employee records
$stmt = $conn->query("SELECT DISTINCT branchid, branchname FROM employee WHERE status='Active' AND branchid IS NOT NULL AND branchid != ''");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($hrmBranches[$row['branchid']])) {
        $hrmBranches[$row['branchid']] = trim($row['branchname']);
    }
}

// Branch location coordinates (approximate for Ghanaian towns)
$branchCoords = [
    'HEAD OFFICE'  => ['lat' => 7.541030, 'lng' => -1.205351, 'loc' => 'AMANTIN - BONO EAST'],
    'EJURA'        => ['lat' => 7.384000, 'lng' => -1.355973, 'loc' => 'EJURA - ASHANTI'],
    'AMANTIN'      => ['lat' => 7.541030, 'lng' => -1.205351, 'loc' => 'AMANTIN - BONO EAST'],
    'ATEBUBU'      => ['lat' => 7.753070, 'lng' => -0.983680, 'loc' => 'ATEBUBU - BONO EAST'],
    'YEJI'         => ['lat' => 8.216280, 'lng' => -0.663600, 'loc' => 'YEJI - BONO EAST'],
    'KWAME DANSO'  => ['lat' => 7.679700, 'lng' => -0.415200, 'loc' => 'KWAME DANSO - BONO EAST'],
    'KAJAJI'       => ['lat' => 7.600000, 'lng' => -1.100000, 'loc' => 'KAJAJI - BONO EAST'],
    'AHWIAA'       => ['lat' => 6.748100, 'lng' => -1.588500, 'loc' => 'AHWIAA - ASHANTI'],
    'KEJETIA'      => ['lat' => 6.690000, 'lng' => -1.622000, 'loc' => 'KEJETIA - ASHANTI'],
];

$insertBranch = $conn->prepare("
    INSERT INTO tbl_branches (BranchID, BranchName, BranchLocation, Latitude, Longitude, AllowedRadius) 
    VALUES (?, ?, ?, ?, ?, 100.00)
    ON DUPLICATE KEY UPDATE BranchName=VALUES(BranchName)
");

foreach ($hrmBranches as $hrmCode => $branchName) {
    $normalizedName = strtoupper(trim($branchName));
    
    if (isset($existingBranches[$normalizedName])) {
        // Branch already exists in tbl_branches
        $branchMapping[$hrmCode] = $existingBranches[$normalizedName];
        echo "  → Mapped HRM '$branchName' ($hrmCode) → existing {$existingBranches[$normalizedName]}\n";
    } else {
        // Create new branch with GH format
        $newBranchId = 'GH15100' . str_pad($nextBranchNum, 2, '0', STR_PAD_LEFT);
        $nextBranchNum++;
        
        $coords = $branchCoords[$normalizedName] ?? ['lat' => 0.0, 'lng' => 0.0, 'loc' => $normalizedName];
        $insertBranch->execute([$newBranchId, $normalizedName, $coords['loc'], $coords['lat'], $coords['lng']]);
        
        $branchMapping[$hrmCode] = $newBranchId;
        $existingBranches[$normalizedName] = $newBranchId;
        echo "  + Created new branch '$normalizedName' → $newBranchId\n";
    }
}

echo "  ✓ Branch mapping complete (" . count($branchMapping) . " HRM branches mapped)\n\n";

// ─── Step 5: Merge HRM departments into tbl_departments ──────
echo "[5/6] Merging HRM departments into tbl_departments...\n";

// Map HRM department names to existing tbl_departments
$existingDepts = [];
$stmt = $conn->query("SELECT DepartmentID, DepartmentName FROM tbl_departments");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingDepts[strtoupper(trim($row['DepartmentName']))] = $row['DepartmentID'];
}

// HRM department name → attendance DepartmentID mapping
$deptNameMap = [
    'ADMINISTRATION & HRM'   => 'Human Resources/Administrator',
    'ICT'                    => 'IT Department',
    'CREDIT'                 => 'Credit Department',
    'AUDIT & INSPECTION'     => 'Audit Department',
    'AML, RISK & COMPLIANCE' => 'AML/Compliance Department',
    'OPERATIONS'             => 'Operations',
    'EXECUTIVE'              => null, // New
];

$deptMapping = []; // HRM depid → tbl_departments DepartmentID

// Get max DepartmentID for auto-increment
$maxDeptId = $conn->query("SELECT MAX(DepartmentID) FROM tbl_departments")->fetchColumn();
$nextDeptId = $maxDeptId + 1;

$hrmDepts = [];
$stmt = $conn->query("SELECT id, dep_name, code FROM department");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $hrmDepts[$row['code']] = trim($row['dep_name']);
}

$insertDept = $conn->prepare("INSERT INTO tbl_departments (DepartmentID, DepartmentName, Description) VALUES (?, ?, ?)");

foreach ($hrmDepts as $hrmCode => $deptName) {
    $normalizedName = strtoupper(trim($deptName));
    
    // Check if we have a manual mapping
    $mappedTo = null;
    foreach ($deptNameMap as $hrmName => $attName) {
        if (strtoupper($hrmName) === $normalizedName) {
            if ($attName !== null) {
                // Find the existing ID
                foreach ($existingDepts as $existName => $existId) {
                    if (strtoupper($attName) === $existName) {
                        $mappedTo = $existId;
                        break;
                    }
                }
            }
            break;
        }
    }
    
    if ($mappedTo !== null) {
        $deptMapping[$hrmCode] = $mappedTo;
        echo "  → Mapped HRM '$deptName' ($hrmCode) → existing DepartmentID $mappedTo\n";
    } else {
        // Create new department
        $insertDept->execute([$nextDeptId, $normalizedName, "Imported from HRM system"]);
        $deptMapping[$hrmCode] = $nextDeptId;
        $existingDepts[$normalizedName] = $nextDeptId;
        echo "  + Created new department '$normalizedName' → DepartmentID $nextDeptId\n";
        $nextDeptId++;
    }
}

echo "  ✓ Department mapping complete (" . count($deptMapping) . " HRM departments mapped)\n\n";

// ─── Step 6: Import HRM employees into tbl_employees ─────────
echo "[6/6] Importing HRM active employees into tbl_employees...\n";

// Get existing employee IDs to avoid duplicates
$existingEmps = [];
$stmt = $conn->query("SELECT EmployeeID FROM tbl_employees");
while ($row = $stmt->fetch(PDO::FETCH_COLUMN)) {
    $existingEmps[strtoupper($row)] = true;
}

// Designation → RoleID mapping
$roleMapping = [
    'CHIEF EXECUTIVE OFFICER'              => 1, // Administrator
    'HEAD OF ADMINISTATION & HRM'          => 2, // Manager
    'HEAD OF AML, RISK & COMPLIANCE'       => 2,
    'HEAD OF BANKING OPERATIONS & FINANCE' => 2,
    'HEAD OF CREDIT'                       => 2,
    'HEAD OF ICT'                          => 2,
    'HEAD OF INTERNAL AUDIT & INSPECTION'  => 2,
    'MARKETING MANAGER'                    => 2,
    'BRANCH MANAGER'                       => 2,
    'MICROFINANCE COORDINATOR'             => 2,
];

// Designation → RankID mapping
$rankMapping = [
    'CHIEF EXECUTIVE OFFICER'              => 1, // CEO
    'HEAD OF ADMINISTATION & HRM'          => 2, // MANAGEMENT
    'HEAD OF AML, RISK & COMPLIANCE'       => 2,
    'HEAD OF BANKING OPERATIONS & FINANCE' => 2,
    'HEAD OF CREDIT'                       => 2,
    'HEAD OF ICT'                          => 2,
    'HEAD OF INTERNAL AUDIT & INSPECTION'  => 2,
    'MARKETING MANAGER'                    => 3, // MIDDLE-MANAGEMENT
    'BRANCH MANAGER'                       => 3,
    'MICROFINANCE COORDINATOR'             => 3,
    'ACCOUNTANT'                           => 4, // OFFICERS
    'ASSOCIATE RELATIONSHIP OFFICER'       => 4,
    'BRANCH OPERATIONS OFFICER'            => 4,
    'BRANCH RELATIONSHIP OFFICER'          => 4,
    'CUSTOMER SERVICE ASSOCIATE'           => 4,
    'ICT OFFICER'                          => 4,
    'INTERNAL AUDITOR'                     => 4,
    'MOBILE BANKER'                        => 4,
    'DRIVER'                               => 5, // ANCILLARY STAFF
    'MESSENGER/OFFICE ASSISTANT'           => 5,
    'SECURITY GUARD'                       => 5,
];

// Designation → CategoryID mapping
$categoryMapping = [
    'SECURITY GUARD'           => 1, // Security
    'DRIVER'                   => 3, // Cleaner (ancillary)
    'MESSENGER/OFFICE ASSISTANT' => 3,
];

$insertEmp = $conn->prepare("
    INSERT INTO tbl_employees (EmployeeID, FullName, Username, Password, PhoneNumber, BranchID, DepartmentID, RoleID, CategoryID, IsSpecial, RankID, CustomPIN, PINSetupComplete)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NULL, 0)
");

$imported = 0;
$skippedEmps = 0;
$errors = [];

// Get all active HRM employees
$stmt = $conn->query("
    SELECT staffid, firstname, lastname, ememail, empassword, emphone, 
           branchid, branchname, depid, department, designation, emrole, status
    FROM employee 
    WHERE status = 'Active' 
    AND staffid != 'admin'
    ORDER BY staffid
");

while ($emp = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $staffId = strtoupper(trim($emp['staffid']));
    
    // Skip if numeric-only staffid (incomplete records)
    if (preg_match('/^\d+$/', $staffId)) {
        $skippedEmps++;
        echo "  ⚠ Skipped numeric staffid: $staffId ({$emp['firstname']} {$emp['lastname']})\n";
        continue;
    }
    
    // Map staffid → EmployeeID format
    // Existing format: AKCBST0001 in HRM → we'll keep HRM staffids but add 'F' if needed to match existing pattern
    // Existing tbl_employees uses AKCBSTF0005 format (with F)
    // HRM uses AKCBST0001 format (without F)
    // We'll convert: AKCBST0001 → AKCBSTF0001 to match the existing convention
    $employeeId = $staffId;
    if (preg_match('/^AKCBST(\d+)$/', $staffId, $m)) {
        $employeeId = 'AKCBSTF' . $m[1];
    }
    
    // Skip if already exists
    if (isset($existingEmps[strtoupper($employeeId)])) {
        $skippedEmps++;
        echo "  ⚠ Skipped existing: $employeeId ({$emp['firstname']} {$emp['lastname']})\n";
        continue;
    }
    
    $fullName = trim($emp['firstname'] . ' ' . $emp['lastname']);
    
    // Generate username from first initial + lastname
    $firstName = trim($emp['firstname']);
    $lastName = trim($emp['lastname']);
    $username = strtoupper(substr($firstName, 0, 1) . $lastName);
    $username = preg_replace('/[^A-Z0-9]/', '', $username); // Remove special chars
    
    // Ensure unique username
    $baseUsername = $username;
    $usernameNum = 1;
    while (true) {
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM tbl_employees WHERE Username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetchColumn() == 0) break;
        $username = $baseUsername . $usernameNum;
        $usernameNum++;
    }
    
    // Default password (bcrypt hash of "1234")
    $password = password_hash('1234', PASSWORD_DEFAULT);
    
    // Format phone number
    $phone = trim($emp['emphone']);
    if (!empty($phone)) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        // Convert 0xx to 233xx
        if (preg_match('/^0(\d{9})$/', $phone, $m)) {
            $phone = '233' . $m[1];
        } elseif (preg_match('/^\+233(\d{9})$/', $phone, $m)) {
            $phone = '233' . $m[1];
        }
    }
    
    // Map branch
    $branchId = $branchMapping[$emp['branchid']] ?? 'GH1510010'; // Default to HEAD OFFICE
    
    // Map department
    $deptId = $deptMapping[$emp['depid']] ?? 1; // Default to HR
    
    // Map role (default: Employee=3)
    $designation = strtoupper(trim($emp['designation']));
    $roleId = $roleMapping[$designation] ?? 3;
    
    // Map rank
    $rankId = $rankMapping[$designation] ?? 4; // Default: OFFICERS
    
    // Map category (default: General-Staff=4)
    $categoryId = $categoryMapping[$designation] ?? 4;
    
    try {
        $insertEmp->execute([
            $employeeId, $fullName, $username, $password, $phone,
            $branchId, $deptId, $roleId, $categoryId, $rankId
        ]);
        $imported++;
        echo "  + Imported: $employeeId - $fullName ($designation) → Branch:$branchId Dept:$deptId\n";
        $existingEmps[strtoupper($employeeId)] = true;
    } catch (Exception $e) {
        $errors[] = "$employeeId: " . $e->getMessage();
        echo "  ✗ Error: $employeeId - " . $e->getMessage() . "\n";
    }
}

echo "\n  ✓ Imported $imported employees, skipped $skippedEmps\n";
if (!empty($errors)) {
    echo "  ✗ " . count($errors) . " errors occurred\n";
}

// ─── Summary ──────────────────────────────────────────────────
echo "\n========================================\n";
echo "  MIGRATION COMPLETE\n";
echo "========================================\n";

// Verify counts
$totalTables = $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='signsync_db'")->fetchColumn();
$totalEmployees = $conn->query("SELECT COUNT(*) FROM signsync_db.tbl_employees")->fetchColumn();
$totalBranches = $conn->query("SELECT COUNT(*) FROM signsync_db.tbl_branches")->fetchColumn();
$totalDepts = $conn->query("SELECT COUNT(*) FROM signsync_db.tbl_departments")->fetchColumn();

echo "  Total tables:      $totalTables\n";
echo "  Total branches:    $totalBranches\n";
echo "  Total departments: $totalDepts\n";
echo "  Total employees:   $totalEmployees\n";
echo "\n  Branch list:\n";
$stmt = $conn->query("SELECT BranchID, BranchName FROM signsync_db.tbl_branches ORDER BY BranchID");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cnt = $conn->query("SELECT COUNT(*) FROM signsync_db.tbl_employees WHERE BranchID='{$row['BranchID']}'")->fetchColumn();
    echo "    {$row['BranchID']} - {$row['BranchName']} ($cnt employees)\n";
}

echo "\n  Next step: Update db.php to use 'signsync_db'\n";
echo "========================================\n";
