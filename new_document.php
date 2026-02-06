<?php
session_start();
include "db.php";

// Protect page from unauthorized access
if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

$isAdmin = isset($_SESSION['admin']);
$userType = $isAdmin ? 'admin' : 'employee';
$userId = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];
$userName = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_name'];

// Create documents table if it doesn't exist
$createTable = "CREATE TABLE IF NOT EXISTS documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    document_type VARCHAR(50) NOT NULL DEFAULT 'letter',
    originating_team VARCHAR(100) NOT NULL,
    remarks TEXT,
    route_before DATE NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    uploaded_by_id VARCHAR(20) NOT NULL,
    uploaded_by_type ENUM('admin', 'employee') NOT NULL,
    uploaded_by_name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    route_type VARCHAR(50) DEFAULT 'Internal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_reference_no (reference_no),
    INDEX idx_status (status),
    INDEX idx_route_before (route_before),
    INDEX idx_uploaded_by (uploaded_by_id, uploaded_by_type)
)";
mysqli_query($conn, $createTable);

// Add route_type column if it doesn't exist
$rtCol = @mysqli_query($conn, "SHOW COLUMNS FROM documents LIKE 'route_type'");
if ($rtCol && mysqli_num_rows($rtCol) === 0) {
    @mysqli_query($conn, "ALTER TABLE documents ADD COLUMN route_type VARCHAR(50) DEFAULT 'Internal' AFTER status");
}

// Add reference_no to existing table if missing
$cols = @mysqli_query($conn, "SHOW COLUMNS FROM documents LIKE 'reference_no'");
if ($cols && mysqli_num_rows($cols) === 0) {
    mysqli_query($conn, "ALTER TABLE documents ADD COLUMN reference_no VARCHAR(20) NULL AFTER document_id");
    mysqli_query($conn, "ALTER TABLE documents ADD UNIQUE KEY uk_reference_no (reference_no)");
    // Backfill existing rows with DOC-000, DOC-001, ...
    $res = mysqli_query($conn, "SELECT document_id FROM documents ORDER BY document_id");
    $n = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $ref = 'DOC-' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
        $refEsc = mysqli_real_escape_string($conn, $ref);
        mysqli_query($conn, "UPDATE documents SET reference_no = '$refEsc' WHERE document_id = " . (int)$row['document_id']);
        $n++;
    }
    mysqli_query($conn, "ALTER TABLE documents MODIFY reference_no VARCHAR(20) NOT NULL");
}

// Table for additional files (document has first file in file_path/file_name; 2nd and 3rd go here)
$createDocFiles = "CREATE TABLE IF NOT EXISTS document_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    sort_order TINYINT DEFAULT 0
)";
mysqli_query($conn, $createDocFiles);

