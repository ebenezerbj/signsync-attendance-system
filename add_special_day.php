<?php
include 'db.php';

// Fetch employees
$employees = $conn->query("SELECT EmployeeID, FullName FROM tbl_employees ORDER BY FullName")->fetchAll(PDO::FETCH_ASSOC);

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeID = trim($_POST['EmployeeID']);
    $date = trim($_POST['SpecialDate']);
    $type = trim($_POST['Type']);

    if (!$employeeID || !$date || !$type) {
        $error = "All fields are required.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO tbl_special_days (EmployeeID, SpecialDate, Type) VALUES (?, ?, ?)");
            $stmt->execute([$employeeID, $date, $type]);
            $success = "Special day set!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Add Special Day</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container">
    <h2>Assign Special Day</h2>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label>Employee</label>
        <select name="EmployeeID" class="form-select" required>
          <option value="">-- Select Employee --</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?= $e['EmployeeID'] ?>"><?= htmlspecialchars($e['FullName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label>Date</label>
        <input type="date" name="SpecialDate" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Type</label>
        <select name="Type" class="form-select" required>
          <option value="DayOff">Day Off</option>
          <option value="WorkDay">Force Work</option>
        </select>
      </div>
      <button class="btn btn-primary">Add Special Day</button>
    </form>
  </div>
</body>
</html>
