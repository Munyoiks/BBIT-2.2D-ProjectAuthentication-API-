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
      position: relative;
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: 
        radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.2) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(118, 75, 162, 0.2) 0%, transparent 50%);
      pointer-events: none;
    }

    .container {
      background: rgba(255, 255, 255, 0.95);
      padding: 60px 50px;
      border-radius: 24px;
      box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.1),
        0 0 0 1px rgba(255, 255, 255, 0.3);
      width: 100%;
      max-width: 500px;
      text-align: center;
      backdrop-filter: blur(15px);
      border: 1px solid rgba(255, 255, 255, 0.4);
      position: relative;
      z-index: 1;
      transform: translateY(0);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .container:hover {
      transform: translateY(-5px);
      box-shadow: 
        0 25px 50px rgba(0, 0, 0, 0.15),
        0 0 0 1px rgba(255, 255, 255, 0.4);
    }

    .logo {
      font-size: 4rem;
      margin-bottom: 20px;
      display: block;
      background: linear-gradient(135deg, #667eea, #764ba2);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      filter: drop-shadow(0 4px 8px rgba(102, 126, 234, 0.2));
    }

    h1 {
      color: #2d3748;
      font-size: 36px;
      margin-bottom: 15px;
      font-weight: 700;
      letter-spacing: -0.5px;
      background: linear-gradient(135deg, #2d3748, #4a5568);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .subtitle {
      color: #718096;
      font-size: 17px;
      margin-bottom: 45px;
      line-height: 1.6;
      font-weight: 400;
    }

    .btn-group {
      display: flex;
      flex-direction: column;
      gap: 18px;
      margin-bottom: 35px;
    }

    .btn {
      padding: 18px 28px;
      border: none;
      border-radius: 14px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      text-decoration: none;
      display: block;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .btn:hover::before {
      left: 100%;
    }

    .btn-register {
      background: linear-gradient(135deg, #28a745, #20c997);
      color: white;
      box-shadow: 0 6px 20px rgba(40, 167, 69, 0.25);
    }

    .btn-register:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(40, 167, 69, 0.4);
    }

    .btn-login {
      background: linear-gradient(135deg, #ffc107, #fd7e14);
      color: white;
      box-shadow: 0 6px 20px rgba(255, 193, 7, 0.25);
    }

    .btn-login:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(255, 193, 7, 0.4);
    }

    .features {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.08), rgba(118, 75, 162, 0.08));
      padding: 25px;
      border-radius: 16px;
      margin-bottom: 35px;
      border: 1px solid rgba(102, 126, 234, 0.1);
      position: relative;
    }

    .features::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, #667eea, #764ba2);
      border-radius: 16px 16px 0 0;
    }

    .features h3 {
      color: #2d3748;
      margin-bottom: 20px;
      font-size: 18px;
      font-weight: 600;
    }

    .features ul {
      list-style: none;
      text-align: left;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .features li {
      color: #4a5568;
      margin-bottom: 0;
      padding: 8px 0 8px 28px;
      position: relative;
      font-weight: 500;
    }

    .features li:before {
      content: "✓";
      color: #28a745;
      font-weight: bold;
      position: absolute;
      left: 8px;
      background: rgba(40, 167, 69, 0.1);
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
    }

    footer {
      margin-top: 30px;
      color: #718096;
      font-size: 14px;
      border-top: 1px solid rgba(0, 0, 0, 0.08);
      padding-top: 25px;
    }

    .company-name {
      color: #2d3748;
      font-weight: 600;
      margin-top: 8px;
      font-size: 15px;
    }

    @media (max-width: 480px) {
      .container {
        padding: 40px 25px;
      }
      
      h1 {
        font-size: 28px;
      }
      
      .features ul {
        grid-template-columns: 1fr;
      }
      
      .logo {
        font-size: 3.5rem;
      }
    }

    @media (max-width: 360px) {
      .container {
        padding: 30px 20px;
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