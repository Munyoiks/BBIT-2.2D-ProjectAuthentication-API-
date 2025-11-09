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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $error = "Email already exists!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, is_verified, is_admin) VALUES (?, ?, ?, ?, 1, 0)");
        $stmt->bind_param("ssss", $full_name, $email, $phone, $password);
        
        if ($stmt->execute()) {
            $message = "Tenant added successfully!";
            // Clear form
            $full_name = $email = $phone = "";
        } else {
            $error = "Error adding tenant: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Tenant</title>
    <style>
        body {font-family: Arial, sans-serif; background: #f6f8fa; margin: 0;}
        .header {background: #007bff; color: #fff; padding: 15px; text-align: center; position: relative;}
        .content {padding: 20px; max-width: 500px; margin: 0 auto;}
        .logout {position: absolute; top: 15px; right: 20px;}
        .logout a {color: white; text-decoration: none; background: #dc3545; padding: 6px 10px; border-radius: 4px;}
        .back {position: absolute; top: 15px; left: 20px;}
        .back a {color: white; text-decoration: none; background: #6c757d; padding: 6px 10px; border-radius: 4px;}
        
        .form-group {margin-bottom: 15px;}
        label {display: block; margin-bottom: 5px; font-weight: bold;}
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;
        }
        .btn {padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;}
        .btn:hover {background: #0056b3;}
        
        .alert {padding: 10px; margin: 10px 0; border-radius: 4px;}
        .alert-success {background: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .alert-error {background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
    </style>
</head>
<body>
    <div class="header">
        <div class="back"><a href="manage_tenants.php">← Back to Tenants</a></div>
        <h2>Add New Tenant</h2>
        <div class="logout"><a href="admin_logout.php">Logout</a></div>
    </div>
    
    <div class="content">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name:</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number:</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">Add Tenant</button>
        </form>
    </div>
</body>
</html>
