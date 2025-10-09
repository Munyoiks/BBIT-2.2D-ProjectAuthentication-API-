<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Database connection (use the same as in dashboard.php)
$host = 'localhost';
$dbname = 'your_database_name';
$username = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch user data
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT full_name, email, phone, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $full_name = $user['full_name'];
        $email = $user['email'];
        $phone = $user['phone'] ?? 'Not set';
        $member_since = date('F Y', strtotime($user['created_at']));
    } else {
        $full_name = $_SESSION['full_name'] ?? 'Tenant';
        $email = $_SESSION['email'] ?? '';
        $phone = 'Not set';
        $member_since = 'Unknown';
    }
} catch (PDOException $e) {
    $full_name = $_SESSION['full_name'] ?? 'Tenant';
    $email = $_SESSION['email'] ?? '';
    $phone = 'Not set';
    $member_since = 'Unknown';
    error_log("Database error: " . $e->getMessage());
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Handle profile update
        $new_full_name = $_POST['full_name'] ?? '';
        $new_phone = $_POST['phone'] ?? '';
        
        if (!empty($new_full_name)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
                $stmt->execute([$new_full_name, $new_phone, $user_id]);
                $success_message = "Profile updated successfully!";
                $_SESSION['full_name'] = $new_full_name;
                $full_name = $new_full_name;
                $phone = $new_phone;
            } catch (PDOException $e) {
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Handle password change
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($new_password === $confirm_password) {
            // Verify current password and update (you'll need to implement proper password verification)
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $success_message = "Password changed successfully!";
            } catch (PDOException $e) {
                $error_message = "Error changing password: " . $e->getMessage();
            }
        } else {
            $error_message = "New passwords do not match!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mojo Tenant System | Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f8;
        }
        .sidebar {
            height: 100vh;
            background: #0d6efd;
            color: white;
            padding: 20px;
            position: fixed;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 8px;
        }
        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .content {
            margin-left: 220px;
            padding: 40px;
        }
        .card {
            border-radius: 10px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h4>🏠 Mojo Tenant</h4>
    <p>Welcome, <strong><?= htmlspecialchars($full_name) ?></strong></p>
    <hr>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="profile.php">👤 Profile</a>
    <a href="rent.php">💰 Rent & Payments</a>
    <a href="maintenance.php">🛠️ Maintenance</a>
    <a href="announcements.php">📢 Announcements</a>
    <a href="messages.php">💬 Messages</a>
    <a href="settings.php">⚙️ Settings</a>
    <a href="logout.php" class="text-danger">🚪 Logout</a>
</div>

<div class="content">
    <h2 class="mb-4">Account Settings</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card p-4 shadow-sm mb-4">
                <h5>Profile Information</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($full_name) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($email) ?>" disabled>
                        <small class="text-muted">Contact administrator to change email</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($phone) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Member Since</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($member_since) ?>" disabled>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-4 shadow-sm">
                <h5>Change Password</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                </form>
            </div>

            <div class="card p-4 shadow-sm mt-4">
                <h5>Notification Preferences</h5>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                    <label class="form-check-label" for="emailNotifications">
                        Email notifications
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="smsNotifications">
                    <label class="form-check-label" for="smsNotifications">
                        SMS notifications
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rentReminders" checked>
                    <label class="form-check-label" for="rentReminders">
                        Rent payment reminders
                    </label>
                </div>
                <button class="btn btn-secondary mt-3">Save Preferences</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>