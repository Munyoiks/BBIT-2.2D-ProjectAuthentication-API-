<?php
// maintenance_requests_migration.php

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

// Create `maintenance_requests` table with the exact structure
$sql = "
CREATE TABLE IF NOT EXISTS maintenance_requests (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    urgency ENUM('low','medium','high') DEFAULT 'medium',
    status ENUM('pending','in_progress','completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_urgency (urgency),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "Table 'maintenance_requests' created or already exists.<br>";
} else {
    echo "Error creating maintenance_requests table: " . $conn->error . "<br>";
}

// Check if we need to alter the table structure (for existing tables)
$result = $conn->query("DESCRIBE maintenance_requests");
if ($result) {
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = true;
    }

    // Add missing columns if they don't exist
    if (!isset($existingColumns['urgency'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN urgency ENUM('low','medium','high') DEFAULT 'medium'");
        echo "Added missing column: urgency<br>";
    }

    if (!isset($existingColumns['status'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN status ENUM('pending','in_progress','completed') DEFAULT 'pending'");
        echo "Added missing column: status<br>";
    }

    if (!isset($existingColumns['updated_at'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "Added missing column: updated_at<br>";
    }

    // Add foreign key constraint if it doesn't exist
    $fkCheck = $conn->query("
        SELECT COUNT(*) as fk_exists 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE CONSTRAINT_SCHEMA = '$dbname' 
        AND TABLE_NAME = 'maintenance_requests' 
        AND CONSTRAINT_NAME = 'maintenance_requests_ibfk_1'
    ");
    
    if ($fkCheck && $fkCheck->fetch_assoc()['fk_exists'] == 0) {
        $conn->query("ALTER TABLE maintenance_requests ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "Added foreign key constraint: user_id -> users(id)<br>";
    }

    // Add indexes if they don't exist
    $indexes = [
        'idx_user_id' => 'user_id',
        'idx_status' => 'status',
        'idx_urgency' => 'urgency',
        'idx_created_at' => 'created_at'
    ];

    foreach ($indexes as $indexName => $column) {
        $indexCheck = $conn->query("SHOW INDEX FROM maintenance_requests WHERE Key_name = '$indexName'");
        if ($indexCheck->num_rows == 0) {
            $conn->query("CREATE INDEX $indexName ON maintenance_requests ($column)");
            echo "Added index: $indexName<br>";
        }
    }
} else {
    echo "Error describing maintenance_requests table: " . $conn->error . "<br>";
}

// Add sample maintenance requests for testing (only if none exist)
$checkRequests = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests");
if ($checkRequests) {
    $requestCount = $checkRequests->fetch_assoc()['count'];
    
    if ($requestCount == 0) {
        // Get a sample user to associate with maintenance requests
        $sampleUser = $conn->query("SELECT id FROM users WHERE email = 'tenant@example.com' LIMIT 1");
        
        if ($sampleUser && $sampleUser->num_rows > 0) {
            $user = $sampleUser->fetch_assoc();
            $user_id = $user['id'];
            
            // Insert sample maintenance requests
            $sampleRequests = [
                [
                    'user_id' => $user_id,
                    'title' => 'Leaking Kitchen Faucet',
                    'description' => 'The kitchen faucet has been leaking constantly for the past 2 days. It drips about once every second, even when fully turned off.',
                    'urgency' => 'medium',
                    'status' => 'pending'
                ],
                [
                    'user_id' => $user_id,
                    'title' => 'Broken Air Conditioner',
                    'description' => 'The AC unit in the living room is not cooling properly. It makes strange noises when turned on and only blows warm air.',
                    'urgency' => 'high',
                    'status' => 'in_progress'
                ],
                [
                    'user_id' => $user_id,
                    'title' => 'Stuck Bedroom Window',
                    'description' => 'The bedroom window is stuck and cannot be opened. It seems to be jammed in the frame.',
                    'urgency' => 'low',
                    'status' => 'completed'
                ],
                [
                    'user_id' => $user_id,
                    'title' => 'Electrical Outlet Not Working',
                    'description' => 'The electrical outlet near the kitchen counter stopped working yesterday. No power in any of the two sockets.',
                    'urgency' => 'high',
                    'status' => 'pending'
                ]
            ];
            
            $insertCount = 0;
            foreach ($sampleRequests as $request) {
                $stmt = $conn->prepare("
                    INSERT INTO maintenance_requests (user_id, title, description, urgency, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "issss", 
                    $request['user_id'],
                    $request['title'],
                    $request['description'],
                    $request['urgency'],
                    $request['status']
                );
                
                if ($stmt->execute()) {
                    $insertCount++;
                }
                $stmt->close();
            }
            
            echo "Added $insertCount sample maintenance request records.<br>";
        } else {
            echo "No sample user found. Please run users migration first.<br>";
        }
    } else {
        echo "Maintenance requests already exist in the database ($requestCount records).<br>";
    }
}

// Display sample data for verification
echo "<br><strong>Sample Maintenance Requests:</strong><br>";
$sampleData = $conn->query("
    SELECT mr.id, u.email, mr.title, mr.urgency, mr.status, mr.created_at 
    FROM maintenance_requests mr 
    JOIN users u ON mr.user_id = u.id 
    LIMIT 5
");

if ($sampleData && $sampleData->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>User</th><th>Title</th><th>Urgency</th><th>Status</th><th>Created</th></tr>";
    while ($row = $sampleData->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . $row['urgency'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<br>Maintenance requests migration complete!";
$conn->close();
?>