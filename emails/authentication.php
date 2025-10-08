<<<<<<< HEAD
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
=======
<?php
session_start();
require 'vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;  // Updated: Correct namespace for robthree/twofactorauth
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection (update with your MariaDB credentials)
$conn = new mysqli("localhost", "your-username", "your-password", "auth_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize 2FA library (requires a QR code provider; using built-in BaconQrCode by default)
$tfa = new TwoFactorAuth();  // This uses the default QR provider (BaconQrCode)

// Helper function to generate random 2FA code for email/SMS
function generate2FACode() {
    return rand(100000, 999999);
}

// Registration
if (isset($_POST['register'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $secret = $tfa->createSecret(); // Generate TOTP secret

    $stmt = $conn->prepare("INSERT INTO users (email, phone, password, tfa_secret) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $email, $phone, $password, $secret);
    if ($stmt->execute()) {
        echo "Registration successful! Scan this QR code with Google Authenticator: ";
        $qrCodeUrl = $tfa->getQRCodeImageAsDataUri("MyApp:$email", $secret);
        echo "<img src='$qrCodeUrl' alt='QR Code for 2FA'>";
        // Optional: Verify the setup by asking for a code immediately
    } else {
        echo "Error during registration: " . $conn->error;
    }
    $stmt->close();
}

// Login
if (isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, email, phone, password, tfa_secret FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['tfa_secret'] = $user['tfa_secret'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['tfa_method'] = $_POST['tfa_method'];

            if ($_POST['tfa_method'] === 'app') {
                header("Location: verify_2fa.php");
                exit();
            } else {
                $code = generate2FACode();
                $_SESSION['tfa_code'] = $code;
                $_SESSION['tfa_expiry'] = time() + 300;  // Code expires in 5 minutes

                if ($_POST['tfa_method'] === 'email') {
                    // Send 2FA code via email
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'lynn.dede@strathmore.edu';  // Update with your email
                        $mail->Password = 'okhr vnik fldt fwdg';     // Update with your app password
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        $mail->setFrom('your-email@gmail.com', 'Auth System');
                        $mail->addAddress($user['email']);
                        $mail->isHTML(false);
                        $mail->Subject = 'Your 2FA Code';
                        $mail->Body = "Your 2FA code is: $code. It expires in 5 minutes.";
                        $mail->send();
                        echo "Email sent with 2FA code.";
                        header("Location: verify_2fa.php");
                        exit();
                    } catch (Exception $e) {
                        echo "Email could not be sent. Error: {$mail->ErrorInfo}";
                    }
                } elseif ($_POST['tfa_method'] === 'sms') {
                    // Placeholder for SMS (integrate Twilio if installed)
                    // Example with Twilio:
                    // use Twilio\Rest\Client;
                    // $client = new Client('ACCOUNT_SID', 'AUTH_TOKEN');
                    // $client->messages->create($user['phone'], [
                    //     'from' => 'YOUR_TWILIO_NUMBER',
                    //     'body' => "Your 2FA code is: $code. Expires in 5 minutes."
                    // ]);
                    echo "SMS 2FA code sent (placeholder - integrate Twilio for real SMS).";
                    header("Location: verify_2fa.php");
                    exit();
                }
            }
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
    <title>User Authentication with 2FA</title>
</head>
<body>
    <h2>Register</h2>
    <form method="POST" action="">
        <input type="email" name="email" placeholder="Email" required><br><br>
        <input type="text" name="phone" placeholder="Phone (e.g., +1234567890)" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit" name="register">Register</button>
    </form>

    <h2>Login</h2>
    <form method="POST" action="">
        <input type="email" name="email" placeholder="Email" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <select name="tfa_method">
            <option value="app">Google Authenticator App</option>
            <option value="email">Email Code</option>
            <option value="sms">SMS Code</option>
        </select><br><br>
        <button type="submit" name="login">Login</button>
    </form>
</body>
</html>
>>>>>>> origin
