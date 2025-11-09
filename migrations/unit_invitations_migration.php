<?php
// unit_invitations_migration.php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "auth_db";

// Connect to MariaDB
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die(" Connection failed: " . $conn->connect_error);
}

// Create the database if it doesn’t exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo " Database '$dbname' created or already exists.<br>";
} else {
    die(" Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create the unit_invitations table
$sql = "
CREATE TABLE IF NOT EXISTS unit_invitations (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    unit_number VARCHAR(20) NOT NULL,
    inviter_id INT(11) NOT NULL,
    invite_email VARCHAR(255) NOT NULL,
    role ENUM('spouse','family','roommate') NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('pending','accepted','expired') DEFAULT 'pending',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    INDEX (inviter_id),
    CONSTRAINT fk_unit_invitations_inviter FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo " Table 'unit_invitations' created or already exists.<br>";
} else {
    echo " Error creating table: " . $conn->error . "<br>";
}

// Show the table structure (like DESCRIBE)
$result = $conn->query("DESCRIBE unit_invitations");
if ($result) {
    echo "<br><strong>unit_invitations table structure:</strong><br>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-top:10px;'>";
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
    echo " Error describing table: " . $conn->error;
}

echo "<br><br> Unit Invitations table migration completed successfully!";
$conn->close();
?>
