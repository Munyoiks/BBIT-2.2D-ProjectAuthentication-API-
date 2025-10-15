<?php
require_once "../db_connect.php"; // Adjust if your DB connection file is elsewhere
session_start();

// Step 1: Check for token in URL
if (!isset($_GET['token'])) {
    die("Invalid or missing reset token.");
}

$token = $_GET['token'];

// Step 2: Verify token validity
$stmt = $conn->prepare("SELECT email, token_expiry FROM users WHERE reset_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid or expired reset link.");
}

$user = $result->fetch_assoc();

//  Check expiry time
if (strtotime($user['token_expiry']) < time()) {
    die("This reset link has expired. Please request a new one.");
}

$email = $user['email'];

// Step 3: If form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        //  Hash password
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update user record
        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE email = ?");
        $update->bind_param("ss", $hashed, $email);

        if ($update->execute()) {
            // Success → Redirect to confirmation page
            header("Location: reset_success.php");
            exit();
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Your Password</title>
  <style>
    body {
      font-family: "Segoe UI", sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: linear-gradient(135deg, #00c6ff, #007bff);
      color: #fff;
    }
    form {
      background: white;
      color: #333;
      padding: 30px;
      border-radius: 15px;
      width: 300px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    h2 { text-align: center; margin-bottom: 20px; }
    input {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      border: 1px solid #ccc;
      border-radius: 8px;
    }
    button {
      width: 100%;
      padding: 10px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
    }
    button:hover {
      background: #0056b3;
    }
    .error {
      color: red;
      text-align: center;
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <form method="POST">
    <h2>Set New Password</h2>
    <p style="font-size: 14px; color: #555;">For: <?= htmlspecialchars($email) ?></p>
    <input type="password" name="password" placeholder="New password" required>
    <input type="password" name="confirm_password" placeholder="Confirm password" required>
    <button type="submit">Reset Password</button>
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
  </form>
</body>
</html>
