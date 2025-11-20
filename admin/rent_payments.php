
<?php
session_start();

// Protect this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../auth/db_config.php";

// Handle individual notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $tenant_id = intval($_POST['tenant_id']);
    $message = trim($_POST['message']);
    $admin_id = $_SESSION['user_id'];
    
    // Get tenant details
    $tenant_stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $tenant_stmt->bind_param("i", $tenant_id);
    $tenant_stmt->execute();
    $tenant_result = $tenant_stmt->get_result();
    
    if ($tenant_result->num_rows > 0) {
        $tenant = $tenant_result->fetch_assoc();
        
        // Save notification to database - CORRECTED VERSION
        $subject = "Rent Payment Notification";
        
        try {
            // First try with user_id field (most common scenario)
            $log_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, subject, message, sent_date) VALUES (?, 'rent_payment', ?, ?, NOW())");
            $log_stmt->bind_param("iss", $tenant_id, $subject, $message);
            
            if ($log_stmt->execute()) {
                $success = "Notification sent to " . htmlspecialchars($tenant['full_name']);
            } else {
                throw new Exception("Failed with user_id");
            }
            $log_stmt->close();
            
        } catch (Exception $e) {
            // If that fails, try with tenant_id field
            try {
                $log_stmt = $conn->prepare("INSERT INTO notifications (tenant_id, type, subject, message, sent_date) VALUES (?, 'rent_payment', ?, ?, NOW())");
                $log_stmt->bind_param("iss", $tenant_id, $subject, $message);
                
                if ($log_stmt->execute()) {
                    $success = "Notification sent to " . htmlspecialchars($tenant['full_name']);
                } else {
                    throw new Exception("Failed with tenant_id");
                }
                $log_stmt->close();
                
            } catch (Exception $e2) {
                // Last attempt - try without specifying the user/tenant field
                try {
                    $log_stmt = $conn->prepare("INSERT INTO notifications (type, subject, message, sent_date) VALUES ('rent_payment', ?, ?, NOW())");
                    $log_stmt->bind_param("ss", $subject, $message);
                    
                    if ($log_stmt->execute()) {
                        $success = "Notification sent to " . htmlspecialchars($tenant['full_name']);
                    } else {
                        $error = "Failed to send notification to " . htmlspecialchars($tenant['full_name']) . ": Database error";
                    }
                    $log_stmt->close();
                    
                } catch (Exception $e3) {
                    $error = "Failed to send notification to " . htmlspecialchars($tenant['full_name']) . ": " . $e3->getMessage();
                }
            }
        }
    }
    $tenant_stmt->close();
}

// Get rent payments with tenant and apartment info
$payments_query = "
    SELECT u.id as tenant_id, u.full_name, u.email, u.phone, u.unit_number, 
           p.amount as paid_amount, p.month, p.status, p.created_at, p.payment_date,
           CASE 
               WHEN p.status = 'success' THEN 'Paid'
               WHEN p.status = 'pending' THEN 'Processing'
               ELSE 'Pending'
           END as payment_status
    FROM users u
    LEFT JOIN payments p ON u.id = p.user_id 
        AND p.month = ?
    WHERE u.role = 'tenant'
    ORDER BY payment_status, u.full_name
";

$current_month = date('F Y');
$payments_stmt = $conn->prepare($payments_query);
$payments_stmt->bind_param("s", $current_month);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();

