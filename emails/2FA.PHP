<?php
session_start();
require 'vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;

$tfa = new TwoFactorAuth();
$message = '';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth_system.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $code = trim($_POST['code']);
    $method = $_SESSION['tfa_method'] ?? null;

    if ($method === 'app') {
        $secret = $_SESSION['tfa_secret'] ?? '';
        if ($tfa->verifyCode($secret, $code)) {
            $_SESSION['authenticated'] = true;
            header("Location: protected_area.php");
            exit();
        } else {
            $message = "Invalid 2FA code.";
        }
    } elseif ($method === 'email') {
        $expectedCode = $_SESSION['tfa_code'] ?? '';
        if (hash_equals($expectedCode, $code)) {
            $_SESSION['authenticated'] = true;
            header("Location: protected_area.php");
            exit();
        } else {
            $message = "Invalid 2FA code.";
        }
    } else {
        $message = "Unknown 2FA method.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify 2FA</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>Two-Factor Authentication</h2>

    <?php if (!empty($message)): ?>
        <p class="error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="code">Enter 2FA Code:</label><br>
            <input type="text" id="code" name="code" placeholder="Enter 2FA Code" required>
        </div>
        <button type="submit" name="verify">Verify</button>
    </form>
</body>
</html>
