<?php
// admins_migration.php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "auth_db";

// Use procedural style for consistency (optional) or keep OOP
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die(" Connection failed: " . $conn->connect_error);
}

//  Create database if it doesn’t exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo " Database '$dbname' created or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

//  Select the database safely
$conn->select_db($dbname);

//  Create admins table
$sql = "
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo " Table 'admins' created or already exists.<br>";
} else {
    echo " Error creating 'admins' table: " . $conn->error . "<br>";
}

// Check and update structure if needed
$result = $conn->query("DESCRIBE admins");
if ($result) {
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = true;
    }

    // Add missing columns if any
    $columnsToAdd = [
        'username' => "ALTER TABLE admins ADD COLUMN username VARCHAR(50) NOT NULL UNIQUE",
        'email' => "ALTER TABLE admins ADD COLUMN email VARCHAR(100) NOT NULL UNIQUE",
        'password' => "ALTER TABLE admins ADD COLUMN password VARCHAR(255) NOT NULL",
        'created_at' => "ALTER TABLE admins ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($columnsToAdd as $col => $query) {
        if (!isset($existingColumns[$col])) {
            $conn->query($query);
            echo " Added missing column: $col<br>";
        }
    }

    //  Verify unique indexes exist properly
    $indexCheck = $conn->query("SHOW INDEX FROM admins WHERE Key_name = 'username'");
    if ($indexCheck->num_rows === 0) {
        $conn->query("CREATE UNIQUE INDEX username_unique ON admins (username)");
        echo "Added unique index: username<br>";
    }

    $indexCheck2 = $conn->query("SHOW INDEX FROM admins WHERE Key_name = 'email'");
    if ($indexCheck2->num_rows === 0) {
        $conn->query("CREATE UNIQUE INDEX email_unique ON admins (email)");
        echo " Added unique index: email<br>";
    }
} else {
    echo " Error describing admins table: " . $conn->error . "<br>";
}

// Add a default admin if none exists
$checkAdmins = $conn->query("SELECT COUNT(*) AS count FROM admins");
if ($checkAdmins) {
    $adminCount = (int) $checkAdmins->fetch_assoc()['count'];
    
    if ($adminCount === 0) {
        $defaultAdmin = [
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT)
        ];
        
        $stmt = $conn->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $defaultAdmin['username'], $defaultAdmin['email'], $defaultAdmin['password']);
        
        if ($stmt->execute()) {
            echo "<br> Default admin account created:<br>";
            echo "- Username: <b>admin</b><br>";
            echo "- Email: <b>vincentkariuki4311@gmail.com</b><br>";
            echo "- Password: <b>munyoiks7</b> (please change this immediately)<br>";
        } else {
            echo " Error adding default admin: " . $stmt->error . "<br>";
        }
        $stmt->close();
    } else {
        echo "Admins already exist in database ($adminCount record(s)).<br>";
    }
} else {
    echo " Error checking admin count: " . $conn->error . "<br>";
}

// Display current admins
echo "<br> Current admins in database:<br>";
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

echo "<br> Admins migration complete!";
$conn->close();
?>
