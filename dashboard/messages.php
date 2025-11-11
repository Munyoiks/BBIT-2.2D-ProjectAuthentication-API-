//messages.php
<?php
session_start();
require_once '../auth/db_config.php';

// Check if tenant is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get the ACTUAL admin ID from database (the one with is_admin = 1)
$admin_query = $conn->query("SELECT id, full_name FROM users WHERE is_admin = 1 LIMIT 1");
if ($admin_query->num_rows > 0) {
    $admin_data = $admin_query->fetch_assoc();
    $admin_id = $admin_data['id'];
    $admin_name = $admin_data['full_name'];
} else {
    die("No admin user found in system");
}

// Verify user is actually a tenant
$user_check = $conn->prepare("SELECT id, full_name, email FROM users WHERE id = ?");
$user_check->bind_param("i", $user_id);
$user_check->execute();
$user_result = $user_check->get_result();

if ($user_result->num_rows === 0) {
    die("Access denied: User not found");
}
$user_data = $user_result->fetch_assoc();
$user_check->close();

$success = '';
$error = '';

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    
    if (!empty($message)) {
        $imagePath = NULL;

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "../uploads/messages/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            $fileName = time() . "_" . basename($_FILES['image']['name']);
            $targetFile = $targetDir . $fileName;

            // Validate image type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($_FILES['image']['tmp_name']);
            
            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imagePath = "uploads/messages/" . $fileName;
                }
            }
        }

        // Debug: Log the message attempt
        error_log("Tenant $user_id sending message to admin $admin_id: $message");
        
        // Insert message - CORRECTED: sending to actual admin ID 7
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, image, sent_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("iiss", $user_id, $admin_id, $message, $imagePath);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Message sent successfully to $admin_name!";
                error_log("Tenant message saved to database - Message ID: " . $stmt->insert_id);
                
                // Redirect to avoid form resubmission
                header("Location: messages.php");
                exit();
            } else {
                $error = "Failed to send message. Database Error: " . $stmt->error;
                error_log("Tenant message failed: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $error = "Failed to prepare statement: " . $conn->error;
            error_log("Prepare failed: " . $conn->error);
        }
    } else {
        $error = "Message cannot be empty";
    }
}

