<?php
include 'db.php';
date_default_timezone_set('UTC');
header('Content-Type: text/html; charset=utf-8');

// ✅ Corrected SMS function — same as your clock in/out style
function sendSMSOnlineGH($message, $destinationNumbers) {
    $apiKey = 'aefc1848ebc7baaa90e71bfb6072287cc2cc197882e73631a1bdc27135a51abb';

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: key ' . $apiKey
    ];

    $messageData = [
        'text' => $message,
        'type' => 0,
        'sender' => 'AKCBANK LTD',
        'destinations' => $destinationNumbers
    ];

    $ch = curl_init('https://api.smsonlinegh.com/v5/message/sms/send');

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($messageData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $caCertPath = __DIR__ . "/cacert.pem";
    if (file_exists($caCertPath)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caCertPath);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        error_log("cURL error sending SMS: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    error_log("SMS API Raw Response: HTTP $httpCode, Body: $response");

    $responseData = json_decode($response, true);

    if ($httpCode == 200 && isset($responseData['handshake']['id']) && $responseData['handshake']['id'] === 0) {
        return true;
    }

    return false;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrPhone = trim($_POST['username']);

    // ✅ check EmployeeID OR PhoneNumber only
    $stmt = $conn->prepare("SELECT * FROM tbl_employees WHERE Username = ? OR PhoneNumber = ?");
    $stmt->execute([$usernameOrPhone, $usernameOrPhone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['PhoneNumber'])) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $conn->prepare("UPDATE tbl_employees SET ResetToken = ?, ResetTokenExpires = ? WHERE EmployeeID = ?");
        $stmt->execute([$token, $expires, $user['EmployeeID']]);

        $phone = $user['PhoneNumber'];
        if (preg_match('/^0\d{9}$/', $phone)) {
            $phone = '233' . substr($phone, 1);
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $resetLink = "{$protocol}://{$_SERVER['HTTP_HOST']}/reset_password.php?token=$token";
        $smsText = "Hi {$user['FullName']}, reset your password here: $resetLink (valid for 1 hour)";

        if (sendSMSOnlineGH($smsText, [$phone])) {
            $message = "A password reset link has been sent via SMS.";
        } else {
            $message = "Error sending SMS. Please try again or contact admin.";
        }
    } else {
        $message = "No user found with that username/phone or no phone on file.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Forgot Password</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height:100vh;">
  <div class="p-4 bg-white rounded shadow-sm" style="min-width:320px;max-width:400px;">
    <h2 class="mb-4">Forgot Password</h2>
    <?php if ($message): ?>
      <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Username or Phone Number</label>
        <input type="text" name="username" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
    </form>
    <div class="text-center mt-3">
      <a href="login.php" class="text-decoration-none">Back to Login</a>
    </div>
  </div>
</body>
</html>
