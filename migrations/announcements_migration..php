<?php
// announcements_migration.php

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

// Create announcements table with the exact structure
$sql = "
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "Table 'announcements' created or already exists.<br>";
} else {
    echo "Error creating announcements table: " . $conn->error . "<br>";
}

// Check if we need to alter the table structure (for existing tables)
$result = $conn->query("DESCRIBE announcements");
if ($result) {
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = true;
    }

    // Add missing columns if they don't exist
    if (!isset($existingColumns['title'])) {
        $conn->query("ALTER TABLE announcements ADD COLUMN title VARCHAR(255) NOT NULL");
        echo "Added missing column: title<br>";
    }

    if (!isset($existingColumns['message'])) {
        $conn->query("ALTER TABLE announcements ADD COLUMN message TEXT NOT NULL");
        echo "Added missing column: message<br>";
    }

    if (!isset($existingColumns['created_at'])) {
        $conn->query("ALTER TABLE announcements ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added missing column: created_at<br>";
    }
} else {
    echo "Error describing announcements table: " . $conn->error . "<br>";
}

// Add sample announcement data for testing (only if no announcements exist)
$checkAnnouncements = $conn->query("SELECT COUNT(*) as count FROM announcements");
if ($checkAnnouncements) {
    $announcementCount = $checkAnnouncements->fetch_assoc()['count'];
    
    if ($announcementCount == 0) {
        // Insert sample announcements
        $sampleAnnouncements = [
            [
                'title' => 'Welcome to Our Apartment Community!',
                'message' => 'We would like to extend a warm welcome to all our new and existing residents. Our community is growing, and we\'re excited to have you here. Please remember to follow the community guidelines and be respectful of your neighbors.'
            ],
            [
                'title' => 'Maintenance Schedule Update',
                'message' => 'Scheduled maintenance for the building elevators will take place next Tuesday from 9:00 AM to 3:00 PM. During this time, elevator services will be temporarily unavailable. We apologize for any inconvenience and appreciate your understanding.'
            ],
            [
                'title' => 'Rent Payment Reminder',
                'message' => 'This is a friendly reminder that rent payments are due by the 5th of every month. Late payments will incur a penalty fee as per your rental agreement. You can make payments through the online portal or at the management office.'
            ],
            [
                'title' => 'Community Clean-up Day',
                'message' => 'Join us for our quarterly community clean-up day this Saturday from 8:00 AM to 12:00 PM. We\'ll be cleaning common areas, the garden, and the parking lot. Refreshments will be provided for all volunteers!'
            ],
            [
                'title' => 'Parking Lot Repairs',
                'message' => 'Important: The west parking lot will be closed for repairs from Monday to Friday next week. Please use the east parking lot during this period. Vehicles left in the west lot will be towed at the owner\'s expense.'
            ],
            [
                'title' => 'New Security Measures',
                'message' => 'To enhance security in our building, we have installed additional CCTV cameras in common areas and upgraded the access control system. All residents will receive new access cards by the end of the week. Please visit the management office to collect yours.'
            ],
            [
                'title' => 'Utility Bill Update',
                'message' => 'Please be informed that water and electricity bills for the previous month are now available for viewing in your tenant portal. Payments are due within 15 days. Contact management if you have any questions about your bill.'
            ],
            [
                'title' => 'Fire Safety Drill',
                'message' => 'A mandatory fire safety drill will be conducted next Wednesday at 10:00 AM. All residents are required to participate. Please follow instructions from building management and emergency personnel during the drill.'
            ],
            [
                'title' => 'Gym Equipment Maintenance',
                'message' => 'The gym will be closed for equipment maintenance and upgrades from Thursday to Sunday this week. We apologize for the inconvenience and look forward to providing you with better facilities when we reopen.'
            ],
            [
                'title' => 'Holiday Season Notice',
                'message' => 'As we approach the holiday season, please be mindful of noise levels, especially during evening hours. Also, remember that decorations in common areas must be approved by management. Wishing everyone a joyful holiday season!'
            ]
        ];
        
        $insertCount = 0;
        foreach ($sampleAnnouncements as $announcement) {
            $stmt = $conn->prepare("
                INSERT INTO announcements (title, message)
                VALUES (?, ?)
            ");
            $stmt->bind_param(
                "ss", 
                $announcement['title'],
                $announcement['message']
            );
            
            if ($stmt->execute()) {
                $insertCount++;
            }
            $stmt->close();
        }
        
        echo "Added $insertCount sample announcement records.<br>";
    } else {
        echo "Announcements already exist in the database ($announcementCount records).<br>";
    }
}

// Display current announcements for verification
echo "<br>Current announcements in database:<br>";
$announcementsResult = $conn->query("SELECT id, title, message, created_at FROM announcements ORDER BY created_at DESC");
if ($announcementsResult && $announcementsResult->num_rows > 0) {
    echo "<div style='margin: 20px 0;'>";
    while ($announcement = $announcementsResult->fetch_assoc()) {
        echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; background-color: #f9f9f9;'>";
        echo "<h3 style='margin: 0 0 10px 0; color: #333;'>{$announcement['title']}</h3>";
        echo "<p style='margin: 0 0 10px 0; color: #666; line-height: 1.5;'>{$announcement['message']}</p>";
        echo "<small style='color: #999;'>Posted on: " . date('F j, Y g:i A', strtotime($announcement['created_at'])) . "</small>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "No announcements found in database.<br>";
}

// Show statistics
$statsResult = $conn->query("
    SELECT 
        COUNT(*) as total,
        MIN(created_at) as oldest,
        MAX(created_at) as newest
    FROM announcements
");

if ($statsResult && $statsRow = $statsResult->fetch_assoc()) {
    echo "<br>Announcements Statistics:<br>";
    echo "- Total Announcements: {$statsRow['total']}<br>";
    if ($statsRow['oldest']) {
        echo "- Oldest Announcement: " . date('F j, Y', strtotime($statsRow['oldest'])) . "<br>";
    }
    if ($statsRow['newest']) {
        echo "- Newest Announcement: " . date('F j, Y', strtotime($statsRow['newest'])) . "<br>";
    }
}

echo "<br>Announcements migration complete!";
$conn->close();
?>