<?php
$host = "localhost";
$user = "root";        // default for XAMPP
$password = "";        // leave empty if using XAMPP
$database = "drims_database";

// Create connection
$conn = mysqli_connect($host, $user, $password, $database);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
