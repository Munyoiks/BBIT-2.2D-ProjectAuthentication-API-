<?php
session_start();

if (!isset($_SESSION['pending_email'])) {
    header("Location: register.php");
    exit();
}

$email = $_SESSION['pending_email'];
$code = $_SESSION['verification_code'] ?? null;

// Handle "Resend" request
if (isset($_GET['resend'])) {
    $code = rand(100000, 999999);
    $_SESSION['verification_code'] = $code;
    $resending = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Email | Mojo Tenant System</title>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  <script src="emailConfig.js"></script>

  <style>
    body {
      font-family: "Segoe UI", Arial, sans-serif;
      background: linear-gradient(135deg, #007bff, #00d4ff);
      height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .verify-container {
      background: white;
      padding: 35px 30px;
      border-radius: 14px;
      width: 90%;
      max-width: 400px;
      text-align: center;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
      animation: fadeIn 0.8s ease;
    }

    h2 {
      color: #007bff;
      margin-bottom: 10px;
      font-size: 1.6rem;
    }

    p {
      color: #555;
      font-size: 15px;
      margin-bottom: 20px;
    }

    input {
      width: 100%;
      padding: 12px;
      font-size: 15px;
      border: 1px solid #ccc;
      border-radius: 8px;
      margin-bottom: 15px;
      transition: border 0.2s;
    }

    input:focus {
      border-color: #007bff;
      outline: none;
    }

    button {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      cursor: pointer;
      transition: 0.3s;
    }

    button[type="submit"] {
      background: #007bff;
      color: white;
    }

    button[type="submit"]:hover {
      background: #0056b3;
    }

    #resend-btn {
      background: #f5f5f5;
      color: #007bff;
      margin-top: 10px;
      border: 1px solid #007bff;
    }

    #resend-btn:hover {
      background: #007bff;
      color: white;
    }

    .note {
      font-size: 13px;
      color: #777;
      margin-top: 10px;
    }

    .error {
      color: red;
      font-weight: bold;
      margin-top: 10px;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>
  <div class="verify-container">
    <h2>Email Verification</h2>
    <p>A 6-digit code has been sent to <strong><?php echo htmlspecialchars($email); ?></strong>.</p>

    <?php
    if (isset($_SESSION['error'])) {
        echo "<p class='error'>{$_SESSION['error']}</p>";
        unset($_SESSION['error']);
    }
    ?>

    <form method="POST" action="verify_code.php">
        <input type="text" name="code" placeholder="Enter 6-digit code" required maxlength="6">
        <button type="submit">Verify</button>
    </form>

    <button id="resend-btn" onclick="resendCode()">Resend Code</button>
    <p class="note">Didn’t receive it? Check your spam folder or click “Resend Code”.</p>
  </div>

  <script>
    const resending = <?php echo isset($resending) ? 'true' : 'false'; ?>;
    const email = "<?php echo $email; ?>";
    const code = "<?php echo $code; ?>";

    // Auto-send if "resend" was clicked
    if (resending) {
      sendVerificationEmail(email, code)
        .then(() => alert("A new verification code has been sent to " + email))
        .catch(() => alert("Failed to resend verification email. Please try again."));
    }

    // Button action
    function resendCode() {
      window.location.href = "verify.php?resend=1";
    }
  </script>
</body>
</html>
