<?php
// mpesa_transactions_migration.php

$servername = "localhost";
$username = "root";
$password = "1234";
$dbname = "auth_db";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die(" Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo " Database '$dbname' created or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select database
$conn->select_db($dbname);

// Create mpesa_transactions table
$sql = "
CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(100) UNIQUE,
    user_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    phone_number VARCHAR(20),
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('completed', 'pending', 'failed') DEFAULT 'completed',
    description TEXT,
    CONSTRAINT fk_mpesa_user FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo " Table 'mpesa_transactions' created or already exists.<br>";
} else {
    echo " Error creating table: " . $conn->error . "<br>";
}

// Display table structure
$result = $conn->query("DESCRIBE mpesa_transactions");
if ($result) {
    echo "<br><strong>mpesa_transactions table structure:</strong><br>";
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

echo "<br><br> Mpesa Transactions table migration completed successfully!";
$conn->close();
?>
