<?php
session_start();
include '../auth/db_config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

// Fetch tenant info
$user_query = mysqli_query($conn, "SELECT full_name AS name, email, phone, unit_number FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($user_query);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $unit_number = mysqli_real_escape_string($conn, $_POST['unit_number']);
    $issue = mysqli_real_escape_string($conn, $_POST['issue']);
    $urgency = mysqli_real_escape_string($conn, $_POST['urgency']);
    $preferred_date = mysqli_real_escape_string($conn, $_POST['preferred_date']);

    // Handle photo upload
    $photo_path = NULL;
    if (!empty($_FILES['issue_photo']['name'])) {
        $target_dir = "../uploads/maintenance/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $photo_name = time() . "_" . basename($_FILES["issue_photo"]["name"]);
        $target_file = $target_dir . $photo_name;
        if (move_uploaded_file($_FILES["issue_photo"]["tmp_name"], $target_file)) {
            $photo_path = "uploads/maintenance/" . $photo_name;
        }
    }

    $sql = "INSERT INTO maintenance_requests (tenant_id, unit_number, issue, urgency, preferred_date, photo, status, created_at)
            VALUES ('$user_id', '$unit_number', '$issue', '$urgency', '$preferred_date', '$photo_path', 'Pending', NOW())";

    if (mysqli_query($conn, $sql)) {
        $message = "Maintenance request submitted successfully! Our team will reach out soon.";
        $message_type = "success";
    } else {
        $message = " Error submitting request: " . mysqli_error($conn);
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance Request | Mojo Systems</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  :root {
    --blue: #3498db;
    --purple1: #667eea;
    --purple2: #764ba2;
    --gradient: linear-gradient(135deg, var(--purple1), var(--purple2));
    --white: #fff;
    --gray: #6c757d;
    --success: #28a745;
    --danger: #dc3545;
    --radius: 12px;
    --shadow: 0 8px 25px rgba(0,0,0,0.1);
  }

  body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: var(--gradient);
    margin: 0;
    padding: 20px;
    color: #333;
  }

  .container {
    max-width: 850px;
    margin: 0 auto;
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
  }

  .header {
    background: var(--gradient);
    color: var(--white);
    text-align: center;
    padding: 40px 25px 30px;
    position: relative;
  }

  .header h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
  }

  .header p {
    opacity: 0.9;
    font-size: 1rem;
    margin-top: 8px;
  }

  .logo i {
    font-size: 3rem;
    color: var(--blue);
    margin-bottom: 15px;
  }

  .back-btn {
    position: absolute;
    top: 20px;
    left: 25px;
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 8px 16px;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    transition: 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .back-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
  }

  .content {
    padding: 30px;
  }

  .section-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--purple1);
    display: flex;
    align-items: center;
    margin-bottom: 20px;
  }

  .section-title i {
    background: var(--blue);
    color: white;
    padding: 10px;
    border-radius: 50%;
    margin-right: 10px;
  }

  .form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
  }

  .form-group {
    flex: 1 0 45%;
    display: flex;
    flex-direction: column;
  }

  label {
    font-weight: 600;
    margin-bottom: 8px;
  }

  input, select, textarea {
    padding: 12px 14px;
    border: 1px solid #ddd;
    border-radius: var(--radius);
    background: #f9f9f9;
    font-size: 1rem;
    transition: 0.3s;
  }

  input:focus, select:focus, textarea:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    outline: none;
    background: var(--white);
  }

  textarea {
    resize: vertical;
    min-height: 120px;
  }

  .file-input {
    padding: 12px;
    border: 1px dashed #ccc;
    border-radius: var(--radius);
    text-align: center;
    color: var(--gray);
    cursor: pointer;
    transition: 0.3s;
  }

  .file-input:hover {
    border-color: var(--blue);
    color: var(--blue);
    background: rgba(52,152,219,0.05);
  }

  .message {
    text-align: center;
    padding: 15px;
    border-radius: var(--radius);
    margin-bottom: 20px;
    font-weight: 600;
  }

  .message.success {
    background: rgba(40,167,69,0.1);
    color: var(--success);
  }

  .message.error {
    background: rgba(220,53,69,0.1);
    color: var(--danger);
  }

  button {
    display: block;
    width: 100%;
    padding: 14px 20px;
    background: var(--blue);
    color: white;
    border: none;
    border-radius: var(--radius);
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
  }

  button:hover {
    background: #2176bd;
    transform: translateY(-2px);
  }

  .footer {
    background: #f1f1f1;
    text-align: center;
    padding: 20px;
    font-size: 0.9rem;
    color: var(--gray);
  }

  .footer a {
    color: var(--blue);
    text-decoration: none;
    font-weight: 600;
  }

  .footer a:hover {
    text-decoration: underline;
  }

  @media (max-width: 768px) {
    .form-group {
      flex: 1 0 100%;
    }
    .header h1 { font-size: 1.7rem; }
  }
</style>
</head>
<body>
  <div class="container">
    <div class="header">
      <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
      <div class="logo"><i class="fas fa-tools"></i></div>
      <h1>Maintenance Request</h1>
      <p>Submit and track your service requests with Mojo Systems</p>
    </div>

    <div class="content">
      <?php if ($message): ?>
        <div class="message <?= $message_type; ?>"><?= $message; ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <div class="section-title"><i class="fas fa-user"></i> Personal Details</div>
        <div class="form-row">
          <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($user_data['name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Unit Number</label>
            <input type="text" name="unit_number" value="<?= htmlspecialchars($user_data['unit_number'] ?? '') ?>" required>
          </div>
        </div>

        <div class="section-title"><i class="fas fa-wrench"></i> Issue Details</div>
        <div class="form-row">
          <div class="form-group">
            <label>Urgency</label>
            <select name="urgency" required>
              <option value="">Select urgency</option>
              <option value="low">Low (within 1 week)</option>
              <option value="medium">Medium (within 3 days)</option>
              <option value="high">High (ASAP)</option>
            </select>
          </div>
          <div class="form-group">
            <label>Preferred Date</label>
            <input type="date" name="preferred_date" min="<?= date('Y-m-d'); ?>">
          </div>
        </div>

        <div class="form-group">
          <label>Issue Description</label>
          <textarea name="issue" placeholder="Describe the issue..." required></textarea>
        </div>

        <div class="form-group">
          <label>Upload Photo (Optional)</label>
          <label class="file-input">
            <i class="fas fa-camera"></i> Choose Image
            <input type="file" name="issue_photo" accept="image/*" style="display:none">
          </label>
        </div>

        <button type="submit"><i class="fas fa-paper-plane"></i> Submit Request</button>
      </form>
    </div>

    <div class="footer">
      <p>Need urgent help? Call <a href="tel:+254722877608">+254 722 877 608</a></p>
      <p>&copy; <?= date('Y'); ?> Mojo Systems. All rights reserved.</p>
    </div>
  </div>
</body>
</html>
