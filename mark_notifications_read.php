<?php
error_reporting(0); ini_set('display_errors',0); mysqli_report(MYSQLI_REPORT_OFF);
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$isAdmin  = isset($_SESSION['admin']);
$userId   = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];
$userType = $isAdmin ? 'admin' : 'employee';

$uidEsc = mysqli_real_escape_string($conn, $userId);
$utEsc  = mysqli_real_escape_string($conn, $userType);

$nid = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if ($nid > 0) {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1
        WHERE notification_id = $nid AND user_id = '$uidEsc' AND user_type = '$utEsc'");
} else {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1
        WHERE user_id = '$uidEsc' AND user_type = '$utEsc' AND is_read = 0");
}

echo json_encode(['success' => true]);
