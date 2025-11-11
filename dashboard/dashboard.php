<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'auth_db';
$username = 'root';
$password = 'munyoiks7';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch user data from database including unit information
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT full_name, email, unit_number, unit_role, is_primary_tenant FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $full_name = $user['full_name'];
        $email = $user['email'];
        $unit_number = $user['unit_number'];
        $unit_role = $user['unit_role'];
        $is_primary_tenant = $user['is_primary_tenant'];
        
        // Update session with latest data
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        $_SESSION['unit_number'] = $unit_number;
        $_SESSION['unit_role'] = $unit_role;
        $_SESSION['is_primary_tenant'] = $is_primary_tenant;
    } else {
        // Fallback if user not found
        $full_name = $_SESSION['full_name'] ?? 'Tenant';
        $email = $_SESSION['email'] ?? '';

        $unit_number = $_SESSION['unit_number'] ?? 'Not assigned';
    }

// Get unread notifications for the tenant
try {
    $notifications_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $notifications_stmt->execute([$user_id]);
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
    $unread_notification_count = count($notifications);
} catch (PDOException $e) {
    $notifications = [];
    $unread_notification_count = 0;
    error_log("Notifications error: " . $e->getMessage());
}

    $unit_role = $_SESSION['unit_role'] ?? 'primary';
    $is_primary_tenant = $_SESSION['is_primary_tenant'] ?? 0;

    // Get unit occupants count and list
    $occupants_stmt = $pdo->prepare("SELECT full_name, email, unit_role, is_primary_tenant FROM users WHERE unit_number = ? AND is_verified = 1 ORDER BY is_primary_tenant DESC, full_name");
    $occupants_stmt->execute([$unit_number]);
    $occupants = $occupants_stmt->fetchAll(PDO::FETCH_ASSOC);
    $occupant_count = count($occupants);

    // Get maintenance requests count for this unit
    $maintenance_stmt = $pdo->prepare("SELECT COUNT(*) as maintenance_count FROM maintenance_requests WHERE unit_number = ? AND status = 'Pending'");
    $maintenance_stmt->execute([$unit_number]);
    $maintenance_data = $maintenance_stmt->fetch(PDO::FETCH_ASSOC);
    $maintenance_count = $maintenance_data['maintenance_count'];

    // Get unread messages count
    $messages_stmt = $pdo->prepare("SELECT COUNT(*) as message_count FROM admin_notifications WHERE unit_number = ? AND is_read = 0");
    $messages_stmt->execute([$unit_number]);
    $messages_data = $messages_stmt->fetch(PDO::FETCH_ASSOC);
    $message_count = $messages_data['message_count'] ?? 0;

} catch (PDOException $e) {
    // Fallback to session data if database connection fails
    $full_name = $_SESSION['full_name'] ?? 'Tenant';
    $email = $_SESSION['email'] ?? '';
    $unit_number = $_SESSION['unit_number'] ?? 'Not assigned';
    $unit_role = $_SESSION['unit_role'] ?? 'primary';
    $is_primary_tenant = $_SESSION['is_primary_tenant'] ?? 0;
    $occupants = [];
    $occupant_count = 1;
    $maintenance_count = 0;
    $message_count = 0;
    error_log("Database error: " . $e->getMessage());
}

// Function to get role badge class
function getRoleBadgeClass($role, $is_primary) {
    if ($is_primary) return 'bg-primary';
    switch ($role) {
        case 'spouse': return 'bg-danger';
        case 'family': return 'bg-success';
        case 'roommate': return 'bg-secondary';
        default: return 'bg-info';
    }
}

