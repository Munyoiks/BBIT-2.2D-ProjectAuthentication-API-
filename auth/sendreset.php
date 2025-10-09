<?php
session_start();
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_token'])) {
    header("Location: reset.php");
    exit();
}

$email = $_SESSION['reset_email'];
$token = $_SESSION['reset_token'];

// Construct reset link
$resetLink = "http://localhost/BBIT-2.2D-ProjectAuthentication-API-/newpassword.php?token=$token&email=$email";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sending Password Reset Email...</title>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  <script src="emailConfig.js"></script>
  <style>
    body { font-family: Arial, sans-serif; background: #f1f3f6; display: flex; align-items: center; justify-content: center; height: 100vh; }
    .box { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 0 10px rgba(0,0,0,0.1); text-align: center; width: 90%; max-width: 400px; }
    h2 { color: #007bff; }
    p { color: #333; }
  </style>
</head>
<body>
  <div class="box">
    <h2>Almost there!</h2>
    <p>We’re sending a password reset link to <strong><?= htmlspecialchars($email) ?></strong>.</p>
    <p>Please check your inbox shortly.</p>
  </div>

  <script>
    sendPasswordReset("<?= $email ?>", "<?= $resetLink ?>")
      .then(() => console.log("✅ Password reset email sent"))
      .catch(err => console.error("❌ EmailJS failed:", err));
  </script>
</body>
</html>
