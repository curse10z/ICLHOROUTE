<?php
session_start();
include "db.php";

echo "<pre style='font-family:monospace;padding:20px;'>";
echo "<h2>DRIMS - Employee Login Debug</h2>";

if (!$conn) {
    echo "ERROR: No DB connection!\n";
    exit;
}

// Show all employees
$result = mysqli_query($conn, "SELECT employee_id, name, email, team, LEFT(password,20) as pw_preview, LENGTH(password) as pw_len FROM employees ORDER BY employee_id");
if (!$result) {
    echo "ERROR querying employees: " . mysqli_error($conn) . "\n";
} else {
    echo "=== EMPLOYEES IN DATABASE ===\n";
    $count = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $count++;
        $isHashed = preg_match('/^\$2[ayb]\$.{56}$/', $row['pw_preview'] . '...');
        echo "ID: {$row['employee_id']}\n";
        echo "  Name:     {$row['name']}\n";
        echo "  Email:    {$row['email']}\n";
        echo "  Team:     {$row['team']}\n";
        echo "  PW Len:   {$row['pw_len']} chars\n";
        echo "  PW Type:  " . ($isHashed ? "BCRYPT HASH (correct)" : "PLAIN TEXT or CORRUPTED") . "\n";
        echo "  PW Start: {$row['pw_preview']}...\n";
        echo "\n";
    }
    if ($count === 0) {
        echo "NO EMPLOYEES FOUND IN DATABASE!\n";
    }
    echo "Total employees: $count\n\n";
}

// Test a specific login if submitted
if (isset($_POST['test_login'])) {
    $test_id = trim($_POST['test_id']);
    $test_pass = trim($_POST['test_pass']);
    
    echo "=== LOGIN TEST ===\n";
    echo "Testing ID: '$test_id'\n";
    echo "Testing PW: '$test_pass'\n\n";
    
    $stmt = mysqli_prepare($conn, "SELECT * FROM employees WHERE employee_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $test_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $emp = mysqli_fetch_assoc($res);
    
    if (!$emp) {
        echo "RESULT: Employee ID '$test_id' NOT FOUND in employees table.\n";
        echo "Make sure you're using the Employee ID (e.g. 2026-01), not the name or email.\n";
    } else {
        echo "Employee found: {$emp['name']}\n";
        $stored_pw = $emp['password'];
        echo "Stored PW length: " . strlen($stored_pw) . "\n";
        
        if (preg_match('/^\$2[ayb]\\$.{56}$/', $stored_pw)) {
            $match = password_verify($test_pass, $stored_pw);
            echo "PW Type: BCRYPT\n";
            echo "RESULT: " . ($match ? "✅ PASSWORD CORRECT - Login should work!" : "❌ PASSWORD WRONG - Password does not match.") . "\n";
        } else {
            $match = ($test_pass === $stored_pw);
            echo "PW Type: PLAIN TEXT\n";
            echo "RESULT: " . ($match ? "✅ PASSWORD CORRECT" : "❌ PASSWORD WRONG") . "\n";
        }
    }
    echo "\n";
}

echo "</pre>";
?>
<!DOCTYPE html>
<html>
<head><title>Login Debug</title></head>
<body style="font-family:Arial;background:#1a1a2e;color:#eee;padding:20px;">
<h2 style="color:#e94560;">Test Employee Login</h2>
<form method="POST" style="background:#16213e;padding:20px;border-radius:8px;max-width:400px;">
    <div style="margin-bottom:12px;">
        <label>Employee ID (e.g. 2026-01):</label><br>
        <input type="text" name="test_id" value="<?php echo isset($_POST['test_id']) ? htmlspecialchars($_POST['test_id']) : ''; ?>" 
               style="width:100%;padding:8px;margin-top:4px;border-radius:4px;border:1px solid #444;background:#0f3460;color:#eee;">
    </div>
    <div style="margin-bottom:12px;">
        <label>Password:</label><br>
        <input type="text" name="test_pass" value="<?php echo isset($_POST['test_pass']) ? htmlspecialchars($_POST['test_pass']) : ''; ?>"
               style="width:100%;padding:8px;margin-top:4px;border-radius:4px;border:1px solid #444;background:#0f3460;color:#eee;">
    </div>
    <button type="submit" name="test_login" style="background:#e94560;color:white;border:none;padding:10px 20px;border-radius:4px;cursor:pointer;">
        Test Login
    </button>
</form>
<p style="color:#888;font-size:12px;margin-top:20px;">DELETE this file after debugging!</p>
</body>
</html>
