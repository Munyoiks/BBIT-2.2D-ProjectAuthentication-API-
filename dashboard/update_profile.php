<?php
session_start();
require_once "../auth/db_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');

    // Validate required fields
    if (empty($full_name) || empty($phone)) {
        $error = "Full name and phone number are required fields.";
    } else {
        try {
            // Update user profile in database
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, emergency_contact = ? WHERE id = ?");
            $stmt->bind_param("sssi", $full_name, $phone, $emergency_contact, $user_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success = "Profile updated successfully!";
                } else {
                    $error = "No changes were made to your profile.";
                }
            } else {
                $error = "Database error: " . $stmt->error;
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $error = "An error occurred while updating your profile: " . $e->getMessage();
        }
    }
}

// Fetch updated user data to display
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Redirect back to profile page with messages
$_SESSION['profile_update_success'] = $success;
$_SESSION['profile_update_error'] = $error;
header("Location: profile.php");
exit();
?>
