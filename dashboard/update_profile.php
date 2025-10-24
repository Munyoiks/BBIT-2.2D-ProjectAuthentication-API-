<<<<<<< HEAD
<?php
session_start();
require_once '../auth/db_config.php'; // adjust path if needed

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch current user data
$stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Update profile info
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST['full_name']);
    $email = strtolower(trim($_POST['email']));
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($full_name) || empty($email) || empty($phone)) {
        $message = "<p class='error'>Please fill in all required fields.</p>";
    } else {
        // If password is being updated
        if (!empty($password)) {
            if ($password !== $confirm_password) {
                $message = "<p class='error'>Passwords do not match.</p>";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, password=? WHERE id=?");
                $stmt->bind_param("ssssi", $full_name, $email, $phone, $hashed_password, $user_id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
            $stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
        }

        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $message = "<p class='success'>Profile updated successfully!</p>";
        } else {
            $message = "<p class='error'>Error updating profile: " . htmlspecialchars($stmt->error) . "</p>";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Profile | Mojo Tenant System</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; }
        .container { max-width: 500px; margin: 60px auto; padding: 25px; background: #fff;
                     border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; color: #333; }
        label { font-weight: bold; display: block; margin-top: 10px; }
        input[type=text], input[type=email], input[type=password] {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; margin-top: 5px;
        }
        button {
            margin-top: 20px; width: 100%; padding: 10px; background: #007bff;
            color: white; border: none; border-radius: 6px; cursor: pointer;
        }
        button:hover { background: #0056b3; }
        .success { color: green; text-align: center; }
        .error { color: red; text-align: center; }
        .back { text-align: center; margin-top: 10px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h2>Update Profile</h2>
    <?php echo $message; ?>

    <form method="POST" action="">
        <label for="full_name">Full Name:</label>
        <input type="text" id="full_name" name="full_name"
               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email"
               value="<?php echo htmlspecialchars($user['email']); ?>" required>

        <label for="phone">Phone Number:</label>
        <input type="text" id="phone" name="phone"
               value="<?php echo htmlspecialchars($user['phone']); ?>" required>

        <label for="password">New Password (optional):</label>
        <input type="password" id="password" name="password" placeholder="Leave blank to keep current">

        <label for="confirm_password">Confirm New Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password">

        <button type="submit">Save Changes</button>
    </form>

    <div class="back">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>
</div>

</body>
=======
<?php
session_start();
require_once '../auth/db_config.php'; // adjust path if needed

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch current user data
$stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Update profile info
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST['full_name']);
    $email = strtolower(trim($_POST['email']));
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($full_name) || empty($email) || empty($phone)) {
        $message = "<p class='error'>Please fill in all required fields.</p>";
    } else {
        // If password is being updated
        if (!empty($password)) {
            if ($password !== $confirm_password) {
                $message = "<p class='error'>Passwords do not match.</p>";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, password=? WHERE id=?");
                $stmt->bind_param("ssssi", $full_name, $email, $phone, $hashed_password, $user_id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
            $stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
        }

        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $message = "<p class='success'>Profile updated successfully!</p>";
        } else {
            $message = "<p class='error'>Error updating profile: " . htmlspecialchars($stmt->error) . "</p>";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Profile | Mojo Tenant System</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; }
        .container { max-width: 500px; margin: 60px auto; padding: 25px; background: #fff;
                     border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; color: #333; }
        label { font-weight: bold; display: block; margin-top: 10px; }
        input[type=text], input[type=email], input[type=password] {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; margin-top: 5px;
        }
        button {
            margin-top: 20px; width: 100%; padding: 10px; background: #007bff;
            color: white; border: none; border-radius: 6px; cursor: pointer;
        }
        button:hover { background: #0056b3; }
        .success { color: green; text-align: center; }
        .error { color: red; text-align: center; }
        .back { text-align: center; margin-top: 10px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h2>Update Profile</h2>
    <?php echo $message; ?>

    <form method="POST" action="">
        <label for="full_name">Full Name:</label>
        <input type="text" id="full_name" name="full_name"
               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email"
               value="<?php echo htmlspecialchars($user['email']); ?>" required>

        <label for="phone">Phone Number:</label>
        <input type="text" id="phone" name="phone"
               value="<?php echo htmlspecialchars($user['phone']); ?>" required>

        <label for="password">New Password (optional):</label>
        <input type="password" id="password" name="password" placeholder="Leave blank to keep current">

        <label for="confirm_password">Confirm New Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password">

        <button type="submit">Save Changes</button>
    </form>

    <div class="back">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>
</div>

</body>
>>>>>>> 22ac192ccce8ae52be570e19bf8f174fa1564bb6
</html>