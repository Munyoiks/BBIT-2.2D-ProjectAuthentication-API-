<?php
// announcements_migration.php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "auth_db";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die(" Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo " Database '$dbname' created or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create announcements table with full structure
$sql = "
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved') DEFAULT 'pending',
    posted_by INT(11) DEFAULT 1,
    suggested_by INT(11) DEFAULT NULL,
    related_suggestion_id INT(11) DEFAULT NULL,
    priority ENUM('low','medium','high') DEFAULT 'low'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if ($conn->query($sql) === TRUE) {
    echo " Table 'announcements' created or already exists.<br>";
} else {
    echo "Error creating 'announcements' table: " . $conn->error . "<br>";
}

// Verify existing columns and alter if needed
$result = $conn->query("DESCRIBE announcements");
if ($result) {
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = true;
    }

    // Columns that should exist
    $columnsToAdd = [
        'title' => "ALTER TABLE announcements ADD COLUMN title VARCHAR(255) NOT NULL",
        'message' => "ALTER TABLE announcements ADD COLUMN message TEXT NOT NULL",
        'created_at' => "ALTER TABLE announcements ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'status' => "ALTER TABLE announcements ADD COLUMN status ENUM('pending','approved') DEFAULT 'pending'",
        'posted_by' => "ALTER TABLE announcements ADD COLUMN posted_by INT(11) DEFAULT 1",
        'suggested_by' => "ALTER TABLE announcements ADD COLUMN suggested_by INT(11) DEFAULT NULL",
        'related_suggestion_id' => "ALTER TABLE announcements ADD COLUMN related_suggestion_id INT(11) DEFAULT NULL",
        'priority' => "ALTER TABLE announcements ADD COLUMN priority ENUM('low','medium','high') DEFAULT 'low'"
    ];

    foreach ($columnsToAdd as $col => $query) {
        if (!isset($existingColumns[$col])) {
            $conn->query($query);
            echo " Added missing column: $col<br>";
        }
    }
} else {
    echo " Error describing announcements table: " . $conn->error . "<br>";
}
//  Add sample announcements if none exist
$check = $conn->query("SELECT COUNT(*) AS count FROM announcements");
if ($check) {
    $count = (int)$check->fetch_assoc()['count'];
    if ($count === 0) {
        $samples = [
            [
                'title' => 'Welcome to Our Apartment Community!',
                'message' => 'We would like to extend a warm welcome to all our residents.',
                'status' => 'approved',
                'priority' => 'medium'
            ],
            [
                'title' => 'Maintenance Schedule Update',
                'message' => 'Elevator maintenance next Tuesday, 9:00 AM–3:00 PM.',
                'status' => 'pending',
                'priority' => 'high'
            ],
            [
                'title' => 'Community Clean-up Day',
                'message' => 'Join us this Saturday from 8:00 AM for our community clean-up!',
                'status' => 'approved',
                'priority' => 'low'
            ]
        ];

        $stmt = $conn->prepare("
            INSERT INTO announcements (title, message, status, priority)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($samples as $a) {
            $stmt->bind_param("ssss", $a['title'], $a['message'], $a['status'], $a['priority']);
            $stmt->execute();
        }
        $stmt->close();

        echo " Added sample announcements.<br>";
    } else {
  echo " Announcements already exist ($count record(s)).<br>";
    }
} else {
    echo " Error checking announcements: " . $conn->error . "<br>";
}

// Display announcements
echo "<br> Current Announcements:<br>";
$result = $conn->query("SELECT id, title, status, priority, created_at FROM announcements ORDER BY created_at DESC");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse:collapse;margin:10px 0;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Priority</th><th>Created At</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['title']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['priority']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No announcements found.<br>";
}

// Table stats
$stats = $conn->query("
    SELECT COUNT(*) AS total, MIN(created_at) AS oldest, MAX(created_at) AS newest
    FROM announcements
");
if ($stats && $row = $stats->fetch_assoc()) {
    echo "<br> Announcements Stats:<br>";
    echo "- Total: {$row['total']}<br>";
    if ($row['oldest']) echo "- Oldest: " . date('F j, Y', strtotime($row['oldest'])) . "<br>";
    if ($row['newest']) echo "- Newest: " . date('F j, Y', strtotime($row['newest'])) . "<br>";
}

echo "<br>✅ Announcements migration complete!";
$conn->close();
?>

