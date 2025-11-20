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

// Initialize variables to avoid undefined warnings
$full_name = '';
$email = '';
$phone = '';
$created_at = '';
$apartment_id = null;
$success_message = '';
$error_message = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch user data from database
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT full_name, email, phone, created_at, apartment_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $full_name = $user['full_name'];
        $email = $user['email'];
        $phone = $user['phone'] ?? 'Not set';
        $created_at = $user['created_at'];
        $apartment_id = $user['apartment_id'];
        
        // Update session with latest data
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
    } else {
        // Fallback if user not found
        $full_name = $_SESSION['full_name'] ?? 'Tenant';
        $email = $_SESSION['email'] ?? '';
        $phone = 'Not set';
        $created_at = date('Y-m-d');
        $apartment_id = null;
    }

    // Handle name and profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $new_name = trim($_POST['full_name']);
        $new_phone = trim($_POST['phone']);
        
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

// Handle account deletion (archive + delete)
// Handle account deletion (archive + delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $user_id = $_SESSION['user_id'];
    $confirm_text = $_POST['confirm_text'] ?? '';

    if ($confirm_text !== 'DELETE ALL') {
        $error_message = "Please type 'DELETE ALL' to confirm account deletion.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1️⃣ Get the current user's details including role and relationships
            $stmt = $pdo->prepare("
                SELECT u.id, u.email, u.full_name, u.apartment_id, u.unit_role, u.is_primary_tenant, 
                       u.linked_to, p.email as primary_tenant_email
                FROM users u 
                LEFT JOIN users p ON u.linked_to = p.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$user_id]);
            $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current_user) {
                throw new Exception("User not found!");
            }
            
            $apartment_id = $current_user['apartment_id'];
            $user_email = $current_user['email'];
            $user_full_name = $current_user['full_name'];
            $is_primary_tenant = $current_user['is_primary_tenant'];
            $linked_to = $current_user['linked_to'];
            $primary_tenant_email = $current_user['primary_tenant_email'];

            error_log("Starting deletion process for user: $user_email (ID: $user_id), Apartment: $apartment_id, Primary Tenant: $is_primary_tenant");

            // 2️⃣ Determine which users to delete based on relationships
            $users_to_delete = [];
            
            if ($is_primary_tenant) {
                // If current user is primary tenant, delete all users in the apartment
                error_log("User is primary tenant, deleting all users in apartment $apartment_id");
                $stmt = $pdo->prepare("
                    SELECT u.id, u.email, u.full_name, u.unit_role, u.is_primary_tenant, 
                           u.linked_to, p.email as primary_tenant_email
                    FROM users u 
                    LEFT JOIN users p ON u.linked_to = p.id 
                    WHERE u.apartment_id = ?
                ");
                $stmt->execute([$apartment_id]);
                $users_to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } else if ($linked_to) {
                // If current user is linked to someone, delete all users linked to the same primary tenant
                error_log("User is linked to primary tenant ID: $linked_to, finding all linked users");
                $stmt = $pdo->prepare("
                    SELECT u.id, u.email, u.full_name, u.unit_role, u.is_primary_tenant, 
                           u.linked_to, p.email as primary_tenant_email
                    FROM users u 
                    LEFT JOIN users p ON u.linked_to = p.id 
                    WHERE u.apartment_id = ? OR u.linked_to = ? OR u.id = ?
                ");
                $stmt->execute([$apartment_id, $linked_to, $linked_to]);
                $users_to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } else {
                // If no relationships, just delete the current user
                error_log("No relationships found, deleting only current user");
                $users_to_delete = [$current_user];
            }

            error_log("Found " . count($users_to_delete) . " users to delete");

            // 3️⃣ Track the primary tenant email for archiving
            $primary_tenant_info = null;
            foreach ($users_to_delete as $user) {
                if ($user['is_primary_tenant']) {
                    $primary_tenant_info = $user;
                    break;
                }
            }

            // If no primary tenant found in the list, use the current user's linked_to info
            if (!$primary_tenant_info && $linked_to) {
                $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE id = ?");
                $stmt->execute([$linked_to]);
                $primary_tenant_info = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $primary_tenant_email_for_archive = $primary_tenant_info ? $primary_tenant_info['email'] : $user_email;
            $primary_tenant_id_for_archive = $primary_tenant_info ? $primary_tenant_info['id'] : $user_id;

            error_log("Primary tenant email for archive tracking: $primary_tenant_email_for_archive");

            // 4️⃣ Track emails we've already archived to avoid duplicates
            $archived_emails = [];
            
            // Archive and delete all identified users
            foreach ($users_to_delete as $user) {
                $user_id_to_delete = $user['id'];
                $user_email_to_delete = $user['email'];
                $user_full_name_to_delete = $user['full_name'];
                $is_user_primary_tenant = $user['is_primary_tenant'];
                $user_linked_to = $user['linked_to'];

                error_log("Processing user: $user_email_to_delete (ID: $user_id_to_delete), Primary: $is_user_primary_tenant, Linked To: $user_linked_to");

                // Determine linked_to email for archiving
                $linked_to_email = null;
                if ($user_linked_to) {
                    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                    $stmt->execute([$user_linked_to]);
                    $linked_user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $linked_to_email = $linked_user ? $linked_user['email'] : null;
                }

                // Check if we've already archived this email to avoid duplicates
                if (in_array($user_email_to_delete, $archived_emails)) {
                    error_log("Skipping archive for duplicate email: $user_email_to_delete, proceeding with deletion only");
                    
                    // Just delete the user without archiving (since it's already archived)
                    $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id_to_delete]);
                    $pdo->prepare("DELETE FROM mpesa_transactions WHERE user_id = ?")->execute([$user_id_to_delete]);
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id_to_delete]);
                    
                    error_log("Deleted duplicate user without archiving: $user_email_to_delete (ID: $user_id_to_delete)");
                    continue;
                }

                // Archive user data with relationship tracking
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO archived_users (
                            full_name, email, phone, emergency_contact, password, verification_code,
                            is_verified, reset_token, reset_expiry, reset_attempts, last_reset_at, token_expiry,
                            role, unit_number, unit_role, is_primary_tenant, invited_by, invitation_token,
                            invitation_accepted_at, created_at, updated_at, is_admin, linked_to,
                            archived_by, deleted_at
                        )
                        SELECT 
                            full_name, email, phone, emergency_contact, password, verification_code,
                            is_verified, reset_token, reset_expiry, reset_attempts, last_reset_at, token_expiry,
                            role, unit_number, unit_role, is_primary_tenant, invited_by, invitation_token,
                            invitation_accepted_at, created_at, updated_at, is_admin, ?,
                            ?, NOW()
                        FROM users WHERE id = ?
                    ");
                    $archive_result = $stmt->execute([$linked_to_email, $user_id, $user_id_to_delete]);
                    
                    if (!$archive_result) {
                        throw new Exception("Failed to archive user ID: $user_id_to_delete");
                    }
                    
                    // Mark this email as archived
                    $archived_emails[] = $user_email_to_delete;
                    error_log("Archived user: $user_email_to_delete, Linked To: " . ($linked_to_email ?: 'N/A'));

                } catch (PDOException $e) {
                    // If archive fails due to duplicate email, log and continue with deletion
                    if ($e->getCode() == '23000') {
                        error_log("Duplicate email detected during archive: $user_email_to_delete, proceeding with deletion only");
                        $archived_emails[] = $user_email_to_delete;
                    } else {
                        throw $e; // Re-throw if it's a different error
                    }
                }

                // Archive notifications
                $stmt = $pdo->prepare("
                    INSERT INTO archived_notifications (
                        user_id, title, message, type, is_read, created_at, tenant_id,
                        subject, sent_date, status, tenant_name, recipient_email,
                        archived_by, deleted_at
                    )
                    SELECT 
                        user_id, title, message, type, is_read, created_at, tenant_id,
                        subject, sent_date, status, tenant_name, recipient_email,
                        ?, NOW()
                    FROM notifications WHERE user_id = ?
                ");
                $stmt->execute([$user_id, $user_id_to_delete]);

                // Archive M-Pesa transactions
                $stmt = $pdo->prepare("
                    INSERT INTO archived_mpesa_transactions (
                        transaction_id, user_id, amount, phone_number, transaction_date,
                        status, description, archived_by, deleted_at
                    )
                    SELECT 
                        transaction_id, user_id, amount, phone_number, transaction_date,
                        status, description, ?, NOW()
                    FROM mpesa_transactions WHERE user_id = ?
                ");
                $stmt->execute([$user_id, $user_id_to_delete]);

                // Delete live data
                $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id_to_delete]);
                $pdo->prepare("DELETE FROM mpesa_transactions WHERE user_id = ?")->execute([$user_id_to_delete]);
                
                // Finally delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $delete_result = $stmt->execute([$user_id_to_delete]);
                
                if (!$delete_result) {
                    throw new Exception("Failed to delete user ID: $user_id_to_delete");
                }
                
                error_log("Successfully deleted user: $user_email_to_delete (ID: $user_id_to_delete)");
            }

            // 5️⃣ Archive and update apartment if it exists
            if ($apartment_id) {
                // Check if apartment exists before archiving
                $stmt = $pdo->prepare("SELECT id FROM apartments WHERE id = ?");
                $stmt->execute([$apartment_id]);
                $apartment_exists = $stmt->fetch();
                
                if ($apartment_exists) {
                    // Archive apartment with primary tenant info
                    $stmt = $pdo->prepare("
                        INSERT INTO archived_apartments (
                            apartment_number, building_name, rent_amount, is_occupied, tenant_id,
                            created_at, archived_by, deleted_at
                        )
                        SELECT 
                            apartment_number, building_name, rent_amount, is_occupied, ?,
                            created_at, ?, NOW()
                        FROM apartments WHERE id = ?
                    ");
                    $stmt->execute([$primary_tenant_id_for_archive, $user_id, $apartment_id]);

                    // Mark apartment as vacant in live table
                    $stmt = $pdo->prepare("UPDATE apartments SET status = 'vacant', tenant_id = NULL WHERE id = ?");
                    $stmt->execute([$apartment_id]);
                    
                    error_log("Apartment $apartment_id marked as vacant and archived. Primary tenant was: $primary_tenant_email_for_archive");
                } else {
                    error_log("Apartment $apartment_id not found, skipping apartment operations");
                }
            }

            // COMMIT THE TRANSACTION
            $pdo->commit();
            
            error_log("TRANSACTION COMMITTED: Account deletion completed successfully. Primary tenant: $primary_tenant_email_for_archive");

            // Destroy session and redirect
            session_unset();
            session_destroy();
            
            header("Location: ../auth/login.php?deleted=1&primary_tenant=" . urlencode($primary_tenant_email_for_archive));
            exit();

        } catch (Exception $e) {
            // ROLLBACK on error
            $pdo->rollBack();
            error_log("Account deletion failed for user $user_id: " . $e->getMessage());
            $error_message = "Error deleting account. Please contact support. Error: " . $e->getMessage();
        }
    }
}
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monrine Tenant System | Settings</title>
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

        .danger-zone {
            border: 2px solid #dc3545;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-home me-2"></i>Monrine Tenant</h4>
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
            <div class="card mt-4 danger-zone">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                </div>
                <div class="card-body">
                    <p class="text-danger"><strong>Warning:</strong> This action will delete your account and ALL related accounts (roommates, spouses, family members) in your apartment.</p>
                    <p class="text-muted">Once deleted, all data will be permanently removed and cannot be recovered.</p>
                    
                    <form method="POST" id="deleteAccountForm">
                        <div class="mb-3">
                            <label for="confirm_text" class="form-label">Type "DELETE ALL" to confirm:</label>
                            <input type="text" class="form-control" id="confirm_text" name="confirm_text" placeholder="Type DELETE ALL here" required>
                            <div class="form-text">This confirms you understand all accounts in your apartment will be deleted.</div>
                        </div>
                        <button type="submit" name="delete_account" class="btn btn-danger w-100" id="deleteButton" disabled>
                            <i class="fas fa-trash me-2"></i>Delete All Accounts in My Apartment
                        </button>
                    </form>
                </div>
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
    document.getElementById('confirm_text').addEventListener('input', function() {
        const deleteButton = document.getElementById('deleteButton');
        deleteButton.disabled = this.value !== 'DELETE ALL';
    });

    // Delete account form confirmation
    document.getElementById('deleteAccountForm').addEventListener('submit', function(e) {
        if (!confirm('ARE YOU ABSOLUTELY SURE? This will delete ALL accounts in your apartment including roommates and family members. This action cannot be undone!')) {
            e.preventDefault();
        }
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
