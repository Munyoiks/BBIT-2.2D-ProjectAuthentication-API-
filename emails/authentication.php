```php
<?php
session_start();
require 'vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Twilio\Rest\Client;
use Dotenv\Dotenv;
use RobThree\Auth\Providers\Qr\QRServerProvider;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database connection (update with your MariaDB credentials in .env too if you want)
$conn = new mysqli("localhost", "your-username", "your-password", "auth_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize 2FA library
$tfa = new TwoFactorAuth(new QRServerProvider());

// Helper function to generate random 2FA code for email/SMS
function generate2FACode() {
    return rand(100000, 999999);
}

// Registration
if (isset($_POST['register'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $secret = $tfa->createSecret();

    $stmt = $conn->prepare("INSERT INTO users (email, phone, password, tfa_secret) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $email, $phone, $password, $secret);
    if ($stmt->execute()) {
        echo "Registration successful! Scan this QR code with Google Authenticator: ";
        $qrCodeUrl = $tfa->getQRCodeImageAsDataUri("MyApp:$email", $secret);
        echo "<img src='$qrCodeUrl' alt='QR Code for 2FA'>";
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
                $_SESSION['tfa_expiry'] = time() + 300;  // expires in 5 minutes

                if ($_POST['tfa_method'] === 'email') {
                    // Send 2FA code via email
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = $_ENV['MAIL_HOST'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $_ENV['MAIL_USERNAME'];
                        $mail->Password = $_ENV['MAIL_PASSWORD'];
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = $_ENV['MAIL_PORT'];

                        $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME']);
                        $mail->addAddress($user['email']);
                        $mail->isHTML(false);
                        $mail->Subject = 'Your 2FA Code';
                        $mail->Body = "Your 2FA code is: $code. It expires in 5 minutes.";
                        $mail->send();

                        header("Location: verify_2fa.php");
                        exit();
                    } catch (Exception $e) {
                        echo "Email could not be sent. Error: {$mail->ErrorInfo}";
                    }
                } elseif ($_POST['tfa_method'] === 'sms') {
                    try {
                        $twilio = new Client($_ENV['TWILIO_SID'], $_ENV['TWILIO_AUTH_TOKEN']);
                        $twilio->messages->create($user['phone'], [
                            'from' => $_ENV['TWILIO_NUMBER'],
                            'body' => "Your 2FA code is: $code. Expires in 5 minutes."
                        ]);

                        header("Location: verify_2fa.php");
                        exit();
                    } catch (Exception $e) {
                        echo "SMS could not be sent. Error: {$e->getMessage()}";
                    }
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
```
