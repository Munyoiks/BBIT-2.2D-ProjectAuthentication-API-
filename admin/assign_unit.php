<?php
// Include database connection
include_once '../auth/db_config.php';

// Check if tenant ID is provided
if (!isset($_GET['tenant_id']) || empty($_GET['tenant_id'])) {
    header("Location: manage_tenants.php");
    exit();
}

$tenant_id = $_GET['tenant_id'];

// Fetch tenant details
$tenant_sql = "SELECT * FROM users WHERE id = ? AND role = 'tenant'";
$tenant_stmt = $conn->prepare($tenant_sql);
$tenant_stmt->bind_param("i", $tenant_id);
$tenant_stmt->execute();
$tenant_result = $tenant_stmt->get_result();
$tenant = $tenant_result->fetch_assoc();
$tenant_stmt->close();

if (!$tenant) {
    header("Location: manage_tenants.php?error=Tenant not found");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_number = $_POST['unit_number'];
    $unit_role = $_POST['unit_role'];
    $is_primary_tenant = isset($_POST['is_primary_tenant']) ? 1 : 0;
    
    // Update tenant unit information
    $update_sql = "UPDATE users SET unit_number = ?, unit_role = ?, is_primary_tenant = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssii", $unit_number, $unit_role, $is_primary_tenant, $tenant_id);
    
    if ($update_stmt->execute()) {
        $message = "Unit assigned successfully!";
        // Refresh tenant data
        $tenant['unit_number'] = $unit_number;
        $tenant['unit_role'] = $unit_role;
        $tenant['is_primary_tenant'] = $is_primary_tenant;
    } else {
        $error = "Error assigning unit: " . $conn->error;
    }
    $update_stmt->close();
}

// Define available apartments
$apartments = [];
// Ground Floor (G1 to G14)
$apartments[] = ['G1', 'Ground Floor'];
$apartments[] = ['G2', 'Ground Floor'];
$apartments[] = ['G3', 'Ground Floor'];
$apartments[] = ['G4', 'Ground Floor'];
$apartments[] = ['G5', 'Ground Floor'];
$apartments[] = ['G6', 'Ground Floor'];
$apartments[] = ['G7', 'Ground Floor'];
$apartments[] = ['G8', 'Ground Floor'];
$apartments[] = ['G9', 'Ground Floor'];
$apartments[] = ['G10', 'Ground Floor'];
$apartments[] = ['G11', 'Ground Floor'];
$apartments[] = ['G12', 'Ground Floor'];
$apartments[] = ['G13', 'Ground Floor'];
$apartments[] = ['G14', 'Ground Floor'];

// 1st Floor (F1-1 to F1-13)
$apartments[] = ['F1-1', '1st Floor'];
$apartments[] = ['F1-2', '1st Floor'];
$apartments[] = ['F1-3', '1st Floor'];
$apartments[] = ['F1-4', '1st Floor'];
$apartments[] = ['F1-5', '1st Floor'];
$apartments[] = ['F1-6', '1st Floor'];
$apartments[] = ['F1-7', '1st Floor'];
$apartments[] = ['F1-8', '1st Floor'];
$apartments[] = ['F1-9', '1st Floor']; // Office
$apartments[] = ['F1-10', '1st Floor'];
$apartments[] = ['F1-11', '1st Floor']; // Caretaker
$apartments[] = ['F1-12', '1st Floor'];
$apartments[] = ['F1-13', '1st Floor'];

// 2nd Floor (F2-1 to F2-13)
$apartments[] = ['F2-1', '2nd Floor'];
$apartments[] = ['F2-2', '2nd Floor'];
$apartments[] = ['F2-3', '2nd Floor'];
$apartments[] = ['F2-4', '2nd Floor'];
$apartments[] = ['F2-5', '2nd Floor'];
$apartments[] = ['F2-6', '2nd Floor'];
$apartments[] = ['F2-7', '2nd Floor'];
$apartments[] = ['F2-8', '2nd Floor'];
$apartments[] = ['F2-9', '2nd Floor'];
$apartments[] = ['F2-10', '2nd Floor'];
$apartments[] = ['F2-11', '2nd Floor'];
$apartments[] = ['F2-12', '2nd Floor'];
$apartments[] = ['F2-13', '2nd Floor'];

