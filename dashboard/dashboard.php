<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Database connection (add your database credentials)
$host = 'localhost';
$dbname = 'auth_db';
$username = 'root';
$password = 'munyoiks7';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mojo Tenant System | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0d6efd;
            --primary-dark: #0b5ed7;
            --secondary: #6c757d;
            --success: #198754;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 280px;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        .sidebar {
            height: 100vh;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0;
            position: fixed;
            width: var(--sidebar-width);
            box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 700;
        }

        .user-info {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }

        .user-info p {
            margin: 0;
        }

        .sidebar-nav {
            padding: 15px 0;
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar-nav a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px 20px;
            margin: 3px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            flex-shrink: 0;
        }

        .sidebar-nav a i {
            width: 24px;
            margin-right: 12px;
            text-align: center;
            font-size: 1.1rem;
        }

        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.2);
        }

        .sidebar-nav a.logout {
            color: #ff6b6b;
            margin-top: auto;
            margin-bottom: 20px;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            padding: 1.5rem;
        }

        .stat-card {
            border-left: 4px solid var(--primary);
        }

        .stat-card.warning {
            border-left-color: var(--warning);
        }

        .stat-card.success {
            border-left-color: var(--success);
        }

        .stat-card.danger {
            border-left-color: var(--danger);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .stat-card.warning .stat-icon {
            color: var(--warning);
        }

        .stat-card.success .stat-icon {
            color: var(--success);
        }

        .stat-card.danger .stat-icon {
            color: var(--danger);
        }

        .welcome-card {
            background: linear-gradient(120deg, #e0f2ff, #f0f8ff);
            border: none;
            border-radius: 12px;
            padding: 25px;
        }

        /* Custom scrollbar for sidebar */
        .sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .content {
                margin-left: 0;
            }

            .sidebar-nav {
                max-height: 300px;
            }
        }

        /* Ensure content area is scrollable if needed */
        .content {
            overflow-y: auto;
        }

        /* Compact menu items */
        .sidebar-nav a {
            min-height: 44px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-home me-2"></i>Mojo Tenant</h4>
    </div>
    
    <div class="user-info">
        <p>Welcome, <strong><?= htmlspecialchars($full_name) ?></strong></p>
        <small class="text-light"><?= htmlspecialchars($email) ?></small>
    </div>
    
    <div class="sidebar-nav">
        <a href="dashboard.php" class="active">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="profile.php">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="rent.php">
            <i class="fas fa-money-bill-wave"></i> Rent & Payments
        </a>
        <a href="maintenance.php">
            <i class="fas fa-tools"></i> Maintenance
        </a>
        <a href="announcements.php">
            <i class="fas fa-bullhorn"></i> Announcements
        </a>
        <a href="messages.php">
            <i class="fas fa-comments"></i> Messages
        </a>
        <a href="settings.php">
            <i class="fas fa-cog"></i> Settings
        </a>
        <a href="logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<div class="content">
    <div class="dashboard-header">
        <h2 class="mb-2">Dashboard Overview</h2>
        <p class="text-muted">Welcome to your tenant dashboard</p>
    </div>
    
    <!-- Welcome message with user's name -->
    <div class="card welcome-card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <h4>Welcome back, <?= htmlspecialchars($full_name) ?>! 👋</h4>
                    <p class="mb-0 text-muted">Here's your current tenant dashboard overview.</p>
                </div>
                <div class="flex-shrink-0">
                    <i class="fas fa-home fa-3x text-primary opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <h5>Next Rent Due</h5>
                    <p class="text-muted">October 30, 2025</p>
                    <div class="progress mb-2" style="height: 6px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted">5 days remaining</small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card warning">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h5>Maintenance Requests</h5>
                    <p class="text-muted">2 active issues</p>
                    <div class="d-flex justify-content-between">
                        <span class="badge bg-warning">1 pending</span>
                        <span class="badge bg-info">1 in progress</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card danger">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h5>Unread Messages</h5>
                    <p class="text-muted">1 new message</p>
                    <button class="btn btn-sm btn-outline-danger">View Messages</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Latest Announcement</h5>
                    <div class="alert alert-info">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle fa-2x"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6>Water Maintenance Notice</h6>
                                <p class="mb-0">Water supply will be temporarily off on Friday from 8 AM to 12 PM for maintenance. Please store water accordingly.</p>
                                <small class="text-muted">Posted: October 25, 2025</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary">
                            <i class="fas fa-money-bill-wave me-2"></i> Pay Rent
                        </button>
                        <button class="btn btn-outline-primary">
                            <i class="fas fa-tools me-2"></i> Request Maintenance
                        </button>
                        <button class="btn btn-outline-primary">
                            <i class="fas fa-envelope me-2"></i> Contact Landlord
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Simple script to handle sidebar scrolling and ensure logout button is always visible
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarNav = document.querySelector('.sidebar-nav');
        const logoutLink = document.querySelector('.sidebar-nav a.logout');
        
        // Ensure logout button stays at the bottom
        if (sidebarNav && logoutLink) {
            sidebarNav.appendChild(logoutLink);
        }
        
        // Add smooth scrolling to content area if needed
        const contentArea = document.querySelector('.content');
        if (contentArea.scrollHeight > contentArea.clientHeight) {
            contentArea.style.overflowY = 'auto';
        }
    });
</script>
</body>
</html>