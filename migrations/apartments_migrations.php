<?php

/**
 * Migration: Create Apartments Table
 * Description: Creates the 'apartments' table with all fields, keys, and defaults.
 */

require_once '../auth/db_config.php'; // Include your DB connection file

try {
    $pdo = Database::getConnection(); // Assume Database::getConnection() returns a PDO instance

    $sql = "
        CREATE TABLE IF NOT EXISTS apartments (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            apartment_number VARCHAR(20) NOT NULL UNIQUE,
            building_name VARCHAR(100) DEFAULT NULL,
            rent_amount DECIMAL(10,2) NOT NULL,
            is_occupied TINYINT(1) DEFAULT 0,
            tenant_id INT(11) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('vacant', 'occupied') DEFAULT 'vacant',
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    $pdo->exec($sql);
    echo " Migration successful: 'apartments' table created.\n";

} catch (PDOException $e) {
    echo " Migration failed: " . $e->getMessage() . "\n";
}

class Database {
    public static function getConnection() {
        $host = 'localhost';
        $db   = 'auth_db';
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
}
