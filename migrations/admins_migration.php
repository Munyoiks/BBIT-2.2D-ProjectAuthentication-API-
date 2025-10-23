<?php
// admins_migration.php

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

// Create admins table with the exact structure
$sql = "
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "Table 'admins' created or already exists.<br>";
} else {
    echo "Error creating admins table: " . $conn->error . "<br>";
}

// Check if we need to alter the table structure (for existing tables)
$result = $conn->query("DESCRIBE admins");
if ($result) {
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = true;
    }

    // Add missing columns if they don't exist
    if (!isset($existingColumns['username'])) {
        $conn->query("ALTER TABLE admins ADD COLUMN username VARCHAR(50) UNIQUE NOT NULL");
        echo "Added missing column: username<br>";
    }

    if (!isset($existingColumns['email'])) {
        $conn->query("ALTER TABLE admins ADD COLUMN email VARCHAR(100) UNIQUE NOT NULL");
        echo "Added missing column: email<br>";
    }

    if (!isset($existingColumns['password'])) {
        $conn->query("ALTER TABLE admins ADD COLUMN password VARCHAR(255) NOT NULL");
        echo "Added missing column: password<br>";
    }

    if (!isset($existingColumns['created_at'])) {
        $conn->query("ALTER TABLE admins ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added missing column: created_at<br>";
    }

    // Add unique constraints if they don't exist
    $indexCheck = $conn->query("SHOW INDEX FROM admins WHERE Key_name = 'username'");
    if ($indexCheck->num_rows == 0) {
        $conn->query("CREATE UNIQUE INDEX username ON admins (username)");
        echo "Added unique constraint: username<br>";
    }

    $indexCheck2 = $conn->query("SHOW INDEX FROM admins WHERE Key_name = 'email'");
    if ($indexCheck2->num_rows == 0) {
        $conn->query("CREATE UNIQUE INDEX email ON admins (email)");
        echo "Added unique constraint: email<br>";
    }
} else {
    echo "Error describing admins table: " . $conn->error . "<br>";
}

// Add default admin account (only if no admins exist)
$checkAdmins = $conn->query("SELECT COUNT(*) as count FROM admins");
if ($checkAdmins) {
    $adminCount = $checkAdmins->fetch_assoc()['count'];
    
    if ($adminCount == 0) {
        // Insert default admin account
        $defaultAdmin = [
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT) // You should change this password
        ];
        
        $stmt = $conn->prepare("
            INSERT INTO admins (username, email, password)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param(
            "sss", 
            $defaultAdmin['username'],
            $defaultAdmin['email'],
            $defaultAdmin['password']
        );
        
        if ($stmt->execute()) {
            echo "Added default admin account:<br>";
            echo "- Username: admin<br>";
            echo "- Email: admin@example.com<br>";
            echo "- Password: admin123 (please change this immediately)<br>";
        } else {
            echo "Error adding default admin: " . $stmt->error . "<br>";
        }
        $stmt->close();
    } else {
        echo "Admins already exist in the database ($adminCount records).<br>";
    }
}

// Display current admins for verification
echo "<br>Current admins in database:<br>";
$adminsResult = $conn->query("SELECT id, username, email, created_at FROM admins");
if ($adminsResult && $adminsResult->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Created At</th></tr>";
    while ($admin = $adminsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$admin['id']}</td>";
        echo "<td>{$admin['username']}</td>";
        echo "<td>{$admin['email']}</td>";
        echo "<td>{$admin['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No admins found in database.<br>";
}

echo "<br>Admins migration complete!";
$conn->close();
?>