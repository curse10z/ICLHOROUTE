<?php
$_nb_isAdmin  = isset($_SESSION['admin']);
$_nb_userId   = $_nb_isAdmin ? ($_SESSION['admin'] ?? '') : ($_SESSION['employee_id'] ?? '');
$_nb_userType = $_nb_isAdmin ? 'admin' : 'employee';
?>
<script>
/* Apply saved theme immediately — prevents flash of wrong theme */
(function () {
    if (localStorage.getItem('drims_theme') === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
    }
}());
</script>

<button class="theme-toggle-btn" id="themeToggleBtn" aria-label="Toggle light/dark mode" title="Toggle light/dark mode">
    <svg class="icon-sun" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="5"/>
        <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
    </svg>
    <svg class="icon-moon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
    </svg>
</button>

<div class="notif-bell-wrap" id="notifBellWrap">
    <button class="notif-bell-btn" id="notifBellBtn" aria-label="Notifications">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="notif-badge" id="notifBadge" style="display:none">0</span>
    </button>
    <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-dropdown-header">
            <span>Notifications</span>
            <button class="notif-mark-read-btn" id="notifMarkRead">Mark all read</button>
        </div>
        <div class="notif-list" id="notifList">
            <div class="notif-empty">No notifications yet</div>
        </div>
        <a href="/ICLHO_Route/notifications_history.php" class="notif-view-all">View all notifications →</a>
    </div>
</div>
<script>
window.DRIMS_NOTIF_USER = {
    id:   <?php echo json_encode($_nb_userId); ?>,
    type: <?php echo json_encode($_nb_userType); ?>
};

document.getElementById('themeToggleBtn').addEventListener('click', function () {
    const html    = document.documentElement;
    const isLight = html.getAttribute('data-theme') === 'light';
    const next    = isLight ? 'dark' : 'light';
    if (next === 'dark') {
        html.removeAttribute('data-theme');
    } else {
        html.setAttribute('data-theme', next);
    }
    localStorage.setItem('drims_theme', next);
    document.cookie = 'drims_theme=' + next + '; path=/; max-age=31536000; SameSite=Lax';
});
</script>
