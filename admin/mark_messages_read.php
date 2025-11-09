// mark_messages_read.php
<?php

session_start();
require_once '../auth/db_config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = $_POST['admin_id'] ?? 0;
    $tenant_id = $_POST['tenant_id'] ?? 0;
    
    if ($admin_id && $tenant_id) {
        // Mark messages from this tenant as read
        $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $stmt->bind_param("ii", $tenant_id, $admin_id);
        $stmt->execute();
        $stmt->close();
        
        error_log("Marked messages as read from tenant $tenant_id to admin $admin_id");
    }
}
?>
