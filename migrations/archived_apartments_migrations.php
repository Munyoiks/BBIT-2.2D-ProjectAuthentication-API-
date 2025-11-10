<?php
// archived_apartments_migration.php

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
    echo "Database '$dbname' created or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

//  Create archived_apartments table
$sql = "
CREATE TABLE IF NOT EXISTS archived_apartments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    apartment_number VARCHAR(20) NOT NULL UNIQUE,
    building_name VARCHAR(100) DEFAULT NULL,
    rent_amount DECIMAL(10,2) NOT NULL,
    is_occupied TINYINT(1) DEFAULT 0,
    tenant_id INT(11) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    archived_by INT(11) DEFAULT NULL,
    INDEX (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo " Table 'archived_apartments' created or already exists.<br>";
} else {
    echo " Error creating 'archived_apartments' table: " . $conn->error . "<br>";
}

// Check if table structure matches expected schema
$result = $conn->query("DESCRIBE archived_apartments");
if ($result) {
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = true;
    }

    $columnsToAdd = [
        'apartment_number' => "ALTER TABLE archived_apartments ADD COLUMN apartment_number VARCHAR(20) NOT NULL UNIQUE AFTER id",
        'building_name' => "ALTER TABLE archived_apartments ADD COLUMN building_name VARCHAR(100) DEFAULT NULL AFTER apartment_number",
        'rent_amount' => "ALTER TABLE archived_apartments ADD COLUMN rent_amount DECIMAL(10,2) NOT NULL AFTER building_name",
        'is_occupied' => "ALTER TABLE archived_apartments ADD COLUMN is_occupied TINYINT(1) DEFAULT 0 AFTER rent_amount",
        'tenant_id' => "ALTER TABLE archived_apartments ADD COLUMN tenant_id INT(11) DEFAULT NULL AFTER is_occupied",
        'created_at' => "ALTER TABLE archived_apartments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER tenant_id",
        'deleted_at' => "ALTER TABLE archived_apartments ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER created_at",
        'archived_by' => "ALTER TABLE archived_apartments ADD COLUMN archived_by INT(11) DEFAULT NULL AFTER deleted_at"
    ];

    foreach ($columnsToAdd as $col => $query) {
        if (!isset($existingColumns[$col])) {
            $conn->query($query);
            echo "🛠 Added missing column: $col<br>";
        }
    }
} else {
    echo " Error describing 'archived_apartments' table: " . $conn->error . "<br>";
}

// Insert sample archived apartment records if none exist
$check = $conn->query("SELECT COUNT(*) AS count FROM archived_apartments");
if ($check) {
    $count = (int)$check->fetch_assoc()['count'];
    if ($count === 0) {
        $sampleData = [
            [
                'apartment_number' => 'A-101',
                'building_name' => 'Sunset Tower',
                'rent_amount' => 25000.00,
                'is_occupied' => 0,
                'tenant_id' => null,
                'deleted_at' => '2025-10-10 14:30:00',
                'archived_by' => 1
            ],
            [
                'apartment_number' => 'B-203',
                'building_name' => 'Palm Residences',
                'rent_amount' => 30000.00,
                'is_occupied' => 0,
                'tenant_id' => null,
                'deleted_at' => '2025-09-28 09:45:00',
                'archived_by' => 2
            ],
            [
                'apartment_number' => 'C-310',
                'building_name' => 'Blue Sky Apartments',
                'rent_amount' => 28000.00,
                'is_occupied' => 0,
                'tenant_id' => 105,
                'deleted_at' => '2025-09-15 17:20:00',
                'archived_by' => 1
            ]
        ];

        $stmt = $conn->prepare("
            INSERT INTO archived_apartments 
            (apartment_number, building_name, rent_amount, is_occupied, tenant_id, deleted_at, archived_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($sampleData as $row) {
            $stmt->bind_param(
                "ssdii si",
                $row['apartment_number'],
                $row['building_name'],
                $row['rent_amount'],
                $row['is_occupied'],
                $row['tenant_id'],
                $row['deleted_at'],
                $row['archived_by']
            );
            $stmt->execute();
        }

        $stmt->close();
        echo " Added sample archived apartments.<br>";
    } else {
        echo " Archived apartments already exist ($count record(s)).<br>";
    }
} else {
    echo " Error checking 'archived_apartments' table: " . $conn->error . "<br>";
}

//  Display current archived apartments
echo "<br>Current Archived Apartments:<br>";
$result = $conn->query("
    SELECT id, apartment_number, building_name, rent_amount, is_occupied, deleted_at, archived_by
    FROM archived_apartments
    ORDER BY deleted_at DESC
");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse:collapse;margin:10px 0;'>";
    echo "<tr><th>ID</th><th>Apartment No.</th><th>Building</th><th>Rent (KSh)</th><th>Occupied</th><th>Deleted At</th><th>Archived By</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['apartment_number']}</td>";
        echo "<td>{$row['building_name']}</td>";
        echo "<td>{$row['rent_amount']}</td>";
        echo "<td>" . ($row['is_occupied'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$row['deleted_at']}</td>";
        echo "<td>{$row['archived_by']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No archived apartments found.<br>";
}

echo "<br> Archived apartments migration complete!";
$conn->close();
?>
