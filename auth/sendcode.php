<?php
session_start();

if (!isset($_POST['email'])) {
    header("Location: index.php");
    exit();
}

$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

if (!$email) {
    die(" Invalid email address.");
}

// Generate 6-digit code
$code = rand(100000, 999999);
$_SESSION['tfa_code'] = $code;
$_SESSION['tfa_expiry'] = time() + 300; // 5 minutes
$_SESSION['email'] = $email;
$_SESSION['pending_emailjs'] = true;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Code</title>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
</head>
<body>
  <h2>We sent a code to <?= htmlspecialchars($email) ?></h2>
  <form action="verifycode.php" method="POST">
    <input type="text" name="code" placeholder="Enter 6-digit code" required>
    <button type="submit">Verify</button>
  </form>

  <script>
  emailjs.init({ publicKey: "T38uilUqfOVLAnbQE" });

  <?php if (isset($_SESSION['pending_emailjs']) && $_SESSION['pending_emailjs']): ?>
    emailjs.send("service_hit0nhj", "template_lyjg5vx", {
        to_email: "<?= $_SESSION['email'] ?>",
        message: "Your MojoAuth verification code is: <?= $_SESSION['tfa_code'] ?> (expires in 5 minutes)"
    })
    .then(() => {
        console.log(" Code sent to <?= $_SESSION['email'] ?>");
    })
    .catch((error) => {
        console.error(" EmailJS failed:", error);
        alert("Error sending email. Please try again later.");
    });
  <?php unset($_SESSION['pending_emailjs']); endif; ?>
  </script>
</body>
</html>
