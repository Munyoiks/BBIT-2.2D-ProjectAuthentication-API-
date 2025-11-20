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

// Check for profile update messages from update_profile.php
if (isset($_SESSION['profile_update_success'])) {
    $success = $_SESSION['profile_update_success'];
    unset($_SESSION['profile_update_success']);
} elseif (isset($_SESSION['profile_update_error'])) {
    $error = $_SESSION['profile_update_error'];
    unset($_SESSION['profile_update_error']);
}

// Create user_agreements table if it doesn't exist with CORRECT structure
$create_table_sql = "CREATE TABLE IF NOT EXISTS user_agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    agreement_file VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    agreement_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
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
            // Create a consistent filename format
            $filename = "signed_agreement_" . $user_id . "_" . time() . ".pdf";
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Check if user already has an agreement
                $check_stmt = $conn->prepare("SELECT id FROM user_agreements WHERE user_id = ?");
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Update existing agreement - get old filename to delete it
                    $old_file_stmt = $conn->prepare("SELECT agreement_file FROM user_agreements WHERE user_id = ?");
                    $old_file_stmt->bind_param("i", $user_id);
                    $old_file_stmt->execute();
                    $old_file_result = $old_file_stmt->get_result();
                    $old_file_data = $old_file_result->fetch_assoc();
                    $old_file_stmt->close();
                    
                    // Delete old file
                    if ($old_file_data && file_exists($upload_dir . $old_file_data['agreement_file'])) {
                        unlink($upload_dir . $old_file_data['agreement_file']);
                    }
                    
                    // Update database
                    $stmt = $conn->prepare("UPDATE user_agreements 
                                          SET agreement_file = ?, agreement_status = 'pending', uploaded_at = NOW() 
                                          WHERE user_id = ?");
                    $stmt->bind_param("si", $filename, $user_id);
                } else {
                    // Insert new agreement
                    $stmt = $conn->prepare("INSERT INTO user_agreements (user_id, agreement_file, agreement_status) 
                                          VALUES (?, ?, 'pending')");
                    $stmt->bind_param("is", $user_id, $filename);
                }
                
                if ($stmt->execute()) {
                    $success = "Signed agreement uploaded successfully! Admin will review it shortly.";
                    
                    // DEBUG: Log the upload for troubleshooting
                    error_log("AGREEMENT UPLOAD SUCCESS: User $user_id uploaded $filename");
                } else {
                    $error = "Database error: " . $stmt->error;
                    // Delete the uploaded file if database operation failed
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                    error_log("AGREEMENT UPLOAD ERROR: " . $stmt->error);
                }
                
                $stmt->close();
                $check_stmt->close();
            } else {
                $error = "Failed to upload file. Please try again.";
                error_log("AGREEMENT UPLOAD ERROR: Failed to move uploaded file");
            }
        } else {
            $error = "Please upload a PDF file under 5MB.";
        }
    } else {
        $error = "File upload error. Please try again. Error code: " . $file['error'];
    }
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Check if user has uploaded an agreement
$agreement = null;
try {
    $stmt = $conn->prepare("SELECT agreement_file, uploaded_at, agreement_status, admin_notes FROM user_agreements WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $agreement_result = $stmt->get_result();
    $agreement = $agreement_result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    // If there's an error, table might not exist properly
    $agreement = null;
    error_log("AGREEMENT FETCH ERROR: " . $e->getMessage());
}

// DEBUG: Check what agreements exist in the system
$debug_stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_agreements");
$debug_stmt->execute();
$debug_result = $debug_stmt->get_result();
$total_agreements = $debug_result->fetch_assoc()['total'];
$debug_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Profile | Monrine Tenant Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    /* Your existing CSS styles remain the same */
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

    .agreement-details {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 12px;
      margin: 20px 0;
      border-left: 4px solid var(--secondary);
    }

    .agreement-details p {
      margin: 8px 0;
    }

    .file-info {
      background: #e9f7fe;
      padding: 15px;
      border-radius: 8px;
      margin: 10px 0;
      font-family: monospace;
      font-size: 14px;
    }

    .debug-info {
      background: #f8f9fa;
      padding: 10px;
      border-radius: 8px;
      margin: 10px 0;
      font-size: 12px;
      color: #666;
      border-left: 3px solid #ffc107;
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

        <!-- Debug Information -->
        <div class="debug-info">
          <strong>System Status:</strong> 
          Total agreements in system: <?= $total_agreements ?> | 
          Your agreement: <?= $agreement ? 'Uploaded' : 'Not uploaded' ?>
        </div>

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
      <!-- Enhanced Agreement Section -->
      <div class="profile-section agreement-section">
        <div class="section-title">
          <i class="fas fa-file-contract"></i> Tenant Agreement
        </div>
        <div class="agreement-content">
          <div class="row">
            <div class="col-md-6">
              <div class="agreement-info-card">
                <h5><i class="fas fa-info-circle"></i> How it Works</h5>
                <ol>
                  <li>Download the official tenant agreement template.</li>
                  <li>Print, sign, and scan the completed agreement.</li>
                  <li>Upload the signed PDF using the form below.</li>
                  <li>Wait for admin review and approval.</li>
                </ol>
                <a href="generate_tenant_doc.php" class="btn btn-success mt-2">
                  <i class="fas fa-download"></i> Download PDF Agreement
                </a>
              </div>
              <div class="agreement-status mt-4">
                <?php if ($agreement): ?>
                  <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <strong>Agreement Uploaded</strong><br>
                    <span>File: <?= htmlspecialchars($agreement['agreement_file']) ?></span><br>
                    <span>Status: <span class="status-badge status-<?= $agreement['agreement_status'] ?>"><?= ucfirst($agreement['agreement_status']) ?></span></span><br>
                    <span>Uploaded: <?= date('M j, Y g:i A', strtotime($agreement['uploaded_at'])) ?></span>
                    <?php if (!empty($agreement['admin_notes'])): ?>
                      <br><span><strong>Admin Notes:</strong> <?= nl2br(htmlspecialchars($agreement['admin_notes'])) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="file-links mt-2">
                    <?php
                      $file_path = "../uploads/signed_agreements/" . $agreement['agreement_file'];
                      $full_url = "http://" . $_SERVER['HTTP_HOST'] . "/BBIT-2.2D-ProjectAuthentication-API-/uploads/signed_agreements/" . $agreement['agreement_file'];
                    ?>
                    <a href="<?= $file_path ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                      <i class="fas fa-eye"></i> View Uploaded Agreement
                    </a>
                    <small class="d-block mt-1">Direct link: <a href="<?= $full_url ?>" target="_blank"><?= $full_url ?></a></small>
                  </div>
                <?php else: ?>
                  <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle"></i> <strong>No Agreement Uploaded</strong><br>
                    Please download, sign, and upload the agreement below.
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-md-6">
              <div class="agreement-upload-card">
                <h5><i class="fas fa-upload"></i> Upload Signed Agreement</h5>
                <form method="POST" enctype="multipart/form-data">
                  <div class="form-group">
                    <label for="signed_agreement">Select PDF File</label>
                    <input type="file" id="signed_agreement" name="signed_agreement" accept=".pdf" required>
                    <small class="form-text text-muted">PDF only, max 5MB. Filename is auto-generated.</small>
                  </div>
                  <button type="submit" class="btn btn-primary mt-2">
                    <i class="fas fa-upload"></i> Upload
                  </button>
                </form>
              </div>
            </div>
          </div>
          <div class="action-buttons mt-4">
            <a href="dashboard.php" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Add some client-side validation
    document.addEventListener('DOMContentLoaded', function() {
      const fileInput = document.getElementById('signed_agreement');
      
      fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
          // Check file type
          if (file.type !== 'application/pdf') {
            alert('Please select a PDF file.');
            this.value = '';
            return;
          }
          
          // Check file size (5MB)
          if (file.size > 5 * 1024 * 1024) {
            alert('File size must be less than 5MB.');
            this.value = '';
            return;
          }
        }
      });
    });
  </script>
</body>
</html>
