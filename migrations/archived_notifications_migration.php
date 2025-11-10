<?php
// archived_notifications_migration.php

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

// Create archived_notifications table
$sql = "
CREATE TABLE IF NOT EXISTS archived_notifications (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT 'System Notification',
    message TEXT DEFAULT NULL,
    type VARCHAR(50) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tenant_id INT(11) DEFAULT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    sent_date DATETIME DEFAULT NULL,
    status ENUM('sent','failed') DEFAULT NULL,
    tenant_name VARCHAR(255) DEFAULT NULL,
    recipient_email VARCHAR(255) DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    archived_by INT(11) DEFAULT NULL,
    CONSTRAINT fk_archived_notifications_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "Table 'archived_notifications' created or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Verify structure
$result = $conn->query("DESCRIBE archived_notifications");
if ($result) {
    echo "<br><strong>archived_notifications table structure:</strong><br>";
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
