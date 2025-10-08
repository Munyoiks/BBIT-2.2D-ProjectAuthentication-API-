<?php
// Database connection for Mojo Tenant System

$host = "localhost";
$user = "root";
$pass = "munyoiks7";  // your secure password
$dbname = "auth_db";  // your database name

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die(" Database connection failed: " . $conn->connect_error);
}
?>

