<?php
  session_start();

  $autoloadPath = __DIR__ . '/../vendor/autoload.php';
  if (!file_exists($autoloadPath)) {
      die("Autoloader not found at: $autoloadPath. Current directory: " . __DIR__);
  }
  require $autoloadPath;

  use RobThree\Auth\TwoFactorAuth;
  use RobThree\Auth\Providers\Qr\QRServerProvider;

  $tfa = new TwoFactorAuth(new QRServerProvider());

  if (!isset($_SESSION['user_id'])) {
      header("Location: authentication.php");
      exit();
  }

  if (isset($_POST['verify'])) {
      $code = $_POST['code'];
      $method = $_SESSION['tfa_method'];

      if ($method === 'app') {
          $secret = $_SESSION['tfa_secret'];
          if ($tfa->verifyCode($secret, $code)) {
              $_SESSION['authenticated'] = true;
              echo "2FA verified! Welcome!";
              // Redirect to protected area
          } else {
              echo "Invalid 2FA code.";
          }
      } else {
          if (time() > $_SESSION['tfa_expiry']) {
              echo "2FA code expired. Please request a new one.";
          } elseif ($code == $_SESSION['tfa_code']) {
              $_SESSION['authenticated'] = true;
              echo "2FA verified! Welcome!";
              // Redirect to protected area
          } else {
              echo "Invalid 2FA code.";
          }
      }
  }
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
      <meta charset="UTF-8">
      <title>Verify 2FA</title>
  </head>
  <body>
      <h2>Enter 2FA Code</h2>
      <form method="POST" action="">
          <input type="text" name="code" placeholder="Enter 2FA Code" required><br><br>
          <button type="submit" name="verify">Verify</button>
      </form>
  </body>
  </html>