<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'auth_db';
$username = 'root';
$password = 'munyoiks7';

$success_message = '';
$error_message = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch user data
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $full_name = $user['full_name'];
        $email = $user['email'];
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email'] = $email;
    } else {
        $full_name = $_SESSION['full_name'] ?? 'Tenant';
        $email = $_SESSION['email'] ?? '';
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['submit_request'])) {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $priority = $_POST['priority'];
            $category = $_POST['category'];
            
            // Validate inputs
            if (empty($title) || empty($description)) {
                $error_message = "Please fill in all required fields.";
            } else {
                // Insert maintenance request
                $stmt = $pdo->prepare("INSERT INTO maintenance_requests (user_id, title, description, priority, category, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$user_id, $title, $description, $priority, $category]);
                
                $success_message = "Maintenance request submitted successfully!";
            }
        }
        
        // Handle status updates (for demonstration)
        if (isset($_POST['update_status'])) {
            $request_id = $_POST['request_id'];
            $new_status = $_POST['status'];
            
            // In a real application, this would be done by admin/landlord
            $stmt = $pdo->prepare("UPDATE maintenance_requests SET status = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$new_status, $request_id, $user_id]);
            
            $success_message = "Request status updated successfully!";
        }
    }

    // Fetch maintenance requests
    $stmt = $pdo->prepare("SELECT * FROM maintenance_requests WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $maintenance_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $full_name = $_SESSION['full_name'] ?? 'Tenant';
    $email = $_SESSION['email'] ?? '';
    $maintenance_requests = [];
    error_log("Database error: " . $e->getMessage());
    $error_message = "Unable to load maintenance requests. Please try again later.";
}

// Create maintenance_requests table if it doesn't exist
try {
    $create_table_sql = "CREATE TABLE IF NOT EXISTS maintenance_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        priority ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
        category VARCHAR(100) NOT NULL,
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($create_table_sql);
} catch (PDOException $e) {
    error_log("Table creation error: " . $e->getMessage());
}
