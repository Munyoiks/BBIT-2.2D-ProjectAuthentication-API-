<?php
session_start();
require_once "../auth/db_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$admin_id = 1; // Main admin
$success = '';
$error = '';

// === FETCH ONLY APPROVED ANNOUNCEMENTS ===
$announcements_result = mysqli_query($conn, "
    SELECT a.*, u.full_name AS author_name 
    FROM announcements a 
    LEFT JOIN users u ON a.posted_by = u.id 
    WHERE a.status = 'approved' 
    ORDER BY a.created_at DESC
");

// === HANDLE TENANT SUGGESTION (inserts as 'pending') ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['suggest_title'], $_POST['suggest_message'])) {
    $title = trim($_POST['suggest_title']);
    $message = trim($_POST['suggest_message']);

    if (empty($title) || empty($message)) {
        $error = "Both title and message are required.";
    } else {
        $title_esc = mysqli_real_escape_string($conn, $title);
        $msg_esc = mysqli_real_escape_string($conn, $message);

        $query = "INSERT INTO announcements 
                  (title, message, status, posted_by, suggested_by, created_at) 
                  VALUES ('$title_esc', '$msg_esc', 'pending', '$admin_id', '$user_id', NOW())";

        if (mysqli_query($conn, $query)) {
            $success = "Your announcement suggestion has been sent to the admin for approval.";
        } else {
            $error = "Failed to send suggestion. Please try again.";
        }
    }
}

// === GET CURRENT USER NAME ===
$user_stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$current_user = $user_result->fetch_assoc();
$user_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Announcements | Mojo Tenant Dashboard</title>
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

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--gradient);
      min-height: 100vh;
      padding: 40px 20px;
      color: var(--text);
      line-height: 1.6;
    }

    .container {
      max-width: 1000px;
      margin: 0 auto;
      background: white;
      border-radius: 20px;
      box-shadow: var(--shadow);
      overflow: hidden;
      animation: slideUp 0.6s ease;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .header {
      background: var(--primary);
      color: white;
      padding: 40px;
      text-align: center;
    }

    .header h1 {
      font-size: 2.2em;
      margin-bottom: 8px;
      font-weight: 600;
    }

    .header p { opacity: 0.9; font-size: 1.1em; }

    .content { padding: 40px; }

    .section-title {
      color: var(--primary);
      font-size: 1.5em;
      margin-bottom: 25px;
      padding-bottom: 12px;
      border-bottom: 2px solid var(--light);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .section-title i { color: var(--secondary); }

    .announcement-list {
      display: flex;
      flex-direction: column;
      gap: 20px;
      margin-bottom: 40px;
    }

    .announcement-item {
      background: #f8f9fa;
      border-left: 5px solid var(--secondary);
      padding: 20px;
      border-radius: 12px;
      transition: all 0.3s ease;
    }

    .announcement-item:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.1);
      background: white;
    }

    .announcement-item h3 {
      color: var(--primary);
      margin-bottom: 8px;
      font-size: 1.3em;
    }

    .announcement-item p {
      color: var(--text);
      margin-bottom: 12px;
      line-height: 1.7;
    }

    .announcement-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.9rem;
      color: #7f8c8d;
    }

    .announcement-meta .author {
      font-weight: 500;
      color: var(--secondary);
    }

    .suggestion-form {
      background: #f0f7ff;
      border: 2px dashed var(--secondary);
      border-radius: 16px;
      padding: 30px;
      margin-top: 30px;
    }

    .suggestion-form h3 {
      color: var(--primary);
      margin-bottom: 15px;
      font-size: 1.4em;
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

    input, textarea {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid var(--border);
      border-radius: 12px;
      font-size: 15px;
      transition: all 0.3s ease;
      background: white;
    }

    input:focus, textarea:focus {
      outline: none;
      border-color: var(--secondary);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }

    textarea { resize: vertical; min-height: 100px; }

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

    .alert {
      padding: 15px 20px;
      border-radius: 12px;
      margin-bottom: 25px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
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

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: var(--secondary);
      text-decoration: none;
      font-weight: 500;
      padding: 12px 24px;
      border: 2px solid var(--border);
      border-radius: 12px;
      transition: all 0.3s ease;
      margin-top: 30px;
    }

    .back-link:hover {
      background: var(--secondary);
      color: white;
      border-color: var(--secondary);
      transform: translateY(-2px);
    }

    .empty-state {
      text-align: center;
      padding: 40px;
      color: #95a5a6;
      font-size: 1.1em;
    }

    .empty-state i {
      font-size: 3em;
      margin-bottom: 15px;
      color: #bdc3c7;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <h1>Announcements</h1>
      <p>Stay updated with community notices</p>
    </div>

    <!-- Content -->
    <div class="content">
      <!-- Alerts -->
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

      <!-- Approved Announcements -->
      <div class="section-title">
        <i class="fas fa-newspaper"></i> Latest Announcements
      </div>

      <div class="announcement-list">
        <?php if (mysqli_num_rows($announcements_result) > 0): ?>
          <?php while ($a = mysqli_fetch_assoc($announcements_result)): ?>
            <div class="announcement-item">
              <h3><?= htmlspecialchars($a['title']) ?></h3>
              <p><?= nl2br(htmlspecialchars($a['message'])) ?></p>
              <div class="announcement-meta">
                <span class="author">
                  <i class="fas fa-user"></i> <?= htmlspecialchars($a['author_name'] ?? 'Admin') ?>
                </span>
                <span>
                  <i class="fas fa-clock"></i> <?= date('M j, Y \a\t g:i A', strtotime($a['created_at'])) ?>
                </span>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <p>No announcements posted yet. Check back soon!</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Suggest Announcement -->
      <div class="suggestion-form">
        <h3>Suggest an Announcement</h3>
        <p>Your suggestion will be reviewed by the admin before going live.</p>
        <form method="POST">
          <div class="form-group">
            <label for="suggest_title">Title</label>
            <input type="text" id="suggest_title" name="suggest_title" placeholder="e.g., Community Cleanup Day" required>
          </div>
          <div class="form-group">
            <label for="suggest_message">Message</label>
            <textarea id="suggest_message" name="suggest_message" placeholder="Provide full details..." required></textarea>
          </div>
          <button type="submit" class="btn btn-primary">
            Submit for Approval
          </button>
        </form>
      </div>

      <!-- Back to Dashboard -->
      <a href="dashboard.php" class="back-link">
        Back to Dashboard
      </a>
    </div>
  </div>
</body>
</html>
