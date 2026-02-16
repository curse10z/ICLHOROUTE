<?php
/**
 * DRIMS Configuration File
 * Production-ready configuration with environment variable support
 */

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'drims_database');

// Application Settings
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');

// Upload Settings
define('MAX_UPLOAD_SIZE', (int)(getenv('MAX_UPLOAD_SIZE') ?: 10485760)); // 10MB default
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Session Configuration
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 3600)); // 1 hour
define('SESSION_NAME', getenv('SESSION_NAME') ?: 'DRIMS_SESSION');

// Security Settings
define('SECURE_COOKIES', getenv('SECURE_COOKIES') === 'true');
define('SAME_SITE_COOKIES', getenv('SAME_SITE_COOKIES') ?: 'Lax');

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    // Only set session config if session hasn't started yet
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', SAME_SITE_COOKIES);
    if (SECURE_COOKIES) {
        ini_set('session.cookie_secure', 1);
    }
}

// Allowed file extensions
define('ALLOWED_EXTENSIONS', explode(',', getenv('ALLOWED_EXTENSIONS') ?: 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,webp,bmp,txt,rtf,odt,ods,odp'));
?>
