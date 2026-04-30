<?php
$_tu_isAdmin  = isset($_SESSION['admin']);
$_tu_userId   = $_tu_isAdmin ? ($_SESSION['admin'] ?? '') : ($_SESSION['employee_id'] ?? '');
$_tu_picUrl   = '';

if ($_tu_isAdmin) {
    $_tu_displayName = htmlspecialchars($_SESSION['admin'] ?? 'Admin');
    $_tu_displaySub  = 'ADMIN';
    if (!empty($_tu_userId) && isset($conn)) {
        $_tu_uidEsc = mysqli_real_escape_string($conn, $_tu_userId);
        $_tu_pr = mysqli_query($conn, "SELECT profile_pic FROM admin WHERE username = '$_tu_uidEsc' LIMIT 1");
        if ($_tu_pr && $_tu_prow = mysqli_fetch_assoc($_tu_pr)) {
            $_tu_pic = $_tu_prow['profile_pic'] ?? '';
            if (!empty($_tu_pic))
                $_tu_picUrl = '/ICLHO_Route/serve_avatar.php';
        }
    }
} else {
    $_tu_displayName = htmlspecialchars($_SESSION['employee_name'] ?? 'Employee');
    $_tu_empTeam = '';
    if (!empty($_tu_userId) && isset($conn)) {
        $_tu_empId = mysqli_real_escape_string($conn, $_tu_userId);
        $_tu_tr = mysqli_query($conn, "SELECT team, profile_pic FROM employees WHERE employee_id = '$_tu_empId' LIMIT 1");
        if ($_tu_tr && $_tu_trow = mysqli_fetch_assoc($_tu_tr)) {
            $_tu_empTeam = $_tu_trow['team'] ?? '';
            $_tu_pic     = $_tu_trow['profile_pic'] ?? '';
            if (!empty($_tu_pic))
                $_tu_picUrl = '/ICLHO_Route/serve_avatar.php';
        }
    }
    $_tu_displaySub = htmlspecialchars($_tu_empTeam ?: 'Employee');
}
?>
<div class="topbar-user-wrap" id="topbarUserWrap">
    <button class="topbar-user-chip" id="topbarUserBtn" aria-label="Account menu">
        <div class="topbar-user-avatar">
            <?php if (!empty($_tu_picUrl)): ?>
                <img src="<?php echo htmlspecialchars($_tu_picUrl); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:7px;">
            <?php else: ?>
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                </svg>
            <?php endif; ?>
        </div>
        <div class="topbar-user-info">
            <span class="topbar-user-name"><?php echo $_tu_displayName; ?></span>
            <span class="topbar-user-sub"><?php echo $_tu_displaySub; ?></span>
        </div>
        <svg class="topbar-user-chevron" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path d="M6 9l6 6 6-6"/>
        </svg>
    </button>
    <div class="topbar-user-dropdown" id="topbarUserDropdown">
        <div class="tud-header">
            <div class="tud-avatar-lg">
                <?php if (!empty($_tu_picUrl)): ?>
                    <img src="<?php echo htmlspecialchars($_tu_picUrl); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:9px;">
                <?php else: ?>
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                <?php endif; ?>
            </div>
            <div>
                <div class="tud-name"><?php echo $_tu_displayName; ?></div>
                <div class="tud-role"><?php echo $_tu_displaySub; ?></div>
            </div>
        </div>
        <div class="tud-divider"></div>
        <a href="/ICLHO_Route/profile.php" class="tud-item">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            Profile Settings
        </a>
        <a href="/ICLHO_Route/logout.php" class="tud-item tud-item--danger">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Logout
        </a>
    </div>
</div>
