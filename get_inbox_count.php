<?php
error_reporting(0); ini_set('display_errors', 0); mysqli_report(MYSQLI_REPORT_OFF);
session_start();
include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    echo json_encode(['count' => 0, 'max_id' => 0]); exit;
}

$isAdmin  = isset($_SESSION['admin']);
$userId   = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];
$userType = $isAdmin ? 'admin' : 'employee';
$uidEsc   = mysqli_real_escape_string($conn, $userId);
$utEsc    = mysqli_real_escape_string($conn, $userType);
$type     = in_array($_GET['type'] ?? '', ['incoming', 'outgoing']) ? $_GET['type'] : 'incoming';

$userTeam = null;
if (!$isAdmin) {
    $tRes = mysqli_query($conn, "SELECT team FROM employees WHERE employee_id='$uidEsc' LIMIT 1");
    if ($tRes && $row = mysqli_fetch_assoc($tRes)) $userTeam = $row['team'];
}

if ($type === 'outgoing') {
    $where = "(routed_by_id='$uidEsc' AND routed_by_type='$utEsc')
              OR (routed_by_id IS NULL AND uploaded_by_id='$uidEsc' AND uploaded_by_type='$utEsc')";
} else {
    $notSelf = "NOT ((routed_by_id='$uidEsc' AND routed_by_type='$utEsc')
                OR (routed_by_id IS NULL AND uploaded_by_id='$uidEsc' AND uploaded_by_type='$utEsc'))";
    if ($isAdmin) {
        $where = "recipient_team='Admin' AND $notSelf";
    } else {
        $teamEsc = mysqli_real_escape_string($conn, $userTeam ?? '');
        $where   = "recipient_team='$teamEsc' AND $notSelf";
    }
}

$res = mysqli_query($conn, "SELECT COUNT(*) AS c, COALESCE(MAX(document_id),0) AS mx FROM documents WHERE $where");
$row = $res ? mysqli_fetch_assoc($res) : null;
echo json_encode(['count' => (int)($row['c'] ?? 0), 'max_id' => (int)($row['mx'] ?? 0)]);
