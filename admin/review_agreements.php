// review_agreements.php

<?php
session_start();
require_once "../auth/db_config.php";

// Require admin login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['full_name'];
$success = "";
$error = "";

// Create tables if they don't exist
$create_agreements_table = "CREATE TABLE IF NOT EXISTS user_agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    agreement_file VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    agreement_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_agreement (user_id)
)";
$conn->query($create_agreements_table);

$create_notifications_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('success', 'warning', 'error', 'info') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($create_notifications_table);

// Handle approval or rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = intval($_POST['user_id']);
    $status = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    $notes = trim($_POST['admin_notes']);
    
    // Get user info for notification
    $user_stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();

    // Update agreement status
    $stmt = $conn->prepare("UPDATE user_agreements SET agreement_status = ?, admin_notes = ?, reviewed_at = NOW(), reviewed_by = ? WHERE user_id = ?");
    $stmt->bind_param("ssii", $status, $notes, $_SESSION['user_id'], $user_id);

    if ($stmt->execute()) {
        // Create notification for tenant
        if ($status === 'approved') {
            $notification_title = "Agreement Approved";
            $notification_message = "Your tenant agreement has been approved by the administrator. You can now proceed with your tenancy.";
            $notification_type = "success";
            $success_message = "✅ Agreement approved successfully! Tenant has been notified.";
        } else {
            $notification_title = "Agreement Requires Revision";
            $notification_message = "Your tenant agreement requires some revisions. Please review the admin notes and upload a new version.";
            $notification_type = "warning";
            $success_message = "⚠ Agreement rejected. Tenant has been notified to make revisions.";
        }
        
        // Add admin notes to notification if provided
        if (!empty($notes)) {
            $notification_message .= "\n\nAdmin Notes: " . $notes;
        }
        
        // Insert notification
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $notif_stmt->bind_param("isss", $user_id, $notification_title, $notification_message, $notification_type);
        $notif_stmt->execute();
        $notif_stmt->close();
        
        $success = $success_message;
    } else {
        $error = "⚠ Failed to update agreement. Please try again.";
    }

    $stmt->close();
}

