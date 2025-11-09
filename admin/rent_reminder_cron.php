// rent_reminder_cron.php
<?php

require_once '../auth/db_config.php'; // Your database connection file

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    exit;
}

// Include your functions
require_once 'system_settings.php';

$result = sendRentReminders($conn);
error_log("Rent reminders cron: " . $result['message']);

$conn->close();
?>
