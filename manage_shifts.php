<?php
include 'db.php';

// --- Handle delete ---
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $conn->prepare("DELETE FROM tbl_shifts WHERE ShiftID = ?")->execute([$id]);
  header("Location: manage_shifts.php");
  exit;
}

// --- Handle update ---
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = intval($_POST['ShiftID']);
  $name = trim($_POST['Name']);
  $start = trim($_POST['StartTime']);
  $end = trim($_POST['EndTime']);

  if (!$name || !$start || !$end) {
    $error = "All fields required.";
  } else {
    $stmt = $conn->prepare("UPDATE tbl_shifts SET Name=?, StartTime=?, EndTime=? WHERE ShiftID=?");
    $stmt->execute([$name, $start, $end, $id]);
    $success = "Shift updated!";
  }
}

// --- Fetch all shifts ---
$shifts = $conn->query("SELECT * FROM tbl_shifts")->fetchAll(PDO::FETCH_ASSOC);

// --- If editing ---
$editShift = null;
if (isset($_GET['edit'])) {
  $id = intval($_GET['edit']);
  $stmt = $conn->prepare("SELECT * FROM tbl_shifts WHERE ShiftID = ?");
  $stmt->execute([$id]);
  $editShift = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Manage Shifts</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container">
    <h2>Manage Shifts</h2>
    <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if ($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <?php if ($editShift): ?>
    <form method="post" class="mb-4">
      <input type="hidden" name="ShiftID" value="<?= $editShift['ShiftID'] ?>">
      <div class="mb-3">
        <label>Shift Name</label>
        <input name="Name" class="form-control" value="<?= htmlspecialchars($editShift['Name']) ?>" required>
      </div>
      <div class="mb-3">
        <label>Start Time</label>
        <input type="time" name="StartTime" class="form-control" value="<?= $editShift['StartTime'] ?>" required>
      </div>
      <div class="mb-3">
        <label>End Time</label>
        <input type="time" name="EndTime" class="form-control" value="<?= $editShift['EndTime'] ?>" required>
      </div>
      <button class="btn btn-primary">Update Shift</button>
      <a href="manage_shifts.php" class="btn btn-secondary">Cancel</a>
    </form>
    <?php endif; ?>

    <table class="table table-bordered">
      <thead>
        <tr><th>Shift</th><th>Start</th><th>End</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($shifts as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['Name']) ?></td>
            <td><?= $s['StartTime'] ?></td>
            <td><?= $s['EndTime'] ?></td>
            <td>
              <a href="?edit=<?= $s['ShiftID'] ?>" class="btn btn-sm btn-info">Edit</a>
              <a href="?delete=<?= $s['ShiftID'] ?>" class="btn btn-sm btn-danger"
                onclick="return confirm('Delete this shift?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>