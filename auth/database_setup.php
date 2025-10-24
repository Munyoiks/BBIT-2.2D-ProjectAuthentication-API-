<?php
require_once(__DIR__ . '/db_config.php');

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if (!$conn->query($sql)) {
    error_log("Error creating database '$dbname': " . $conn->error);
}

// Database is already selected via db_config.php, so no need for $conn->select_db($dbname);

// Create users table
$sql = "
CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) DEFAULT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    verification_code VARCHAR(10) DEFAULT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_expiry DATETIME DEFAULT NULL,
    reset_attempts INT(11) DEFAULT 0,
    last_reset_at DATETIME DEFAULT NULL,
    token_expiry DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($sql)) {
    error_log("Error creating table 'users': " . $conn->error);
}

// Insert sample user if not exists
$sampleEmail = "tenant@example.com";
$samplePassword = password_hash("password123", PASSWORD_DEFAULT);

$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$checkStmt->bind_param("s", $sampleEmail);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows === 0) {
    $insertStmt = $conn->prepare("
        INSERT INTO users (full_name, email, phone, password, is_verified)
        VALUES (?, ?, ?, ?, 1)
    ");
    $fullName = "Sample Tenant";
    $phone = "0712345678";
    $insertStmt->bind_param("ssss", $fullName, $sampleEmail, $phone, $samplePassword);

    if (!$insertStmt->execute()) {
        error_log("Error adding sample user: " . $insertStmt->error);
    }
    $insertStmt->close();
}

$checkStmt->close();
$conn->close();
