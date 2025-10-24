<?php
// migration_create_user_tables.php

$host = 'localhost';
$db   = 'auth_db';
$user = 'root';
$pass = ''; // <--
//  Change this to your actual password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Start migration
    echo " Starting migration...\n";

    // Create user_agreements table
    $sql1 = "
        CREATE TABLE IF NOT EXISTS user_agreements (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL UNIQUE,
            agreement_file VARCHAR(255) DEFAULT NULL,
            agreement_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            admin_notes TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    // Create user_profile table
    $sql2 = "
        CREATE TABLE IF NOT EXISTS user_profile (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL UNIQUE,
            address TEXT DEFAULT NULL,
            emergency_contact VARCHAR(255) DEFAULT NULL,
            profile_picture VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    // Execute both
    $pdo->exec($sql1);
    echo "✅ Table 'user_agreements' created or already exists.\n";

    $pdo->exec($sql2);
    echo "Table 'user_profile' created or already exists.\n";

    echo " Migration completed successfully.\n";

} catch (PDOException $e) {
    echo " Database error: " . $e->getMessage() . "\n";
}
