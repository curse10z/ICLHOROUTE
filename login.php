<?php
session_start();
include "db.php";

if(isset($_POST['login'])){
    $login_id = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = trim($_POST['password']);

    // First, check if it's an admin
    $adminQuery = mysqli_query($conn, "SELECT * FROM admin WHERE username='$login_id'");
    $admin = mysqli_fetch_assoc($adminQuery);

    if($admin && $password === $admin['password']){
        // It's an admin
        $_SESSION['admin'] = $admin['username'];
        $_SESSION['user_type'] = 'admin';
        header("Location: dashboard.php");
        exit();
    } else {
        // Check if it's an employee (using employee_id)
        $columnCheck = "SHOW COLUMNS FROM employees LIKE 'email'";
        $colResult = mysqli_query($conn, $columnCheck);
        $hasEmailColumn = ($colResult && mysqli_num_rows($colResult) > 0);
        
        $employeeQuery = mysqli_query($conn, "SELECT * FROM employees WHERE employee_id='$login_id'");
        $employee = mysqli_fetch_assoc($employeeQuery);

        if($employee && $password === $employee['password']){
            // It's an employee
            $_SESSION['employee_id'] = $employee['employee_id'];
            $_SESSION['employee_name'] = $employee['name'];
            $_SESSION['employee_email'] = $hasEmailColumn ? $employee['email'] : (isset($employee['username']) ? $employee['username'] : '');
            $_SESSION['user_type'] = 'employee';
            header("Location: employee_dashboard.php");
            exit();
        } else {
            $error = "Invalid ID/Username or Password!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<link rel="stylesheet" type="text/css" href="/ICLHO_Route/style.css">
</head>
<body>
        <div class="top-bar">
            <img src="/ICLHO_Route/ICLOGO.jpg" alt="Lo" class="top-bar-logo">
            <div class="top-bar-content">
                <div class="top-bar-title">DRIMS</div>
                <div class="top-bar-desc">Document Route Internal Management System</div>
            </div>
        </div>
    <div class="container">
        <div class="login-box">
            <h2>LOGIN</h2>
            <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Admin Username / Employee ID" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">LOGIN</button>
            </form>
            <p style="text-align: center; margin-top: 15px; color: #666; font-size: 14px;">
                Admin: Use your username<br>
                Employee: Use your Employee ID (e.g., 2026-01)
            </p>
        </div>
    </div>
</body>
</html>
