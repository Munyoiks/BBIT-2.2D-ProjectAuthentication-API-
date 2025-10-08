<?php
session_start();

// Ensure verification session data exists
if (!isset($_SESSION['pending_email']) || !isset($_SESSION['verification_code'])) {
    die("Session expired. Please register again. <a href='register.php'>Register</a>");
}

$email = $_SESSION['pending_email'];
$code = $_SESSION['verification_code'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['code']);

    if ($entered == $code) {
        // Verified successfully — update DB
        require_once "db_config.php";
        $stmt = $conn->prepare("UPDATE users SET verified = 1 WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();

        // Clear verification session
        unset($_SESSION['verification_code']);
        unset($_SESSION['pending_email']);

        // Redirect to dashboard
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "❌ Incorrect code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Email Verification | Mojo Tenant System</title>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  <script>
    (function(){
      emailjs.init({ publicKey: "RFl2-4eHenzarWon4" }); // Your EmailJS Public Key
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
        alert("We couldnt send the code. Try refreshing the page.");
      });
    });
  </script>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #eef2f3;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }
    .verify-container {
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      width: 90%;
      max-width: 400px;
      text-align: center;
    }
    h2 {
      color: #007bff;
      margin-bottom: 10px;
    }
    input {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border: 1px solid #ccc;
      border-radius: 8px;
    }
    button {
      width: 100%;
      padding: 12px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 16px;
    }
    button:hover {
      background: #0056b3;
    }
    .error {
      color: red;
      margin-top: 10px;
      font-weight: bold;
    }
    .note {
      font-size: 14px;
      color: #555;
    }
  </style>
</head>
<body>
  <div class="verify-container">
    <h2>Email Verification</h2>
    <p>A 6-digit code has been sent to <strong><?php echo htmlspecialchars($email); ?></strong></p>

    <?php if (isset($error)): ?>
      <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST">
      <input type="text" name="code" placeholder="Enter 6-digit code" maxlength="6" required>
      <button type="submit">Verify</button>
    </form>

    <p class="note">Didnt get the code? Refresh this page to resend it.</p>
  </div>
</body>
</html>
