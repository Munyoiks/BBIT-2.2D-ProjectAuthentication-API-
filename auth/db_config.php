<?php
// Universal Database Configuration using Environment Variables

// Load environment variables if available (optional for local .env file)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue; // skip comments
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Get credentials from environment or fallback to defaults
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'auth_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'munyoiks7';

// Create connection WITH database selection
$conn = new mysqli($host, $user, $pass, $dbname);

// Set charset
$conn->set_charset("utf8mb4");

// Log connection errors internally, do not show to user
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit; // stop script if connection fails
}
?>
