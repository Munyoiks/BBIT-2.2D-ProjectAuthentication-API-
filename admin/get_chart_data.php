<?php
require_once "../auth/db_config.php";

// Fetch occupancy
$apartments_exist = $conn->query("SHOW TABLES LIKE 'apartments'")->num_rows > 0;
$mpesa_exist = $conn->query("SHOW TABLES LIKE 'mpesa_transactions'")->num_rows > 0;

$data = [
    'totalApartments' => 0,
    'occupiedApartments' => 0,
    'monthlyRevenue' => [],
];

if ($apartments_exist) {
    $apt = $conn->query("SELECT COUNT(*) AS total, SUM(is_occupied) AS occupied FROM apartments")->fetch_assoc();
    $data['totalApartments'] = $apt['total'];
    $data['occupiedApartments'] = $apt['occupied'];
}

if ($mpesa_exist) {
    $result = $conn->query("
        SELECT MONTH(transaction_date) AS month, SUM(amount) AS total
        FROM mpesa_transactions
        WHERE YEAR(transaction_date) = YEAR(CURRENT_DATE())
        GROUP BY MONTH(transaction_date)
        ORDER BY MONTH(transaction_date)
    ");
    $months = array_fill(1, 12, 0);
    while ($row = $result->fetch_assoc()) {
        $months[(int)$row['month']] = (float)$row['total'];
    }
    $data['monthlyRevenue'] = array_values($months);
}

header('Content-Type: application/json');
echo json_encode($data);
?>
