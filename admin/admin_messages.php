<?php
session_start();
require_once '../auth/db_config.php';

// Debug: Check session
error_log("Admin Messages - Session User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Admin Messages - Is Admin: " . ($_SESSION['is_admin'] ?? 'NOT SET'));

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$tenant_id = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tenant_id) {
    $msg = trim($_POST['message']);
    
    if (!empty($msg)) {
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
        error_log("Admin $admin_id sending message to tenant $tenant_id: $msg");
        
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, image, sent_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $admin_id, $tenant_id, $msg, $imagePath);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Message sent successfully!";
            error_log("Admin message saved to database successfully");
            
            // Redirect to avoid form resubmission
            header("Location: ?tenant_id=" . $tenant_id);
            exit();
        } else {
            $error = "Failed to send message. Error: " . $stmt->error;
            error_log("Admin message failed to save: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $error = "Message cannot be empty";
    }
}

// Check for success message from redirect
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Fetch all tenants (show all verified tenants)
$tenants_query = "
    SELECT u.id AS tenant_id, u.full_name, u.email, u.unit_number, u.phone
    FROM users u
    WHERE u.role = 'tenant' AND u.is_verified = 1
    ORDER BY u.full_name
";
$tenants_result = $conn->query($tenants_query);

// Fetch chat with specific tenant
$chat = null;
$tenant_info = null;
if ($tenant_id) {
    // Verify tenant exists and is actually a tenant
    $tenant_info_stmt = $conn->prepare("SELECT id, full_name, email, unit_number, phone FROM users WHERE id = ? AND role = 'tenant'");
    $tenant_info_stmt->bind_param("i", $tenant_id);
    $tenant_info_stmt->execute();
    $tenant_info = $tenant_info_stmt->get_result()->fetch_assoc();
    $tenant_info_stmt->close();

    if ($tenant_info) {
        // Get messages
        $chat_stmt = $conn->prepare("
            SELECT m.*, u.full_name AS sender_name 
            FROM messages m 
            LEFT JOIN users u ON m.sender_id = u.id 
            WHERE (m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?) 
            ORDER BY m.sent_at ASC
        ");
        $chat_stmt->bind_param("iiii", $tenant_id, $admin_id, $admin_id, $tenant_id);
        $chat_stmt->execute();
        $chat = $chat_stmt->get_result();
        
        // Debug: Count messages
        error_log("Found " . $chat->num_rows . " messages between admin $admin_id and tenant $tenant_id");
    } else {
        $error = "Tenant not found or invalid tenant ID";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Messages | Mojo Dashboard</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
  :root {
    --blue: #3498db;
    --purple1: #667eea;
    --purple2: #764ba2;
    --gradient: linear-gradient(135deg, var(--purple1), var(--purple2));
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
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: var(--gradient);
    height: 100vh;
    display: flex;
  }

  .sidebar {
    width: 350px;
    background: var(--card-bg);
    border-right: 2px solid rgba(0, 0, 0, 0.05);
    padding: 20px;
    overflow-y: auto;
    box-shadow: var(--shadow);
  }

  .sidebar-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f2f5;
  }

  .sidebar-header h3 {
    color: var(--purple2);
    margin: 0;
  }

  .tenant {
    padding: 15px;
    background: var(--light-bg);
    border-radius: 12px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
    position: relative;
  }

  .tenant:hover {
    background: rgba(103, 126, 234, 0.1);
    transform: translateX(4px);
    border-color: var(--blue);
  }

  .tenant.active {
    background: rgba(52, 152, 219, 0.15);
    border-color: var(--blue);
  }

  .tenant-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--purple1), var(--purple2));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.1em;
  }

  .tenant-info {
    flex: 1;
  }

  .tenant-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 4px;
  }

  .tenant-details {
    font-size: 0.85em;
    color: #7f8c8d;
  }

  .unread-badge {
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7em;
    display: flex;
    align-items: center;
    justify-content: center;
    position: absolute;
    top: 10px;
    right: 10px;
  }

  .chat-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--card-bg);
    margin: 20px;
    border-radius: 15px;
    box-shadow: var(--shadow);
    overflow: hidden;
  }

  .chat-header {
    background: var(--purple1);
    color: white;
    padding: 20px 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-radius: 15px 15px 0 0;
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
    padding: 25px;
    overflow-y: auto;
    background: var(--light-bg);
    display: flex;
    flex-direction: column;
  }

  .msg {
    max-width: 70%;
    margin: 8px 0;
    padding: 15px 18px;
    border-radius: 18px;
    position: relative;
    box-shadow: var(--shadow);
    word-wrap: break-word;
  }

  .sent {
    background: var(--blue);
    color: white;
    align-self: flex-end;
    border-bottom-right-radius: 5px;
  }

  .received {
    background: #e9ecef;
    color: #2c3e50;
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
    font-family: inherit;
    transition: all 0.3s ease;
    background: #f8f9fa;
    min-height: 60px;
    max-height: 120px;
  }

  textarea:focus {
    outline: none;
    border-color: var(--blue);
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
    border-color: var(--blue);
    background: #e8f4fd;
  }

  .file-upload input {
    display: none;
  }

  button {
    padding: 15px 25px;
    background: var(--blue);
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

  .debug-info {
    background: #f8f9fa;
    padding: 10px 15px;
    margin: 10px;
    border-radius: 8px;
    font-size: 0.8em;
    color: #666;
  }

  @media (max-width: 768px) {
    body {
      flex-direction: column;
    }
    
    .sidebar {
      width: 100%;
      height: 200px;
    }
    
    .chat-container {
      margin: 10px;
    }
    
    .msg {
      max-width: 85%;
    }
    
    .chat-header {
      flex-direction: column;
      gap: 10px;
      align-items: flex-start;
    }
    
    .refresh-btn {
      align-self: flex-end;
    }
  }
</style>
</head>
<body>
  <div class="sidebar">
    <div class="sidebar-header">
      <i class="fas fa-comments" style="color: var(--purple2); font-size: 1.5em;"></i>
      <h3>Tenant Conversations</h3>
    </div>
    
    <?php if ($tenants_result->num_rows > 0): ?>
      <?php while ($tenant = $tenants_result->fetch_assoc()): 
        // Check for unread messages
        $unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $unread_stmt->bind_param("ii", $tenant['tenant_id'], $admin_id);
        $unread_stmt->execute();
        $unread_result = $unread_stmt->get_result();
        $unread_count = $unread_result->fetch_assoc()['unread_count'];
        $unread_stmt->close();
      ?>
        <div class="tenant <?= $tenant['tenant_id'] == $tenant_id ? 'active' : '' ?>" 
             onclick="window.location.href='?tenant_id=<?= $tenant['tenant_id'] ?>'">
          <div class="tenant-avatar">
            <?= strtoupper(substr($tenant['full_name'], 0, 1)) ?>
          </div>
          <div class="tenant-info">
            <div class="tenant-name"><?= htmlspecialchars($tenant['full_name']) ?></div>
            <div class="tenant-details">
              <?= htmlspecialchars($tenant['email']) ?>
              <?php if ($tenant['unit_number']): ?>
                • Unit <?= htmlspecialchars($tenant['unit_number']) ?>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($unread_count > 0): ?>
            <div class="unread-badge" title="<?= $unread_count ?> unread messages">
              <?= $unread_count ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-users"></i>
        <h4>No Tenants</h4>
        <p>No verified tenants found.</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="chat-container">
    <?php if (isset($success)): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Debug Information -->
    <?php if ($tenant_id): ?>
      <div class="debug-info">
        <strong>Debug Info:</strong><br>
        Admin ID: <?= $admin_id ?><br>
        Tenant ID: <?= $tenant_id ?><br>
        Messages Found: <?= $chat ? $chat->num_rows : '0' ?>
      </div>
    <?php endif; ?>

    <?php if ($tenant_id && $tenant_info): ?>
      <div class="chat-header">
        <div class="chat-header-left">
          <div class="chat-header-avatar">
            <?= strtoupper(substr($tenant_info['full_name'], 0, 1)) ?>
          </div>
          <div class="chat-header-info">
            <h3><?= htmlspecialchars($tenant_info['full_name']) ?></h3>
            <p>
              <?= htmlspecialchars($tenant_info['email']) ?>
              <?php if ($tenant_info['unit_number']): ?>
                • Unit <?= htmlspecialchars($tenant_info['unit_number']) ?>
              <?php endif; ?>
              <?php if ($tenant_info['phone']): ?>
                • <?= htmlspecialchars($tenant_info['phone']) ?>
              <?php endif; ?>
            </p>
          </div>
        </div>
        <button class="refresh-btn" onclick="manualRefresh()">
          <i class="fas fa-sync-alt"></i> Refresh
        </button>
      </div>

      <div class="chat-box" id="chat-box">
        <?php if ($chat && $chat->num_rows > 0): ?>
          <?php while ($row = $chat->fetch_assoc()): ?>
            <div class="msg <?= ($row['sender_id'] == $admin_id) ? 'sent' : 'received'; ?>">
              <div class="msg-content">
                <?= nl2br(htmlspecialchars($row['message'])) ?>
                <?php if (!empty($row['image'])): ?>
                  <img src="../<?= htmlspecialchars($row['image']) ?>" alt="Attachment" onclick="openImage(this.src)">
                <?php endif; ?>
              </div>
              <div class="msg-time">
                <?= date('M j, g:i A', strtotime($row['sent_at'])) ?>
                <?php if ($row['sender_id'] == $admin_id): ?>
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
            <p>Start a conversation with <?= htmlspecialchars($tenant_info['full_name']) ?></p>
          </div>
        <?php endif; ?>
      </div>

      <form method="POST" action="?tenant_id=<?= $tenant_id ?>" enctype="multipart/form-data" class="message-form" id="messageForm">
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
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-comments fa-3x"></i>
        <h3>Select a Conversation</h3>
        <p>Choose a tenant from the sidebar to start messaging</p>
      </div>
    <?php endif; ?>
  </div>

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

  // Mark messages as read when viewing conversation
  <?php if ($tenant_id && $tenant_info): ?>
    // Mark messages as read when page loads
    fetch('mark_messages_read.php?tenant_id=<?= $tenant_id ?>', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'admin_id=<?= $admin_id ?>&tenant_id=<?= $tenant_id ?>'
    });
  <?php endif; ?>
</script>
</body>
</html>
