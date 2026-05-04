<?php
function createAuditTable($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS audit_logs (
        log_id      INT AUTO_INCREMENT PRIMARY KEY,
        user_id     VARCHAR(50)  NOT NULL,
        user_type   VARCHAR(20)  NOT NULL,
        user_name   VARCHAR(100) NOT NULL,
        action      VARCHAR(50)  NOT NULL,
        module      VARCHAR(50)  NOT NULL,
        description TEXT,
        ip_address  VARCHAR(45)  DEFAULT NULL,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user    (user_id, user_type),
        INDEX idx_action  (action),
        INDEX idx_module  (module),
        INDEX idx_created (created_at)
    )");
}

function logAudit($conn, $userId, $userType, $userName, $action, $module, $description) {
    createAuditTable($conn);
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
    $uidE    = mysqli_real_escape_string($conn, $userId);
    $utE     = mysqli_real_escape_string($conn, $userType);
    $unE     = mysqli_real_escape_string($conn, $userName);
    $actE    = mysqli_real_escape_string($conn, $action);
    $modE    = mysqli_real_escape_string($conn, $module);
    $descE   = mysqli_real_escape_string($conn, $description);
    $ipE     = mysqli_real_escape_string($conn, $ip);
    mysqli_query($conn, "INSERT INTO audit_logs (user_id, user_type, user_name, action, module, description, ip_address)
        VALUES ('$uidE', '$utE', '$unE', '$actE', '$modE', '$descE', '$ipE')");
}