// Handle download request
if (isset($_GET['download']) && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    
    $stmt = $conn->prepare("SELECT agreement_file FROM user_agreements WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $agreement = $result->fetch_assoc();
    $stmt->close();
    
    if ($agreement && !empty($agreement['agreement_file'])) {
        $file_path = "../uploads/signed_agreements/" . $agreement['agreement_file'];
        
        if (file_exists($file_path)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="agreement_' . $user_id . '_' . $agreement['agreement_file'] . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            $error = "File not found on server.";
        }
    } else {
        $error = "No agreement file found for this user.";
    }
}

// Fetch all agreements with user info
$sql = "SELECT u.id AS user_id, u.full_name, u.email, u.phone, u.unit_number,
               a.agreement_file, a.agreement_status, a.uploaded_at, a.admin_notes, a.reviewed_at,
               admin.full_name as reviewed_by_name
        FROM users u
        INNER JOIN user_agreements a ON u.id = a.user_id
        LEFT JOIN users admin ON a.reviewed_by = admin.id
        ORDER BY 
            CASE a.agreement_status 
                WHEN 'pending' THEN 1
                WHEN 'rejected' THEN 2
                WHEN 'approved' THEN 3
            END,
            a.uploaded_at DESC";
$result = $conn->query($sql);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(agreement_status = 'pending') as pending,
    SUM(agreement_status = 'approved') as approved,
    SUM(agreement_status = 'rejected') as rejected
    FROM user_agreements";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agreement Management | Monrine Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --sidebar: #34495e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--sidebar);
            color: white;
            padding: 0;
        }

        .sidebar-header {
            padding: 30px 25px;
            background: var(--primary);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 22px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .sidebar-nav a {
            color: #bdc3c7;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 25px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            font-weight: 500;
        }

        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: var(--primary);
            color: white;
            border-left-color: var(--secondary);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .header h1 {
            color: var(--primary);
            font-size: 28px;
            margin-bottom: 5px;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 5px solid var(--secondary);
        }

        .stat-card.pending { border-left-color: var(--warning); }
        .stat-card.approved { border-left-color: var(--success); }
        .stat-card.rejected { border-left-color: var(--danger); }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            display: block;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Agreement Cards */
        .agreements-grid {
            display: grid;
            gap: 25px;
        }

        .agreement-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .agreement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .agreement-header {
            padding: 20px 25px;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tenant-info h3 {
            color: var(--primary);
            margin-bottom: 5px;
        }

        .tenant-info p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        .agreement-body {
            padding: 25px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .agreement-details h4 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 16px;
        }

        .detail-item {
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .detail-item i {
            color: var(--secondary);
            margin-top: 2px;
        }

        .action-panel {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        .btn-primary { background: var(--secondary); color: white; }
        .btn-primary:hover { background: #2980b9; transform: translateY(-2px); }

        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #219653; transform: translateY(-2px); }

        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #c0392b; transform: translateY(-2px); }

        .btn-outline { 
            background: transparent; 
            border: 2px solid var(--secondary); 
            color: var(--secondary); 
        }
        .btn-outline:hover { 
            background: var(--secondary); 
            color: white; 
            transform: translateY(-2px);
        }

        textarea {
            width: 100%;
            min-height: 80px;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            resize: vertical;
            font-family: inherit;
            margin-bottom: 15px;
        }

        textarea:focus {
            outline: none;
            border-color: var(--secondary);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 64px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }

        @media (max-width: 1024px) {
            .agreement-body {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-building"></i> Monrine Management</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
                <a href="manage_tenants.php">
                    <i class="fas fa-users"></i>Tenants
                </a>
                <a href="manage_apartments.php">
                    <i class="fas fa-building"></i>Apartments
                </a>
                <a href="rent_payments.php">
                    <i class="fas fa-money-bill-wave"></i>Payments
                </a>
                <a href="review_agreements.php" class="active">
                    <i class="fas fa-file-contract"></i>Agreements
                </a>
                <a href="generate_reports.php">
                    <i class="fas fa-chart-bar"></i>Reports
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-file-contract"></i> Agreement Management</h1>
                <p>Review and manage tenant agreements</p>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number"><?= $stats['total'] ?></span>
                    <div class="stat-label">Total Agreements</div>
                </div>
                <div class="stat-card pending">
                    <span class="stat-number"><?= $stats['pending'] ?></span>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card approved">
                    <span class="stat-number"><?= $stats['approved'] ?></span>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card rejected">
                    <span class="stat-number"><?= $stats['rejected'] ?></span>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <!-- Agreements List -->
            <div class="agreements-grid">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="agreement-card">
                        <div class="agreement-header">
                            <div class="tenant-info">
                                <h3><?= htmlspecialchars($row['full_name']) ?></h3>
                                <p><?= htmlspecialchars($row['email']) ?> • Unit <?= htmlspecialchars($row['unit_number'] ?? 'N/A') ?></p>
                            </div>
                            <span class="status-badge status-<?= $row['agreement_status'] ?>">
                                <?= ucfirst($row['agreement_status']) ?>
                            </span>
                        </div>

                        <div class="agreement-body">
                            <div class="agreement-details">
                                <h4><i class="fas fa-info-circle"></i> Agreement Details</h4>
                                <div class="detail-item">
                                    <i class="fas fa-file-pdf"></i>
                                    <div>
                                        <strong>File:</strong> <?= htmlspecialchars($row['agreement_file']) ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <div>
                                        <strong>Uploaded:</strong> <?= date('F j, Y g:i A', strtotime($row['uploaded_at'])) ?>
                                    </div>
                                </div>
                                <?php if ($row['reviewed_at']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-user-check"></i>
                                    <div>
                                        <strong>Reviewed:</strong> <?= date('F j, Y g:i A', strtotime($row['reviewed_at'])) ?>
                                        <?php if ($row['reviewed_by_name']): ?>
                                            by <?= htmlspecialchars($row['reviewed_by_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($row['admin_notes'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-sticky-note"></i>
                                    <div>
                                        <strong>Admin Notes:</strong> <?= nl2br(htmlspecialchars($row['admin_notes'])) ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="action-panel">
                                <h4><i class="fas fa-cog"></i> Actions</h4>
                                
                                <!-- Download Button -->
                                <a href="?download=true&user_id=<?= $row['user_id'] ?>" class="btn btn-outline btn-block">
                                    <i class="fas fa-download"></i> Download Agreement
                                </a>

                                <!-- View Button -->
                                <a href="../uploads/signed_agreements/<?= htmlspecialchars($row['agreement_file']) ?>" 
                                   target="_blank" class="btn btn-primary btn-block" style="margin-top: 10px;">
                                    <i class="fas fa-eye"></i> View Agreement
                                </a>

                                <!-- Approval Form -->
                                <?php if ($row['agreement_status'] == 'pending'): ?>
                                <form method="POST" style="margin-top: 15px;">
                                    <textarea name="admin_notes" placeholder="Add notes for the tenant (optional)..." 
                                              value="<?= htmlspecialchars($row['admin_notes'] ?? '') ?>"></textarea>
                                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                    <div class="action-buttons">
                                        <button type="submit" name="action" value="approve" class="btn btn-success">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </form>
                                <?php else: ?>
                                <div style="margin-top: 15px; padding: 10px; background: #e9ecef; border-radius: 6px; text-align: center;">
                                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                    <br><small>Agreement <?= $row['agreement_status'] ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-contract"></i>
                        <h3>No Agreements Found</h3>
                        <p>There are no tenant agreements waiting for review.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Add confirmation for reject actions
        document.addEventListener('DOMContentLoaded', function() {
            const rejectButtons = document.querySelectorAll('button[value="reject"]');
            rejectButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to reject this agreement? The tenant will be notified to make revisions.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
