<?php
session_start();
include "db.php";

// Protect page from unauthorized access
if(!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])){
    header("Location: login.php");
    exit();
}

// Get inbox type
$inboxType = isset($_GET['type']) ? $_GET['type'] : 'new';
$validTypes = ['new', 'incoming', 'outgoing'];
if(!in_array($inboxType, $validTypes)){
    $inboxType = 'new';
}

// Determine user type
$isAdmin = isset($_SESSION['admin']);
$userType = $isAdmin ? 'admin' : 'employee';
$userId = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];

// Page titles
$pageTitles = [
    'new' => 'New Documents',
    'incoming' => 'Incoming Routed Documents',
    'outgoing' => 'Outgoing Routed Documents'
];

$pageTitle = $pageTitles[$inboxType];

// Employee: get their team so we only show documents routed to that team
$userTeam = null;
if (!$isAdmin && isset($_SESSION['employee_id'])) {
    $eid = mysqli_real_escape_string($conn, $_SESSION['employee_id']);
    $teamRes = mysqli_query($conn, "SELECT team FROM employees WHERE employee_id = '$eid' LIMIT 1");
    if ($teamRes && $row = mysqli_fetch_assoc($teamRes)) {
        $userTeam = $row['team'];
    }
}

// Ensure documents table and recipient_team exist (minimal bootstrap)
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'documents'");
if ($t && mysqli_num_rows($t) > 0) {
    $rc = @mysqli_query($conn, "SHOW COLUMNS FROM documents LIKE 'recipient_team'");
    if ($rc && mysqli_num_rows($rc) === 0) {
        @mysqli_query($conn, "ALTER TABLE documents ADD COLUMN recipient_team VARCHAR(100) NULL");
    }
    
    // Add route_type column if it doesn't exist
    $rtCol = @mysqli_query($conn, "SHOW COLUMNS FROM documents LIKE 'route_type'");
    if ($rtCol && mysqli_num_rows($rtCol) === 0) {
        @mysqli_query($conn, "ALTER TABLE documents ADD COLUMN route_type VARCHAR(50) DEFAULT 'Internal' AFTER status");
    }
}


// Update status (POST) from table dropdowns
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_id'], $_POST['new_status'])) {
    $docId = (int)$_POST['doc_id'];
    $allowed = ['pending', 'completed', 'pending for completion', 'reverted'];
    $newStatus = trim($_POST['new_status']);
    if ($docId > 0 && in_array($newStatus, $allowed, true)) {
        $newStatusEsc = mysqli_real_escape_string($conn, $newStatus);
        mysqli_query($conn, "UPDATE documents SET status = '$newStatusEsc' WHERE document_id = $docId");
    }
    $redir = "inbox.php?type=" . urlencode($inboxType);
    if (isset($_GET['q']) && $_GET['q'] !== '') $redir .= "&q=" . urlencode($_GET['q']);
    header("Location: " . $redir);
    exit();
}

