<?php
include 'db.php';
echo "<pre>";
$r = mysqli_query($conn, "SELECT employee_id, name, email, password FROM employees");
if (!$r) { echo "Query failed: " . mysqli_error($conn); exit; }
$rows = mysqli_fetch_all($r, MYSQLI_ASSOC);
if (empty($rows)) {
    echo "NO EMPLOYEES IN DATABASE\n";
} else {
    foreach ($rows as $row) {
        echo "ID: " . $row['employee_id'] . "\n";
        echo "Name: " . $row['name'] . "\n";
        echo "Email: " . $row['email'] . "\n";
        echo "Password type: " . (preg_match('/^\$2[ayb]\$.{56}$/', $row['password']) ? 'bcrypt hash' : 'PLAIN TEXT') . "\n";
        echo "Password value: " . htmlspecialchars(substr($row['password'], 0, 40)) . "...\n";
        echo "---\n";
    }
}
// Also test login logic
echo "\n\nLogin test - if password is '1234' for first employee:\n";
if (!empty($rows)) {
    $emp = $rows[0];
    $testPw = '1234';
    $isBcrypt = preg_match('/^\$2[ayb]\$.{56}$/', $emp['password']);
    if ($isBcrypt) {
        echo "verify('1234', hash) = " . (password_verify($testPw, $emp['password']) ? 'YES MATCH' : 'NO MATCH') . "\n";
    } else {
        echo "plain compare: " . ($testPw === $emp['password'] ? 'YES MATCH' : 'NO MATCH') . "\n";
    }
}
echo "</pre>";
