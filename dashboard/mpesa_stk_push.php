<?php
session_start();
require_once "../auth/db_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- M-Pesa API credentials ---
$consumerKey = 'QoAwY42wjTsCJT4DQejuknJFAyJkzzxMaj61RLPT2tChsN4D';
$consumerSecret = 'GEeXLrxcUTyFVZYeoOp5Gi5NKsrZgkZRcv2bkOVtQsFuFpWPRLq6JFO1aCzSMrAB';
$businessShortCode = '174379';
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
$callbackURL = 'https://yourdomain.com/dashboard/callback_url.php';

// --- Get input values ---
$amount = $_POST['amount'];
$phone = $_POST['phone'];

// Format phone number (in case user enters 07XXXXXXXX)
if (substr($phone, 0, 1) === '0') {
    $phone = '254' . substr($phone, 1);
}

// --- Get Access Token ---
$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $url);
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($curl);
$result = json_decode($result);
$access_token = $result->access_token;
curl_close($curl);

// --- Initiate STK Push ---
$timestamp = date("YmdHis");
$password = base64_encode($businessShortCode . $passkey . $timestamp);

$stkheader = ['Content-Type:application/json', 'Authorization:Bearer ' . $access_token];

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
    'AccountReference' => 'MojoRent',
    'TransactionDesc' => 'Monthly Rent Payment'
];

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
curl_setopt($curl, CURLOPT_HTTPHEADER, $stkheader);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
$response = curl_exec($curl);
curl_close($curl);

$responseData = json_decode($response, true);

// --- Handle response ---
if (isset($responseData['ResponseCode']) && $responseData['ResponseCode'] == "0") {
    $MerchantRequestID = $responseData['MerchantRequestID'];
    $CheckoutRequestID = $responseData['CheckoutRequestID'];

    // Insert into payments table (pending)
    $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, phone, phone_number, month, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $month = date("F Y");
    $stmt->bind_param("idsss", $user_id, $amount, $phone, $phone, $month);
    $stmt->execute();
    
    echo "<h3> STK Push sent successfully!</h3>";
    echo "<p>Please complete payment on your phone.</p>";
    echo "<pre>" . json_encode($responseData, JSON_PRETTY_PRINT) . "</pre>";

} else {
    echo "<h3> Failed to initiate STK Push</h3>";
    echo "<pre>" . json_encode($responseData, JSON_PRETTY_PRINT) . "</pre>";
}
?>
