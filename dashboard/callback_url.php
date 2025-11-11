// callback_url.php
<?php
require_once "../auth/db_config.php";

// Read the incoming JSON from Safaricom
$data = file_get_contents('php://input');
file_put_contents('mpesa_callback_log.json', $data . PHP_EOL, FILE_APPEND);

$response = json_decode($data, true);

if (!$response) {
    http_response_code(400);
    exit("Invalid JSON");
}

// Check for STK callback info
if (isset($response['Body']['stkCallback'])) {
    $stkCallback = $response['Body']['stkCallback'];
    $resultCode = $stkCallback['ResultCode'];
    $resultDesc = $stkCallback['ResultDesc'];
    $checkoutRequestID = $stkCallback['CheckoutRequestID'];
    $merchantRequestID = $stkCallback['MerchantRequestID'];

    // Log the callback details
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'checkoutRequestID' => $checkoutRequestID,
        'merchantRequestID' => $merchantRequestID,
        'resultCode' => $resultCode,
        'resultDesc' => $resultDesc,
        'full_response' => $stkCallback
    ];
    file_put_contents('mpesa_detailed_log.json', json_encode($logData) . PHP_EOL, FILE_APPEND);

    // Process based on result code
    if ($resultCode == 0) {
        // Payment was successful
        if (isset($stkCallback['CallbackMetadata']['Item'])) {
            $items = $stkCallback['CallbackMetadata']['Item'];
            $amount = null;
            $mpesaReceipt = null;
            $phone = null;
            $transactionDate = null;

            foreach ($items as $item) {
                switch ($item['Name']) {
                    case 'Amount':
                        $amount = $item['Value'];
                        break;
                    case 'MpesaReceiptNumber':
                        $mpesaReceipt = $item['Value'];
                        break;
                    case 'PhoneNumber':
                        $phone = $item['Value'];
                        break;
                    case 'TransactionDate':
                        $transactionDate = $item['Value'];
                        break;
                }
            }

            if ($amount && $mpesaReceipt && $phone) {
                // Find the pending payment using checkoutRequestID
                $find_stmt = $conn->prepare("
                    SELECT id, user_id, amount, phone, month 
                    FROM payments 
                    WHERE mpesa_receipt = ? AND status = 'pending'
                ");
                $find_stmt->bind_param("s", $checkoutRequestID);
                $find_stmt->execute();
                $result = $find_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $payment = $result->fetch_assoc();
                    $find_stmt->close();

                    // Update the payment record with success details
                    $update_stmt = $conn->prepare("
                        UPDATE payments 
                        SET status = 'success', 
                            mpesa_receipt = ?,
                            payment_date = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->bind_param("si", $mpesaReceipt, $payment['id']);
                    
                    if ($update_stmt->execute()) {
                        // Log successful update
                        $successLog = "Payment updated successfully - Receipt: $mpesaReceipt, Amount: $amount, User ID: {$payment['user_id']}\n";
                        file_put_contents('mpesa_success_log.txt', $successLog, FILE_APPEND);
                        
                        // You can also send notification to user here if needed
                    } else {
                        $errorLog = "Failed to update payment: " . $update_stmt->error . "\n";
                        file_put_contents('mpesa_error_log.txt', $errorLog, FILE_APPEND);
                    }
                    $update_stmt->close();
                    
                } else {
                    // If no pending payment found, create a new one
                    $user_id = $this->findUserIdByPhone($phone, $conn);
                    
                    $insert_stmt = $conn->prepare("
                        INSERT INTO payments (user_id, amount, phone, month, mpesa_receipt, status, created_at, payment_date) 
                        VALUES (?, ?, ?, 'Auto-Detected', ?, 'success', NOW(), NOW())
                    ");
                    $insert_stmt->bind_param("idss", $user_id, $amount, $phone, $mpesaReceipt);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                    
                    $log = "New payment created - Receipt: $mpesaReceipt, Amount: $amount, Phone: $phone\n";
                    file_put_contents('mpesa_new_payments.txt', $log, FILE_APPEND);
                }
            }
        }
    } else {
        // Payment failed or was cancelled
        // Update the payment status to failed
        $update_failed_stmt = $conn->prepare("
            UPDATE payments 
            SET status = 'failed', 
                updated_at = NOW() 
            WHERE mpesa_receipt = ? AND status = 'pending'
        ");
        $update_failed_stmt->bind_param("s", $checkoutRequestID);
        $update_failed_stmt->execute();
        
        $failedLog = "Payment failed - CheckoutID: $checkoutRequestID, Code: $resultCode, Desc: $resultDesc\n";
        file_put_contents('mpesa_failed_log.txt', $failedLog, FILE_APPEND);
        
        $update_failed_stmt->close();
    }
}

// Helper function to find user by phone number
function findUserIdByPhone($phone, $conn) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user['id'];
    }
    
    $stmt->close();
    return 1; // Default admin user if not found
}

// Always respond to Safaricom (important!)
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback received successfully']);
?>
