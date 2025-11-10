// archived_mpesa_transactions.php
<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "auth_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$up = "
CREATE TABLE IF NOT EXISTS archived_mpesa_transactions (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(100) UNIQUE,
    user_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    phone_number VARCHAR(20),
    transaction_date TIMESTAMP DEFAULT current_timestamp(),
    status ENUM('completed','pending','failed') DEFAULT 'completed',
    description TEXT,
    deleted_at DATETIME DEFAULT NULL,
    archived_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT current_timestamp(),
    CONSTRAINT fk_archived_mpesa_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($up) === TRUE) {
    echo " Table archived_mpesa_transactions created successfully!";
} else {
    echo " Error creating table: " . $conn->error;
}

$conn->close();
