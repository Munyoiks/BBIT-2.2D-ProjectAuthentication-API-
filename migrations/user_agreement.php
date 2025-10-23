<?php
// migration_create_user_agreements.php

$host = 'localhost';
$db   = 'auth_db';
$user = 'root';
$pass = '1234'; 
$charset = 'utf8mb4';

// Set up DSN and options
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    $sql = "
        CREATE TABLE IF NOT EXISTS user_agreements (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL UNIQUE,
            agreement_file VARCHAR(255) DEFAULT NULL,
            agreement_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            admin_notes TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
    echo " Table 'user_agreements' created or already exists.\n";

} catch (PDOException $e) {
    echo " Database error: " . $e->getMessage() . "\n";
}
