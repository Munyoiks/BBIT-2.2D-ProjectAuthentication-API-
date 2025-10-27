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

$success_message = '';
$error_message = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch user data
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $full_name = $user['full_name'];
        $email = $user['email'];
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
    } else {
        $full_name = $_SESSION['full_name'] ?? 'Tenant';
        $email = $_SESSION['email'] ?? '';
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['submit_request'])) {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $priority = $_POST['priority'];
            $category = $_POST['category'];
            
            // Validate inputs
            if (empty($title) || empty($description)) {
                $error_message = "Please fill in all required fields.";
            } else {
                // Insert maintenance request
                $stmt = $pdo->prepare("INSERT INTO maintenance_requests (user_id, title, description, priority, category, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $title, $description, $priority, $category]);
                
                $success_message = "Maintenance request submitted successfully!";
            }
        }
        
        // Handle status updates (for demonstration)
        if (isset($_POST['update_status'])) {
            $request_id = $_POST['request_id'];
            $new_status = $_POST['status'];
            
            // In a real application, this would be done by admin/landlord
            $stmt = $pdo->prepare("UPDATE maintenance_requests SET status = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$new_status, $request_id, $user_id]);
            
            $success_message = "Request status updated successfully!";
        }
    }

    // Fetch maintenance requests
    $stmt = $pdo->prepare("SELECT * FROM maintenance_requests WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $maintenance_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $full_name = $_SESSION['full_name'] ?? 'Tenant';
    $email = $_SESSION['email'] ?? '';
    $maintenance_requests = [];
    error_log("Database error: " . $e->getMessage());
    $error_message = "Unable to load maintenance requests. Please try again later.";
}

// Create maintenance_requests table if it doesn't exist
try {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS maintenance_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        priority ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
        category VARCHAR(100) NOT NULL,
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($create_table_sql);
} catch (PDOException $e) {
    error_log("Table creation error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mojo Tenant System | Maintenance</title>
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

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #eaeaea;
            padding: 1.25rem;
        }

        .status-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 600;
        }

        .priority-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 600;
        }

        .priority-low {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .priority-medium {
            background-color: #fff3cd;
            color: #856404;
        }

        .priority-high {
            background-color: #f8d7da;
            color: #721c24;
        }

        .priority-emergency {
            background-color: #721c24;
            color: white;
        }

        .request-item {
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .request-item:hover {
            border-left-color: var(--primary-dark);
        }

        .request-item.pending {
            border-left-color: var(--warning);
        }

        .request-item.in_progress {
            border-left-color: var(--primary);
        }

        .request-item.completed {
            border-left-color: var(--success);
        }

        .request-item.cancelled {
            border-left-color: var(--danger);
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

        .form-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #dee2e6;
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
        <a href="dashboard.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="profile.php">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="rent.php">
            <i class="fas fa-money-bill-wave"></i> Rent & Payments
        </a>
        <a href="maintenance.php" class="active">
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Maintenance Requests</h2>
            <p class="text-muted">Submit and track maintenance requests for your property</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRequestModal">
            <i class="fas fa-plus me-2"></i>New Request
        </button>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Your Maintenance Requests</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($maintenance_requests)): ?>
                        <div class="empty-state"> 