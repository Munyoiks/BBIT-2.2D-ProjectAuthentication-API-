<?php
session_start();

// Ensure email was provided
if (!isset($_POST['email'])) {
    header("Location: index.php");
    exit();
}

$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    die("Invalid email address.");
}

// Generate 6-digit OTP
$code = rand(100000, 999999);

// Store OTP and expiry time (5 mins)
$_SESSION['verification_code'] = $code;
$_SESSION['pending_email'] = $email;
$_SESSION['otp_expiry'] = time() + 300; // 5 minutes
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Code | Mojo Tenant System</title>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  <script src="emailConfig.js"></script>
  <style>
    body {
      font-family: "Segoe UI", Arial, sans-serif;
      background: #f8f9fa;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }
    .container {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      width: 90%;
      max-width: 400px;
      text-align: center;
    }
    input {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border-radius: 5px;
      border: 1px solid #ccc;
    }
    button {
      width: 100%;
      padding: 10px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      background: #007bff;
      color: white;
      font-size: 16px;
      transition: 0.3s;
    }
    button:hover {
      background: #0056b3;
    }
    #resend-btn {
      background: #f5f5f5;
      color: #007bff;
      border: 1px solid #007bff;
      margin-top: 10px;
    }
    #resend-btn:hover {
      background: #007bff;
      color: white;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Verify Your Email</h2>
    <p>We’ve sent a 6-digit code to <strong><?= htmlspecialchars($email) ?></strong>.</p>

    <form action="verify_code.php" method="POST">
      <input type="text" name="code" placeholder="Enter 6-digit code" required maxlength="6">
      <button type="submit">Verify</button>
    </form>

    <button id="resend-btn">Resend Code</button>
    <p style="font-size:13px; color:#555;">Didn’t receive it? Check spam or click “Resend Code”.</p>
  </div>

  <script>
    const email = "<?= $_SESSION['pending_email'] ?>";
    const code = "<?= $_SESSION['verification_code'] ?>";

    // Prevent duplicate sends on refresh
    if (!sessionStorage.getItem("otpSent")) {
      sendVerificationEmail(email, code)
        .then(() => {
          console.log("✅ OTP sent to:", email);
          sessionStorage.setItem("otpSent", "true");
        })
        .catch((err) => {
          console.error("❌ Email send failed:", err);
          alert("Failed to send verification code. Please try again.");
        });
    }

    // Resend button handler
    document.getElementById("resend-btn").addEventListener("click", () => {
      fetch("resend_otp.php")
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            sendVerificationEmail(email, data.new_code)
              .then(() => alert("✅ New code sent to " + email))
              .catch(() => alert("❌ Failed to send new OTP."));
          } else {
            alert("Session expired. Please register again.");
            window.location.href = "register.php";
          }
        })
        .catch(() => alert("Error contacting server."));
    });
  </script>
</body>
</html>
