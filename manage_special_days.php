<?php
include 'db.php';

// Fetch employees for dropdown
$employees = $conn->query("SELECT EmployeeID, FullName FROM tbl_employees ORDER BY FullName")->fetchAll(PDO::FETCH_ASSOC);

// --- Handle delete ---
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $conn->prepare("DELETE FROM tbl_special_days WHERE SpecialDayID = ?")->execute([$id]);
  header("Location: manage_special_days.php");
  exit;
}

// --- Handle update ---
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = intval($_POST['SpecialDayID']);
  $employeeID = trim($_POST['EmployeeID']);
  $date = trim($_POST['SpecialDate']);
  $type = trim($_POST['Type']);

  if (!$employeeID || !$date || !$type) {
    $error = "All fields required.";
  } else {
    $stmt = $conn->prepare("UPDATE tbl_special_days SET EmployeeID=?, SpecialDate=?, Type=? WHERE SpecialDayID=?");
    $stmt->execute([$employeeID, $date, $type, $id]);
    $success = "Special day updated!";
  }
}

// --- Fetch all special days ---
$specials = $conn->query("SELECT sd.*, e.FullName FROM tbl_special_days sd LEFT JOIN tbl_employees e ON sd.EmployeeID = e.EmployeeID ORDER BY sd.SpecialDate DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- If editing ---
$editSpecial = null;
if (isset($_GET['edit'])) {
  $id = intval($_GET['edit']);
  $stmt = $conn->prepare("SELECT * FROM tbl_special_days WHERE SpecialDayID = ?");
  $stmt->execute([$id]);
  $editSpecial = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Manage Special Days</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container">
    <h2>Manage Special Days</h2>
    <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if ($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <?php if ($editSpecial): ?>
    <form method="post" class="mb-4">
      <input type="hidden" name="SpecialDayID" value="<?= $editSpecial['SpecialDayID'] ?>">
      <div class="mb-3">
        <label>Employee</label>
        <select name="EmployeeID" class="form-select" required>
          <option value="">-- Select Employee --</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= $e['EmployeeID'] ?>" <?= $editSpecial['EmployeeID'] == $e['EmployeeID'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($e['FullName']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label>Date</label>
        <input type="date" name="SpecialDate" class="form-control" value="<?= $editSpecial['SpecialDate'] ?>" required>
      </div>
      <div class="mb-3">
        <label>Type</label>
        <select name="Type" class="form-select" required>
          <option value="DayOff" <?= $editSpecial['Type'] == 'DayOff' ? 'selected' : '' ?>>Day Off</option>
          <option value="WorkDay" <?= $editSpecial['Type'] == 'WorkDay' ? 'selected' : '' ?>>Force Work</option>
        </select>
      </div>
      <button class="btn btn-primary">Update Special Day</button>
      <a href="manage_special_days.php" class="btn btn-secondary">Cancel</a>
    </form>
    <?php endif; ?>

    <table class="table table-bordered">
      <thead>
        <tr><th>Employee</th><th>Date</th><th>Type</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($specials as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['FullName']) ?></td>
            <td><?= $s['SpecialDate'] ?></td>
            <td><?= $s['Type'] ?></td>
            <td>
              <a href="?edit=<?= $s['SpecialDayID'] ?>" class="btn btn-sm btn-info">Edit</a>
              <a href="?delete=<?= $s['SpecialDayID'] ?>" class="btn btn-sm btn-danger"
                onclick="return confirm('Delete this special day?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>