// Function to get role display name
function getRoleDisplayName($role, $is_primary) {
    if ($is_primary) return 'Primary Tenant';
    switch ($role) {
        case 'spouse': return 'Spouse/Partner';
        case 'family': return 'Family Member';
        case 'roommate': return 'Roommate';
        default: return ucfirst($role);
    }
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
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
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

        .unit-card {
            background: linear-gradient(120deg, #e8f5e8, #f0f8f0);
            border: none;
            border-radius: 12px;
            padding: 20px;
        }

        .occupant-item {
            border-left: 4px solid var(--primary);
            padding: 10px 15px;
            margin: 5px 0;
            background: white;
            border-radius: 8px;
        }

        .occupant-item.primary {
            border-left-color: var(--primary);
        }

        .occupant-item.spouse {
            border-left-color: var(--danger);
        }

        .occupant-item.family {
            border-left-color: var(--success);
        }

        .occupant-item.roommate {
            border-left-color: var(--secondary);
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

        .card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .card-link:hover {
            color: inherit;
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
        <?php if ($unit_number && $unit_number !== 'Not assigned'): ?>
            <small class="text-light d-block mt-1">
                <i class="fas fa-door-open me-1"></i>Unit <?= htmlspecialchars($unit_number) ?>
                <span class="badge bg-light text-dark ms-1">
                    <?= getRoleDisplayName($unit_role, $is_primary_tenant) ?>
                </span>
            </small>
        <?php endif; ?>
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
        <a href="notifications.php">
            <i class="fas fa-bell"></i> Notifications
            <?php if ($unread_notification_count > 0): ?>
                <span class="badge bg-danger ms-auto"><?= $unread_notification_count ?></span>
            <?php endif; ?>
        </a>
        <a href="messages.php">
            <i class="fas fa-comments"></i> Messages
        </a>
        <?php if ($is_primary_tenant): ?>
        <a href="family.php">
            <i class="fas fa-users"></i> Family Management
        </a>
        <?php endif; ?>
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
        <p class="text-muted">
            Welcome to your tenant dashboard 
            <?php if ($unit_number && $unit_number !== 'Not assigned'): ?>
                • Unit <?= htmlspecialchars($unit_number) ?>
            <?php endif; ?>
        </p>
    </div>
    
    <!-- Welcome message with user's name and unit info -->

    <div class="card welcome-card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <h4>Welcome back, <?= htmlspecialchars($full_name) ?>! 👋</h4>
                    <p class="mb-2 text-muted">
                        <?php if ($unit_number && $unit_number !== 'Not assigned'): ?>
                            You're logged in as <strong><?= getRoleDisplayName($unit_role, $is_primary_tenant) ?></strong> of <strong>Unit <?= htmlspecialchars($unit_number) ?></strong>
                        <?php else: ?>
                            Your unit assignment is pending. Please contact administration.
                        <?php endif; ?>
                    </p>
                    <div class="d-flex gap-2">
                        <span class="badge bg-primary">
                            <i class="fas fa-users me-1"></i><?= $occupant_count ?> occupant(s)
                        </span>
                        <?php if ($is_primary_tenant): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-star me-1"></i>Primary Tenant
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <i class="fas fa-home fa-3x text-primary opacity-25"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Panel -->
    <?php if (!empty($notifications)): ?>
    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="card-title mb-0">
                <i class="fas fa-bell me-2"></i>Notifications (<?= $unread_notification_count ?> unread)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item notification-item 
                        <?= $notification['type'] == 'success' ? 'border-success' : '' ?>
                        <?= $notification['type'] == 'warning' ? 'border-warning' : '' ?>
                        <?= $notification['type'] == 'error' ? 'border-danger' : '' ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">
                                <?php if ($notification['type'] == 'success'): ?>
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                <?php elseif ($notification['type'] == 'warning'): ?>
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                <?php elseif ($notification['type'] == 'error'): ?>
                                    <i class="fas fa-times-circle text-danger me-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-info-circle text-primary me-2"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($notification['title']) ?>
                            </h6>
                            <small class="text-muted"><?= date('M j, g:i A', strtotime($notification['created_at'])) ?></small>
                        </div>
                        <p class="mb-1"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                        <form method="POST" action="mark_notification_read.php" class="mt-2">
                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-check me-1"></i>Mark as read
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-3">
            <a href="rent.php" class="card-link">
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
            </a>
        </div>

        <div class="col-md-3">
            <a href="maintenance.php" class="card-link">
                <div class="card stat-card warning">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <h5>Maintenance Requests</h5>
                        <p class="text-muted"><?= $maintenance_count ?> active issues</p>
                        <div class="d-flex justify-content-between">
                            <span class="badge bg-warning"><?= $maintenance_count ?> pending</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3">
            <a href="messages.php" class="card-link">
                <div class="card stat-card danger">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h5>Unread Messages</h5>
                        <p class="text-muted"><?= $message_count ?> new message(s)</p>
                        <span class="btn btn-sm btn-outline-danger">View Messages</span>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3">
            <div class="card stat-card success">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5>Unit Occupants</h5>
                    <p class="text-muted"><?= $occupant_count ?> people</p>
                    <div class="d-flex justify-content-between">
                        <span class="badge bg-success"><?= $occupant_count ?> total</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>Unit Occupants
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($occupants)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($occupants as $occupant): ?>
                                <div class="list-group-item occupant-item <?= $occupant['unit_role'] ?> px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($occupant['full_name']) ?></h6>
                                            <p class="mb-0 text-muted small"><?= htmlspecialchars($occupant['email']) ?></p>
                                        </div>
                                        <div>
                                            <span class="badge <?= getRoleBadgeClass($occupant['unit_role'], $occupant['is_primary_tenant']) ?>">
                                                <?= getRoleDisplayName($occupant['unit_role'], $occupant['is_primary_tenant']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No occupants found for this unit.</p>
                    <?php endif; ?>
                    
                    <?php if ($is_primary_tenant): ?>
                        <div class="mt-3">
                            <a href="family.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-plus me-1"></i>Manage Occupants
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Latest Announcement</h5>
                </div>
                <div class="card-body">
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

            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="rent.php" class="btn btn-primary">
                            <i class="fas fa-money-bill-wave me-2"></i> Pay Rent
                        </a>
                        <a href="maintenance.php" class="btn btn-outline-primary">
                            <i class="fas fa-tools me-2"></i> Request Maintenance
                        </a>
                        <a href="messages.php" class="btn btn-outline-primary">
                            <i class="fas fa-envelope me-2"></i> Contact Landlord
                        </a>
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

        // Add interactive effects to stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });
</script>
</body>
</html>
