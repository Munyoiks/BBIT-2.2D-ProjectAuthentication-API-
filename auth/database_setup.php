<?php
// database_setup.php
$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "auth_db"; 

// Connect to MySQL server
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die(" Connection failed: " . $conn->connect_error);
}

//  Create the database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo " Database '$dbname' created or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the new database
$conn->select_db($dbname);

//  Create the `users` table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) DEFAULT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    verification_code VARCHAR(10) DEFAULT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_expiry DATETIME DEFAULT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo " Table 'users' created successfully.<br>";
} else {
    echo " Error creating table: " . $conn->error . "<br>";
}

//  (Optional) Insert a sample verified user
$sampleEmail = "tenant@example.com";
$samplePassword = password_hash("password123", PASSWORD_DEFAULT);
$checkUser = $conn->query("SELECT * FROM users WHERE email='$sampleEmail'");
if ($checkUser->num_rows === 0) {
    $conn->query("INSERT INTO users (full_name, email, phone, password, is_verified)
                  VALUES ('Sample Tenant', '$sampleEmail', '0712345678', '$samplePassword', 1)");
    echo " Sample user added (email: tenant@example.com / password: password123)<br>";
} else {
    echo "Sample user already exists.<br>";
}

echo "<br> Database setup complete!";
$conn->close();
?>
