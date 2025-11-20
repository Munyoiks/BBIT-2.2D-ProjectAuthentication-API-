
<?php
// Include database connection
include_once '../auth/db_config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_type = $_POST['notification_type'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $recipient_type = $_POST['recipient_type'];
    $specific_tenant = $_POST['specific_tenant'] ?? null;
    $specific_unit = $_POST['specific_unit'] ?? null;
    
    // Determine recipients based on selection
    $recipients = [];
    
    if ($recipient_type === 'all') {
        // Get all tenants
        $sql = "SELECT id, full_name, email, unit_number FROM users WHERE role = 'tenant' AND email IS NOT NULL";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $recipients[] = $row;
            }
        }
    } 
    elseif ($recipient_type === 'specific_tenant' && $specific_tenant) {
        // Get specific tenant
        $sql = "SELECT id, full_name, email, unit_number FROM users WHERE id = ? AND role = 'tenant'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $specific_tenant);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $recipients[] = $result->fetch_assoc();
        }
        $stmt->close();
    }
    elseif ($recipient_type === 'specific_unit' && $specific_unit) {
        // Get all tenants in specific unit
        $sql = "SELECT id, full_name, email, unit_number FROM users WHERE unit_number = ? AND role = 'tenant' AND email IS NOT NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $specific_unit);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $recipients[] = $row;
            }
        }
        $stmt->close();
    }
    elseif ($recipient_type === 'unassigned') {
        // Get tenants without units
        $sql = "SELECT id, full_name, email, unit_number FROM users WHERE (unit_number IS NULL OR unit_number = '') AND role = 'tenant' AND email IS NOT NULL";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $recipients[] = $row;
            }
        }
    }
    
    // Send notifications
    $sent_count = 0;
    $failed_count = 0;
    $sent_details = [];
    
    foreach ($recipients as $recipient) {
        // In a real application, you would send actual emails here
        // For demo purposes, we'll simulate sending and log to database
        
        $email = $recipient['email'];
        $tenant_name = $recipient['full_name'];
        
        // Simulate email sending (replace with actual email function)
        $email_sent = true; // simulate success
        
        if ($email_sent) {
            $sent_count++;
            $sent_details[] = [
                'name' => $tenant_name,
                'email' => $email,
                'unit' => $recipient['unit_number'] ?? 'Not assigned',
                'status' => 'Sent'
            ];
            
            // Log to database
            $log_sql = "INSERT INTO notifications (tenant_id, tenant_name, tenant_email, notification_type, subject, message, sent_at, status) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), 'sent')";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("isssss", $recipient['id'], $tenant_name, $email, $notification_type, $subject, $message);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $failed_count++;
            $sent_details[] = [
                'name' => $tenant_name,
                'email' => $email,
                'unit' => $recipient['unit_number'] ?? 'Not assigned',
                'status' => 'Failed'
            ];
        }
    }
    
    if ($sent_count > 0) {
        $message = "Notification sent successfully! {$sent_count} recipients notified." . ($failed_count > 0 ? " {$failed_count} failed." : "");
    } else {
        $error = "No notifications were sent. Please check your recipient selection.";
    }
}

// Fetch tenants for dropdown
$tenants_sql = "SELECT id, full_name, email, unit_number FROM users WHERE role = 'tenant' ORDER BY full_name";
$tenants_result = $conn->query($tenants_sql);

// Fetch units for dropdown
$units_sql = "SELECT DISTINCT unit_number FROM users WHERE unit_number IS NOT NULL AND unit_number != '' ORDER BY unit_number";
$units_result = $conn->query($units_sql);

// Define available apartments for unit selection
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
$apartments[] = ['F1-9', '1st Floor'];
$apartments[] = ['F1-10', '1st Floor'];
$apartments[] = ['F1-11', '1st Floor'];
$apartments[] = ['F1-12', '1st Floor'];
$apartments[] = ['F1-13', '1st Floor'];

