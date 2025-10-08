<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mojo Tenant System</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, #b3b8beff, #f3fcffff);
      height: 100vh;
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      color: white;
    }

    .container {
      text-align: center;
      background: rgba(255, 255, 255, 0.1);
      padding: 40px;
      border-radius: 15px;
      box-shadow: 0 0 15px rgba(0,0,0,0.2);
      width: 90%;
      max-width: 400px;
    }

    h1 {
      font-size: 28px;
      margin-bottom: 10px;
    }

    p {
      font-size: 16px;
      margin-bottom: 30px;
    }

    button {
      width: 100%;
      padding: 15px;
      border: none;
      border-radius: 8px;
      margin: 10px 0;
      font-size: 16px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .register-btn {
      background-color: #28a745;
      color: white;
    }

    .register-btn:hover {
      background-color: #218838;
    }

    .login-btn {
      background-color: #ffc107;
      color: #333;
    }

    .login-btn:hover {
      background-color: #e0a800;
    }

    footer {
      margin-top: 25px;
      font-size: 14px;
      opacity: 0.8;
    }
  </style>
</head>
<body>

  <div class="container">
    <h1>🏠 Mojo Tenant System</h1>
    <p>Manage tenants, rent, and property records efficiently.</p>

    <form action="auth.php" method="get">
      <button type="submit" name="action" value="register" class="register-btn">Register</button>
    </form>

    <form action="auth.php" method="get">
      <button type="submit" name="action" value="login" class="login-btn">Login</button>
    </form>

    <footer>
      <p>© <?php echo date('Y'); ?> Mojo Electrical Enterprise</p>
    </footer>
  </div>

</body>
</html>
