<?php
session_start();
require_once "../auth/db_config.php"; 

// Check if token is present
if (!isset($_GET['token'])) {
    $_SESSION['error'] = "Invalid or missing token.";
    header("Location: reset.php");
    exit();
}

$token = $_GET['token'];

//  Validate token and expiry
$stmt = $conn->prepare("SELECT email, token_expiry FROM users WHERE reset_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Invalid or expired token.";
    header("Location: reset.php");
    exit();
}

$user = $result->fetch_assoc();
$email = $user['email'];
$expiry = strtotime($user['token_expiry']);

if (time() > $expiry) {
    $_SESSION['error'] = "Your password reset link has expired. Please request a new one.";
    header("Location: reset.php");
    exit();
}

//  Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Hash new password
        $hashed_pass = password_hash($new_pass, PASSWORD_BCRYPT);

        // Update password & clear token
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE email = ?");
        $stmt->bind_param("ss", $hashed_pass, $email);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Password reset successfully!";
            header("Location: reset_success.php");
            exit();
        } else {
            $error = "Failed to reset password. Try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Set New Password | Monrine Tenant System</title>
<style>
  body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: linear-gradient(135deg, #00b4d8, #0077b6);
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .container {
    background: white;
    padding: 35px;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    animation: fadeIn 0.8s ease;
  }
  h2 {
    color: #0077b6;
    margin-bottom: 20px;
  }
  input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 15px;
  }
  button {
    background: #0077b6;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 8px;
    cursor: pointer;
    width: 100%;
    font-size: 16px;
  }
  button:hover {
    background: #005f8a;
  }
  .error {
    color: red;
    margin-bottom: 15px;
  }
  @keyframes fadeIn {
    from {opacity: 0; transform: translateY(10px);}
    to {opacity: 1; transform: translateY(0);}
  }
</style>
</head>
<body>

<div class="container">
  <h2>Set a New Password</h2>
  
  <?php if (isset($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  
  <form method="POST">
    <input type="password" name="password" placeholder="Enter new password" required>
    <input type="password" name="confirm_password" placeholder="Confirm new password" required>
    <button type="submit">Update Password</button>
  </form>
</div>

</body>
</html>
