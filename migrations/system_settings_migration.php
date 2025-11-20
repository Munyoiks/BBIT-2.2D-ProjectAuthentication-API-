<?php
// system_settings_migration.php

$servername = "localhost";
$username = "root";
$password = "1234";
$dbname = "auth_db";

// Connect to MySQL/MariaDB
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// Create the database if it doesn’t exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "✅ Database '$dbname' created or already exists.<br>";
} else {
    die("❌ Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create the system_settings table
$sql = "
CREATE TABLE IF NOT EXISTS system_settings (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    system_name VARCHAR(255) NOT NULL DEFAULT 'Monrine Tenant System',
    contact_email VARCHAR(255) NOT NULL DEFAULT 'support@Monrinesystem.com',
    grace_period INT(11) NOT NULL DEFAULT 5,
    reminder_day INT(11) NOT NULL DEFAULT 28,
    notifications TINYINT(1) NOT NULL DEFAULT 1,
    currency VARCHAR(10) NOT NULL DEFAULT 'KES',
    date_format VARCHAR(20) NOT NULL DEFAULT 'Y-m-d',
    timezone VARCHAR(50) NOT NULL DEFAULT 'Africa/Nairobi',
    late_fee DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    late_fee_type ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed',
    late_fee_value DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "✅ Table 'system_settings' created or already exists.<br>";
} else {
    echo "❌ Error creating table: " . $conn->error . "<br>";
}

// Display table structure (like DESCRIBE)
$result = $conn->query("DESCRIBE system_settings");
if ($result) {
    echo "<br><strong>system_settings table structure:</strong><br>";
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
    echo "❌ Error describing table: " . $conn->error;
}

echo "<br><br>🎉 System Settings table migration completed successfully!";
$conn->close();
?>
