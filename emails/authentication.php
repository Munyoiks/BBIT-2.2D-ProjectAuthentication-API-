<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// DB connection
$conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// Format Kenyan phone numbers into E.164
function formatPhone($phone) {
    if (!$phone) return false;
    $phone = preg_replace('/\D/', '', $phone); // remove non-digits
    if (preg_match('/^0\d{9}$/', $phone)) {
        return '+254' . substr($phone, 1);
    } elseif (preg_match('/^254\d{9}$/', $phone)) {
        return '+' . $phone;
    } elseif (preg_match('/^\+254\d{9}$/', $phone)) {
        return $phone;
    }
    return false;
}

// -------------------- Registration --------------------
if (isset($_POST['register'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = formatPhone($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    if (!$email) die("Invalid email format");
    if (!$phone) die("Invalid Kenyan phone number");

    // Check duplicates
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $checkStmt->bind_param("ss", $email, $phone);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        echo "Email or phone already exists.";
        exit();
    }
    $checkStmt->close();

    // Insert user (no tfa_secret needed for email-based 2FA)
    $stmt = $conn->prepare("INSERT INTO users (email, phone, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $phone, $password);

    if ($stmt->execute()) {
        echo "Registration successful!<br>";
        echo "A verification email will be sent shortly. <a href='auth.php'>Proceed to Login</a>";
        // Trigger email verification (handled by JS on page load)
    } else {
        echo "Database Error: " . $conn->error;
    }
    $stmt->close();
}

// -------------------- Login --------------------
if (isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    if (!$email) {
        echo "Invalid email format.";
        exit();
    }

    $stmt = $conn->prepare("SELECT id, password, phone FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['login_time'] = time();

            // Generate 2FA code for email
            $code = rand(100000, 999999);
            $_SESSION['tfa_code'] = $code;
            $_SESSION['tfa_expiry'] = time() + 300; // 5 minutes
            $_SESSION['pending_emailjs'] = true;

            // Output HTML with JS to send 2FA email
            echo "<!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>2FA Verification</title>
                <script src='https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js'></script>
                <script src='emailConfig.js'></script> <!-- Your EmailJS config -->
            </head>
            <body>
                <p>Sending 2FA code to <strong>$email</strong>...</p>
                <script>
                    (function() {
                        sendVerificationEmail('$email', '$_SESSION[tfa_code]');
                    })();
                </script>
            </body>
            </html>";
            exit();
        } else {
            echo "Invalid password.";
        }
    } else {
        echo "User not found.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Authentication</title>
    <style>
        .container { max-width: 400px; margin: 50px auto; padding: 20px; }
        form { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        input { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        h2 { color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="phone" placeholder="Phone (e.g., 0722123456)" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="register">Register</button>
        </form>

        <h2>Login</h2>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
    </div>
</body>
</html>