<?php
session_start();
require_once "../auth/db_config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_email = $_SESSION['email'];
$admin_name = $_SESSION['full_name'] ?? 'Administrator';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $request_id = $conn->real_escape_string($_POST['request_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $admin_notes = $conn->real_escape_string($_POST['admin_notes'] ?? '');
    
    $update_query = "UPDATE maintenance_requests SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssi", $status, $admin_notes, $request_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Maintenance request status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating maintenance request: " . $conn->error;
    }
    header("Location: maintenance.php");
    exit();
}

$maintenance_requests = $conn->query("
    SELECT 
        mr.*, 
        u.full_name, 
        u.email, 
        u.phone, 
        a.apartment_number 
    FROM maintenance_requests mr 
    JOIN users u ON mr.tenant_id = u.id 
    LEFT JOIN apartments a ON mr.unit_number = a.apartment_number
    ORDER BY mr.created_at DESC
");


// Count by status
$pending_count = $conn->query("SELECT COUNT(*) AS c FROM maintenance_requests WHERE status = 'pending'")->fetch_assoc()['c'];
$in_progress_count = $conn->query("SELECT COUNT(*) AS c FROM maintenance_requests WHERE status = 'in_progress'")->fetch_assoc()['c'];
$completed_count = $conn->query("SELECT COUNT(*) AS c FROM maintenance_requests WHERE status = 'completed'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance Requests | Monrine Tenant Management System</title>
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
  height: 100vh;        /* full viewport height */
  background: var(--sidebar);
  color: white;
  padding: 0;
  display: flex;
  flex-direction: column;
  box-shadow: 2px 0 10px rgba(0,0,0,0.1);
  overflow-y: auto;      /* allows scrolling */
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

/* Maintenance Requests Table */
.table-container {
  background: white;
  border-radius: 15px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  overflow: hidden;
}

.table-header {
  padding: 25px;
  border-bottom: 1px solid #ecf0f1;
  display: flex;
  justify-content: between;
  align-items: center;
}

.table-header h3 {
  font-size: 20px;
  color: var(--primary);
  font-weight: 600;
}

.table-responsive {
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
.status-in_progress { background: #cce7ff; color: #004085; }
.status-completed { background: #d4edda; color: #155724; }

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  display: inline-block;
  text-align: center;
  transition: all 0.3s ease;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 11px;
}

.btn-primary { background: var(--secondary); color: white; }
.btn-primary:hover { background: #2980b9; }

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
}

.modal-content {
  background: white;
  margin: 5% auto;
  padding: 0;
  border-radius: 15px;
  width: 90%;
  max-width: 600px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-header {
  padding: 20px 25px;
  border-bottom: 1px solid #ecf0f1;
  display: flex;
  justify-content: between;
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
  min-height: 100px;
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
    <h2><i class="fas fa-building"></i> Monrine Management</h2>
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
    <a href="admin_maintenance.php" class="active">
      <i class="fas fa-tools"></i>Maintenance
    </a>
    <a href="admin_messages.php">
      <i class="fas fa-comments"></i>Messages
    </a>
    <a href="admin_announcments.php">
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
      <h1>Maintenance Requests 🛠</h1>
      <p>Manage and track maintenance requests from tenants</p>
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

  <!-- Stats Grid -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-number" style="color: #f39c12;"><?= $pending_count ?></div>
      <div class="stat-label">Pending Requests</div>
    </div>
    <div class="stat-card">
      <div class="stat-number" style="color: #3498db;"><?= $in_progress_count ?></div>
      <div class="stat-label">In Progress</div>
    </div>
    <div class="stat-card">
      <div class="stat-number" style="color: #27ae60;"><?= $completed_count ?></div>
      <div class="stat-label">Completed</div>
    </div>
    <div class="stat-card">
      <div class="stat-number" style="color: #2c3e50;"><?= $pending_count + $in_progress_count + $completed_count ?></div>
      <div class="stat-label">Total Requests</div>
    </div>
  </div>

  <!-- Maintenance Requests Table -->
  <div class="table-container">
    <div class="table-header">
      <h3>All Maintenance Requests</h3>
    </div>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Tenant</th>
            <th>Apartment</th>
            <th>Issue Type</th>
            <th>Description</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while($request = $maintenance_requests->fetch_assoc()): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($request['full_name']) ?></strong><br>
              <small style="color: #7f8c8d;"><?= htmlspecialchars($request['email']) ?></small>
            </td>
            <td><?= htmlspecialchars($request['apartment_number'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($request['issue_type']) ?></td>
            <td style="max-width: 200px;">
              <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= htmlspecialchars($request['description']) ?>
              </div>
            </td>
            <td>
              <?php
              $priority_color = [
                'low' => '#27ae60',
                'medium' => '#f39c12', 
                'high' => '#e74c3c'
              ];
              ?>
              <span style="color: <?= $priority_color[$request['priority']] ?? '#7f8c8d' ?>; font-weight: 600;">
                <?= ucfirst($request['priority']) ?>
              </span>
            </td>
            <td>
              <span class="status-badge status-<?= $request['status'] ?>">
                <?= str_replace('_', ' ', ucfirst($request['status'])) ?>
              </span>
            </td>
            <td><?= date('M j, Y', strtotime($request['created_at'])) ?></td>
            <td>
              <button class="btn btn-primary btn-sm" onclick="openModal(<?= $request['id'] ?>)">
                <i class="fas fa-edit"></i> Update
              </button>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Update Status Modal -->
<div id="updateModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Update Maintenance Request</h3>
      <span class="close">&times;</span>
    </div>
    <form method="POST" action="maintenance.php">
      <div class="modal-body">
        <input type="hidden" name="request_id" id="request_id">
        <div class="form-group">
          <label for="status">Status</label>
          <select class="form-control" name="status" id="status" required>
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
          </select>
        </div>
        <div class="form-group">
          <label for="admin_notes">Admin Notes</label>
          <textarea class="form-control" name="admin_notes" id="admin_notes" placeholder="Add any notes or updates..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" style="background: #95a5a6; color: white;" onclick="closeModal()">Cancel</button>
        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
      </div>
    </form>
  </div>
</div>

<script>
// Modal functionality
const modal = document.getElementById('updateModal');
const closeBtn = document.querySelector('.close');

function openModal(requestId) {
  document.getElementById('request_id').value = requestId;
  modal.style.display = 'block';
}

function closeModal() {
  modal.style.display = 'none';
}

closeBtn.onclick = closeModal;

window.onclick = function(event) {
  if (event.target == modal) {
    closeModal();
  }
}
</script>
</body>
</html>
