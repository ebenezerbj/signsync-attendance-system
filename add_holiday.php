<?php
include 'db.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['Name']);
    $date = trim($_POST['HolidayDate']);

    if (!$name || !$date) {
        $error = "All fields are required.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO tbl_holidays (Name, HolidayDate) VALUES (?, ?)");
            $stmt->execute([$name, $date]);
            $success = "Holiday added!";
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
  <title>Add Holiday</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container">
    <h2>Add Public Holiday</h2>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label>Holiday Name</label>
        <input name="Name" class="form-control" required>
      </div>
      <div class="mb-3">
        <label>Date</label>
        <input type="date" name="HolidayDate" class="form-control" required>
      </div>
      <button class="btn btn-primary">Add Holiday</button>
    </form>
  </div>
</body>
</html>
