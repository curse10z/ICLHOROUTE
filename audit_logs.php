<?php
session_start();
include 'db.php';
require_once 'audit_utils.php';

if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit(); }
$userName = $_SESSION['admin'];

createAuditTable($conn);

/* ── Filters ──────────────────────────────────────────────────────── */
$fSearch  = trim($_GET['search']  ?? '');
$fAction  = trim($_GET['action']  ?? '');
$fModule  = trim($_GET['module']  ?? '');
$fUser    = trim($_GET['user']    ?? '');
$fDateFrom= trim($_GET['from']    ?? '');
$fDateTo  = trim($_GET['to']      ?? '');
$page     = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

$where = ['1=1'];
if ($fSearch  !== '') $where[] = "description LIKE '%" . mysqli_real_escape_string($conn, $fSearch) . "%'";
if ($fAction  !== '') $where[] = "action = '"     . mysqli_real_escape_string($conn, $fAction)  . "'";
if ($fModule  !== '') $where[] = "module = '"     . mysqli_real_escape_string($conn, $fModule)  . "'";
if ($fUser    !== '') $where[] = "(user_id LIKE '%" . mysqli_real_escape_string($conn, $fUser) . "%' OR user_name LIKE '%" . mysqli_real_escape_string($conn, $fUser) . "%')";
if ($fDateFrom!== '') $where[] = "created_at >= '" . mysqli_real_escape_string($conn, $fDateFrom) . " 00:00:00'";
if ($fDateTo  !== '') $where[] = "created_at <= '" . mysqli_real_escape_string($conn, $fDateTo)   . " 23:59:59'";

$whereStr = implode(' AND ', $where);

$totalRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM audit_logs WHERE $whereStr");
$total    = ($totalRes && $r = mysqli_fetch_assoc($totalRes)) ? (int)$r['c'] : 0;
$pages    = max(1, ceil($total / $perPage));

$logsRes  = mysqli_query($conn, "SELECT * FROM audit_logs WHERE $whereStr ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$logs = [];
if ($logsRes) while ($r = mysqli_fetch_assoc($logsRes)) $logs[] = $r;

/* ── Distinct filter options ──────────────────────────────────────── */
$actions = []; $r = mysqli_query($conn, "SELECT DISTINCT action FROM audit_logs ORDER BY action"); if ($r) while ($row = mysqli_fetch_assoc($r)) $actions[] = $row['action'];
$modules = []; $r = mysqli_query($conn, "SELECT DISTINCT module FROM audit_logs ORDER BY module"); if ($r) while ($row = mysqli_fetch_assoc($r)) $modules[] = $row['module'];

/* ── Summary stats ────────────────────────────────────────────────── */
$todayCount   = 0; $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM audit_logs WHERE DATE(created_at)=CURDATE()"); if ($r && $row = mysqli_fetch_assoc($r)) $todayCount = (int)$row['c'];
$activeUsers  = 0; $r = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) AS c FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"); if ($r && $row = mysqli_fetch_assoc($r)) $activeUsers = (int)$row['c'];
$topModule    = '—'; $r = mysqli_query($conn, "SELECT module, COUNT(*) AS c FROM audit_logs GROUP BY module ORDER BY c DESC LIMIT 1"); if ($r && $row = mysqli_fetch_assoc($r)) $topModule = $row['module'];

/* ── Action badge colours ─────────────────────────────────────────── */
function actionBadge($action) {
    $map = [
        'LOGIN'    => ['#10b981','rgba(16,185,129,.15)'],
        'LOGOUT'   => ['#94a3b8','rgba(148,163,184,.12)'],
        'CREATE'   => ['#38bdf8','rgba(14,165,233,.15)'],
        'UPDATE'   => ['#fbbf24','rgba(245,158,11,.15)'],
        'DELETE'   => ['#fb7185','rgba(244,63,94,.12)'],
        'UPLOAD'   => ['#a78bfa','rgba(139,92,246,.15)'],
        'ROUTE'    => ['#34d399','rgba(52,211,153,.15)'],
        'STATUS'   => ['#f97316','rgba(249,115,22,.15)'],
        'LOGIN_FAIL'=>['#f43f5e','rgba(244,63,94,.18)'],
        'MESSAGE'  => ['#818cf8','rgba(99,102,241,.15)'],
        'PASSWORD' => ['#e879f9','rgba(232,121,249,.15)'],
    ];
    $c = $map[$action] ?? ['#cbd5e1','rgba(203,213,225,.12)'];
    return "<span style=\"color:{$c[0]};background:{$c[1]};border:1px solid {$c[0]}33;padding:2px 8px;border-radius:12px;font-size:.7rem;font-weight:700;letter-spacing:.4px;white-space:nowrap;\">$action</span>";
}

