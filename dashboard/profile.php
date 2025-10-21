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

// Create user_agreements table if it doesn't exist FIRST
$create_table_sql = "CREATE TABLE IF NOT EXISTS user_agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_agreement (user_id)
)";
$conn->query($create_table_sql);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['signed_agreement'])) {
    $upload_dir = "../uploads/signed_agreements/";
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['signed_agreement'];
    $allowed_types = ['application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $filename = "signed_agreement_" . $user_id . "_" . time() . ".pdf";
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $stmt = $conn->prepare("INSERT INTO user_agreements (user_id, file_name) VALUES (?, ?) 
                                      ON DUPLICATE KEY UPDATE file_name = ?, uploaded_at = NOW()");
                $stmt->bind_param("iss", $user_id, $filename, $filename);
                $stmt->execute();
                $stmt->close();
                
                $success = "Signed agreement uploaded successfully! Admin will review it shortly.";
            } else {
                $error = "Failed to upload file. Please try again.";
            }
        } else {
            $error = "Please upload a PDF file under 5MB.";
        }
    } else {
        $error = "File upload error. Please try again.";
    }
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Initialize agreement variable
$agreement = null;

// Check if user has uploaded an agreement - with error handling
try {
    $stmt = $conn->prepare("SELECT file_name, uploaded_at, status FROM user_agreements WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $agreement_result = $stmt->get_result();
    $agreement = $agreement_result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    // If there's still an error, table doesn't exist properly
    $agreement = null;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Profile | Mojo Tenant Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #2c3e50;
      --primary-light: #34495e;
      --secondary: #3498db;
      --accent: #e74c3c;
      --success: #27ae60;
      --warning: #f39c12;
      --light: #ecf0f1;
      --dark: #2c3e50;
      --text: #34495e;
      --border: #bdc3c7;
      --shadow: 0 8px 30px rgba(0,0,0,0.12);
      --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--gradient);
      min-height: 100vh;
      padding: 40px 20px;
      color: var(--text);
      line-height: 1.6;
    }

    .profile-container {
      max-width: 1000px;
      margin: 0 auto;
      background: white;
      border-radius: 20px;
      box-shadow: var(--shadow);
      overflow: hidden;
      animation: slideUp 0.6s ease;
    }

    @keyframes slideUp {
      from { 
        opacity: 0; 
        transform: translateY(30px); 
      }
      to { 
        opacity: 1; 
        transform: translateY(0); 
      }
    }

    .profile-header {
      background: var(--primary);
      color: white;
      padding: 40px;
      text-align: center;
      position: relative;
    }

    .profile-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: linear-gradient(45deg, var(--secondary), var(--success));
      margin: 0 auto 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
      color: white;
      font-weight: bold;
      border: 4px solid white;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .profile-header h1 {
      font-size: 2.2em;
      margin-bottom: 8px;
      font-weight: 600;
    }

    .profile-header p {
      opacity: 0.9;
      font-size: 1.1em;
    }

    .profile-content {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0;
    }

    @media (max-width: 768px) {
      .profile-content {
        grid-template-columns: 1fr;
      }
    }

    .profile-section {
      padding: 40px;
    }

    .profile-section:first-child {
      border-right: 1px solid var(--border);
    }

    @media (max-width: 768px) {
      .profile-section:first-child {
        border-right: none;
        border-bottom: 1px solid var(--border);
      }
    }

    .section-title {
      color: var(--primary);
      font-size: 1.4em;
      margin-bottom: 25px;
      padding-bottom: 12px;
      border-bottom: 2px solid var(--light);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title i {
      color: var(--secondary);
    }

    .info-grid {
      display: grid;
      gap: 20px;
      margin-bottom: 30px;
    }

    .info-item {
      display: flex;
      align-items: flex-start;
      gap: 15px;
    }

    .info-icon {
      width: 40px;
      height: 40px;
      background: var(--light);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--secondary);
      flex-shrink: 0;
    }

    .info-content h4 {
      color: var(--primary);
      margin-bottom: 4px;
      font-weight: 600;
    }

    .info-content p {
      color: var(--text);
      opacity: 0.8;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: var(--primary);
      font-weight: 500;
    }

    input, textarea, select {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid var(--border);
      border-radius: 12px;
      font-size: 15px;
      transition: all 0.3s ease;
      background: white;
    }

    input:focus, textarea:focus, select:focus {
      outline: none;
      border-color: var(--secondary);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
      transform: translateY(-2px);
    }

    textarea {
      resize: vertical;
      min-height: 100px;
    }

    .btn {
      padding: 14px 28px;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      text-align: center;
      justify-content: center;
    }

    .btn-primary {
      background: var(--secondary);
      color: white;
    }

    .btn-primary:hover {
      background: var(--primary-light);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
    }

    .btn-success {
      background: var(--success);
      color: white;
    }

    .btn-success:hover {
      background: #219653;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
    }

    .btn-outline {
      background: transparent;
      border: 2px solid var(--secondary);
      color: var(--secondary);
    }

    .btn-outline:hover {
      background: var(--secondary);
      color: white;
      transform: translateY(-2px);
    }

    .file-upload-area {
      border: 2px dashed var(--border);
      border-radius: 12px;
      padding: 30px;
      text-align: center;
      margin: 20px 0;
      transition: all 0.3s ease;
    }

    .file-upload-area:hover {
      border-color: var(--secondary);
      background: rgba(52, 152, 219, 0.05);
    }

    .file-upload-area i {
      font-size: 48px;
      color: var(--secondary);
      margin-bottom: 15px;
    }

    .status-badge {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .status-pending { background: #fff3cd; color: #856404; }
    .status-approved { background: #d4edda; color: #155724; }
    .status-rejected { background: #f8d7da; color: #721c24; }

    .alert {
      padding: 15px 20px;
      border-radius: 12px;
      margin-bottom: 25px;
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

    .action-buttons {
      display: grid;
      gap: 15px;
      margin-top: 30px;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: var(--secondary);
      text-decoration: none;
      font-weight: 500;
      margin-top: 30px;
      padding: 12px 20px;
      border: 2px solid var(--border);
      border-radius: 12px;
      transition: all 0.3s ease;
    }

    .back-link:hover {
      background: var(--secondary);
      color: white;
      border-color: var(--secondary);
      transform: translateY(-2px);
    }
  </style>
</head>

<body>
  <div class="profile-container">
    <!-- Header Section -->
    <div class="profile-header">
      <div class="profile-avatar">
        <?php
          $initials = '';
          if (isset($user['full_name'])) {
            $name_parts = explode(' ', $user['full_name']);
            foreach ($name_parts as $part) {
              $initials .= strtoupper(substr($part, 0, 1));
            }
          }
          echo substr($initials, 0, 2);
        ?>
      </div>
      <h1><?= htmlspecialchars($user['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></h1>
      <p><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <!-- Content Section -->
    <div class="profile-content">
      <!-- Personal Information -->
      <div class="profile-section">
        <div class="section-title">
          <i class="fas fa-user-circle"></i>
          Personal Information
        </div>

        <?php if (!empty($success)): ?>
          <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
          <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <div class="info-grid">
          <div class="info-item">
            <div class="info-icon">
              <i class="fas fa-phone"></i>
            </div>
            <div class="info-content">
              <h4>Phone Number</h4>
              <p><?= htmlspecialchars($user['phone'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>

          <div class="info-item">
            <div class="info-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="info-content">
              <h4>Emergency Contact</h4>
              <p><?= htmlspecialchars($user['emergency_contact'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>
        </div>

        <form class="edit-form" method="POST" action="update_profile.php">
          <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
          </div>

          <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
          </div>

          <div class="form-group">
            <label for="emergency_contact">Emergency Contact</label>
            <input type="text" id="emergency_contact" name="emergency_contact" placeholder="Name and phone number" value="<?= htmlspecialchars($user['emergency_contact'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Profile
          </button>
        </form>
      </div>

      <!-- Agreement Section -->
      <div class="profile-section">
        <div class="section-title">
          <i class="fas fa-file-contract"></i>
          Tenant Agreement
        </div>

        <!-- Agreement Status -->
        <?php if ($agreement): ?>
          <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <strong>Agreement Uploaded</strong>
            <br>
            <small>Status: <span class="status-badge status-<?= $agreement['status'] ?>"><?= $agreement['status'] ?></span></small>
            <br>
            <small>Uploaded on: <?= date('M j, Y g:i A', strtotime($agreement['uploaded_at'])) ?></small>
          </div>
        <?php else: ?>
          <div class="alert alert-error">
            <i class="fas fa-clock"></i>
            <strong>No Agreement Uploaded</strong>
            <br>
            <small>Please download, sign, and upload the agreement</small>
          </div>
        <?php endif; ?>

        <!-- Download Agreement -->
        <div class="file-upload-area">
          <i class="fas fa-file-download"></i>
          <h4>Download Agreement Template</h4>
          <p>Download the tenant agreement, print it, sign it, and upload the signed copy below.</p>
          <a href="generate_tenant_doc.php" class="btn btn-success" style="margin-top: 15px;">
            <i class="fas fa-download"></i> Download PDF Agreement
          </a>
        </div>

        <!-- Upload Signed Agreement -->
        <form method="POST" enctype="multipart/form-data">
          <div class="form-group">
            <label for="signed_agreement">Upload Signed Agreement</label>
            <input type="file" id="signed_agreement" name="signed_agreement" accept=".pdf" required>
            <small style="display: block; margin-top: 8px; color: #666;">
              Only PDF files are accepted (max 5MB)
            </small>
          </div>

          <button type="submit" class="btn btn-primary">
            <i class="fas fa-upload"></i> Upload Signed Agreement
          </button>
        </form>

        <!-- View Uploaded Agreement -->
        <?php if ($agreement && isset($agreement['file_name'])): ?>
          <div style="margin-top: 20px;">
            <a href="../uploads/signed_agreements/<?= htmlspecialchars($agreement['file_name']) ?>" 
               class="btn btn-outline" target="_blank">
              <i class="fas fa-eye"></i> View Uploaded Agreement
            </a>
          </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
          <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>