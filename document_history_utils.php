<?php
/**
 * Document History Utility Functions
 * Provides logging functionality for document audit trail
 */

/**
 * Log a document action to the history table
 * 
 * @param mysqli $conn Database connection
 * @param int $documentId Document ID
 * @param string $actionType Type of action (created, status_changed, routed, viewed, downloaded, edited, deleted)
 * @param string $actionDescription Human-readable description of the action
 * @param string $performedById User ID who performed the action
 * @param string $performedByType User type (admin or employee)
 * @param string $performedByName User name
 * @param string|null $oldStatus Previous status (for status changes)
 * @param string|null $newStatus New status (for status changes)
 * @param string|null $oldTeam Previous team (for routing)
 * @param string|null $newTeam New team (for routing)
 * @return bool Success status
 */
function logDocumentHistory(
    $conn, 
    $documentId, 
    $actionType, 
    $actionDescription, 
    $performedById, 
    $performedByType, 
    $performedByName,
    $oldStatus = null,
    $newStatus = null,
    $oldTeam = null,
    $newTeam = null
) {
    // Get reference number for the document
    $refStmt = mysqli_prepare($conn, "SELECT reference_no FROM documents WHERE document_id = ?");
    mysqli_stmt_bind_param($refStmt, "i", $documentId);
    mysqli_stmt_execute($refStmt);
    $refResult = mysqli_stmt_get_result($refStmt);
    $referenceNo = null;
    if ($refResult && $row = mysqli_fetch_assoc($refResult)) {
        $referenceNo = $row['reference_no'];
    }
    mysqli_stmt_close($refStmt);
    
    // Prepare the insert statement
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO document_history 
        (document_id, reference_no, action_type, action_description, old_status, new_status, old_team, new_team, performed_by_id, performed_by_type, performed_by_name) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    if (!$stmt) {
        error_log("Failed to prepare document history statement: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "issssssssss", 
        $documentId,
        $referenceNo,
        $actionType,
        $actionDescription,
        $oldStatus,
        $newStatus,
        $oldTeam,
        $newTeam,
        $performedById,
        $performedByType,
        $performedByName
    );
    
    $result = mysqli_stmt_execute($stmt);
    
    if (!$result) {
        error_log("Failed to log document history: " . mysqli_error($conn));
    }
    
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * Get document history for a specific document
 * 
 * @param mysqli $conn Database connection
 * @param int $documentId Document ID
 * @param int $limit Maximum number of records to return (default: 50)
 * @return array Array of history records
 */
function getDocumentHistory($conn, $documentId, $limit = 50) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM document_history WHERE document_id = ? ORDER BY created_at DESC LIMIT ?");
    mysqli_stmt_bind_param($stmt, "ii", $documentId, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $history = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
    return $history;
}

/**
 * Get recent history across all documents
 * 
 * @param mysqli $conn Database connection
 * @param int $limit Maximum number of records to return (default: 100)
 * @param string|null $actionType Filter by action type (optional)
 * @return array Array of history records
 */
function getRecentHistory($conn, $limit = 100, $actionType = null) {
    if ($actionType) {
        $stmt = mysqli_prepare($conn, "SELECT h.*, d.title as document_title 
                  FROM document_history h
                  LEFT JOIN documents d ON h.document_id = d.document_id
                  WHERE h.action_type = ?
                  ORDER BY h.created_at DESC LIMIT ?");
        mysqli_stmt_bind_param($stmt, "si", $actionType, $limit);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT h.*, d.title as document_title 
                  FROM document_history h
                  LEFT JOIN documents d ON h.document_id = d.document_id
                  ORDER BY h.created_at DESC LIMIT ?");
        mysqli_stmt_bind_param($stmt, "i", $limit);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $history = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
    return $history;
}

/**
 * Create document_history table if it doesn't exist
 * 
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function createDocumentHistoryTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS document_history (
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
        performed_by_type ENUM('admin', 'employee') NOT NULL,
        performed_by_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_document_id (document_id),
        INDEX idx_created_at (created_at),
        INDEX idx_action_type (action_type),
        INDEX idx_performed_by (performed_by_id, performed_by_type)
    )";
    
    return mysqli_query($conn, $sql);
}
?>