/* ── Export CSV ───────────────────────────────────────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Log ID','Timestamp','User ID','User Type','User Name','Action','Module','Description','IP Address']);
    $expRes = mysqli_query($conn, "SELECT * FROM audit_logs WHERE $whereStr ORDER BY created_at DESC");
    if ($expRes) while ($row = mysqli_fetch_assoc($expRes)) {
        fputcsv($out, [$row['log_id'],$row['created_at'],$row['user_id'],$row['user_type'],$row['user_name'],$row['action'],$row['module'],$row['description'],$row['ip_address']]);
    }
    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Logs — DRIMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/ICLHO_Route/style.css">
<style>
.audit-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.audit-title  { font-size:1.5rem; font-weight:800; color:#f1f5f9; display:flex; align-items:center; gap:10px; }
.audit-title svg { color:#10b981; }

.stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
.astat { background:linear-gradient(135deg,#0f1a2e,#111c30); border:1px solid rgba(255,255,255,.07); border-radius:14px; padding:16px 20px; display:flex; align-items:center; gap:14px; }
.astat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.astat-val  { font-size:1.6rem; font-weight:800; color:#f1f5f9; line-height:1; }
.astat-lbl  { font-size:.72rem; font-weight:600; color:#8ba3c0; margin-top:3px; letter-spacing:.4px; }

.filter-card { background:rgba(15,26,46,.9); border:1px solid rgba(255,255,255,.07); border-radius:14px; padding:16px 20px; margin-bottom:20px; }
.filter-row  { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
.filter-group { display:flex; flex-direction:column; gap:5px; }
.filter-group label { font-size:.72rem; font-weight:600; color:#8ba3c0; letter-spacing:.4px; text-transform:uppercase; }
.filter-group input,
.filter-group select { background:#0d1b2e; border:1.5px solid rgba(255,255,255,.1); border-radius:8px; color:#e2e8f0; font-size:.84rem; padding:7px 12px; outline:none; transition:border-color .2s; min-width:130px; }
.filter-group input:focus, .filter-group select:focus { border-color:#10b981; }
.filter-search { flex:1; min-width:200px; }
.filter-btns { display:flex; gap:8px; }
.btn-filter  { background:#10b981; color:#fff; border:none; border-radius:8px; padding:8px 18px; font-size:.84rem; font-weight:700; cursor:pointer; transition:background .2s; }
.btn-filter:hover { background:#0ea371; }
.btn-reset   { background:rgba(255,255,255,.06); color:#94a3b8; border:1px solid rgba(255,255,255,.1); border-radius:8px; padding:8px 14px; font-size:.84rem; cursor:pointer; }
.btn-reset:hover { background:rgba(255,255,255,.1); }
.btn-export  { background:rgba(139,92,246,.15); color:#a78bfa; border:1px solid rgba(139,92,246,.3); border-radius:8px; padding:8px 16px; font-size:.84rem; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; text-decoration:none; transition:background .2s; }
.btn-export:hover { background:rgba(139,92,246,.25); }

.audit-table-wrap { background:rgba(15,26,46,.9); border:1px solid rgba(255,255,255,.07); border-radius:14px; overflow:hidden; }
.audit-table { width:100%; border-collapse:collapse; table-layout:fixed; }
.audit-table th { padding:10px 12px; text-align:left; font-size:.7rem; font-weight:700; color:#8ba3c0; letter-spacing:.5px; text-transform:uppercase; background:rgba(255,255,255,.03); border-bottom:1px solid rgba(255,255,255,.07); white-space:nowrap; }
.audit-table td { padding:9px 12px; font-size:.82rem; color:rgba(255,255,255,.82); border-bottom:1px solid rgba(255,255,255,.04); vertical-align:middle; }
.audit-table tbody tr:hover { background:rgba(16,185,129,.05); }
.audit-table tbody tr:last-child td { border-bottom:none; }

.audit-table th:nth-child(1) { width:5%;  }  /* # */
.audit-table th:nth-child(2) { width:14%; }  /* Timestamp */
.audit-table th:nth-child(3) { width:13%; }  /* User */
.audit-table th:nth-child(4) { width:7%;  }  /* Type */
.audit-table th:nth-child(5) { width:9%;  }  /* Action */
.audit-table th:nth-child(6) { width:10%; }  /* Module */
.audit-table th:nth-child(7) { width:33%; }  /* Description */
.audit-table th:nth-child(8) { width:9%;  }  /* IP */

