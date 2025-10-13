<?php
session_start();

// Ensure reset info exists
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_token'])) {
    die("Reset session expired. Please go back and try again. <a href='reset.php'>Forgot Password</a>");
}

$email = $_SESSION['reset_email'];
$token = $_SESSION['reset_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sending Reset Email | Mojo Tenant System</title>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  <script src="emailConfig.js"></script>
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
    .sending-container {
      background: white;
      padding: 35px 40px;
      border-radius: 12px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
      text-align: center;
      animation: fadeIn 0.7s ease;
      max-width: 400px;
    }
    h2 {
      color: #007bff;
      margin-bottom: 10px;
    }
    p {
      color: #333;
      font-size: 15px;
      margin-bottom: 15px;
    }
    .loader {
      border: 4px solid #f3f3f3;
      border-top: 4px solid #007bff;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 20px auto;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <div class="sending-container">
    <h2>Sending Reset Link...</h2>
    <p>Please wait while we send a password reset link to:</p>
    <p><strong><?php echo htmlspecialchars($email); ?></strong></p>
    <div class="loader"></div>
    <p>This may take a few seconds.</p>
  </div>

  <script>
    const email = "<?php echo $email; ?>";
    const token = "<?php echo $token; ?>";

    // Trigger reset email sending
    triggerPasswordReset(email, token);

    // Redirect after sending
    setTimeout(() => {
      alert("A password reset link has been sent to " + email + ". Please check your inbox or spam folder.");
      window.location.href = "login.php";
    }, 4000);
  </script>
</body>
</html>
