<?php
session_start();
require_once "../auth/db_config.php";

// Ensure admin access
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

// Helper function to get system settings
function getSystemSettings($conn) {
    $defaults = [
        'system_name' => 'Monrine Tenant System',
        'contact_email' => 'ianmunyoiks@gmail.com',
        'grace_period' => 5,
        'reminder_day' => 28,
        'notifications' => 1,
        'currency' => 'KES',
        'date_format' => 'Y-m-d',
        'timezone' => 'Africa/Nairobi',
        'late_fee' => 500,
        'late_fee_type' => 'fixed',
        'late_fee_value' => 500
    ];
    
    try {
        $res = $conn->query("SELECT * FROM system_settings LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $db_settings = $res->fetch_assoc();
            foreach ($defaults as $key => $default_value) {
                $defaults[$key] = isset($db_settings[$key]) && $db_settings[$key] !== null ? $db_settings[$key] : $default_value;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting system settings: " . $e->getMessage());
    }
    return $defaults;
}

// Test notification function
function sendTestNotification($conn) {
    // Get logged-in admin info
    $user_id = $_SESSION['user_id'];
    $admin_query = $conn->query("SELECT full_name FROM users WHERE id = $user_id");
    
    if ($admin_query->num_rows > 0) {
        $admin = $admin_query->fetch_assoc();
        
        $subject = "System Test Notification";
        $message = "Hello " . $admin['full_name'] . ",\n\n";
        $message .= "This is a test notification from your Monrine Tenant System.\n\n";
        $message .= "If you can see this inside your Notifications panel, your system notifications are working correctly.\n\n";
        $message .= "System Time: " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "Thank you!\nMonrine Tenant System";
        
        $title = "System Test Notification";
        $escaped_message = $conn->real_escape_string($message);
        $escaped_subject = $conn->real_escape_string($subject);
        $escaped_title = $conn->real_escape_string($title);

        // Insert notification into the system
        $sql = "INSERT INTO notifications (user_id, type, title, subject, message, sent_date, status)
                VALUES ($user_id, 'info', '$escaped_title', '$escaped_subject', '$escaped_message', NOW(), 'sent')";
        
        if ($conn->query($sql)) {
            return true;
        } else {
            error_log('Notification insert failed: ' . $conn->error);
            return false;
        }
    }
    return false;
}

// Function to create rent reminder notification in the system
function createRentReminderNotification($conn, $tenant, $settings) {
    $user_id = $tenant['id'];
    
    // Create the notification message
    $message = "Dear " . $tenant['full_name'] . ",\n\n";
    $message .= "This is a friendly reminder that your rent payment of KSh " . number_format($tenant['rent_amount']) . " for " . date('F Y') . " is due.\n\n";
    $message .= "Apartment: " . $tenant['apartment_number'] . "\n";
    
    // Check if reminder_day exists in settings
    if (isset($settings['reminder_day']) && !empty($settings['reminder_day'])) {
        $message .= "Due Date: " . date('jS F Y', strtotime(date('Y-m-' . $settings['reminder_day']))) . "\n";
    }
    
    if (isset($settings['grace_period']) && !empty($settings['grace_period'])) {
        $message .= "Grace Period: " . $settings['grace_period'] . " days\n";
    }
    
    if (isset($settings['late_fee']) && !empty($settings['late_fee'])) {
        $message .= "Late Fee: KSh " . number_format($settings['late_fee']) . "\n\n";
    }
    
    $message .= "Please make your payment before the end of the month to avoid any penalties.\n\n";
    $message .= "Thank you,\n";
    $message .= $settings['system_name'] . " Management";

    // Insert as a system notification
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, sent_date, status) 
                           VALUES (?, 'rent_reminder', 'Rent Payment Reminder', ?, NOW(), 'sent')");
    $stmt->bind_param("is", $user_id, $message);
    
    if ($stmt->execute()) {
        error_log("Rent reminder created for: " . $tenant['full_name'] . " (Apartment: " . $tenant['apartment_number'] . ")");
        return true;
    } else {
        error_log('Failed to create rent reminder notification for ' . $tenant['full_name'] . ': ' . $stmt->error);
        return false;
    }
}

// Function to send rent reminders to unpaid tenants
function sendRentReminders($conn) {
    // Get system settings
    $settings_query = $conn->query("SELECT * FROM system_settings LIMIT 1");
    if ($settings_query->num_rows === 0) {
        return ['success' => false, 'message' => 'System settings not found.'];
    }
    
    $settings = $settings_query->fetch_assoc();
    
    if (!$settings['notifications']) {
        return ['success' => false, 'message' => 'Notifications are disabled in system settings.'];
    }
    
    $current_month = date('n');
    $current_year = date('Y');
    
    // Find tenants who haven't paid rent for current month
    $query = "
        SELECT u.id, u.full_name, u.email, u.phone, a.apartment_number, a.rent_amount
        FROM users u
        INNER JOIN apartments a ON u.id = a.tenant_id
        WHERE u.is_admin = 0 
        AND a.tenant_id IS NOT NULL
        AND a.status = 'occupied'
        AND u.id NOT IN (
            SELECT user_id FROM mpesa_transactions 
            WHERE MONTH(transaction_date) = ? AND YEAR(transaction_date) = ? 
            AND status = 'completed'
        )
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $current_month, $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_tenants_found = $result->num_rows;
    $notifications_created = 0;
    $failed_creations = 0;
    
    // Debug: Log how many tenants were found
    error_log("Rent reminders: Found $total_tenants_found unpaid tenants for " . date('F Y'));
    
    while ($tenant = $result->fetch_assoc()) {
        // Create internal notification
        if (createRentReminderNotification($conn, $tenant, $settings)) {
            $notifications_created++;
        } else {
            $failed_creations++;
        }
    }
    
    if ($notifications_created > 0) {
        return [
            'success' => true, 
            'message' => "Created $notifications_created rent reminder notifications. " . 
                        ($failed_creations > 0 ? "$failed_creations failed to create." : "All notifications created successfully.")
        ];
    } else if ($total_tenants_found > 0 && $notifications_created === 0) {
        return [
            'success' => false, 
            'message' => " Found $total_tenants_found unpaid tenants but failed to create notifications for all of them."
        ];
    } else {
        return [
            'success' => true, 
            'message' => " All tenants have paid rent for " . date('F Y') . ". No reminders needed."
        ];
    }
}

// Auto-run rent reminders on the reminder day of each month
function autoRentReminders($conn) {
    // Check if session exists
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $current_day = date('j');
    $current_month = date('Y-m');
    $settings = getSystemSettings($conn);
    
    $reminder_day = $settings['reminder_day'] ?? 5; // Default to 5th
    
    // Check if we already ran reminders this month
    $last_run = $_SESSION['rent_reminders_this_month'] ?? null;
    
    if ($current_day == $reminder_day && $last_run !== $current_month) {
        $result = sendRentReminders($conn);
        
        // Store that we've run it this month
        $_SESSION['rent_reminders_this_month'] = $current_month;
        $_SESSION['last_reminder_result'] = $result;
        
        // Log the result
        error_log("Auto rent reminders executed: " . ($result['message'] ?? 'Unknown result'));
        
        return $result;
    }
    
    return ['success' => true, 'message' => 'Reminders not scheduled for today or already run this month'];
}

// Handle form submission
$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // General Settings
    if (isset($_POST['update_settings'])) {
        $system_name = trim($_POST['system_name'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $grace_period = intval($_POST['grace_period'] ?? 5);
        $reminder_day = intval($_POST['reminder_day'] ?? 28);
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        $currency = trim($_POST['currency'] ?? 'KES');
        $date_format = trim($_POST['date_format'] ?? 'Y-m-d');
        $timezone = trim($_POST['timezone'] ?? 'Africa/Nairobi');
        $late_fee = floatval($_POST['late_fee'] ?? 500);
        $late_fee_type = trim($_POST['late_fee_type'] ?? 'fixed');
        $late_fee_value = floatval($_POST['late_fee_value'] ?? 500);

        try {
            // First, let's check what columns actually exist in the table
            $check_columns = $conn->query("SHOW COLUMNS FROM system_settings");
            $existing_columns = [];
            while($col = $check_columns->fetch_assoc()) {
                $existing_columns[] = $col['Field'];
            }

            // Check if settings exist
            $check = $conn->query("SELECT id FROM system_settings LIMIT 1");
            if ($check->num_rows > 0) {
                // Build dynamic UPDATE query based on existing columns
                $update_fields = [];
                $update_params = [];
                $update_types = "";
                
                if (in_array('system_name', $existing_columns)) {
                    $update_fields[] = "system_name=?";
                    $update_params[] = $system_name;
                    $update_types .= "s";
                }
                if (in_array('contact_email', $existing_columns)) {
                    $update_fields[] = "contact_email=?";
                    $update_params[] = $contact_email;
                    $update_types .= "s";
                }
                if (in_array('grace_period', $existing_columns)) {
                    $update_fields[] = "grace_period=?";
                    $update_params[] = $grace_period;
                    $update_types .= "i";
                }
                if (in_array('reminder_day', $existing_columns)) {
                    $update_fields[] = "reminder_day=?";
                    $update_params[] = $reminder_day;
                    $update_types .= "i";
                }
                if (in_array('notifications', $existing_columns)) {
                    $update_fields[] = "notifications=?";
                    $update_params[] = $notifications;
                    $update_types .= "i";
                }
                if (in_array('currency', $existing_columns)) {
                    $update_fields[] = "currency=?";
                    $update_params[] = $currency;
                    $update_types .= "s";
                }
                if (in_array('date_format', $existing_columns)) {
                    $update_fields[] = "date_format=?";
                    $update_params[] = $date_format;
                    $update_types .= "s";
                }
                if (in_array('timezone', $existing_columns)) {
                    $update_fields[] = "timezone=?";
                    $update_params[] = $timezone;
                    $update_types .= "s";
                }
                if (in_array('late_fee', $existing_columns)) {
                    $update_fields[] = "late_fee=?";
                    $update_params[] = $late_fee;
                    $update_types .= "d";
                }
                if (in_array('late_fee_type', $existing_columns)) {
                    $update_fields[] = "late_fee_type=?";
                    $update_params[] = $late_fee_type;
                    $update_types .= "s";
                }
                if (in_array('late_fee_value', $existing_columns)) {
                    $update_fields[] = "late_fee_value=?";
                    $update_params[] = $late_fee_value;
                    $update_types .= "d";
                }

                if (!empty($update_fields)) {
                    $sql = "UPDATE system_settings SET " . implode(", ", $update_fields);
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($update_types, ...$update_params);
                    $stmt->execute();
                    $success = " System settings updated successfully.";
                }
            } else {
                // Insert first-time settings - only include existing columns
                $insert_fields = [];
                $insert_placeholders = [];
                $insert_params = [];
                $insert_types = "";
                
                if (in_array('system_name', $existing_columns)) {
                    $insert_fields[] = "system_name";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $system_name;
                    $insert_types .= "s";
                }
                if (in_array('contact_email', $existing_columns)) {
                    $insert_fields[] = "contact_email";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $contact_email;
                    $insert_types .= "s";
                }
                if (in_array('grace_period', $existing_columns)) {
                    $insert_fields[] = "grace_period";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $grace_period;
                    $insert_types .= "i";
                }
                if (in_array('reminder_day', $existing_columns)) {
                    $insert_fields[] = "reminder_day";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $reminder_day;
                    $insert_types .= "i";
                }
                if (in_array('notifications', $existing_columns)) {
                    $insert_fields[] = "notifications";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $notifications;
                    $insert_types .= "i";
                }
                if (in_array('currency', $existing_columns)) {
                    $insert_fields[] = "currency";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $currency;
                    $insert_types .= "s";
                }
                if (in_array('date_format', $existing_columns)) {
                    $insert_fields[] = "date_format";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $date_format;
                    $insert_types .= "s";
                }
                if (in_array('timezone', $existing_columns)) {
                    $insert_fields[] = "timezone";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $timezone;
                    $insert_types .= "s";
                }
                if (in_array('late_fee', $existing_columns)) {
                    $insert_fields[] = "late_fee";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $late_fee;
                    $insert_types .= "d";
                }
                if (in_array('late_fee_type', $existing_columns)) {
                    $insert_fields[] = "late_fee_type";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $late_fee_type;
                    $insert_types .= "s";
                }
                if (in_array('late_fee_value', $existing_columns)) {
                    $insert_fields[] = "late_fee_value";
                    $insert_placeholders[] = "?";
                    $insert_params[] = $late_fee_value;
                    $insert_types .= "d";
                }

                if (!empty($insert_fields)) {
                    $sql = "INSERT INTO system_settings (" . implode(", ", $insert_fields) . ") 
                            VALUES (" . implode(", ", $insert_placeholders) . ")";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($insert_types, ...$insert_params);
                    $stmt->execute();
                    $success = " System settings created successfully.";
                }
            }
        } catch (Exception $e) {
            $error = " Error updating settings: " . $e->getMessage();
        }
    }
    
    // Test notification
    if (isset($_POST['test_notification'])) {
        if (sendTestNotification($conn)) {
            $success = " Test notification sent successfully! Check your email.";
        } else {
            $error = " Failed to send test notification. Check your email configuration.";
        }
    }
    
    // Send notifications to unpaid tenants
    if (isset($_POST['send_reminders'])) {
        $result = sendRentReminders($conn);
        if ($result['success']) {
            $success = " " . $result['message'];
        } else {
            $error = " " . $result['message'];
        }
    }
}

// Load current settings with proper NULL value handling
$defaults = [
    'system_name' => 'Monrine Tenant System',
    'contact_email' => 'ianmunyoiks@gmail.com',
    'grace_period' => 5,
    'reminder_day' => 28,
    'notifications' => 1,
    'currency' => 'KES',
    'date_format' => 'Y-m-d',
    'timezone' => 'Africa/Nairobi',
    'late_fee' => 500,
    'late_fee_type' => 'fixed',
    'late_fee_value' => 500
];

$current = $defaults;

try {
    $res = $conn->query("SELECT * FROM system_settings LIMIT 1");
    if ($res->num_rows > 0) {
        $db_settings = $res->fetch_assoc();
        
        // Merge database values with defaults, handling NULL values properly
        foreach ($defaults as $key => $default_value) {
            $current[$key] = isset($db_settings[$key]) && $db_settings[$key] !== null ? $db_settings[$key] : $default_value;
        }
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Call auto rent reminders (but only if not already called)
if (!defined('RENT_REMINDERS_CALLED')) {
    define('RENT_REMINDERS_CALLED', true);
    $autoResult = autoRentReminders($conn);
    
    // Optional: Display result on admin pages
    if (isset($autoResult) && strpos($_SERVER['PHP_SELF'], 'admin') !== false) {
        $alertClass = $autoResult['success'] ? 'alert-success' : 'alert-warning';
        echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                <strong>Rent Reminders:</strong> " . $autoResult['message'] . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

// Helper function to safely output values
function safe_output($value) {
    return htmlspecialchars($value ?? '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border: #dee2e6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h2 {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header a {
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .header a:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1000px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary);
            margin: 30px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title:first-child {
            margin-top: 0;
        }
        
        form {
            margin-top: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-hint {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        input[type=text], input[type=email], input[type=number], input[type=password], select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
            transform: scale(1.2);
        }
        
        button {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-success {
            background: var(--success);
        }
        
        .btn-success:hover {
            background: #3ab0d9;
        }
        
        .btn-warning {
            background: var(--warning);
        }
        
        .btn-warning:hover {
            background: #e6891e;
        }
        
        .message {
            margin: 15px 0;
            padding: 12px 15px;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
        }
        
        .success {
            background-color: rgba(76, 201, 240, 0.2);
            color: #0a7ea3;
            border-left: 4px solid var(--success);
        }
        
        .error {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        hr {
            margin: 25px 0;
            border: none;
            height: 1px;
            background: var(--border);
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                margin: 20px;
                padding: 20px;
            }
        }
        
        .notification-info {
            background: #e8f4fd;
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .fee-settings {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .notification-log {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            background: #f8f9fa;
        }
        
        .log-entry {
            padding: 8px;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 15px 0;
        }
    </style>
</head>
<body>

<div class="header">
    <h2><i class="fas fa-cogs"></i> System Settings</h2>
    <a href="../admin/admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="container">
    <?php if($success): ?>
        <div class="message success"><?= safe_output($success) ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="message error"><?= safe_output($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="update_settings" value="1">
        
        <div class="section-title">
            <i class="fas fa-sliders-h"></i> General Settings
        </div>
        
        <div class="settings-grid">
            <div class="form-group">
                <label for="system_name">System Name</label>
                <input type="text" id="system_name" name="system_name" value="<?= safe_output($current['system_name']) ?>" required>
                <div class="form-hint">The name displayed throughout the system</div>
            </div>
            
            <div class="form-group">
                <label for="contact_email">Contact Email</label>
                <input type="email" id="contact_email" name="contact_email" value="<?= safe_output($current['contact_email']) ?>" required>
                <div class="form-hint">Email address for system notifications</div>
            </div>
        </div>
        
        <div class="section-title">
            <i class="fas fa-bell"></i> Notification Settings
        </div>
        
        <div class="notification-info">
            <strong><i class="fas fa-info-circle"></i> How notifications work:</strong>
            <p>On the reminder day each month, the system will automatically send notifications to tenants who haven't paid rent for the current month. Late fees will be applied after the grace period.</p>
        </div>
        
        <div class="settings-grid">
            <div class="form-group">
                <label for="reminder_day">Rent Reminder Day</label>
                <input type="number" id="reminder_day" name="reminder_day" value="<?= safe_output($current['reminder_day']) ?>" min="1" max="31">
                <div class="form-hint">Day of month to send automatic rent reminders</div>
            </div>
        </div>
        
        <div class="checkbox-group">
            <input type="checkbox" id="notifications" name="notifications" <?= $current['notifications'] ? 'checked' : '' ?>>
            <label for="notifications">Enable Automatic Notifications</label>
        </div>
        
        <div class="action-buttons">
            <button type="submit" name="send_reminders" class="btn-warning">
                <i class="fas fa-paper-plane"></i> Send Rent Reminders Now
            </button>
            
            <button type="submit" name="test_notification" class="btn-success">
                <i class="fas fa-bell"></i> Send Test Notification
            </button>
        </div>
        
        <div class="section-title">
            <i class="fas fa-money-bill-wave"></i> Late Fee Settings
        </div>
        
        <div class="fee-settings">
            <div class="settings-grid">
                <div class="form-group">
                    <label for="late_fee_type">Late Fee Type</label>
                    <select id="late_fee_type" name="late_fee_type" onchange="toggleLateFeeFields()">
                        <option value="fixed" <?= $current['late_fee_type'] == 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                        <option value="percentage" <?= $current['late_fee_type'] == 'percentage' ? 'selected' : '' ?>>Percentage of Rent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="late_fee_value" id="late_fee_label">
                        <?= $current['late_fee_type'] == 'fixed' ? 'Late Fee Amount (KES)' : 'Late Fee Percentage (%)' ?>
                    </label>
                    <input type="number" id="late_fee_value" name="late_fee_value" 
                           value="<?= safe_output($current['late_fee_value']) ?>" 
                           min="0" 
                           step="<?= $current['late_fee_type'] == 'fixed' ? '50' : '0.5' ?>"
                           required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="grace_period">Grace Period (days)</label>
                <input type="number" id="grace_period" name="grace_period" value="<?= safe_output($current['grace_period']) ?>" min="0" max="30">
                <div class="form-hint">Days after due date before late fees apply</div>
            </div>
        </div>
        
        <div class="section-title">
            <i class="fas fa-globe"></i> Regional Settings
        </div>
        
        <div class="settings-grid">
            <div class="form-group">
                <label for="currency">Currency</label>
                <select id="currency" name="currency">
                    <option value="KES" <?= $current['currency'] == 'KES' ? 'selected' : '' ?>>Kenyan Shilling (KES)</option>
                    <option value="USD" <?= $current['currency'] == 'USD' ? 'selected' : '' ?>>US Dollar (USD)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="date_format">Date Format</label>
                <select id="date_format" name="date_format">
                    <option value="Y-m-d" <?= $current['date_format'] == 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                    <option value="d/m/Y" <?= $current['date_format'] == 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                    <option value="m/d/Y" <?= $current['date_format'] == 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="timezone">Timezone</label>
            <select id="timezone" name="timezone">
                <option value="Africa/Nairobi" <?= $current['timezone'] == 'Africa/Nairobi' ? 'selected' : '' ?>>Nairobi, Kenya (EAT)</option>
                <option value="UTC" <?= $current['timezone'] == 'UTC' ? 'selected' : '' ?>>UTC</option>
            </select>
        </div>
        
        <button type="submit"><i class="fas fa-save"></i> Save Settings</button>
    </form>

    <!-- Notification Log Section -->
    <div class="section-title">
        <i class="fas fa-history"></i> Recent Notifications
    </div>
    
    <div class="notification-log">
        <?php
        // Check if notifications table exists and show recent notifications
        $user_id = $_SESSION['user_id'];
        $log_query = $conn->query("
            SELECT n.*, u.full_name 
            FROM notifications n 
            LEFT JOIN users u ON n.user_id = u.id 
            WHERE n.user_id = $user_id
            ORDER BY n.sent_date DESC 
            LIMIT 10
        ");
        
        if ($log_query && $log_query->num_rows > 0) {
            while($log = $log_query->fetch_assoc()) {
                echo '<div class="log-entry">';
                echo '<strong>' . safe_output($log['type'] ?? 'Unknown') . '</strong>';
                
                // Safely display recipient information
                if (!empty($log['full_name'])) {
                    echo ' to ' . safe_output($log['full_name']);
                } else {
                    echo ' (System notification)';
                }
                
                echo '<br><small>' . safe_output($log['sent_date'] ?? 'Unknown date') . '</small>';
                echo '</div>';
            }
        } else {
            echo '<div class="log-entry">No notifications sent yet.</div>';
        }
        ?>
    </div>
</div>

<script>
    // Toggle late fee fields based on type
    function toggleLateFeeFields() {
        const type = document.getElementById('late_fee_type').value;
        const label = document.getElementById('late_fee_label');
        const input = document.getElementById('late_fee_value');
        
        if (type === 'fixed') {
            label.textContent = 'Late Fee Amount (KES)';
            input.step = '50';
            input.min = '0';
            input.max = '';
            input.placeholder = 'Enter fixed amount';
        } else {
            label.textContent = 'Late Fee Percentage (%)';
            input.step = '0.5';
            input.min = '0';
            input.max = '100';
            input.placeholder = 'Enter percentage';
        }
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleLateFeeFields();
    });
</script>
</body>
</html>
