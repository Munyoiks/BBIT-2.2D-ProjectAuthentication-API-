<?php
session_start();

// ---- DATABASE CONNECTION ----
$host = "localhost";
$user = "root";
$pass = "";
$db   = "mojo_db"; // change to your actual DB name

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// ---- FORMAT PHONE NUMBER ----
function formatPhone($phone) {
    if (!$phone) return false;
    $phone = preg_replace('/\D/', '', $phone); // remove non-digits
    if (preg_match('/^0\d{9}$/', $phone)) {
        return '+254' . substr($phone, 1);
    } elseif (preg_match('/^254\d{9}$/', $phone)) {
        return '+' . $phone;
    } elseif (preg_match('/^\+254\d{9}$/', $phone)) {
        return $phone;
    }
    return false;
}

// ---- REGISTER ----
if (isset($_POST['register'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = formatPhone($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    if (!$email) die("Invalid email format");
    if (!$phone) die("Invalid Kenyan phone number");

    $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $check->bind_param("ss", $email, $phone);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        echo "Email or phone already exists.";
        exit();
    }
    $check->close();

    $stmt = $conn->prepare("INSERT INTO users (email, phone, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $phone, $password);

    if ($stmt->execute()) {
        echo "Registration successful! <a href='auth.php'>Login Now</a>";
    } else {
        echo "Database Error: " . $conn->error;
    }
    $stmt->close();
}

// ---- LOGIN ----
if (isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    if (!$email) {
        echo "Invalid email format.";
        exit();
    }

    $stmt = $conn->prepare("SELECT id, password, phone FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            // Generate 6-digit verification code
            $code = rand(100000, 999999);

            $_SESSION['pending_email'] = $email;
            $_SESSION['verification_code'] = $code;
            $_SESSION['login_id'] = $user['id'];

            // Go to verification page
            header("Location: verify.php");
            exit();
        } else {
            echo "Invalid password.";
        }
    } else {
        echo "User not found.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Authentication</title>
    <style>
        .container { max-width: 400px; margin: 50px auto; padding: 20px; }
        form { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        input { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        h2 { color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="phone" placeholder="Phone (e.g., 0722123456)" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="register">Register</button>
        </form>

        <h2>Login</h2>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
    </div>
</body>
</html>
