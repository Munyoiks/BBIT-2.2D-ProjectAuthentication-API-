<?php
session_start();

// Protect this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../auth/db_config.php";

// Handle apartment deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM apartments WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $message = "Apartment deleted successfully";
    } else {
        $error = "Error deleting apartment";
    }
}

// Handle generate apartments
if (isset($_POST['generate_apartments'])) {
    generateAllApartments($conn);
    $message = "All apartments generated successfully!";
}

// Handle sync with users (mark apartments as occupied based on registered tenants)
if (isset($_POST['sync_occupancy'])) {
    syncApartmentOccupancy($conn);
    $message = "Apartment occupancy synced with tenant registrations!";
}

// Function to sync apartment occupancy with user registrations
function syncApartmentOccupancy($conn) {
    // Get all occupied units from users table
    $occupied_units_query = "
        SELECT DISTINCT unit_number 
        FROM users 
        WHERE unit_number IS NOT NULL 
        AND unit_number != '' 
        AND is_verified = 1
    ";
    $occupied_units_result = $conn->query($occupied_units_query);
    
    $occupied_units = [];
    while ($row = $occupied_units_result->fetch_assoc()) {
        $occupied_units[] = $row['unit_number'];
    }
    
    // Update apartment occupancy status
    $update_stmt = $conn->prepare("UPDATE apartments SET is_occupied = 0 WHERE 1");
    $update_stmt->execute();
    
    foreach ($occupied_units as $unit) {
        $update_occupied = $conn->prepare("UPDATE apartments SET is_occupied = 1 WHERE apartment_number = ?");
        $update_occupied->bind_param("s", $unit);
        $update_occupied->execute();
    }
}

// Get all apartments with tenant info
$apartments_query = "
    SELECT a.*, u.full_name as tenant_name, u.email as tenant_email, u.phone as tenant_phone,
           u.is_primary_tenant, u.unit_role, u.created_at as tenant_since
    FROM apartments a 
    LEFT JOIN users u ON a.apartment_number = u.unit_number AND u.is_verified = 1
    ORDER BY 
        CASE 
            WHEN a.building_name = 'Ground Floor' THEN 1
            WHEN a.building_name = '1st Floor' THEN 2
            WHEN a.building_name = '2nd Floor' THEN 3
            WHEN a.building_name = '3rd Floor' THEN 4
            WHEN a.building_name = '4th Floor' THEN 5
            WHEN a.building_name = '5th Floor' THEN 6
            WHEN a.building_name = 'Penthouse' THEN 7
            ELSE 0
        END ASC,
        a.apartment_number
";
$apartments_result = $conn->query($apartments_query);

