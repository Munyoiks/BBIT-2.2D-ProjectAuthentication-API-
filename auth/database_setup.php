<?php
// database_setup.php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "auth_db";

// 1️ Connect to MySQL
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die(" Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo " Database '$dbname' created or already exists.<br>";
} else {
    die(" Error creating database: " . $conn->error);
}

//  Select the database
$conn->select_db($dbname);

//  Create `users` table with your exact structure
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

if ($conn->query($sql) === TRUE) {
    echo " Table 'users' created or already exists.<br>";
} else {
    echo " Error creating table: " . $conn->error . "<br>";
}

//  Add a sample verified user (only if not already present)
$sampleEmail = "tenant@example.com";
$samplePassword = password_hash("password123", PASSWORD_DEFAULT);
$checkUser = $conn->query("SELECT * FROM users WHERE email='$sampleEmail'");

if ($checkUser->num_rows === 0) {
    $conn->query("
        INSERT INTO users (full_name, email, phone, password, is_verified)
        VALUES ('Sample Tenant', '$sampleEmail', '0712345678', '$samplePassword', 1)
    ");
    echo " Sample user added (email: tenant@example.com / password: password123)<br>";
} else {
    echo " Sample user already exists.<br>";
}

echo "<br> Database setup complete!";
$conn->close();
?>
