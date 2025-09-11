<?php
include 'db.php';

// --- Handle delete ---
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $conn->prepare("DELETE FROM tbl_holidays WHERE HolidayID = ?")->execute([$id]);
  header("Location: manage_holidays.php");
  exit;
}

// --- Handle update ---
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = intval($_POST['HolidayID']);
  $name = trim($_POST['Name']);
  $date = trim($_POST['HolidayDate']);

  if (!$name || !$date) {
    $error = "All fields required.";
  } else {
    $stmt = $conn->prepare("UPDATE tbl_holidays SET Name=?, HolidayDate=? WHERE HolidayID=?");
    $stmt->execute([$name, $date, $id]);
    $success = "Holiday updated!";
  }
}

// --- Fetch all holidays ---
$holidays = $conn->query("SELECT * FROM tbl_holidays")->fetchAll(PDO::FETCH_ASSOC);

// --- If editing ---
$editHoliday = null;
if (isset($_GET['edit'])) {
  $id = intval($_GET['edit']);
  $stmt = $conn->prepare("SELECT * FROM tbl_holidays WHERE HolidayID = ?");
  $stmt->execute([$id]);
  $editHoliday = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Manage Holidays</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container">
    <h2>Manage Holidays</h2>
    <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
    <?php if ($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <?php if ($editHoliday): ?>
    <form method="post" class="mb-4">
      <input type="hidden" name="HolidayID" value="<?= $editHoliday['HolidayID'] ?>">
      <div class="mb-3">
        <label>Holiday Name</label>
        <input name="Name" class="form-control" value="<?= htmlspecialchars($editHoliday['Name']) ?>" required>
      </div>
      <div class="mb-3">
        <label>Date</label>
        <input type="date" name="HolidayDate" class="form-control" value="<?= $editHoliday['HolidayDate'] ?>" required>
      </div>
      <button class="btn btn-primary">Update Holiday</button>
      <a href="manage_holidays.php" class="btn btn-secondary">Cancel</a>
    </form>
    <?php endif; ?>

    <table class="table table-bordered">
      <thead>
        <tr><th>Name</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($holidays as $h): ?>
          <tr>
            <td><?= htmlspecialchars($h['Name']) ?></td>
            <td><?= $h['HolidayDate'] ?></td>
            <td>
              <a href="?edit=<?= $h['HolidayID'] ?>" class="btn btn-sm btn-info">Edit</a>
              <a href="?delete=<?= $h['HolidayID'] ?>" class="btn btn-sm btn-danger"
                onclick="return confirm('Delete this holiday?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>