// Check for success message from redirect
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Retrieve messages between THIS tenant and THE ACTUAL admin
$stmt = $conn->prepare("
    SELECT m.*, u.full_name AS sender_name 
    FROM messages m 
    LEFT JOIN users u ON m.sender_id = u.id 
    WHERE (m.sender_id = ? AND m.receiver_id = ?) 
       OR (m.sender_id = ? AND m.receiver_id = ?) 
    ORDER BY m.sent_at ASC
");

if ($stmt) {
    $stmt->bind_param("iiii", $user_id, $admin_id, $admin_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Debug: Count messages
    $message_count = $result->num_rows;
    error_log("Tenant $user_id found $message_count messages with admin $admin_id");
} else {
    $error = "Failed to prepare message query: " . $conn->error;
    $result = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages | Mojo Tenant</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --light-bg: #f8f9fa;
            --card-bg: #ffffff;
            --shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0; 
            padding: 20px;
            min-height: 100vh;
        }

        .chat-container {
            width: 600px; 
            max-width: 100%; 
            margin: 20px auto; 
            background: var(--card-bg);
            border-radius: 16px; 
            box-shadow: var(--shadow);
            display: flex; 
            flex-direction: column; 
            height: 80vh;
            overflow: hidden;
        }

        .chat-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 16px 16px 0 0;
        }

        .chat-header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .chat-header-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            font-weight: bold;
        }

        .chat-header-info h3 {
            margin: 0;
            font-size: 1.2em;
        }

        .chat-header-info p {
            margin: 4px 0 0 0;
            opacity: 0.9;
            font-size: 0.9em;
        }

        .refresh-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .chat-box {
            flex: 1; 
            padding: 20px; 
            overflow-y: auto; 
            background: var(--light-bg);
            display: flex;
            flex-direction: column;
        }

        .msg {
            max-width: 75%; 
            margin: 8px 0; 
            padding: 15px 18px; 
            border-radius: 18px;
            position: relative;
            box-shadow: var(--shadow);
            word-wrap: break-word;
        }

        .sent {
            background: var(--primary); 
            color: white; 
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }

        .received {
            background: #e9ecef; 
            color: var(--secondary);
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }

        .msg-content {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .msg-time {
            font-size: 0.75em;
            opacity: 0.8;
            text-align: right;
        }

        .sent .msg-time { color: rgba(255,255,255,0.8); }
        .received .msg-time { color: #95a5a6; }

        .msg img {
            max-width: 250px;
            border-radius: 12px;
            margin-top: 10px;
            border: 2px solid rgba(0,0,0,0.1);
            cursor: pointer;
        }

        .message-form {
            padding: 20px;
            background: white;
            border-top: 2px solid #e9ecef;
        }

        .form-group {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        textarea {
            flex: 1;
            resize: none; 
            padding: 15px 18px; 
            border-radius: 12px; 
            border: 2px solid #ddd;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            font-family: inherit;
            min-height: 60px;
            max-height: 120px;
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .file-upload {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 15px;
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload:hover {
            border-color: var(--primary);
            background: #e8f4fd;
        }

        .file-upload input {
            display: none;
        }

        button {
            padding: 15px 25px; 
            background: var(--primary); 
            color: white; 
            border: none;
            border-radius: 12px; 
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        button:hover { 
            background: #2176bd; 
            transform: translateY(-2px);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            margin: 20px auto;
            display: block;
            width: fit-content;
            padding: 12px 24px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .back-link:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .alert {
            padding: 12px 16px;
            margin: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d5f4e6;
            color: #27ae60;
            border-left-color: #2ecc71;
        }

        .alert-error {
            background: #fdecea;
            color: #e74c3c;
            border-left-color: #e74c3c;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #bdc3c7;
        }

        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 20px;
            border-radius: 8px;
            font-size: 0.9em;
            color: #666;
        }

        @media (max-width: 650px) {
            .chat-container { 
                height: 85vh; 
                margin: 10px auto; 
            }
            .msg { max-width: 85%; }
            .form-group { flex-direction: column; }
            .file-upload { width: 100%; justify-content: center; }
            .chat-header { flex-direction: column; gap: 10px; align-items: flex-start; }
            .refresh-btn { align-self: flex-end; }
        }
    </style>
</head>
<body>

    <?php if (isset($success)): ?>
        <div class="alert alert-success" style="max-width: 600px; margin: 20px auto;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error" style="max-width: 600px; margin: 20px auto;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Debug Information -->
    <div class="debug-info" style="max-width: 600px; margin: 10px auto;">
        <strong>Debug Info:</strong><br>
        Tenant: <?= htmlspecialchars($user_data['full_name']) ?> (ID: <?= $user_id ?>)<br>
        Admin: <?= htmlspecialchars($admin_name) ?> (ID: <?= $admin_id ?>)<br>
        Messages Found: <?= $result ? $result->num_rows : 'Error' ?>
    </div>

    <div class="chat-container">
        <!-- Chat Header -->
        <div class="chat-header">
            <div class="chat-header-left">
                <div class="chat-header-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="chat-header-info">
                    <h3>Messages with <?= htmlspecialchars($admin_name) ?></h3>
                    <p>Contact building administration</p>
                    <small style="opacity: 0.7;">You: <?= htmlspecialchars($user_data['full_name']) ?></small>
                </div>
            </div>
            <button class="refresh-btn" onclick="manualRefresh()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>

        <!-- Chat Messages -->
        <div class="chat-box" id="chat-box">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="msg <?= ($row['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                        <div class="msg-content">
                            <?= nl2br(htmlspecialchars($row['message'])) ?>
                            <?php if (!empty($row['image'])): ?>
                                <img src="../<?= htmlspecialchars($row['image']) ?>" alt="Attachment" onclick="openImage(this.src)">
                            <?php endif; ?>
                        </div>
                        <div class="msg-time">
                            <?= date('M j, Y g:i A', strtotime($row['sent_at'])) ?>
                            <?php if ($row['sender_id'] == $user_id): ?>
                                <br><small style="opacity: 0.6;">(You)</small>
                            <?php else: ?>
                                <br><small style="opacity: 0.6;">(<?= htmlspecialchars($row['sender_name']) ?>)</small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comment-dots"></i>
                    <h4>No Messages Yet</h4>
                    <p>Start a conversation with <?= htmlspecialchars($admin_name) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Send Message Form -->
        <form method="POST" action="" enctype="multipart/form-data" class="message-form" id="messageForm">
            <div class="form-group">
                <textarea name="message" rows="2" placeholder="Type your message..." required maxlength="1000" id="messageInput"></textarea>
                
                <label class="file-upload">
                    <i class="fas fa-paperclip"></i>
                    <input type="file" name="image" accept="image/*" id="fileInput">
                </label>
                
                <button type="submit" id="sendButton">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </div>
        </form>
    </div>

    <!-- Back to Dashboard -->
    <a href="dashboard.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <script>
        // Auto-scroll to bottom
        const chatBox = document.getElementById('chat-box');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

        // Auto-resize textarea
        const textarea = document.getElementById('messageInput');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });

            // Submit form on Enter (but allow Shift+Enter for new line)
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    document.getElementById('messageForm').submit();
                }
            });
        }

        // Show selected file name
        const fileInput = document.getElementById('fileInput');
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const label = this.parentElement;
                if (this.files.length > 0) {
                    label.innerHTML = <i class="fas fa-check"></i> ${this.files[0].name};
                    label.style.background = '#d5f4e6';
                    label.style.borderColor = '#2ecc71';
                } else {
                    label.innerHTML = '<i class="fas fa-paperclip"></i>';
                    label.style.background = '#f8f9fa';
                    label.style.borderColor = '#ddd';
                }
            });
        }

        function openImage(src) {
            window.open(src, '_blank');
        }

        // Manual refresh function
        function manualRefresh() {
            window.location.reload();
        }

        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>

</body>
</html>

<?php
if (isset($stmt)) $stmt->close();
$conn->close();
?>
