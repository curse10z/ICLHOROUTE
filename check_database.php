<?php
// Debug page to check database connection and employees table
include "db.php";

echo "<h2>Database Connection Check</h2>";
if ($conn) {
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    echo "<p>Database: " . $database . "</p>";
} else {
    echo "<p style='color: red;'>✗ Database connection failed!</p>";
    exit();
}

echo "<h2>Check if employees table exists</h2>";
$tableCheck = "SHOW TABLES LIKE 'employees'";
$result = mysqli_query($conn, $tableCheck);

if (mysqli_num_rows($result) > 0) {
    echo "<p style='color: green;'>✓ Employees table exists!</p>";
    
    echo "<h2>Employees in Database</h2>";
    $query = "SELECT * FROM employees";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>Employee ID</th><th>Name</th><th>Email / Username</th><th>Team</th><th>Created At</th></tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['employee_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . htmlspecialchars(isset($row['email']) ? $row['email'] : (isset($row['username']) ? $row['username'] : '')) . "</td>";
            echo "<td>" . htmlspecialchars($row['team']) . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠ No employees found in the database.</p>";
        echo "<p>Try adding an employee through the employees.php page.</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Employees table does NOT exist!</p>";
    echo "<p>Creating table now...</p>";
    
    $createTable = "CREATE TABLE IF NOT EXISTS employees (
        employee_id VARCHAR(20) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(50) NOT NULL,
        team VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $createTable)) {
        echo "<p style='color: green;'>✓ Employees table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating table: " . mysqli_error($conn) . "</p>";
    }
}

echo "<hr>";
echo "<h2>SQL Query to View Employees</h2>";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
echo "USE drims_database;\n";
echo "SELECT * FROM employees;";
echo "</pre>";

mysqli_close($conn);
?>
