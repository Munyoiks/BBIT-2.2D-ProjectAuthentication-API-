//vacant_apartments.php
<?php
session_start();

// Protect this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../auth/db_config.php";

// Get vacant apartments
$vacant_query = "SELECT * FROM apartments WHERE is_occupied = 0 ORDER BY building_name, apartment_number";
$vacant_result = $conn->query($vacant_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vacant Apartments</title>
    <style>
        body {font-family: Arial, sans-serif; background: #f6f8fa; margin: 0;}
        .header {background: #007bff; color: #fff; padding: 15px; text-align: center; position: relative;}
        .content {padding: 20px;}
        .logout {position: absolute; top: 15px; right: 20px;}
        .logout a {color: white; text-decoration: none; background: #dc3545; padding: 6px 10px; border-radius: 4px;}
        .back {position: absolute; top: 15px; left: 20px;}
        .back a {color: white; text-decoration: none; background: #6c757d; padding: 6px 10px; border-radius: 4px;}
        
        .apartments-grid {display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;}
        .apartment-card {background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #dc3545;}
        .apartment-card h3 {margin-top: 0; color: #dc3545;}
        .apartment-info {margin: 10px 0;}
        
        .btn {padding: 8px 15px; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px;}
        .btn-success {background: #28a745; color: white;}
    </style>
</head>
<body>
    <div class="header">
        <div class="back"><a href="manage_apartments.php">← Back to Apartments</a></div>
        <h2>Vacant Apartments</h2>
        <div class="logout"><a href="admin_logout.php">Logout</a></div>
    </div>
    
    <div class="content">
        <h3>Available Apartments (<?= $vacant_result->num_rows ?>)</h3>
        
        <?php if ($vacant_result->num_rows > 0): ?>
        <div class="apartments-grid">
            <?php while($apt = $vacant_result->fetch_assoc()): ?>
            <div class="apartment-card">
                <h3><?= htmlspecialchars($apt['apartment_number']) ?></h3>
                <div class="apartment-info">
                    <strong>Building:</strong> <?= htmlspecialchars($apt['building_name']) ?>
                </div>
                <div class="apartment-info">
                    <strong>Monthly Rent:</strong> KSh <?= number_format($apt['rent_amount']) ?>
                </div>
                <div class="apartment-info">
                    <strong>Status:</strong> <span style="color: #dc3545; font-weight: bold;">Vacant</span>
                </div>
                <a href="assign_apartment.php" class="btn btn-success">Assign to Tenant</a>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <p>No vacant apartments available.</p>
        <?php endif; ?>
    </div>
</body>
</html>
