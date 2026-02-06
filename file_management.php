<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "db.php";

// Protect page from unauthorized access
if(!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])){
    header("Location: login.php");
    exit();
}

// Determine user type
$isAdmin = isset($_SESSION['admin']);
$userType = $isAdmin ? 'admin' : 'employee';
$userId = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];

// Employee: get their team
$userTeam = null;
if (!$isAdmin && isset($_SESSION['employee_id'])) {
    $eid = mysqli_real_escape_string($conn, $_SESSION['employee_id']);
    $teamRes = mysqli_query($conn, "SELECT team FROM employees WHERE employee_id = '$eid' LIMIT 1");
    if ($teamRes && $row = mysqli_fetch_assoc($teamRes)) {
        $userTeam = $row['team'];
    }
}

// Handle status update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_id'], $_POST['new_status'])) {
    $docId = (int)$_POST['doc_id'];
    $allowed = ['pending', 'completed', 'pending for completion', 'reverted'];
    $newStatus = trim($_POST['new_status']);
    if ($docId > 0 && in_array($newStatus, $allowed, true)) {
        $newStatusEsc = mysqli_real_escape_string($conn, $newStatus);
        mysqli_query($conn, "UPDATE documents SET status = '$newStatusEsc' WHERE document_id = $docId");
    }
    header("Location: file_management.php");
    exit();
}

// Get filter parameters
$search = trim($_GET['q'] ?? '');
$filterTeam = trim($_GET['team'] ?? '');
$filterRouteType = trim($_GET['route_type'] ?? '');

// Fetch all teams for filter dropdown
$teamsQuery = "SELECT team_name FROM teams ORDER BY team_name ASC";
$teamsResult = mysqli_query($conn, $teamsQuery);
$allTeams = [];
if ($teamsResult) {
    while ($teamRow = mysqli_fetch_assoc($teamsResult)) {
        $allTeams[] = $teamRow['team_name'];
    }
}

// Base query - show all uploaded documents
$where = "1=1";

// For employees, only show documents uploaded by them or routed to their team
if ($userTeam !== null) {
    $teamEsc = mysqli_real_escape_string($conn, $userTeam);
    $uidEsc = mysqli_real_escape_string($conn, $userId);
    $utEsc = mysqli_real_escape_string($conn, $userType);
    $where .= " AND (recipient_team = '$teamEsc' OR (uploaded_by_id = '$uidEsc' AND uploaded_by_type = '$utEsc'))";
}

// Apply search filter
if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (title LIKE '%$s%' OR reference_no LIKE '%$s%' OR document_type LIKE '%$s%' OR remarks LIKE '%$s%')";
}

// Apply team filter
if ($filterTeam !== '') {
    $ft = mysqli_real_escape_string($conn, $filterTeam);
    $where .= " AND recipient_team = '$ft'";
}

// Apply route type filter
if ($filterRouteType !== '') {
    $frt = mysqli_real_escape_string($conn, $filterRouteType);
    $where .= " AND route_type = '$frt'";
}

$docQuery = "SELECT document_id, reference_no, title, document_type, created_at, route_before, status, route_type, remarks, recipient_team, uploaded_by_name, originating_team, file_path, file_name
             FROM documents WHERE $where ORDER BY created_at DESC";
