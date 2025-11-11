<?php 
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require_once "../auth/db_config.php"; 

// Debug: Check if file is updated
error_log("Login.php loaded - Version: " . date('Y-m-d H:i:s'));

if (isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    if (!$email) {
        $error = "Invalid email format.";
    } else {
        // Fetch user and admin info
        $stmt = $conn->prepare("SELECT id, full_name, email, password, is_verified, is_admin FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                
                // Debug logging
                error_log("Login attempt - User ID: " . $user['id'] . ", Is Admin: " . $user['is_admin'] . ", Is Verified: " . $user['is_verified']);

                // Admins bypass verification and go to admin dashboard
                if ($user['is_admin'] == 1) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['is_admin'] = $user['is_admin'];

                    // Debug
                    error_log("Redirecting admin to admin dashboard");
                    
                    // Redirect admin to admin dashboard
                    header("Location: ../admin/admin_dashboard.php");
                    exit();
                } 
                // Tenants must be verified and go to tenant dashboard
                else if ($user['is_verified'] == 1) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['is_admin'] = $user['is_admin'];

                    // Debug
                    error_log("Redirecting tenant to tenant dashboard");
                    
                    // Redirect tenant to tenant dashboard
                    header("Location: ../dashboard/dashboard.php");
                    exit();
                } else {
                    $error = "Your account is not verified. Please check your email for the verification link.";
                }

            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "No account found with that email.";
        }
    }
}

// Force session regeneration to prevent stale sessions
session_regenerate_id(true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mojo Tenant System | Login</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px 35px;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #8B5FBF, #6A3093);
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 1.8rem;
        }

        p.lead {
            text-align: center;
            color: #666;
            margin-top: 0;
            margin-bottom: 30px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        form {
            margin-top: 8px;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            margin: 12px 0;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        input:focus {
            outline: none;
            border-color: #8B5FBF;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(139, 95, 191, 0.1);
        }

        input::placeholder {
            color: #999;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #8B5FBF, #6A3093);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 95, 191, 0.3);
        }

        button:hover {
            background: linear-gradient(135deg, #7a4fa8, #5a287a);
            box-shadow: 0 6px 20px rgba(139, 95, 191, 0.4);
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

        .error {
            color: #e74c3c;
            margin-bottom: 20px;
            text-align: center;
            padding: 12px;
            background: #fdf2f2;
            border-radius: 8px;
            border-left: 4px solid #e74c3c;
            font-size: 0.9rem;
        }

        .link {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
        }

        a {
            color: #8B5FBF;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        a:hover {
            color: #6A3093;
            text-decoration: underline;
        }

        .small {
            font-size: 13px;
            color: #666;
        }

        .forgot-password {
            display: block;
            margin-bottom: 8px;
        }

        .register-link {
            display: block;
            margin-top: 12px;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 25px;
            }
            
            h2 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Login to Mojo Tenant System</h2>
    <p class="lead">Welcome back — enter your credentials below.</p>

    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="email" name="email" placeholder="Email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>

    <div class="link">
        <a id="forgot-link" href="../passReset/reset.php" class="forgot-password">Forgot Password?</a>
        <a href="register.php" class="register-link">Don't have an account? Register</a>
        
        <!-- Admin password reset note -->
        <p class="small" style="margin-top: 15px; color: #e74c3c;">
            <strong>Admin Note:</strong> If you're an admin and forgot your password, contact system administrator.
        </p>
    </div>
</div>

<script>
// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Force hard refresh check
console.log("Login page loaded at: " + new Date().toLocaleString());

(function(){
  const el = document.getElementById('forgot-link');
  if (el) {
    console.log("Forgot password link href:", el.getAttribute('href'));
  }
})();
</script>
</body>
</html>
