<?php
// topbar.php — included on the right side of every page's .top-navbar
// Requires: $currentUser
// The "My Profile" modal itself lives in profile_modal.php, included separately
// at the end of <body> — .top-navbar has position:sticky + z-index, which
// creates its own stacking context, and a modal nested inside it gets trapped
// below Bootstrap's dynamically-appended .modal-backdrop (unclickable).
$tbUser    = $currentUser ?? ['full_name' => 'User', 'role' => 'user', 'avatar_path' => null];
$tbInitial = strtoupper(substr($tbUser['full_name'], 0, 1));
$tbAvatar  = avatarUrl($tbUser);
require_once __DIR__ . '/date_time.php';
?>
<div class="topbar-search" id="topbarSearch">
    <i class="fas fa-magnifying-glass tsi"></i>
    <input type="text" id="topbarSearchInput" placeholder="Search here..." autocomplete="off">
    <div class="topbar-search-results d-none" id="topbarSearchResults"></div>
</div>

<div class="topbar-actions">
    <?php if (in_array($activePage ?? '', [
        'purchase_requisitions', 'internal_rfq', 'po_internal', 'receiving_reports', 'inventory',
        'stock_transfers', 'issuances', 'outgoing_rfq', 'outgoing_quotations', 'po_outgoing',
        'delivery_reports', 'cost_profit_reports', 'reports', 'branches', 'suppliers', 'clients',
        'items', 'settings', 'access_levels', 'audit_logs',
    ], true)): ?>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="moduleReportButton" title="Preview, print or download the module report">
        <i class="fas fa-file-pdf me-1"></i><span class="d-none d-sm-inline">Report</span>
    </button>
    <?php include __DIR__ . '/report_tools.php'; ?>
    <?php endif; ?>
    <div class="dropdown">
        <button class="topbar-icon-btn" type="button" id="topbarBell" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
            <i class="fas fa-bell"></i>
            <span class="badge-dot d-none" id="topbarBellBadge">0</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end notif-dropdown" aria-labelledby="topbarBell">
            <div class="notif-header">
                <span>Notifications</span>
                <button type="button" onclick="topbarMarkAllRead()">Mark all read</button>
            </div>
            <div class="notif-list" id="topbarNotifList">
                <div class="notif-empty"><i class="fas fa-spinner fa-spin"></i>Loading…</div>
            </div>
        </div>
    </div>

    <div class="dropdown">
        <button class="user-pill" type="button" id="topbarUserMenu" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="up-avatar">
                <?php if ($tbAvatar): ?>
                    <?php /* If the image 404s anyway, swap it for the initial rather
                             than leaving the browser's broken-image icon in a circle. */ ?>
                    <img src="<?= e($tbAvatar) ?>" alt=""
                         onerror="this.replaceWith(document.createTextNode(<?= e(json_encode($tbInitial)) ?>))">
                <?php else: ?>
                    <?= e($tbInitial) ?>
                <?php endif; ?>
            </span>
            <span class="up-name d-none d-sm-inline"><?= e($tbUser['full_name']) ?></span>
            <i class="fas fa-chevron-down up-caret"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end user-dropdown" aria-labelledby="topbarUserMenu">
            <div class="ud-role"><?= e(roleLabel($tbUser['role'])) ?></div>
            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal"><i class="fas fa-user"></i> My Profile</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item text-danger" href="#" onclick="confirmLogout();return false;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</div>