// Add other floors as needed...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications</title>
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
            max-width: 1000px;
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
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .recipient-options {
            display: none;
            margin-top: 10px;
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
        
        .alert-info {
            background: #e8f4fd;
            color: #3498db;
            border-left-color: #3498db;
        }
        
        .notification-preview {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-top: 10px;
            display: none;
        }
        
        .preview-header {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .preview-content {
            color: #555;
            line-height: 1.5;
        }
        
        .recipient-count {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #4a6ee0, #2c52c9);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stats-number {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
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
        <div class="back"><a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></div>
        <h2>Send Notifications</h2>
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
        
        <!-- Quick Stats -->
        <div class="form-row">
            <div class="stats-card">
                <div class="stats-number">
                    <?php
                    $total_tenants_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'tenant' AND email IS NOT NULL";
                    $total_result = $conn->query($total_tenants_sql);
                    $total_tenants = $total_result->fetch_assoc()['total'];
                    echo $total_tenants;
                    ?>
                </div>
                <div class="stats-label">Total Tenants</div>
            </div>
            <div class="stats-card">
                <div class="stats-number">
                    <?php
                    $assigned_sql = "SELECT COUNT(*) as assigned FROM users WHERE role = 'tenant' AND unit_number IS NOT NULL AND unit_number != ''";
                    $assigned_result = $conn->query($assigned_sql);
                    $assigned_tenants = $assigned_result->fetch_assoc()['assigned'];
                    echo $assigned_tenants;
                    ?>
                </div>
                <div class="stats-label">Assigned Units</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bell"></i> Compose Notification</h3>
            </div>
            
            <form method="POST" action="" id="notificationForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="notification_type" class="form-label">Notification Type *</label>
                        <select name="notification_type" id="notification_type" class="form-select" required>
                            <option value="">Select type</option>
                            <option value="maintenance">Maintenance Alert</option>
                            <option value="rent">Rent Reminder</option>
                            <option value="announcement">General Announcement</option>
                            <option value="emergency">Emergency Alert</option>
                            <option value="meeting">Meeting Notice</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipient_type" class="form-label">Send To *</label>
                        <select name="recipient_type" id="recipient_type" class="form-select" required>
                            <option value="">Select recipients</option>
                            <option value="all">All Tenants</option>
                            <option value="specific_tenant">Specific Tenant</option>
                            <option value="specific_unit">Specific Unit</option>
                            <option value="unassigned">Unassigned Tenants</option>
                        </select>
                    </div>
                </div>
                
                <!-- Specific Tenant Option -->
                <div id="specific_tenant_option" class="recipient-options">
                    <div class="form-group">
                        <label for="specific_tenant" class="form-label">Select Tenant</label>
                        <select name="specific_tenant" id="specific_tenant" class="form-select">
                            <option value="">Select a tenant</option>
                            <?php while($tenant = $tenants_result->fetch_assoc()): ?>
                                <option value="<?= $tenant['id'] ?>">
                                    <?= htmlspecialchars($tenant['full_name']) ?> 
                                    (<?= $tenant['email'] ?>)
                                    <?= $tenant['unit_number'] ? " - Unit " . $tenant['unit_number'] : '' ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Specific Unit Option -->
                <div id="specific_unit_option" class="recipient-options">
                    <div class="form-group">
                        <label for="specific_unit" class="form-label">Select Unit</label>
                        <select name="specific_unit" id="specific_unit" class="form-select">
                            <option value="">Select a unit</option>
                            <?php foreach($apartments as $apartment): ?>
                                <option value="<?= $apartment[0] ?>">
                                    <?= $apartment[0] ?> (<?= $apartment[1] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="subject" class="form-label">Subject *</label>
                    <input type="text" name="subject" id="subject" class="form-control" 
                           placeholder="Enter notification subject" required>
                </div>
                
                <div class="form-group">
                    <label for="message" class="form-label">Message *</label>
                    <textarea name="message" id="message" class="form-control" 
                              placeholder="Enter your notification message here..." required></textarea>
                    <small style="color: #7f8c8d; margin-top: 5px; display: block;">
                        You can use HTML tags for formatting. Maximum 2000 characters.
                    </small>
                </div>
                
                <!-- Preview Section -->
                <div class="notification-preview" id="notificationPreview">
                    <div class="preview-header">
                        <span>Preview</span>
                        <span class="recipient-count" id="recipientCount">0 recipients</span>
                    </div>
                    <div class="preview-content">
                        <strong id="previewSubject">Subject will appear here</strong>
                        <div id="previewMessage" style="margin-top: 10px;">Message content will appear here</div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> Send Notification
                    </button>
                    <button type="button" class="btn btn-primary" id="previewBtn">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <a href="admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Notification Tips -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-lightbulb"></i> Notification Tips</h3>
            </div>
            <div style="color: #555; line-height: 1.6;">
                <p><strong>Best Practices:</strong></p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>Keep subject lines clear and concise</li>
                    <li>Use bullet points for important information</li>
                    <li>Include relevant dates and deadlines</li>
                    <li>Specify any required actions from tenants</li>
                    <li>Provide contact information for questions</li>
                </ul>
                <p style="margin-top: 15px;"><strong>Emergency Alerts:</strong> Use for urgent matters requiring immediate attention.</p>
                <p><strong>Maintenance Alerts:</strong> Notify about scheduled maintenance or repairs.</p>
                <p><strong>Rent Reminders:</strong> Send 3-5 days before rent due date.</p>
            </div>
        </div>
    </div>

    <script>
        // Show/hide recipient options based on selection
        document.getElementById('recipient_type').addEventListener('change', function() {
            const recipientType = this.value;
            
            // Hide all options first
            document.querySelectorAll('.recipient-options').forEach(option => {
                option.style.display = 'none';
            });
            
            // Show selected option
            if (recipientType === 'specific_tenant') {
                document.getElementById('specific_tenant_option').style.display = 'block';
            } else if (recipientType === 'specific_unit') {
                document.getElementById('specific_unit_option').style.display = 'block';
            }
            
            updateRecipientCount();
        });
        
        // Preview functionality
        document.getElementById('previewBtn').addEventListener('click', function() {
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
            if (subject && message) {
                document.getElementById('previewSubject').textContent = subject;
                document.getElementById('previewMessage').innerHTML = message.replace(/\n/g, '<br>');
                document.getElementById('notificationPreview').style.display = 'block';
                updateRecipientCount();
            } else {
                alert('Please enter both subject and message to preview.');
            }
        });
        
        // Update recipient count
        function updateRecipientCount() {
            const recipientType = document.getElementById('recipient_type').value;
            let count = 0;
            
            // This would typically be an AJAX call to get actual counts
            // For demo, we'll use estimates
            switch(recipientType) {
                case 'all':
                    count = <?= $total_tenants ?>;
                    break;
                case 'specific_tenant':
                    count = document.getElementById('specific_tenant').value ? 1 : 0;
                    break;
                case 'specific_unit':
                    count = document.getElementById('specific_unit').value ? '2-4' : 0; // Estimate
                    break;
                case 'unassigned':
                    count = <?= $total_tenants - $assigned_tenants ?>;
                    break;
                default:
                    count = 0;
            }
            
            document.getElementById('recipientCount').textContent = count + ' recipients';
        }
        
        // Auto-update recipient count when options change
        document.getElementById('specific_tenant').addEventListener('change', updateRecipientCount);
        document.getElementById('specific_unit').addEventListener('change', updateRecipientCount);
        
        // Form validation
        document.getElementById('notificationForm').addEventListener('submit', function(e) {
            const recipientType = document.getElementById('recipient_type').value;
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
            if (!subject || !message) {
                alert('Please fill in both subject and message fields.');
                e.preventDefault();
                return;
            }
            
            if (recipientType === 'specific_tenant' && !document.getElementById('specific_tenant').value) {
                alert('Please select a specific tenant.');
                e.preventDefault();
                return;
            }
            
            if (recipientType === 'specific_unit' && !document.getElementById('specific_unit').value) {
                alert('Please select a specific unit.');
                e.preventDefault();
                return;
            }
            
            if (message.length > 2000) {
                alert('Message is too long. Maximum 2000 characters allowed.');
                e.preventDefault();
                return;
            }
            
            if (!confirm('Are you sure you want to send this notification?')) {
                e.preventDefault();
                return;
            }
        });
        
        // Auto-preview when typing
        let previewTimeout;
        document.getElementById('subject').addEventListener('input', function() {
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(updatePreview, 500);
        });
        
        document.getElementById('message').addEventListener('input', function() {
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(updatePreview, 500);
        });
        
        function updatePreview() {
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
            if (subject && message) {
                document.getElementById('previewSubject').textContent = subject;
                document.getElementById('previewMessage').innerHTML = message.replace(/\n/g, '<br>');
                if (document.getElementById('notificationPreview').style.display !== 'block') {
                    document.getElementById('notificationPreview').style.display = 'block';
                }
                updateRecipientCount();
            }
        }
    </script>
</body>
</html>
