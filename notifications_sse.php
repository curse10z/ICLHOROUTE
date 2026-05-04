<?php
error_reporting(0); ini_set('display_errors',0); mysqli_report(MYSQLI_REPORT_OFF);
session_start();
include 'db.php';
require_once 'notifications_utils.php';

if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    http_response_code(401);
    exit();
}

$isAdmin  = isset($_SESSION['admin']);
$userId   = $isAdmin ? $_SESSION['admin']   : $_SESSION['employee_id'];
$userType = $isAdmin ? 'admin'              : 'employee';

// Release session lock so other requests from the same user are not blocked
session_write_close();

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');  // disable nginx / proxy buffering
header('Connection: keep-alive');

// Clear any output buffers
while (ob_get_level()) ob_end_clean();

createNotificationsTable($conn);

$uidEsc    = mysqli_real_escape_string($conn, $userId);
$utEsc     = mysqli_real_escape_string($conn, $userType);
$lastCount = -1;
$lastMaxId = 0;
$tick      = 0;

// Resolve user's team for overdue checks
$userTeamSSE = '';
if (!$isAdmin) {
    $tRes = mysqli_query($conn, "SELECT team FROM employees WHERE employee_id='$uidEsc' LIMIT 1");
    if ($tRes && $tRow = mysqli_fetch_assoc($tRes)) $userTeamSSE = $tRow['team'];
}

set_time_limit(0);

while (true) {
    if (connection_aborted()) break;

    // Re-ping the DB connection (long-lived process)
    if (!mysqli_ping($conn)) {
        mysqli_close($conn);
        include 'db.php';
        $uidEsc = mysqli_real_escape_string($conn, $userId);
        $utEsc  = mysqli_real_escape_string($conn, $userType);
    }

    $countRes = mysqli_query($conn, "SELECT COUNT(*) AS c, MAX(notification_id) AS mx
        FROM notifications
        WHERE user_id='$uidEsc' AND user_type='$utEsc' AND is_read=0");
    $row      = ($countRes) ? mysqli_fetch_assoc($countRes) : null;
    $count    = $row ? (int)$row['c']  : 0;
    $maxId    = $row ? (int)$row['mx'] : 0;

    if ($count !== $lastCount || $maxId !== $lastMaxId) {
        $notifRes = mysqli_query($conn, "SELECT notification_id, type, doc_id, doc_title, reference_no, message, is_read, created_at
            FROM notifications
            WHERE user_id='$uidEsc' AND user_type='$utEsc'
            ORDER BY created_at DESC LIMIT 20");
        $notifications = [];
        if ($notifRes) while ($r = mysqli_fetch_assoc($notifRes)) $notifications[] = $r;

        $payload = json_encode(['count' => $count, 'notifications' => $notifications]);
        echo "data: $payload\n\n";
        $lastCount = $count;
        $lastMaxId = $maxId;
    } else {
        // Keep-alive every ~30 ticks so proxy/browser doesn't close idle connection
        if ($tick % 15 === 0) echo ": ping\n\n";
    }

    // ── Check for overdue documents every 60 s (30 ticks × 2 s) ──
    if ($tick % 30 === 0) {
        if ($isAdmin) {
            $teamCond = "recipient_team = 'Admin'";
        } else {
            $teamEscSSE = mysqli_real_escape_string($conn, $userTeamSSE);
            $teamCond   = "recipient_team = '$teamEscSSE'";
        }
        $odRes = mysqli_query($conn,
            "SELECT document_id, title, reference_no, route_before FROM documents
             WHERE $teamCond
               AND status IN ('pending','pending for completion')
               AND route_before IS NOT NULL AND route_before < CURDATE()");
        if ($odRes) while ($od = mysqli_fetch_assoc($odRes)) {
            $dId = (int)$od['document_id'];
            $chk = mysqli_query($conn,
                "SELECT notification_id FROM notifications
                 WHERE user_id='$uidEsc' AND user_type='$utEsc' AND type='overdue' AND doc_id=$dId LIMIT 1");
            if ($chk && mysqli_num_rows($chk) === 0) {
                $dueDate = date('F j, Y', strtotime($od['route_before']));
                $msg     = "OVERDUE: '{$od['title']}' ({$od['reference_no']}) was due on $dueDate";
                createNotification($conn, $userId, $userType, $dId, $od['title'], $od['reference_no'], 'overdue', $msg);
            }
        }

        // ── Escalation: 3+ days overdue → notify all admins ──
        if ($isAdmin) {
            $escRes = mysqli_query($conn,
                "SELECT document_id, title, reference_no, route_before, recipient_team FROM documents
                 WHERE status IN ('pending','pending for completion')
                   AND route_before IS NOT NULL
                   AND route_before < CURDATE() - INTERVAL 3 DAY");
            if ($escRes) while ($es = mysqli_fetch_assoc($escRes)) {
                $dId = (int)$es['document_id'];
                $chk = mysqli_query($conn,
                    "SELECT notification_id FROM notifications
                     WHERE user_id='$uidEsc' AND user_type='$utEsc' AND type='escalation' AND doc_id=$dId LIMIT 1");
                if ($chk && mysqli_num_rows($chk) === 0) {
                    $dueDate  = date('F j, Y', strtotime($es['route_before']));
                    $daysLate = max(0, (int)floor((time() - strtotime($es['route_before'])) / 86400));
                    $msg = "ESCALATION: '{$es['title']}' ({$es['reference_no']}) in {$es['recipient_team']} is {$daysLate} days overdue (due $dueDate)";
                    createNotification($conn, $userId, $userType, $dId, $es['title'], $es['reference_no'], 'escalation', $msg);
                }
            }
        }
    }

    if (ob_get_level()) ob_flush();
    flush();

    if (connection_aborted()) break;
    $tick++;
    sleep(2); // check DB every 2 seconds
}
