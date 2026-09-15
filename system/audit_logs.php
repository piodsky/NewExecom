<?php
require_once 'config.php';
requireSystemToolsAdmin();
$currentUser = getCurrentUser();
$activePage  = 'audit_logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Log | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="vendor/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.al-filters { display: flex; flex-wrap: wrap; gap: .6rem; align-items: flex-end; }
.al-filters .al-field { display: flex; flex-direction: column; gap: .2rem; }
.al-filters label { font-size: .72rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
.al-filters input, .al-filters select { min-width: 150px; }
/* The checkbox is not a data entry field — it must not inherit the 150px
   width or the uppercase field-label styling above. */
.al-filters input[type="checkbox"] { min-width: 0; }
.al-filters .form-check-label { text-transform: none; letter-spacing: 0; font-size: .8rem; font-weight: 500; color: var(--text); }
.al-filters .al-grow { flex: 1 1 220px; }

/* One colour per module, so a run of rows from the same area of the system
   reads as a block rather than as a wall of identical text. */
.al-mod {
    display: inline-block; border-radius: 999px; padding: .1rem .55rem;
    font-size: .7rem; font-weight: 700; letter-spacing: .02em; white-space: nowrap;
    background: #e5e7eb; color: #374151;
}
.al-mod[data-m="purchase_orders"] { background: #dbeafe; color: #1e40af; }
.al-mod[data-m="master_data"]     { background: #ede9fe; color: #5b21b6; }
.al-mod[data-m="inspection"]      { background: #dcfce7; color: #166534; }
.al-mod[data-m="auth"]            { background: #f1f5f9; color: #475569; }
.al-mod[data-m="settings"]        { background: #fee2e2; color: #991b1b; }
.al-mod[data-m="access_levels"]   { background: #fef3c7; color: #92400e; }
.al-mod[data-m="reports"]         { background: #cffafe; color: #155e75; }

/* Deletions are the rows anyone scanning this page is looking for. */
.al-action { font-weight: 600; font-size: .8rem; white-space: nowrap; }
.al-action.is-delete { color: #b91c1c; }
.al-action.is-create { color: #15803d; }

.al-when { white-space: nowrap; font-variant-numeric: tabular-nums; font-size: .82rem; }
.al-when small { display: block; color: var(--muted); }
.al-user { font-weight: 600; }
.al-details { font-size: .84rem; }
.al-ip { color: var(--muted); font-size: .76rem; font-variant-numeric: tabular-nums; }
#auditTable td { vertical-align: top; }
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
                <div class="page-title">Activity Log</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Activity Log</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel mb-3">
            <div class="panel-body">
                <div class="al-filters">
                    <div class="al-field"><label for="alFrom">From</label>
                        <input type="date" id="alFrom" class="form-control form-control-sm"></div>
                    <div class="al-field"><label for="alTo">To</label>
                        <input type="date" id="alTo" class="form-control form-control-sm"></div>
                    <div class="al-field"><label for="alUser">User</label>
                        <select id="alUser" class="form-select form-select-sm"><option value="">Everyone</option></select></div>
                    <div class="al-field"><label for="alModule">Module</label>
                        <select id="alModule" class="form-select form-select-sm"><option value="">All modules</option></select></div>
                    <div class="al-field"><label for="alAction">Action</label>
                        <select id="alAction" class="form-select form-select-sm"><option value="">All actions</option></select></div>
                    <div class="al-field al-grow"><label for="alQuery">Search</label>
                        <input type="text" id="alQuery" class="form-control form-control-sm" placeholder="PO number, user, action…"></div>
                    <div class="al-field">
                        <label>&nbsp;</label>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="alIncludeAuth" onchange="loadLogs()">
                            <label class="form-check-label small" for="alIncludeAuth">Include sign-ins</label>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-primary-app" onclick="loadLogs()"><i class="fas fa-filter me-1"></i> Filter</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()"><i class="fas fa-eraser me-1"></i> Clear</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="exportCsv()"><i class="fas fa-file-csv me-1"></i> CSV</button>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Who changed what</span>
                <span class="text-muted small" id="alCount"></span>
            </div>
            <div class="panel-body">
                <div id="alCapped" class="alert alert-warning py-2 px-3 small d-none"></div>
                <div id="alHint" class="text-muted small mb-2"><i class="fas fa-circle-info me-1"></i>Sign-ins and sign-outs are hidden &mdash; tick <em>Include sign-ins</em> to see them.</div>
                <table id="auditTable" class="app-table w-100">
                    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Details</th><th>IP</th></tr></thead>
                    <tbody id="auditBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/jquery.dataTables.min.js"></script>
<script src="vendor/js/dataTables.bootstrap5.min.js"></script>
<script>
function showToast(msg,type='success'){const id='t'+Date.now();const bg=type==='success'?'bg-success':type==='error'?'bg-danger':'bg-warning text-dark';document.getElementById('toastContainer').insertAdjacentHTML('beforeend',`<div id="${id}" class="toast align-items-center text-white ${bg} border-0"><div class="d-flex"><div class="toast-body">${msg}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);const t=new bootstrap.Toast(document.getElementById(id),{delay:4000});t.show();document.getElementById(id).addEventListener('hidden.bs.toast',()=>document.getElementById(id)?.remove());}
function escapeHtml(v){if(v===null||v===undefined)return '';return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}

let dt = null;
let currentRows = [];
let filtersLoaded = false;

function fmtWhen(v) {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d)) return escapeHtml(v);
    return `${d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })}`
         + `<small>${d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })}</small>`;
}

// CREATE_PO -> "Create PO". The stored action is a machine token; nobody
// should have to read SCREAMING_SNAKE_CASE to audit their own office.
function prettyAction(action) {
    const a = String(action || '');
    return a.toLowerCase().split('_')
        .map(w => w === 'po' || w === 'hrmis' ? w.toUpperCase() : w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

function actionClass(action) {
    const a = String(action || '').toUpperCase();
    if (a.startsWith('DELETE') || a.startsWith('REMOVE')) return 'is-delete';
    if (a.startsWith('CREATE') || a.startsWith('IMPORT')) return 'is-create';
    return '';
}

function fillSelect(id, values, keep) {
    const sel = document.getElementById(id);
    const first = sel.querySelector('option').outerHTML;
    sel.innerHTML = first + values.map(v =>
        `<option value="${escapeHtml(v)}"${v === keep ? ' selected' : ''}>${escapeHtml(v)}</option>`).join('');
}

async function loadLogs() {
    const params = new URLSearchParams({
        from:   document.getElementById('alFrom').value,
        to:     document.getElementById('alTo').value,
        user:   document.getElementById('alUser').value,
        module: document.getElementById('alModule').value,
        action: document.getElementById('alAction').value,
        q:      document.getElementById('alQuery').value.trim(),
        include_auth: document.getElementById('alIncludeAuth').checked ? '1' : '',
    });
    try {
        const res = await fetch('api/audit_logs.php?' + params.toString());
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load the activity log.', 'error');

        currentRows = data.rows;
        // Dropdowns are built once from the whole table; rebuilding them on
        // every filter would drop the value the user just picked.
        if (!filtersLoaded) {
            fillSelect('alUser', data.filters.users);
            fillSelect('alModule', data.filters.modules);
            fillSelect('alAction', data.filters.actions);
            filtersLoaded = true;
        }

        document.getElementById('alCount').textContent =
            data.total ? `${data.total.toLocaleString()} entr${data.total === 1 ? 'y' : 'ies'}` : 'nothing recorded';
        document.getElementById('alHint').classList.toggle('d-none',
            document.getElementById('alIncludeAuth').checked || document.getElementById('alModule').value !== '');
        const capped = document.getElementById('alCapped');
        capped.classList.toggle('d-none', !data.capped);
        if (data.capped) {
            capped.innerHTML = `<i class="fas fa-triangle-exclamation me-1"></i>Showing the newest `
                + `<strong>${data.max.toLocaleString()}</strong> of ${data.total.toLocaleString()} entries. `
                + `Narrow the dates or pick a module to see the rest.`;
        }

        if (dt) { dt.destroy(); dt = null; }
        document.getElementById('auditBody').innerHTML = currentRows.map(r => `
            <tr>
                <td class="al-when" data-order="${escapeHtml(r.Date_Created)}">${fmtWhen(r.Date_Created)}</td>
                <td class="al-user">${escapeHtml(r.Username)}</td>
                <td><span class="al-action ${actionClass(r.Action)}">${escapeHtml(prettyAction(r.Action))}</span></td>
                <td><span class="al-mod" data-m="${escapeHtml(r.Module)}">${escapeHtml(r.Module || '—')}</span></td>
                <td class="al-details">${escapeHtml(r.Details)}</td>
                <td class="al-ip">${escapeHtml(r.IP_Address)}</td>
            </tr>`).join('');
        dt = $('#auditTable').DataTable({
            pageLength: 25,
            // Sorted on the raw timestamp in data-order, not the pretty text —
            // "Aug 7" beats "Aug 10" as a string.
            order: [[0, 'desc']],
            language: { search: '', searchPlaceholder: 'Search in this page…' },
        });
    } catch (err) {
        showToast('Unable to load the activity log.', 'error');
        console.error(err);
    }
}

function clearFilters() {
    ['alFrom','alTo','alQuery'].forEach(id => document.getElementById(id).value = '');
    ['alUser','alModule','alAction'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('alIncludeAuth').checked = false;
    loadLogs();
}

function exportCsv() {
    if (!currentRows.length) return showToast('Nothing to export.', 'warning');
    const cell = v => `"${String(v == null ? '' : v).replace(/"/g, '""')}"`;
    const csv = ['When,User,Action,Module,Details,IP']
        .concat(currentRows.map(r =>
            [r.Date_Created, r.Username, r.Action, r.Module, r.Details, r.IP_Address].map(cell).join(',')))
        .join('\r\n');
    // BOM so Excel opens it as UTF-8 rather than mangling the names.
    const url = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = `activity-log-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

document.getElementById('alQuery').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); loadLogs(); }
});

loadLogs();
</script>
<?php include 'profile_modal.php'; ?>
<?php include 'assistant.php'; ?>
</body>
</html>
