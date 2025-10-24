<?php
// payments_migration.php

$servername = "localhost";
$username = "root";
$password = "1234";
$dbname = "auth_db";

// Connect to MySQL
$conn = new mysqli($servername, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database '$dbname' created or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);

// Create `payments` table with the exact structure
$sql = "
CREATE TABLE IF NOT EXISTS payments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    phone VARCHAR(20) NOT NULL,
    month VARCHAR(20) DEFAULT NULL,
    mpesa_receipt VARCHAR(50) DEFAULT NULL,
    status ENUM('pending','success','failed') DEFAULT 'pending',
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql) === TRUE) {
    echo "Table 'payments' created or already exists.<br>";
} else {
    echo "Error creating payments table: " . $conn->error . "<br>";
}

// Check if we need to alter the table structure (for existing tables)
$result = $conn->query("DESCRIBE payments");
if ($result) {
    $existingColumns = [];
    while ($row = $result->fetch_assoc()) {
        $existingColumns[$row['Field']] = true;
    }

    // Add missing columns if they don't exist
    if (!isset($existingColumns['month'])) {
        $conn->query("ALTER TABLE payments ADD COLUMN month VARCHAR(20) DEFAULT NULL");
        echo "Added missing column: month<br>";
    }

    if (!isset($existingColumns['mpesa_receipt'])) {
        $conn->query("ALTER TABLE payments ADD COLUMN mpesa_receipt VARCHAR(50) DEFAULT NULL");
        echo "Added missing column: mpesa_receipt<br>";
    }

    if (!isset($existingColumns['status'])) {
        $conn->query("ALTER TABLE payments ADD COLUMN status ENUM('pending','success','failed') DEFAULT 'pending'");
        echo "Added missing column: status<br>";
    }

    if (!isset($existingColumns['payment_date'])) {
        $conn->query("ALTER TABLE payments ADD COLUMN payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added missing column: payment_date<br>";
    }

    if (!isset($existingColumns['created_at'])) {
        $conn->query("ALTER TABLE payments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Added missing column: created_at<br>";
    }

    // Add foreign key constraint if it doesn't exist
    $fkCheck = $conn->query("
        SELECT COUNT(*) as fk_exists 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE CONSTRAINT_SCHEMA = '$dbname' 
        AND TABLE_NAME = 'payments' 
        AND CONSTRAINT_NAME = 'payments_ibfk_1'
    ");
    
    if ($fkCheck && $fkCheck->fetch_assoc()['fk_exists'] == 0) {
        $conn->query("ALTER TABLE payments ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "Added foreign key constraint: user_id -> users(id)<br>";
    }

    // Add indexes if they don't exist
    $indexCheck = $conn->query("SHOW INDEX FROM payments WHERE Key_name = 'idx_user_id'");
    if ($indexCheck->num_rows == 0) {
        $conn->query("CREATE INDEX idx_user_id ON payments (user_id)");
        echo "Added index: idx_user_id<br>";
    }

    $indexCheck2 = $conn->query("SHOW INDEX FROM payments WHERE Key_name = 'idx_payment_date'");
    if ($indexCheck2->num_rows == 0) {
        $conn->query("CREATE INDEX idx_payment_date ON payments (payment_date)");
        echo "Added index: idx_payment_date<br>";
    }
} else {
    echo "Error describing payments table: " . $conn->error . "<br>";
}

// Add sample payment data for testing (only if no payments exist)
$checkPayments = $conn->query("SELECT COUNT(*) as count FROM payments");
if ($checkPayments) {
    $paymentCount = $checkPayments->fetch_assoc()['count'];
    
    if ($paymentCount == 0) {
        // Get a sample user to associate with payments
        $sampleUser = $conn->query("SELECT id FROM users WHERE email = 'tenant@example.com' LIMIT 1");
        
        if ($sampleUser && $sampleUser->num_rows > 0) {
            $user = $sampleUser->fetch_assoc();
            $user_id = $user['id'];
            
            // Insert sample payments
            $samplePayments = [
                [
                    'user_id' => $user_id,
                    'amount' => 5000.00,
                    'phone' => '254712345678',
                    'month' => 'January 2024',
                    'mpesa_receipt' => 'RC123456789',
                    'status' => 'success'
                ],
                [
                    'user_id' => $user_id,
                    'amount' => 5000.00,
                    'phone' => '254712345678',
                    'month' => 'February 2024',
                    'mpesa_receipt' => 'RC123456790',
                    'status' => 'success'
                ],
                [
                    'user_id' => $user_id,
                    'amount' => 5000.00,
                    'phone' => '254712345678',
                    'month' => 'March 2024',
                    'mpesa_receipt' => NULL,
                    'status' => 'pending'
                ]
            ];
            
            $insertCount = 0;
            foreach ($samplePayments as $payment) {
                $stmt = $conn->prepare("
                    INSERT INTO payments (user_id, amount, phone, month, mpesa_receipt, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "idssss", 
                    $payment['user_id'],
                    $payment['amount'],
                    $payment['phone'],
                    $payment['month'],
                    $payment['mpesa_receipt'],
                    $payment['status']
                );
                
                if ($stmt->execute()) {
                    $insertCount++;
                }
                $stmt->close();
            }
            
            echo "Added $insertCount sample payment records.<br>";
        } else {
            echo "No sample user found. Please run users migration first.<br>";
        }
    } else {
        echo "Payments already exist in the database ($paymentCount records).<br>";
    }
}

echo "<br>Payments migration complete!";
$conn->close();
?>