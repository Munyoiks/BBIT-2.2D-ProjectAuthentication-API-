// user_agreements_migration.php
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "auth_db";

// Connect to MariaDB
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die(" Connection failed: " . $conn->connect_error);
}

// Create database if it doesn’t exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo " Database '$dbname' created or already exists.<br>";
} else {
    die(" Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create the user_agreements table
$sql = "
CREATE TABLE IF NOT EXISTS user_agreements (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL UNIQUE,
    agreement_file VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    agreement_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    reviewed_by INT(11) DEFAULT NULL,
    INDEX (reviewed_by),
    CONSTRAINT fk_user_agreements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_agreements_admin FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo " Table 'user_agreements' created or already exists.<br>";
} else {
    echo " Error creating table: " . $conn->error . "<br>";
}

// Show the table structure (like DESCRIBE)
$result = $conn->query("DESCRIBE user_agreements");
if ($result) {
    echo "<br><strong>user_agreements table structure:</strong><br>";
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

echo "<br><br> User Agreements table migration completed successfully!";
$conn->close();
?>
