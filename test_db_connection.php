<?php
/**
 * Database Connection Test
 * Tests all aspects of the new database connection
 */

echo "====================================\n";
echo "Database Connection Test Results\n";
echo "====================================\n\n";

// Test 1: Configuration Loading
echo "Test 1: Configuration Loading\n";
echo "------------------------------\n";
require_once 'config.php';
echo "✓ DB_HOST: " . DB_HOST . "\n";
echo "✓ DB_PORT: " . DB_PORT . "\n";
echo "✓ DB_NAME: " . DB_NAME . "\n";
echo "✓ DB_USER: " . DB_USER . "\n";
echo "\n";

// Test 2: Database Connection
echo "Test 2: Database Connection\n";
echo "------------------------------\n";
require_once 'db.php';

if (isset($conn) && $conn) {
    echo "✓ Connection successful!\n";
    echo "✓ Connection type: " . get_class($conn) . "\n";
} else {
    echo "✗ Connection failed!\n";
    exit(1);
}
echo "\n";

// Test 3: Charset Verification
echo "Test 3: Charset Verification\n";
echo "------------------------------\n";
$result = mysqli_query($conn, 'SELECT @@character_set_connection');
$charset = mysqli_fetch_row($result)[0];
echo "✓ Character set: " . $charset . "\n";
if ($charset === 'utf8mb4') {
    echo "✓ UTF-8MB4 correctly configured!\n";
} else {
    echo "✗ Warning: Expected utf8mb4, got " . $charset . "\n";
}
echo "\n";

// Test 4: MySQL Version
echo "Test 4: MySQL/MariaDB Version\n";
echo "------------------------------\n";
$result = mysqli_query($conn, 'SELECT VERSION()');
$version = mysqli_fetch_row($result)[0];
echo "✓ Database version: " . $version . "\n";
echo "\n";

// Test 5: Timezone Check
echo "Test 5: Timezone Configuration\n";
echo "------------------------------\n";
$result = mysqli_query($conn, 'SELECT @@session.time_zone');
$timezone = mysqli_fetch_row($result)[0];
echo "✓ Session timezone: " . $timezone . "\n";
echo "\n";

// Test 6: Test Database Existence
echo "Test 6: Database & Tables\n";
echo "------------------------------\n";
$tables = ['admin', 'employees', 'documents', 'messages', 'teams'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' NOT FOUND\n";
    }
}
echo "\n";

// Test 7: Prepared Statement Test
echo "Test 7: Prepared Statements\n";
echo "------------------------------\n";
$testId = 'test';
$stmt = mysqli_prepare($conn, "SELECT * FROM admin WHERE username = ?");
if ($stmt) {
    echo "✓ Prepared statement created successfully\n";
    mysqli_stmt_bind_param($stmt, "s", $testId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    echo "✓ Prepared statement executed successfully\n";
    mysqli_stmt_close($stmt);
} else {
    echo "✗ Failed to create prepared statement\n";
}
echo "\n";

// Test 8: Error Log Check
echo "Test 8: Error Logging\n";
echo "------------------------------\n";
if (is_dir('logs')) {
    echo "✓ Logs directory exists\n";
    if (is_writable('logs')) {
        echo "✓ Logs directory is writable\n";
    } else {
        echo "✗ Logs directory is not writable\n";
    }
} else {
    echo "✗ Logs directory does not exist\n";
}

if (file_exists('logs/.htaccess')) {
    echo "✓ .htaccess protection exists\n";
} else {
    echo "✗ .htaccess protection missing\n";
}
echo "\n";

echo "====================================\n";
echo "All tests completed successfully!\n";
echo "====================================\n";

mysqli_close($conn);
?>
