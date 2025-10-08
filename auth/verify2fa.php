<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['tfa_code'], $_SESSION['tfa_expiry'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);

    if (time() > $_SESSION['tfa_expiry']) {
        $error = " Code expired. Please login again.";
        session_unset();
        session_destroy();
    } elseif ($code == $_SESSION['tfa_code']) {
        // Successful verification
        $_SESSION['verified'] = true; 
        unset($_SESSION['tfa_code'], $_SESSION['tfa_expiry']); 

        $success = " 2FA verification successful!";
        header("Refresh: 2; URL=dashboard.php");
        exit();
    } else {
        $error = " Invalid code. Try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify 2FA</title>
    <style>
        .container { max-width: 400px; margin: 50px auto; padding: 20px; }
        form { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        input { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; text-align: center; letter-spacing: 5px; }
        button { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #218838; }
        .error { color: red; margin: 10px 0; font-weight: bold; }
        .success { color: green; margin: 10px 0; font-weight: bold; }
        h2 { color: #333; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Enter 2FA Code</h2>
        <?php if ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?= $success ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="code" placeholder="6-digit code" maxlength="6" required>
            <button type="submit">Verify</button>
        </form>
        <p style="text-align:center;">Code sent to: <strong><?= htmlspecialchars($_SESSION['phone']) ?></strong></p>
    </div>
</body>
</html>
