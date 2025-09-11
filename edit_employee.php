<?php
// filepath: c:\laragon\www\attendance_register\edit_employee.php
include 'db.php';

$employee_id = $_GET['id'] ?? null;
if (!$employee_id) {
    die("Employee ID is required.");
}

// Fetch current employee data
$stmt = $conn->prepare("SELECT * FROM tbl_employees WHERE EmployeeID = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee not found.");
}

// Fetch lookup data for dropdowns
$branches = $conn->query("SELECT BranchID, BranchName FROM tbl_branches ORDER BY BranchName")->fetchAll(PDO::FETCH_ASSOC);
$categories = $conn->query("SELECT CategoryID, CategoryName FROM employee_categories ORDER BY CategoryName")->fetchAll(PDO::FETCH_ASSOC);
$departments = $conn->query("SELECT DepartmentID, DepartmentName FROM tbl_departments ORDER BY DepartmentName")->fetchAll(PDO::FETCH_ASSOC);
$roles = $conn->query("SELECT RoleID, RoleName FROM tbl_roles ORDER BY RoleName")->fetchAll(PDO::FETCH_ASSOC);
$ranks = $conn->query("SELECT RankID, RankName FROM tbl_ranks ORDER BY RankName")->fetchAll(PDO::FETCH_ASSOC);

