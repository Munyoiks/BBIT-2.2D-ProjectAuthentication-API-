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