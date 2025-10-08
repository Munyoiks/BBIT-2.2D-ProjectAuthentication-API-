<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <style>
    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
    .container { max-width: 400px; margin: auto; padding: 20px; border: 1px solid #ddd; border-radius: 6px; }
    input { width: 100%; padding: 10px; margin: 10px 0; }
    button { width: 100%; padding: 12px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
    button:hover { background: #0056b3; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Forgot Password?</h2>
    <p>Enter your email and we'll send a reset link</p>
    <form action="sendreset.php" method="POST">
      <input type="email" name="email" placeholder="Enter your email" required>
      <button type="submit">Send Reset Link</button>
    </form>
  </div>
</body>
</html>