.td-desc { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:0; }
.td-mono { font-family:monospace; font-size:.75rem; color:#64748b; }
.type-admin { color:#10b981; font-weight:700; font-size:.73rem; background:rgba(16,185,129,.12); padding:2px 7px; border-radius:10px; }
.type-emp   { color:#38bdf8; font-weight:700; font-size:.73rem; background:rgba(14,165,233,.12); padding:2px 7px; border-radius:10px; }

.pagination { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; border-top:1px solid rgba(255,255,255,.06); }
.page-info  { font-size:.8rem; color:#64748b; }
.page-links { display:flex; gap:4px; }
.page-links a, .page-links span { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; font-size:.8rem; text-decoration:none; color:#94a3b8; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); }
.page-links a:hover   { background:rgba(16,185,129,.15); color:#10b981; border-color:#10b981; }
.page-links span.cur  { background:#10b981; color:#fff; border-color:#10b981; font-weight:700; }

.no-logs { text-align:center; padding:48px 0; color:#475569; }
.no-logs svg { margin-bottom:12px; opacity:.4; }
</style>
</head>
<body>
<div class="top-bar">
  <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
    <span></span><span></span><span></span>
  </button>
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
    <img src="/ICLHO_Route/ICLOGO.jpg" alt="Logo" style="width:36px;height:36px;min-width:36px;max-width:36px;border-radius:9px;object-fit:cover;display:block;flex-shrink:0;">
    <div>
      <div class="sidebar-brand-name">DRIMS</div>
      <div class="sidebar-brand-sub">Admin Panel</div>
    </div>
    <button class="sidebar-close" id="sidebarClose">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section-label">Main Menu</div>
    <a href="dashboard.php" class="nav-item"><svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span class="nav-text">Dashboard</span></a>
    <div class="nav-item nav-item-parent" id="inboxMenu">
      <div class="nav-item-header">
        <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 10h16M4 14h10"/><path d="M20 18H4"/></svg>
        <span class="nav-text">Routing Tray</span><span class="nav-arrow">▾</span>
      </div>
      <div class="nav-submenu" id="inboxSubmenu">
        <a href="inbox.php?type=incoming" class="nav-subitem"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M16 17l-4 4-4-4M12 3v18"/></svg><span class="nav-text">Incoming Routed Documents</span></a>
        <a href="inbox.php?type=outgoing" class="nav-subitem"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 7l4-4 4 4M12 3v18"/></svg><span class="nav-text">Outgoing Routed</span></a>
        <a href="messages.php" class="nav-subitem"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span class="nav-text">Inbox</span></a>
      </div>
    </div>
    <a href="file_management.php" class="nav-item"><svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13,2 13,9 20,9"/></svg><span class="nav-text">File Management</span></a>
    <a href="new_document.php" class="nav-item"><svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span class="nav-text">Document Upload</span></a>
    <a href="employees.php" class="nav-item"><svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span class="nav-text">Employees</span></a>
    <a href="audit_logs.php" class="nav-item active"><svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg><span class="nav-text">Audit Logs</span></a>
  </nav>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="dashboard-container sidebar-hidden" id="mainContent">
  <div style="padding:28px 32px 60px;">

    <div class="audit-header">
      <div class="audit-title">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
        Audit Logs
      </div>
      <a href="?<?php echo http_build_query(array_merge($_GET, ['export'=>'csv'])); ?>" class="btn-export">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
      </a>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="astat">
        <div class="astat-icon" style="background:rgba(16,185,129,.15);color:#10b981;">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div><div class="astat-val"><?php echo number_format($todayCount); ?></div><div class="astat-lbl">Events Today</div></div>
      </div>
      <div class="astat">
        <div class="astat-icon" style="background:rgba(14,165,233,.15);color:#38bdf8;">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div><div class="astat-val"><?php echo $activeUsers; ?></div><div class="astat-lbl">Active Users (24h)</div></div>
      </div>
      <div class="astat">
        <div class="astat-icon" style="background:rgba(139,92,246,.15);color:#a78bfa;">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        </div>
        <div><div class="astat-val" style="font-size:1.1rem;line-height:1.4;"><?php echo htmlspecialchars($topModule); ?></div><div class="astat-lbl">Top Module</div></div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
      <form method="GET" action="">
        <div class="filter-row">
          <div class="filter-group filter-search">
            <label>Search Description</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($fSearch); ?>" placeholder="Search…">
          </div>
          <div class="filter-group">
            <label>User</label>
            <input type="text" name="user" value="<?php echo htmlspecialchars($fUser); ?>" placeholder="Name or ID…">
          </div>
          <div class="filter-group">
            <label>Action</label>
            <select name="action">
              <option value="">All Actions</option>
              <?php foreach ($actions as $a): ?>
                <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $fAction===$a?'selected':''; ?>><?php echo htmlspecialchars($a); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <label>Module</label>
            <select name="module">
              <option value="">All Modules</option>
              <?php foreach ($modules as $m): ?>
                <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $fModule===$m?'selected':''; ?>><?php echo htmlspecialchars($m); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <label>Date From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($fDateFrom); ?>">
          </div>
          <div class="filter-group">
            <label>Date To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($fDateTo); ?>">
          </div>
          <div class="filter-btns">
            <button type="submit" class="btn-filter">Search</button>
            <a href="audit_logs.php" class="btn-reset">Reset</a>
          </div>
        </div>
      </form>
    </div>

    <!-- Table -->
    <div class="audit-table-wrap">
      <?php if (empty($logs)): ?>
        <div class="no-logs">
          <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
          <p>No audit log entries found.</p>
        </div>
      <?php else: ?>
        <table class="audit-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Timestamp</th>
              <th>User</th>
              <th>Type</th>
              <th>Action</th>
              <th>Module</th>
              <th>Description</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $i => $log): ?>
            <tr>
              <td style="color:#475569;font-size:.75rem;"><?php echo $offset + $i + 1; ?></td>
              <td style="white-space:nowrap;font-size:.78rem;color:#94a3b8;">
                <?php
                  $dt = new DateTime($log['created_at']);
                  echo $dt->format('M d, Y') . '<br><span style="color:#475569;">' . $dt->format('h:i:s A') . '</span>';
                ?>
              </td>
              <td>
                <div style="font-weight:600;color:#e2e8f0;font-size:.82rem;"><?php echo htmlspecialchars($log['user_name']); ?></div>
                <div style="font-size:.72rem;color:#475569;"><?php echo htmlspecialchars($log['user_id']); ?></div>
              </td>
              <td><span class="<?php echo $log['user_type']==='admin'?'type-admin':'type-emp'; ?>"><?php echo strtoupper($log['user_type']); ?></span></td>
              <td><?php echo actionBadge($log['action']); ?></td>
              <td style="color:#94a3b8;font-size:.8rem;"><?php echo htmlspecialchars($log['module']); ?></td>
              <td class="td-desc" title="<?php echo htmlspecialchars($log['description']); ?>"><?php echo htmlspecialchars($log['description']); ?></td>
              <td class="td-mono"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="pagination">
          <div class="page-info">Showing <?php echo $offset+1; ?>–<?php echo min($offset+$perPage,$total); ?> of <?php echo number_format($total); ?> entries</div>
          <div class="page-links">
            <?php
              $qBase = array_merge($_GET, ['p'=>1]); unset($qBase['export']);
              if ($page > 1): $qBase['p'] = $page-1; ?>
                <a href="?<?php echo http_build_query($qBase); ?>">‹</a>
            <?php endif;
              $start = max(1, $page-2); $end = min($pages, $page+2);
              for ($pg=$start; $pg<=$end; $pg++): $qBase['p']=$pg; ?>
                <?php if ($pg===$page): ?><span class="cur"><?php echo $pg; ?></span>
                <?php else: ?><a href="?<?php echo http_build_query($qBase); ?>"><?php echo $pg; ?></a><?php endif; ?>
            <?php endfor;
              if ($page < $pages): $qBase['p']=$page+1; ?>
                <a href="?<?php echo http_build_query($qBase); ?>">›</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
const menuToggle   = document.getElementById('menuToggle');
const sidebar      = document.getElementById('sidebar');
const mainContent  = document.getElementById('mainContent');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar()  { sidebar.classList.remove('hidden'); mainContent.classList.remove('sidebar-hidden'); sidebarOverlay.classList.add('active'); }
function closeSidebar() { sidebar.classList.add('hidden');    mainContent.classList.add('sidebar-hidden');    sidebarOverlay.classList.remove('active'); }

if (menuToggle) menuToggle.addEventListener('click', openSidebar);
document.getElementById('sidebarClose')?.addEventListener('click', closeSidebar);
sidebarOverlay?.addEventListener('click', closeSidebar);

const inboxMenu = document.getElementById('inboxMenu');
if (inboxMenu) inboxMenu.addEventListener('click', e => { if (e.target.closest('.nav-item-header')) inboxMenu.classList.toggle('active'); });
</script>
<script src="/ICLHO_Route/notifications.js"></script>
</body>
</html>