// Ensure uploads directory exists
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Allowed file types
$allowedTypes = [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
    'txt', 'rtf', 'odt', 'ods', 'odp'
];
$maxFileSize = 10 * 1024 * 1024; // 10 MB

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_document'])) {
    $title = trim($_POST['title'] ?? '');
    $documentType = trim($_POST['document_type'] ?? '');
    $originatingTeam = trim($_POST['originating_team'] ?? '');
    $recipientTeam = trim($_POST['recipient_team'] ?? '');
    $routeType = trim($_POST['route_type'] ?? 'Internal');
    $remarks = trim($_POST['remarks'] ?? '');
    $routeBefore = trim($_POST['route_before'] ?? '');

    // Collect files from dynamic rows (name="document_file[]")
    $uploadedFiles = [];
    if (!empty($_FILES['document_file']['name'])) {
        $names = $_FILES['document_file']['name'];
        if (!is_array($names)) {
            $names = [$names];
            $_FILES['document_file']['type'] = [$_FILES['document_file']['type']];
            $_FILES['document_file']['tmp_name'] = [$_FILES['document_file']['tmp_name']];
            $_FILES['document_file']['error'] = [$_FILES['document_file']['error']];
            $_FILES['document_file']['size'] = [$_FILES['document_file']['size']];
        }
        foreach ($names as $i => $name) {
            if (!empty($name) && (int)$_FILES['document_file']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $uploadedFiles[] = [
                    'name'     => $_FILES['document_file']['name'][$i],
                    'type'     => $_FILES['document_file']['type'][$i],
                    'tmp_name' => $_FILES['document_file']['tmp_name'][$i],
                    'error'    => $_FILES['document_file']['error'][$i],
                    'size'     => $_FILES['document_file']['size'][$i],
                ];
            }
        }
    }

    // Validation
    if (empty($title)) {
        $error = 'Document Title is required.';
    } elseif (empty($documentType)) {
        $error = 'Please select a Document Type.';
    } elseif (count($uploadedFiles) === 0) {
        $error = 'Please add at least one file (up to 3).';
    } elseif (count($uploadedFiles) > 3) {
        $error = 'Maximum 3 files allowed.';
    } else {
        if ($routeBefore && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $routeBefore)) {
            $error = 'Route Before must be a valid date (YYYY-MM-DD).';
        } else {
            $fileErrors = [];
            foreach ($uploadedFiles as $idx => $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $fileErrors[] = 'File ' . ($idx + 1) . ' upload failed.';
                } elseif ($file['size'] > $maxFileSize) {
                    $fileErrors[] = 'File ' . ($idx + 1) . ' must be 10 MB or less.';
                } else {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedTypes)) {
                        $fileErrors[] = 'File ' . ($idx + 1) . ' type not allowed.';
                    }
                }
            }
            if (!empty($fileErrors)) {
                $error = implode(' ', $fileErrors);
            } else {
                $storedPaths = [];
                $ok = true;
                foreach ($uploadedFiles as $file) {
                    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
                    $storedName = date('Ymd_His') . '_' . uniqid() . '_' . $safeName;
                    $targetPath = $uploadDir . $storedName;
                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $error = 'Failed to save uploaded file. Check folder permissions.';
                        foreach ($storedPaths as $p) @unlink($p);
                        $ok = false;
                        break;
                    }
                    $storedPaths[] = $targetPath;
                }
                if ($ok) {
                    $first = $uploadedFiles[0];
                    $firstStored = 'uploads/' . basename($storedPaths[0]);
                    $titleEsc = mysqli_real_escape_string($conn, $title);
                    $documentTypeEsc = mysqli_real_escape_string($conn, $documentType);
                    $originatingTeamEsc = $originatingTeam ? "'" . mysqli_real_escape_string($conn, $originatingTeam) . "'" : 'NULL';
                    $recipientTeamEsc = $recipientTeam ? "'" . mysqli_real_escape_string($conn, $recipientTeam) . "'" : 'NULL';
                    $remarksEsc = mysqli_real_escape_string($conn, $remarks);
                    $routeBeforeEsc = $routeBefore ? "'" . mysqli_real_escape_string($conn, $routeBefore) . "'" : 'NULL';
                    $filePathEsc = mysqli_real_escape_string($conn, $firstStored);
                    $fileNameEsc = mysqli_real_escape_string($conn, $first['name']);
                    $userIdEsc = mysqli_real_escape_string($conn, $userId);
                    $userTypeEsc = mysqli_real_escape_string($conn, $userType);
                    $userNameEsc = mysqli_real_escape_string($conn, $userName);

                    // Next reference no: DOC-000, DOC-001, ...
                    $refRes = mysqli_query($conn, "SELECT reference_no FROM documents ORDER BY document_id DESC LIMIT 1");
                    $nextNum = 0;
                    if ($refRes && ($row = mysqli_fetch_assoc($refRes)) && !empty($row['reference_no'])) {
                        if (preg_match('/^DOC-(\d+)$/', $row['reference_no'], $m)) {
                            $nextNum = (int)$m[1] + 1;
                        }
                    }
                    $referenceNo = 'DOC-' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
                    $referenceNoEsc = mysqli_real_escape_string($conn, $referenceNo);
                    $routeTypeEsc = mysqli_real_escape_string($conn, $routeType);

                    $sql = "INSERT INTO documents (reference_no, title, document_type, originating_team, recipient_team, remarks, route_before, route_type, file_path, file_name, uploaded_by_id, uploaded_by_type, uploaded_by_name, status) 
                            VALUES ('$referenceNoEsc', '$titleEsc', '$documentTypeEsc', $originatingTeamEsc, $recipientTeamEsc, '$remarksEsc', $routeBeforeEsc, '$routeTypeEsc', '$filePathEsc', '$fileNameEsc', '$userIdEsc', '$userTypeEsc', '$userNameEsc', 'pending')";
                    if (mysqli_query($conn, $sql)) {
                        $docId = mysqli_insert_id($conn);
                        for ($i = 1; $i < count($uploadedFiles); $i++) {
                            $f = $uploadedFiles[$i];
                            $path = 'uploads/' . basename($storedPaths[$i]);
                            $pathEsc = mysqli_real_escape_string($conn, $path);
                            $nameEsc = mysqli_real_escape_string($conn, $f['name']);
                            mysqli_query($conn, "INSERT INTO document_files (document_id, file_path, file_name, sort_order) VALUES ($docId, '$pathEsc', '$nameEsc', $i)");
                        }
                        $success = 'Document uploaded successfully! Reference: ' . htmlspecialchars($referenceNo) . '.';
                        $_POST = [];
                        $_FILES = [];
                    } else {
                        $error = 'Failed to save document record: ' . mysqli_error($conn);
                        foreach ($storedPaths as $p) @unlink($p);
                    }
                }
            }
        }
    }
}

