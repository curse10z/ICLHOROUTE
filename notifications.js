/* notifications.js — DRIMS polling-based notification system */
(function () {
    'use strict';

    const ENDPOINT    = '/ICLHO_Route/get_notifications.php';
    const SSE_URL     = '/ICLHO_Route/notifications_sse.php';
    const MARK_READ   = '/ICLHO_Route/mark_notifications_read.php';
    const POLL_MS     = 10000; // fallback polling interval (ms)

    let _prevCount    = -1;
    let _dropOpen     = false;
    let _pollTimer    = null;
    let _es           = null;

    /* ── User account dropdown ────────────────────────────────── */
    const userBtn      = document.getElementById('topbarUserBtn');
    const userDropdown = document.getElementById('topbarUserDropdown');
    const userWrap     = document.getElementById('topbarUserWrap');

    if (userBtn && userDropdown) {
        let _userOpen = false;

        userBtn.addEventListener('click', e => {
            e.stopPropagation();
            _userOpen = !_userOpen;
            userBtn.classList.toggle('open', _userOpen);
            userDropdown.classList.toggle('open', _userOpen);
        });

        document.addEventListener('click', e => {
            if (_userOpen && userWrap && !userWrap.contains(e.target)) {
                _userOpen = false;
                userBtn.classList.remove('open');
                userDropdown.classList.remove('open');
            }
        });
    }

    const bellBtn     = document.getElementById('notifBellBtn');
    const badge       = document.getElementById('notifBadge');
    const dropdown    = document.getElementById('notifDropdown');
    const listEl      = document.getElementById('notifList');
    const markReadBtn = document.getElementById('notifMarkRead');

    if (!bellBtn) return;

    /* ── Sidebar unread message badge ────────────────────────── */
    function updateSidebarMsgBadge(notifications) {
        const link = document.querySelector('a.nav-subitem[href*="messages.php"]');
        if (!link) return;
        const unread = notifications.filter(n => n.type === 'message' && !parseInt(n.is_read)).length;
        let badge = document.getElementById('sidebarMsgBadge');
        if (!badge) {
            badge = document.createElement('span');
            badge.id = 'sidebarMsgBadge';
            badge.style.cssText = 'background:rgba(139,92,246,.85);color:#fff;border-radius:10px;padding:1px 7px;font-size:.62rem;font-weight:700;margin-left:auto;line-height:1.6;flex-shrink:0;';
            link.style.display = 'flex';
            link.style.alignItems = 'center';
            link.appendChild(badge);
        }
        badge.textContent  = unread > 99 ? '99+' : unread;
        badge.style.display = unread > 0 ? 'inline' : 'none';
    }

    /* ── Fetch & render ───────────────────────────────────────── */
    function fetchNotifications(showToast) {
        fetch(ENDPOINT, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                const count = data.count || 0;
                updateBadge(count);

                if (showToast && _prevCount >= 0 && count > _prevCount) {
                    const newOnes = (data.notifications || [])
                        .filter(n => !n.is_read || parseInt(n.is_read) === 0)
                        .slice(0, count - _prevCount);
                    newOnes.forEach(n => showToastNotif(n));
                }

                _prevCount = count;
                renderList(data.notifications || []);
                updateSidebarMsgBadge(data.notifications || []);
            })
            .catch(() => {});
    }

    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    function renderList(notifications) {
        if (!notifications.length) {
            listEl.innerHTML = '<div class="notif-empty">No notifications yet</div>';
            return;
        }
        const MSG_ICON     = '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
        const CAL_ICON     = '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
        const OVERDUE_ICON     = '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        const ESCALATION_ICON = '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        const PRIO_LABEL = { normal:'Normal', low:'Low', important:'Important', urgent:'Urgent' };
        listEl.innerHTML = notifications.map(n => {
            const isUnread      = !parseInt(n.is_read);
            const isMsg         = n.type === 'message';
            const isCal         = n.type === 'calendar';
            const isOverdue     = n.type === 'overdue';
            const isEscalation  = n.type === 'escalation';
            const icon          = isMsg ? MSG_ICON
                : isCal ? CAL_ICON
                : isOverdue ? OVERDUE_ICON
                : isEscalation ? ESCALATION_ICON
                : n.type === 'incoming'
                    ? '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M16 17l-4 4-4-4M12 3v18"/></svg>'
                    : '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 7l4-4 4 4M12 3v18"/></svg>';
            const typeLabel     = isMsg ? 'Message' : isCal ? 'Monthly Board' : isOverdue ? 'Overdue' : isEscalation ? '⚠ Escalation' : n.type === 'incoming' ? 'Incoming' : 'Outgoing';
            const typeCls       = isMsg ? 'notif-type-msg' : isCal ? 'notif-type-cal' : isOverdue ? 'notif-type-overdue' : isEscalation ? 'notif-type-escalation' : n.type === 'incoming' ? 'notif-type-in' : 'notif-type-out';
            const timeStr       = formatTime(n.created_at);
            const href          = isMsg ? '/ICLHO_Route/messages.php'
                : isCal ? '#monthly-board'
                : (isOverdue || isEscalation) ? '/ICLHO_Route/inbox.php?type=incoming'
                : n.type === 'incoming' ? '/ICLHO_Route/inbox.php?type=incoming'
                : '/ICLHO_Route/inbox.php?type=outgoing';
            const calExtra  = isCal
                ? `<div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
                      <span class="notif-cal-prio" data-p="${escH(n.doc_title)}">${escH(PRIO_LABEL[n.doc_title] || 'Event')}</span>
                      ${n.reference_no ? `<span style="font-size:.68rem;color:rgba(255,255,255,.35);">${escH(n.reference_no)}</span>` : ''}
                   </div>`
                : (n.reference_no ? `<div class="notif-item-ref">${escH(n.reference_no)}</div>` : '');
            return `<a class="notif-item${isUnread ? ' unread' : ''}" href="${href}" style="text-decoration:none;color:inherit;display:flex;gap:10px;padding:10px 16px;">
                <div class="notif-item-icon ${typeCls}">${icon}</div>
                <div class="notif-item-content">
                    <div class="notif-item-meta">
                        <span class="notif-type-badge ${typeCls}">${typeLabel}</span>
                        <span class="notif-item-time">${escH(timeStr)}</span>
                    </div>
                    <div class="notif-item-msg">${escH(n.message)}</div>
                    ${calExtra}
                </div>
            </a>`;
        }).join('');
    }

    /* ── Bell toggle ──────────────────────────────────────────── */
    bellBtn.addEventListener('click', e => {
        e.stopPropagation();
        _dropOpen = !_dropOpen;
        dropdown.classList.toggle('open', _dropOpen);
        if (_dropOpen) {
            fetch(MARK_READ, { method: 'POST', credentials: 'same-origin' })
                .then(() => { updateBadge(0); _prevCount = 0; })
                .catch(() => {});
        }
    });

    document.addEventListener('click', e => {
        if (_dropOpen && !document.getElementById('notifBellWrap').contains(e.target)) {
            _dropOpen = false;
            dropdown.classList.remove('open');
        }
    });

    /* ── Mark all read button ─────────────────────────────────── */
    if (markReadBtn) {
        markReadBtn.addEventListener('click', e => {
            e.stopPropagation();
            fetch(MARK_READ, { method: 'POST', credentials: 'same-origin' })
                .then(() => { updateBadge(0); _prevCount = 0; fetchNotifications(false); })
                .catch(() => {});
        });
    }

    /* ── Toast ────────────────────────────────────────────────── */
    function showToastNotif(n) {
        let container = document.getElementById('notifToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifToastContainer';
            container.className = 'notif-toast-container';
            document.body.appendChild(container);
        }

        const toast      = document.createElement('div');
        const isMsg         = n.type === 'message';
        const isIn          = n.type === 'incoming';
        const isCal         = n.type === 'calendar';
        const isOverdue     = n.type === 'overdue';
        const isEscalation  = n.type === 'escalation';
        toast.className     = 'notif-toast notif-toast--' + n.type;
        const toastIcon     = isMsg
            ? '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'
            : isCal
                ? '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
                : isEscalation
                    ? '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
                    : isOverdue
                        ? '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                        : isIn
                            ? '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M16 17l-4 4-4-4M12 3v18"/></svg>'
                            : '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 7l4-4 4 4M12 3v18"/></svg>';
        const toastLabel    = isMsg ? 'New Message' : isCal ? 'Monthly Board' : isEscalation ? '⚠ Escalation' : isOverdue ? 'Overdue Document' : isIn ? 'Incoming Document' : 'Outgoing Document';
        toast.innerHTML = `
            <div class="notif-toast-icon">${toastIcon}</div>
            <div class="notif-toast-body">
                <div class="notif-toast-type">${toastLabel}</div>
                <div class="notif-toast-msg">${escH(n.message)}</div>
            </div>
            <button class="notif-toast-close" aria-label="Dismiss">&times;</button>`;

        container.appendChild(toast);

        requestAnimationFrame(() => toast.classList.add('visible'));

        const dismiss = () => {
            toast.classList.remove('visible');
            setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
        };

        toast.querySelector('.notif-toast-close').addEventListener('click', dismiss);
        setTimeout(dismiss, 5000);
    }

    /* ── SSE (real-time) with polling fallback ────────────────── */
    function startSSE() {
        if (!window.EventSource) { startPolling(); return; }

        _es = new EventSource(SSE_URL, { withCredentials: true });

        _es.onmessage = function (e) {
            try {
                const data  = JSON.parse(e.data);
                const count = data.count || 0;
                updateBadge(count);
                if (_prevCount >= 0 && count > _prevCount) {
                    const newOnes = (data.notifications || [])
                        .filter(n => !n.is_read || parseInt(n.is_read) === 0)
                        .slice(0, count - _prevCount);
                    newOnes.forEach(n => showToastNotif(n));
                    // Dispatch events so inbox page can react in real-time
                    if (newOnes.some(n => n.type === 'incoming')) window.dispatchEvent(new Event('drims-new-incoming'));
                    if (newOnes.some(n => n.type === 'outgoing')) window.dispatchEvent(new Event('drims-new-outgoing'));
                }
                _prevCount = count;
                renderList(data.notifications || []);
                updateSidebarMsgBadge(data.notifications || []);
            } catch (_) {}
        };

        _es.onerror = function () {
            _es.close();
            _es = null;
            // Reconnect after 5 s
            setTimeout(startSSE, 5000);
        };
    }

    function startPolling() {
        fetchNotifications(false);
        _pollTimer = setInterval(() => fetchNotifications(true), POLL_MS);
    }

    // Initial fetch so the bell is populated immediately on page load,
    // then SSE keeps it updated in real-time.
    fetchNotifications(false);
    startSSE();

    // Reconnect SSE when the tab becomes visible again
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && (!_es || _es.readyState === EventSource.CLOSED)) {
            startSSE();
        }
    });

    /* ── Helpers ──────────────────────────────────────────────── */
    function escH(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatTime(ts) {
        if (!ts) return '';
        const d    = new Date(ts.replace(' ', 'T'));
        const now  = new Date();
        const diff = Math.floor((now - d) / 1000);
        if (diff < 60)   return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return d.toLocaleDateString();
    }
})();