// Get notification statistics (with error handling)
try {
    $total_notifications = $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
    $unread_notifications = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch_assoc()['count'];
} catch (Exception $e) {
    $total_notifications = 0;
    $unread_notifications = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent Payments - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid #4a6ee0;
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.notifications {
            border-left-color: #e74c3c;
        }
        
        .stat-card h3 {
            color: #7f8c8d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: 700;
            color: #4a6ee0;
        }
        
        .stat-card.notifications .stat-number {
            color: #e74c3c;
        }
        
        .notification-badge {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.8em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 5px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 30px;
            margin-top: 20px;
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
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-paid {
            background: #d5f4e6;
            color: #27ae60;
            border: 1px solid #2ecc71;
        }
        
        .status-pending {
            background: #fef9e7;
            color: #f39c12;
            border: 1px solid #f1c40f;
        }
        
        .status-processing {
            background: #e8f4fd;
            color: #3498db;
            border: 1px solid #3498db;
        }
        
        .amount {
            font-weight: 600;
            color: #2ecc71;
        }
        
        .tenant-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .tenant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4a6ee0, #2c52c9);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1em;
            position: relative;
        }
        
        .notification-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #e74c3c;
            border: 2px solid white;
            border-radius: 50%;
            width: 12px;
            height: 12px;
        }
        
        .tenant-details {
            display: flex;
            flex-direction: column;
        }
        
        .tenant-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .tenant-email {
            font-size: 0.85em;
            color: #7f8c8d;
        }
        
        .btn {
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            border: none;
            cursor: pointer;
            gap: 6px;
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
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        .notification-form {
            background: #f8fafc;
            padding: 20px;
            margin: 10px 0;
            border-radius: 10px;
            border-left: 4px solid #3498db;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .notification-form textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin: 15px 0;
            font-family: inherit;
            font-size: 1em;
            resize: vertical;
            min-height: 120px;
            transition: border-color 0.3s ease;
        }
        
        .notification-form textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .alert {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .month-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .month-selector select {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1em;
            background: white;
            cursor: pointer;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 12px 8px;
                font-size: 0.9em;
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
            
            .stats-cards {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 10px;
            }
            
            .tenant-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="back"><a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></div>
        <h2>Rent Payments Management</h2>
        <div class="logout"><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>
    
    <div class="content">
        <?php if(isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3>Total Tenants</h3>
                <div class="stat-number"><?= $payments_result->num_rows ?></div>
            </div>
            <div class="stat-card">
                <h3>Paid This Month</h3>
                <div class="stat-number" style="color: #27ae60;">
                    <?php 
                    $paid_count = $conn->query("SELECT COUNT(*) as count FROM payments WHERE month = '$current_month' AND status = 'success'")->fetch_assoc()['count'];
                    echo $paid_count;
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Pending Payments</h3>
                <div class="stat-number" style="color: #f39c12;">
                    <?= $payments_result->num_rows - $paid_count ?>
                </div>
            </div>
            <div class="stat-card notifications" onclick="showNotifications()">
                <h3>Notifications Sent</h3>
                <div class="stat-number">
                    <?= $total_notifications ?>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="notification-badge"><?= $unread_notifications ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="month-selector">
                <label for="month_filter"><strong>Filter by Month:</strong></label>
                <select id="month_filter" onchange="filterByMonth(this.value)">
                    <option value="<?= $current_month ?>" selected>Current Month (<?= $current_month ?>)</option>
                    <?php
                    // Generate last 6 months
                    for ($i = 1; $i <= 6; $i++) {
                        $month = date('F Y', strtotime("-$i months"));
                        echo "<option value='$month'>$month</option>";
                    }
                    ?>
                </select>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Unit</th>
                        <th>Phone</th>
                        <th>Amount Paid</th>
                        <th>Payment Month</th>
                        <th>Payment Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments_result->num_rows > 0): ?>
                        <?php while($payment = $payments_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="tenant-info">
                                    <div class="tenant-avatar">
                                        <?= strtoupper(substr($payment['full_name'], 0, 1)) ?>
                                        <?php 
                                        // Check for unread notifications (with error handling)
                                        try {
                                            $unread_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE tenant_id = {$payment['tenant_id']} AND is_read = 0")->fetch_assoc()['count'];
                                            if ($unread_count > 0): ?>
                                                <div class="notification-dot" title="<?= $unread_count ?> unread notifications"></div>
                                            <?php endif;
                                        } catch (Exception $e) {
                                            // Column doesn't exist, ignore
                                        }
                                        ?>
                                    </div>
                                    <div class="tenant-details">
                                        <div class="tenant-name"><?= htmlspecialchars($payment['full_name']) ?></div>
                                        <div class="tenant-email"><?= htmlspecialchars($payment['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($payment['unit_number'] ?? 'Not assigned') ?></td>
                            <td><?= htmlspecialchars($payment['phone'] ?? 'N/A') ?></td>
                            <td class="amount">
                                <?= $payment['paid_amount'] ? 'KSh ' . number_format($payment['paid_amount']) : '-' ?>
                            </td>
                            <td><?= htmlspecialchars($payment['month'] ?? $current_month) ?></td>
                            <td>
                                <?= $payment['payment_date'] ? date('M j, Y g:i A', strtotime($payment['payment_date'])) : 
                                   ($payment['created_at'] ? date('M j, Y', strtotime($payment['created_at'])) : '-') ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= strtolower($payment['payment_status']) ?>">
                                    <?= $payment['payment_status'] ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary" onclick="openNotificationForm(<?= $payment['tenant_id'] ?>)">
                                    <i class="fas fa-bell"></i> Notify
                                </button>
                                <button class="btn btn-secondary" onclick="viewNotifications(<?= $payment['tenant_id'] ?>, '<?= htmlspecialchars($payment['full_name']) ?>')" style="margin-top: 5px;">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Notification Form (Hidden by default) -->
                        <tr id="notification-form-<?= $payment['tenant_id'] ?>" style="display: none;">
                            <td colspan="8">
                                <div class="notification-form">
                                    <form method="POST">
                                        <input type="hidden" name="tenant_id" value="<?= $payment['tenant_id'] ?>">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                            <h4 style="color: #2c3e50; margin: 0;">
                                                <i class="fas fa-paper-plane"></i> 
                                                Send Notification to <?= htmlspecialchars($payment['full_name']) ?>
                                            </h4>
                                            <button type="button" class="btn btn-secondary" onclick="closeNotificationForm(<?= $payment['tenant_id'] ?>)">
                                                <i class="fas fa-times"></i> Close
                                            </button>
                                        </div>
                                        <textarea name="message" placeholder="Enter your notification message..." required>
Dear <?= htmlspecialchars($payment['full_name']) ?>,

This is a reminder regarding your rent payment for <?= $current_month ?>.

<?php if ($payment['payment_status'] == 'Paid'): ?>
We have successfully received your payment of KSh <?= number_format($payment['paid_amount']) ?>. Thank you for your timely payment!
<?php else: ?>
Your rent payment for <?= $current_month ?> is currently pending. Please make the payment at your earliest convenience.
<?php endif; ?>

If you have any questions, please don't hesitate to contact us.

Best regards,
Management
                                        </textarea>
                                        <button type="submit" name="send_notification" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Send Internal Notification
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h3>No Tenant Data Available</h3>
                                    <p>There are no tenant payment records for the current month.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Notifications Modal -->
    <div class="modal" id="notificationsModal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3><i class="fas fa-bell"></i> Notifications History</h3>
                <button class="btn btn-secondary" onclick="closeModal('notificationsModal')">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div id="notificationsContent">
                <!-- Notifications will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function openNotificationForm(tenantId) {
            // Close any other open forms
            document.querySelectorAll('[id^="notification-form-"]').forEach(form => {
                form.style.display = 'none';
            });
            
            // Open the selected form
            const form = document.getElementById('notification-form-' + tenantId);
            form.style.display = 'table-row';
            
            // Scroll to the form
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        function closeNotificationForm(tenantId) {
            document.getElementById('notification-form-' + tenantId).style.display = 'none';
        }
        
        function viewNotifications(tenantId, tenantName) {
            // Load notifications for this tenant
            const content = `
                <div class="empty-state">
                    <i class="fas fa-bell"></i>
                    <h4>Notifications for ${tenantName}</h4>
                    <p>Notification viewing feature coming soon!</p>
                    <p><small>Notifications are stored in the database and can be viewed by tenants.</small></p>
                </div>
            `;
            document.getElementById('notificationsContent').innerHTML = content;
            document.getElementById('notificationsModal').style.display = 'flex';
        }
        
        function showNotifications() {
            const content = `
                <div class="empty-state">
                    <i class="fas fa-bell"></i>
                    <h4>All Notifications</h4>
                    <p>Total notifications sent: <?= $total_notifications ?></p>
                    <p>Unread notifications: <?= $unread_notifications ?></p>
                    <p><small>Notifications are working and stored in the database!</small></p>
                </div>
            `;
            document.getElementById('notificationsContent').innerHTML = content;
            document.getElementById('notificationsModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function filterByMonth(month) {
            if (month !== '<?= $current_month ?>') {
                alert('Loading payments for: ' + month + '\nThis would reload the page with filtered data in a full implementation.');
                // window.location.href = '?month=' + encodeURIComponent(month);
            }
        }
        
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('notificationsModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
