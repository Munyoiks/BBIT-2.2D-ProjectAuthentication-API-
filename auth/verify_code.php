<?php
session_start();
require_once "db_config.php";

// Ensure the verification data exists
if (!isset($_SESSION['pending_email']) || !isset($_SESSION['verification_code'])) {
    die("Session expired. Please register again. <a href='register.php'>Register</a>");
}

$email = $_SESSION['pending_email'];
$code = $_SESSION['verification_code'];

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['code']);

    if ($entered == $code) {
        // ✅ Code matches — verify the user in DB
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();

        // ✅ Clear session vars
        unset($_SESSION['verification_code']);
        unset($_SESSION['pending_email']);

        // (Optional) Automatically log the user in
        $_SESSION['user_email'] = $email;

        header("Location: dashboard.php");
        exit();
    } else {
        // ❌ Incorrect code
        $_SESSION['error'] = "Incorrect code. Please try again.";
        header("Location: verify.php");
        exit();
    }
} else {
    header("Location: verify.php");
    exit();
}
?>
