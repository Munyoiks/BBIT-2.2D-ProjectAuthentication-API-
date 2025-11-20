
<?php
session_start();
require_once '../auth/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch tenant/user info
$stmt = $conn->prepare("SELECT full_name, email, phone, unit_number FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch payment history
$payment_stmt = $conn->prepare("
    SELECT id, amount, phone, month, status, mpesa_receipt, created_at, payment_date 
    FROM payments 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$payment_stmt->bind_param("i", $user_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payments = $payment_result->fetch_all(MYSQLI_ASSOC);
$payment_stmt->close();

$message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $amount = trim($_POST['amount']);
    $phone = trim($_POST['phone']);
    $month = trim($_POST['month']);

    if (empty($amount) || empty($phone) || empty($month)) {
        $message = "<div class='alert alert-error'>Please enter amount, phone number, and select month.</div>";
    } elseif ($amount < 10) {
        $message = "<div class='alert alert-error'>Amount must be at least KES 10.</div>";
    } else {
        // Format phone number correctly
        $original_phone = $phone;
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }

        // Check if payment for this month already exists and is successful
        $check_stmt = $conn->prepare("SELECT id FROM payments WHERE user_id = ? AND month = ? AND status = 'success'");
        $check_stmt->bind_param("is", $user_id, $month);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = "<div class='alert alert-warning'>You have already made a successful payment for $month.</div>";
            $check_stmt->close();
        } else {
            $check_stmt->close();

            // === Daraja API credentials ===
            $consumerKey = 'QoAwY42wjTsCJT4DQejuknJFAyJkzzxMaj61RLPT2tChsN4D';
            $consumerSecret = 'GEeXLrxcUTyFVZYeoOp5Gi5NKsrZgkZRcv2bkOVtQsFuFpWPRLq6JFO1aCzSMrAB';
            $businessShortCode = '174379'; // Test paybill
            $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
            $callbackURL = 'https://yourdomain.com/dashboard/callback.php'; // UPDATE THIS WITH YOUR ACTUAL DOMAIN

            // === Get Access Token ===
            $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
            $curl = curl_init($url);
            $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
            curl_setopt_array($curl, [
                CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $credentials],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30
            ]);
            $result = curl_exec($curl);
            $token_data = json_decode($result);
            $access_token = $token_data->access_token ?? '';
            curl_close($curl);

            if (empty($access_token)) {
                $message = "<div class='alert alert-error'>Failed to connect to M-Pesa. Please try again later.</div>";
            } else {
                // === Prepare STK push request ===
                $timestamp = date("YmdHis");
                $password = base64_encode($businessShortCode . $passkey . $timestamp);

                $stkheader = [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token
                ];

                $curl_post_data = [
                    'BusinessShortCode' => $businessShortCode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => $businessShortCode,
                    'PhoneNumber' => $phone,
                    'CallBackURL' => $callbackURL,
                    'AccountReference' => 'Rent-' . $user_id,
                    'TransactionDesc' => "Rent Payment for $month - " . ($user['unit_number'] ?? 'N/A')
                ];

                $curl = curl_init('https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
                curl_setopt_array($curl, [
                    CURLOPT_HTTPHEADER => $stkheader,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($curl_post_data),
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT => 30
                ]);
                $response = curl_exec($curl);
                $curl_error = curl_error($curl);
                curl_close($curl);

                $responseData = json_decode($response, true);

                // === Handle STK response ===
                if (isset($responseData['ResponseCode']) && $responseData['ResponseCode'] == "0") {
                    // Payment request accepted
                    $status = "pending";
                    $checkout_request_id = $responseData['CheckoutRequestID'] ?? '';
                    $merchant_request_id = $responseData['MerchantRequestID'] ?? '';

                    // Store payment with checkout_request_id as temporary mpesa_receipt
                    $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, phone, month, status, mpesa_receipt, merchant_request_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("idsssss", $user_id, $amount, $phone, $month, $status, $checkout_request_id, $merchant_request_id);
                    
                    if ($stmt->execute()) {
                        $message = "<div class='alert alert-success'>
                            <h4><i class='fas fa-check-circle'></i> Payment Initiated Successfully!</h4>
                            <p>Please check your phone <strong>$original_phone</strong> to complete the M-Pesa payment.</p>
                            <p><strong>Amount:</strong> KES " . number_format($amount) . "</p>
                            <p><strong>Month:</strong> $month</p>
                            <p><strong>Reference:</strong> Rent-$user_id</p>
                            <p><small>You will receive an MPESA confirmation message shortly.</small></p>
                        </div>";
                    } else {
                        $message = "<div class='alert alert-error'>Failed to save payment record. Please contact support.</div>";
                    }
                    $stmt->close();
                } else {
                    $errorMsg = $responseData['errorMessage'] ?? 'Unknown error occurred';
                    $message = "<div class='alert alert-error'>
                        <h4><i class='fas fa-exclamation-circle'></i> Payment Failed</h4>
                        <p>Failed to initiate payment: $errorMsg</p>
                        " . ($curl_error ? "<p>Network Error: $curl_error</p>" : "") . "
                    </div>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent Payments - Monrine Electrical Enterprise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            padding: 20px;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5em;
        }

        .header p {
            color: #666;
            font-size: 1.1em;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .user-info {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 10px;
        }

        .user-info p {
            margin: 8px 0;
            font-size: 1.1em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }

        .alert i {
            margin-right: 10px;
        }

        .payment-history {
            max-height: 500px;
            overflow-y: auto;
        }

        .payment-item {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: transform 0.2s;
        }

        .payment-item:hover {
            transform: translateX(5px);
        }

        .payment-success {
            border-left: 4px solid #28a745;
        }

        .payment-pending {
            border-left: 4px solid #ffc107;
        }

        .payment-failed {
            border-left: 4px solid #dc3545;
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .payment-amount {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }

        .payment-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .payment-details {
            color: #666;
            font-size: 0.9em;
        }

        .payment-details p {
            margin: 5px 0;
        }

        .empty-state {
            text-align: center;
            color: #666;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: #ccc;
        }

        .month-select {
            background: white;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 16px;
            width: 100%;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .receipt-number {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h1>Rent Payments</h1>
            <p>Manage your rental payments securely with M-Pesa</p>
        </div>

        <div class="main-content">
            <!-- Payment Form Section -->
            <div class="card">
                <h2><i class="fas fa-credit-card"></i> Make Payment</h2>
                
                <div class="user-info">
                    <p><i class="fas fa-user"></i> <strong>Tenant:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                    <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-home"></i> <strong>Unit:</strong> <?php echo htmlspecialchars($user['unit_number'] ?? 'Not assigned'); ?></p>
                    <p><i class="fas fa-phone"></i> <strong>Default Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                </div>

                <?php echo $message; ?>

                <form method="POST" action="" id="paymentForm">
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-mobile-alt"></i> M-Pesa Phone Number</label>
                        <input type="text" name="phone" id="phone" class="form-control" 
                               placeholder="e.g. 254712345678" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        <small style="color: #666; margin-top: 5px; display: block;">Must be registered with M-Pesa</small>
                    </div>

                    <div class="form-group">
                        <label for="amount"><i class="fas fa-money-bill-wave"></i> Amount (KES)</label>
                        <input type="number" name="amount" id="amount" class="form-control" 
                               placeholder="e.g. 80000" min="10" step="1" required>
                    </div>

                    <div class="form-group">
                        <label for="month"><i class="fas fa-calendar-alt"></i> Payment Month</label>
                        <select name="month" id="month" class="month-select" required>
                            <option value="">Select Month</option>
                            <?php
                            $months = [
                                'January', 'February', 'March', 'April', 'May', 'June',
                                'July', 'August', 'September', 'October', 'November', 'December'
                            ];
                            $currentYear = date('Y');
                            $currentMonth = date('n');
                            
                            for ($i = 0; $i < 12; $i++) {
                                $monthIndex = ($currentMonth - 1 + $i) % 12;
                                $monthYear = $months[$monthIndex] . " " . ($currentYear + floor(($currentMonth - 1 + $i) / 12));
                                $selected = $i == 0 ? 'selected' : '';
                                echo "<option value='$monthYear' $selected>$monthYear</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <button type="submit" class="btn" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Pay with M-Pesa
                    </button>

                    <div class="loading" id="loading">
                        <div class="loading-spinner"></div>
                        <p>Processing payment request...</p>
                    </div>
                </form>

                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h4><i class="fas fa-info-circle"></i> Payment Instructions:</h4>
                    <ol style="margin-left: 20px; margin-top: 10px;">
                        <li>Enter your M-Pesa registered phone number</li>
                        <li>Enter the payment amount</li>
                        <li>Select the payment month</li>
                        <li>Click "Pay with M-Pesa"</li>
                        <li>Check your phone for STK push prompt</li>
                        <li>Enter your M-Pesa PIN to complete</li>
                        <li>Wait for confirmation message</li>
                    </ol>
                </div>
            </div>

            <!-- Payment History Section -->
            <div class="card">
                <h2><i class="fas fa-history"></i> Payment History</h2>
                <div class="payment-history">
                    <?php if (empty($payments)): ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h3>No Payments Yet</h3>
                            <p>Your payment history will appear here</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <div class="payment-item payment-<?php echo $payment['status']; ?>">
                                <div class="payment-header">
                                    <span class="payment-amount">KES <?php echo number_format($payment['amount'], 2); ?></span>
                                    <span class="payment-status status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </div>
                                <div class="payment-details">
                                    <p><strong>Month:</strong> <?php echo htmlspecialchars($payment['month']); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($payment['phone']); ?></p>
                                    <?php if ($payment['mpesa_receipt'] && $payment['status'] == 'success'): ?>
                                        <p><strong>Receipt:</strong> <span class="receipt-number"><?php echo htmlspecialchars($payment['mpesa_receipt']); ?></span></p>
                                    <?php elseif ($payment['status'] == 'pending'): ?>
                                        <p><strong>Status:</strong> Waiting for MPESA confirmation...</p>
                                    <?php endif; ?>
                                    <p><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></p>
                                    <?php if ($payment['payment_date'] && $payment['status'] == 'success'): ?>
                                        <p><strong>Confirmed:</strong> <?php echo date('M j, Y g:i A', strtotime($payment['payment_date'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-format phone number
        document.getElementById('phone').addEventListener('blur', function(e) {
            let phone = e.target.value.replace(/\D/g, '');
            if (phone.startsWith('0')) {
                phone = '254' + phone.substring(1);
            } else if (!phone.startsWith('254')) {
                phone = '254' + phone;
            }
            e.target.value = phone;
        });

        // Add thousands separator to amount input
        document.getElementById('amount').addEventListener('blur', function(e) {
            let value = e.target.value.replace(/,/g, '');
            if (value) {
                e.target.value = Number(value).toLocaleString();
            }
        });

        // Remove commas when focusing on amount input
        document.getElementById('amount').addEventListener('focus', function(e) {
            let value = e.target.value.replace(/,/g, '');
            e.target.value = value;
        });

        // Form submission handling
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            
            // Show loading state
            submitBtn.style.display = 'none';
            loading.style.display = 'block';
            
            // Basic validation
            const amount = document.getElementById('amount').value.replace(/,/g, '');
            const phone = document.getElementById('phone').value;
            const month = document.getElementById('month').value;
            
            if (!amount || !phone || !month) {
                alert('Please fill in all required fields.');
                submitBtn.style.display = 'block';
                loading.style.display = 'none';
                e.preventDefault();
                return;
            }
            
            if (amount < 10) {
                alert('Amount must be at least KES 10.');
                submitBtn.style.display = 'block';
                loading.style.display = 'none';
                e.preventDefault();
                return;
            }
            
            // Allow form to submit normally
        });

        // Auto-refresh page every 30 seconds to update payment status
        setInterval(function() {
            const pendingPayments = document.querySelectorAll('.status-pending');
            if (pendingPayments.length > 0) {
                // If there are pending payments, refresh to check status
                window.location.reload();
            }
        }, 30000); // 30 seconds
    </script>
</body>
</html>
