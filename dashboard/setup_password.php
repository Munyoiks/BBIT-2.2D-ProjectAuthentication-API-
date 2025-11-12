<?php
session_start();
require_once '../auth/db_config.php';

$token = $_GET['invite'] ?? '';
$fid   = $_GET['fid'] ?? '';

if (!$token || !$fid) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>Invalid invitation link.</h3>");
}

// === FETCH FAMILY MEMBER ===
$stmt = $conn->prepare("SELECT * FROM families WHERE id = ? AND invitation_token = ?");
$stmt->bind_param("is", $fid, $token);
$stmt->execute();
$result = $stmt->get_result();
$family = $result->fetch_assoc();

if (!$family) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>Invalid or used invitation link.</h3>");
}

// === CHECK EXPIRY ===
if ($family['invitation_expires_at'] && $family['invitation_expires_at'] < date('Y-m-d H:i:s')) {
    die("<h3 style='color:red;text-align:center;margin-top:50px;'>This invitation has expired.</h3>");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password']);

    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // === INSERT USER ===
        $sql = "INSERT INTO users 
                (full_name, email, phone, password, is_verified, role, unit_number, unit_role, is_primary_tenant, invited_by, invitation_token, invitation_accepted_at)
                VALUES (?, ?, ?, ?, 1, 'tenant', ?, 'family', 0, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);

        // CORRECT: 7 values → "ssssisi"
        $stmt->bind_param(
            "ssssisi", 
            $family['full_name'],     // s
            $family['email'],         // s
            $family['phone'],         // s
            $hash,                    // s
            $family['unit_number'],   // s
            $family['invited_by'],    // i (INT)
            $token                    // s
        );

        if ($stmt->execute()) {
            // === CLEAR TOKEN ===
            $conn->query("UPDATE families SET invitation_token = NULL, invitation_expires_at = NULL WHERE id = " . (int)$fid);

            $_SESSION['flash'] = "Account created! You can now log in.";
            header("Location: ../auth/login.php");
            exit;
        } else {
            $error = "Failed to create account. Try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password – Mojo Tenant</title>
    <style>
        body{font-family:system-ui,sans-serif;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
        .box{background:white;padding:2.5rem;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.2);width:100%;max-width:420px;text-align:center}
        h2{margin-bottom:1rem;color:#2c3e50}
        p{font-size:0.95rem;color:#555;margin-bottom:1.5rem}
        .form-group{margin-bottom:1rem}
        label{display:block;text-align:left;margin-bottom:.5rem;font-weight:500;color:#374151}
        input{width:100%;padding:14px;border:2px solid #e5e7eb;border-radius:12px;font-size:16px;transition:.3s}
        input:focus{outline:none;border-color:#4F46E5;box-shadow:0 0 0 3px rgba(79,70,229,.1)}
        .btn{background:#4F46E5;color:white;padding:14px;border:none;border-radius:12px;width:100%;font-weight:600;cursor:pointer;margin-top:1rem;transition:.3s}
        .btn:hover{background:#4338CA;transform:translateY(-1px)}
        .error{color:#dc2626;margin-top:.5rem;font-size:0.9rem}
        .logo{font-size:1.8rem;font-weight:700;color:#4F46E5;margin-bottom:1rem}
    </style>
</head>
<body>
<div class="box">
    <div class="logo">Mojo Tenant</div>
    <h2>Set Your Password</h2>
    <p>Hello <strong><?=htmlspecialchars($family['full_name'])?></strong>,<br>
       You've been invited to join <strong>Unit <?=htmlspecialchars($family['unit_number'])?></strong>.</p>

    <?php if ($error): ?>
        <div class="error"><?=$error?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>Choose a Password (6+ characters)</label>
            <input type="password" name="password" required minlength="6" placeholder="Enter strong password">
        </div>
        <button type="submit" class="btn">Create Account & Login</button>
    </form>
</div>
</body>
</html>
