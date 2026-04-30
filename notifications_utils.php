<?php
function createNotificationsTable($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id         VARCHAR(50)  NOT NULL,
        user_type       VARCHAR(20)  NOT NULL,
        doc_id          INT          DEFAULT NULL,
        doc_title       VARCHAR(255) DEFAULT '',
        reference_no    VARCHAR(100) DEFAULT '',
        type            VARCHAR(20)  NOT NULL,
        message         TEXT,
        is_read         TINYINT(1)   DEFAULT 0,
        created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user   (user_id, user_type),
        INDEX idx_unread (user_id, user_type, is_read)
    )");
}

function createNotification($conn, $userId, $userType, $docId, $docTitle, $refNo, $type, $message) {
    createNotificationsTable($conn);
    $userIdEsc   = mysqli_real_escape_string($conn, $userId);
    $userTypeEsc = mysqli_real_escape_string($conn, $userType);
    $titleEsc    = mysqli_real_escape_string($conn, $docTitle);
    $refEsc      = mysqli_real_escape_string($conn, $refNo);
    $typeEsc     = mysqli_real_escape_string($conn, $type);
    $msgEsc      = mysqli_real_escape_string($conn, $message);
    $docIdVal    = ($docId !== null && $docId > 0) ? (int)$docId : 'NULL';
    mysqli_query($conn, "INSERT INTO notifications (user_id, user_type, doc_id, doc_title, reference_no, type, message)
        VALUES ('$userIdEsc', '$userTypeEsc', $docIdVal, '$titleEsc', '$refEsc', '$typeEsc', '$msgEsc')");
}

function notifyTeam($conn, $team, $docId, $docTitle, $refNo, $type, $message) {
    createNotificationsTable($conn);
    $teamEsc = mysqli_real_escape_string($conn, $team);
    if ($team === 'Admin') {
        $adminRes = mysqli_query($conn, "SELECT username FROM admin LIMIT 1");
        if ($adminRes && $row = mysqli_fetch_assoc($adminRes)) {
            createNotification($conn, $row['username'], 'admin', $docId, $docTitle, $refNo, $type, $message);
        }
    } else {
        $empRes = mysqli_query($conn, "SELECT employee_id FROM employees WHERE team = '$teamEsc'");
        if ($empRes) {
            while ($row = mysqli_fetch_assoc($empRes)) {
                createNotification($conn, $row['employee_id'], 'employee', $docId, $docTitle, $refNo, $type, $message);
            }
        }
    }
}
