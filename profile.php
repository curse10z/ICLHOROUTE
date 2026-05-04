<?php
session_start();
include "db.php";
require_once 'audit_utils.php';

if (!isset($_SESSION['admin']) && !isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

$isAdmin  = isset($_SESSION['admin']);
$userId   = $isAdmin ? $_SESSION['admin'] : $_SESSION['employee_id'];
$userType = $isAdmin ? 'admin' : 'employee';
$userName = $isAdmin ? $_SESSION['admin'] : ($_SESSION['employee_name'] ?? '');

// Ensure profile_pic column exists (ignore if already exists)
foreach (["ALTER TABLE `admin` ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL",
          "ALTER TABLE `employees` ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL"] as $_sql) {
    try { mysqli_query($conn, $_sql); } catch (Exception $_e) { /* column already exists */ }
}

$error      = '';
$success    = '';
$picSuccess = isset($_GET['pic']) && $_GET['pic'] === 'updated' ? 'Profile picture updated successfully!' : '';

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $ueCode = $_FILES['profile_pic']['error'];
    $ueMap  = [1=>'File too large (server limit).',2=>'File too large.',3=>'Partial upload.',4=>'No file selected.',6=>'No temp folder.',7=>'Cannot write to disk.',8=>'Upload blocked by extension.'];
    if ($ueCode !== UPLOAD_ERR_OK) {
        $error = $ueMap[$ueCode] ?? 'Upload error code ' . $ueCode;
    } else {
        $file    = $_FILES['profile_pic'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) {
            $error = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = 'File too large (max 2 MB).';
        } else {
            $dir = str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/uploads/profile_pictures/');
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userId);
            $filename = $userType . '_' . $safeName . '.' . $ext;
            $destPath = $dir . DIRECTORY_SEPARATOR . $filename;
            $tbl = $isAdmin ? 'admin' : 'employees';
            $pk  = $isAdmin ? 'username' : 'employee_id';
            $uidEsc = mysqli_real_escape_string($conn, $userId);
            // Delete old file
            $oldRes = mysqli_query($conn, "SELECT profile_pic FROM `$tbl` WHERE `$pk` = '$uidEsc' LIMIT 1");
            if ($oldRes && $oldRow = mysqli_fetch_assoc($oldRes)) {
                if (!empty($oldRow['profile_pic'])) @unlink(__DIR__ . DIRECTORY_SEPARATOR . $oldRow['profile_pic']);
            }
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $relPath = 'uploads/profile_pictures/' . $filename;
                $relEsc  = mysqli_real_escape_string($conn, $relPath);
                $upd = mysqli_query($conn, "UPDATE `$tbl` SET profile_pic = '$relEsc' WHERE `$pk` = '$uidEsc'");
                if ($upd && mysqli_affected_rows($conn) >= 0) {
                    logAudit($conn, $userId, $userType, $userName, 'UPDATE', 'Profile', 'Updated profile picture');
                    header('Location: profile.php?pic=updated');
                    exit();
                } else {
                    $error = 'File saved but DB update failed: ' . mysqli_error($conn);
                }
            } else {
                $error = 'Could not save file. Check that ' . $dir . ' is writable.';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPw = $_POST['current_password'] ?? '';
    $newPw     = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';
    if ($newPw !== $confirmPw) {
        $error = 'New passwords do not match.';
    } elseif (strlen($newPw) < 6) {
        $error = 'New password must be at least 6 characters.';
    } else {
        $uidEsc = mysqli_real_escape_string($conn, $userId);
        if ($isAdmin) {
            $chkRes = mysqli_query($conn, "SELECT password FROM admin WHERE username = '$uidEsc' LIMIT 1");
            $chkRow = $chkRes ? mysqli_fetch_assoc($chkRes) : null;
            $valid  = $chkRow && password_verify($currentPw, $chkRow['password']);
            if ($valid) {
                $hash = password_hash($newPw, PASSWORD_DEFAULT);
                $hashEsc = mysqli_real_escape_string($conn, $hash);
                mysqli_query($conn, "UPDATE admin SET password = '$hashEsc' WHERE username = '$uidEsc'");
                logAudit($conn, $userId, $userType, $userName, 'PASSWORD', 'Profile', 'Admin changed their password');
                $success = 'Password updated successfully.';
            } else { $error = 'Current password is incorrect.'; }
        } else {
            $chkRes = mysqli_query($conn, "SELECT password FROM employees WHERE employee_id = '$uidEsc' LIMIT 1");
            $chkRow = $chkRes ? mysqli_fetch_assoc($chkRes) : null;
            $valid  = $chkRow && password_verify($currentPw, $chkRow['password']);
            if ($valid) {
                $hash = password_hash($newPw, PASSWORD_DEFAULT);
                $hashEsc = mysqli_real_escape_string($conn, $hash);
                mysqli_query($conn, "UPDATE employees SET password = '$hashEsc' WHERE employee_id = '$uidEsc'");
                logAudit($conn, $userId, $userType, $userName, 'PASSWORD', 'Profile', 'Employee changed their password');
                $success = 'Password updated successfully.';
            } else { $error = 'Current password is incorrect.'; }
        }
    }
}

// Fetch user data (including profile_pic)
$uidEsc = mysqli_real_escape_string($conn, $userId);
if ($isAdmin) {
    $res = mysqli_query($conn, "SELECT username, profile_pic FROM admin WHERE username = '$uidEsc' LIMIT 1");
    $userData    = ($res && $row = mysqli_fetch_assoc($res)) ? $row : [];
    $displayName = $userData['username'] ?? $userId;
    $displayTeam = 'ADMIN';
    $profilePic  = $userData['profile_pic'] ?? '';
} else {
    $res = mysqli_query($conn, "SELECT name, team, profile_pic FROM employees WHERE employee_id = '$uidEsc' LIMIT 1");
    $userData    = ($res && $row = mysqli_fetch_assoc($res)) ? $row : [];
    $displayName = $userData['name'] ?? $userName;
    $displayTeam = $userData['team'] ?? '';
    $profilePic  = $userData['profile_pic'] ?? '';
}

$hasPic = !empty($profilePic);
$picUrl  = $hasPic ? '/ICLHO_Route/serve_avatar.php?v=' . time() : '';
$nameParts = explode(' ', trim($displayName));
$initials  = strtoupper(substr($nameParts[0], 0, 1) . (count($nameParts) > 1 ? substr(end($nameParts), 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile Settings — DRIMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/ICLHO_Route/style.css">
<style>
/* ── Profile Page ───────────────────────────────── */
.pf-outer { max-width: 680px; margin: 0 auto; padding: 36px 20px 60px; width: 100%; }

.pf-card { background: #0d1b2e; border: 1px solid rgba(255,255,255,.08); border-radius: 20px; overflow: hidden; box-shadow: 0 24px 64px rgba(0,0,0,.5); }

/* Hero */
.pf-hero { background: linear-gradient(135deg, #0a1628 0%, #0d2a26 60%, #0a1f1a 100%); padding: 36px 32px 28px; display: flex; align-items: center; gap: 24px; position: relative; }
.pf-hero::after { content:''; position:absolute; inset:0; background: radial-gradient(ellipse at 80% 50%, rgba(16,185,129,.08) 0%, transparent 70%); pointer-events:none; }

.pf-avatar-wrap { flex-shrink: 0; }
.pf-avatar-circle {
  width: 90px; height: 90px; border-radius: 50%;
  background: linear-gradient(135deg, rgba(16,185,129,.25), rgba(16,185,129,.08));
  border: 3px solid rgba(16,185,129,.35);
  display: flex; align-items: center; justify-content: center;
  overflow: hidden; cursor: pointer; position: relative;
  transition: border-color .2s;
}
.pf-avatar-circle:hover { border-color: #10b981; }
.pf-avatar-circle img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
.pf-initials-text { font-size: 2rem; font-weight: 800; color: #10b981; line-height:1; position:relative; z-index:1; }
.pf-avatar-cam {
  position: absolute; inset: 0;
  background: rgba(0,0,0,.55);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity .2s;
  color: #fff; z-index: 2;
}
.pf-avatar-circle:hover .pf-avatar-cam { opacity: 1; }

.pf-hero-info { flex:1; }
.pf-hero-name { font-size: 1.4rem; font-weight: 800; color: #f1f5f9; line-height:1.2; }
.pf-hero-role {
  display: inline-block; margin-top: 6px;
  font-size: .68rem; font-weight: 700; color: #10b981;
  text-transform: uppercase; letter-spacing: .6px;
  background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.25);
  border-radius: 6px; padding: 3px 9px;
}
.pf-hero-id { margin-top: 8px; font-size: .78rem; color: rgba(255,255,255,.4); }

/* Body */
.pf-body { padding: 0 32px 32px; }

.pf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 28px; }
@media(max-width:560px){ .pf-row { grid-template-columns:1fr; } }

.pf-section {
  background: rgba(255,255,255,.025);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 14px; padding: 22px 20px;
}
.pf-section-title {
  font-size: .68rem; font-weight: 700; color: rgba(255,255,255,.35);
  text-transform: uppercase; letter-spacing: .7px; margin-bottom: 16px;
}

.pf-info-item { margin-bottom: 14px; }
.pf-info-item:last-child { margin-bottom: 0; }
.pf-info-label { font-size: .72rem; font-weight: 600; color: rgba(255,255,255,.4); margin-bottom: 4px; }
.pf-info-value {
  font-size: .88rem; font-weight: 600; color: #e2e8f0;
  background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07);
  border-radius: 8px; padding: 9px 12px;
}

.pf-field { margin-bottom: 12px; }
.pf-field label { display:block; font-size:.72rem; font-weight:600; color:rgba(255,255,255,.4); margin-bottom:5px; }
.pf-field input {
  width:100%; padding:9px 12px; background:rgba(255,255,255,.05);
  border:1.5px solid rgba(255,255,255,.09); border-radius:8px;
  color:#fff; font-size:.85rem; font-family:inherit; outline:none;
  transition:border-color .2s; box-sizing:border-box;
}
.pf-field input:focus { border-color:#10b981; box-shadow:0 0 0 3px rgba(16,185,129,.1); }

.pf-alert { padding:10px 13px; border-radius:8px; font-size:.8rem; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
.pf-alert--success { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.25); color:#6ee7b7; }
.pf-alert--error   { background:rgba(244,63,94,.1);  border:1px solid rgba(244,63,94,.25);  color:#fda4af; }

.pf-btn {
  width:100%; margin-top:4px; padding:10px; background:#10b981;
  border:none; border-radius:9px; color:#fff; font-weight:700;
  font-size:.85rem; cursor:pointer; font-family:inherit; transition:background .2s;
}
.pf-btn:hover { background:#059669; }
</style>
</head>
<body>
<div class="aurora"></div>
<div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div><div class="blob b4"></div>
<div class="grid-lines"></div>

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

<!-- SIDEBAR -->
<div class="sidebar hidden" id="sidebar">
  <div class="sidebar-brand">
    <img src="/ICLHO_Route/ICLOGO.jpg" alt="Logo" style="width:36px;height:36px;min-width:36px;max-width:36px;border-radius:9px;object-fit:cover;display:block;flex-shrink:0;">
    <div>
      <div class="sidebar-brand-name">DRIMS</div>
      <div class="sidebar-brand-sub"><?php echo $isAdmin ? 'Admin Panel' : 'Employee Panel'; ?></div>
    </div>
    <button class="sidebar-close" id="sidebarClose">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section-label">Main Menu</div>
    <a href="<?php echo $isAdmin ? 'dashboard.php' : 'employee_dashboard.php'; ?>" class="nav-item">
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
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 16V4m0 12l-4-4m4 4l4-4"/><path d="M4 20h16"/></svg>
          Incoming Routed
        </a>
        <a href="inbox.php?type=outgoing" class="nav-subitem">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 8v8m0-8l-4 4m4-4l4 4"/><path d="M4 4h16"/></svg>
          Outgoing Routed
        </a>
        <a href="messages.php" class="nav-subitem">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Inbox
        </a>
      </div>
    </div>
    <a href="file_management.php" class="nav-item">
      <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13,2 13,9 20,9"/></svg>
      <span class="nav-text">File Management</span>
    </a>
    <a href="new_document.php" class="nav-item">
      <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <span class="nav-text">Document Upload</span>
    </a>
    <?php if ($isAdmin): ?>
    <a href="employees.php" class="nav-item">
      <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span class="nav-text">Employees</span>
    </a>
    <a href="audit_logs.php" class="nav-item">
      <svg class="nav-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
      <span class="nav-text">Audit Logs</span>
    </a>
    <?php endif; ?>
  </nav>
</div>

<div class="dashboard-container sidebar-hidden" id="mainContent">
  <div class="pf-outer">
    <div class="pf-card">

      <!-- Hero -->
      <div class="pf-hero">
        <div class="pf-avatar-wrap">
          <form method="POST" enctype="multipart/form-data" id="pfPicForm">
            <label class="pf-avatar-circle" for="pfPicInput">
              <?php if ($hasPic): ?>
                <img src="<?php echo $picUrl; ?>" alt="" id="pfAvatarImg"
                     onerror="this.style.display='none';document.getElementById('pfAvatarInitials').style.display='flex';">
                <span class="pf-initials-text" id="pfAvatarInitials" style="display:none"><?php echo $initials; ?></span>
              <?php else: ?>
                <span class="pf-initials-text" id="pfAvatarInitials"><?php echo $initials; ?></span>
              <?php endif; ?>
              <span class="pf-avatar-cam">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              </span>
            </label>
            <input type="file" id="pfPicInput" name="profile_pic" accept="image/*" style="display:none" onchange="document.getElementById('pfPicForm').submit()">
          </form>
        </div>
        <div class="pf-hero-info">
          <div class="pf-hero-name"><?php echo htmlspecialchars($displayName); ?></div>
          <div class="pf-hero-role"><?php echo htmlspecialchars($displayTeam ?: 'Employee'); ?></div>
          <div class="pf-hero-id"><?php echo htmlspecialchars($userId); ?></div>
          <?php if ($error && empty($success)): ?>
            <div class="pf-alert pf-alert--error" style="margin-top:10px;font-size:.75rem;"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Body -->
      <div class="pf-body">
        <?php if ($picSuccess): ?>
          <div class="pf-alert pf-alert--success" style="margin-top:20px">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
            <?php echo htmlspecialchars($picSuccess); ?>
          </div>
        <?php endif; ?>

        <div class="pf-row">

          <!-- Account Info -->
          <div class="pf-section">
            <div class="pf-section-title">Account Info</div>
            <div class="pf-info-item">
              <div class="pf-info-label">Username / ID</div>
              <div class="pf-info-value"><?php echo htmlspecialchars($userId); ?></div>
            </div>
            <div class="pf-info-item">
              <div class="pf-info-label">Role</div>
              <div class="pf-info-value"><?php echo htmlspecialchars($displayTeam ?: 'Employee'); ?></div>
            </div>
            <?php if (!$isAdmin && !empty($userData['team'])): ?>
            <div class="pf-info-item">
              <div class="pf-info-label">Team</div>
              <div class="pf-info-value"><?php echo htmlspecialchars($userData['team']); ?></div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Change Password -->
          <div class="pf-section">
            <div class="pf-section-title">Change Password</div>
            <?php if ($success): ?>
              <div class="pf-alert pf-alert--success">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                <?php echo htmlspecialchars($success); ?>
              </div>
            <?php endif; ?>
            <?php if ($error && !$picSuccess): ?>
              <div class="pf-alert pf-alert--error">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo htmlspecialchars($error); ?>
              </div>
            <?php endif; ?>
            <form method="POST">
              <div class="pf-field">
                <label>Current Password</label>
                <input type="password" name="current_password" placeholder="Enter current password" required>
              </div>
              <div class="pf-field">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="At least 6 characters" required>
              </div>
              <div class="pf-field">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Repeat new password" required>
              </div>
              <button type="submit" name="change_password" class="pf-btn">Update Password</button>
            </form>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script>
const toggle=document.getElementById('menuToggle'),
      sidebar=document.getElementById('sidebar'),
      overlay=document.getElementById('sidebarOverlay'),
      main=document.getElementById('mainContent'),
      closeBtn=document.getElementById('sidebarClose');
function openSidebar(){sidebar.classList.remove('hidden');main.classList.remove('sidebar-hidden');overlay.classList.add('active');}
function closeSidebar(){sidebar.classList.add('hidden');main.classList.add('sidebar-hidden');overlay.classList.remove('active');}
toggle.addEventListener('click',()=>sidebar.classList.contains('hidden')?openSidebar():closeSidebar());
if(closeBtn)closeBtn.addEventListener('click',closeSidebar);
overlay.addEventListener('click',closeSidebar);
const inboxMenu=document.getElementById('inboxMenu');
if(inboxMenu)inboxMenu.addEventListener('click',e=>{if(e.target.closest('.nav-item-header'))inboxMenu.classList.toggle('active');});
</script>
<script src="/ICLHO_Route/notifications.js"></script>
</body>
</html>
