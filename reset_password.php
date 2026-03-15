<?php
include 'db.php';
date_default_timezone_set('UTC');
header('Content-Type: text/html; charset=utf-8');

$message = '';
$showForm = false;
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];
    $newPassword = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);

    if ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $showForm = true;
    } elseif (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters.';
        $showForm = true;
    } else {
        // Check if token is valid and not expired
        $stmt = $conn->prepare("SELECT * FROM tbl_employees WHERE ResetToken = ? AND ResetTokenExpires >= ?");
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("UPDATE tbl_employees SET Password = ?, ResetToken = NULL, ResetTokenExpires = NULL WHERE EmployeeID = ?");
            $stmt->execute([$hashedPassword, $user['EmployeeID']]);

            $message = 'Your password has been reset. You can now <a href="login.php">login</a>.';
        } else {
            $message = 'Invalid or expired token.';
        }
    }
} elseif (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    // Verify token exists before showing form
    $stmt = $conn->prepare("SELECT EmployeeID FROM tbl_employees WHERE ResetToken = ? AND ResetTokenExpires >= ?");
    $stmt->execute([$token, date('Y-m-d H:i:s')]);
    if ($stmt->fetch()) {
        $showForm = true;
    } else {
        $message = 'Invalid or expired token.';
    }
} else {
    $message = 'Invalid or missing token.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Reset Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height:100vh;">
  <div class="p-4 bg-white rounded shadow-sm" style="min-width:320px;max-width:400px;">
    <h2 class="mb-4">Reset Password</h2>
    <?php if ($message): ?>
      <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($showForm): ?>
      <form method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
      </form>
    <?php endif; ?>

    <div class="text-center mt-3">
      <a href="login.php" class="text-decoration-none">Back to Login</a>
    </div>
  </div>
</body>
</html>
