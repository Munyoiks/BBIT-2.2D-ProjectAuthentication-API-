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
$password = '';

$success_message = '';
$error_message = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch user data including unit number
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT full_name, email, unit_number FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $full_name = $user['full_name'];
        $email = $user['email'];
        $unit_number = $user['unit_number'] ?? 'Not specified';
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
        $_SESSION['unit_number'] = $unit_number;
    } else {
        $full_name = $_SESSION['full_name'] ?? 'Tenant';
        $email = $_SESSION['email'] ?? '';
        $unit_number = $_SESSION['unit_number'] ?? 'Not specified';
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['submit_request'])) {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $priority = $_POST['priority'];
            $category = $_POST['category'];
            
            // Validate inputs
            if (empty($title) || empty($description)) {
                $error_message = "Please fill in all required fields.";
            } else {
                // Insert maintenance request using existing table structure
                $combined_issue = "Category: $category | Priority: " . ucfirst($priority) . " | Title: $title | Description: $description";
                
                $stmt = $pdo->prepare("INSERT INTO maintenance_requests (tenant_id, issue, status, unit_number, tenant_name) VALUES (?, ?, 'Pending', ?, ?)");
                $stmt->execute([$user_id, $combined_issue, $unit_number, $full_name]);
                
                // Send notification to admin
                sendAdminNotification($pdo, $user_id, $full_name, $unit_number, $title, $category, $priority, $description);
                
                $success_message = "Maintenance request submitted successfully! The admin has been notified.";
            }
        }
    }

    // Fetch maintenance requests using existing table structure
    $stmt = $pdo->prepare("SELECT * FROM maintenance_requests WHERE tenant_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $maintenance_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process the combined issue field to extract components for display
    foreach ($maintenance_requests as &$request) {
        $issue_parts = explode(' | ', $request['issue']);
        $request['category'] = 'Other';
        $request['priority'] = 'medium';
        $request['title'] = 'Maintenance Request';
        $request['description'] = $request['issue']; // Fallback to full issue
        
        foreach ($issue_parts as $part) {
            if (strpos($part, 'Category: ') === 0) {
                $request['category'] = substr($part, 10);
            } elseif (strpos($part, 'Priority: ') === 0) {
                $request['priority'] = strtolower(substr($part, 10));
            } elseif (strpos($part, 'Title: ') === 0) {
                $request['title'] = substr($part, 7);
            } elseif (strpos($part, 'Description: ') === 0) {
                $request['description'] = substr($part, 13);
            }
        }
    }
    unset($request); // Break the reference

} catch (PDOException $e) {
    $full_name = $_SESSION['full_name'] ?? 'Tenant';
    $email = $_SESSION['email'] ?? '';
    $unit_number = $_SESSION['unit_number'] ?? 'Not specified';
    $maintenance_requests = [];
    error_log("Database error: " . $e->getMessage());
    $error_message = "Unable to load maintenance requests. Please try again later.";
}

/**
 * Send notification to admin about new maintenance request
 */
function sendAdminNotification($pdo, $tenant_id, $tenant_name, $unit_number, $title, $category, $priority, $description) {
    try {
        // Create admin notifications table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            tenant_name VARCHAR(100) NOT NULL,
            unit_number VARCHAR(20) NULL,
            request_title VARCHAR(255) NOT NULL,
            request_category VARCHAR(100) NOT NULL,
            request_priority VARCHAR(20) NOT NULL,
            request_description TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // Insert notification
        $stmt = $pdo->prepare("INSERT INTO admin_notifications (tenant_id, tenant_name, unit_number, request_title, request_category, request_priority, request_description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $tenant_name, $unit_number, $title, $category, $priority, $description]);
        
        // You can also send email notification here if needed
        // sendAdminEmail($tenant_name, $unit_number, $title, $category, $priority, $description);
        
        return true;
    } catch (PDOException $e) {
        error_log("Admin notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email notification to admin (optional)
 */
function sendAdminEmail($tenant_name, $unit_number, $title, $category, $priority, $description) {
    $to = "admin@mojotenant.com"; // Replace with actual admin email
    $subject = "New Maintenance Request - " . ucfirst($priority) . " Priority";
    
    $message = "
    <html>
    <head>
        <title>New Maintenance Request</title>
    </head>
    <body>
        <h2>New Maintenance Request Submitted</h2>
        <table>
            <tr><td><strong>Tenant:</strong></td><td>$tenant_name</td></tr>
            <tr><td><strong>Unit Number:</strong></td><td>$unit_number</td></tr>
            <tr><td><strong>Title:</strong></td><td>$title</td></tr>
            <tr><td><strong>Category:</strong></td><td>$category</td></tr>
            <tr><td><strong>Priority:</strong></td><td>" . ucfirst($priority) . "</td></tr>
            <tr><td><strong>Description:</strong></td><td>$description</td></tr>
            <tr><td><strong>Submitted:</strong></td><td>" . date('Y-m-d H:i:s') . "</td></tr>
        </table>
        <br>
        <p>Please log in to the admin panel to review this request.</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: maintenance@mojotenant.com" . "\r\n";
    
    mail($to, $subject, $message, $headers);
}
?>