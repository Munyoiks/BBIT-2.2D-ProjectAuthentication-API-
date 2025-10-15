<?php
session_start();
require_once "db_config.php"; 

if (isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    if (!$email) {
        $error = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email, password, is_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                if ($user['is_verified'] == 1) {
                    // Verified user → go to dashboard
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];

                    header("Location: ../dashboard/dashboard.php");
                    exit();
                } else {
                    $error = "Your account is not verified. Please check your email for the verification code.";
                }
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "No account found with that email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mojo Tenant System | Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; }
        .container { max-width: 420px; margin: 70px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        h2 { text-align: center; color: #333; margin-bottom: 8px; }
        p.lead { text-align:center; color:#666; margin-top:0; margin-bottom:18px; }
        form { margin-top: 8px; }
        input { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .error { color: red; margin-bottom: 12px; text-align: center; }
        .link { text-align: center; margin-top: 14px; font-size: 14px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .small { font-size: 13px; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <h2>Login to Mojo Tenant System</h2>
    <p class="lead">Welcome back — enter your credentials below.</p>

    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>

    <div class="link">
        <!-- Recommended relative path from auth/ to passReset/ -->
        <a id="forgot-link" href="../passReset/reset.php">Forgot Password?</a><br>


        <a href="register.php">Don’t have an account? Register</a>
    </div>
</div>

<script>

  (function(){
    const el = document.getElementById('forgot-link');
    if (el) {
      console.log("Forgot password link href:", el.getAttribute('href'));
    }
  })();
</script>
</body>
</html>
