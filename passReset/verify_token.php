<?php
session_start();
require_once "../auth/db_config.php";

$token = $_GET['token'] ?? '';

if (!$token) {
    die("<h2>Invalid password reset link.</h2>");
}

$stmt = $conn->prepare("SELECT email, token_expiry FROM users WHERE reset_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<h2>Invalid or expired token.</h2>");
}

$user = $result->fetch_assoc();
$email = $user['email'];
$expiry = $user['token_expiry'];

if (strtotime($expiry) < time()) {
    die("<h2>This password reset link has expired.</h2>");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        echo "<p style='color:red;'>Passwords do not match.</p>";
    } elseif (strlen($new_pass) < 6) {
        echo "<p style='color:red;'>Password must be at least 6 characters.</p>";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE email = ?");
        $update->bind_param("ss", $hashed, $email);
        $update->execute();
        echo "<script>alert(' Password reset successful! You can now log in.'); window.location.href='../auth/login.php';</script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password | Monrine Tenant System</title>
  <style>
    body {
      font-family: "Segoe UI", Arial, sans-serif;
      background: linear-gradient(135deg, #00c6ff, #0072ff);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
    }
    .container {
      background: #fff;
      padding: 30px 40px;
      border-radius: 12px;
      text-align: center;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
      width: 90%;
      max-width: 400px;
      animation: fadeIn 0.8s ease;
    }
    h2 {
      color: #007bff;
      margin-bottom: 15px;
    }
    input[type="password"] {
      width: 100%;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 15px;
    }
    button {
      background: #007bff;
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 8px;
      cursor: pointer;
      width: 100%;
      font-size: 16px;
      transition: background 0.3s ease;
    }
    button:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Set a New Password</h2>
    <form method="POST" action="">
      <input type="password" name="password" placeholder="Enter new password" required>
      <input type="password" name="confirm_password" placeholder="Confirm password" required>
      <button type="submit">Update Password</button>
    </form>
  </div>
</body>
</html>
