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
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }

    .container {
      background: rgba(255, 255, 255, 0.95);
      padding: 50px 40px;
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 450px;
      text-align: center;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .logo {
      font-size: 3rem;
      margin-bottom: 15px;
      display: block;
    }

    h1 {
      color: #333;
      font-size: 32px;
      margin-bottom: 15px;
      font-weight: 600;
    }

    .subtitle {
      color: #666;
      font-size: 16px;
      margin-bottom: 40px;
      line-height: 1.5;
    }

    .btn-group {
      display: flex;
      flex-direction: column;
      gap: 15px;
      margin-bottom: 30px;
    }

    .btn {
      padding: 16px 24px;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: block;
      text-align: center;
    }

    .btn-register {
      background: linear-gradient(135deg, #28a745, #20c997);
      color: white;
      box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    }

    .btn-register:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
    }

    .btn-login {
      background: linear-gradient(135deg, #ffc107, #fd7e14);
      color: white;
      box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
    }

    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
    }

    .features {
      background: rgba(102, 126, 234, 0.1);
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 30px;
    }

    .features h3 {
      color: #333;
      margin-bottom: 15px;
      font-size: 18px;
    }

    .features ul {
      list-style: none;
      text-align: left;
    }

    .features li {
      color: #555;
      margin-bottom: 8px;
      padding-left: 20px;
      position: relative;
    }

    .features li:before {
      content: "✓";
      color: #28a745;
      font-weight: bold;
      position: absolute;
      left: 0;
    }

    footer {
      margin-top: 25px;
      color: #888;
      font-size: 14px;
      border-top: 1px solid rgba(0, 0, 0, 0.1);
      padding-top: 20px;
    }

    .company-name {
      color: #333;
      font-weight: 600;
      margin-top: 5px;
    }

    @media (max-width: 480px) {
      .container {
        padding: 40px 25px;
      }
      
      h1 {
        font-size: 28px;
      }
    }
  </style>
</head>
<body>

  <div class="container">
    <div class="logo">🏠</div>
    <h1>Mojo Tenant System</h1>
    <p class="subtitle">Streamline your property management with our comprehensive tenant and rent tracking system</p>

    <div class="features">
      <h3>Everything You Need</h3>
      <ul>
        <li>Tenant Management</li>
        <li>Rent Collection</li>
        <li>Property Records</li>
        <li>Maintenance Tracking</li>
      </ul>
    </div>

    <div class="btn-group">
      <a href="register.php" class="btn btn-register">Create New Account</a>
      <a href="login.php" class="btn btn-login">Sign In to Your Account</a>
    </div>

    <footer>
      <p>Professional Property Management Solution</p>
      <p class="company-name">© <?php echo date('Y'); ?> Mojo Electrical Enterprise</p>
    </footer>
  </div>

</body>
</html>