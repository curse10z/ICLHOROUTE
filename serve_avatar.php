<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    http_response_code(403); exit();
}

$isAdmin  = isset($_SESSION['admin']);
$userId   = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];

// Allow fetching another user's avatar (e.g. for messages page)
$reqId   = isset($_GET['uid'])   ? trim($_GET['uid'])   : null;
$reqType = isset($_GET['utype']) ? trim($_GET['utype']) : null;

if ($reqId !== null && $reqType !== null) {
    $uidEsc = mysqli_real_escape_string($conn, $reqId);
    if ($reqType === 'admin') {
        $res = mysqli_query($conn, "SELECT profile_pic FROM admin WHERE username = '$uidEsc' LIMIT 1");
    } else {
        $res = mysqli_query($conn, "SELECT profile_pic FROM employees WHERE employee_id = '$uidEsc' LIMIT 1");
    }
} else {
    $uidEsc = mysqli_real_escape_string($conn, $userId);
    if ($isAdmin) {
        $res = mysqli_query($conn, "SELECT profile_pic FROM admin WHERE username = '$uidEsc' LIMIT 1");
    } else {
        $res = mysqli_query($conn, "SELECT profile_pic FROM employees WHERE employee_id = '$uidEsc' LIMIT 1");
    }
}

$row     = ($res && $r = mysqli_fetch_assoc($res)) ? $r : [];
$relPath = $row['profile_pic'] ?? '';

if (empty($relPath)) { http_response_code(404); exit(); }

$absPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

if (!file_exists($absPath)) { http_response_code(404); exit(); }

$ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
$mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
$type = $mime[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $type);
header('Cache-Control: private, max-age=3600');
readfile($absPath);
exit();
