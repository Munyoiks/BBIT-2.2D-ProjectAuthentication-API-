<?php
// debug_messages.php
session_start();
require_once '../auth/db_config.php';

echo "<h3>Debug Messages System</h3>";

// Check messages table structure
$result = $conn->query("SHOW COLUMNS FROM messages");
echo "<h4>Messages Table Structure:</h4>";
while ($row = $result->fetch_assoc()) {
    echo "Field: {$row['Field']} | Type: {$row['Type']}<br>";
}

// Check recent messages
echo "<h4>Recent Messages:</h4>";
$recent = $conn->query("SELECT * FROM messages ORDER BY sent_at DESC LIMIT 10");
if ($recent->num_rows > 0) {
    while ($msg = $recent->fetch_assoc()) {
        echo "ID: {$msg['id']} | From: {$msg['sender_id']} | To: {$msg['receiver_id']} | Message: " . substr($msg['message'], 0, 50) . " | Time: {$msg['sent_at']}<br>";
    }
} else {
    echo "No messages found in database.<br>";
}

// Check admin user
echo "<h4>Admin User:</h4>";
$admin = $conn->query("SELECT id, full_name, email FROM users WHERE is_admin = 1 LIMIT 1");
if ($admin->num_rows > 0) {
    $admin_data = $admin->fetch_assoc();
    echo "Admin ID: {$admin_data['id']} | Name: {$admin_data['full_name']} | Email: {$admin_data['email']}<br>";
} else {
    echo "No admin user found.<br>";
}

// Check current tenant
if (isset($_SESSION['user_id'])) {
    $tenant_id = $_SESSION['user_id'];
    $tenant = $conn->query("SELECT id, full_name, email FROM users WHERE id = $tenant_id");
    if ($tenant->num_rows > 0) {
        $tenant_data = $tenant->fetch_assoc();
        echo "<h4>Current Tenant:</h4>";
        echo "Tenant ID: {$tenant_data['id']} | Name: {$tenant_data['full_name']} | Email: {$tenant_data['email']}<br>";
    }
}
?>
