<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['pending_email'])) {
    echo json_encode(["success" => false]);
    exit();
}

// Generate new OTP
$new_code = rand(100000, 999999);
$_SESSION['verification_code'] = $new_code;
$_SESSION['otp_expiry'] = time() + 300; // reset 5-minute timer

echo json_encode([
    "success" => true,
    "new_code" => $new_code
]);
