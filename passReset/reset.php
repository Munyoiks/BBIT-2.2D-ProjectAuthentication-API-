<?php
session_start();
require_once "../auth/db_config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header("Location: reset.php");
        exit();
    }

    // Check if the email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $_SESSION['error'] = "No account found with that email.";
        header("Location: reset.php");
        exit();
    }

    // Generate reset token & expiry (15 minutes)
    $token = bin2hex(random_bytes(32));
    $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

    // Save to DB
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
    $stmt->bind_param("sss", $token, $expiry, $email);
    $stmt->execute();
    $stmt->close();

    // Build link
    $reset_link = "http://localhost/BBIT-2.2D-ProjectAuthentication-API-/passReset/newpassword.php?token=" . $token;


    // Store temporarily for sendreset.php
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_link'] = $reset_link;

    header("Location: sendreset.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password | Mojo Tenant System</title>
  <style>
    body {
      font-family: "Segoe UI", Arial, sans-serif;
      background: linear-gradient(135deg, #007bff, #00d4ff);
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
    input[type="email"] {
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
    .message {
      color: red;
      font-size: 14px;
      margin-bottom: 15px;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Forgot Password?</h2>
    <p>Enter your registered email to reset your password.</p>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="message"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="email" name="email" placeholder="Enter your email" required>
      <button type="submit">Send Reset Link</button>
    </form>

    <a href="../auth/login.php">⬅ Back to Login</a>
  </div>
</body>
</html>
