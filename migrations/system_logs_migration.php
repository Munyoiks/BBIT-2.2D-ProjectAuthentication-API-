<?php
// system_logs_migration.php

$servername = "localhost";
$username = "root";
$password = "1234";
$dbname = "auth_db";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die(" Connection failed: " . $conn->connect_error);
}

// Create the database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo " Database '$dbname' created or already exists.<br>";
} else {
    die(" Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create the system_logs table
$sql = "
CREATE TABLE IF NOT EXISTS system_logs (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(255) DEFAULT NULL,
    apartment_number VARCHAR(100) DEFAULT NULL,
    performed_by INT(11) DEFAULT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_performed_by (performed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo " Table 'system_logs' created or already exists.<br>";
} else {
    echo " Error creating table: " . $conn->error . "<br>";
}

// Display structure like DESCRIBE
$result = $conn->query("DESCRIBE system_logs");
if ($result) {
    echo "<br><strong>system_logs table structure:</strong><br>";
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

echo "<br><br> System Logs table migration completed successfully!";
$conn->close();
?>
