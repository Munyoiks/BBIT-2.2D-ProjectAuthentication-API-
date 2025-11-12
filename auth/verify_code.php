<?php
session_start();
require_once "db_config.php";

// Ensure verification session data exists
if (!isset($_SESSION['pending_email']) || !isset($_SESSION['verification_code'])) {
    die("Session expired. Please register again. <a href='register.php'>Register</a>");
}

$email = $_SESSION['pending_email'];
$code = $_SESSION['verification_code'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = trim($_POST['code']);

    if ($entered == $code) {
        //  Code matches — verify user in DB
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();

        //  Clear temporary session vars
        unset($_SESSION['verification_code']);
        unset($_SESSION['pending_email']);

        // Automatically log user in
        $_SESSION['user_email'] = $email;
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Verification Successful</title>
            <style>
                body {
                    font-family: "Segoe UI", Arial, sans-serif;
                    background: linear-gradient(135deg, #007bff, #00d4ff);
                    height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0;
                }
                .success-container {
                    background: #fff;
                    padding: 30px 40px;
                    border-radius: 12px;
                    text-align: center;
                    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
                    animation: fadeIn 0.7s ease;
                }
                h2 {
                    color: #007bff;
                    margin-bottom: 10px;
                }
                p {
                    color: #333;
                    font-size: 15px;
                    margin-bottom: 20px;
                }
                @keyframes fadeIn {
                    from {opacity: 0; transform: translateY(20px);}
                    to {opacity: 1; transform: translateY(0);}
                }
            </style>
        </head>
        <body>
            <div class="success-container">
                <h2> Email Verified Successfully!</h2>
                <p>Redirecting you to your dashboard...</p>
            </div>

            <script>
                //  Redirect to the correct dashboard location
                setTimeout(() => {
                    window.location.href = "http://localhost/BBIT-2.2D-ProjectAuthentication-API-1/dashboard/dashboard.php";
                }, 2500);
            </script>
        </body>
        </html>
        <?php
        exit();
    } else {
        // Incorrect code
        $_SESSION['error'] = "Incorrect code. Please try again.";
        header("Location: verify.php");
        exit();
    }
} else {
    header("Location: verify.php");
    exit();
}
?>
