<?php
// admin_notifications_migration.php

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
    die(" Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create admin_notifications table with full structure
$sql = "
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT(11) NOT NULL,
    tenant_name VARCHAR(100) NOT NULL,
    unit_number VARCHAR(20) DEFAULT NULL,
    request_title VARCHAR(255) NOT NULL,
    request_category VARCHAR(100) NOT NULL,
    request_priority VARCHAR(20) NOT NULL,
    request_description TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo " Table 'admin_notifications' created or already exists.<br>";
} else {
    echo " Error creating 'admin_notifications' table: " . $conn->error . "<br>";
}

// Verify the existing columns and alter if needed
$result = $conn->query("DESCRIBE admin_notifications");
if ($result) {
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = true;
    }

    // Columns that should exist
    $columnsToAdd = [
        'tenant_id' => "ALTER TABLE admin_notifications ADD COLUMN tenant_id INT(11) NOT NULL AFTER id",
        'tenant_name' => "ALTER TABLE admin_notifications ADD COLUMN tenant_name VARCHAR(100) NOT NULL AFTER tenant_id",
        'unit_number' => "ALTER TABLE admin_notifications ADD COLUMN unit_number VARCHAR(20) DEFAULT NULL AFTER tenant_name",
        'request_title' => "ALTER TABLE admin_notifications ADD COLUMN request_title VARCHAR(255) NOT NULL AFTER unit_number",
        'request_category' => "ALTER TABLE admin_notifications ADD COLUMN request_category VARCHAR(100) NOT NULL AFTER request_title",
        'request_priority' => "ALTER TABLE admin_notifications ADD COLUMN request_priority VARCHAR(20) NOT NULL AFTER request_category",
        'request_description' => "ALTER TABLE admin_notifications ADD COLUMN request_description TEXT NOT NULL AFTER request_priority",
        'is_read' => "ALTER TABLE admin_notifications ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER request_description",
        'created_at' => "ALTER TABLE admin_notifications ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER is_read"
    ];

    foreach ($columnsToAdd as $col => $query) {
        if (!isset($existingColumns[$col])) {
            $conn->query($query);
            echo " Added missing column: $col<br>";
        }
    }
} else {
    echo "Error describing admin_notifications table: " . $conn->error . "<br>";
}

// Add sample notifications if table is empty
$check = $conn->query("SELECT COUNT(*) AS count FROM admin_notifications");
if ($check) {
    $count = (int)$check->fetch_assoc()['count'];
    if ($count === 0) {
        $samples = [
            [
                'tenant_id' => 1,
                'tenant_name' => 'John Doe',
                'unit_number' => 'A-102',
                'request_title' => 'Leaking Bathroom Tap',
                'request_category' => 'Plumbing',
                'request_priority' => 'High',
                'request_description' => 'The bathroom tap has been leaking since yesterday. Please send maintenance soon.'
            ],
            [
                'tenant_id' => 2,
                'tenant_name' => 'Jane Mwangi',
                'unit_number' => 'B-204',
                'request_title' => 'Broken Window Lock',
                'request_category' => 'Repairs',
                'request_priority' => 'Medium',
                'request_description' => 'The window lock in the living room is broken and won’t close properly.'
            ],
            [
                'tenant_id' => 3,
                'tenant_name' => 'Ali Yusuf',
                'unit_number' => 'C-305',
                'request_title' => 'Light Bulb Replacement',
                'request_category' => 'Electrical',
                'request_priority' => 'Low',
                'request_description' => 'The hallway light bulb needs replacement.'
            ]
        ];

        $stmt = $conn->prepare("
            INSERT INTO admin_notifications 
            (tenant_id, tenant_name, unit_number, request_title, request_category, request_priority, request_description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($samples as $n) {
            $stmt->bind_param(
                "issssss",
                $n['tenant_id'],
                $n['tenant_name'],
                $n['unit_number'],
                $n['request_title'],
                $n['request_category'],
                $n['request_priority'],
                $n['request_description']
            );
            $stmt->execute();
        }
        $stmt->close();

        echo " Added sample notifications.<br>";
    } else {
        echo " Notifications already exist ($count record(s)).<br>";
    }
} else {
    echo " Error checking notifications: " . $conn->error . "<br>";
}
//  Display notifications summary
echo "<br>📬 Current Admin Notifications:<br>";
$result = $conn->query("SELECT id, tenant_name, request_title, request_priority, is_read, created_at FROM admin_notifications ORDER BY created_at DESC");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse:collapse;margin:10px 0;'>";
    echo "<tr><th>ID</th><th>Tenant</th><th>Request</th><th>Priority</th><th>Read</th><th>Created At</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $readStatus = $row['is_read'] ? '' : '';
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['tenant_name']}</td>";
        echo "<td>{$row['request_title']}</td>";
        echo "<td>{$row['request_priority']}</td>";
        echo "<td style='text-align:center;'>$readStatus</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No admin notifications found.<br>";
}

echo "<br>✅ Admin notifications migration complete!";
$conn->close();
?>
