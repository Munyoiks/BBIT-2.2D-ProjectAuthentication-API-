// manage_tenants.php

<?php
// Include database connection
include_once '../auth/db_config.php';

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $delete_sql = "DELETE FROM users WHERE id = ? AND role = 'tenant'";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $message = "Tenant deleted successfully";
    } else {
        $error = "Error deleting tenant: " . $conn->error;
    }
    $stmt->close();
}

// Fetch tenants data (users with role 'tenant')
$sql = "SELECT * FROM users WHERE role = 'tenant' ORDER BY created_at DESC";
$tenants_result = $conn->query($sql);

if (!$tenants_result) {
    die("Query failed: " . $conn->error);
}

// Count statistics
$total_tenants = $tenants_result->num_rows;
$assigned_count = 0;
$unassigned_count = 0;

if ($total_tenants > 0) {
    $tenants_result->data_seek(0);
    while($tenant = $tenants_result->fetch_assoc()) {
        if (!empty($tenant['unit_number'])) {
            $assigned_count++;
        } else {
            $unassigned_count++;
        }
    }
    $tenants_result->data_seek(0); // Reset pointer for display
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tenants</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS styles remain the same */
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
            max-width: 1400px;
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
            padding: 25px;
            margin-top: 20px;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        th {
            background: linear-gradient(135deg, #4a6ee0, #2c52c9);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background: #f8fafc;
            transition: background 0.3s ease;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .btn {
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 6px;
            margin: 2px;
            font-size: 0.85em;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            border: none;
            cursor: pointer;
        }
        
        .btn i {
            margin-right: 5px;
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
        
        .add-tenant-btn {
            background: #2ecc71;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);
        }
        
        .add-tenant-btn i {
            margin-right: 8px;
        }
        
        .add-tenant-btn:hover {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(46, 204, 113, 0.4);
        }
        
        .alert {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
            border-left: 4px solid;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 1.2em;
        }
        
        .alert-success {
            background: #d5f4e6;
            color: #27ae60;
            border-left-color: #2ecc71;
        }
        
        .alert-error {
            background: #fdecea;
            color: #e74c3c;
            border-left-color: #e74c3c;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-assigned {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .status-unassigned {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .status-pending {
            background: #e8f4fd;
            color: #3498db;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .tenant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4a6ee0;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .tenant-info {
            display: flex;
            align-items: center;
        }
        
        .search-filter {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #4a6ee0;
            box-shadow: 0 0 0 3px rgba(74, 110, 224, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
        }
        
        .filter-options {
            display: flex;
            gap: 10px;
        }
        
        .filter-options select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            font-size: 1em;
            cursor: pointer;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5em;
            color: white;
        }
        
        .stat-info h3 {
            font-size: 1.8em;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .stat-info p {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 12px 8px;
                font-size: 0.9em;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                margin: 2px 0;
            }
            
            .search-filter {
                flex-direction: column;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="back"><a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></div>
        <h2>Manage Tenants</h2>
        <div class="logout"><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>
    
    <div class="content">
        <?php if (isset($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon" style="background: #4a6ee0;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $total_tenants ?></h3>
                    <p>Total Tenants</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #2ecc71;">
                    <i class="fas fa-home"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $assigned_count ?></h3>
                    <p>Assigned Units</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #e74c3c;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $unassigned_count ?></h3>
                    <p>Unassigned Tenants</p>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter Section -->
        <div class="search-filter">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search tenants by name, email, or unit...">
            </div>
            <div class="filter-options">
                <select id="statusFilter">
                    <option value="all">All Statuses</option>
                    <option value="assigned">Assigned</option>
                    <option value="unassigned">Unassigned</option>
                </select>
                <select id="sortBy">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="name">Name A-Z</option>
                </select>
            </div>
        </div>
        
        <a href="add_tenant.php" class="add-tenant-btn">
            <i class="fas fa-user-plus"></i> Add New Tenant
        </a>
        
        <div class="card">
            <table id="tenantsTable">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Contact Info</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tenants_result->num_rows > 0): ?>
                        <?php while($tenant = $tenants_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="tenant-info">
                                        <div class="tenant-avatar">
                                            <?= strtoupper(substr($tenant['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($tenant['full_name']) ?></strong>
                                            <div style="font-size: 0.8em; color: #7f8c8d;">ID: <?= $tenant['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><i class="fas fa-envelope" style="color: #4a6ee0; margin-right: 5px;"></i> <?= htmlspecialchars($tenant['email']) ?></div>
                                    <div><i class="fas fa-phone" style="color: #2ecc71; margin-right: 5px;"></i> <?= htmlspecialchars($tenant['phone'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($tenant['unit_number'])): ?>
                                        <span class="status-badge status-assigned">
                                            <i class="fas fa-home"></i> <?= htmlspecialchars($tenant['unit_number']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-unassigned">
                                            <i class="fas fa-times-circle"></i> Not assigned
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($tenant['unit_number'])): ?>
                                        <span class="status-badge status-assigned">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-unassigned">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-pending">
                                        <?= htmlspecialchars($tenant['unit_role'] ?? 'primary') ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($tenant['created_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_tenant_details.php?id=<?= $tenant['id'] ?>" class="btn btn-primary" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="assign_unit.php?tenant_id=<?= $tenant['id'] ?>" class="btn btn-success" title="Assign Unit">
                                            <i class="fas fa-home"></i> Assign
                                        </a>
                                        <a href="edit_tenant.php?id=<?= $tenant['id'] ?>" class="btn btn-warning" title="Edit Tenant">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="manage_tenants.php?delete_id=<?= $tenant['id'] ?>" class="btn btn-danger" title="Delete Tenant" onclick="return confirm('Are you sure you want to delete this tenant?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #7f8c8d;">
                                <i class="fas fa-users" style="font-size: 3em; margin-bottom: 15px; display: block; color: #bdc3c7;"></i>
                                No tenants found. <a href="add_tenant.php" style="color: #4a6ee0; text-decoration: none; font-weight: 600;">Add your first tenant</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Simple search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#tenantsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
        
        // Status filter functionality
        document.getElementById('statusFilter').addEventListener('change', function() {
            const filterValue = this.value;
            const rows = document.querySelectorAll('#tenantsTable tbody tr');
            
            rows.forEach(row => {
                if (filterValue === 'all') {
                    row.style.display = '';
                } else {
                    const statusCell = row.cells[3];
                    const status = statusCell.textContent.toLowerCase().includes('active') ? 'assigned' : 'unassigned';
                    row.style.display = status === filterValue ? '' : 'none';
                }
            });
        });
    </script>
</body>
</html>
