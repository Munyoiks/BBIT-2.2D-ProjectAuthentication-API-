<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Database connection (add your database credentials)
$host = 'localhost';
$dbname = 'your_database_name';
$username = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch user data from database
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $full_name = $user['full_name'];
        $email = $user['email'];
        
        // Update session with latest data
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
    } else {
        // Fallback if user not found
        $full_name = $_SESSION['full_name'] ?? 'Tenant';
        $email = $_SESSION['email'] ?? '';
    }
} catch (PDOException $e) {
    // Fallback to session data if database connection fails
    $full_name = $_SESSION['full_name'] ?? 'Tenant';
    $email = $_SESSION['email'] ?? '';
    error_log("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mojo Tenant System | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f8;
        }
        .sidebar {
            height: 100vh;
            background: #0d6efd;
            color: white;
            padding: 20px;
            position: fixed;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 8px;
        }
        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .content {
            margin-left: 220px;
            padding: 40px;
        }
        .card {
            border-radius: 10px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h4>🏠 Mojo Tenant</h4>
    <p>Welcome, <strong><?= htmlspecialchars($full_name) ?></strong></p>
    <hr>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="profile.php">👤 Profile</a>
    <a href="rent.php">💰 Rent & Payments</a>
    <a href="maintenance.php">🛠️ Maintenance</a>
    <a href="announcements.php">📢 Announcements</a>
    <a href="messages.php">💬 Messages</a>
    <a href="settings.php">⚙️ Settings</a>
    <a href="logout.php" class="text-danger">🚪 Logout</a>
</div>

<div class="content">
    <h2 class="mb-4">Dashboard Overview</h2>
    
    <!-- Welcome message with user's name -->
    <div class="alert alert-info mb-4">
        <h4>Welcome back, <?= htmlspecialchars($full_name) ?>! 👋</h4>
        <p class="mb-0">Here's your current tenant dashboard overview.</p>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card p-3 shadow-sm">
                <h5>Next Rent Due</h5>
                <p>October 30, 2025</p>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card p-3 shadow-sm">
                <h5>Maintenance Requests</h5>
                <p>2 active issues</p>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card p-3 shadow-sm">
                <h5>Unread Messages</h5>
                <p>1 new message</p>
            </div>
        </div>
    </div>

    <div class="card p-4 mt-4 shadow-sm">
        <h5>Latest Announcement</h5>
        <p><strong>Notice:</strong> Water supply will be temporarily off on Friday from 8 AM to 12 PM for maintenance.</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>