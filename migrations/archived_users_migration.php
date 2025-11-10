<?php
// archived_users_migration.php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "auth_db";

// Connect to MySQL
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

// Create archived_users table
$sql = "
CREATE TABLE IF NOT EXISTS archived_users (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    emergency_contact VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    verification_code VARCHAR(10) DEFAULT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_expiry DATETIME DEFAULT NULL,
    reset_attempts INT(11) DEFAULT 0,
    last_reset_at DATETIME DEFAULT NULL,
    token_expiry DATETIME DEFAULT NULL,
    role VARCHAR(20) DEFAULT 'tenant',
    unit_number VARCHAR(20) DEFAULT NULL,
    unit_role ENUM('primary','secondary','family','roommate') DEFAULT 'primary',
    is_primary_tenant TINYINT(1) DEFAULT 0,
    invited_by INT(11) DEFAULT NULL,
    invitation_token VARCHAR(64) DEFAULT NULL,
    invitation_accepted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_admin TINYINT(1) DEFAULT 0,
    linked_to VARCHAR(255) DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    archived_by INT(11) DEFAULT NULL,
    CONSTRAINT fk_archived_users_invited_by FOREIGN KEY (invited_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "Table 'archived_users' created or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Verify structure
$result = $conn->query("DESCRIBE archived_users");
if ($result) {
    echo "<br><strong>archived_users table structure:</strong><br>";
    echo "<table border='1' cellpadding='4' style='border-collapse: collapse; margin-top:10px;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error describing table: " . $conn->error;
}

echo "<br><br> Migration completed successfully!";
$conn->close();
?>
