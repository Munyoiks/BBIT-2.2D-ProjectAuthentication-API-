//view_tenant_details.php

<?php
// Include database connection
include_once '../auth/db_config.php';

// Check if tenant ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_tenants.php");
    exit();
}

$tenant_id = $_GET['id'];

// Fetch tenant details
$sql = "SELECT * FROM users WHERE id = ? AND role = 'tenant'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$tenant = $result->fetch_assoc();
$stmt->close();

if (!$tenant) {
    header("Location: manage_tenants.php?error=Tenant not found");
    exit();
}

// Fetch linked tenants (roommates/family members)
$linked_sql = "SELECT * FROM users WHERE linked_to = ? OR id = ? ORDER BY unit_role";
$linked_stmt = $conn->prepare($linked_sql);
$linked_stmt->bind_param("ii", $tenant_id, $tenant_id);
$linked_stmt->execute();
$linked_result = $linked_stmt->get_result();
$linked_tenants = [];
while ($row = $linked_result->fetch_assoc()) {
    $linked_tenants[] = $row;
}
$linked_stmt->close();

// Check if this tenant is the primary tenant
$is_primary = ($tenant['unit_role'] === 'primary' || $tenant['is_primary_tenant'] == 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Details - <?= htmlspecialchars($tenant['full_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #4a6ee0, #2c52c9);
            color: #fff;
            padding: 20px 0;
            text-align: center;
            position: relative;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .header h2 {
            font-weight: 600;
            margin: 0;
            font-size: 1.8rem;
        }
        
        .content {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .logout {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .back {
            position: absolute;
            top: 20px;
            left: 20px;
        }
        
        .logout a, .back a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .logout a {
            background: #e74c3c;
        }
        
        .logout a:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .back a {
            background: #34495e;
        }
        
        .back a:hover {
            background: #2c3e50;
            transform: translateY(-2px);
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
        }
        
        .card-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
        }
        
        .card-title {
            font-size: 1.5em;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i {
            color: #4a6ee0;
        }
        
        .tenant-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a6ee0, #2c52c9);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            font-weight: bold;
            margin-right: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .profile-info h1 {
            font-size: 2.2em;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .profile-info .tenant-id {
            color: #7f8c8d;
            font-size: 1em;
            margin-bottom: 10px;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-active {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .status-pending {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .status-inactive {
            background: #fdecea;
            color: #e74c3c;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #4a6ee0;
        }
        
        .info-label {
            font-size: 0.85em;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.1em;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .btn {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            border: none;
            cursor: pointer;
            margin: 5px;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }
        
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        
        .btn-success:hover {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 204, 113, 0.3);
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(243, 156, 18, 0.3);
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(149, 165, 166, 0.3);
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }
        
        .linked-tenants {
            margin-top: 20px;
        }
        
        .linked-tenant-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #2ecc71;
        }
        
        .linked-tenant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2ecc71;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .linked-tenant-info {
            flex: 1;
        }
        
        .linked-tenant-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .linked-tenant-role {
            font-size: 0.85em;
            color: #7f8c8d;
            text-transform: capitalize;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #bdc3c7;
        }
        
        .verification-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .verified {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .not-verified {
            background: #fdecea;
            color: #e74c3c;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }
            
            .tenant-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin: 5px 0;
            }
            
            .logout, .back {
                position: static;
                display: inline-block;
                margin: 5px;
            }
            
            .header h2 {
                margin: 10px 0;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="back"><a href="manage_tenants.php"><i class="fas fa-arrow-left"></i> Back to Tenants</a></div>
        <h2>Tenant Details</h2>
        <div class="logout"><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>
    
    <div class="content">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="tenant-avatar">
                <?= strtoupper(substr($tenant['full_name'], 0, 1)) ?>
            </div>
            <div class="profile-info">
                <h1><?= htmlspecialchars($tenant['full_name']) ?></h1>
                <div class="tenant-id">Tenant ID: <?= $tenant['id'] ?></div>
                <div>
                    <?php if (!empty($tenant['unit_number'])): ?>
                        <span class="status-badge status-active">
                            <i class="fas fa-check-circle"></i> Active Tenant
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-pending">
                            <i class="fas fa-clock"></i> Pending Assignment
                        </span>
                    <?php endif; ?>
                    
                    <span class="verification-status <?= $tenant['is_verified'] ? 'verified' : 'not-verified' ?>">
                        <i class="fas <?= $tenant['is_verified'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                        <?= $tenant['is_verified'] ? 'Verified' : 'Not Verified' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Personal Information Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user"></i> Personal Information</h3>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><?= htmlspecialchars($tenant['full_name']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email Address</span>
                    <span class="info-value"><?= htmlspecialchars($tenant['email']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone Number</span>
                    <span class="info-value"><?= htmlspecialchars($tenant['phone'] ?? 'Not provided') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Emergency Contact</span>
                    <span class="info-value"><?= htmlspecialchars($tenant['emergency_contact'] ?? 'Not provided') ?></span>
                </div>
            </div>
        </div>

        <!-- Unit Information Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-home"></i> Unit Information</h3>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Unit Number</span>
                    <span class="info-value">
                        <?php if (!empty($tenant['unit_number'])): ?>
                            <?= htmlspecialchars($tenant['unit_number']) ?>
                        <?php else: ?>
                            <span style="color: #e74c3c;">Not assigned</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Unit Role</span>
                    <span class="info-value" style="text-transform: capitalize;">
                        <?= htmlspecialchars($tenant['unit_role'] ?? 'primary') ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Primary Tenant</span>
                    <span class="info-value">
                        <?= $is_primary ? 'Yes' : 'No' ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Account Created</span>
                    <span class="info-value"><?= date('F j, Y g:i A', strtotime($tenant['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Linked Tenants Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-users"></i> Household Members</h3>
            </div>
            <div class="linked-tenants">
                <?php if (count($linked_tenants) > 0): ?>
                    <?php foreach($linked_tenants as $linked_tenant): ?>
                        <?php if ($linked_tenant['id'] != $tenant_id): ?>
                            <div class="linked-tenant-item">
                                <div class="linked-tenant-avatar">
                                    <?= strtoupper(substr($linked_tenant['full_name'], 0, 1)) ?>
                                </div>
                                <div class="linked-tenant-info">
                                    <div class="linked-tenant-name"><?= htmlspecialchars($linked_tenant['full_name']) ?></div>
                                    <div class="linked-tenant-role">
                                        <?= htmlspecialchars($linked_tenant['unit_role'] ?? 'secondary') ?> 
                                        • <?= htmlspecialchars($linked_tenant['email']) ?>
                                    </div>
                                </div>
                                <a href="view_tenant_details.php?id=<?= $linked_tenant['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No Household Members</h3>
                        <p>This tenant doesn't have any linked household members.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-cog"></i> Management Actions</h3>
            </div>
            <div class="action-buttons">
                <a href="edit_tenant.php?id=<?= $tenant['id'] ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Tenant
                </a>
                <a href="assign_unit.php?tenant_id=<?= $tenant['id'] ?>" class="btn btn-success">
                    <i class="fas fa-home"></i> Assign/Change Unit
                </a>
                <?php if ($is_primary): ?>
                    <a href="add_household_member.php?primary_tenant_id=<?= $tenant['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add Household Member
                    </a>
                <?php endif; ?>
                <a href="send_notification.php?tenant_id=<?= $tenant['id'] ?>" class="btn btn-secondary">
                    <i class="fas fa-bell"></i> Send Notification
                </a>
                <a href="manage_tenants.php?delete_id=<?= $tenant['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this tenant? This action cannot be undone.')">
                    <i class="fas fa-trash"></i> Delete Tenant
                </a>
            </div>
        </div>
    </div>

    <script>
        // Simple confirmation for delete actions
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.btn-danger');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
