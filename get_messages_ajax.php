<?php
error_reporting(0); ini_set('display_errors',0); mysqli_report(MYSQLI_REPORT_OFF);
session_start();
include "db.php";
require_once 'notifications_utils.php';

// Auth check
if(!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$isAdmin  = isset($_SESSION['admin']);
$userType = $isAdmin ? 'admin' : 'employee';
$userId   = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];
$userName = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_name'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Ensure soft-delete tracking table exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS deleted_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id   VARCHAR(20) NOT NULL,
    user_type ENUM('admin','employee') NOT NULL,
    other_id  VARCHAR(20) NOT NULL,
    other_type ENUM('admin','employee') NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_del_conv (user_id, user_type, other_id, other_type)
)");

$uEsc  = mysqli_real_escape_string($conn, $userId);
$utEsc = mysqli_real_escape_string($conn, $userType);

// ── ACTION: send ─────────────────────────────────────────────────────────────
if($action === 'send'){
    $rid      = mysqli_real_escape_string($conn, trim($_POST['recipient_id']   ?? ''));
    $rtype    = mysqli_real_escape_string($conn, trim($_POST['recipient_type'] ?? ''));
    $rawMsg   = trim($_POST['message'] ?? '');
    $msg      = mysqli_real_escape_string($conn, $rawMsg);

    if(empty($rid) || empty($msg)){
        echo json_encode(['success'=>false,'error'=>'Recipient and message required']); exit();
    }

    $recipientName = '';
    if($rtype === 'admin'){
        $q = mysqli_query($conn,"SELECT username FROM admin WHERE username='$rid'");
        if($r = mysqli_fetch_assoc($q)) $recipientName = $r['username'];
    } else {
        $q = mysqli_query($conn,"SELECT name FROM employees WHERE employee_id='$rid'");
        if($r = mysqli_fetch_assoc($q)) $recipientName = $r['name'];
    }
    if(!$recipientName){ echo json_encode(['success'=>false,'error'=>'Recipient not found']); exit(); }

    $senderName = mysqli_real_escape_string($conn, $userName);
    $sql = "INSERT INTO messages (sender_id,sender_type,sender_name,recipient_id,recipient_type,recipient_name,message)
            VALUES ('$uEsc','$utEsc','$senderName','$rid','$rtype','".mysqli_real_escape_string($conn,$recipientName)."','$msg')";

    if(mysqli_query($conn,$sql)){
        $newId = mysqli_insert_id($conn);
        // Un-hide this conversation if sender had previously deleted it
        mysqli_query($conn,"DELETE FROM deleted_conversations
                            WHERE user_id='$uEsc' AND user_type='$utEsc'
                              AND other_id='$rid'  AND other_type='$rtype'");
        // Notify recipient
        $preview = mb_strlen($rawMsg) > 60 ? mb_substr($rawMsg, 0, 60) . '...' : $rawMsg;
        createNotification($conn, $_POST['recipient_id'], $_POST['recipient_type'],
            null, '', '', 'message', $userName . ': ' . $preview);
        $row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM messages WHERE message_id=$newId"));
        echo json_encode(['success'=>true,'message'=>$row]);
    } else {
        echo json_encode(['success'=>false,'error'=>mysqli_error($conn)]);
    }
    exit();
}

// ── ACTION: poll ─────────────────────────────────────────────────────────────
if($action === 'poll'){
    $chatId   = mysqli_real_escape_string($conn, $_GET['chat_id']   ?? '');
    $chatType = mysqli_real_escape_string($conn, $_GET['chat_type'] ?? '');
    $afterId  = (int)($_GET['after_id'] ?? 0);

    $response = ['messages'=>[], 'conversations'=>[], 'unread_total'=>0];

    // New messages in active chat
    if($chatId && $chatType){
        $res = mysqli_query($conn,"SELECT * FROM messages
            WHERE message_id > $afterId
              AND ((sender_id='$uEsc' AND sender_type='$utEsc' AND recipient_id='$chatId' AND recipient_type='$chatType')
                OR (recipient_id='$uEsc' AND recipient_type='$utEsc' AND sender_id='$chatId' AND sender_type='$chatType'))
            ORDER BY created_at ASC");
        while($row = mysqli_fetch_assoc($res)) $response['messages'][] = $row;

        // Mark as read
        mysqli_query($conn,"UPDATE messages SET is_read=1
            WHERE recipient_id='$uEsc' AND recipient_type='$utEsc'
              AND sender_id='$chatId'  AND sender_type='$chatType' AND is_read=0");
    }

    // Conversation sidebar — exclude soft-deleted convos
    // A convo reappears automatically if a new message arrives AFTER the deletion timestamp
    $convQ = "SELECT
        CASE WHEN sender_id='$uEsc' AND sender_type='$utEsc' THEN recipient_id   ELSE sender_id   END AS other_id,
        CASE WHEN sender_id='$uEsc' AND sender_type='$utEsc' THEN recipient_type ELSE sender_type END AS other_type,
        CASE WHEN sender_id='$uEsc' AND sender_type='$utEsc' THEN recipient_name ELSE sender_name END AS other_name,
        MAX(message_id) AS last_id,
        MAX(created_at) AS last_time,
        SUM(CASE WHEN recipient_id='$uEsc' AND recipient_type='$utEsc' AND is_read=0 THEN 1 ELSE 0 END) AS unread
    FROM messages
    WHERE (sender_id='$uEsc' AND sender_type='$utEsc')
       OR (recipient_id='$uEsc' AND recipient_type='$utEsc')
    GROUP BY other_id, other_type, other_name
    HAVING NOT EXISTS (
        SELECT 1 FROM deleted_conversations dc
        WHERE dc.user_id='$uEsc' AND dc.user_type='$utEsc'
          AND dc.other_id  = other_id
          AND dc.other_type= other_type
          AND dc.deleted_at >= MAX(messages.created_at)
    )
    ORDER BY last_time DESC";

    $convRes = mysqli_query($conn, $convQ);
    while($conv = mysqli_fetch_assoc($convRes)){
        $lastRow = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT message, created_at FROM messages WHERE message_id=".(int)$conv['last_id']));
        $response['conversations'][] = [
            'user_id'           => $conv['other_id'],
            'user_type'         => $conv['other_type'],
            'user_name'         => $conv['other_name'],
            'last_message'      => $lastRow['message']    ?? '',
            'last_message_time' => $lastRow['created_at'] ?? '',
            'unread_count'      => (int)$conv['unread'],
        ];
    }

    $ub = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM messages WHERE recipient_id='$uEsc' AND recipient_type='$utEsc' AND is_read=0"));
    $response['unread_total'] = (int)($ub['c'] ?? 0);

    echo json_encode($response);
    exit();
}

// ── ACTION: delete (soft-delete — only hides from THIS user's view) ──────────
if($action === 'delete'){
    $convId   = mysqli_real_escape_string($conn, trim($_POST['conv_id']   ?? ''));
    $convType = mysqli_real_escape_string($conn, trim($_POST['conv_type'] ?? ''));

    if(empty($convId) || empty($convType)){
        echo json_encode(['success'=>false,'error'=>'Missing parameters']); exit();
    }
    if($convId === $userId && $convType === $userType){
        echo json_encode(['success'=>false,'error'=>'Invalid operation']); exit();
    }

    // Upsert: insert or refresh the deleted_at timestamp
    $ins = "INSERT INTO deleted_conversations (user_id, user_type, other_id, other_type)
            VALUES ('$uEsc','$utEsc','$convId','$convType')
            ON DUPLICATE KEY UPDATE deleted_at = CURRENT_TIMESTAMP";

    if(mysqli_query($conn, $ins)){
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'error'=>mysqli_error($conn)]);
    }
    exit();
}

echo json_encode(['error'=>'Unknown action']);
