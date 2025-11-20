<?php
session_start();
require_once "../auth/db_config.php";
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

// Validate POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['suggest_title'], $_POST['suggest_message'])) {
    $user_id = $_SESSION['user_id'];
    $admin_id = 1; // Main admin

    $title = trim($_POST['suggest_title']);
    $message = trim($_POST['suggest_message']);

    if (empty($title) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Both title and message are required.']);
        exit;
    }

    // Escape input
    $title_esc = mysqli_real_escape_string($conn, $title);
    $msg_esc = mysqli_real_escape_string($conn, $message);

    // Insert suggestion into DB with status 'pending'
    $query = "INSERT INTO announcements (title, message, status, posted_by, suggested_by, created_at) 
              VALUES ('$title_esc', '$msg_esc', 'pending', '$admin_id', '$user_id', NOW())";

    if (mysqli_query($conn, $query)) {
        $suggestion_id = mysqli_insert_id($conn);

        // Optionally, fetch last approved announcements for live update
        $latest_result = mysqli_query($conn, "
            SELECT a.*, u.full_name AS author_name 
            FROM announcements a 
            LEFT JOIN users u ON a.posted_by = u.id 
            WHERE a.status = 'approved'
            ORDER BY a.created_at DESC
            LIMIT 5
        ");

        $latest_announcements = [];
        while ($row = mysqli_fetch_assoc($latest_result)) {
            $latest_announcements[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'message' => nl2br(htmlspecialchars($row['message'])),
                'author' => htmlspecialchars($row['author_name'] ?? 'Admin'),
                'created_at' => date('M j, Y \a\t g:i A', strtotime($row['created_at']))
            ];
        }

        echo json_encode([
            'success' => true,
            'message' => 'Your announcement suggestion has been sent to admin for approval.',
            'latest_announcements' => $latest_announcements
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send suggestion. Try again.']);
    }

    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
