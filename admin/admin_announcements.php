<?php
session_start();
require_once "../auth/db_config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_email = $_SESSION['email'];
$admin_name = $_SESSION['full_name'] ?? 'Administrator';

// Handle new announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);
    $priority = $conn->real_escape_string($_POST['priority']);
    
    $insert_query = "INSERT INTO announcements (admin_id, title, content, priority, created_at) 
                     VALUES ('{$_SESSION['user_id']}', '$title', '$content', '$priority', NOW())";
    
    if ($conn->query($insert_query)) {
        $_SESSION['success_message'] = "Announcement posted successfully!";
    } else {
        $_SESSION['error_message'] = "Error creating announcement: " . $conn->error;
    }
    header("Location: announcements.php");
    exit();
}

// Handle suggestion status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_suggestion_status'])) {
    $suggestion_id = $conn->real_escape_string($_POST['suggestion_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $admin_response = $conn->real_escape_string($_POST['admin_response'] ?? '');
    
    $update_query = "UPDATE tenant_suggestions SET status = ?, admin_response = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssi", $status, $admin_response, $suggestion_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Suggestion status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating suggestion: " . $conn->error;
    }
    header("Location: announcements.php");
    exit();
}

// Get announcements
$announcements = $conn->query("
    SELECT a.*, u.full_name AS admin_name
    FROM announcements a
    JOIN users u ON a.posted_by = u.id
    ORDER BY a.created_at DESC
");


// Get tenant suggestions
$suggestions = $conn->query("
    SELECT ts.*, u.full_name, u.email, apt.apartment_number 
    FROM tenant_suggestions ts 
    JOIN users u ON ts.tenant_id = u.id 
    LEFT JOIN apartments apt ON u.id = apt.tenant_id 
    ORDER BY ts.created_at DESC
");

// Count suggestions by status
$pending_suggestions = $conn->query("SELECT COUNT(*) AS c FROM tenant_suggestions WHERE status = 'pending'")->fetch_assoc()['c'];
$reviewed_suggestions = $conn->query("SELECT COUNT(*) AS c FROM tenant_suggestions WHERE status = 'reviewed'")->fetch_assoc()['c'];
$implemented_suggestions = $conn->query("SELECT COUNT(*) AS c FROM tenant_suggestions WHERE status = 'implemented'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements & Suggestions | Mojo Tenant Management System</title>
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

/* Tabs */
.tabs {
  display: flex;
  background: white;
  border-radius: 15px 15px 0 0;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  margin-bottom: 0;
}

.tab {
  padding: 20px 30px;
  cursor: pointer;
  font-weight: 600;
  color: #7f8c8d;
  border-bottom: 3px solid transparent;
  transition: all 0.3s ease;
}

.tab.active {
  color: var(--secondary);
  border-bottom-color: var(--secondary);
}

.tab-content {
  display: none;
}

.tab-content.active {
  display: block;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: white;
  padding: 20px;
  border-radius: 15px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  text-align: center;
}

.stat-number {
  font-size: 32px;
  font-weight: 700;
  margin-bottom: 5px;
}

.stat-label {
  color: #7f8c8d;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

/* Content Sections */
.content-section {
  background: white;
  border-radius: 0 0 15px 15px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  overflow: hidden;
}

.section-header {
  padding: 25px;
  border-bottom: 1px solid #ecf0f1;
  display: flex;
  justify-content: between;
  align-items: center;
}

.section-header h3 {
  font-size: 20px;
  color: var(--primary);
  font-weight: 600;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  display: inline-block;
  text-align: center;
  transition: all 0.3s ease;
}

.btn-primary { 
  background: var(--secondary); 
  color: white; 
}

.btn-primary:hover { 
  background: #2980b9; 
  transform: translateY(-2px);
}

/* Announcements Grid */
.announcements-grid {
  padding: 25px;
  display: grid;
  gap: 20px;
}

.announcement-card {
  border: 1px solid #ecf0f1;
  border-radius: 10px;
  padding: 20px;
  transition: all 0.3s ease;
}

.announcement-card:hover {
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  transform: translateY(-2px);
}

.announcement-header {
  display: flex;
  justify-content: between;
  align-items: start;
  margin-bottom: 15px;
}

.announcement-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--primary);
  margin-bottom: 5px;
}

.announcement-meta {
  font-size: 12px;
  color: #7f8c8d;
}

.priority-badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.priority-high { background: #f8d7da; color: #721c24; }
.priority-medium { background: #fff3cd; color: #856404; }
.priority-low { background: #d1ecf1; color: #0c5460; }

.announcement-content {
  color: #555;
  line-height: 1.6;
}

/* Suggestions Table */
.table-container {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 15px 20px;
  text-align: left;
  border-bottom: 1px solid #ecf0f1;
}

th {
  background: #f8f9fa;
  font-weight: 600;
  color: var(--primary);
}

.status-badge {
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-reviewed { background: #cce7ff; color: #004085; }
.status-implemented { background: #d4edda; color: #155724; }

.btn-sm {
  padding: 6px 12px;
  font-size: 11px;
}

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.5);
  align-items: center;
  justify-content: center;
}

.modal-content {
  background: white;
  padding: 0;
  border-radius: 18px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  display: flex;
  flex-direction: column;
}

.modal-header {
  padding: 20px 25px;
  border-bottom: 1px solid #ecf0f1;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  font-size: 18px;
  color: var(--primary);
}

.close {
  font-size: 24px;
  cursor: pointer;
  color: #7f8c8d;
}

.close:hover {
  color: var(--danger);
}

.modal-body {
  padding: 25px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--primary);
}

.form-control {
  width: 100%;
  padding: 12px 15px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.3s ease;
}

.form-control:focus {
  outline: none;
  border-color: var(--secondary);
}

textarea.form-control {
  resize: vertical;
  min-height: 120px;
}

.modal-footer {
  padding: 20px 25px;
  border-top: 1px solid #ecf0f1;
  text-align: right;
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
  
  .tabs {
    flex-direction: column;
  }
  
  .tab {
    border-bottom: 1px solid #ecf0f1;
    border-left: 3px solid transparent;
  }
  
  .tab.active {
    border-left-color: var(--secondary);
    border-bottom-color: #ecf0f1;
  }
  
  .table-responsive {
    font-size: 14px;
  }
  
  th, td {
    padding: 10px 15px;
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
    <a href="messages.php">
      <i class="fas fa-comments"></i>Messages
    </a>
    <a href="announcements.php" class="active">
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
      <h1>Announcements & Suggestions 📢</h1>
      <p>Communicate with tenants and review their suggestions</p>
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

  <!-- Tabs -->
  <div class="tabs">
    <div class="tab active" onclick="switchTab('announcements')">Announcements</div>
    <div class="tab" onclick="switchTab('suggestions')">Tenant Suggestions</div>
  </div>

  <!-- Announcements Tab -->
  <div id="announcements" class="tab-content active">
    <div class="content-section">
      <div class="section-header">
        <h3>Recent Announcements</h3>
        <button class="btn btn-primary" onclick="openAnnouncementModal()">
          <i class="fas fa-plus"></i> New Announcement
        </button>
      </div>
      <div class="announcements-grid">
        <?php while($announcement = $announcements->fetch_assoc()): ?>
        <div class="announcement-card">
          <div class="announcement-header">
            <div>
              <div class="announcement-title"><?= htmlspecialchars($announcement['title']) ?></div>
              <div class="announcement-meta">
                Posted by <?= htmlspecialchars($announcement['admin_name']) ?> • 
                <?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?>
              </div>
            </div>
            <span class="priority-badge priority-<?= $announcement['priority'] ?>">
              <?= ucfirst($announcement['priority'] ?? 'low') ?> Priority

            </span>
          </div>
          <div class="announcement-content">
            <?= nl2br(htmlspecialchars($announcement['message'])) ?>
          </div>
        </div>
        <?php endwhile; ?>
        
        <?php if ($announcements->num_rows === 0): ?>
          <div style="text-align: center; padding: 40px; color: #7f8c8d;">
            <i class="fas fa-bullhorn" style="font-size: 48px; margin-bottom: 15px;"></i>
            <h3>No announcements yet</h3>
            <p>Create your first announcement to communicate with tenants</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Suggestions Tab -->
  <div id="suggestions" class="tab-content">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-number" style="color: #f39c12;"><?= $pending_suggestions ?></div>
        <div class="stat-label">Pending Review</div>
      </div>
      <div class="stat-card">
        <div class="stat-number" style="color: #3498db;"><?= $reviewed_suggestions ?></div>
        <div class="stat-label">Reviewed</div>
      </div>
      <div class="stat-card">
        <div class="stat-number" style="color: #27ae60;"><?= $implemented_suggestions ?></div>
        <div class="stat-label">Implemented</div>
      </div>
      <div class="stat-card">
        <div class="stat-number" style="color: #2c3e50;">
          <?= $pending_suggestions + $reviewed_suggestions + $implemented_suggestions ?>
        </div>
        <div class="stat-label">Total Suggestions</div>
      </div>
    </div>

    <div class="content-section">
      <div class="section-header">
        <h3>Tenant Suggestions</h3>
      </div>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Tenant</th>
              <th>Apartment</th>
              <th>Suggestion</th>
              <th>Category</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($suggestion = $suggestions->fetch_assoc()): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($suggestion['full_name']) ?></strong><br>
                <small style="color: #7f8c8d;"><?= htmlspecialchars($suggestion['email']) ?></small>
              </td>
              <td><?= htmlspecialchars($suggestion['apartment_number'] ?? 'N/A') ?></td>
              <td style="max-width: 300px;">
                <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  <?= htmlspecialchars($suggestion['suggestion']) ?>
                </div>
                <?php if ($suggestion['admin_response']): ?>
                  <div style="font-size: 12px; color: #27ae60; margin-top: 5px;">
                    <strong>Response:</strong> <?= htmlspecialchars($suggestion['admin_response']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($suggestion['category']) ?></td>
              <td>
                <span class="status-badge status-<?= $suggestion['status'] ?>">
                  <?= ucfirst($suggestion['status']) ?>
                </span>
              </td>
              <td><?= date('M j, Y', strtotime($suggestion['created_at'])) ?></td>
              <td>
                <button class="btn btn-primary btn-sm" onclick="openSuggestionModal(<?= $suggestion['id'] ?>)">
                  <i class="fas fa-edit"></i> Review
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- New Announcement Modal -->
<div id="announcementModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Create New Announcement</h3>
      <span class="close" onclick="closeAnnouncementModal()">&times;</span>
    </div>
    <form method="POST" action="announcements.php">
      <div class="modal-body">
        <div class="form-group">
          <label for="title">Title</label>
          <input type="text" class="form-control" name="title" id="title" required placeholder="Enter announcement title">
        </div>
        <div class="form-group">
          <label for="priority">Priority</label>
          <select class="form-control" name="priority" id="priority" required>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>
        <div class="form-group">
          <label for="content">Content</label>
          <textarea class="form-control" name="content" id="content" required placeholder="Enter announcement content..."></textarea>
        </div>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: center; gap: 1rem; padding-bottom: 30px;">
        <button type="button" class="btn" style="background: #95a5a6; color: white; min-width:120px;" onclick="closeAnnouncementModal()">Cancel</button>
        <button type="submit" name="create_announcement" class="btn btn-primary" style="background: #6D28D9; color: #fff; min-width:140px; font-size: 1.1rem; font-weight: 600; border-radius: 8px;">Post Announcement</button>
      </div>
    </form>
  </div>
</div>

<!-- Review Suggestion Modal -->
<div id="suggestionModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Review Suggestion</h3>
      <span class="close" onclick="closeSuggestionModal()">&times;</span>
    </div>
    <form method="POST" action="announcements.php">
      <div class="modal-body">
        <input type="hidden" name="suggestion_id" id="suggestion_id">
        <div class="form-group">
          <label for="status">Status</label>
          <select class="form-control" name="status" id="status" required>
            <option value="pending">Pending</option>
            <option value="reviewed">Reviewed</option>
            <option value="implemented">Implemented</option>
          </select>
        </div>
        <div class="form-group">
          <label for="admin_response">Admin Response</label>
          <textarea class="form-control" name="admin_response" id="admin_response" placeholder="Add your response to the tenant..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background: #95a5a6; color: white;" onclick="closeSuggestionModal()">Cancel</button>
        <button type="submit" name="update_suggestion_status" class="btn btn-primary">Update Suggestion</button>
      </div>
    </form>
  </div>
</div>

<script>
// Tab switching
function switchTab(tabName) {
  // Hide all tab contents
  document.querySelectorAll('.tab-content').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Remove active class from all tabs
  document.querySelectorAll('.tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Show selected tab content
  document.getElementById(tabName).classList.add('active');
  
  // Activate selected tab
  event.target.classList.add('active');
}

// Announcement Modal
function openAnnouncementModal() {
  document.getElementById('announcementModal').style.display = 'flex';
}

function closeAnnouncementModal() {
  document.getElementById('announcementModal').style.display = 'none';
}

// Suggestion Modal
function openSuggestionModal(suggestionId) {
  document.getElementById('suggestion_id').value = suggestionId;
  document.getElementById('suggestionModal').style.display = 'flex';
}

function closeSuggestionModal() {
  document.getElementById('suggestionModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
  const announcementModal = document.getElementById('announcementModal');
  const suggestionModal = document.getElementById('suggestionModal');
  
  if (event.target == announcementModal) {
    closeAnnouncementModal();
  }
  if (event.target == suggestionModal) {
    closeSuggestionModal();
  }
}
</script>
</body>
</html>
