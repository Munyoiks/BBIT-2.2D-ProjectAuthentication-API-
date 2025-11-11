// notifications.php
<?php
session_start();
require_once "../auth/db_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications_result = $stmt->get_result();
$notifications = $notifications_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Mark all as read when viewing notifications page
$update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();
$update_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Mojo Tenant</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .notification-item {
            border-left: 4px solid;
            margin-bottom: 15px;
        }
        .notification-success { border-left-color: #28a745; }
        .notification-warning { border-left-color: #ffc107; }
        .notification-danger { border-left-color: #dc3545; }
        .notification-info { border-left-color: #17a2b8; }
    </style>
</head>
<body>
    <!-- Use the same sidebar structure as your dashboard -->
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <?php if (!empty($notifications)): ?>
            <div class="list-group">
                <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item notification-item 
                        <?= $notification['type'] == 'success' ? 'notification-success' : '' ?>
                        <?= $notification['type'] == 'warning' ? 'notification-warning' : '' ?>
                        <?= $notification['type'] == 'error' ? 'notification-danger' : '' ?>
                        <?= $notification['type'] == 'info' ? 'notification-info' : '' ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">
                                <?php if ($notification['type'] == 'success'): ?>
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                <?php elseif ($notification['type'] == 'warning'): ?>
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                <?php elseif ($notification['type'] == 'error'): ?>
                                    <i class="fas fa-times-circle text-danger me-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-info-circle text-primary me-2"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($notification['title']) ?>
                            </h5>
                            <small class="text-muted"><?= date('F j, Y g:i A', strtotime($notification['created_at'])) ?></small>
                        </div>
                        <p class="mb-1"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-inbox fa-2x mb-3"></i>
                <h4>No Notifications</h4>
                <p class="mb-0">You don't have any notifications at the moment.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
