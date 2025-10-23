<?php
// Database connection for Mojo Tenant System

$host = "localhost";
$user = "root";
$pass = "1234";  
$dbname = "auth_db";  

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die(" Database connection failed: " . $conn->connect_error);
}
?>

