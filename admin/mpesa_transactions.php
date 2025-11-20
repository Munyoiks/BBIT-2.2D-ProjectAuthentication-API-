<?php
session_start();

// Protect this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../auth/db_config.php";

// Get all payments transactions (from the payments table used by rent.php)
$transactions_query = "
    SELECT p.*, u.full_name, u.email, u.unit_number 
    FROM payments p 
    LEFT JOIN users u ON p.user_id = u.id 
    ORDER BY p.created_at DESC
";
$transactions_result = $conn->query($transactions_query);

// Get total revenue
$total_revenue = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'success'")->fetch_assoc()['total'];
$monthly_revenue = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'success' AND MONTH(created_at) = MONTH(CURRENT_DATE())")->fetch_assoc()['total'];
$today_revenue = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'success' AND DATE(created_at) = CURDATE()")->fetch_assoc()['total'];

// Get status counts
$completed_count = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status = 'success'")->fetch_assoc()['total'];
$pending_count = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'")->fetch_assoc()['total'];
$failed_count = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status = 'failed'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPESA Transactions</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            padding: 20px 0;
            text-align: center;
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h2 {
            font-weight: 600;
            margin: 0;
        }
        
        .content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .logout {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .back {
            position: absolute;
            top: 20px;
            left: 20px;
        }
        
        .logout a, .back a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .logout a {
            background: #dc3545;
        }
        
        .logout a:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .back a {
            background: #6c757d;
        }
        
        .back a:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            border-left: 4px solid #007bff;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            color: #6c757d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: 700;
            color: #007bff;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 30px;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        th {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background: #f8f9fa;
            transition: background 0.3s ease;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .amount {
            font-weight: 600;
            color: #28a745;
        }
        
        .transaction-id {
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            color: #495057;
        }
        
        .tenant-info {
            display: flex;
            flex-direction: column;
        }
        
        .tenant-name {
            font-weight: 600;
            color: #007bff;
        }
        
        .tenant-email {
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .date-time {
            display: flex;
            flex-direction: column;
        }
        
        .date {
            font-weight: 600;
        }
        
        .time {
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        @media (max-width: 768px) {
            .content {
                padding: 15px;
            }
            
            .header {
                padding: 15px 0;
            }
            
            .logout, .back {
                position: static;
                display: inline-block;
                margin: 5px;
            }
            
            .header h2 {
                margin: 10px 0;
            }
            
            .stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 10px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 0.9em;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filter-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="back"><a href="admin_dashboard.php">← Back to Dashboard</a></div>
        <h2>MPESA Transactions</h2>
        <div class="logout"><a href="admin_logout.php">Logout</a></div>
    </div>
    
    <div class="content">
        <!-- Statistics Cards -->
        <div class="stats">
            <div class="stat-card">
                <h3>Total Transactions</h3>
                <div class="stat-number"><?= $transactions_result->num_rows ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="stat-number">KSh <?= number_format($total_revenue ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>This Month</h3>
                <div class="stat-number">KSh <?= number_format($monthly_revenue ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Today</h3>
                <div class="stat-number">KSh <?= number_format($today_revenue ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <h3>Completed</h3>
                <div class="stat-number" style="color: #28a745;"><?= $completed_count ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="stat-number" style="color: #ffc107;"><?= $pending_count ?></div>
            </div>
            <div class="stat-card">
                <h3>Failed</h3>
                <div class="stat-number" style="color: #dc3545;"><?= $failed_count ?></div>
            </div>
        </div>
        
        <div class="card">
            <!-- Quick Filters -->
            <div class="filters">
                <button class="filter-btn active" onclick="filterTransactions('all')">All Transactions</button>
                <button class="filter-btn" onclick="filterTransactions('success')">Completed</button>
                <button class="filter-btn" onclick="filterTransactions('pending')">Pending</button>
                <button class="filter-btn" onclick="filterTransactions('failed')">Failed</button>
                <button class="filter-btn" onclick="filterTransactions('today')">Today</button>
            </div>
            
            <table id="transactionsTable">
                <thead>
                    <tr>
                        <th>Receipt Number</th>
                        <th>Tenant</th>
                        <th>Unit</th>
                        <th>Amount</th>
                        <th>Phone</th>
                        <th>Month</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions_result->num_rows > 0): ?>
                        <?php while($transaction = $transactions_result->fetch_assoc()): ?>
                        <tr class="transaction-row" data-status="<?= $transaction['status'] ?>" data-date="<?= date('Y-m-d', strtotime($transaction['created_at'])) ?>">
                            <td>
                                <span class="transaction-id"><?= htmlspecialchars($transaction['mpesa_receipt'] ?? 'Pending...') ?></span>
                            </td>
                            <td>
                                <div class="tenant-info">
                                    <span class="tenant-name"><?= htmlspecialchars($transaction['full_name'] ?? 'N/A') ?></span>
                                    <span class="tenant-email"><?= htmlspecialchars($transaction['email'] ?? '') ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($transaction['unit_number'] ?? 'N/A') ?></td>
                            <td class="amount">KSh <?= number_format($transaction['amount']) ?></td>
                            <td><?= htmlspecialchars($transaction['phone'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($transaction['month'] ?? 'N/A') ?></td>
                            <td>
                                <div class="date-time">
                                    <span class="date"><?= date('M j, Y', strtotime($transaction['created_at'])) ?></span>
                                    <span class="time"><?= date('H:i', strtotime($transaction['created_at'])) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $transaction['status'] ?>">
                                    <?= ucfirst($transaction['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <h3>No Transactions Found</h3>
                                    <p>There are no MPESA transactions recorded yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function filterTransactions(filter) {
            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            const rows = document.querySelectorAll('.transaction-row');
            const today = new Date().toISOString().split('T')[0];
            
            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const date = row.getAttribute('data-date');
                
                switch(filter) {
                    case 'all':
                        row.style.display = '';
                        break;
                    case 'success':
                        row.style.display = status === 'success' ? '' : 'none';
                        break;
                    case 'pending':
                        row.style.display = status === 'pending' ? '' : 'none';
                        break;
                    case 'failed':
                        row.style.display = status === 'failed' ? '' : 'none';
                        break;
                    case 'today':
                        row.style.display = date === today ? '' : 'none';
                        break;
                }
            });
        }
        
        // Add some interactive features
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.transaction-row');
            
            tableRows.forEach(row => {
                row.addEventListener('click', function() {
                    this.style.backgroundColor = '#f8f9fa';
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 300);
                });
            });
        });
    </script>
</body>
</html>