// Get filter parameters
$search = trim($_GET['q'] ?? '');
$filterTeam = trim($_GET['team'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
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

// Base visibility filter
$where = "1=1";
if ($userTeam !== null) {
    $teamEsc = mysqli_real_escape_string($conn, $userTeam);
    $where .= " AND recipient_team = '$teamEsc'";
}

// Tab filters
switch ($inboxType) {
    case 'new':
        $where .= " AND status = 'pending'";
        break;
    case 'outgoing':
        $uidEsc = mysqli_real_escape_string($conn, $userId);
        $utEsc = mysqli_real_escape_string($conn, $userType);
        $where .= " AND uploaded_by_id = '$uidEsc' AND uploaded_by_type = '$utEsc'";
        break;
    case 'incoming':
        // Incoming = routed to you/your team (already filtered above for employees)
        // For admin (no team), show all
        break;
}

// Apply search filter
if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (title LIKE '%$s%' OR reference_no LIKE '%$s%' OR document_type LIKE '%$s%' OR remarks LIKE '%$s%' OR uploaded_by_name LIKE '%$s%' OR recipient_team LIKE '%$s%')";
}

// Apply team filter
if ($filterTeam !== '') {
    $ft = mysqli_real_escape_string($conn, $filterTeam);
    $where .= " AND recipient_team = '$ft'";
}

// Apply status filter
if ($filterStatus !== '') {
    $fs = mysqli_real_escape_string($conn, $filterStatus);
    $where .= " AND status = '$fs'";
}

// Apply route type filter
if ($filterRouteType !== '') {
    $frt = mysqli_real_escape_string($conn, $filterRouteType);
    $where .= " AND route_type = '$frt'";
}

$docQuery = "SELECT document_id, reference_no, title, document_type, created_at, route_before, status, route_type, remarks, recipient_team, uploaded_by_name, file_path, file_name
             FROM documents WHERE $where ORDER BY created_at DESC";
$docResult = mysqli_query($conn, $docQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - DRIMS</title>
    <link rel="stylesheet" type="text/css" href="/ICLHO_Route/style.css">
    <style>
        .doc-table-wrap { overflow-x: auto; margin-top: 16px; }
        .doc-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .doc-table th, .doc-table td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .doc-table th { background: linear-gradient(135deg, #2d6cdf 0%, #1f4fb3 100%); color: #fff; font-weight: 600; }
        .doc-table tbody tr:hover { background: #f8fafc; }
        .doc-table .status-btn-wrap { position: relative; }
        .doc-table .status-btn { padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; background: #f1f5f9; cursor: pointer; font-size: 14px; }
        .doc-table .status-btn:hover { background: #e2e8f0; }
        .doc-table .status-dropdown { display: none; position: absolute; left: 0; top: 100%; z-index: 10; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); min-width: 180px; margin-top: 4px; }
        .doc-table .status-dropdown.show { display: block; }
        .doc-table .status-dropdown button { display: block; width: 100%; padding: 10px 14px; text-align: left; border: none; background: none; cursor: pointer; font-size: 14px; }
        .doc-table .status-dropdown button:hover { background: #f1f5f9; }
        .doc-table .actions-btn { padding: 6px 12px; border-radius: 6px; border: 1px solid #2d6cdf; background: #eff6ff; color: #2d6cdf; cursor: pointer; font-size: 14px; }
        .doc-table .actions-btn:hover { background: #dbeafe; }
        .doc-table .actions-popup { display: none; position: absolute; right: 0; top: 100%; z-index: 20; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); min-width: 140px; margin-top: 4px; padding: 8px 0; }
        .doc-table .actions-popup.show { display: block; }
        .doc-table .actions-popup a, .doc-table .actions-popup button { display: block; width: 100%; padding: 10px 16px; text-align: left; border: none; background: none; cursor: pointer; font-size: 14px; color: #333; text-decoration: none; }
        .doc-table .actions-popup a:hover, .doc-table .actions-popup button:hover { background: #f1f5f9; }
        .doc-table .actions-cell { position: relative; }
        .doc-search-form { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .doc-search-form input[type="text"] { flex: 1; min-width: 200px; padding: 12px 16px; border: 2px solid #e0e6ed; border-radius: 8px; font-size: 15px; }
        .doc-search-form input:focus { outline: none; border-color: #2d6cdf; }
        .doc-search-form .btn-search { padding: 12px 24px; background: linear-gradient(135deg, #2d6cdf 0%, #1f4fb3 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .doc-search-form .btn-search:hover { opacity: 0.95; }
        .doc-table .remarks-cell { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
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
    
    <!-- Menu Button Below Top Bar -->
    <div class="menu-button-container">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
    
    <!-- Sidebar Menu -->
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
                    <a href="inbox.php?type=new" class="nav-subitem <?php echo $inboxType == 'new' ? 'active' : ''; ?>">
                        <span class="nav-icon">🆕</span>
                        <span class="nav-text">New</span>
                    </a>
                    <a href="inbox.php?type=incoming" class="nav-subitem <?php echo $inboxType == 'incoming' ? 'active' : ''; ?>">
                        <span class="nav-icon">📥</span>
                        <span class="nav-text">Incoming Routed Documents</span>
                    </a>
                    <a href="inbox.php?type=outgoing" class="nav-subitem <?php echo $inboxType == 'outgoing' ? 'active' : ''; ?>">
                        <span class="nav-icon">📤</span>
                        <span class="nav-text">Outgoing Routed</span>
                    </a>
                    <a href="messages.php" class="nav-subitem">
                        <span class="nav-icon">💬</span>
                        <span class="nav-text">Inbox</span>
                    </a>
                </div>
            </div>
            <?php if($isAdmin): ?>
                <a href="#" class="nav-item">
                    <span class="nav-icon">📄</span>
                    <span class="nav-text">File Management</span>
                </a>
                <a href="new_document.php" class="nav-item">
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
                <a href="#" class="nav-item">
                    <span class="nav-icon">📄</span>
                    <span class="nav-text">File Management</span>
                </a>
                <a href="new_document.php" class="nav-item">
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
            <a href="#" class="nav-item">
                <span class="nav-icon">⚙️</span>
                <span class="nav-text">Settings</span>
            </a>
            <a href="logout.php" class="nav-item logout-item">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </nav>
    </div>
    
    <!-- Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="dashboard-container sidebar-hidden">
        <div class="employees-content">
            <div class="page-header">
                <h1><?php echo $pageTitle; ?></h1>
            </div>
            
            <div class="table-container">
                <form method="get" action="inbox.php" class="doc-search-form">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($inboxType); ?>">
                    <input type="text" name="q" placeholder="Search documents (title, reference no., type, remarks)..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="team" style="padding: 12px 16px; border: 2px solid #e0e6ed; border-radius: 8px; font-size: 15px; min-width: 150px;">
                        <option value="">All Teams</option>
                        <?php foreach ($allTeams as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $filterTeam === $t ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php if (in_array($inboxType, ['incoming', 'outgoing'])): ?>
                    <select name="status" style="padding: 12px 16px; border: 2px solid #e0e6ed; border-radius: 8px; font-size: 15px; min-width: 150px;">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="pending for completion" <?php echo $filterStatus === 'pending for completion' ? 'selected' : ''; ?>>Pending for Completion</option>
                        <option value="reverted" <?php echo $filterStatus === 'reverted' ? 'selected' : ''; ?>>Reverted</option>
                    </select>
                    <?php endif; ?>
                    
                    <select name="route_type" style="padding: 12px 16px; border: 2px solid #e0e6ed; border-radius: 8px; font-size: 15px; min-width: 150px;">
                        <option value="">All Route Types</option>
                        <option value="Internal" <?php echo $filterRouteType === 'Internal' ? 'selected' : ''; ?>>Internal</option>
                        <option value="External" <?php echo $filterRouteType === 'External' ? 'selected' : ''; ?>>External</option>
                        <option value="Urgent" <?php echo $filterRouteType === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                        <option value="Confidential" <?php echo $filterRouteType === 'Confidential' ? 'selected' : ''; ?>>Confidential</option>
                    </select>
                    
                    <button type="submit" class="btn-search">🔍 Search</button>
                    <?php if ($search !== '' || $filterTeam !== '' || $filterStatus !== '' || $filterRouteType !== ''): ?>
                        <a href="inbox.php?type=<?php echo urlencode($inboxType); ?>" class="btn-clear" style="padding: 12px 24px; background: #f1f5f9; color: #64748b; border-radius: 8px; text-decoration: none; font-weight: 600;">Clear</a>
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
                                            <div class="status-btn-wrap">
                                                <button type="button" class="status-btn" data-doc-id="<?php echo (int)$doc['document_id']; ?>" aria-haspopup="true" aria-expanded="false"><?php echo htmlspecialchars($status); ?> ▼</button>
                                                <div class="status-dropdown" id="status-dd-<?php echo (int)$doc['document_id']; ?>">
                                                    <form method="post" action="inbox.php?type=<?php echo urlencode($inboxType); ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">
                                                        <input type="hidden" name="doc_id" value="<?php echo (int)$doc['document_id']; ?>">
                                                        <input type="hidden" name="new_status" value="pending"><button type="submit">Pending</button>
                                                    </form>
                                                    <form method="post" action="inbox.php?type=<?php echo urlencode($inboxType); ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">
                                                        <input type="hidden" name="doc_id" value="<?php echo (int)$doc['document_id']; ?>">
                                                        <input type="hidden" name="new_status" value="completed"><button type="submit">Completed</button>
                                                    </form>
                                                    <form method="post" action="inbox.php?type=<?php echo urlencode($inboxType); ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">
                                                        <input type="hidden" name="doc_id" value="<?php echo (int)$doc['document_id']; ?>">
                                                        <input type="hidden" name="new_status" value="pending for completion"><button type="submit">Pending for completion</button>
                                                    </form>
                                                    <form method="post" action="inbox.php?type=<?php echo urlencode($inboxType); ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">
                                                        <input type="hidden" name="doc_id" value="<?php echo (int)$doc['document_id']; ?>">
                                                        <input type="hidden" name="new_status" value="reverted"><button type="submit">Reverted</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="remarks-cell" title="<?php echo htmlspecialchars($doc['remarks'] ?? ''); ?>"><?php echo htmlspecialchars($doc['remarks'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['recipient_team'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($doc['uploaded_by_name'] ?? '—'); ?></td>
                                        <td class="actions-cell">
                                            <button type="button" class="actions-btn" data-doc-id="<?php echo (int)$doc['document_id']; ?>">Actions ▼</button>
                                            <div class="actions-popup" id="actions-popup-<?php echo (int)$doc['document_id']; ?>">
                                                <a href="/ICLHO_Route/<?php echo htmlspecialchars($doc['file_path'] ?? ''); ?>" target="_blank" rel="noopener">View</a>
                                                <button type="button" class="comments-btn">Comments</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="no-data">No documents found in <?php echo htmlspecialchars(strtolower($pageTitle)); ?>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
            // Keep submenu open on inbox page
            inboxMenu.classList.add('active');
            
            inboxMenu.addEventListener('click', (e) => {
                if (e.target.closest('.nav-item-header')) {
                    inboxMenu.classList.toggle('active');
                }
            });
        }


        // Status button: toggle dropdown and close others
        document.querySelectorAll('.status-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = btn.getAttribute('data-doc-id');
                const dd = document.getElementById('status-dd-' + id);
                if (dd) dd.classList.toggle('show');
                document.querySelectorAll('.status-dropdown').forEach(function(d) {
                    if (d !== dd) d.classList.remove('show');
                });
            });
        });

        // Actions button: toggle popup
        document.querySelectorAll('.actions-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = btn.getAttribute('data-doc-id');
                const popup = document.getElementById('actions-popup-' + id);
                if (popup) {
                    popup.classList.toggle('show');
                    document.querySelectorAll('.actions-popup').forEach(function(p) {
                        if (p !== popup) p.classList.remove('show');
                    });
                }
            });
        });

        document.querySelectorAll('.comments-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                alert('Comments feature will be added here.');
            });
        });

        document.addEventListener('click', function() {
            document.querySelectorAll('.status-dropdown.show, .actions-popup.show').forEach(function(el) {
                el.classList.remove('show');
            });
        });
    </script>
</body>
</html>
