<?php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use RobThree\Auth\TwoFactorAuth;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Database connection
$conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// Verification handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $code = $_POST['code'];

    // Check OTP
    $stmt = $conn->prepare("SELECT * FROM otps WHERE email=? AND code=? AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $otpResult = $stmt->get_result();

    if ($otpResult->num_rows > 0) {
        // Valid OTP → clear it
        $stmt = $conn->prepare("DELETE FROM otps WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        echo " Verification successful! You are logged in.";
    } else {
        echo "Invalid or expired code.";
    }
}
?>

