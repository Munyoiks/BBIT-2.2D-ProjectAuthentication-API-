// tenant_profiles.php
<?php
session_start();

// Protect this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../auth/db_config.php";

// Get all tenants
$tenants_query = "SELECT id, full_name, email, phone, created_at FROM users WHERE is_admin = 0 ORDER BY full_name";
$tenants_result = $conn->query($tenants_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tenant Profiles - Admin View</title>
    <style>
        body {font-family: Arial, sans-serif; background: #f6f8fa; margin: 0;}
        .header {background: #007bff; color: #fff; padding: 15px; text-align: center; position: relative;}
        .content {padding: 20px;}
        .logout {position: absolute; top: 15px; right: 20px;}
        .logout a {color: white; text-decoration: none; background: #dc3545; padding: 6px 10px; border-radius: 4px;}
        .back {position: absolute; top: 15px; left: 20px;}
        .back a {color: white; text-decoration: none; background: #6c757d; padding: 6px 10px; border-radius: 4px;}
        
        .tenant-grid {display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;}
        .tenant-card {background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
        .tenant-card h3 {margin-top: 0; color: #007bff; border-bottom: 1px solid #eee; padding-bottom: 10px;}
        .tenant-info {margin: 10px 0;}
        .tenant-info strong {color: #555;}
    </style>
</head>
<body>
    <div class="header">
        <div class="back"><a href="admin_dashboard.php">← Back to Dashboard</a></div>
        <h2>Tenant Profiles - Admin View</h2>
        <div class="logout"><a href="admin_logout.php">Logout</a></div>
    </div>
    
    <div class="content">
        <h3>All Tenants (<?= $tenants_result->num_rows ?>)</h3>
        
        <?php if ($tenants_result->num_rows > 0): ?>
        <div class="tenant-grid">
            <?php while($tenant = $tenants_result->fetch_assoc()): ?>
            <div class="tenant-card">
                <h3><?= htmlspecialchars($tenant['full_name']) ?></h3>
                <div class="tenant-info">
                    <strong>Email:</strong> <?= htmlspecialchars($tenant['email']) ?>
                </div>
                <div class="tenant-info">
                    <strong>Phone:</strong> <?= htmlspecialchars($tenant['phone'] ?? 'Not provided') ?>
                </div>
                <div class="tenant-info">
                    <strong>Member Since:</strong> <?= date('M j, Y', strtotime($tenant['created_at'])) ?>
                </div>
                <div class="tenant-info">
                    <strong>User ID:</strong> <?= $tenant['id'] ?>
                </div>
                <div style="margin-top: 15px;">
                    <a href="view_tenant_details.php?id=<?= $tenant['id'] ?>" style="background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; display: inline-block;">
                        View Full Details
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <p>No tenants found in the system.</p>
        <?php endif; ?>
    </div>
</body>
</html>
