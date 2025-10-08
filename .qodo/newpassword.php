<?php
session_start();

// DB connection
$conn = new mysqli("localhost", "mojo_user", "StrongPass123!", "mojo_db");
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $token = $_POST['token'];
    $newPass = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Validate token
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND reset_token=? AND reset_expiry > NOW()");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        die("❌ Invalid or expired reset link.");
    }

    // Update password
    $stmt = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expiry=NULL WHERE email=?");
    $stmt->bind_param("ss", $newPass, $email);
    $stmt->execute();

    echo "✅ Password updated successfully. <a href='login.php'>Login here</a>";
    exit();
}

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Set New Password</title>
  <style>
    body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
    .container { max-width: 400px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 6px; }
    input, button { width: 100%; padding: 10px; margin: 8px 0; }
    button { background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
    button:hover { background: #218838; }
  </style>
</head>
<body>
<div class="container">
    <h2>Reset Your Password</h2>
    <form method="POST">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="password" name="password" placeholder="Enter new password" required>
        <button type="submit">Update Password</button>
    </form>
</div>
</body>
</html>
