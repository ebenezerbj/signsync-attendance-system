<?php
include 'db.php';

// Fetch lookup data for dropdowns
$branches = $conn->query("SELECT BranchID, BranchName FROM tbl_branches ORDER BY BranchName")->fetchAll(PDO::FETCH_ASSOC);
$categories = $conn->query("SELECT CategoryID, CategoryName FROM employee_categories ORDER BY CategoryName")->fetchAll(PDO::FETCH_ASSOC);
$departments = $conn->query("SELECT DepartmentID, DepartmentName FROM tbl_departments ORDER BY DepartmentName")->fetchAll(PDO::FETCH_ASSOC);
$roles = $conn->query("SELECT RoleID, RoleName FROM tbl_roles ORDER BY RoleName")->fetchAll(PDO::FETCH_ASSOC);
$ranks = $conn->query("SELECT RankID, RankName FROM tbl_ranks ORDER BY RankName")->fetchAll(PDO::FETCH_ASSOC);

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Combine prefix with user input for EmployeeID
    $employeeIDSuffix = trim($_POST['EmployeeIDSuffix']);
    $employeeID = 'AKCBSTF' . $employeeIDSuffix;

    $fullName = trim($_POST['FullName']);
    $username = trim($_POST['Username']);
    $password = $_POST['Password'];
    $phone = trim($_POST['PhoneNumber']);
    $departmentID = $_POST['DepartmentID'] ?? null;
    $roleID = $_POST['RoleID'] ?? null;
    $rankID = $_POST['RankID'] ?? null;
    $categoryID = $_POST['CategoryID'] ?? null;
    $isSpecial = isset($_POST['is_special']) ? 1 : 0;
    $selectedBranches = $_POST['BranchIDs'] ?? [];

    if (
        !$employeeIDSuffix || !$fullName || !$username || !$password || !$phone ||
        !$departmentID || !$roleID || !$rankID || !$categoryID || empty($selectedBranches)
    ) {
        $error = "All fields are required.";
    } else {
        try {
            $conn->beginTransaction();

            // Check for duplicate EmployeeID or Username
            $check = $conn->prepare("SELECT COUNT(*) FROM tbl_employees WHERE EmployeeID = ? OR Username = ?");
            $check->execute([$employeeID, $username]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("Employee ID or Username already exists.");
            }

            $mainBranch = $selectedBranches[0]; // Assign the first selected branch as the primary
            $stmt = $conn->prepare(
                "INSERT INTO tbl_employees (EmployeeID, FullName, Username, Password, PhoneNumber, BranchID, DepartmentID, RoleID, CategoryID, IsSpecial, RankID)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $employeeID, $fullName, $username, password_hash($password, PASSWORD_DEFAULT),
                $phone, $mainBranch, $departmentID, $roleID, $categoryID, $isSpecial, $rankID
            ]);

            // Map all selected branches in the junction table
            $mapBranch = $conn->prepare("INSERT INTO employee_branches (EmployeeID, BranchID) VALUES (?, ?)");
            foreach ($selectedBranches as $branchID) {
                $mapBranch->execute([$employeeID, $branchID]);
            }

            // Assign default shift based on category
            $catStmt = $conn->prepare("SELECT DefaultShiftID FROM employee_categories WHERE CategoryID = ?");
            $catStmt->execute([$categoryID]);
            if ($defaultShiftID = $catStmt->fetchColumn()) {
                $mapShift = $conn->prepare("INSERT INTO employee_shifts (EmployeeID, ShiftID) VALUES (?, ?)");
                $mapShift->execute([$employeeID, $defaultShiftID]);
            }

            $conn->commit();
            $success = "Employee added successfully!";

        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Add New Employee</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .form-section { max-width: 650px; margin: 40px auto; background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 8px #0001; }
    .required { color: #d00; }
  </style>
</head>
<body>
  <div class="form-section">
    <h2 class="mb-4">Add New Employee</h2>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <form method="post" autocomplete="off">
      <!-- Employee ID -->
      <div class="mb-3">
        <label for="EmployeeIDSuffix" class="form-label">Employee ID <span class="required">*</span></label>
        <div class="input-group">
          <span class="input-group-text">AKCBSTF</span>
          <input name="EmployeeIDSuffix" id="EmployeeIDSuffix" class="form-control" required maxlength="8">
        </div>
      </div>
      <!-- Full Name -->
      <div class="mb-3">
        <label for="FullName" class="form-label">Full Name <span class="required">*</span></label>
        <input name="FullName" id="FullName" class="form-control" required>
      </div>
      <!-- Department -->
      <div class="mb-3">
        <label for="DepartmentID" class="form-label">Department <span class="required">*</span></label>
        <select name="DepartmentID" id="DepartmentID" class="form-select" required>
          <option value="">-- Select Department --</option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?= $dept['DepartmentID'] ?>"><?= htmlspecialchars($dept['DepartmentName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Role -->
       <div class="mb-3">
        <label for="RoleID" class="form-label">Role <span class="required">*</span></label>
        <select name="RoleID" id="RoleID" class="form-select" required>
          <option value="">-- Select Role --</option>
          <?php foreach ($roles as $role): ?>
            <option value="<?= $role['RoleID'] ?>"><?= htmlspecialchars($role['RoleName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Rank -->
      <div class="mb-3">
        <label for="RankID" class="form-label">Rank <span class="required">*</span></label>
        <select name="RankID" id="RankID" class="form-select" required>
          <option value="">-- Select Rank --</option>
          <?php foreach ($ranks as $rank): ?>
            <option value="<?= $rank['RankID'] ?>"><?= htmlspecialchars($rank['RankName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Username & Password -->
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="Username" class="form-label">Username <span class="required">*</span></label>
          <input name="Username" id="Username" class="form-control" required>
        </div>
        <div class="col-md-6 mb-3">
          <label for="Password" class="form-label">Password <span class="required">*</span></label>
          <input type="password" name="Password" id="Password" class="form-control" required>
        </div>
      </div>
      <!-- Phone Number -->
      <div class="mb-3">
        <label for="PhoneNumber" class="form-label">Phone Number <span class="required">*</span></label>
        <input name="PhoneNumber" id="PhoneNumber" class="form-control" required>
      </div>
      <!-- Branches -->
      <div class="mb-3">
        <label class="form-label">Assign Branch(es) <span class="required">*</span></label>
        <div class="d-flex flex-wrap gap-3">
          <?php foreach ($branches as $b): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="BranchIDs[]" id="branch<?= $b['BranchID'] ?>" value="<?= $b['BranchID'] ?>">
              <label class="form-check-label" for="branch<?= $b['BranchID'] ?>"><?= htmlspecialchars($b['BranchName']) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- Category -->
      <div class="mb-3">
        <label for="CategoryID" class="form-label">Employee Category <span class="required">*</span></label>
        <select name="CategoryID" id="CategoryID" class="form-select" required>
          <option value="">-- Select Category --</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['CategoryID'] ?>"><?= htmlspecialchars($cat['CategoryName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Special Employee -->
      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" name="is_special" id="is_special">
        <label class="form-check-label" for="is_special">Is Special Employee (e.g., exempt from standard clock-in rules)</label>
      </div>
      <button type="submit" class="btn btn-primary w-100">Add Employee</button>
    </form>
  </div>
</body>
</html>
