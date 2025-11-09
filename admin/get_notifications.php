<?php
session_start();
require_once "../auth/db_config.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    exit("Unauthorized access");
}

$tenant_id = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : null;

if ($tenant_id) {
    // Get notifications for specific tenant
    $query = "
        SELECT n.*, u.full_name as admin_name, t.full_name as tenant_name
        FROM notifications n
        LEFT JOIN users u ON n.admin_id = u.id
        LEFT JOIN users t ON n.tenant_id = t.id
        WHERE n.tenant_id = ?
        ORDER BY n.sent_date DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Get all notifications
    $query = "
        SELECT n.*, u.full_name as admin_name, t.full_name as tenant_name
        FROM notifications n
        LEFT JOIN users u ON n.admin_id = u.id
        LEFT JOIN users t ON n.tenant_id = t.id
        ORDER BY n.sent_date DESC
        LIMIT 50
    ";
    $result = $conn->query($query);
}

if ($result->num_rows > 0) {
    echo '<div class="notifications-list">';
    while($notification = $result->fetch_assoc()) {
        echo '
        <div class="notification-item" style="border-left: 4px solid #3498db; padding: 15px; margin-bottom: 15px; background: #f8f9fa; border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <strong>' . htmlspecialchars($notification['subject']) . '</strong>
                <small style="color: #666;">' . date('M j, Y g:i A', strtotime($notification['sent_date'])) . '</small>
            </div>
            <p style="margin-bottom: 8px; color: #555;">' . nl2br(htmlspecialchars($notification['message'])) . '</p>
            <div style="font-size: 0.9em; color: #666;">
                <strong>To:</strong> ' . htmlspecialchars($notification['tenant_name']) . ' | 
                <strong>From:</strong> ' . htmlspecialchars($notification['admin_name']) . ' |
                <strong>Status:</strong> ' . ($notification['is_read'] ? 'Read' : 'Unread')
        . '</div>
        </div>';
    }
    echo '</div>';
} else {
    echo '<div class="empty-state" style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-bell-slash" style="font-size: 3em; margin-bottom: 15px; color: #ccc;"></i>
            <h3>No Notifications</h3>
            <p>No notifications found.</p>
          </div>';
}
?>
