<?php
include 'db.php';

$success = $error = '';
$days = $conn->query("SELECT DayName FROM tbl_working_days ORDER BY DayID")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Name']);
    $start = trim($_POST['StartTime']);
    $end = trim($_POST['EndTime']);
    $workingDaysArr = $_POST['WorkingDays'] ?? [];
    $workingDays = implode(',', $workingDaysArr);

    if (!$name || !$start || !$end || empty($workingDaysArr)) {
        $error = "All fields are required and at least one day must be selected.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO tbl_shifts (Name, StartTime, EndTime, WorkingDays) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $start, $end, $workingDays]);
            $success = "✅ Shift added successfully!";
        } catch (PDOException $e) {
            $error = "❌ Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Add Shift</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .day-checkbox { margin-right: 1rem; }
  </style>
</head>
<body class="p-4">
  <div class="container">
    <h2 class="mb-4">Add New Shift</h2>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="mb-3">
        <label class="form-label">Shift Name</label>
        <input name="Name" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Start Time</label>
        <input type="time" name="StartTime" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">End Time</label>
        <input type="time" name="EndTime" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Working Days</label>
        <div class="border rounded p-3">
          <?php foreach ($days as $day): ?>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="WorkingDays[]" value="<?= htmlspecialchars($day) ?>" id="day_<?= htmlspecialchars($day) ?>">
              <label class="form-check-label" for="day_<?= htmlspecialchars($day) ?>"><?= htmlspecialchars($day) ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <small class="text-muted">Select one or more days for this shift.</small>
      </div>

      <button type="submit" class="btn btn-primary">Add Shift</button>
    </form>
  </div>
</body>
</html>
