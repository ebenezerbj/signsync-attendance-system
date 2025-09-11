<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Join with tbl_roles to get the RoleName from RoleID
    $stmt = $conn->prepare("
        SELECT e.EmployeeID, e.FullName, e.Username, e.Password, r.RoleName
        FROM tbl_employees e
        JOIN tbl_roles r ON e.RoleID = r.RoleID
        WHERE e.Username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user['Password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['EmployeeID'];
            $_SESSION['user_name'] = $user['FullName'];
            $_SESSION['user_role'] = $user['RoleName']; // Store role name in session

            // Redirect based on role
            $role = strtolower($user['RoleName']);
            if ($role === 'administrator') {
                header('Location: admin_dashboard.php');
            } elseif ($role === 'manager') {
                header('Location: manager_portal.php');
            } else {
                header('Location: employee_portal.php');
            }
            exit;
        } else {
            $error = 'Incorrect password.';
        }
    } else {
        $error = 'Username not found.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" 
    rel="stylesheet">
  <style>
    body {
      background: #0d6efd;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      color: #fff;
    }
    .login-box {
      background: #fff;
      color: #333;
      padding: 2rem;
      border-radius: 8px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 0 15px rgba(0,0,0,0.2);
    }
    .login-box h2 {
      margin-bottom: 1.5rem;
      text-align: center;
    }
    .form-control:focus {
      box-shadow: none;
      border-color: #0d6efd;
    }
    .btn-primary {
      background: #0d6efd;
      border: none;
    }
    .btn-primary:hover {
      background: #0b5ed7;
    }
  </style>
</head>

<body>

  <div class="login-box">
    <h2>Admin Login</h2>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input 
          type="text" 
          name="username" 
          class="form-control" 
          placeholder="Enter username" 
          required>
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <input 
          type="password" 
          name="password" 
          class="form-control" 
          placeholder="Enter password" 
          required>
      </div>

      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <div class="text-center mt-3">
      <a href="forgot_password.php" class="text-decoration-none">Forgot Password?</a>
    </div>
  </div>

</body>
</html>

