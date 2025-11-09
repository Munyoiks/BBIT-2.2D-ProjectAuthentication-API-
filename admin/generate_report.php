<?php
session_start();
require_once "../auth/db_config.php";

// Ensure admin access
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['full_name'] ?? 'Administrator';

// Initialize stats with default values
$stats = [
    'total_tenants' => 0,
    'total_apartments' => 0,
    'occupied_apartments' => 0,
    'vacant_apartments' => 0,
    'monthly_revenue' => 0,
    'collection_rate' => 0
];

$monthly_revenue = [];
$payment_status = ['Paid' => 0, 'Pending' => 0];
$recent_transactions = [];
$top_apartments = [];

// ✅ Fetch data safely with error handling
try {
    // Total verified tenants
    $res = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0 AND is_verified = 1");
    $stats['total_tenants'] = $res->fetch_assoc()['total'] ?? 0;

    // Apartments data
    $res = $conn->query("SELECT COUNT(*) as total FROM apartments");
    $stats['total_apartments'] = $res->fetch_assoc()['total'] ?? 0;

    $res = $conn->query("SELECT COUNT(*) as total FROM apartments WHERE is_occupied = 1");
    $stats['occupied_apartments'] = $res->fetch_assoc()['total'] ?? 0;
    $stats['vacant_apartments'] = $stats['total_apartments'] - $stats['occupied_apartments'];

    // Current month revenue
    $res = $conn->query("SELECT SUM(amount) as total FROM mpesa_transactions WHERE MONTH(transaction_date) = MONTH(CURRENT_DATE()) AND YEAR(transaction_date) = YEAR(CURRENT_DATE())");
    $stats['monthly_revenue'] = $res->fetch_assoc()['total'] ?? 0;

    // Revenue per month (last 6 months)
    $revenue_query = "
        SELECT DATE_FORMAT(transaction_date, '%b %Y') AS month, 
               SUM(amount) AS total,
               COUNT(*) as transactions
        FROM mpesa_transactions
        WHERE transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(transaction_date), MONTH(transaction_date)
        ORDER BY YEAR(transaction_date), MONTH(transaction_date)
    ";
    $result = $conn->query($revenue_query);
    while ($row = $result->fetch_assoc()) {
        $monthly_revenue[$row['month']] = [
            'revenue' => (int)$row['total'],
            'transactions' => (int)$row['transactions']
        ];
    }

    // Payment summary for current month
    $payment_query = "
        SELECT 
            CASE WHEN m.id IS NOT NULL THEN 'Paid' ELSE 'Pending' END AS status,
            COUNT(DISTINCT u.id) AS total
        FROM users u
        LEFT JOIN apartments a ON u.id = a.tenant_id
        LEFT JOIN mpesa_transactions m ON u.id = m.user_id 
            AND MONTH(m.transaction_date) = MONTH(CURRENT_DATE())
            AND YEAR(m.transaction_date) = YEAR(CURRENT_DATE())
        WHERE u.is_admin = 0 AND u.is_verified = 1 AND a.id IS NOT NULL
        GROUP BY status
    ";
    $res = $conn->query($payment_query);
    while ($row = $res->fetch_assoc()) {
        $payment_status[$row['status']] = (int)$row['total'];
    }

    // Recent transactions
    $recent_query = "
        SELECT mt.*, u.full_name, u.unit_number
        FROM mpesa_transactions mt
        JOIN users u ON mt.user_id = u.id
        ORDER BY mt.transaction_date DESC
        LIMIT 10
    ";
    $recent_result = $conn->query($recent_query);
    while ($row = $recent_result->fetch_assoc()) {
        $recent_transactions[] = $row;
    }

    // Top performing apartments by revenue
    $top_apt_query = "
        SELECT a.apartment_number, a.building_name, a.rent_amount,
               u.full_name, SUM(mt.amount) as total_revenue
        FROM apartments a
        LEFT JOIN users u ON a.tenant_id = u.id
        LEFT JOIN mpesa_transactions mt ON u.id = mt.user_id 
            AND mt.transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 3 MONTH)
        WHERE a.is_occupied = 1
        GROUP BY a.id
        ORDER BY total_revenue DESC
        LIMIT 5
    ";
    $top_result = $conn->query($top_apt_query);
    while ($row = $top_result->fetch_assoc()) {
        $top_apartments[] = $row;
    }

    // Calculate collection rate
    $total_occupied = $stats['occupied_apartments'];
    $total_paid = $payment_status['Paid'];
    $stats['collection_rate'] = $total_occupied > 0 ? round(($total_paid / $total_occupied) * 100, 1) : 0;

} catch (Exception $e) {
    $error = "Error generating reports: " . $e->getMessage();
    error_log("Report Generation Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financial Reports | Mojo Management System</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --primary: #2c3e50;
    --secondary: #3498db;
    --success: #27ae60;
    --warning: #f39c12;
    --danger: #e74c3c;
    --light: #ecf0f1;
    --dark: #2c3e50;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #333;
    min-height: 100vh;
    padding: 20px;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    overflow: hidden;
}

/* Header Styles */
.header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 25px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.header h1 {
    font-size: 28px;
    font-weight: 600;
}

.header-actions {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: var(--secondary);
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #229954;
    transform: translateY(-2px);
}

.btn-outline {
    background: transparent;
    color: white;
    border: 2px solid white;
}

.btn-outline:hover {
    background: white;
    color: var(--primary);
    transform: translateY(-2px);
}

/* Content Styles */
.content {
    padding: 30px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-left: 5px solid var(--secondary);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card h3 {
    font-size: 14px;
    color: #7f8c8d;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 5px;
}

.stat-subtext {
    font-size: 14px;
    color: #95a5a6;
}

.stat-card.success { border-left-color: var(--success); }
.stat-card.warning { border-left-color: var(--warning); }
.stat-card.danger { border-left-color: var(--danger); }

/* Charts Section */
.charts-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin-bottom: 40px;
}

.chart-container {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-header h3 {
    font-size: 18px;
    color: var(--primary);
    font-weight: 600;
}

/* Tables Section */
.tables-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

.table-container {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.table-container h3 {
    font-size: 18px;
    color: var(--primary);
    margin-bottom: 20px;
    font-weight: 600;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ecf0f1;
}

th {
    background: #f8f9fa;
    color: var(--primary);
    font-weight: 600;
}

tr:hover {
    background: #f8f9fa;
}

.status-paid { color: var(--success); font-weight: 600; }
.status-pending { color: var(--warning); font-weight: 600; }

/* Report Actions */
.report-actions {
    text-align: center;
    padding: 30px;
    border-top: 2px solid #ecf0f1;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .charts-section,
    .tables-section {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .header {
        flex-direction: column;
        text-align: center;
    }
    
    .header-actions {
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .content {
        padding: 20px;
    }
}

/* Print Styles */
@media print {
    .header-actions,
    .report-actions {
        display: none;
    }
    
    body {
        background: white;
        padding: 0;
    }
    
    .container {
        box-shadow: none;
        border-radius: 0;
    }
}
</style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-chart-bar"></i> Financial Reports Dashboard</h1>
                <p style="margin-top: 5px; opacity: 0.9;">Comprehensive overview of property performance and finances</p>
            </div>
            <div class="header-actions">
                <a href="admin_dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if (!empty($error)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid var(--danger);">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Tenants</h3>
                    <div class="stat-number"><?= $stats['total_tenants'] ?></div>
                    <div class="stat-subtext">Verified residents</div>
                </div>
                
                <div class="stat-card">
                    <h3>Total Apartments</h3>
                    <div class="stat-number"><?= $stats['total_apartments'] ?></div>
                    <div class="stat-subtext">Property units</div>
                </div>
                
                <div class="stat-card success">
                    <h3>Occupied Units</h3>
                    <div class="stat-number"><?= $stats['occupied_apartments'] ?></div>
                    <div class="stat-subtext"><?= round(($stats['occupied_apartments'] / max(1, $stats['total_apartments'])) * 100, 1) ?>% occupancy</div>
                </div>
                
                <div class="stat-card warning">
                    <h3>Vacant Units</h3>
                    <div class="stat-number"><?= $stats['vacant_apartments'] ?></div>
                    <div class="stat-subtext">Available for rent</div>
                </div>
                
                <div class="stat-card">
                    <h3>Monthly Revenue</h3>
                    <div class="stat-number">KSh <?= number_format($stats['monthly_revenue']) ?></div>
                    <div class="stat-subtext">Current month collections</div>
                </div>
                
                <div class="stat-card <?= $stats['collection_rate'] >= 80 ? 'success' : 'danger' ?>">
                    <h3>Collection Rate</h3>
                    <div class="stat-number"><?= $stats['collection_rate'] ?>%</div>
                    <div class="stat-subtext">Rent payment efficiency</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Revenue Trend (Last 6 Months)</h3>
                    </div>
                    <canvas id="revenueChart" height="300"></canvas>
                </div>
                
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Payment Status (Current Month)</h3>
                    </div>
                    <canvas id="paymentChart" height="300"></canvas>
                </div>
            </div>

            <!-- Tables Section -->
            <div class="tables-section">
                <div class="table-container">
                    <h3><i class="fas fa-history"></i> Recent Transactions</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Unit</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_transactions)): ?>
                                <?php foreach($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><?= htmlspecialchars($transaction['full_name']) ?></td>
                                    <td><?= htmlspecialchars($transaction['unit_number'] ?? 'N/A') ?></td>
                                    <td>KSh <?= number_format($transaction['amount']) ?></td>
                                    <td><?= date('M j, Y', strtotime($transaction['transaction_date'])) ?></td>
                                    <td class="status-paid">Completed</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #95a5a6;">No recent transactions</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-container">
                    <h3><i class="fas fa-trophy"></i> Top Performing Apartments</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Apartment</th>
                                <th>Tenant</th>
                                <th>Rent</th>
                                <th>Revenue (3M)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($top_apartments)): ?>
                                <?php foreach($top_apartments as $apartment): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($apartment['apartment_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($apartment['full_name'] ?? 'Vacant') ?></td>
                                    <td>KSh <?= number_format($apartment['rent_amount']) ?></td>
                                    <td>KSh <?= number_format($apartment['total_revenue'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #95a5a6;">No apartment data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Report Actions -->
            <div class="report-actions">
                <button id="downloadPDF" class="btn btn-success">
                    <i class="fas fa-file-pdf"></i> Download PDF Report
                </button>
            </div>
        </div>
    </div>

    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($monthly_revenue)) ?>,
                datasets: [{
                    label: 'Monthly Revenue (KSh)',
                    data: <?= json_encode(array_column($monthly_revenue, 'revenue')) ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KSh ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Payment Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentChart = new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Pending'],
                datasets: [{
                    data: [<?= $payment_status['Paid'] ?>, <?= $payment_status['Pending'] ?>],
                    backgroundColor: ['#27ae60', '#f39c12'],
                    borderColor: '#fff',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Download PDF Report
        document.getElementById('downloadPDF').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Add title
            doc.setFontSize(20);
            doc.text('Mojo Management System - Financial Report', 20, 30);
            
            // Add date
            doc.setFontSize(12);
            doc.text('Generated on: <?= date("F j, Y") ?>', 20, 45);
            doc.text('Generated by: <?= htmlspecialchars($admin_name) ?>', 20, 55);
            
            // Add statistics
            doc.setFontSize(16);
            doc.text('Key Statistics', 20, 75);
            doc.setFontSize(12);
            doc.text(Total Tenants: ${<?= $stats['total_tenants'] ?>}, 20, 90);
            doc.text(Total Apartments: ${<?= $stats['total_apartments'] ?>}, 20, 100);
            doc.text(Occupied Units: ${<?= $stats['occupied_apartments'] ?>}, 20, 110);
            doc.text(Monthly Revenue: KSh ${<?= $stats['monthly_revenue'] ?>}, 20, 120);
            doc.text(Collection Rate: ${<?= $stats['collection_rate'] ?>}%, 20, 130);
            
            // Add note
            doc.setFontSize(10);
            doc.text('Report generated automatically by Mojo Management System', 20, 280);
            
            // Save the PDF
            doc.save('mojo_financial_report_<?= date("Y_m_d") ?>.pdf');
        });
    </script>

    <!-- Include jsPDF from CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</body>
</html>
