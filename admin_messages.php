<?php
session_start();
require_once "../auth/db_config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_email = $_SESSION['email'];
$admin_name = $_SESSION['full_name'] ?? 'Administrator';

// Get tenant list for sidebar
$tenants = $conn->query("
    SELECT u.id, u.full_name, u.email, u.phone, a.apartment_number 
    FROM users u 
    LEFT JOIN apartments a ON u.id = a.tenant_id 
    WHERE u.is_admin = 0 AND u.is_verified = 1
    ORDER BY u.full_name
");

// Handle selected tenant
$selected_tenant_id = $_GET['tenant_id'] ?? null;
$selected_tenant = null;
$messages = [];

if ($selected_tenant_id) {
    // Get tenant details
    $selected_tenant = $conn->query("
        SELECT u.id, u.full_name, u.email, u.phone, a.apartment_number 
        FROM users u 
        LEFT JOIN apartments a ON u.id = a.tenant_id 
        WHERE u.id = '$selected_tenant_id'
    ")->fetch_assoc();

    // Get messages
    $messages = $conn->query("
        SELECT * FROM messages 
        WHERE (sender_id = '$selected_tenant_id' AND receiver_id = '{$_SESSION['user_id']}') 
           OR (sender_id = '{$_SESSION['user_id']}' AND receiver_id = '$selected_tenant_id')
        ORDER BY created_at ASC
    ");

    // Mark messages as read
    $conn->query("UPDATE messages SET is_read = 1 WHERE receiver_id = '{$_SESSION['user_id']}' AND sender_id = '$selected_tenant_id'");
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $selected_tenant_id) {
    $message = $conn->real_escape_string($_POST['message']);
    
    $insert_query = "INSERT INTO messages (sender_id, receiver_id, message, created_at) 
                     VALUES ('{$_SESSION['user_id']}', '$selected_tenant_id', '$message', NOW())";
    
    if ($conn->query($insert_query)) {
        $_SESSION['success_message'] = "Message sent successfully!";
        header("Location: messages.php?tenant_id=$selected_tenant_id");
        exit();
    } else {
        $_SESSION['error_message'] = "Error sending message: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages | Mojo Tenant Management System</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
:root {
  --primary: #2c3e50;
  --secondary: #3498db;
  --success: #27ae60;
  --warning: #f39c12;
  --danger: #e74c3c;
  --light: #ecf0f1;
  --dark: #2c3e50;
  --sidebar: #34495e;
  --sidebar-hover: #2c3e50;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #333;
  min-height: 100vh;
  display: flex;
}

/* Sidebar Styles */
.sidebar {
  width: 280px;
  background: var(--sidebar);
  color: white;
  padding: 0;
  display: flex;
  flex-direction: column;
  box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar-header {
  padding: 30px 25px;
  background: var(--primary);
  border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header h2 {
  font-size: 22px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
}

.sidebar-header h2 i {
  color: var(--secondary);
}

.sidebar-nav {
  flex: 1;
  padding: 20px 0;
}

.sidebar-nav a {
  color: #bdc3c7;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 15px 25px;
  transition: all 0.3s ease;
  border-left: 4px solid transparent;
  font-weight: 500;
}

.sidebar-nav a:hover {
  background: var(--sidebar-hover);
  color: white;
  border-left-color: var(--secondary);
}

.sidebar-nav a.active {
  background: var(--sidebar-hover);
  color: white;
  border-left-color: var(--secondary);
}

.sidebar-nav a i {
  width: 20px;
  text-align: center;
}

.logout-section {
  padding: 15px 25px;
  border-top: 1px solid rgba(255,255,255,0.1);
  margin-top: auto;
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  background: var(--danger);
  color: white;
  text-decoration: none;
  border-radius: 8px;
  transition: all 0.3s ease;
  font-weight: 500;
  width: 100%;
  border: none;
  cursor: pointer;
  font-size: 14px;
}

.logout-btn:hover {
  background: #c0392b;
  transform: translateY(-2px);
}

/* Main Content Styles */
.main {
  flex: 1;
  padding: 30px;
  overflow-y: auto;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  background: white;
  padding: 25px 30px;
  border-radius: 15px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.welcome-section h1 {
  font-size: 28px;
  color: var(--primary);
  margin-bottom: 5px;
}

.welcome-section p {
  color: #7f8c8d;
  font-size: 16px;
}

.admin-info {
  display: flex;
  align-items: center;
  gap: 15px;
}

.admin-avatar {
  width: 50px;
  height: 50px;
  background: linear-gradient(135deg, var(--secondary), #2980b9);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 18px;
}

/* Messages Layout */
.messages-container {
  display: flex;
  gap: 30px;
  height: calc(100vh - 200px);
}

.tenants-sidebar {
  width: 350px;
  background: white;
  border-radius: 15px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.tenants-header {
  padding: 20px;
  border-bottom: 1px solid #ecf0f1;
}

.tenants-header h3 {
  font-size: 18px;
  color: var(--primary);
  font-weight: 600;
}

.tenants-list {
  flex: 1;
  overflow-y: auto;
}

.tenant-item {
  padding: 15px 20px;
  border-bottom: 1px solid #ecf0f1;
  cursor: pointer;
  transition: all 0.3s ease;
}

.tenant-item:hover {
  background: #f8f9fa;
}

.tenant-item.active {
  background: #e3f2fd;
  border-left: 4px solid var(--secondary);
}

.tenant-name {
  font-weight: 600;
  color: var(--primary);
  margin-bottom: 5px;
}

.tenant-details {
  font-size: 12px;
  color: #7f8c8d;
}

.chat-area {
  flex: 1;
  background: white;
  border-radius: 15px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.chat-header {
  padding: 20px;
  border-bottom: 1px solid #ecf0f1;
  background: var(--primary);
  color: white;
}

.chat-header h3 {
  font-size: 18px;
  font-weight: 600;
}

.chat-messages {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
  background: #f8f9fa;
}

.message {
  margin-bottom: 15px;
  display: flex;
}

.message.sent {
  justify-content: flex-end;
}

.message.received {
  justify-content: flex-start;
}

.message-bubble {
  max-width: 70%;
  padding: 12px 16px;
  border-radius: 18px;
  position: relative;
}

.message.sent .message-bubble {
  background: var(--secondary);
  color: white;
  border-bottom-right-radius: 5px;
}

.message.received .message-bubble {
  background: white;
  color: #333;
  border: 1px solid #e0e0e0;
  border-bottom-left-radius: 5px;
}

.message-time {
  font-size: 11px;
  color: #7f8c8d;
  margin-top: 5px;
  text-align: right;
}

.message.received .message-time {
  text-align: left;
}

.chat-input {
  padding: 20px;
  border-top: 1px solid #ecf0f1;
  background: white;
}

.message-form {
  display: flex;
  gap: 10px;
}

.message-input {
  flex: 1;
  padding: 12px 15px;
  border: 1px solid #ddd;
  border-radius: 25px;
  font-size: 14px;
  outline: none;
}

.message-input:focus {
  border-color: var(--secondary);
}

.send-btn {
  padding: 12px 25px;
  background: var(--secondary);
  color: white;
  border: none;
  border-radius: 25px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
}

.send-btn:hover {
  background: #2980b9;
  transform: translateY(-2px);
}

.send-btn:disabled {
  background: #bdc3c7;
  cursor: not-allowed;
  transform: none;
}

.no-chat {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #7f8c8d;
  text-align: center;
}

.no-chat i {
  font-size: 48px;
  margin-bottom: 15px;
  color: #bdc3c7;
}

/* Alert Messages */
.alert {
  padding: 15px 20px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-weight: 500;
}

.alert-success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.alert-error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

/* Responsive Design */
@media (max-width: 768px) {
  body {
    flex-direction: column;
  }
  
  .sidebar {
    width: 100%;
    height: auto;
  }
  
  .sidebar-nav {
    display: flex;
    flex-wrap: wrap;
    padding: 10px;
  }
  
  .sidebar-nav a {
    flex: 1;
    min-width: 120px;
    justify-content: center;
    text-align: center;
    border-left: none;
    border-bottom: 4px solid transparent;
  }
  
  .sidebar-nav a:hover,
  .sidebar-nav a.active {
    border-left: none;
    border-bottom-color: var(--secondary);
  }
  
  .logout-section {
    text-align: center;
  }
  
  .messages-container {
    flex-direction: column;
    height: auto;
  }
  
  .tenants-sidebar {
    width: 100%;
    height: 300px;
  }
  
  .message-bubble {
    max-width: 85%;
  }
}
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
  <div class="sidebar-header">
    <h2><i class="fas fa-building"></i> Mojo Management</h2>
  </div>
  
  <nav class="sidebar-nav">
    <a href="admin_dashboard.php">
      <i class="fas fa-tachometer-alt"></i>Dashboard
    </a>
    <a href="manage_tenants.php">
      <i class="fas fa-users"></i>Tenants
    </a>
    <a href="manage_apartments.php">
      <i class="fas fa-building"></i>Apartments
    </a>
    <a href="rent_payments.php">
      <i class="fas fa-money-bill-wave"></i>Payments
    </a>
    <a href="mpesa_transactions.php">
      <i class="fas fa-mobile-alt"></i>MPESA Transactions
    </a>
    <a href="review_agreements.php">
      <i class="fas fa-file-contract"></i>Agreements
    </a>
    <a href="maintenance.php">
      <i class="fas fa-tools"></i>Maintenance
    </a>
    <a href="messages.php" class="active">
      <i class="fas fa-comments"></i>Messages
    </a>
    <a href="announcements.php">
      <i class="fas fa-bullhorn"></i>Announcements
    </a>
    <a href="generate_reports.php">
      <i class="fas fa-chart-bar"></i>Reports
    </a>
    <a href="system_settings.php">
      <i class="fas fa-cog"></i>Settings
    </a>
    
    <div class="logout-section">
      <a href="../auth/login.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>Logout
      </a>
    </div>
  </nav>
</div>

<!-- Main Content -->
<div class="main">
  <!-- Header -->
  <div class="header">
    <div class="welcome-section">
      <h1>Messages 💬</h1>
      <p>Communicate with your tenants</p>
    </div>
    <div class="admin-info">
      <div class="admin-avatar">
        <?= strtoupper(substr($admin_name, 0, 1)) ?>
      </div>
      <div>
        <strong><?= htmlspecialchars($admin_name) ?></strong>
        <p style="color: #7f8c8d; font-size: 14px;">Administrator</p>
      </div>
    </div>
  </div>

  <!-- Alert Messages -->
  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
      <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-error">
      <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
  <?php endif; ?>

  <!-- Messages Container -->
  <div class="messages-container">
    <!-- Tenants Sidebar -->
    <div class="tenants-sidebar">
      <div class="tenants-header">
        <h3>Tenants</h3>
      </div>
      <div class="tenants-list">
        <?php while($tenant = $tenants->fetch_assoc()): ?>
          <div class="tenant-item <?= $selected_tenant_id == $tenant['id'] ? 'active' : '' ?>" 
               onclick="window.location.href='messages.php?tenant_id=<?= $tenant['id'] ?>'">
            <div class="tenant-name"><?= htmlspecialchars($tenant['full_name']) ?></div>
            <div class="tenant-details">
              <?= htmlspecialchars($tenant['email']) ?> • 
              Apt: <?= htmlspecialchars($tenant['apartment_number'] ?? 'N/A') ?>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Chat Area -->
    <div class="chat-area">
      <?php if ($selected_tenant): ?>
        <div class="chat-header">
          <h3>
            <i class="fas fa-user"></i> 
            Chat with <?= htmlspecialchars($selected_tenant['full_name']) ?>
            <small style="font-size: 14px; font-weight: normal;">
              - <?= htmlspecialchars($selected_tenant['apartment_number'] ?? 'No apartment assigned') ?>
            </small>
          </h3>
        </div>
        
        <div class="chat-messages" id="chatMessages">
          <?php while($message = $messages->fetch_assoc()): ?>
            <div class="message <?= $message['sender_id'] == $_SESSION['user_id'] ? 'sent' : 'received' ?>">
              <div class="message-bubble">
                <?= htmlspecialchars($message['message']) ?>
                <div class="message-time">
                  <?= date('M j, g:i A', strtotime($message['created_at'])) ?>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
        
        <div class="chat-input">
          <form method="POST" class="message-form" id="messageForm">
            <input type="text" name="message" class="message-input" placeholder="Type your message..." required>
            <button type="submit" name="send_message" class="send-btn">
              <i class="fas fa-paper-plane"></i> Send
            </button>
          </form>
        </div>
      <?php else: ?>
        <div class="no-chat">
          <div>
            <i class="fas fa-comments"></i>
            <h3>Select a tenant to start chatting</h3>
            <p>Choose a tenant from the sidebar to view and send messages</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Auto-scroll to bottom of chat
function scrollToBottom() {
  const chatMessages = document.getElementById('chatMessages');
  if (chatMessages) {
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }
}

// Scroll to bottom when page loads
window.onload = scrollToBottom;

// Auto-refresh messages every 5 seconds
setInterval(function() {
  if (<?= $selected_tenant_id ? 'true' : 'false' ?>) {
    location.reload();
  }
}, 5000);
</script>
</body>
</html>
