<?php
session_start();

// If not logged in, redirect to authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: authentication.php");
    exit();
}

$email = $_SESSION['email'] ?? 'Unknown';
$phone = $_SESSION['phone'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Dashboard</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f4f6f9;
    }
    .sidebar {
      width: 220px;
      height: 100vh;
      background: #2c3e50;
      color: #fff;
      position: fixed;
      top: 0;
      left: 0;
      padding-top: 20px;
    }
    .sidebar h2 {
      text-align: center;
      margin-bottom: 30px;
    }
    .sidebar a {
      display: block;
      padding: 12px 20px;
      color: #fff;
      text-decoration: none;
      transition: 0.3s;
    }
    .sidebar a:hover {
      background: #34495e;
    }
    .main-content {
      margin-left: 220px;
      padding: 20px;
    }
    .card {
      background: #fff;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .logout-btn {
      background: #e74c3c;
      border: none;
      padding: 10px 20px;
      color: #fff;
      cursor: pointer;
      border-radius: 5px;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <h2>Attendance</h2>
    <a href="#">Dashboard</a>
    <a href="#">Employees/Students</a>
    <a href="#">Mark Attendance</a>
    <a href="#">View Attendance</a>
    <a href="#">Leave Requests</a>
    <a href="#">Reports</a>
    <a href="#">User Settings</a>
    <form method="POST" action="logout.php" style="margin-top:20px; text-align:center;">
      <button class="logout-btn" type="submit" name="logout">Logout</button>
    </form>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <h1>Welcome, <?php echo htmlspecialchars($email); ?></h1>
    <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
    <p>You are logged in with 2FA successfully.</p>

    <div class="card">
      <h2>Quick Stats</h2>
      <p>Total Employees/Students: <strong>120</strong></p>
      <p>Present Today: <strong>110</strong></p>
      <p>On Leave: <strong>5</strong></p>
      <p>Absent: <strong>5</strong></p>
    </div>

    <div class="card">
      <h2>Recent Attendance Records</h2>
      <table border="1" cellpadding="10" cellspacing="0" width="100%">
        <tr>
          <th>Name</th>
          <th>Date</th>
          <th>Status</th>
        </tr>
        <tr>
          <td>John Doe</td>
          <td>2025-10-01</td>
          <td>Present</td>
        </tr>
        <tr>
          <td>Jane Smith</td>
          <td>2025-10-01</td>
          <td>Leave</td>
        </tr>
      </table>
    </div>
  </div>
</body>
</html>