// 3rd Floor (F3-1 to F3-13)
$apartments[] = ['F3-1', '3rd Floor'];
$apartments[] = ['F3-2', '3rd Floor'];
$apartments[] = ['F3-3', '3rd Floor'];
$apartments[] = ['F3-4', '3rd Floor'];
$apartments[] = ['F3-5', '3rd Floor'];
$apartments[] = ['F3-6', '3rd Floor'];
$apartments[] = ['F3-7', '3rd Floor'];
$apartments[] = ['F3-8', '3rd Floor'];
$apartments[] = ['F3-9', '3rd Floor'];
$apartments[] = ['F3-10', '3rd Floor'];
$apartments[] = ['F3-11', '3rd Floor'];
$apartments[] = ['F3-12', '3rd Floor'];
$apartments[] = ['F3-13', '3rd Floor'];

// 4th Floor (F4-1 to F4-13)
$apartments[] = ['F4-1', '4th Floor'];
$apartments[] = ['F4-2', '4th Floor'];
$apartments[] = ['F4-3', '4th Floor'];
$apartments[] = ['F4-4', '4th Floor'];
$apartments[] = ['F4-5', '4th Floor'];
$apartments[] = ['F4-6', '4th Floor'];
$apartments[] = ['F4-7', '4th Floor'];
$apartments[] = ['F4-8', '4th Floor'];
$apartments[] = ['F4-9', '4th Floor'];
$apartments[] = ['F4-10', '4th Floor'];
$apartments[] = ['F4-11', '4th Floor'];
$apartments[] = ['F4-12', '4th Floor'];
$apartments[] = ['F4-13', '4th Floor'];

// 5th Floor (F5-1 to F5-13)
$apartments[] = ['F5-1', '5th Floor'];
$apartments[] = ['F5-2', '5th Floor'];
$apartments[] = ['F5-3', '5th Floor'];
$apartments[] = ['F5-4', '5th Floor'];
$apartments[] = ['F5-5', '5th Floor'];
$apartments[] = ['F5-6', '5th Floor'];
$apartments[] = ['F5-7', '5th Floor'];
$apartments[] = ['F5-8', '5th Floor'];
$apartments[] = ['F5-9', '5th Floor'];
$apartments[] = ['F5-10', '5th Floor'];
$apartments[] = ['F5-11', '5th Floor'];
$apartments[] = ['F5-12', '5th Floor'];
$apartments[] = ['F5-13', '5th Floor'];