<script>
function topbarEscapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function topbarTimeAgo(iso) {
    const diff = (Date.now() - new Date(iso.replace(' ', 'T'))) / 1000;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

// The real count, not the badge's own text — the badge displays "9+" past
// nine, and parsing THAT back into a number to decrement it would floor
// anything above 9 at 9 forever. Kept in sync by topbarLoadNotifications()
// and stepped down by topbarMarkRead()/topbarMarkAllRead() without a full
// reload, which is what makes clicking a notification feel instant instead
// of waiting for the next time the bell is opened.
let topbarUnreadCount = 0;

function topbarRenderBadge() {
    const badge = document.getElementById('topbarBellBadge');
    if (topbarUnreadCount > 0) {
        badge.textContent = topbarUnreadCount > 9 ? '9+' : topbarUnreadCount;
        badge.classList.remove('d-none');
    } else {
        badge.classList.add('d-none');
    }
}

async function topbarLoadNotifications() {
    const list = document.getElementById('topbarNotifList');
    try {
        const res  = await fetch('api/notifications.php');
        const data = await res.json();
        if (!data.success) return;

        topbarUnreadCount = data.unread_count || 0;
        topbarRenderBadge();

        if (!data.notifications.length) {
            list.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash"></i>No notifications yet.</div>';
            return;
        }
        list.innerHTML = data.notifications.map(n => `
            <a href="${topbarEscapeHtml(n.link) || '#'}" class="notif-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}" onclick="topbarMarkRead('${n.id}', this)">
                <div class="ni-title">${topbarEscapeHtml(n.title)}</div>
                ${n.message ? `<div class="ni-message">${topbarEscapeHtml(n.message)}</div>` : ''}
                <div class="ni-time">${topbarTimeAgo(n.created_at)}</div>
            </a>
        `).join('');
    } catch (err) {
        list.innerHTML = '<div class="notif-empty">Failed to load notifications.</div>';
        console.error(err);
    }
}

// Marks read the moment it's clicked — the unread highlight clears and the
// badge count steps down right away (Facebook-style), instead of only
// catching up the next time the bell dropdown is reopened. el is the clicked
// <a> itself (passed as `this` from the inline handler) rather than looked
// up again by data-id, since we already have it for free.
function topbarMarkRead(id, el) {
    if (el && el.classList.contains('unread')) {
        el.classList.remove('unread');
        topbarUnreadCount = Math.max(0, topbarUnreadCount - 1);
        topbarRenderBadge();
    }
    fetch('api/notifications.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'mark_read', id }),
    }).catch(() => {});
}

async function topbarMarkAllRead() {
    try {
        await fetch('api/notifications.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'mark_all_read' }),
        });
        topbarLoadNotifications();
    } catch (err) { console.error(err); }
}

document.getElementById('topbarBell')?.addEventListener('show.bs.dropdown', topbarLoadNotifications);
topbarLoadNotifications();

// Quick nav search — filters the already permission-filtered sidebar links
// rendered on this page, rather than keeping a second hardcoded page list
// that could drift out of sync with what a given role can actually see.
(function () {
    const input   = document.getElementById('topbarSearchInput');
    const results = document.getElementById('topbarSearchResults');
    if (!input || !results) return;

    function navLinks() {
        return [...document.querySelectorAll('.sidebar-link')].filter(a => !a.classList.contains('disabled'));
    }

    function render(matches) {
        if (!matches.length) {
            results.innerHTML = '<div class="topbar-search-empty">No matching pages.</div>';
            results.classList.remove('d-none');
            return;
        }
        results.innerHTML = matches.map((a, i) => {
            const icon = a.querySelector('.nav-icon i')?.outerHTML || '<i class="fas fa-circle"></i>';
            const label = a.textContent.trim();
            return `<a href="${a.getAttribute('href')}" class="${i === 0 ? 'tsr-active' : ''}">${icon}<span>${topbarEscapeHtml(label)}</span></a>`;
        }).join('');
        results.classList.remove('d-none');
    }

    function search() {
        const q = input.value.trim().toLowerCase();
        if (!q) { results.classList.add('d-none'); results.innerHTML = ''; return; }
        const matches = navLinks().filter(a => a.textContent.trim().toLowerCase().includes(q));
        render(matches);
    }

    input.addEventListener('input', search);
    input.addEventListener('focus', () => { if (input.value.trim()) search(); });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const first = results.querySelector('a');
            if (first) window.location.href = first.getAttribute('href');
        } else if (e.key === 'Escape') {
            input.blur();
            results.classList.add('d-none');
        }
    });
    document.addEventListener('click', (e) => {
        if (!document.getElementById('topbarSearch').contains(e.target)) {
            results.classList.add('d-none');
        }
    });
})();
</script>