// Allow NULL for route_before and originating_team (optional fields)
@mysqli_query($conn, "ALTER TABLE documents MODIFY route_before DATE NULL");
@mysqli_query($conn, "ALTER TABLE documents MODIFY originating_team VARCHAR(100) NULL");

// Add recipient_team so documents can be routed to a team (only that team sees it on dashboard)
$rc = @mysqli_query($conn, "SHOW COLUMNS FROM documents LIKE 'recipient_team'");
if ($rc && mysqli_num_rows($rc) === 0) {
    mysqli_query($conn, "ALTER TABLE documents ADD COLUMN recipient_team VARCHAR(100) NULL AFTER originating_team");
}
// Default status for new docs: pending (user can change to completed, pending for completion, reverted)
@mysqli_query($conn, "ALTER TABLE documents MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
@mysqli_query($conn, "UPDATE documents SET status = 'pending' WHERE status = 'new' OR status = ''");

// Create teams table if it doesn't exist (same as in employees.php)
$createTeamsTable = "CREATE TABLE IF NOT EXISTS teams (
    team_id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $createTeamsTable);

// Insert default teams if table is empty
$checkTeams = "SELECT COUNT(*) as count FROM teams";
$teamsCheckResult = mysqli_query($conn, $checkTeams);
if ($teamsCheckResult) {
    $teamsCheckRow = mysqli_fetch_assoc($teamsCheckResult);
    if ($teamsCheckRow['count'] == 0) {
        $insertTeams = "INSERT INTO teams (team_name, description) VALUES
            ('Admin', 'Administrative Team'),
            ('Frontdesk', 'Front Desk Operations'),
            ('Technical', 'Technical Support Team'),
            ('Survey', 'Survey and Assessment Team'),
            ('TXZ', 'TXZ Department'),
            ('Atty. Peter', 'Legal - Atty. Peter'),
            ('OV', 'Office of the Vice'),
            ('Eviction and Dismantling', 'Eviction and Dismantling Operations'),
            ('Legal Team', 'Legal Department'),
            ('HHRO', 'Human Resources and Housing Operations')";
        mysqli_query($conn, $insertTeams);
    }
}

