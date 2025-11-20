//setup_database.php
<?php
session_start();

// Protect this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../auth/db_config.php";

$success = true;
$messages = [];

// SQL commands to create required tables
$sql_commands = [
    "apartments" => "
        CREATE TABLE IF NOT EXISTS apartments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            apartment_number VARCHAR(20) NOT NULL UNIQUE,
            building_name VARCHAR(100),
            rent_amount DECIMAL(10,2) NOT NULL,
            is_occupied BOOLEAN DEFAULT FALSE,
            tenant_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ",
    
    "mpesa_transactions" => "
        CREATE TABLE IF NOT EXISTS mpesa_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(100) UNIQUE,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            phone_number VARCHAR(20),
            transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('completed', 'pending', 'failed') DEFAULT 'completed',
            description TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ",
    
    "sample_apartments" => "
        INSERT IGNORE INTO apartments (apartment_number, building_name, rent_amount) VALUES
        ('A101', 'Main Building', 15000.00),
        ('A102', 'Main Building', 15000.00),
        ('B201', 'Annex Building', 12000.00),
        ('B202', 'Annex Building', 12000.00),
        ('C301', 'Garden View', 18000.00),
        ('C302', 'Garden View', 18000.00)
    ",
    
    "sample_mpesa" => "
        INSERT IGNORE INTO mpesa_transactions (transaction_id, user_id, amount, phone_number, status, description) VALUES
        ('MPE001', 1, 15000.00, '254712345678', 'completed', 'Rent payment for A101'),
        ('MPE002', 2, 15000.00, '254723456789', 'completed', 'Rent payment for A102'),
        ('MPE003', 3, 12000.00, '254734567890', 'completed', 'Rent payment for B201')
    "
];

// Execute SQL commands
foreach ($sql_commands as $table_name => $sql) {
    try {
        if ($conn->query($sql)) {
            if (strpos($table_name, 'sample_') === 0) {
                $messages[] = "✓ Sample data inserted successfully";
            } else {
                $messages[] = "✓ Table '$table_name' created successfully";
            }
        } else {
            $messages[] = "✗ Failed to create/setup: $table_name - " . $conn->error;
            $success = false;
        }
    } catch (Exception $e) {
        $messages[] = "✗ Error with $table_name: " . $e->getMessage();
        $success = false;
    }
}

// Check if tables were created successfully
$tables_to_check = ['apartments', 'mpesa_transactions'];
$created_tables = [];

foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        $created_tables[] = $table;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Setup - Monrine Tenant System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
            max-width: 600px;
            width: 100%;
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .status-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .success { border-left-color: #28a745; background: #d4edda; }
        .error { border-left-color: #dc3545; background: #f8d7da; }
        
        .message-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .message-list li {
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .message-list li:last-child {
            border-bottom: none;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 20px;
        }
        
        .table-info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .table-info h3 {
            margin-top: 0;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Setup</h1>
        
        <div class="status-box <?php echo $success ? 'success' : 'error'; ?>">
            <h3><?php echo $success ? 'Setup Completed Successfully!' : 'Setup Completed with Errors'; ?></h3>
            
            <ul class="message-list">
                <?php foreach ($messages as $message): ?>
                    <li><?php echo htmlspecialchars($message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (!empty($created_tables)): ?>
        <div class="table-info">
            <h3>Created Tables:</h3>
            <ul class="message-list">
                <?php foreach ($created_tables as $table): ?>
                    <li>✓ <?php echo htmlspecialchars($table); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="action-buttons">
            <?php if ($success): ?>
                <a href="admin_dashboard.php" class="btn btn-success">Go to Dashboard</a>
            <?php else: ?>
                <a href="setup_database.php" class="btn">Retry Setup</a>
            <?php endif; ?>
            <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h4>What was created:</h4>
            <ul>
                <li><strong>apartments table</strong> - Stores apartment information and occupancy status</li>
                <li><strong>mpesa_transactions table</strong> - Tracks rent payments via MPESA</li>
                <li><strong>Sample data</strong> - 6 sample apartments and 3 sample transactions</li>
            </ul>
        </div>
    </div>
</body>
</html>
