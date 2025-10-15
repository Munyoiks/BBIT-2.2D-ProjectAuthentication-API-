<?php
session_start();

//  Ensure required session data is available
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_link'])) {
    header("Location: reset.php");
    exit();
}

$email = $_SESSION['reset_email'];
$reset_link = $_SESSION['reset_link'];

// Optional: If you store user's name in DB, fetch it for a personalized email
$full_name = $_SESSION['full_name'] ?? "User";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sending Password Reset Link...</title>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  
  <script src="../auth/emailConfig.js"></script>


  <style>
    body {
      font-family: "Segoe UI", sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100vh;
      background: linear-gradient(135deg, #007bff, #00c6ff);
      color: #fff;
      text-align: center;
    }
    h2 { margin-bottom: 15px; }
    p { font-size: 16px; }
    .loader {
      margin-top: 25px;
      width: 50px;
      height: 50px;
      border: 6px solid #ffffff50;
      border-top-color: white;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .error { color: #ffcccc; margin-top: 20px; }
  </style>
</head>
<body>
  <h2>Sending Password Reset Link...</h2>
  <p>Please wait while we send an email to <strong><?= htmlspecialchars($email) ?></strong></p>
  <div class="loader"></div>

  <script>
    
    const email = "<?= addslashes($email) ?>";
    const full_name = "<?= addslashes($full_name) ?>";
    const reset_link = "<?= addslashes($reset_link) ?>";

    // Send Password Reset Email using EmailJS
    sendPasswordResetEmail(email, full_name, reset_link)
      .then(() => {
        console.log(" Password reset link sent to:", email);
        // Redirect to success page
        setTimeout(() => {
          window.location.href = "reset_success.php";
        }, 2000);
      })
      .catch((err) => {
        console.error(" Failed to send email:", err);
        document.body.innerHTML += `
          <p class="error"> Failed to send email. Please try again later.</p>
          <a href='reset.php' style='color:white;text-decoration:underline;'>Back</a>
        `;
      });
  </script>
</body>
</html>