$docResult = mysqli_query($conn, $docQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Management - DRIMS</title>
    <link rel="stylesheet" type="text/css" href="/ICLHO_Route/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Variables for Design System */
        :root {
            /* Primary Colors */
            --primary: #4267B2;
            --primary-dark: #365899;
            --primary-light: #5b7bd5;
            
            /* Status Colors */
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
            
            /* Neutrals */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Spacing */
            --spacing-xs: 4px;
            --spacing-sm: 8px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
            
            /* Border Radius */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        /* Global Typography */
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
        }
        
        /* Modern Table Styling */
        .doc-table-wrap { 
            overflow-x: auto; 
            margin-top: var(--spacing-md);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            padding: var(--spacing-lg);
        }
        
        .doc-table { 
            width: 100%; 
            border-collapse: separate;
            border-spacing: 0;
            background: transparent;
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        
        .doc-table th, .doc-table td { 
            padding: 16px 18px;
            text-align: left;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .doc-table th { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .doc-table tbody tr {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #fff;
        }
        
        .doc-table tbody tr:nth-child(even) {
            background: var(--gray-50);
        }
        
        .doc-table tbody tr:hover {
            background: var(--info-light) !important;
            transform: scale(1.01);
            box-shadow: var(--shadow-md);
            position: relative;
            z-index: 1;
        }
        
        .doc-table .actions-btn { 
            padding: 10px 20px;
            border-radius: var(--radius-md);
            border: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }
        
        .doc-table .actions-btn:hover { 
            transform: translateY(-2px) scale(1.05);
            box-shadow: var(--shadow-lg), 0 0 20px rgba(66, 103, 178, 0.4);
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
        }
        
        .doc-table .actions-btn:active {
            transform: translateY(0) scale(0.98);
        }
        
        .doc-table .actions-cell { position: relative; }
        
        /* Modern Search Form */
        .doc-search-form {
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-lg);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }
        
        .doc-search-form input[type="text"],
        .doc-search-form select {
            padding: 12px 18px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            color: var(--gray-900);
        }
        
        .doc-search-form input[type="text"] {
            flex: 1;
            min-width: 250px;
        }
        
        .doc-search-form input[type="text"]:focus,
        .doc-search-form select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(66, 103, 178, 0.1);
        }
        
        .doc-search-form select {
            min-width: 150px;
            cursor: pointer;
        }
        
        .btn-search {
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg), 0 0 20px rgba(66, 103, 178, 0.4);
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
        }
        
        .btn-search:active {
            transform: translateY(0);
        }
        
        /* Popup Menu Styles */
        .actions-popup { 
            display: none; 
            position: fixed; 
            z-index: 9999; 
            background: #fff; 
            border-radius: 12px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05); 
            min-width: 300px;
            min-height: 280px;
            padding: 0;
            overflow: hidden;
            animation: popupFadeIn 0.15s ease-out;
        }
        @keyframes popupFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .actions-popup.show { display: block; }
        .actions-popup-header {
            background: linear-gradient(135deg, #2d6cdf 0%, #1f4fb3 100%);
            color: #fff;
            padding: 12px 16px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .actions-popup-header span {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .actions-popup-close {
            background: rgba(255,255,255,0.95);
            border: 2px solid rgba(255,255,255,0.8);
            color: #1f2937;
            cursor: pointer;
            font-size: 20px;
            font-weight: 700;
            padding: 0;
            width: 28px;
            height: 28px;
            min-width: 28px;
            max-width: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            flex-shrink: 0;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            align-self: flex-start;
            margin-top: 0px;
        }
        .actions-popup-close:hover { 
            background: #fff;
            color: #ef4444;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        }
        .actions-popup-section { 
            padding: 8px 0; 
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100px;
        }
        .actions-popup-section:last-child { border-bottom: none; }
        .actions-popup-label { padding: 8px 18px 4px; font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .actions-popup a, .actions-popup button { 
            display: inline-flex; 
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 60%; 
            padding: 12px 24px; 
            text-align: center; 
            border: none; 
            background: none; 
            cursor: pointer; 
            font-size: 14px; 
            color: #333; 
            text-decoration: none; 
            box-sizing: border-box; 
            transition: background 0.15s, color 0.15s;
            margin: 4px auto;
            margin-top: 30px;
        }
        .actions-popup a:hover, .actions-popup button:hover { background: #f1f5f9; color: #2d6cdf; }
        .actions-popup .view-file-link { color: #2d6cdf; font-weight: 600; }
        .actions-popup .view-file-link:hover { background: #eff6ff; }
        
        .doc-search-form { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .doc-search-form input[type="text"] { flex: 1; min-width: 200px; padding: 12px 16px; border: 2px solid #e0e6ed; border-radius: 8px; font-size: 15px; }
        .doc-search-form input:focus { outline: none; border-color: #2d6cdf; }
        .doc-search-form .btn-search { padding: 12px 24px; background: linear-gradient(135deg, #2d6cdf 0%, #1f4fb3 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .doc-search-form .btn-search:hover { opacity: 0.95; }
        .doc-table .remarks-cell { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        
        /* Overlay */
        .popup-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 9998; }
        .popup-overlay.show { display: block; }
        
        /* Document Details Modal */
        .doc-details-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 80px rgba(0,0,0,0.35);
            width: 95%;
            max-width: 1200px;
            max-height: 95vh;
            overflow: hidden;
            z-index: 10000;
        }
        .doc-details-modal.show { display: flex; flex-direction: column; }
        
        .doc-details-header {
            background: linear-gradient(135deg, #4267B2 0%, #365899 100%);
            color: #fff;
            padding: 20px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        .doc-details-header h2 { 
            margin: 0; 
            font-size: 20px; 
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        .doc-details-close-x {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 26px;
            font-weight: 300;
            padding: 0;
            line-height: 1;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.85;
            transition: opacity 0.2s;
        }
        .doc-details-close-x:hover { opacity: 1; }
        
        .doc-details-body {
            flex: 1;
            overflow-y: auto;
            background: #fff;
        }
        
        .doc-section-header {
            background: linear-gradient(135deg, #4267B2 0%, #365899 100%);
            color: #fff;
            padding: 12px 28px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0;
        }
        
        .doc-info-content { 
            padding: 28px 28px 24px 28px;
            background: #fff;
        }
        
        .doc-info-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 32px;
            margin-bottom: 20px;
        }
        .doc-info-row:last-child { margin-bottom: 0; }
        .doc-info-row.full { grid-template-columns: 1fr; }
        
        .doc-info-item { 
            display: flex; 
            flex-direction: column;
        }
        
        .doc-info-label { 
            font-size: 11px; 
            color: #8b8b8b; 
            font-weight: 600; 
            text-transform: uppercase; 
            margin-bottom: 7px;
            letter-spacing: 0.5px;
        }
        
        .doc-info-value { 
            font-size: 15px; 
            color: #1c1e21; 
            font-weight: 400; 
            line-height: 1.5;
            word-wrap: break-word;
        }
        
        .doc-info-value.doc-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .doc-info-value.doc-route-history {
            background: #f0f4ff;
            padding: 12px 16px;
            border-radius: 6px;
            border-left: 4px solid #4267B2;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .doc-preview-section {
            background: #fff;
            padding: 24px 28px 28px 28px;
            border-top: none;
        }

        .doc-preview-frame {
            width: 100%;
            height: 450px;
            border: 1px solid #dadde1;
            border-radius: 6px;
            background: #fff;
        }
        .doc-preview-placeholder {
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8b8b8b;
            font-size: 14px;
            background: #fff;
            border: 1px solid #dadde1;
            border-radius: 6px;
        }
        
        .doc-details-footer {
            background: #f5f6f7;
            padding: 18px 28px;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 14px;
            border-top: 1px solid #dadde1;
            flex-shrink: 0;
        }
        
        .doc-details-footer a, .doc-details-footer button {
            padding: 11px 22px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
        }
        
        .btn-download {
            background: #4267B2;
            color: #fff;
        }
        .btn-download:hover { background: #365899; }
        
        .btn-open-tab {
            background: #6c5ce7;
            color: #fff;
        }
        .btn-open-tab:hover { background: #5f4dd1; }
        
        .btn-close-modal {
            background: #8b90a0;
            color: #fff;
            margin-left: auto;
        }
        .btn-close-modal:hover { background: #6d7280; }
    </style>
</head>
<body>
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
            <div class="nav-item nav-item-parent" id="inboxMenu">
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
            <a href="file_management.php" class="nav-item active">
                <span class="nav-icon">📄</span>
                <span class="nav-text">File Management</span>
            </a>
            <a href="new_document.php" class="nav-item">
                <span class="nav-icon">📤</span>
                <span class="nav-text">Document Upload</span>
            </a>
            <?php if($isAdmin): ?>
            <a href="employees.php" class="nav-item">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Employees</span>
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
        <div class="employees-content">
            <div class="page-header">
                <h1>File Management Dashboard</h1>
                <p style="color: #64748b; font-size: 1rem; margin-top: 8px;">View and manage all uploaded documents in the system</p>
            </div>
            
            <div class="table-container">
                <form method="get" action="file_management.php" class="doc-search-form">
                    <input type="text" name="q" placeholder="Search documents..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="team" style="padding: 12px 16px; border: 2px solid #e0e6ed; border-radius: 8px; font-size: 15px; min-width: 150px;">
                        <option value="">All Teams</option>
                        <?php foreach ($allTeams as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $filterTeam === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="route_type" style="padding: 12px 16px; border: 2px solid #e0e6ed; border-radius: 8px; font-size: 15px; min-width: 150px;">
                        <option value="">All Route Types</option>
                        <option value="Internal" <?php echo $filterRouteType === 'Internal' ? 'selected' : ''; ?>>Internal</option>
                        <option value="External" <?php echo $filterRouteType === 'External' ? 'selected' : ''; ?>>External</option>
                        <option value="Urgent" <?php echo $filterRouteType === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                        <option value="Confidential" <?php echo $filterRouteType === 'Confidential' ? 'selected' : ''; ?>>Confidential</option>
                    </select>
                    
                    <button type="submit" class="btn-search">🔍 Search</button>
                    <?php if ($search !== '' || $filterTeam !== '' || $filterRouteType !== ''): ?>
                        <a href="file_management.php" style="padding: 12px 24px; background: #f1f5f9; color: #64748b; border-radius: 8px; text-decoration: none; font-weight: 600;">Clear</a>
                    <?php endif; ?>
                </form>

                <div class="doc-table-wrap">
                    <table class="doc-table employees-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Reference No.</th>
                                <th>Type</th>
                                <th>Route Date</th>
                                <th>Due Date</th>
                                <th>Route Type</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Recipient Team</th>
                                <th>Route History</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($docResult && mysqli_num_rows($docResult) > 0): ?>
                                <?php while ($doc = mysqli_fetch_assoc($docResult)):
                                    $routeDate = $doc['created_at'] ? date('Y-m-d', strtotime($doc['created_at'])) : '—';
                                    $dueDate = $doc['route_before'] ? date('Y-m-d', strtotime($doc['route_before'])) : '—';
                                    $status = $doc['status'] ?: 'pending';
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($doc['reference_no']); ?></td>
                                        <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                        <td><?php echo $routeDate; ?></td>
                                        <td><?php echo $dueDate; ?></td>
                                        <td>
                                            <?php 
                                            $routeType = $doc['route_type'] ?? 'Internal';
                                            $rtColors = [
                                                'Internal' => 'background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: 600;',
                                                'External' => 'background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: 600;',
                                                'Urgent' => 'background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: 600;',
                                                'Confidential' => 'background: #e9d5ff; color: #6b21a8; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: 600;'
                                            ];
                                            $rtStyle = $rtColors[$routeType] ?? $rtColors['Internal'];
                                            ?>
                                            <span style="<?php echo $rtStyle; ?>"><?php echo htmlspecialchars($routeType); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            // Status badge styles
                                            $statusStyles = [
                                                'pending' => 'background: #fef3c7; color: #92400e;',
                                                'completed' => 'background: #d1fae5; color: #065f46;',
                                                'reverted' => 'background: #fee2e2; color: #991b1b;',
                                                'pending for completion' => 'background: #e0e7ff; color: #3730a3;'
                                            ];
                                            $statusStyle = $statusStyles[$status] ?? $statusStyles['pending'];
                                            ?>
                                            <span style="<?php echo $statusStyle; ?> padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td class="remarks-cell" title="<?php echo htmlspecialchars($doc['remarks'] ?? ''); ?>"><?php echo htmlspecialchars($doc['remarks'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['recipient_team'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['uploaded_by_name'] ?? '—'); ?></td>
                                        <td class="actions-cell">
                                            <button type="button" class="actions-btn" 
                                                data-doc-id="<?php echo (int)$doc['document_id']; ?>" 
                                                data-doc-title="<?php echo htmlspecialchars($doc['title']); ?>" 
                                                data-file-path="<?php echo htmlspecialchars($doc['file_path'] ?? ''); ?>"
                                                data-file-name="<?php echo htmlspecialchars($doc['file_name'] ?? ''); ?>"
                                                data-reference="<?php echo htmlspecialchars($doc['reference_no'] ?? ''); ?>"
                                                data-route-date="<?php echo htmlspecialchars($doc['created_at'] ?? ''); ?>"
                                                data-route-type="<?php echo htmlspecialchars($doc['route_type'] ?? ''); ?>"
                                                data-route-history="<?php echo htmlspecialchars($doc['route_before'] ?? ''); ?>"
                                                data-remarks="<?php echo htmlspecialchars($doc['remarks'] ?? ''); ?>"
                                                data-recipient="<?php echo htmlspecialchars($doc['recipient_team'] ?? ''); ?>"
                                                data-creator="<?php echo htmlspecialchars($doc['uploaded_by_name'] ?? ''); ?>"
                                                data-originating-team="<?php echo htmlspecialchars($doc['originating_team'] ?? ''); ?>"
                                                data-status="<?php echo htmlspecialchars($status); ?>"
                                                data-doc-type="<?php echo htmlspecialchars($doc['document_type'] ?? ''); ?>"
                                            >Actions ▼</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="no-data">No documents found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Popup Overlay -->
    <div class="popup-overlay" id="popupOverlay"></div>
    
    <!-- Actions Popup Menu -->
    <div class="actions-popup" id="actionsPopup">
        <div class="actions-popup-header">
            <span id="popupTitle">Document Actions</span>
            <button type="button" class="actions-popup-close" id="popupClose">×</button>
        </div>
        <div class="actions-popup-section">
            <button type="button" id="popupViewDetails" class="view-file-link">📄 View File</button>
            <button type="button" class="comments-btn">💬 Comments</button>
        </div>
    </div>
    
    <!-- Document Details Modal -->
    <div class="doc-details-modal" id="docDetailsModal">
        <div class="doc-details-header">
            <h2>📄 Document Details</h2>
            <button type="button" class="doc-details-close-x" id="docDetailsClose">×</button>
        </div>
        <div class="doc-details-body">
            <!-- Document Information Section -->
            <div class="doc-section-header">📋 DOCUMENT INFORMATION</div>
            <div class="doc-info-content">
                <div class="doc-info-row">
                    <div class="doc-info-item">
                        <span class="doc-info-label">Document ID</span>
                        <span class="doc-info-value" id="detailDocId">—</span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-label">Reference Number</span>
                        <span class="doc-info-value" id="detailReference">—</span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-label">Document Type</span>
                        <span class="doc-info-value" id="detailDocType">—</span>
                    </div>
                </div>
                <div class="doc-info-row full">
                    <div class="doc-info-item">
                        <span class="doc-info-label">Title</span>
                        <span class="doc-info-value doc-title" id="detailTitle">—</span>
                    </div>
                </div>
                <div class="doc-info-row full">
                    <div class="doc-info-item">
                        <span class="doc-info-label">File Name</span>
                        <span class="doc-info-value" id="detailFileName">—</span>
                    </div>
                </div>
            </div>

            <!-- Routing Information Section -->
            <div class="doc-section-header">🔄 ROUTING INFORMATION</div>
            <div class="doc-info-content">
                <div class="doc-info-row">
                    <div class="doc-info-item">
                        <span class="doc-info-label">Status</span>
                        <span class="doc-info-value" id="detailStatus">—</span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-label">Route Type</span>
                        <span class="doc-info-value" id="detailRouteType">—</span>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-label">Date Created</span>
                        <span class="doc-info-value" id="detailRouteDate">—</span>
                    </div>
                </div>
                <div class="doc-info-row full">
                    <div class="doc-info-item">
                        <span class="doc-info-label">📍 Route History</span>
                        <span class="doc-info-value doc-route-history" id="detailRouteHistory">—</span>
                    </div>
                </div>
                <div class="doc-info-row full">
                    <div class="doc-info-item">
                        <span class="doc-info-label">💬 Remarks</span>
                        <span class="doc-info-value" id="detailRemarks">—</span>
                    </div>
                </div>
            </div>
            
            <!-- File Preview Section -->
            <div class="doc-section-header">👁️ FILE PREVIEW</div>
            <div class="doc-preview-section">
                <div id="docPreviewContainer">
                    <div class="doc-preview-placeholder">Click "Open in new tab" to view the file</div>
                </div>
            </div>
        </div>
        <div class="doc-details-footer">
            <a href="#" id="detailDownload" download class="btn-download">⬇ Download</a>
            <a href="#" id="detailOpenTab" target="_blank" rel="noopener" class="btn-open-tab">↗ Open in new tab</a>
        </div>
    </div>
    
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const dashboardContainer = document.querySelector('.dashboard-container');
        const menuButtonContainer = document.querySelector('.menu-button-container');
        
        // Toggle sidebar visibility
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
        
        // Inbox submenu toggle
        const inboxMenu = document.getElementById('inboxMenu');
        if (inboxMenu) {
            inboxMenu.addEventListener('click', (e) => {
                if (e.target.closest('.nav-item-header')) {
                    inboxMenu.classList.toggle('active');
                }
            });
        }

        // Popup Menu Logic
        const popupOverlay = document.getElementById('popupOverlay');
        const actionsPopup = document.getElementById('actionsPopup');
        const popupClose = document.getElementById('popupClose');
        const popupTitle = document.getElementById('popupTitle');
        
        // Document Details Modal
        const docDetailsModal = document.getElementById('docDetailsModal');
        const docDetailsClose = document.getElementById('docDetailsClose');
        const popupViewDetails = document.getElementById('popupViewDetails');
        
        // Store current document data
        let currentDocData = {};

        function openPopup(docData) {
            currentDocData = docData;
            popupTitle.textContent = docData.title || 'Document Actions';
            
            // Position popup in center of screen
            actionsPopup.style.top = '50%';
            actionsPopup.style.left = '50%';
            actionsPopup.style.transform = 'translate(-50%, -50%)';
            
            // Show popup and overlay
            popupOverlay.classList.add('show');
            actionsPopup.classList.add('show');
        }

        function closePopup() {
            popupOverlay.classList.remove('show');
            actionsPopup.classList.remove('show');
        }
        
        function openDocDetails() {
            // Close Actions popup first
            closePopup();
            
            // Populate Document Details modal
            document.getElementById('detailDocId').textContent = currentDocData.id || '—';
            document.getElementById('detailReference').textContent = currentDocData.reference || '—';
            document.getElementById('detailTitle').textContent = currentDocData.title || '—';
            document.getElementById('detailFileName').textContent = currentDocData.fileName || '—';
            document.getElementById('detailDocType').textContent = currentDocData.docType || '—';
            document.getElementById('detailRouteDate').textContent = currentDocData.routeDate || '—';
            document.getElementById('detailRouteType').textContent = currentDocData.routeType || '—';
            document.getElementById('detailStatus').textContent = currentDocData.status || '—';
            // Format Route History to show uploader and teams
            let routeHistoryText = '—';
            if (currentDocData.creator && currentDocData.originatingTeam) {
                routeHistoryText = `Uploaded by ${currentDocData.creator} from ${currentDocData.originatingTeam}`;
                if (currentDocData.recipient) {
                    routeHistoryText += ` → Routed to ${currentDocData.recipient}`;
                }
            }
            document.getElementById('detailRouteHistory').textContent = routeHistoryText;
            document.getElementById('detailRemarks').textContent = currentDocData.remarks || '—';
            
            // Set file links
            const fileUrl = '/ICLHO_Route/' + currentDocData.filePath;
            document.getElementById('detailDownload').href = fileUrl;
            document.getElementById('detailOpenTab').href = fileUrl;
            
            // File preview
            const previewContainer = document.getElementById('docPreviewContainer');
            const ext = (currentDocData.fileName || '').split('.').pop().toLowerCase();
            
            if (['pdf'].includes(ext)) {
                previewContainer.innerHTML = '<iframe src="' + fileUrl + '" class="doc-preview-frame"></iframe>';
            } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                previewContainer.innerHTML = '<img src="' + fileUrl + '" style="max-width:100%; max-height:300px; border-radius:4px;" alt="Document Preview">';
            } else {
                previewContainer.innerHTML = '<div class="doc-preview-placeholder">Preview not available for this file type. Click "Open in New Tab" to view.</div>';
            }
            
            // Show modal
            popupOverlay.classList.add('show');
            docDetailsModal.classList.add('show');
        }
        
        function closeDocDetails() {
            popupOverlay.classList.remove('show');
            docDetailsModal.classList.remove('show');
        }

        // Actions button click - open popup
        document.querySelectorAll('.actions-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const docData = {
                    id: btn.getAttribute('data-doc-id'),
                    title: btn.getAttribute('data-doc-title'),
                    filePath: btn.getAttribute('data-file-path'),
                    fileName: btn.getAttribute('data-file-name'),
                    reference: btn.getAttribute('data-reference'),
                    routeDate: btn.getAttribute('data-route-date'),
                    routeType: btn.getAttribute('data-route-type'),
                    routeHistory: btn.getAttribute('data-route-history'),
                    remarks: btn.getAttribute('data-remarks'),
                    recipient: btn.getAttribute('data-recipient'),
                    creator: btn.getAttribute('data-creator'),
                    originatingTeam: btn.getAttribute('data-originating-team'),
                    status: btn.getAttribute('data-status'),
                    docType: btn.getAttribute('data-doc-type')
                };
                openPopup(docData);
            });
        });
        
        // View File button - open Document Details modal
        popupViewDetails.addEventListener('click', openDocDetails);

        // Close popup on X button click
        popupClose.addEventListener('click', closePopup);
        
        // Close Document Details modal on X button click
        docDetailsClose.addEventListener('click', closeDocDetails);
        
        // Close Document Details modal on Close button click
        const docDetailsCloseBtn = document.getElementById('docDetailsCloseBtn');
        if (docDetailsCloseBtn) {
            docDetailsCloseBtn.addEventListener('click', closeDocDetails);
        }

        // Close on overlay click
        popupOverlay.addEventListener('click', function() {
            closePopup();
            closeDocDetails();
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePopup();
                closeDocDetails();
            }
        });
    </script>
</body>
</html>
