<?php
// database_migration.php

$servername = "localhost";
$username = "root";
$password = "1234";
$dbname = "auth_db";


$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database '$dbname' created or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create `users` table with your exact structure
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
    echo "Table 'users' created or already exists.<br>";
    
    // Add this after table creation to reset any problematic token data
    $conn->query("UPDATE users SET reset_token = NULL, verification_code = NULL, token_expiry = NULL WHERE reset_token = '' OR verification_code = ''");
    echo "Reset empty token fields to NULL<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Check if we need to alter the table structure (for existing tables)
$result = $conn->query("DESCRIBE users");
$existingColumns = [];
while ($row = $result->fetch_assoc()) {
    $existingColumns[$row['Field']] = true;
}

// Add missing columns if they don't exist
if (!isset($existingColumns['token_expiry'])) {
    $conn->query("ALTER TABLE users ADD COLUMN token_expiry DATETIME DEFAULT NULL");
    echo "Added missing column: token_expiry<br>";
}

if (!isset($existingColumns['reset_attempts'])) {
    $conn->query("ALTER TABLE users ADD COLUMN reset_attempts INT(11) DEFAULT 0");
    echo "Added missing column: reset_attempts<br>";
}

if (!isset($existingColumns['last_reset_at'])) {
    $conn->query("ALTER TABLE users ADD COLUMN last_reset_at DATETIME DEFAULT NULL");
    echo "Added missing column: last_reset_at<br>";
}

// Add a sample verified user (using prepared statements to prevent SQL injection)
$sampleEmail = "tenant@example.com";
$samplePassword = password_hash("password123", PASSWORD_DEFAULT);

// Check if user exists using prepared statement
$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$checkStmt->bind_param("s", $sampleEmail);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows === 0) {
    // Insert sample user using prepared statement
    $insertStmt = $conn->prepare("
        INSERT INTO users (full_name, email, phone, password, is_verified, verification_code, reset_token, reset_expiry, reset_attempts, last_reset_at, token_expiry)
        VALUES (?, ?, ?, ?, 1, NULL, NULL, NULL, 0, NULL, NULL)
    ");
    $fullName = "Sample Tenant";
    $phone = "0712345678";
    
    $insertStmt->bind_param("ssss", $fullName, $sampleEmail, $phone, $samplePassword);
    
    if ($insertStmt->execute()) {
        echo "Sample user added (email: tenant@example.com / password: password123)<br>";
    } else {
        echo "Error adding sample user: " . $insertStmt->error . "<br>";
    }
    $insertStmt->close();
} else {
    echo "Sample user already exists.<br>";
}

$checkStmt->close();

echo "<br>Database migration complete!";
$conn->close();
?>