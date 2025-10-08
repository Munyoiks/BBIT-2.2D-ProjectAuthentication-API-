<?php
session_start();

// DB connection
$conn = new mysqli("localhost", "mojo_user", "StrongPass123!", "mojo_db");
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if (!$email) {
        die(" Invalid email address.");
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        die(" No account found with that email.");
    }

    // Generate reset token
    $token = bin2hex(random_bytes(16));
    $expiry = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiry

    // Store token in DB
    $stmt = $conn->prepare("UPDATE users SET reset_token=?, reset_expiry=? WHERE email=?");
    $stmt->bind_param("sss", $token, $expiry, $email);
    $stmt->execute();

    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_token'] = $token;
    $_SESSION['pending_reset_emailjs'] = true;

    // Redirect to confirmation page
    // Redirect to confirmation page
header("Location: sendreset.php");
exit();

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
        .container { max-width: 400px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 6px; }
        input, button { width: 100%; padding: 10px; margin: 8px 0; }
        button { background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <h2>Forgot Password?</h2>
    <p>Enter your email to reset your password</p>
    <form method="POST">
        <input type="email" name="email" placeholder="Enter your email" required>
        <button type="submit">Send Reset Link</button>
    </form>
</div>
</body>
</html>
