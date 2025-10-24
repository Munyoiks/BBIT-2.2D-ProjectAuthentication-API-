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
    if (!isset($existingColumns['tenant_id'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN tenant_id INT");
        echo "Added missing column: tenant_id<br>";
    }

    if (!isset($existingColumns['issue'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN issue TEXT");
        echo "Added missing column: issue<br>";
    }

    if (!isset($existingColumns['status'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN status ENUM('Pending', 'Resolved') DEFAULT 'Pending'");
        echo "Added missing column: status<br>";
    }

    if (!isset($existingColumns['created_at'])) {
        $conn->query("ALTER TABLE maintenance_requests ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added missing column: created_at<br>";
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
        $conn->query("ALTER TABLE maintenance_requests ADD FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "Added foreign key constraint: tenant_id -> users(id)<br>";
    }
} else {
    echo "Error describing maintenance_requests table: " . $conn->error . "<br>";
}

// Add sample maintenance request data for testing (only if no requests exist)
$checkRequests = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests");
if ($checkRequests) {
    $requestCount = $checkRequests->fetch_assoc()['count'];
    
    if ($requestCount == 0) {
        // Get sample users to associate with maintenance requests
        $sampleUsers = $conn->query("SELECT id, name FROM users WHERE role = 'tenant' LIMIT 5");
        $users = [];
        
        if ($sampleUsers && $sampleUsers->num_rows > 0) {
            while ($user = $sampleUsers->fetch_assoc()) {
                $users[] = $user;
            }
            
            // Insert sample maintenance requests
            $sampleRequests = [
                [
                    'tenant_id' => $users[0]['id'],
                    'issue' => 'Kitchen sink is leaking and water is pooling under the cabinet. The dripping has been constant for 2 days now.',
                    'status' => 'Pending'
                ],
                [
                    'tenant_id' => $users[1]['id'],
                    'issue' => 'Air conditioning unit not cooling properly. The temperature stays at 78°F even when set to 68°F.',
                    'status' => 'Resolved'
                ],
                [
                    'tenant_id' => $users[2]['id'],
                    'issue' => 'Bathroom toilet keeps running after flushing. Have to jiggle the handle to make it stop.',
                    'status' => 'Pending'
                ],
                [
                    'tenant_id' => $users[0]['id'],
                    'issue' => 'Bedroom window won\'t close properly. There\'s a draft coming in and security concern.',
                    'status' => 'Pending'
                ],
                [
                    'tenant_id' => $users[3]['id'],
                    'issue' => 'Electrical outlet in living room sparks when plugging in devices. Concerned about fire hazard.',
                    'status' => 'Resolved'
                ],
                [
                    'tenant_id' => $users[4]['id'],
                    'issue' => 'Garbage disposal making loud grinding noise and not working. Smell coming from sink drain.',
                    'status' => 'Pending'
                ],
                [
                    'tenant_id' => $users[1]['id'],
                    'issue' => 'Balcony door difficult to open and close. Seems to be misaligned with the frame.',
                    'status' => 'Resolved'
                ],
                [
                    'tenant_id' => $users[2]['id'],
                    'issue' => 'Water pressure in shower very weak. Takes much longer to rinse shampoo from hair.',
                    'status' => 'Pending'
                ],
                [
                    'tenant_id' => $users[3]['id'],
                    'issue' => 'Carpet in hallway has loose area that poses tripping hazard. Needs to be re-stretched or replaced.',
                    'status' => 'Pending'
                ],
                [
                    'tenant_id' => $users[4]['id'],
                    'issue' => 'Smoke detector in hallway beeps every 30 seconds. Probably needs battery replacement.',
                    'status' => 'Resolved'
                ]
            ];
            
            $insertCount = 0;
            foreach ($sampleRequests as $request) {
                $stmt = $conn->prepare("
                    INSERT INTO maintenance_requests (tenant_id, issue, status)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param(
                    "iss", 
                    $request['tenant_id'],
                    $request['issue'],
                    $request['status']
                );
                
                if ($stmt->execute()) {
                    $insertCount++;
                }
                $stmt->close();
            }
            
            echo "Added $insertCount sample maintenance request records.<br>";
        } else {
            echo "No tenant users found. Please run users migration first or create tenant accounts.<br>";
        }
    } else {
        echo "Maintenance requests already exist in the database ($requestCount records).<br>";
    }
}

// Display current maintenance requests for verification
echo "<br>Current maintenance requests in database:<br>";
$requestsResult = $conn->query("
    SELECT mr.id, u.name as tenant_name, mr.issue, mr.status, mr.created_at 
    FROM maintenance_requests mr 
    LEFT JOIN users u ON mr.tenant_id = u.id 
    ORDER BY mr.created_at DESC
");

if ($requestsResult && $requestsResult->num_rows > 0) {
    echo "<div style='margin: 20px 0;'>";
    while ($request = $requestsResult->fetch_assoc()) {
        $statusColor = $request['status'] === 'Resolved' ? '#4CAF50' : '#FF9800';
        $backgroundColor = $request['status'] === 'Resolved' ? '#f0f8f0' : '#fff8f0';
        
        echo "<div style='border: 2px solid $statusColor; padding: 15px; margin: 10px 0; border-radius: 8px; background-color: $backgroundColor;'>";
        echo "<div style='display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;'>";
        echo "<div>";
        echo "<h3 style='margin: 0 0 5px 0; color: #333;'>Request #{$request['id']}</h3>";
        echo "<p style='margin: 0; color: #666;'><strong>Tenant:</strong> {$request['tenant_name']}</p>";
        echo "</div>";
        echo "<span style='background-color: $statusColor; color: white; padding: 6px 12px; border-radius: 15px; font-weight: bold;'>";
        echo $request['status'];
        echo "</span>";
        echo "</div>";
        echo "<p style='margin: 10px 0; color: #555; line-height: 1.5; background-color: white; padding: 10px; border-radius: 4px;'>";
        echo nl2br(htmlspecialchars($request['issue']));
        echo "</p>";
        echo "<small style='color: #999;'>Submitted: " . date('F j, Y g:i A', strtotime($request['created_at'])) . "</small>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "No maintenance requests found in database.<br>";
}

// Show statistics
$statsResult = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved_count,
        MIN(created_at) as oldest,
        MAX(created_at) as newest
    FROM maintenance_requests
");

if ($statsResult && $statsRow = $statsResult->fetch_assoc()) {
    $resolvedPercentage = $statsRow['total'] > 0 ? round(($statsRow['resolved_count'] / $statsRow['total']) * 100) : 0;
    
    echo "<br>Maintenance Requests Statistics:<br>";
    echo "- Total Requests: {$statsRow['total']}<br>";
    echo "- Pending: {$statsRow['pending_count']}<br>";
    echo "- Resolved: {$statsRow['resolved_count']}<br>";
    echo "- Resolution Rate: {$resolvedPercentage}%<br>";
    
    if ($statsRow['oldest']) {
        echo "- Oldest Request: " . date('F j, Y', strtotime($statsRow['oldest'])) . "<br>";
    }
    if ($statsRow['newest']) {
        echo "- Newest Request: " . date('F j, Y', strtotime($statsRow['newest'])) . "<br>";
    }
}

echo "<br>Maintenance requests migration complete!";
$conn->close();
?>