// Fetch currently assigned branches for this employee
$assigned_branches_stmt = $conn->prepare("SELECT BranchID FROM employee_branches WHERE EmployeeID = ?");
$assigned_branches_stmt->execute([$employee_id]);
$assigned_branches = $assigned_branches_stmt->fetchAll(PDO::FETCH_COLUMN);

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        $fullName = trim($_POST['FullName']);
        $username = trim($_POST['Username']);
        $password = $_POST['Password']; // Optional
        $phone = trim($_POST['PhoneNumber']);
        $departmentID = $_POST['DepartmentID'] ?? null;
        $roleID = $_POST['RoleID'] ?? null;
        $rankID = $_POST['RankID'] ?? null;
        $categoryID = $_POST['CategoryID'] ?? null;
        $isSpecial = isset($_POST['is_special']) ? 1 : 0;
        $selectedBranches = $_POST['BranchIDs'] ?? [];
        $mainBranch = $selectedBranches[0] ?? $employee['BranchID'];

        // Basic validation
        if (!$fullName || !$username || !$phone || !$departmentID || !$roleID || !$rankID || !$categoryID || empty($selectedBranches)) {
            throw new Exception("All fields except password are required.");
        }

        // Check for username conflict (if changed)
        if ($username !== $employee['Username']) {
            $check = $conn->prepare("SELECT COUNT(*) FROM tbl_employees WHERE Username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("Username already exists.");
            }
        }

        // Prepare the update query
        $sql = "UPDATE tbl_employees SET FullName=?, Username=?, PhoneNumber=?, BranchID=?, DepartmentID=?, RoleID=?, CategoryID=?, IsSpecial=?, RankID=?";
        $params = [$fullName, $username, $phone, $mainBranch, $departmentID, $roleID, $categoryID, $isSpecial, $rankID];

        // If password is provided, add it to the query
        if (!empty($password)) {
            $sql .= ", Password=?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE EmployeeID = ?";
        $params[] = $employee_id;

        $update_stmt = $conn->prepare($sql);
        $update_stmt->execute($params);

        // Update branch mappings: delete old, insert new
        $conn->prepare("DELETE FROM employee_branches WHERE EmployeeID = ?")->execute([$employee_id]);
        $mapBranch = $conn->prepare("INSERT INTO employee_branches (EmployeeID, BranchID) VALUES (?, ?)");
        foreach ($selectedBranches as $branchID) {
            $mapBranch->execute([$employee_id, $branchID]);
        }

        $conn->commit();
        $success = "Employee updated successfully! <a href='admin_dashboard.php'>Back to Dashboard</a>";

        // Refresh employee data to show updated values in the form
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        $assigned_branches_stmt->execute([$employee_id]);
        $assigned_branches = $assigned_branches_stmt->fetchAll(PDO::FETCH_COLUMN);

    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Edit Employee</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .form-section { max-width: 650px; margin: 40px auto; background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 8px #0001; }
    .required { color: #d00; }
  </style>
</head>
<body>
  <div class="form-section">
    <h2 class="mb-4">Edit Employee: <?= htmlspecialchars($employee['FullName']) ?></h2>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <form method="post" autocomplete="off">
      <!-- Employee ID (Read-only) -->
      <div class="mb-3">
        <label class="form-label">Employee ID</label>
        <input class="form-control" value="<?= htmlspecialchars($employee['EmployeeID']) ?>" readonly>
      </div>
      <!-- Full Name -->
      <div class="mb-3">
        <label for="FullName" class="form-label">Full Name <span class="required">*</span></label>
        <input name="FullName" id="FullName" class="form-control" value="<?= htmlspecialchars($employee['FullName']) ?>" required>
      </div>
      <!-- Department -->
      <div class="mb-3">
        <label for="DepartmentID" class="form-label">Department <span class="required">*</span></label>
        <select name="DepartmentID" id="DepartmentID" class="form-select" required>
          <?php foreach ($departments as $dept): ?>
            <option value="<?= $dept['DepartmentID'] ?>" <?= $employee['DepartmentID'] == $dept['DepartmentID'] ? 'selected' : '' ?>><?= htmlspecialchars($dept['DepartmentName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Role -->
       <div class="mb-3">
        <label for="RoleID" class="form-label">Role <span class="required">*</span></label>
        <select name="RoleID" id="RoleID" class="form-select" required>
          <?php foreach ($roles as $role): ?>
            <option value="<?= $role['RoleID'] ?>" <?= $employee['RoleID'] == $role['RoleID'] ? 'selected' : '' ?>><?= htmlspecialchars($role['RoleName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Rank -->
      <div class="mb-3">
        <label for="RankID" class="form-label">Rank <span class="required">*</span></label>
        <select name="RankID" id="RankID" class="form-select" required>
          <?php foreach ($ranks as $rank): ?>
            <option value="<?= $rank['RankID'] ?>" <?= $employee['RankID'] == $rank['RankID'] ? 'selected' : '' ?>><?= htmlspecialchars($rank['RankName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Username & Password -->
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="Username" class="form-label">Username <span class="required">*</span></label>
          <input name="Username" id="Username" class="form-control" value="<?= htmlspecialchars($employee['Username']) ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label for="Password" class="form-label">New Password</label>
          <input type="password" name="Password" id="Password" class="form-control" placeholder="Leave blank to keep current">
        </div>
      </div>
      <!-- Phone Number -->
      <div class="mb-3">
        <label for="PhoneNumber" class="form-label">Phone Number <span class="required">*</span></label>
        <input name="PhoneNumber" id="PhoneNumber" class="form-control" value="<?= htmlspecialchars($employee['PhoneNumber']) ?>" required>
      </div>
      <!-- Branches -->
      <div class="mb-3">
        <label class="form-label">Assign Branch(es) <span class="required">*</span></label>
        <div class="d-flex flex-wrap gap-3">
          <?php foreach ($branches as $b): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="BranchIDs[]" id="branch<?= $b['BranchID'] ?>" value="<?= $b['BranchID'] ?>" <?= in_array($b['BranchID'], $assigned_branches) ? 'checked' : '' ?>>
              <label class="form-check-label" for="branch<?= $b['BranchID'] ?>"><?= htmlspecialchars($b['BranchName']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- Category -->
      <div class="mb-3">
        <label for="CategoryID" class="form-label">Employee Category <span class="required">*</span></label>
        <select name="CategoryID" id="CategoryID" class="form-select" required>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['CategoryID'] ?>" <?= $employee['CategoryID'] == $cat['CategoryID'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['CategoryName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Special Employee -->
      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" name="is_special" id="is_special" <?= $employee['IsSpecial'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="is_special">Is Special Employee</label>
      </div>
      <button type="submit" class="btn btn-primary w-100">Update Employee</button>
    </form>
  </div>
</body>
</html>
