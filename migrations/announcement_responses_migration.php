<?php
// announcement_responses_migration.php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "auth_db";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "✅ Database '$dbname' created or already exists.<br>";
} else {
    die("❌ Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// ✅ Create announcement_responses table
$sql = "
CREATE TABLE IF NOT EXISTS announcement_responses (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT(11) NOT NULL,
    tenant_id INT(11) NOT NULL,
    message TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (announcement_id),
    INDEX (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "✅ Table 'announcement_responses' created or already exists.<br>";
} else {
    echo "❌ Error creating 'announcement_responses' table: " . $conn->error . "<br>";
}

// ✅ Verify existing columns and alter if missing
$result = $conn->query("DESCRIBE announcement_responses");
if ($result) {
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = true;
    }

    $columnsToAdd = [
        'announcement_id' => "ALTER TABLE announcement_responses ADD COLUMN announcement_id INT(11) NOT NULL AFTER id",
        'tenant_id' => "ALTER TABLE announcement_responses ADD COLUMN tenant_id INT(11) NOT NULL AFTER announcement_id",
        'message' => "ALTER TABLE announcement_responses ADD COLUMN message TEXT NOT NULL AFTER tenant_id",
        'image' => "ALTER TABLE announcement_responses ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER message",
        'created_at' => "ALTER TABLE announcement_responses ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER image"
    ];

    foreach ($columnsToAdd as $col => $query) {
        if (!isset($existingColumns[$col])) {
            $conn->query($query);
            echo "🛠 Added missing column: $col<br>";
        }
    }
} else {
    echo "❌ Error describing announcement_responses table: " . $conn->error . "<br>";
}

// ✅ Insert sample responses if table is empty
$check = $conn->query("SELECT COUNT(*) AS count FROM announcement_responses");
if ($check) {
    $count = (int)$check->fetch_assoc()['count'];
    if ($count === 0) {
        $samples = [
            [
                'announcement_id' => 1,
                'tenant_id' => 101,
                'message' => 'Thank you for the notice. We will be available for the maintenance schedule.',
                'image' => NULL
            ],
            [
                'announcement_id' => 1,
                'tenant_id' => 102,
                'message' => 'Please clarify if water supply will be affected during the repair.',
                'image' => NULL
            ],
            [
                'announcement_id' => 2,
                'tenant_id' => 103,
                'message' => 'Attached is the photo showing the current state of the corridor light.',
                'image' => 'uploads/corridor_light.jpg'
            ]
        ];

        $stmt = $conn->prepare("
            INSERT INTO announcement_responses 
            (announcement_id, tenant_id, message, image)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($samples as $r) {
            $stmt->bind_param(
                "iiss",
                $r['announcement_id'],
                $r['tenant_id'],
                $r['message'],
                $r['image']
            );
            $stmt->execute();
        }
        $stmt->close();

        echo "✅ Added sample announcement responses.<br>";
    } else {
        echo "ℹ Announcement responses already exist ($count record(s)).<br>";
    }
} else {
    echo "❌ Error checking announcement_responses table: " . $conn->error . "<br>";
}

// ✅ Display summary of current responses
echo "<br>💬 Current Announcement Responses:<br>";
$result = $conn->query("
    SELECT id, announcement_id, tenant_id, LEFT(message, 60) AS short_message, created_at 
    FROM announcement_responses 
    ORDER BY created_at DESC
");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse:collapse;margin:10px 0;'>";
    echo "<tr><th>ID</th><th>Announcement ID</th><th>Tenant ID</th><th>Message Preview</th><th>Created At</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['announcement_id']}</td>";
        echo "<td>{$row['tenant_id']}</td>";
        echo "<td>" . htmlspecialchars($row['short_message']) . "...</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No announcement responses found.<br>";
}

echo "<br>✅ Announcement responses migration complete!";
$conn->close();
?>
