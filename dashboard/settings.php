<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'auth_db';
$username = 'root';
$password = 'munyoiks7';

$success_message = '';
$error_message = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch user data from database
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT full_name, email, phone, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $full_name = $user['full_name'];
        $email = $user['email'];
        $phone = $user['phone'] ?? 'Not set';
        $created_at = $user['created_at'];
        
        // Update session with latest data
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
    } else {
        // Fallback if user not found
        $full_name = $_SESSION['full_name'] ?? 'Tenant';
        $email = $_SESSION['email'] ?? '';
        $phone = 'Not set';
        $created_at = date('Y-m-d');
    }

    // Handle name and profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $new_name = trim($_POST['full_name']);
        $new_phone = trim($_POST['phone']);
        $notification_preferences = isset($_POST['email_notifications']) ? 1 : 0;
        
        // Validate name
        if (empty($new_name)) {
            $error_message = "Full name cannot be empty.";
        } elseif (strlen($new_name) < 2) {
            $error_message = "Full name must be at least 2 characters long.";
        } else {
            // Update user data in database
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$new_name, $new_phone, $user_id]);
            
            // Update session and local variables
            $_SESSION['full_name'] = $new_name;
            $full_name = $new_name;
            $phone = $new_phone;
            
            $success_message = "Profile updated successfully! Your name has been changed.";
        }
    }

    // Handle password change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($current_password, $user_data['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "New password must be at least 6 characters long.";
                }
            } else {
                $error_message = "New passwords do not match.";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    }

} catch (PDOException $e) {
    // Fallback to session data if database connection fails
    $full_name = $_SESSION['full_name'] ?? 'Tenant';
    $email = $_SESSION['email'] ?? '';
    $phone = 'Not set';
    $created_at = date('Y-m-d');
    error_log("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mojo Tenant System | Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0d6efd;
            --primary-dark: #0b5ed7;
            --secondary: #6c757d;
            --success: #198754;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 280px;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        .sidebar {
            height: 100vh;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0;
            position: fixed;
            width: var(--sidebar-width);
            box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 700;
        }

        .user-info {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }

        .user-info p {
            margin: 0;
        }

        .sidebar-nav {
            padding: 15px 0;
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar-nav a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px 20px;
            margin: 3px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            flex-shrink: 0;
        }

        .sidebar-nav a i {
            width: 24px;
            margin-right: 12px;
            text-align: center;
            font-size: 1.1rem;
        }

        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.2);
        }

        .sidebar-nav a.logout {
            color: #ff6b6b;
            margin-top: auto;
            margin-bottom: 20px;
        }

        .content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }

        .settings-header {
            margin-bottom: 30px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(120deg, #f8f9fa, #e9ecef);
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .strength-weak { background-color: #dc3545; width: 25%; }
        .strength-fair { background-color: #ffc107; width: 50%; }
        .strength-good { background-color: #20c997; width: 75%; }
        .strength-strong { background-color: #198754; width: 100%; }

        .settings-section {
            margin-bottom: 2rem;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--primary);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        /* Custom scrollbar for sidebar */
        .sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .content {
                margin-left: 0;
            }

            .sidebar-nav {
                max-height: 300px;
            }
        }

        /* Compact menu items */
        .sidebar-nav a {
            min-height: 44px;
        }

        .name-change-notice {
            background: linear-gradient(120deg, #e3f2fd, #f3e5f5);
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-home me-2"></i>Mojo Tenant</h4>
    </div>
    
    <div class="user-info">
        <p>Welcome, <strong><?= htmlspecialchars($full_name) ?></strong></p>
        <small class="text-light"><?= htmlspecialchars($email) ?></small>
    </div>
    
    <div class="sidebar-nav">
        <a href="dashboard.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="profile.php">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="rent.php">
            <i class="fas fa-money-bill-wave"></i> Rent & Payments
        </a>
        <a href="maintenance.php">
            <i class="fas fa-tools"></i> Maintenance
        </a>
        <a href="announcements.php">
            <i class="fas fa-bullhorn"></i> Announcements
        </a>
        <a href="messages.php">
            <i class="fas fa-comments"></i> Messages
        </a>
        <a href="settings.php" class="active">
            <i class="fas fa-cog"></i> Settings
        </a>
        <a href="logout.php" class="logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<div class="content">
    <div class="settings-header">
        <h2 class="mb-2">Account Settings</h2>
        <p class="text-muted">Manage your account preferences and security</p>
    </div>

    <!-- Alert Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <div class="name-change-notice">
            <i class="fas fa-info-circle me-2 text-primary"></i>
            <strong>Name Updated!</strong> Your name has been changed to "<strong><?= htmlspecialchars($full_name) ?></strong>". 
            This change will be reflected across your dashboard and all other pages immediately.
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Settings -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-cog me-2"></i>Profile Settings
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?= htmlspecialchars($full_name) ?>" 
                                   placeholder="Enter your full name" required>
                            <small class="form-text text-muted">This name will be displayed across your dashboard and profile</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($email) ?>" readonly>
                            <small class="form-text text-muted">Primary email cannot be changed</small>
                        </div>

                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($phone) ?>" 
                                   placeholder="Enter your phone number">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Account Created</label>
                            <input type="text" class="form-control" value="<?= date('F j, Y', strtotime($created_at)) ?>" readonly>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Notification Preferences -->
            <div class="card mt-4">
                <div class="card-header">
                    <i class="fas fa-bell me-2"></i>Notification Preferences
                </div>
                <div class="card-body">
                    <div class="form-group d-flex justify-content-between align-items-center">
                        <div>
                            <label class="form-label">Email Notifications</label>
                            <p class="text-muted mb-0">Receive updates via email</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notifications" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="form-group d-flex justify-content-between align-items-center">
                        <div>
                            <label class="form-label">SMS Notifications</label>
                            <p class="text-muted mb-0">Receive important alerts via SMS</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="sms_notifications">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="form-group d-flex justify-content-between align-items-center">
                        <div>
                            <label class="form-label">Rent Reminders</label>
                            <p class="text-muted mb-0">Get reminded before rent is due</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="rent_reminders" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-shield-alt me-2"></i>Security Settings
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required placeholder="Enter current password">
                        </div>

                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Enter new password" minlength="6">
                            <div class="password-strength" id="password-strength"></div>
                            <small class="form-text text-muted">Password must be at least 6 characters long</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
                            <div class="invalid-feedback" id="password-match-feedback">
                                Passwords do not match
                            </div>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Privacy Settings -->
            <div class="card mt-4">
                <div class="card-header">
                    <i class="fas fa-lock me-2"></i>Privacy Settings
                </div>
                <div class="card-body">
                    <div class="form-group d-flex justify-content-between align-items-center">
                        <div>
                            <label class="form-label">Show Profile to Other Tenants</label>
                            <p class="text-muted mb-0">Allow other tenants to see your basic profile</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="profile_visibility">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="form-group d-flex justify-content-between align-items-center">
                        <div>
                            <label class="form-label">Data Sharing</label>
                            <p class="text-muted mb-0">Allow anonymous data for improvements</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="data_sharing" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card mt-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                </div>
                <div class="card-body">
                    <p class="text-muted">Once you delete your account, there is no going back. Please be certain.</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="fas fa-trash me-2"></i>Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete your account? This action cannot be undone.</p>
                <p class="text-danger"><strong>Warning:</strong> All your data will be permanently removed from our systems.</p>
                <div class="form-group">
                    <label for="confirmDelete" class="form-label">Type "DELETE" to confirm:</label>
                    <input type="text" class="form-control" id="confirmDelete" placeholder="Type DELETE here">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                    <i class="fas fa-trash me-2"></i>Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Password strength indicator
    document.getElementById('new_password').addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('password-strength');
        
        let strength = 0;
        if (password.length >= 6) strength += 25;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 25;
        if (password.match(/\d/)) strength += 25;
        if (password.match(/[^a-zA-Z\d]/)) strength += 25;
        
        strengthBar.className = 'password-strength';
        if (strength <= 25) {
            strengthBar.classList.add('strength-weak');
        } else if (strength <= 50) {
            strengthBar.classList.add('strength-fair');
        } else if (strength <= 75) {
            strengthBar.classList.add('strength-good');
        } else {
            strengthBar.classList.add('strength-strong');
        }
    });

    // Password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = this.value;
        const feedback = document.getElementById('password-match-feedback');
        
        if (confirmPassword && newPassword !== confirmPassword) {
            this.classList.add('is-invalid');
            feedback.style.display = 'block';
        } else {
            this.classList.remove('is-invalid');
            feedback.style.display = 'none';
        }
    });

    // Delete account confirmation
    document.getElementById('confirmDelete').addEventListener('input', function() {
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.disabled = this.value !== 'DELETE';
    });

    // Toggle switch functionality
    document.querySelectorAll('.toggle-switch input').forEach(switchInput => {
        switchInput.addEventListener('change', function() {
            // In a real application, you would send an AJAX request here
            console.log('Toggle switched:', this.checked);
        });
    });

    // Name validation
    document.getElementById('full_name').addEventListener('input', function() {
        const name = this.value.trim();
        if (name.length < 2 && name.length > 0) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
</script>
</body>
</html>