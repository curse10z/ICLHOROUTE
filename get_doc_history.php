<?php
session_start();
include "db.php";

// Auth check
if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$docId = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : 0;
if ($docId <= 0) {
    echo json_encode([]);
    exit();
}

// Ensure document_history table exists
$stmt = mysqli_prepare($conn,
    "CREATE TABLE IF NOT EXISTS document_history (
        history_id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        reference_no VARCHAR(20),
        action_type VARCHAR(50) NOT NULL,
        action_description TEXT,
        old_status VARCHAR(50),
        new_status VARCHAR(50),
        old_team VARCHAR(100),
        new_team VARCHAR(100),
        performed_by_id VARCHAR(20) NOT NULL,
        performed_by_type ENUM('admin','employee') NOT NULL,
        performed_by_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_document_id (document_id),
        INDEX idx_created_at (created_at)
    )"
);
if ($stmt) { mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }

$rows = [];
$sel = mysqli_prepare($conn,
    "SELECT history_id, action_type, action_description,
            old_status, new_status, old_team, new_team,
            performed_by_name, performed_by_type, created_at
     FROM document_history
     WHERE document_id = ?
     ORDER BY created_at ASC"
);
if ($sel) {
    mysqli_stmt_bind_param($sel, 'i', $docId);
    mysqli_stmt_execute($sel);
    $result = mysqli_stmt_get_result($sel);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($sel);
}

echo json_encode($rows);
