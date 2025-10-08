<?php
session_start();
if (!isset($_SESSION['pending_reset_emailjs'])) {
    header("Location: reset.php");
    exit();
}

$email = $_SESSION['reset_email'];
$token = $_SESSION['reset_token'];
unset($_SESSION['pending_reset_emailjs']); // prevent resending

$resetLink = "http://localhost/BBIT-2.2D-ProjectAuthentication-API-/newpassword.php?token=$token&email=$email";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Password Reset Email</title>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
</head>
<body>
  <h2>We sent a password reset link to <?= htmlspecialchars($email) ?></h2>
  <p>Please check your inbox.</p>

  <script>
  emailjs.init({ publicKey: "T38uilUqfOVLAnbQE" });

  emailjs.send("service_hit0nhj", "template_hq97rqj", {
      to_email: "<?= $email ?>",
      message: "Click here to reset your password: <?= $resetLink ?>"
  })
  .then(() => {
      console.log(" Rset link sent to <?= $email ?>");
  })
  .catch((error) => {
      console.error(" EmailJS failed:", error);
      alert("Error sending reset email. Please try again later.");
  });
  </script>
</body>
</html>
