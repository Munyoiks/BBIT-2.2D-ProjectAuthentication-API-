<?php
session_start();
require_once '../auth/db_config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Update profile info
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST['full_name']);
    $email = strtolower(trim($_POST['email']));
    $phone = trim($_POST['phone']);
    $emergency_contact = trim($_POST['emergency_contact']);

    if (empty($full_name) || empty($email) || empty($phone)) {
        $_SESSION['update_message'] = "error:Please fill in all required fields.";
    } else {
        try {
            // Check if email already exists for another user
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->bind_param("si", $email, $user_id);
            $check_email->execute();
            $email_result = $check_email->get_result();
            
            if ($email_result->num_rows > 0) {
                // Email already exists for another user
                $_SESSION['update_message'] = "error:This email is already registered by another user. Please use a different email.";
            } else {
                // Email is available, proceed with update
                
                // Check if emergency_contact column exists
                $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'emergency_contact'");
                
                if ($check_column->num_rows > 0) {
                    // Column exists, update with emergency_contact
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, emergency_contact=? WHERE id=?");
                    $stmt->bind_param("ssssi", $full_name, $email, $phone, $emergency_contact, $user_id);
                } else {
                    // Column doesn't exist, update without emergency_contact
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
                    $stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
                }
                
                if ($stmt->execute()) {
                    // Update session data
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    $_SESSION['update_message'] = "success:Profile updated successfully!";
                } else {
                    $_SESSION['update_message'] = "error:Error updating profile: " . htmlspecialchars($stmt->error);
                }
                $stmt->close();
            }
            $check_email->close();
            
        } catch (Exception $e) {
            $_SESSION['update_message'] = "error:Database error: " . htmlspecialchars($e->getMessage());
        }
    }
    
    // Always redirect back to profile.php
    header("Location: profile.php");
    exit();
} else {
    // If someone tries to access this page directly, redirect to profile
    header("Location: profile.php");
    exit();
}
?>