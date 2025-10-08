<?php
session_start();
$conn = new mysqli("localhost", "mojo_user", "StrongPass123!", "mojo_db");

if (!isset($_POST['code']) || !isset($_SESSION['pending_email'])) {
    die("Invalid request");
}

$entered = trim($_POST['code']);
$expected = $_SESSION['verification_code'];
$email = $_SESSION['pending_email'];

if ($entered == $expected && time() < $_SESSION['code_expiry']) {
    // Mark as verified
    $stmt = $conn->prepare("UPDATE users SET verified = 1 WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();

    // Log in user
    $_SESSION['user_email'] = $email;

    // Clear verification session
    unset($_SESSION['verification_code']);
    unset($_SESSION['pending_email']);
    unset($_SESSION['code_expiry']);

    echo "✅ User verified and logged in successfully! <a href='dashboard.php'>Go to Dashboard</a>";
} else {
    echo "❌ Invalid or expired code. <a href='register.php'>Try again</a>";
}
?>