// Fetch all teams from teams table (not just teams with employees)
$teamsResult = mysqli_query($conn, "SELECT team_name FROM teams ORDER BY team_name ASC");
$teamOptions = [];
while ($tr = mysqli_fetch_assoc($teamsResult)) {
    $teamOptions[] = $tr['team_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Document - DRIMS</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
    /* Document Upload page: inline. Slightly bigger overall (second pass). */
    body.new-document-page .nd-page-header { max-width: 960px; margin: 0 auto; padding: 36px 28px 24px; }
    body.new-document-page .nd-title { color: #2d6cdf; font-size: 2.25rem; font-weight: 600; margin: 0 0 12px 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    body.new-document-page .nd-subtitle { color: #64748b; font-size: 1.05rem; margin: 0; line-height: 1.5; }
    body.new-document-page .dashboard-content.document-upload-dashboard { max-width: 960px; margin: 36px auto 56px; padding: 36px 44px 44px; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.06); background: #fff; }
    body.new-document-page .document-upload-dashboard .alert { margin: 0 0 24px 0; padding: 16px 20px; border-radius: 8px; font-size: 1rem; }
    body.new-document-page .document-upload-dashboard .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    body.new-document-page .document-upload-dashboard .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    body.new-document-page .document-upload-dashboard .nd-form-card { background: #fafbfc; border: 1px solid #e8ecf1; border-radius: 10px; padding: 32px 36px 36px; margin-top: 16px; }
    body.new-document-page .nd-section-head { margin-bottom: 32px; }
    body.new-document-page .document-upload-dashboard .nd-form-card .upload-card-title { color: #1e293b; font-size: 1.5rem; font-weight: 600; margin: 0 0 12px 0; text-align: left; }
    body.new-document-page .document-upload-dashboard .nd-form-card .upload-card-desc { color: #64748b; font-size: 1.05rem; margin: 0; line-height: 1.5; text-align: left; }
    body.new-document-page .document-upload-dashboard .document-upload-form .form-row { margin-bottom: 24px; }
    body.new-document-page .document-upload-dashboard .document-upload-form .form-group label { margin-bottom: 12px; font-size: 16px; }
    body.new-document-page .document-upload-dashboard .form-help { margin-top: 10px; font-size: 0.9rem; color: #64748b; line-height: 1.4; }
    body.new-document-page .document-upload-dashboard .document-upload-form .form-group input[type="text"],
    body.new-document-page .document-upload-dashboard .document-upload-form .form-group input[type="date"],
    body.new-document-page .document-upload-dashboard .document-upload-form .form-group select { min-height: 56px; height: 56px; padding: 16px 20px; font-size: 16px; border-radius: 8px; box-sizing: border-box; width: 100%; border: 2px solid #e0e6ed; background: #fff; color: #333; }
    body.new-document-page .document-upload-dashboard .document-upload-form .input-date-wrap .input-with-icon { min-height: 56px; height: 56px; padding: 16px 20px; padding-right: 48px; box-sizing: border-box; width: 100%; border: 2px solid #e0e6ed; font-size: 16px; }
    body.new-document-page .document-upload-dashboard .document-upload-form .form-group textarea { min-height: 144px; padding: 16px 20px; font-size: 16px; border-radius: 8px; box-sizing: border-box; width: 100%; border: 2px solid #e0e6ed; resize: vertical; }
    body.new-document-page .document-upload-dashboard .form-actions-upload { margin-top: 32px; padding-top: 32px; border-top: 1px solid #e2e8f0; }
    body.new-document-page .document-upload-dashboard .nd-dropzone { border: 1px dashed #cbd5e1; background: #fff; border-radius: 10px; padding: 36px; }
    body.new-document-page .document-upload-dashboard .nd-dropzone:hover,
    body.new-document-page .document-upload-dashboard .nd-dropzone.dragover { border-color: #2d6cdf; background: #f8fafc; }
    body.new-document-page .document-upload-dashboard .file-upload-selected { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 16px 20px; }
    body.new-document-page .document-upload-dashboard .btn-submit-upload { box-shadow: 0 1px 3px rgba(45,108,223,0.2); padding: 18px 36px; font-size: 17px; }
    body.new-document-page .document-upload-dashboard .btn-cancel-upload { padding: 16px 32px; font-size: 17px; }
    body.new-document-page .document-upload-dashboard .hidden { display: none !important; }
    /* Single row: one multiple file input + chosen files list with x to remove */
    .nd-file-slot { position: relative; padding: 18px 20px; border: 1px dashed #cbd5e1; border-radius: 8px; background: #fff; cursor: pointer; transition: border-color 0.2s, background 0.2s; }
    .nd-file-slot:hover, .nd-file-slot.dragover { border-color: #2d6cdf; background: #f8fafc; }
    .nd-file-slot .nd-file-input { position: absolute; width: 0; height: 0; opacity: 0; pointer-events: none; }
    .nd-file-slot .nd-file-placeholder { color: #64748b; font-size: 15px; display: block; }
    .nd-file-slot .nd-file-chosen-list { display: flex; flex-direction: column; gap: 8px; margin-top: 0; }
    .nd-file-slot .nd-file-chosen-list.hidden { display: none !important; }
    .nd-file-slot .nd-file-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 6px; background: #f0f9ff; border: 1px solid #bae6fd; }
    .nd-file-slot .nd-file-item .nd-file-name { flex: 1; font-size: 15px; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .nd-file-slot .nd-file-remove { width: 28px; height: 28px; flex-shrink: 0; border: none; background: #fee2e2; color: #991b1b; font-size: 18px; line-height: 1; cursor: pointer; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
    .nd-file-slot .nd-file-remove:hover { background: #fecaca; }
    </style>
</head>
<body class="new-document-page">
    <div class="top-bar">
        <img src="/ICLHO_Route/ICLOGO.jpg" alt="Logo" class="top-bar-logo">
        <div class="top-bar-content">
            <div class="top-bar-title">DRIMS</div>
            <div class="top-bar-desc">Document Route Internal Management System</div>
        </div>
    </div>

    <div class="menu-button-container">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <div class="sidebar hidden" id="sidebar">
        <div class="sidebar-header">
            <h3><?php echo $isAdmin ? 'Admin' : 'Employee'; ?> Menu</h3>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo $isAdmin ? 'dashboard.php' : 'employee_dashboard.php'; ?>" class="nav-item">
                <span class="nav-icon">🏠</span>
                <span class="nav-text">Dashboard</span>
            </a>

            <div class="nav-item nav-item-parent active" id="inboxMenu">
                <div class="nav-item-header">
                    <span class="nav-icon">📬</span>
                    <span class="nav-text">Routing Tray</span>
                    <span class="nav-arrow">▼</span>
                </div>
                <div class="nav-submenu" id="inboxSubmenu">
                    <a href="inbox.php?type=new" class="nav-subitem">
                        <span class="nav-icon">🆕</span>
                        <span class="nav-text">New</span>
                    </a>
                    <a href="inbox.php?type=incoming" class="nav-subitem">
                        <span class="nav-icon">📥</span>
                        <span class="nav-text">Incoming Routed Documents</span>
                    </a>
                    <a href="inbox.php?type=outgoing" class="nav-subitem">
                        <span class="nav-icon">📤</span>
                        <span class="nav-text">Outgoing Routed</span>
                    </a>
                    <a href="messages.php" class="nav-subitem">
                        <span class="nav-icon">💬</span>
                        <span class="nav-text">Inbox</span>
                    </a>
                </div>
            </div>

            <?php if ($isAdmin): ?>
                <a href="file_management.php" class="nav-item">
                    <span class="nav-icon">📄</span>
                    <span class="nav-text">File Management</span>
                </a>
                <a href="new_document.php" class="nav-item active">
                    <span class="nav-icon">📤</span>
                    <span class="nav-text">Document Upload</span>
                </a>
                <a href="#" class="nav-item">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Routes</span>
                </a>
                <a href="employees.php" class="nav-item">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Employees</span>
                </a>
            <?php else: ?>
                <a href="file_management.php" class="nav-item">
                    <span class="nav-icon">📄</span>
                    <span class="nav-text">File Management</span>
                </a>
                <a href="new_document.php" class="nav-item active">
                    <span class="nav-icon">📤</span>
                    <span class="nav-text">Document Upload</span>
                </a>
                <a href="#" class="nav-item">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Routes</span>
                </a>
                <a href="#" class="nav-item">
                    <span class="nav-icon">👤</span>
                    <span class="nav-text">Profile</span>
                </a>
            <?php endif; ?>

            <a href="logout.php" class="nav-item logout-item">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container sidebar-hidden">
        <!-- nd-* = New Document page only: title + intro OUTSIDE white container -->
        <div class="nd-page-header">
            <h1 class="nd-title">Document Upload</h1>
            <p class="nd-subtitle">Upload and register documents to the system. Please ensure proper naming and metadata tagging.</p>
        </div>

        <!-- Single white card container for form -->
        <div class="dashboard-content document-upload-dashboard">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="nd-form-card">
                <div class="nd-section-head">
                    <h2 class="upload-card-title">Upload New Document</h2>
                    <p class="upload-card-desc">Please fill out the form below to upload your document. All fields marked with <span class="required">*</span> are required.</p>
                </div>

                <form method="POST" action="new_document.php" enctype="multipart/form-data" class="document-upload-form">
                    <div class="form-row">
                        <div class="form-group form-group-full">
                            <label for="title">Document Title <span class="required">*</span></label>
                            <input type="text" id="title" name="title" required
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                   placeholder="Enter a clear, descriptive title for your document.">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-full">
                            <label for="document_type">Document Type <span class="required">*</span></label>
                            <select id="document_type" name="document_type" required>
                                <option value="">-- Select Type --</option>
                                <option value="letter" <?php echo ($_POST['document_type'] ?? '') === 'letter' ? 'selected' : ''; ?>>Letter</option>
                            </select>
                            <span class="form-help">Choose the type that best describes your document.</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-full">
                            <label for="originating_team">Originating Team</label>
                            <select id="originating_team" name="originating_team">
                                <option value="">-- Select Team --</option>
                                <?php foreach ($teamOptions as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($_POST['originating_team'] ?? '') === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-help">Team that originated this document.</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-full">
                            <label for="recipient_team">Recipient Team <span class="required">*</span></label>
                            <select id="recipient_team" name="recipient_team" required>
                                <option value="">-- Select Team --</option>
                                <?php foreach ($teamOptions as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($_POST['recipient_team'] ?? '') === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-help">Documents routed to this team will only appear on that team's dashboard.</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-full">
                            <label for="route_type">Route Type <span class="required">*</span></label>
                            <select id="route_type" name="route_type" required>
                                <option value="Internal" <?php echo ($_POST['route_type'] ?? 'Internal') === 'Internal' ? 'selected' : ''; ?>>Internal</option>
                                <option value="External" <?php echo ($_POST['route_type'] ?? '') === 'External' ? 'selected' : ''; ?>>External</option>
                                <option value="Urgent" <?php echo ($_POST['route_type'] ?? '') === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                                <option value="Confidential" <?php echo ($_POST['route_type'] ?? '') === 'Confidential' ? 'selected' : ''; ?>>Confidential</option>
                            </select>
                            <span class="form-help">Select the routing classification for this document.</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-full">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" rows="4" placeholder="Add any additional notes or instructions (optional)."><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                            <span class="form-help">Add any additional notes or instructions (optional).</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="route_before">Route Before (Deadline to initiate routing)</label>
                            <div class="input-date-wrap">
                                <input type="date" id="route_before" name="route_before"
                                       value="<?php echo htmlspecialchars($_POST['route_before'] ?? ''); ?>"
                                       class="input-with-icon">
                                <span class="input-date-icon" aria-hidden="true">📅</span>
                            </div>
                            <span class="form-help">Optional. If set, document will be marked &quot;Pending for Routing&quot; until routed. After this date passes it becomes &quot;Overdue for Routing&quot;.</span>
                        </div>
                    </div>

                    <div class="form-row form-row-upload">
                        <div class="form-group form-group-full">
                            <label>Upload File <span class="required">*</span></label>
                            <p class="form-help" style="margin-top: 0; margin-bottom: 12px;">Select up to 3 files at once. Use <strong>×</strong> next to a file to remove it.</p>
                            <div class="nd-file-slot nd-single-row" id="ndFileSlot">
                                <input type="file" id="document_file_input" name="document_file[]" class="nd-file-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.bmp,.txt,.rtf,.odt,.ods,.odp">
                                <span class="nd-file-placeholder" id="ndFilePlaceholder">Click or drag — up to 3 files</span>
                                <div class="nd-file-chosen-list hidden" id="ndFileChosenList"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-upload">
                        <button type="submit" name="submit_document" class="btn-submit-upload">Upload Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const dashboardContainer = document.querySelector('.dashboard-container');
        const menuButtonContainer = document.querySelector('.menu-button-container');

        menuToggle.addEventListener('click', () => {
            const isHidden = sidebar.classList.contains('hidden');
            if (isHidden) {
                sidebar.classList.remove('hidden');
                dashboardContainer.classList.remove('sidebar-hidden');
                if (menuButtonContainer) {
                    menuButtonContainer.classList.add('sidebar-open');
                }
                if (sidebarOverlay) sidebarOverlay.classList.add('active');
            } else {
                sidebar.classList.add('hidden');
                dashboardContainer.classList.add('sidebar-hidden');
                if (menuButtonContainer) {
                    menuButtonContainer.classList.remove('sidebar-open');
                }
                if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            }
        });
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.add('hidden');
                dashboardContainer.classList.add('sidebar-hidden');
                if (menuButtonContainer) {
                    menuButtonContainer.classList.remove('sidebar-open');
                }
                sidebarOverlay.classList.remove('active');
            });
        }
        if (inboxMenu) {
            inboxMenu.addEventListener('click', (e) => {
                if (e.target.closest('.nav-item-header')) inboxMenu.classList.toggle('active');
            });
        }

        // Single row: one input with multiple (up to 3 files). × removes a file and syncs input via DataTransfer.
        const MAX_FILES = 3;
        const fileInput = document.getElementById('document_file_input');
        const chosenListEl = document.getElementById('ndFileChosenList');
        const placeholderEl = document.getElementById('ndFilePlaceholder');
        const slotEl = document.getElementById('ndFileSlot');

        let selectedFiles = []; // working set (FileList is read-only)

        function syncInputFromFiles() {
            const dt = new DataTransfer();
            selectedFiles.forEach(function(f) { dt.items.add(f); });
            fileInput.files = dt.files;
        }

        function escapeHtml(s) {
            const div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        function renderList() {
            chosenListEl.innerHTML = '';
            selectedFiles.forEach(function(file, index) {
                const row = document.createElement('div');
                row.className = 'nd-file-item';
                const safeName = escapeHtml(file.name);
                row.innerHTML = '<span class="nd-file-name" title="' + safeName + '">' + safeName + '</span><button type="button" class="nd-file-remove" aria-label="Remove file">×</button>';
                row.querySelector('.nd-file-remove').addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    selectedFiles.splice(index, 1);
                    syncInputFromFiles();
                    renderList();
                    updatePlaceholder();
                });
                chosenListEl.appendChild(row);
            });
        }

        function updatePlaceholder() {
            if (selectedFiles.length === 0) {
                placeholderEl.classList.remove('hidden');
                chosenListEl.classList.add('hidden');
            } else {
                placeholderEl.classList.add('hidden');
                chosenListEl.classList.remove('hidden');
            }
        }

        function setFilesFromInput() {
            selectedFiles = Array.from(fileInput.files || []).slice(0, MAX_FILES);
            renderList();
            updatePlaceholder();
        }

        function addFilesFromDrop(files) {
            if (!files || !files.length) return;
            const list = Array.from(files);
            list.forEach(function(f) {
                if (selectedFiles.length < MAX_FILES) selectedFiles.push(f);
            });
            syncInputFromFiles();
            renderList();
            updatePlaceholder();
        }

        slotEl.addEventListener('click', function(e) {
            if (e.target.closest('.nd-file-remove')) return;
            if (e.target !== fileInput) fileInput.click();
        });

        fileInput.addEventListener('change', function() {
            setFilesFromInput();
        });

        slotEl.addEventListener('dragover', function(e) { e.preventDefault(); slotEl.classList.add('dragover'); });
        slotEl.addEventListener('dragleave', function() { slotEl.classList.remove('dragover'); });
        slotEl.addEventListener('drop', function(e) {
            e.preventDefault();
            slotEl.classList.remove('dragover');
            addFilesFromDrop(e.dataTransfer.files);
        });

        document.querySelector('.document-upload-form').addEventListener('submit', function(e) {
            if (selectedFiles.length === 0) {
                e.preventDefault();
                alert('Please add at least one file (up to 3).');
            }
        });
    </script>
</body>
</html>
