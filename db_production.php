<?php
/**
 * Production Database Connection
 * Replace db.php with this file for production deployment
 * Or rename this to db.php after deployment
 */

// Load configuration if available
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    $host = DB_HOST;
    $user = DB_USER;
    $password = DB_PASS;
    $database = DB_NAME;
} else {
    // Fallback to environment variables
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'drims_user';
    $password = getenv('DB_PASS') ?: '';
    $database = getenv('DB_NAME') ?: 'drims_production';
}

// Create connection with error handling
$conn = @mysqli_connect($host, $user, $password, $database);

// Check connection
if (!$conn) {
    // Log error securely (don't expose credentials)
    error_log("Database connection failed: " . mysqli_connect_error());
    
    // Show user-friendly error (don't expose technical details in production)
    if (defined('APP_DEBUG') && APP_DEBUG) {
        die("Database connection failed: " . mysqli_connect_error());
    } else {
        die("System temporarily unavailable. Please contact your administrator.");
    }
}

// Set charset to prevent SQL injection
mysqli_set_charset($conn, "utf8mb4");

// Set timezone
mysqli_query($conn, "SET time_zone = '+08:00'");
?>
