<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../auth/db_config.php";

$tenants_query = "
    SELECT u.*, a.rent_amount, a.building_name 
    FROM users u 
    LEFT JOIN apartments a ON u.unit_number = a.apartment_number 
    WHERE u.is_verified = 1 AND u.is_admin = 0 
    ORDER BY a.building_name, u.unit_number, u.is_primary_tenant DESC
";
$tenants_result = $conn->query($tenants_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tenant Units</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
        
        .back {
            position: absolute;
            top: 20px;
            left: 20px;
        }
        
        .back a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #34495e;
        }
        
        .back a:hover {
            background: #2c3e50;
            transform: translateY(-2px);
        }
        
        .content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
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
        
        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .role-primary {
            background: #d5f4e6;
            color: #27ae60;
            border: 1px solid #2ecc71;
        }
        
        .role-secondary {
            background: #e8f4fd;
            color: #3498db;
            border: 1px solid #3498db;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #bdc3c7;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 12px 8px;
                font-size: 0.9em;
            }
            
            .back {
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
        <div class="back"><a href="manage_apartments.php">← Back to Apartments</a></div>
        <h2>Tenant Units Overview</h2>
    </div>
    
    <div class="content">
        <?php if ($tenants_result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Unit</th>
                    <th>Building</th>
                    <th>Rent</th>
                    <th>Tenant Name</th>
                    <th>Role</th>
                    <th>Contact</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php while($tenant = $tenants_result->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($tenant['unit_number'] ?? 'Not assigned') ?></strong></td>
                    <td><?= htmlspecialchars($tenant['building_name'] ?? 'N/A') ?></td>
                    <td>
                        <?php if (!empty($tenant['rent_amount']) && $tenant['rent_amount'] > 0): ?>
                            KSh <?= number_format((float)$tenant['rent_amount']) ?>
                        <?php else: ?>
                            <span style="color: #95a5a6;">Not set</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($tenant['full_name']) ?></td>
                    <td>
                        <?php if ($tenant['is_primary_tenant'] == 1): ?>
                            <span class="role-badge role-primary">Primary</span>
                        <?php else: ?>
                            <span class="role-badge role-secondary">
                                <?= !empty($tenant['unit_role']) ? ucfirst($tenant['unit_role']) : 'Tenant' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($tenant['email']) ?><br>
                        <small style="color: #7f8c8d;"><?= htmlspecialchars($tenant['phone'] ?? 'No phone') ?></small>
                    </td>
                    <td><?= date('M j, Y', strtotime($tenant['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No Tenants Found</h3>
                <p>There are no verified tenants in the system yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Font Awesome for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