// Function to generate all apartments
function generateAllApartments($conn) {
    // Clear existing apartments first
    $conn->query("DELETE FROM apartments");
    
    $apartments = [];
    
    // Ground Floor (G1-G14)
    $apartments[] = ['G1', 'Ground Floor', 12000];
    $apartments[] = ['G2', 'Ground Floor', 7000];
    $apartments[] = ['G3', 'Ground Floor', 7000];
    $apartments[] = ['G4', 'Ground Floor', 7000];
    $apartments[] = ['G5', 'Ground Floor', 7000];
    $apartments[] = ['G6', 'Ground Floor', 12000];
    $apartments[] = ['G7', 'Ground Floor', 12500];
    $apartments[] = ['G8', 'Ground Floor', 7000];
    $apartments[] = ['G9', 'Ground Floor', 7000];
    $apartments[] = ['G10', 'Ground Floor', 7000];
    $apartments[] = ['G11', 'Ground Floor', 7000];
    $apartments[] = ['G12', 'Ground Floor', 7000];
    $apartments[] = ['G13', 'Ground Floor', 12500];
    $apartments[] = ['G14', 'Ground Floor', 12000];
    
    // 1st Floor (F1-1 to F1-13)
    $apartments[] = ['F1-1', '1st Floor', 10000];
    $apartments[] = ['F1-2', '1st Floor', 7000];
    $apartments[] = ['F1-3', '1st Floor', 7000];
    $apartments[] = ['F1-4', '1st Floor', 7000];
    $apartments[] = ['F1-5', '1st Floor', 7000];
    $apartments[] = ['F1-6', '1st Floor', 10000];
    $apartments[] = ['F1-7', '1st Floor', 8500];
    $apartments[] = ['F1-8', '1st Floor', 7000];
    $apartments[] = ['F1-9', '1st Floor', 0]; // Office - no rent
    $apartments[] = ['F1-10', '1st Floor', 7000];
    $apartments[] = ['F1-11', '1st Floor', 0]; // Caretaker - no rent
    $apartments[] = ['F1-12', '1st Floor', 7000];
    $apartments[] = ['F1-13', '1st Floor', 9000];
    
    // 2nd Floor (F2-1 to F2-13)
    $apartments[] = ['F2-1', '2nd Floor', 12000];
    $apartments[] = ['F2-2', '2nd Floor', 7000];
    $apartments[] = ['F2-3', '2nd Floor', 7000];
    $apartments[] = ['F2-4', '2nd Floor', 7000];
    $apartments[] = ['F2-5', '2nd Floor', 7000];
    $apartments[] = ['F2-6', '2nd Floor', 12000];
    $apartments[] = ['F2-7', '2nd Floor', 12000];
    $apartments[] = ['F2-8', '2nd Floor', 7000];
    $apartments[] = ['F2-9', '2nd Floor', 7000];
    $apartments[] = ['F2-10', '2nd Floor', 7000];
    $apartments[] = ['F2-11', '2nd Floor', 7000];
    $apartments[] = ['F2-12', '2nd Floor', 7000];
    $apartments[] = ['F2-13', '2nd Floor', 12000];
    
    // 3rd Floor (F3-1 to F3-13)
    $apartments[] = ['F3-1', '3rd Floor', 12000];
    $apartments[] = ['F3-2', '3rd Floor', 7000];
    $apartments[] = ['F3-3', '3rd Floor', 7000];
    $apartments[] = ['F3-4', '3rd Floor', 7000];
    $apartments[] = ['F3-5', '3rd Floor', 7000];
    $apartments[] = ['F3-6', '3rd Floor', 12000];
    $apartments[] = ['F3-7', '3rd Floor', 12000];
    $apartments[] = ['F3-8', '3rd Floor', 7000];
    $apartments[] = ['F3-9', '3rd Floor', 7000];
    $apartments[] = ['F3-10', '3rd Floor', 7000];
    $apartments[] = ['F3-11', '3rd Floor', 7000];
    $apartments[] = ['F3-12', '3rd Floor', 7000];
    $apartments[] = ['F3-13', '3rd Floor', 12000];
    
    // 4th Floor (F4-1 to F4-13)
    $apartments[] = ['F4-1', '4th Floor', 12000];
    $apartments[] = ['F4-2', '4th Floor', 7000];
    $apartments[] = ['F4-3', '4th Floor', 7000];
    $apartments[] = ['F4-4', '4th Floor', 7000];
    $apartments[] = ['F4-5', '4th Floor', 7000];
    $apartments[] = ['F4-6', '4th Floor', 12000];
    $apartments[] = ['F4-7', '4th Floor', 12500];
    $apartments[] = ['F4-8', '4th Floor', 7000];
    $apartments[] = ['F4-9', '4th Floor', 7000];
    $apartments[] = ['F4-10', '4th Floor', 7000];
    $apartments[] = ['F4-11', '4th Floor', 7000];
    $apartments[] = ['F4-12', '4th Floor', 7000];
    $apartments[] = ['F4-13', '4th Floor', 12500];
    
    // 5th Floor (F5-1 to F5-13)
    $apartments[] = ['F5-1', '5th Floor', 12000];
    $apartments[] = ['F5-2', '5th Floor', 7000];
    $apartments[] = ['F5-3', '5th Floor', 7000];
    $apartments[] = ['F5-4', '5th Floor', 7000];
    $apartments[] = ['F5-5', '5th Floor', 7000];
    $apartments[] = ['F5-6', '5th Floor', 12500];
    $apartments[] = ['F5-7', '5th Floor', 12000];
    $apartments[] = ['F5-8', '5th Floor', 7000];
    $apartments[] = ['F5-9', '5th Floor', 7000];
    $apartments[] = ['F5-10', '5th Floor', 7000];
    $apartments[] = ['F5-11', '5th Floor', 7000];
    $apartments[] = ['F5-12', '5th Floor', 7000];
    $apartments[] = ['F5-13', '5th Floor', 12000];
    
    // Penthouse (P1-P5)
    $apartments[] = ['P1', 'Penthouse', 12000];
    $apartments[] = ['P2', 'Penthouse', 7000];
    $apartments[] = ['P3', 'Penthouse', 7000];
    $apartments[] = ['P4', 'Penthouse', 7000];
    $apartments[] = ['P5', 'Penthouse', 7000];
    
    // Insert all apartments
    $stmt = $conn->prepare("INSERT INTO apartments (apartment_number, building_name, rent_amount) VALUES (?, ?, ?)");
    
    foreach ($apartments as $apt) {
        $stmt->bind_param("ssd", $apt[0], $apt[1], $apt[2]);
        $stmt->execute();
    }
    
    // Sync occupancy after generating apartments
    syncApartmentOccupancy($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Apartments</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            padding: 20px 0;
            text-align: center;
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h2 {
            font-weight: 600;
            margin: 0;
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
            background: #dc3545;
        }
        
        .logout a:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .back a {
            background: #6c757d;
        }
        
        .back a:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 30px;
            margin-top: 20px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid #007bff;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            color: #6c757d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: 700;
            color: #007bff;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        
        .btn {
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            text-align: center;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }
        
        .floor-section {
            margin-bottom: 30px;
        }
        
        .floor-header {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .floor-header h3 {
            margin: 0;
            font-weight: 600;
        }
        
        .floor-stats {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        
        .apartments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .apartment-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 20px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .apartment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .apartment-card.occupied {
            border-left: 4px solid #28a745;
        }
        
        .apartment-card.vacant {
            border-left: 4px solid #dc3545;
        }
        
        .apartment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .apartment-number {
            font-size: 1.3em;
            font-weight: 700;
            color: #007bff;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-occupied {
            background: #d4edda;
            color: #155724;
        }
        
        .status-vacant {
            background: #f8d7da;
            color: #721c24;
        }
        
        .rent-amount {
            font-size: 1.1em;
            font-weight: 600;
            color: #28a745;
            margin-bottom: 15px;
        }
        
        .no-rent {
            color: #6c757d;
            font-style: italic;
        }
        
        .tenant-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .primary-tenant {
            border-left: 3px solid #007bff;
        }
        
        .secondary-tenant {
            border-left: 3px solid #6c757d;
        }
        
        .tenant-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .role-primary {
            background: #007bff;
            color: white;
        }
        
        .role-secondary {
            background: #6c757d;
            color: white;
        }
        
        .tenant-details {
            font-size: 0.85em;
            color: #6c757d;
            line-height: 1.4;
        }
        
        .apartment-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .apartment-actions .btn {
            padding: 8px 12px;
            font-size: 0.8em;
            flex: 1;
            min-width: 70px;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .header {
                padding: 15px 0;
            }
            
            .logout, .back {
                position: static;
                display: inline-block;
                margin: 5px;
            }
            
            .header h2 {
                margin: 10px 0;
            }
            
            .stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .apartments-grid {
                grid-template-columns: 1fr;
            }
            
            .floor-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="back"><a href="admin_dashboard.php">← Back to Dashboard</a></div>
        <h2>Manage Apartments</h2>
        <div class="logout"><a href="admin_logout.php">Logout</a></div>
    </div>
    
    <div class="content">
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Quick Stats -->
        <div class="stats">
            <?php
            $total_apartments = $conn->query("SELECT COUNT(*) as total FROM apartments")->fetch_assoc()['total'];
            $occupied_apartments = $conn->query("SELECT COUNT(*) as total FROM apartments WHERE is_occupied = 1")->fetch_assoc()['total'];
            $vacant_apartments = $total_apartments - $occupied_apartments;
            $monthly_revenue = $conn->query("SELECT SUM(rent_amount) as total FROM apartments WHERE is_occupied = 1 AND rent_amount > 0")->fetch_assoc()['total'];
            
            // Get tenant statistics
            $total_tenants = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_verified = 1 AND is_admin = 0")->fetch_assoc()['total'];
            $primary_tenants = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_verified = 1 AND is_primary_tenant = 1")->fetch_assoc()['total'];
            ?>
            <div class="stat-card">
                <h3>Total Apartments</h3>
                <div class="stat-number"><?= $total_apartments ?></div>
            </div>
            <div class="stat-card">
                <h3>Occupied</h3>
                <div class="stat-number" style="color: #28a745;"><?= $occupied_apartments ?></div>
            </div>
            <div class="stat-card">
                <h3>Vacant</h3>
                <div class="stat-number" style="color: #dc3545;"><?= $vacant_apartments ?></div>
            </div>
            <div class="stat-card">
                <h3>Monthly Revenue</h3>
                <div class="stat-number" style="color: #17a2b8;">KSh <?= number_format($monthly_revenue ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Tenants</h3>
                <div class="stat-number" style="color: #6c757d;"><?= $total_tenants ?></div>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="add_apartment.php" class="btn btn-success">Add Single Apartment</a>
            <form method="POST" style="display: inline;">
                <button type="submit" name="generate_apartments" class="btn btn-info" onclick="return confirm('This will delete all existing apartments and regenerate them. Continue?')">
                    Generate All Apartments
                </button>
            </form>
            <form method="POST" style="display: inline;">
                <button type="submit" name="sync_occupancy" class="btn btn-secondary">
                    Sync Occupancy with Tenants
                </button>
            </form>
            <a href="vacant_apartments.php" class="btn btn-primary">View Vacant Apartments</a>
            <a href="tenant_units.php" class="btn btn-warning">View Tenant Units</a>
        </div>

        <?php
        // Group apartments by floor in correct order
        $floors = [
            'Ground Floor' => [],
            '1st Floor' => [],
            '2nd Floor' => [],
            '3rd Floor' => [],
            '4th Floor' => [],
            '5th Floor' => [],
            'Penthouse' => []
        ];
        
        if ($apartments_result->num_rows > 0) {
            while($apt = $apartments_result->fetch_assoc()) {
                $floors[$apt['building_name']][] = $apt;
            }
        }
        
        foreach ($floors as $floor_name => $floor_apartments): 
            if (!empty($floor_apartments)):
                $occupied_count = 0;
                $vacant_count = 0;
                foreach ($floor_apartments as $apt) {
                    if ($apt['is_occupied']) {
                        $occupied_count++;
                    } else {
                        $vacant_count++;
                    }
                }
        ?>
        <div class="floor-section">
            <div class="floor-header">
                <h3><?= $floor_name ?></h3>
                <div class="floor-stats">
                    <?= count($floor_apartments) ?> units • 
                    <span style="color: #28a745;"><?= $occupied_count ?> occupied</span> • 
                    <span style="color: #dc3545;"><?= $vacant_count ?> vacant</span>
                </div>
            </div>
            
            <div class="apartments-grid">
                <?php foreach ($floor_apartments as $apt): ?>
                <div class="apartment-card <?= $apt['is_occupied'] ? 'occupied' : 'vacant' ?>">
                    <div class="apartment-header">
                        <div class="apartment-number"><?= htmlspecialchars($apt['apartment_number']) ?></div>
                        <div class="status-badge <?= $apt['is_occupied'] ? 'status-occupied' : 'status-vacant' ?>">
                            <?= $apt['is_occupied'] ? 'Occupied' : 'Vacant' ?>
                        </div>
                    </div>
                    
                    <div class="rent-amount">
                        <?php if ($apt['rent_amount'] == 0): ?>
                            <span class="no-rent">No Rent</span>
                        <?php else: ?>
                            KSh <?= number_format($apt['rent_amount']) ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($apt['tenant_name']): ?>
                        <div class="tenant-info <?= $apt['is_primary_tenant'] ? 'primary-tenant' : 'secondary-tenant' ?>">
                            <div class="tenant-name">
                                <?= htmlspecialchars($apt['tenant_name']) ?>
                                <span class="role-badge <?= $apt['is_primary_tenant'] ? 'role-primary' : 'role-secondary' ?>">
                                    <?= $apt['is_primary_tenant'] ? 'Primary' : ucfirst($apt['unit_role']) ?>
                                </span>
                            </div>
                            <div class="tenant-details">
                                <?= htmlspecialchars($apt['tenant_email']) ?><br>
                                <?= htmlspecialchars($apt['tenant_phone']) ?><br>
                                <small>Since: <?= date('M Y', strtotime($apt['tenant_since'])) ?></small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="color: #6c757d; font-style: italic; margin: 10px 0;">
                            No tenant assigned
                        </div>
                    <?php endif; ?>
                    
                    <div class="apartment-actions">
                        <a href="edit_apartment.php?id=<?= $apt['id'] ?>" class="btn btn-primary">Edit</a>
                        <?php if ($apt['is_occupied']): ?>
                            <a href="view_tenant_details.php?unit=<?= $apt['apartment_number'] ?>" class="btn btn-info">View Tenants</a>
                        <?php else: ?>
                            <a href="assign_tenant.php?unit=<?= $apt['apartment_number'] ?>" class="btn btn-success">Assign Tenant</a>
                        <?php endif; ?>
                        <a href="manage_apartments.php?delete_id=<?= $apt['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this apartment?')">Delete</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php 
            endif;
        endforeach; 
        ?>
        
        <?php if ($apartments_result->num_rows == 0): ?>
        <div class="alert alert-info">
            <h3>No Apartments Found</h3>
            <p>Click "Generate All Apartments" to create all apartments based on your building structure, or "Add Single Apartment" to add them one by one.</p>
            <form method="POST">
                <button type="submit" name="generate_apartments" class="btn btn-success">
                    Generate All Apartments Now
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
