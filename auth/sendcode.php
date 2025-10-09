<?php
session_start();

if (!isset($_POST['email'])) {
    header("Location: index.php");
    exit();
}

$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

if (!$email) {
    die("Invalid email address.");
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
    emailjs.debug = true;

    emailjs.send("service_hit0nhj", "template_lyjg5vx", {
        to_email: "<?= $_SESSION['email'] ?>",
        passcode: "<?= $_SESSION['tfa_code'] ?>",
        time: "<?= date('H:i A') ?>",
    })
    .then(() => {
        console.log(" Verification email sent to <?= $_SESSION['email'] ?>");
    })
    .catch((error) => {
        console.error(" EmailJS failed:", error);
        alert("Failed to send verification code. Check console for details.");
    });
  </script>
</body>
</html>
