<?php
error_reporting(0);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);
require_once 'notifications_utils.php';
ob_start();
session_start();
include 'db.php';
ob_end_clean();
header('Content-Type: application/json');
/* calendar_notes.php — multi-event backend */

if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']); exit();
}

$isAdmin  = isset($_SESSION['admin']);
$userId   = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];
$userType = $isAdmin ? 'admin' : 'employee';

// Auto-create multi-event table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS calendar_events (
    event_id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id         VARCHAR(50)  NOT NULL,
    user_type       VARCHAR(20)  NOT NULL,
    event_date      DATE         NOT NULL,
    event_text      VARCHAR(255) NOT NULL,
    event_priority  VARCHAR(20)  NOT NULL DEFAULT 'normal',
    created_by_name VARCHAR(100) NOT NULL DEFAULT '',
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_date (user_id, user_type, event_date)
)");
@mysqli_query($conn, "ALTER TABLE calendar_events ADD COLUMN event_priority VARCHAR(20) NOT NULL DEFAULT 'normal'");
@mysqli_query($conn, "ALTER TABLE calendar_events ADD COLUMN created_by_name VARCHAR(100) NOT NULL DEFAULT ''");

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$uidE   = mysqli_real_escape_string($conn, $userId);
$utE    = mysqli_real_escape_string($conn, $userType);

// ── GET month events ───────────────────────────────────────────────
if ($action === 'month') {
    $year  = (int)($_GET['year']  ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('n'));
    $from  = sprintf('%04d-%02d-01', $year, $month);
    $to    = date('Y-m-t', strtotime($from));
    $res   = mysqli_query($conn, "SELECT event_id, event_date, event_text, event_priority, created_by_name
        FROM calendar_events
        WHERE event_date BETWEEN '$from' AND '$to'
        ORDER BY event_date, event_id");
    $events = [];
    if ($res) while ($r = mysqli_fetch_assoc($res)) {
        $events[$r['event_date']][] = ['id' => (int)$r['event_id'], 'text' => $r['event_text'], 'priority' => $r['event_priority'], 'by' => $r['created_by_name']];
    }
    echo json_encode(['ok' => true, 'events' => $events]);
    exit();
}

// ── GET single day events ──────────────────────────────────────────
if ($action === 'get') {
    $date = $_GET['date'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { echo json_encode(['ok' => false]); exit(); }
    $dE  = mysqli_real_escape_string($conn, $date);
    $res = mysqli_query($conn, "SELECT event_id, event_text, event_priority, created_by_name FROM calendar_events
        WHERE event_date='$dE'
        ORDER BY event_id");
    $events = [];
    if ($res) while ($r = mysqli_fetch_assoc($res))
        $events[] = ['id' => (int)$r['event_id'], 'text' => $r['event_text'], 'priority' => $r['event_priority'], 'by' => $r['created_by_name']];
    echo json_encode(['ok' => true, 'events' => $events]);
    exit();
}

// ── ADD event ─────────────────────────────────────────────────────
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $date     = $_POST['date'] ?? '';
    $text     = trim($_POST['text'] ?? '');
    $allowed  = ['normal','low','important','urgent'];
    $priority = in_array($_POST['priority'] ?? '', $allowed) ? $_POST['priority'] : 'normal';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $text === '') {
        echo json_encode(['ok' => false]); exit();
    }
    $dE    = mysqli_real_escape_string($conn, $date);
    $textE = mysqli_real_escape_string($conn, substr($text, 0, 255));
    $prioE = mysqli_real_escape_string($conn, $priority);

    // Get poster's display name
    $posterName = $userId;
    if ($isAdmin) {
        $nRes = mysqli_query($conn, "SELECT fullname, name FROM admin WHERE username='$uidE' LIMIT 1");
        if ($nRes && $nRow = mysqli_fetch_assoc($nRes))
            $posterName = !empty($nRow['fullname']) ? $nRow['fullname'] : (!empty($nRow['name']) ? $nRow['name'] : $userId);
    } else {
        $posterName = $_SESSION['employee_name'] ?? $userId;
    }
    $nameE = mysqli_real_escape_string($conn, $posterName);

    mysqli_query($conn, "INSERT INTO calendar_events (user_id, user_type, event_date, event_text, event_priority, created_by_name)
        VALUES ('$uidE','$utE','$dE','$textE','$prioE','$nameE')");
    $newId = mysqli_insert_id($conn);

    // Notify ALL users (including poster — acts as confirmation)
    $prioLabels = ['normal'=>'Normal','low'=>'Low','important'=>'Important','urgent'=>'Urgent'];
    $prioLabel  = $prioLabels[$priority] ?? ucfirst($priority);
    $dispDate   = date('F j, Y', strtotime($date));
    $notifMsg   = "$posterName posted a $prioLabel event on $dispDate: \"$text\"";
    $notifRef   = $dispDate;
    // Notify all admins
    $aRes = mysqli_query($conn, "SELECT username FROM admin");
    if ($aRes) while ($ar = mysqli_fetch_assoc($aRes)) {
        createNotification($conn, $ar['username'], 'admin', null, $priority, $notifRef, 'calendar', $notifMsg);
    }
    // Notify all employees
    $eRes = mysqli_query($conn, "SELECT employee_id FROM employees");
    if ($eRes) while ($er = mysqli_fetch_assoc($eRes)) {
        createNotification($conn, $er['employee_id'], 'employee', null, $priority, $notifRef, 'calendar', $notifMsg);
    }

    echo json_encode(['ok' => true, 'event' => ['id' => $newId, 'text' => $text, 'priority' => $priority, 'by' => $posterName]]);
    exit();
}

// ── DELETE event ──────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $evId = (int)($_POST['event_id'] ?? 0);
    if ($evId <= 0) { echo json_encode(['ok' => false]); exit(); }

    // Fetch event details before deleting
    $evRes = mysqli_query($conn, "SELECT event_text, event_priority, event_date, created_by_name FROM calendar_events WHERE event_id=$evId");
    $evRow = ($evRes && $r = mysqli_fetch_assoc($evRes)) ? $r : null;

    mysqli_query($conn, "DELETE FROM calendar_events WHERE event_id=$evId");

    // Notify all users about the deletion
    if ($evRow) {
        $deleterName = $userId;
        if ($isAdmin) {
            $nRes = mysqli_query($conn, "SELECT fullname, name FROM admin WHERE username='$uidE' LIMIT 1");
            if ($nRes && $nRow = mysqli_fetch_assoc($nRes))
                $deleterName = !empty($nRow['fullname']) ? $nRow['fullname'] : (!empty($nRow['name']) ? $nRow['name'] : $userId);
        } else {
            $deleterName = $_SESSION['employee_name'] ?? $userId;
        }
        $dispDate  = date('F j, Y', strtotime($evRow['event_date']));
        $delMsg    = "$deleterName removed an event on $dispDate: \"{$evRow['event_text']}\"";
        $aRes = mysqli_query($conn, "SELECT username FROM admin");
        if ($aRes) while ($ar = mysqli_fetch_assoc($aRes))
            createNotification($conn, $ar['username'], 'admin', null, $evRow['event_priority'], $dispDate, 'calendar', $delMsg);
        $eRes = mysqli_query($conn, "SELECT employee_id FROM employees");
        if ($eRes) while ($er = mysqli_fetch_assoc($eRes))
            createNotification($conn, $er['employee_id'], 'employee', null, $evRow['event_priority'], $dispDate, 'calendar', $delMsg);
    }

    echo json_encode(['ok' => true]);
    exit();
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
