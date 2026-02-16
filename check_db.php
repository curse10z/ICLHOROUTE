<?php
// Quick database check script
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Starting Database Check...</h2>";

// Test connection manually first
$host = "localhost";
$user = "root";
$password = "";
$database = "drims_database";

echo "<h3>Connection Test:</h3>";
echo "Attempting to connect to MySQL...<br>";

$conn = @mysqli_connect($host, $user, $password);

if (!$conn) {
    echo "<strong style='color:red;'>ERROR: Cannot connect to MySQL!</strong><br>";
    echo "Error details: " . mysqli_connect_error() . "<br>";
    echo "<hr>";
    echo "<h3>Troubleshooting Steps:</h3>";
    echo "<ol>";
    echo "<li><strong>Open XAMPP Control Panel</strong></li>";
    echo "<li><strong>Check if MySQL service shows 'Running' (green)</strong></li>";
    echo "<li>If NOT running, click the <strong>Start</strong> button next to MySQL</li>";
    echo "<li>Wait for it to turn green, then refresh this page</li>";
    echo "</ol>";
    die();
} else {
    echo "✓ <strong style='color:green;'>Successfully connected to MySQL!</strong><br>";
}

// Check if database exists
echo "<br>Checking if database 'drims_database' exists...<br>";
$dbCheck = @mysqli_select_db($conn, $database);

if (!$dbCheck) {
    echo "<strong style='color:red;'>ERROR: Database 'drims_database' does NOT exist!</strong><br>";
    echo "<hr>";
    echo "<h3>Fix: Import the database</h3>";
    echo "<ol>";
    echo "<li>Go to <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>";
    echo "<li>Click 'New' in the left sidebar</li>";
    echo "<li>Create database named: <strong>drims_database</strong></li>";
    echo "<li>Select it, then click 'Import'</li>";
    echo "<li>Choose file: <strong>C:\\xampp\\htdocs\\ICLHO_Route\\drims_database.sql</strong></li>";
    echo "<li>Click 'Go' to import</li>";
    echo "<li>Refresh this page</li>";
    echo "</ol>";
    mysqli_close($conn);
    die();
} else {
    echo "✓ <strong style='color:green;'>Database 'drims_database' exists!</strong><br>";
}

echo "<h2>Database Check Results</h2>";

// Check if tables exist
echo "<h3>Tables Check:</h3>";
$tables = ['admin', 'employees', 'teams', 'documents', 'messages'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "✓ Table '$table' exists<br>";
        
        // Count records
        $countResult = mysqli_query($conn, "SELECT COUNT(*) as count FROM $table");
        $count = mysqli_fetch_assoc($countResult)['count'];
        echo "&nbsp;&nbsp;&nbsp; → $count records<br>";
        
        // Show first few records for employees
        if ($table == 'employees') {
            echo "&nbsp;&nbsp;&nbsp; <strong>Employees:</strong><br>";
            $empResult = mysqli_query($conn, "SELECT employee_id, name, email, team FROM employees LIMIT 5");
            if (mysqli_num_rows($empResult) > 0) {
                while ($emp = mysqli_fetch_assoc($empResult)) {
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; - ID: {$emp['employee_id']}, Name: {$emp['name']}, Team: {$emp['team']}<br>";
                }
            } else {
                echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <span style='color:red;'>No employees found! You need to create an employee first.</span><br>";
            }
        }
    } else {
        echo "✗ Table '$table' MISSING<br>";
    }
}

// Check admin
echo "<h3>Admin Account:</h3>";
$adminResult = mysqli_query($conn, "SELECT username FROM admin");
if ($adminResult && mysqli_num_rows($adminResult) > 0) {
    $admin = mysqli_fetch_assoc($adminResult);
    echo "✓ Admin username: {$admin['username']}<br>";
} else {
    echo "✗ No admin account found<br>";
}

mysqli_close($conn);
?>
