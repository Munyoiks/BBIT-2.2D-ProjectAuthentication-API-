<?php
session_start();

// If not logged in, redirect to authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: authentication.php");
    exit();
}

$email = $_SESSION['email'] ?? 'Unknown';
$phone = $_SESSION['phone'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
</head>
<body>
    <h1>Welcome to your Dashboard </h1>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
    <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>

    <p>You are now logged in with 2FA successfully.</p>

    <form method="POST" action="logout.php">
        <button type="submit" name="logout">Logout</button>
    </form>
</body>
</html>

