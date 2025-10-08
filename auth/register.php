<?php
session_start();
require_once "db_config.php"; // simple file with your DB connection

function formatPhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    if (preg_match('/^0\d{9}$/', $phone)) return '+254' . substr($phone, 1);
    if (preg_match('/^254\d{9}$/', $phone)) return '+' . $phone;
    if (preg_match('/^\+254\d{9}$/', $phone)) return $phone;
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = formatPhone($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    if (!$name || !$email || !$phone) {
        die("Invalid input, please try again.");
    }

    // Check if email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email=? OR phone=?");
    $check->bind_param("ss", $email, $phone);
    $check->execute();
    $res = $check->get_result();
    if ($res->num_rows > 0) {
        echo "Email or phone already exists. <a href='login.php'>Login</a>";
        exit();
    }
    $check->close();

    // Insert user (unverified)
    $stmt = $conn->prepare("INSERT INTO users (name, phone, email, password, verified) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("ssss", $name, $phone, $email, $password);
    if ($stmt->execute()) {
        // Generate verification code
        $code = rand(100000, 999999);
        $_SESSION['verification_code'] = $code;
        $_SESSION['pending_email'] = $email;

        // Output HTML that triggers EmailJS to send code
        echo "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Sending Verification...</title>
            <script src='https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js'></script>
            <script>
              (function() {
                  emailjs.init({ publicKey: 'RFl2-4eHenzarWon4' });
              })();

              function sendCode() {
                  emailjs.send('service_e594fkz', 'template_wzft06q', {
                      to_email: '$email',
                      verification_code: '$code'
                  }).then(() => {
                      alert('A verification code was sent to $email');
                      window.location.href = 'verify.php';
                  }).catch((err) => {
                      console.error('EmailJS error:', err);
                      alert('Failed to send code. Please try again.');
                      window.location.href = 'verify.php';
                  });
              }

              window.onload = sendCode;
            </script>
        </head>
        <body>
            <p>Sending verification code to <strong>$email</strong>...</p>
        </body>
        </html>";
        exit();
    } else {
        echo "Database error: " . $conn->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | Mojo Tenant System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .form-container {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 400px;
        }
        h2 { text-align: center; color: #333; }
        input {
            width: 100%; padding: 12px; margin: 8px 0;
            border: 1px solid #ccc; border-radius: 6px;
        }
        button {
            width: 100%; padding: 12px;
            background: #007bff; color: white;
            border: none; border-radius: 6px;
            cursor: pointer;
        }
        button:hover { background: #0056b3; }
        .link { text-align: center; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Register Account</h2>
        <form method="POST">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="text" name="phone" placeholder="Phone (e.g., 0722123456)" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Register</button>
        </form>
        <div class="link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>
</body>
</html>
