<?php
session_start();

// Protect this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../auth/db_config.php";

$message = "";
$error = "";

// Get tenant details if tenant_id is provided
$tenant = null;
if (isset($_GET['tenant_id'])) {
    $tenant_id = intval($_GET['tenant_id']);
    $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND is_admin = 0");
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc();
}

// Get available apartments
$apartments_result = $conn->query("SELECT id, apartment_number, building_name, rent_amount FROM apartments WHERE is_occupied = 0");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tenant_id = intval($_POST['tenant_id']);
    $apartment_id = intval($_POST['apartment_id']);
    
    // Assign apartment to tenant
    $stmt = $conn->prepare("UPDATE apartments SET tenant_id = ?, is_occupied = 1 WHERE id = ?");
    $stmt->bind_param("ii", $tenant_id, $apartment_id);
    
    if ($stmt->execute()) {
        $message = "Apartment assigned successfully!";
    } else {
        $error = "Error assigning apartment: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Apartment</title>
    <style>
        body {font-family: Arial, sans-serif; background: #f6f8fa; margin: 0;}
        .header {background: #007bff; color: #fff; padding: 15px; text-align: center; position: relative;}
        .content {padding: 20px; max-width: 600px; margin: 0 auto;}
        .logout {position: absolute; top: 15px; right: 20px;}
        .logout a {color: white; text-decoration: none; background: #dc3545; padding: 6px 10px; border-radius: 4px;}
        .back {position: absolute; top: 15px; left: 20px;}
        .back a {color: white; text-decoration: none; background: #6c757d; padding: 6px 10px; border-radius: 4px;}
        
        .form-group {margin-bottom: 15px;}
        label {display: block; margin-bottom: 5px; font-weight: bold;}
        select, input {width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;}
        .btn {padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;}
        .btn:hover {background: #0056b3;}
        
        .alert {padding: 10px; margin: 10px 0; border-radius: 4px;}
        .alert-success {background: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .alert-error {background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        
        .tenant-info {background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px;}
    </style>
</head>
<body>
    <div class="header">
        <div class="back"><a href="manage_tenants.php">← Back to Tenants</a></div>
        <h2>Assign Apartment</h2>
        <div class="logout"><a href="admin_logout.php">Logout</a></div>
    </div>
    
    <div class="content">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($tenant): ?>
        <div class="tenant-info">
            <h3>Assigning apartment for: <?= htmlspecialchars($tenant['full_name']) ?></h3>
            <p>Email: <?= htmlspecialchars($tenant['email']) ?></p>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <?php if (!$tenant): ?>
            <div class="form-group">
                <label>Select Tenant:</label>
                <select name="tenant_id" required>
                    <option value="">Select a tenant</option>
                    <?php
                    $tenants = $conn->query("SELECT id, full_name FROM users WHERE is_admin = 0");
                    while($t = $tenants->fetch_assoc()): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Select Apartment:</label>
                <select name="apartment_id" required>
                    <option value="">Select an apartment</option>
                    <?php while($apt = $apartments_result->fetch_assoc()): ?>
                    <option value="<?= $apt['id'] ?>">
                        <?= htmlspecialchars($apt['apartment_number']) ?> - 
                        <?= htmlspecialchars($apt['building_name']) ?> - 
                        KSh <?= number_format($apt['rent_amount']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <button type="submit" class="btn">Assign Apartment</button>
        </form>
    </div>
</body>
</html>
