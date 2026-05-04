<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notifications_utils.php';

session_start();

// Auth check
if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId   = $_SESSION['admin']       ?? $_SESSION['employee_id']   ?? '';
$userType = isset($_SESSION['admin']) ? 'admin'                     : 'employee';
$userName = $_SESSION['admin']       ?? $_SESSION['employee_name']  ?? 'Unknown';

// Auto-create table if it doesn't exist
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS document_comments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    document_id  INT NOT NULL,
    user_id      VARCHAR(50)  NOT NULL,
    user_type    VARCHAR(20)  NOT NULL,
    user_name    VARCHAR(100) NOT NULL,
    comment_text TEXT         NOT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_doc (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

header('Content-Type: application/json');

// ── GET: fetch comments for a document ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $docId = (int)($_GET['doc_id'] ?? 0);
    if ($docId <= 0) { echo json_encode([]); exit(); }

    $res = mysqli_query($conn,
        "SELECT id, user_name, user_type, comment_text,
                DATE_FORMAT(created_at,'%b %d, %Y %h:%i %p') AS created_at
         FROM document_comments
         WHERE document_id = $docId
         ORDER BY created_at ASC");

    $comments = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $comments[] = $row;
        }
    }
    echo json_encode($comments);
    exit();
}

// ── POST: add a comment ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $docId  = (int)($body['doc_id']       ?? 0);
    $text   = trim($body['comment_text']  ?? '');

    if ($docId <= 0 || $text === '') {
        http_response_code(400);
        echo json_encode(['error' => 'doc_id and comment_text are required']);
        exit();
    }

    $docIdSafe  = $docId;
    $textEsc    = mysqli_real_escape_string($conn, $text);
    $userIdEsc  = mysqli_real_escape_string($conn, $userId);
    $userTypeEsc= mysqli_real_escape_string($conn, $userType);
    $userNameEsc= mysqli_real_escape_string($conn, $userName);

    $ok = mysqli_query($conn,
        "INSERT INTO document_comments (document_id, user_id, user_type, user_name, comment_text)
         VALUES ($docIdSafe, '$userIdEsc', '$userTypeEsc', '$userNameEsc', '$textEsc')");

    // Notify document owner and last router about the new comment
    if ($ok) {
        $docInfo = mysqli_query($conn,
            "SELECT title, reference_no, uploaded_by_id, uploaded_by_type,
                    routed_by_id, routed_by_type FROM documents WHERE document_id = $docIdSafe");
        if ($docInfo && $doc = mysqli_fetch_assoc($docInfo)) {
            $notifMsg = "$userName commented on '{$doc['title']}' ({$doc['reference_no']}): \"" . mb_strimwidth($text, 0, 80, '...') . "\"";
            $notified = [];
            // Notify uploader if not the commenter
            if (!empty($doc['uploaded_by_id']) &&
                !($doc['uploaded_by_id'] === $userId && $doc['uploaded_by_type'] === $userType)) {
                createNotification($conn, $doc['uploaded_by_id'], $doc['uploaded_by_type'],
                    $docIdSafe, $doc['title'], $doc['reference_no'], 'incoming', $notifMsg);
                $notified[] = $doc['uploaded_by_id'] . '|' . $doc['uploaded_by_type'];
            }
            // Notify last router if not the commenter and not already notified
            if (!empty($doc['routed_by_id']) &&
                !($doc['routed_by_id'] === $userId && $doc['routed_by_type'] === $userType) &&
                !in_array($doc['routed_by_id'] . '|' . $doc['routed_by_type'], $notified)) {
                createNotification($conn, $doc['routed_by_id'], $doc['routed_by_type'],
                    $docIdSafe, $doc['title'], $doc['reference_no'], 'incoming', $notifMsg);
            }
        }
    }

    echo json_encode(['success' => (bool)$ok]);
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