// Penthouse (P1-P5)
$apartments[] = ['P1', 'Penthouse'];
$apartments[] = ['P2', 'Penthouse'];
$apartments[] = ['P3', 'Penthouse'];
$apartments[] = ['P4', 'Penthouse'];
$apartments[] = ['P5', 'Penthouse'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Unit - <?= htmlspecialchars($tenant['full_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ... (keep all the existing CSS styles from previous assign_unit.php) ... */
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
            max-width: 800px;
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
        
        .tenant-info {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 4px solid #4a6ee0;
        }
        
        .tenant-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a6ee0, #2c52c9);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            font-weight: bold;
            margin-right: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4a6ee0;
            box-shadow: 0 0 0 3px rgba(74, 110, 224, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1em;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #4a6ee0;
            box-shadow: 0 0 0 3px rgba(74, 110, 224, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            font-weight: 500;
            color: #2c3e50;
            cursor: pointer;
        }
        
        .btn {
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 1em;
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
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
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
        
        .current-assignment {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .current-assignment h4 {
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .unit-option {
            display: flex;
            justify-content: space-between;
        }
        
        .unit-floor {
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
            
            .tenant-info {
                flex-direction: column;
                text-align: center;
            }
            
            .tenant-avatar {
                margin-right: 0;
                margin-bottom: 15px;
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
        <div class="back"><a href="view_tenant_details.php?id=<?= $tenant_id ?>"><i class="fas fa-arrow-left"></i> Back to Tenant</a></div>
        <h2>Assign Unit</h2>
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
        
        <div class="card">
            <div class="tenant-info">
                <div class="tenant-avatar">
                    <?= strtoupper(substr($tenant['full_name'], 0, 1)) ?>
                </div>
                <div>
                    <h3><?= htmlspecialchars($tenant['full_name']) ?></h3>
                    <p>Email: <?= htmlspecialchars($tenant['email']) ?> | Phone: <?= htmlspecialchars($tenant['phone'] ?? 'N/A') ?></p>
                </div>
            </div>
            
            <?php if (!empty($tenant['unit_number'])): ?>
                <div class="current-assignment">
                    <h4><i class="fas fa-info-circle"></i> Current Assignment</h4>
                    <p><strong>Unit:</strong> <?= htmlspecialchars($tenant['unit_number']) ?></p>
                    <p><strong>Role:</strong> <?= ucfirst($tenant['unit_role'] ?? 'primary') ?></p>
                    <p><strong>Primary Tenant:</strong> <?= $tenant['is_primary_tenant'] ? 'Yes' : 'No' ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="unit_number" class="form-label">Unit Number *</label>
                    <select name="unit_number" id="unit_number" class="form-select" required>
                        <option value="">Select a unit</option>
                        <?php foreach($apartments as $apartment): ?>
                            <option value="<?= $apartment[0] ?>" <?= ($tenant['unit_number'] ?? '') === $apartment[0] ? 'selected' : '' ?>>
                                <span class="unit-option">
                                    <span class="unit-number"><?= $apartment[0] ?></span>
                                    <span class="unit-floor">(<?= $apartment[1] ?>)</span>
                                </span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #7f8c8d; margin-top: 5px; display: block;">
                        Select the apartment/unit number for this tenant
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="unit_role" class="form-label">Unit Role *</label>
                    <select name="unit_role" id="unit_role" class="form-select" required>
                        <option value="primary" <?= ($tenant['unit_role'] ?? 'primary') === 'primary' ? 'selected' : '' ?>>Primary Tenant</option>
                        <option value="secondary" <?= ($tenant['unit_role'] ?? '') === 'secondary' ? 'selected' : '' ?>>Secondary Tenant</option>
                        <option value="family" <?= ($tenant['unit_role'] ?? '') === 'family' ? 'selected' : '' ?>>Family Member</option>
                        <option value="roommate" <?= ($tenant['unit_role'] ?? '') === 'roommate' ? 'selected' : '' ?>>Roommate</option>
                    </select>
                    <small style="color: #7f8c8d; margin-top: 5px; display: block;">
                        Defines the tenant's role within the household
                    </small>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_primary_tenant" id="is_primary_tenant" value="1" 
                        <?= ($tenant['is_primary_tenant'] ?? 0) ? 'checked' : '' ?>>
                    <label for="is_primary_tenant">This tenant is the primary tenant for the unit</label>
                </div>
                <small style="color: #7f8c8d; margin-top: -10px; display: block; margin-bottom: 20px;">
                    Primary tenants can manage household members and receive official communications
                </small>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Assignment
                    </button>
                    <a href="view_tenant_details.php?id=<?= $tenant_id ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-check primary tenant if role is set to primary
        document.getElementById('unit_role').addEventListener('change', function() {
            if (this.value === 'primary') {
                document.getElementById('is_primary_tenant').checked = true;
            }
        });
        
        // Show confirmation when changing primary tenant status
        document.getElementById('is_primary_tenant').addEventListener('change', function() {
            if (this.checked) {
                const unitRole = document.getElementById('unit_role').value;
                if (unitRole !== 'primary') {
                    if (confirm('Setting this tenant as primary will change their role to "Primary Tenant". Continue?')) {
                        document.getElementById('unit_role').value = 'primary';
                    } else {
                        this.checked = false;
                    }
                }
            }
        });
    </script>
</body>
</html>
