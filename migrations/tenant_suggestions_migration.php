<?php
// tenant_suggestions_migration.php

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

// Create the tenant_suggestions table
$sql = "
CREATE TABLE IF NOT EXISTS tenant_suggestions (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT(11) NOT NULL,
    suggestion TEXT NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    status ENUM('pending','reviewed','implemented') DEFAULT 'pending',
    admin_response TEXT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (tenant_id),
    CONSTRAINT fk_tenant_suggestions_tenant FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo " Table 'tenant_suggestions' created or already exists.<br>";
} else {
    echo " Error creating table: " . $conn->error . "<br>";
}

// Show the table structure (like DESCRIBE)
$result = $conn->query("DESCRIBE tenant_suggestions");
if ($result) {
    echo "<br><strong>tenant_suggestions table structure:</strong><br>";
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

echo "<br><br> Tenant Suggestions table migration completed successfully!";
$conn->close();
?>
