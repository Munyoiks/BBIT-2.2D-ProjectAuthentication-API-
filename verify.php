<?php
session_start();
if (!isset($_SESSION['pending_email']) || !isset($_SESSION['verification_code'])) {
    die("Session expired. Please register again.");
}

$email = $_SESSION['pending_email'];
$code = $_SESSION['verification_code'];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Verify Your Email</title>
  <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
  <script>
    (function(){
      emailjs.init("RFl2-4eHenzarWon4"); // Your EmailJS Public Key
    })();

    window.addEventListener("DOMContentLoaded", () => {
      // Automatically send the code when user lands here
      emailjs.send("service_e594fkz", "template_wzft06q", {
        to_email: "<?php echo $email; ?>",
        verification_code: "<?php echo $code; ?>"
      }).then(() => {
        console.log("✅ Verification code sent to <?php echo $email; ?>");
      }).catch(err => {
        console.error("❌ Failed to send email:", err);
      });
    });
  </script>
</head>
<body>
  <h2>Email Verification</h2>
  <p>A 6-digit code has been sent to <strong><?php echo $email; ?></strong>. Enter it below:</p>

  <form method="POST" action="verify_action.php">
    <input type="text" name="code" placeholder="Enter 6-digit code" required><br><br>
    <button type="submit">Verify</button>
  </form>
</body>
</html>
