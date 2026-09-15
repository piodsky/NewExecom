<?php
require_once 'config.php';
requireAccessLevelAdmin();
$currentUser = getCurrentUser();
$activePage  = 'access_levels';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Access Level | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
/* Same card vocabulary as the other screens. Only what the matrix adds is here. */
.po-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem 1.4rem; margin-bottom: 1.1rem; }
.po-card-title { display: flex; align-items: center; gap: .55rem; font-weight: 700; color: var(--navy); font-size: 1rem; margin-bottom: 1rem; }
.po-card-title i { color: var(--primary); font-size: 1.05rem; }

/* The grid scrolls sideways on its own for narrower screens — the role column
   is pinned so you can always see whose permissions you are ticking. Down
   from 19 raw HRMIS columns to the 8 that actually drive this app (2026-09-09
   — see AL_COLUMNS in api/access_levels.php), grouped into a second header
   row that mirrors sidebar.php's own Procurement / Inventory Management /
   System sections, in the same order. */
.al-scroll { overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; }
.al-table { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; margin: 0; font-size: .84rem; }
.al-table th, .al-table td { padding: .5rem .7rem; border-bottom: 1px solid var(--border); white-space: nowrap; }
.al-table thead th {
    position: sticky; z-index: 3; background: #f1f5f9; color: var(--navy);
    font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 700;
    text-align: center; vertical-align: middle; border-bottom: 2px solid var(--border);
}
/* Two-tier header: the group row (Procurement / Inventory Management /
   System) sits above the individual column row. Both sticky, stacked — the
   column row's offset is the group row's own fixed height. */
