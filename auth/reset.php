<?php
session_start();
require_once "db_config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if (!$email) {
        die("Invalid email address.");
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        die("No account found with that email.");
    }

    // Generate reset token & expiry
    $token = bin2hex(random_bytes(16));
    $expiry = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiry

    // Store token in DB
    $stmt = $conn->prepare("UPDATE users SET reset_token=?, reset_expiry=? WHERE email=?");
    $stmt->bind_param("sss", $token, $expiry, $email);
    $stmt->execute();

    // Save details for next page
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_token'] = $token;

    // Redirect to email sending page
    header("Location: sendreset.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | Mojo Tenant System</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; background: #f8f9fa; }
        .container { max-width: 400px; margin: auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.1); }
        input, button { width: 100%; padding: 10px; margin: 8px 0; border-radius: 6px; border: 1px solid #ccc; }
        button { background: #007bff; color: #fff; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h2>Forgot Password?</h2>
    <p>Enter your registered email to reset your password</p>
    <form method="POST">
        <input type="email" name="email" placeholder="Enter your email" required>
        <button type="submit">Send Reset Link</button>
    </form>
</div>
</body>
</html>
