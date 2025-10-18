<?php
session_start();
require_once "../auth/db_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Profile | Mojo Tenant Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #007bff, #00c6ff);
      margin: 0;
      padding: 0;
      color: #333;
    }

    .profile-container {
      max-width: 800px;
      margin: 60px auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
      padding: 40px;
      animation: fadeIn 0.6s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .profile-header {
      text-align: center;
      margin-bottom: 30px;
    }

    .profile-header img {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      border: 4px solid #007bff;
      margin-bottom: 15px;
    }

    .profile-header h1 {
      color: #007bff;
      margin: 10px 0;
    }

    .profile-details {
      margin-bottom: 25px;
    }

    .profile-details p {
      margin: 8px 0;
      font-size: 16px;
    }

    .profile-details i {
      color: #007bff;
      margin-right: 8px;
    }

    .edit-form {
      display: grid;
      gap: 15px;
    }

    input, textarea {
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #ccc;
      font-size: 15px;
    }

    button {
      background: #007bff;
      color: white;
      border: none;
      padding: 12px;
      border-radius: 8px;
      font-size: 16px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    button:hover {
      background: #0056b3;
    }

    .download-btn {
      display: block;
      text-align: center;
      margin-top: 25px;
      text-decoration: none;
      background: #28a745;
      color: white;
      padding: 12px 20px;
      border-radius: 8px;
      font-weight: 500;
    }

    .download-btn:hover {
      background: #218838;
    }
  </style>
</head>

<body>
  <div class="profile-container">
    <div class="profile-header">
      <img src="../assets/user_avatar.png" alt="User Profile">
      <h1><?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
      <p><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="profile-details">
      <p><i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone'] ?? 'Not provided', ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <form class="edit-form" method="POST" action="update_profile.php">
      <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Full Name" required>
      <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Phone Number" required>
      <textarea name="address" placeholder="Your Address"><?= htmlspecialchars($user['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      <input type="text" name="emergency_contact" placeholder="Emergency Contact" value="<?= htmlspecialchars($user['emergency_contact'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit"><i class="fas fa-save"></i> Save Changes</button>
    </form>

    <a href="generate_tenant_doc.php" class="download-btn"><i class="fas fa-file-download"></i> Download Tenant Document</a>
  </div>
</body>
</html>