.al-table thead tr.al-group-row th { top: 0; height: 30px; background: var(--navy); color: #fff; border-bottom: 1px solid rgba(255,255,255,.15); }
.al-table thead tr.al-col-row th   { top: 30px; }
.al-table tbody tr:hover { background: #f8fafc; }
.al-table tbody tr:last-child td { border-bottom: 0; }

.al-role { position: sticky; left: 0; z-index: 2; background: #fff; border-right: 2px solid var(--border); text-align: left !important; min-width: 250px; }
/* The Role header spans both header rows (rowspan) and sits in the corner —
   z-index above every other sticky cell so it never slides under a column
   scrolling past underneath it. */
.al-table thead th.al-role { z-index: 5; background: var(--navy); color: #fff; }
.al-table tbody tr:hover .al-role { background: #f8fafc; }
.al-role-head { display: flex; align-items: center; gap: .5rem; }
/* The id is what every other screen and the legacy apps call a role by, so it
   stays visible — as a chip, matching the avatar chip on System Users. */
.al-role-id {
    flex: 0 0 26px;
    display: inline-flex; align-items: center; justify-content: center;
    width: 26px; height: 26px; border-radius: 50%;
    background: #eef2f7; color: #475569;
    font-size: .72rem; font-weight: 700; font-variant-numeric: tabular-nums;
}
/* Same three tiers, same colours, as the role badge on System Users — a role
   coloured one way there must not look like a different kind of role here. */
.al-role-badge {
    display: inline-block; border-radius: 999px; padding: .15rem .6rem;
    font-size: .74rem; font-weight: 700; letter-spacing: .02em; white-space: nowrap;
}
.al-role-badge.tier-super { background: #fee2e2; color: #991b1b; }
.al-role-badge.tier-admin { background: #fef3c7; color: #92400e; }
.al-role-badge.tier-user  { background: #e0e7ff; color: #3730a3; }
.al-role-sub { font-size: .72rem; color: var(--muted); margin-top: .1rem; }
.al-cell { text-align: center; }

.al-check { width: 17px; height: 17px; cursor: pointer; accent-color: var(--primary); }
.al-check:disabled { cursor: not-allowed; opacity: .35; }
/* "Mixed" is a real state: some people in this role have it, others don't.
   Shown as an indeterminate box so it is never silently rounded to on or off. */
.al-mixed { outline: 2px solid #f59e0b; outline-offset: 2px; border-radius: 3px; }
.al-warn { color: #b45309; font-size: .72rem; margin-top: .15rem; display: flex; align-items: center; gap: .3rem; }
.al-norow { font-size: .72rem; color: #b45309; }
.al-legend { display: flex; flex-wrap: wrap; gap: 1.1rem; font-size: .78rem; color: var(--muted); margin-top: .85rem; }
.al-legend span { display: inline-flex; align-items: center; gap: .4rem; }
.al-swatch { width: 13px; height: 13px; border-radius: 3px; display: inline-block; }
/* The mixed swatch draws an outline 2px outside its box, which eats the gap
   the legend row sets — give that one back. */
.al-legend .al-mixed { margin-right: .2rem; }
.al-saving { opacity: .5; pointer-events: none; }
</style>
</head>
<body>
<div class="wrapper">
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <div class="page-title">System Access Level</div>
                <div class="page-subtitle">Show or hide modules per role, stored in HRMIS.</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="fas fa-triangle-exclamation mt-1"></i>
            <div>
                <strong>These permissions are shared with HRMIS.</strong>
                Every tick writes to <code>system_access_level</code> — the same table the
                legacy VB.NET applications read — and applies to <em>every account holding
                that role</em>, immediately. Only the columns that actually control something
                in <?= e(APP_NAME) ?> are shown below, grouped the same way as the sidebar —
                the other legacy-only HRMIS modules (Maintainance, ICS, ARE, and so on) still
                exist in that table but aren't shown here.
            </div>
        </div>

        <div class="po-card">
            <div class="po-card-title"><i class="fas fa-user-shield"></i> System Roles</div>
            <div class="al-scroll">
                <table class="al-table" id="alTable">
                    <thead id="alHead"></thead>
                    <tbody id="alBody">
                        <tr><td class="text-center text-muted p-4">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="al-legend">
                <span><span class="al-swatch al-mixed" style="background:#fff;border:1px solid #cbd5e1;"></span> Mixed — some people in this role differ</span>
                <span><i class="fas fa-triangle-exclamation" style="color:#b45309;"></i> Some holders of this role are filed under another role and won't be changed</span>
            </div>
            <div class="al-legend">
                <span><span class="al-role-badge tier-super">Role&nbsp;1</span> super administrator &mdash; every screen</span>
                <span><span class="al-role-badge tier-admin">Administrator</span> named an administrator role</span>
                <span><span class="al-role-badge tier-user">Other</span> ordinary account</span>
            </div>
        </div>
    </div>
</div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
<?php include 'profile_modal.php'; ?>
<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const apiUrl = 'api/access_levels.php';
let alColumns = [], alWiring = {};

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
function showToast(msg, type = 'success') {
    const bg = type === 'success' ? 'bg-success' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-danger');
    const el = document.createElement('div');
    el.className = `toast align-items-center text-white ${bg} border-0`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(msg)}</div>`
                 + `<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    document.getElementById('toastContainer').appendChild(el);
    new bootstrap.Toast(el, { delay: 5000 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

async function loadMatrix() {
    try {
        const res  = await fetch(apiUrl);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load access levels.', 'danger');
        alColumns = data.columns || [];
        alWiring  = data.wiring  || {};
        renderHead();
        renderRows(data.roles || []);
    } catch (err) {
        document.getElementById('alBody').innerHTML =
            '<tr><td class="text-danger p-4">Unable to load access levels.</td></tr>';
        console.error(err);
    }
}

// Two header rows: nav_group bands on top (Procurement / Inventory
// Management / System — same order sidebar.php uses), the actual columns
// underneath. Consecutive columns sharing a nav_group collapse into one
// spanning cell rather than repeating the group name per column.
function renderHead() {
    const groups = [];
    alColumns.forEach(c => {
        const last = groups[groups.length - 1];
        if (last && last.name === c.nav_group) last.span++;
        else groups.push({ name: c.nav_group, span: 1 });
    });
    const groupRow =
        '<tr class="al-group-row"><th class="al-role" rowspan="2">Role</th>' +
        groups.map(g => `<th colspan="${g.span}">${escapeHtml(g.name)}</th>`).join('') +
        '</tr>';
    const colRow =
        '<tr class="al-col-row">' +
        alColumns.map(c => {
            const drives = alWiring[c.key] || [];
            const title  = drives.length ? 'Controls: ' + drives.join(', ') : 'Stored, not yet wired to a screen';
            return `<th title="${escapeHtml(title)}">${escapeHtml(c.label)}</th>`;
        }).join('') +
        '</tr>';
    document.getElementById('alHead').innerHTML = groupRow + colRow;
}

// Mirrors roleTier() on System Users and deriveAppRole() in config.php:
// role_id 1 outranks everything, then any role whose name carries
// "Administrator", then everyone else. Deliberately duplicated rather than
// shared — but it must stay in step, or the same role would be coloured one
// way on one screen and another way here.
function roleTier(roleId, roleName) {
    if (Number(roleId) === 1) return 'tier-super';
    return /administrator/i.test(String(roleName || '')) ? 'tier-admin' : 'tier-user';
}

function renderRows(roles) {
    document.getElementById('alBody').innerHTML = roles.map(r => {
        // A role with no rows cannot be edited: an UPDATE would match nothing.
        // Disable it and say why, rather than accepting a click that does nothing.
        const noRows = r.rows_count === 0;
        const sub = [
            `${r.users_count} account${r.users_count === 1 ? '' : 's'}`,
            noRows ? '<span class="al-norow">no access rows</span>'
                   : `${r.rows_count} row${r.rows_count === 1 ? '' : 's'}`,
        ].join(' &middot; ');
        const warnParts = [];
        if (r.stray_users) warnParts.push(`${r.stray_users} filed under another role`);
        if (r.orphan_users) warnParts.push(`${r.orphan_users} with no access row yet — save any box here to create it`);
        const warn = warnParts.length
            ? `<div class="al-warn"><i class="fas fa-triangle-exclamation"></i>${warnParts.join('; ')}</div>`
            : '';
        return `<tr data-role="${r.role_id}">
            <td class="al-role">
                <div class="al-role-head"><span class="al-role-id">${r.role_id}</span><span class="al-role-badge ${roleTier(r.role_id, r.role)}">${escapeHtml(r.role)}</span></div>
                <div class="al-role-sub">${sub}</div>${warn}
            </td>
            ${alColumns.map(c => {
                const state = (r.flags || {})[c.key] || 'off';
                return `<td class="al-cell">
                    <input type="checkbox" class="al-check${state === 'mixed' ? ' al-mixed' : ''}"
                           data-role="${r.role_id}" data-col="${escapeHtml(c.key)}"
                           data-role-name="${escapeHtml(r.role)}" data-label="${escapeHtml(c.label)}"
                           ${state === 'on' ? 'checked' : ''} ${noRows ? 'disabled' : ''}>
                </td>`;
            }).join('')}
        </tr>`;
    }).join('');

    // Indeterminate can only be set from script, never from markup.
    document.querySelectorAll('.al-check.al-mixed').forEach(cb => { cb.indeterminate = true; });
}

async function saveFlag(cb) {
    const wasMixed = cb.classList.contains('al-mixed');
    const value    = cb.checked;
    const label    = `${cb.dataset.label} for ${cb.dataset.roleName}`;
    const verb     = value ? 'Give' : 'Remove';
    const extra    = wasMixed
        ? '\n\nSome accounts in this role currently differ — this overwrites all of them.'
        : '';
    if (!confirm(`${verb} "${cb.dataset.label}" ${value ? 'to' : 'from'} every account with the role `
               + `"${cb.dataset.roleName}"?${extra}`)) {
        // Put the box back exactly as it was, indeterminate included.
        cb.checked = wasMixed ? false : !value;
        cb.indeterminate = wasMixed;
        return;
    }
    const row = cb.closest('tr');
    row.classList.add('al-saving');
    try {
        const res = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ role_id: +cb.dataset.role, column: cb.dataset.col, value }),
        });
        const data = await res.json();
        if (!data.success) {
            cb.checked = wasMixed ? false : !value;
            cb.indeterminate = wasMixed;
            return showToast(data.message || 'Unable to save.', 'danger');
        }
        // Saving resolves a mixed column: everyone in the role now agrees.
        cb.classList.remove('al-mixed');
        cb.indeterminate = false;
        showToast(`${data.message} (${data.affected} row${data.affected === 1 ? '' : 's'})`);
    } catch (err) {
        cb.checked = wasMixed ? false : !value;
        cb.indeterminate = wasMixed;
        showToast('Unable to save.', 'danger');
        console.error(err);
    } finally {
        row.classList.remove('al-saving');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadMatrix();
    // Delegated: the grid is rebuilt on every load, so per-box listeners would
    // be re-bound each time.
    document.getElementById('alBody').addEventListener('change', e => {
        if (e.target.classList.contains('al-check')) saveFlag(e.target);
    });
});
</script>
<?php include 'assistant.php'; ?>
</body>
</html>
