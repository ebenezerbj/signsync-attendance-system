<?php
// filepath: c:\laragon\www\attendance_register\delete_employee.php
include 'db.php';

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $employeeID = $_POST['delete_id'];
    try {
        // Remove from mapping table first
        $conn->prepare("DELETE FROM employee_branches WHERE EmployeeID=?")->execute([$employeeID]);
        // Remove from main table
        $conn->prepare("DELETE FROM tbl_employees WHERE EmployeeID=?")->execute([$employeeID]);
        $success = "Employee deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$employees = $conn->query("SELECT EmployeeID, FullName FROM tbl_employees ORDER BY FullName")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Delete Employee</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; }
    .form-section { max-width: 500px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
  </style>
</head>
<body>
  <div class="form-section">
    <h2 class="mb-4 text-danger">Delete Employee</h2>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" onsubmit="return confirm('Are you sure you want to delete this employee?');">
      <label class="form-label">Select Employee</label>
      <select name="delete_id" class="form-select" required>
        <option value="">-- Choose --</option>
        <?php foreach($employees as $e): ?>
          <option value="<?= $e['EmployeeID'] ?>"><?= htmlspecialchars($e['FullName']) ?> (<?= $e['EmployeeID'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-danger w-100 mt-3">Delete Employee</button>
    </form>
  </div>
</body>
</html>