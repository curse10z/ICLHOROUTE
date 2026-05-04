<?php
session_start();
include 'db.php';
require_once 'notifications_utils.php';

if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    header('Location: login.php'); exit;
}

$isAdmin  = isset($_SESSION['admin']);
$userId   = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];
$userType = $isAdmin ? 'admin' : 'employee';

createNotificationsTable($conn);

$uidEsc = mysqli_real_escape_string($conn, $userId);
$utEsc  = mysqli_real_escape_string($conn, $userType);

// ── Filters & pagination ──────────────────────────────────────────────────────
$validTypes = ['all','incoming','outgoing','message','calendar','overdue','escalation'];
$validRead  = ['all','unread','read'];
$filterType = in_array($_GET['ftype'] ?? '', $validTypes) ? $_GET['ftype'] : 'all';
$filterRead = in_array($_GET['fread'] ?? '', $validRead)  ? $_GET['fread'] : 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where = "user_id='$uidEsc' AND user_type='$utEsc'";
if ($filterType !== 'all') { $tEsc = mysqli_real_escape_string($conn, $filterType); $where .= " AND type='$tEsc'"; }
if ($filterRead === 'unread') $where .= ' AND is_read=0';
if ($filterRead === 'read')   $where .= ' AND is_read=1';

$total   = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM notifications WHERE $where"))['c'] ?? 0);
$pages   = max(1, (int)ceil($total / $perPage));
$notifRes = mysqli_query($conn, "SELECT notification_id, type, doc_id, doc_title, reference_no, message, is_read, created_at
    FROM notifications WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$notifications = [];
if ($notifRes) while ($row = mysqli_fetch_assoc($notifRes)) $notifications[] = $row;

// ── Query string helper ───────────────────────────────────────────────────────
function nhQs($extra = []) {
    $base = ['ftype' => $_GET['ftype'] ?? 'all', 'fread' => $_GET['fread'] ?? 'all'];
    $p = array_merge($base, $extra);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== 'all' && $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification History — DRIMS</title>
    <link rel="stylesheet" href="/ICLHO_Route/style.css">
    <style>
        .nh-filter-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:20px; }
        .nh-filter-group { display:flex; gap:4px; background:rgba(255,255,255,.05); border-radius:10px; padding:4px; }
        .nh-filter-btn { background:transparent; border:none; color:rgba(255,255,255,.5); font-size:.76rem; font-weight:600;
            padding:5px 12px; border-radius:7px; cursor:pointer; transition:all .15s; white-space:nowrap; }
        .nh-filter-btn.active, .nh-filter-btn:hover { background:rgba(16,185,129,.2); color:#10b981; }
        .nh-filter-btn.active { color:#6ee7b7; }
        .nh-mark-all { margin-left:auto; background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.3);
            color:#6ee7b7; font-size:.76rem; font-weight:700; padding:6px 14px; border-radius:8px; cursor:pointer; transition:all .15s; }
        .nh-mark-all:hover { background:rgba(16,185,129,.22); }
        .nh-total { font-size:.76rem; color:rgba(255,255,255,.35); }
        .nh-list { display:flex; flex-direction:column; gap:6px; }
        .nh-item { display:flex; align-items:flex-start; gap:14px; padding:14px 18px;
            background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07);
            border-radius:12px; cursor:pointer; transition:all .15s; text-decoration:none; color:inherit; position:relative; }
        .nh-item:hover { background:rgba(16,185,129,.07); border-color:rgba(16,185,129,.2); }
        .nh-item.unread { border-left:3px solid rgba(16,185,129,.55); background:rgba(16,185,129,.05); }
        .nh-item.unread::before { content:''; position:absolute; top:16px; right:16px;
            width:7px; height:7px; border-radius:50%; background:#10b981; }
        .nh-item-icon { width:36px; height:36px; min-width:36px; border-radius:10px; display:flex; align-items:center;
            justify-content:center; }
        .nh-item-body { flex:1; min-width:0; }
        .nh-item-meta { display:flex; align-items:center; gap:8px; margin-bottom:5px; flex-wrap:wrap; }
        .nh-type-badge { font-size:.65rem; font-weight:700; padding:2px 8px; border-radius:5px;
            text-transform:uppercase; letter-spacing:.3px; }
        .nh-item-time { font-size:.7rem; color:rgba(255,255,255,.3); margin-left:auto; white-space:nowrap; }
        .nh-item-msg { font-size:.82rem; color:rgba(255,255,255,.8); line-height:1.45; }
        .nh-item-ref { font-size:.72rem; color:rgba(255,255,255,.35); margin-top:4px; }
        .nh-empty { text-align:center; padding:48px 0; color:rgba(255,255,255,.3); font-size:.9rem; }
        .nh-pagination { display:flex; gap:4px; justify-content:center; margin-top:24px; flex-wrap:wrap; }
        .nh-page-btn { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); color:rgba(255,255,255,.6);
            padding:6px 13px; border-radius:8px; font-size:.78rem; cursor:pointer; text-decoration:none; transition:all .15s; }
        .nh-page-btn:hover, .nh-page-btn.active { background:rgba(16,185,129,.18); border-color:rgba(16,185,129,.35); color:#6ee7b7; }
        .nh-page-btn.active { font-weight:700; pointer-events:none; }
    </style>
</head>
<body>
    <div class="aurora"></div>
    <div class="blob b1"></div><div class="blob b2"></div>
    <div class="blob b3"></div><div class="blob b4"></div>
    <div class="grid-lines"></div>

    <div class="top-bar">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar"><span></span><span></span><span></span></button>
        <img src="/ICLHO_Route/ICLOGO.jpg" alt="Logo" class="top-bar-logo">
        <div class="top-bar-content">
            <div class="top-bar-title">DRIMS</div>
            <div class="top-bar-desc">Document Route Internal Management System</div>
        </div>
        <?php include 'topbar_user.php'; ?>
        <?php include 'notification_bell.php'; ?>
    </div>

    <div class="sidebar hidden" id="sidebar">
        <div class="sidebar-brand">
            <img src="/ICLHO_Route/ICLOGO.jpg" alt="Logo" style="width:36px;height:36px;border-radius:9px;object-fit:cover;">
            <div>
                <div class="sidebar-brand-name">DRIMS</div>
                <div class="sidebar-brand-sub"><?= $isAdmin ? 'Admin' : 'Employee' ?> Panel</div>
            </div>
            <button class="sidebar-close" id="sidebarClose"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="sidebar-section-label">Main Menu</div>
        <nav class="sidebar-nav">
            <a href="<?= $isAdmin ? 'dashboard.php' : 'employee_dashboard.php' ?>" class="nav-item">
                <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span class="nav-text">Dashboard</span>
            </a>
            <div class="nav-item nav-item-parent" id="inboxMenu">
                <div class="nav-item-header">
                    <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 10h16M4 14h10"/><path d="M20 18H4"/></svg>
                    <span class="nav-text">Routing Tray</span>
                    <span class="nav-arrow">▾</span>
                </div>
                <div class="nav-submenu" id="inboxSubmenu">
                    <a href="inbox.php?type=incoming" class="nav-subitem">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M16 17l-4 4-4-4M12 3v18"/></svg>
                        Incoming Routed
                    </a>
                    <a href="inbox.php?type=outgoing" class="nav-subitem">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 7l4-4 4 4M12 3v18"/></svg>
                        Outgoing Routed
                    </a>
                    <a href="messages.php" class="nav-subitem">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Inbox
                    </a>
                </div>
            </div>
            <a href="file_management.php" class="nav-item">
                <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/></svg>
                <span class="nav-text">File Management</span>
            </a>
            <a href="new_document.php" class="nav-item">
                <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span class="nav-text">Document Upload</span>
            </a>
            <a href="notifications_history.php" class="nav-item active">
                <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span class="nav-text">Notifications</span>
            </a>
            <?php if ($isAdmin): ?>
                <a href="employees.php" class="nav-item">
                    <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    <span class="nav-text">Employees</span>
                </a>
                <a href="audit_logs.php" class="nav-item">
                    <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
                    <span class="nav-text">Audit Logs</span>
                </a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container sidebar-hidden">
        <div class="employees-content">
            <div class="page-header">
                <h1>Notification History</h1>
            </div>

            <?php
            $typeLinks  = ['all'=>'All','incoming'=>'Incoming','outgoing'=>'Outgoing','message'=>'Messages','calendar'=>'Monthly Board','overdue'=>'Overdue','escalation'=>'⚠ Escalation'];
            $readLinks  = ['all'=>'All','unread'=>'Unread','read'=>'Read'];
            $typeColors = ['incoming'=>'notif-type-in','outgoing'=>'notif-type-out','message'=>'notif-type-msg','calendar'=>'notif-type-cal','overdue'=>'notif-type-overdue','escalation'=>'notif-type-escalation'];
            $typeLabels = ['incoming'=>'Incoming','outgoing'=>'Outgoing','message'=>'Message','calendar'=>'Monthly Board','overdue'=>'Overdue','escalation'=>'⚠ Escalation'];
            $typeHrefs  = ['incoming'=>'/ICLHO_Route/inbox.php?type=incoming','outgoing'=>'/ICLHO_Route/inbox.php?type=outgoing',
                           'message'=>'/ICLHO_Route/messages.php','calendar'=>'#monthly-board','overdue'=>'/ICLHO_Route/inbox.php?type=incoming',
                           'escalation'=>'/ICLHO_Route/inbox.php?type=incoming'];
            $typeIcons  = [
                'incoming'    => '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M16 17l-4 4-4-4M12 3v18"/></svg>',
                'outgoing'    => '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 7l4-4 4 4M12 3v18"/></svg>',
                'message'     => '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
                'calendar'    => '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
                'overdue'     => '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
                'escalation'  => '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            ];
            ?>

            <div class="nh-filter-bar">
                <div class="nh-filter-group">
                    <?php foreach ($typeLinks as $val => $label): ?>
                        <a href="notifications_history.php?ftype=<?= urlencode($val) ?>&fread=<?= urlencode($filterRead) ?>" class="nh-filter-btn <?= $filterType === $val ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="nh-filter-group">
                    <?php foreach ($readLinks as $val => $label): ?>
                        <a href="notifications_history.php?ftype=<?= urlencode($filterType) ?>&fread=<?= urlencode($val) ?>" class="nh-filter-btn <?= $filterRead === $val ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
                    <?php endforeach; ?>
                </div>
                <span class="nh-total"><?= $total ?> notification<?= $total !== 1 ? 's' : '' ?></span>
                <button class="nh-mark-all" id="nhMarkAll">Mark all read</button>
            </div>

            <div class="nh-list" id="nhList">
                <?php if (!$notifications): ?>
                    <div class="nh-empty">No notifications found.</div>
                <?php else: ?>
                    <?php foreach ($notifications as $n):
                        $nType  = $n['type'] ?? 'incoming';
                        $cls    = $typeColors[$nType] ?? 'notif-type-in';
                        $label  = $typeLabels[$nType] ?? ucfirst($nType);
                        $icon   = $typeIcons[$nType] ?? $typeIcons['incoming'];
                        $href   = $typeHrefs[$nType] ?? '#';
                        $unread = !intval($n['is_read']);
                        $time   = date('M j, Y g:i A', strtotime($n['created_at']));
                    ?>
                    <a class="nh-item <?= $unread ? 'unread' : '' ?>"
                       href="<?= htmlspecialchars($href) ?>"
                       data-id="<?= (int)$n['notification_id'] ?>"
                       data-unread="<?= $unread ? '1' : '0' ?>">
                        <div class="nh-item-icon <?= $cls ?>"><?= $icon ?></div>
                        <div class="nh-item-body">
                            <div class="nh-item-meta">
                                <span class="nh-type-badge notif-type-badge <?= $cls ?>"><?= htmlspecialchars($label) ?></span>
                                <?php if (!empty($n['doc_title']) && in_array($nType, ['incoming','outgoing','overdue'])): ?>
                                    <span style="font-size:.7rem;color:rgba(255,255,255,.35);"><?= htmlspecialchars($n['doc_title']) ?></span>
                                <?php endif; ?>
                                <span class="nh-item-time"><?= htmlspecialchars($time) ?></span>
                            </div>
                            <div class="nh-item-msg"><?= htmlspecialchars($n['message']) ?></div>
                            <?php if (!empty($n['reference_no'])): ?>
                                <div class="nh-item-ref"><?= htmlspecialchars($n['reference_no']) ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($pages > 1): ?>
            <div class="nh-pagination">
                <?php if ($page > 1): ?>
                    <a class="nh-page-btn" href="notifications_history.php?ftype=<?= urlencode($filterType) ?>&fread=<?= urlencode($filterRead) ?>&page=<?= $page-1 ?>">← Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1,$page-3); $i <= min($pages,$page+3); $i++): ?>
                    <a class="nh-page-btn <?= $i===$page?'active':'' ?>" href="notifications_history.php?ftype=<?= urlencode($filterType) ?>&fread=<?= urlencode($filterRead) ?>&page=<?= $i ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <a class="nh-page-btn" href="notifications_history.php?ftype=<?= urlencode($filterType) ?>&fread=<?= urlencode($filterRead) ?>&page=<?= $page+1 ?>">Next →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Sidebar toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar    = document.getElementById('sidebar');
    const overlay    = document.getElementById('sidebarOverlay');
    const closeBtn   = document.getElementById('sidebarClose');
    function openSidebar()  { sidebar.classList.remove('hidden'); sidebar.classList.add('open'); overlay.classList.add('active'); }
    function closeSidebar() { sidebar.classList.add('hidden'); sidebar.classList.remove('open'); overlay.classList.remove('active'); }
    if (menuToggle) menuToggle.addEventListener('click', openSidebar);
    if (closeBtn)   closeBtn.addEventListener('click', closeSidebar);
    if (overlay)    overlay.addEventListener('click', closeSidebar);

    // Routing Tray submenu toggle
    const inboxMenu = document.getElementById('inboxMenu');
    if (inboxMenu) inboxMenu.addEventListener('click', e => { if (e.target.closest('.nav-item-header')) inboxMenu.classList.toggle('active'); });

    // Individual mark-as-read on click
    document.getElementById('nhList').addEventListener('click', function (e) {
        const item = e.target.closest('.nh-item');
        if (!item || item.dataset.unread !== '1') return;
        const nid = item.dataset.id;
        fetch('/ICLHO_Route/mark_notifications_read.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'notification_id=' + encodeURIComponent(nid)
        }).then(() => { item.classList.remove('unread'); item.dataset.unread = '0'; });
    });

    // Mark all read
    document.getElementById('nhMarkAll').addEventListener('click', function () {
        fetch('/ICLHO_Route/mark_notifications_read.php', {
            method: 'POST', credentials: 'same-origin'
        }).then(() => {
            document.querySelectorAll('.nh-item.unread').forEach(el => { el.classList.remove('unread'); el.dataset.unread = '0'; });
            this.textContent = 'All read ✓';
            this.disabled = true;
        });
    });
    </script>
    <script src="/ICLHO_Route/notifications.js"></script>
</body>
</html>
