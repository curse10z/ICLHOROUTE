<?php
error_reporting(0); ini_set('display_errors',0); mysqli_report(MYSQLI_REPORT_OFF);
session_start();
require_once 'db.php';
require_once 'notifications_utils.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$isAdmin  = isset($_SESSION['admin']);
$userId   = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];
$userType = $isAdmin ? 'admin' : 'employee';

createNotificationsTable($conn);

$uidEsc = mysqli_real_escape_string($conn, $userId);
$utEsc  = mysqli_real_escape_string($conn, $userType);

$countRes = mysqli_query($conn, "SELECT COUNT(*) as c FROM notifications
    WHERE user_id = '$uidEsc' AND user_type = '$utEsc' AND is_read = 0");
$count = ($countRes && $row = mysqli_fetch_assoc($countRes)) ? (int)$row['c'] : 0;

$notifRes = mysqli_query($conn, "SELECT notification_id, type, doc_id, doc_title, reference_no, message, is_read, created_at
    FROM notifications
    WHERE user_id = '$uidEsc' AND user_type = '$utEsc'
    ORDER BY created_at DESC LIMIT 20");

$notifications = [];
if ($notifRes) {
    while ($row = mysqli_fetch_assoc($notifRes)) {
        $notifications[] = $row;
    }
}

echo json_encode(['count' => $count, 'notifications' => $notifications]);
