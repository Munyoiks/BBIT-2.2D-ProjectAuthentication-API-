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

// Create maintenance_requests table with the exact structure
$sql = "
CREATE TABLE IF NOT EXISTS maintenance_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT,
    issue TEXT,
    status ENUM('Pending', 'Resolved') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($createTableSQL)) {
    error_log("Error creating table 'maintenance_requests': " . $conn->error);
}

// ---- Add missing columns if table already exists ----
$result = $conn->query("DESCRIBE maintenance_requests");
if ($result) {
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = true;
    }

    if (!isset($existingColumns['tenant_id'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN tenant_id INT");
    }
    if (!isset($existingColumns['issue'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN issue TEXT");
    }
    if (!isset($existingColumns['status'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN status ENUM('Pending', 'Resolved') DEFAULT 'Pending'");
    }
    if (!isset($existingColumns['created_at'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }

    // Add foreign key if missing
    $fkCheck = $conn->query("
        SELECT COUNT(*) as fk_exists 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE CONSTRAINT_SCHEMA = '$dbname' 
        AND TABLE_NAME = 'maintenance_requests' 
        AND CONSTRAINT_NAME = 'maintenance_requests_ibfk_1'
    ");
    if ($fkCheck && $fkCheck->fetch_assoc()['fk_exists'] == 0) {
        $conn->query("ALTER TABLE maintenance_requests ADD FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE");
    }
}

// ---- Insert sample maintenance requests if none exist ----
$checkRequests = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests");
$requestCount = $checkRequests ? (int)$checkRequests->fetch_assoc()['count'] : 0;

if ($requestCount === 0) {
    // Fetch up to 5 tenant users
    $sampleUsers = $conn->query("SELECT id, full_name FROM users WHERE role = 'tenant' LIMIT 5");
    $users = [];
    if ($sampleUsers && $sampleUsers->num_rows > 0) {
        while ($user = $sampleUsers->fetch_assoc()) {
            $users[] = $user;
        }

        // Sample maintenance requests
        $sampleRequests = [
            ['issue' => 'Kitchen sink is leaking under cabinet.', 'status' => 'Pending'],
            ['issue' => 'Air conditioning not cooling properly.', 'status' => 'Resolved'],
            ['issue' => 'Bathroom toilet keeps running after flushing.', 'status' => 'Pending'],
            ['issue' => 'Bedroom window won’t close properly.', 'status' => 'Pending'],
            ['issue' => 'Electrical outlet sparks in living room.', 'status' => 'Resolved'],
            ['issue' => 'Garbage disposal making loud grinding noise.', 'status' => 'Pending'],
            ['issue' => 'Balcony door difficult to open/close.', 'status' => 'Resolved'],
            ['issue' => 'Weak water pressure in shower.', 'status' => 'Pending'],
            ['issue' => 'Loose carpet in hallway causing hazard.', 'status' => 'Pending'],
            ['issue' => 'Smoke detector beeping frequently.', 'status' => 'Resolved'],
        ];

        // Insert sample requests safely
        $insertCount = 0;
        foreach ($sampleRequests as $i => $request) {
            $userIndex = $i % count($users); // cycle through tenant users
            $tenant_id = $users[$userIndex]['id'];
            $stmt = $conn->prepare("INSERT INTO maintenance_requests (tenant_id, issue, status) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $tenant_id, $request['issue'], $request['status']);
            if ($stmt->execute()) $insertCount++;
            $stmt->close();
        }
    } else {
        echo "No tenant users found. Please create tenant accounts first.<br>";
    }
}

// ---- Display current maintenance requests ----
$requestsResult = $conn->query("
    SELECT mr.id, u.full_name AS tenant_name, mr.issue, mr.status, mr.created_at
    FROM maintenance_requests mr
    LEFT JOIN users u ON mr.tenant_id = u.id
    ORDER BY mr.created_at DESC
");

if ($requestsResult && $requestsResult->num_rows > 0) {
    foreach ($requestsResult as $request) {
        $statusColor = $request['status'] === 'Resolved' ? '#4CAF50' : '#FF9800';
        $backgroundColor = $request['status'] === 'Resolved' ? '#f0f8f0' : '#fff8f0';

        echo "<div style='border: 2px solid $statusColor; padding: 15px; margin: 10px 0; border-radius: 8px; background-color: $backgroundColor;'>";
        echo "<div style='display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;'>";
        echo "<div><h3 style='margin: 0 0 5px 0; color: #333;'>Request #{$request['id']}</h3>";
        echo "<p style='margin: 0; color: #666;'><strong>Tenant:</strong> {$request['tenant_name']}</p></div>";
        echo "<span style='background-color: $statusColor; color: white; padding: 6px 12px; border-radius: 15px; font-weight: bold;'>{$request['status']}</span>";
        echo "</div>";
        echo "<p style='margin: 10px 0; color: #555; line-height: 1.5; background-color: white; padding: 10px; border-radius: 4px;'>";
        echo nl2br(htmlspecialchars($request['issue']));
        echo "</p>";
        echo "<small style='color: #999;'>Submitted: " . date('F j, Y g:i A', strtotime($request['created_at'])) . "</small>";
        echo "</div>";
    }
} else {
    echo "No maintenance requests found.<br>";
}

// ---- Show statistics ----
$statsResult = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count,
        MIN(created_at) AS oldest,
        MAX(created_at) AS newest
    FROM maintenance_requests
");

if ($statsResult && $statsRow = $statsResult->fetch_assoc()) {
    $resolvedPercentage = $statsRow['total'] > 0 ? round(($statsRow['resolved_count'] / $statsRow['total']) * 100) : 0;
    echo "<br>Maintenance Requests Statistics:<br>";
    echo "- Total Requests: {$statsRow['total']}<br>";
    echo "- Pending: {$statsRow['pending_count']}<br>";
    echo "- Resolved: {$statsRow['resolved_count']}<br>";
    echo "- Resolution Rate: {$resolvedPercentage}%<br>";
    if ($statsRow['oldest']) echo "- Oldest Request: " . date('F j, Y', strtotime($statsRow['oldest'])) . "<br>";
    if ($statsRow['newest']) echo "- Newest Request: " . date('F j, Y', strtotime($statsRow['newest'])) . "<br>";
}

echo "<br>Maintenance requests migration complete!";
$conn->close();
?>
