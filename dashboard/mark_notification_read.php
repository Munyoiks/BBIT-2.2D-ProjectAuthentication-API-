<?php
session_start();
require_once "../auth/db_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $user_id = $_SESSION['user_id'];
    
    // Verify the notification belongs to the user
    $check_stmt = $conn->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $notification_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Mark as read
        $update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $update_stmt->bind_param("i", $notification_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    $check_stmt->close();
}

// Redirect back to previous page
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
exit();
?>
