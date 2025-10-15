<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Password Reset Successful</title>
<style>
  body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: linear-gradient(135deg, #007bff, #00d4ff);
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .container {
    background: #fff;
    padding: 30px 40px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
  }
  h2 { color: #007bff; margin-bottom: 10px; }
  p { color: #333; }
  a {
    display: inline-block;
    margin-top: 20px;
    color: #007bff;
    text-decoration: none;
    font-weight: bold;
  }
  a:hover { text-decoration: underline; }
</style>
</head>
<body>
  <div class="container">
    <h2> Password Reset Successful</h2>
    <p>You can now log in using your new password.</p>
    <a href="../auth/login.php">Go to Login</a>
  </div>
</body>
</html>
