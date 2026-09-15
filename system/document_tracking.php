<?php
require_once 'config.php';
requireAuth();
requireModule('document_tracking');
$currentUser = getCurrentUser();
$activePage  = 'document_tracking';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document Tracking | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="vendor/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
/* Same card / field / chip vocabulary as Purchase Orders and Inspection, so
   all three screens read as one system. Only what this page adds is here. */
.table-actions { display: flex; gap: .35rem; flex-wrap: wrap; }
.po-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem 1.4rem; margin-bottom: 1.1rem; }
.po-card-compact { padding: .95rem 1.15rem; }
.po-card-title { display: flex; align-items: center; gap: .55rem; font-weight: 700; color: var(--navy); font-size: 1rem; margin-bottom: 1.1rem; }
.po-card-compact .po-card-title { margin-bottom: .65rem; font-size: .92rem; }
.po-card-title i { color: var(--primary); font-size: 1.05rem; }

.po-field-group { margin-bottom: 1rem; }
.po-field-group > label { font-size: .8rem; font-weight: 600; color: var(--muted); margin-bottom: .3rem; display: block; }
.po-input-wrap { position: relative; }
.po-input-wrap > .po-input-icon {
    position: absolute; left: 5px; top: 50%; transform: translateY(-50%);
    width: 28px; height: 28px; border-radius: 8px; background: rgba(37,99,235,.1); color: var(--primary);
    display: flex; align-items: center; justify-content: center; font-size: .78rem; pointer-events: none;
}
.po-input-wrap > .form-control, .po-input-wrap > .form-select {
    padding: .4rem .6rem .4rem 40px; min-height: 38px; font-size: .87rem;
}
.po-field-hint { font-size: .72rem; color: var(--muted); margin-top: .25rem; line-height: 1.35; }
.po-field-hint:empty { display: none; }

.po-chip {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .3rem .7rem; border-radius: 20px;
    font-size: .75rem; font-weight: 600; white-space: nowrap;
    border: 1px solid var(--border); background: #fff; color: var(--muted);
    cursor: pointer; transition: background .15s, border-color .15s, color .15s;
}
.po-chip:hover { background: #f8fafc; }
.po-chip-num { font-weight: 700; color: var(--text); font-variant-numeric: tabular-nums; }
.po-chip.active { background: #eff6ff; border-color: var(--primary); color: var(--primary); }
.po-chip.active .po-chip-num { color: var(--primary); }
.po-chip-tracked   { border-color: #86efac; color: #15803d; }
.po-chip-untracked { border-color: #fcd34d; color: #b45309; }

.num-pill { display: inline-block; padding: .1rem .45rem; border-radius: 6px; font-size: .74rem; font-weight: 700; border: 1px solid transparent; white-space: nowrap; }
.po-num-pill { color: #c2410c; background: #fff2e6; border-color: #fed7aa; }
.pr-num-pill { color: #1d4ed8; background: #e8f0ff; border-color: #bfdbfe; }
.bc-pill     { color: #3730a3; background: #eef2ff; border-color: #c7d2fe; font-family: ui-monospace, Consolas, monospace; }

.status-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 700; padding: .18rem .55rem; border-radius: 20px; }
.status-tracked   { background: #dcfce7; color: #15803d; }
.status-untracked { background: #fef3c7; color: #b45309; }

.po-summary-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: .8rem; }
.po-summary-strip .cell { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: .55rem .75rem; }
.po-summary-strip .cell .k { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }
.po-summary-strip .cell .v { font-size: .87rem; font-weight: 600; color: var(--text); word-break: break-word; }

.source-note { font-size: .74rem; color: var(--muted); margin-top: .4rem; }
.dts-empty { text-align: center; color: var(--muted); padding: 2.5rem 1rem; }
.dts-empty i { font-size: 2rem; opacity: .35; display: block; margin-bottom: .6rem; }
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
                <div class="page-title">Document Tracking</div>
                <div class="page-subtitle">Follow a purchase order through the city's document routing system.</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="row g-3 mb-3 no-print">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div><div class="stat-value" id="statTotal">—</div><div class="stat-label">Purchase Orders</div></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-barcode"></i></div>
                    <div><div class="stat-value" id="statTracked">—</div><div class="stat-label">With a DTS Barcode</div></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon amber"><i class="fas fa-link-slash"></i></div>
                    <div><div class="stat-value" id="statUntracked">—</div><div class="stat-label">Not Yet Linked</div></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon teal"><i class="fas fa-route"></i></div>
                    <div><div class="stat-value" id="statHops" style="font-size:1.15rem;">—</div><div class="stat-label">Movements on Record</div></div>
                </div>
            </div>
        </div>

        <!-- Straight barcode lookup: the fastest path when someone is holding
             the paper and just wants to know where its twin is. -->
        <div class="po-card no-print">
            <div class="po-card-title"><i class="fas fa-magnifying-glass-location"></i> Track a Document</div>
            <div class="row g-2 align-items-end">
                <div class="col-md-4 po-field-group mb-0">
                    <label>DTS Barcode</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-barcode"></i></span>
                    <input type="text" id="lookupBarcode" class="form-control" placeholder="e.g. 2026026449" autocomplete="off"></div>
                    <div class="po-field-hint">Scan or type the barcode printed on the document.</div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary-app flex-fill" id="btnTrack"><i class="fas fa-route me-1"></i> Track</button>
                </div>
            </div>
        </div>

        <div class="po-card no-print">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-3 po-field-group mb-0">
                    <label>Search purchase orders</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="text" id="filterSearch" class="form-control" placeholder="PO/PR number, supplier, barcode"></div>
                </div>
                <div class="col-sm-6 col-md-2 po-field-group mb-0">
                    <label>From</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar"></i></span>
                    <input type="date" id="filterFrom" class="form-control"></div>
                </div>
                <div class="col-sm-6 col-md-2 po-field-group mb-0">
                    <label>To</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar"></i></span>
                    <input type="date" id="filterTo" class="form-control"></div>
                </div>
                <div class="col-sm-6 col-md-2 po-field-group mb-0">
                    <label>Status</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-filter"></i></span>
                    <select id="filterStatus" class="form-select">
                        <option value="">All</option>
                        <option value="tracked">With barcode</option>
                        <option value="untracked">Not linked</option>
                    </select></div>
                </div>
                <div class="col-sm-6 col-md-3 d-flex gap-2">
                    <button class="btn btn-primary-app flex-fill" id="btnFilter"><i class="fas fa-magnifying-glass me-1"></i> Filter</button>
                    <button class="btn btn-outline-secondary flex-fill" id="btnClear"><i class="fas fa-eraser me-1"></i> Clear</button>
                </div>
            </div>
        </div>

        <div class="po-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="po-card-title mb-0"><i class="fas fa-list"></i> Purchase Orders</div>
                <div class="d-flex gap-2 flex-wrap no-print" id="countChips">
                    <button type="button" class="po-chip active" data-status=""><span class="po-chip-num" id="countTotal">0</span> Total</button>
                    <button type="button" class="po-chip po-chip-tracked" data-status="tracked"><span class="po-chip-num" id="countTracked">0</span> With barcode</button>
                    <button type="button" class="po-chip po-chip-untracked" data-status="untracked"><span class="po-chip-num" id="countUntracked">0</span> Not linked</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="app-table table table-hover table-bordered" id="dtsTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>PO Number</th><th>PR Number</th><th>Supplier</th>
                            <th>Department</th><th>PO Date</th><th>DTS Barcode</th>
                            <th>Status</th><th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="dtsBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
<?php include 'profile_modal.php'; ?>
<?php include 'dts_trail.php'; ?>
<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script src="vendor/js/jquery.dataTables.min.js"></script>
<script src="vendor/js/dataTables.bootstrap5.min.js"></script>
<script>
const apiUrl = 'api/document_tracking.php';
let dtsTable = null;

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
    new bootstrap.Toast(el, { delay: 4000 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}
// Same short date the Inspection and Purchase Order lists use.
function fmtDate(v) {
    if (!v || String(v).startsWith('0000')) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d) ? '—' : d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

async function loadList() {
    const params = new URLSearchParams({
        search: document.getElementById('filterSearch').value.trim(),
        from:   document.getElementById('filterFrom').value,
        to:     document.getElementById('filterTo').value,
        status: document.getElementById('filterStatus').value,
    });
    try {
        const res = await fetch(`${apiUrl}?${params}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load purchase orders.', 'danger');
        const c = data.counts || {};
        document.getElementById('countTotal').textContent     = c.total || 0;
        document.getElementById('countTracked').textContent   = c.tracked || 0;
        document.getElementById('countUntracked').textContent = c.untracked || 0;
        renderRows(data.orders || []);
    } catch (err) {
        showToast('Unable to load purchase orders.', 'danger');
        console.error(err);
    }
}

function renderRows(rows) {
    const body = document.getElementById('dtsBody');
    if (dtsTable) { dtsTable.destroy(); dtsTable = null; }
    body.innerHTML = rows.map(r => {
        const badge = r.tracked
            ? '<span class="status-badge status-tracked"><i class="fas fa-barcode"></i> Linked</span>'
            : '<span class="status-badge status-untracked"><i class="fas fa-link-slash"></i> Not linked</span>';
        return `<tr>
            <td><span class="num-pill po-num-pill">${escapeHtml(r.po_number)}</span></td>
            <td>${r.pr_number ? `<span class="num-pill pr-num-pill">${escapeHtml(r.pr_number)}</span>` : '<span class="text-muted">—</span>'}</td>
            <td>${escapeHtml(r.supplier_name) || '<span class="text-muted">—</span>'}</td>
            <td>${escapeHtml(r.department) || '<span class="text-muted">—</span>'}</td>
            <td>${fmtDate(r.date_created)}</td>
            <td>${r.barcode_number ? `<span class="num-pill bc-pill">${escapeHtml(r.barcode_number)}</span>` : '<span class="text-muted">—</span>'}</td>
            <td>${badge}</td>
            <td class="no-print"><div class="table-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="dtsTrackPo('${escapeHtml(r.po_number)}')" title="Track"><i class="fas fa-route"></i></button>
            </div></td>
        </tr>`;
    }).join('');
    dtsTable = $('#dtsTable').DataTable({
        pageLength: 10, lengthMenu: [10, 25, 50, 100], order: [],
        language: { search: '', searchPlaceholder: 'Search table…' },
    });
}

function setStatusFilter(status) {
    document.getElementById('filterStatus').value = status;
    document.querySelectorAll('#countChips .po-chip').forEach(c =>
        c.classList.toggle('active', (c.dataset.status || '') === status));
    loadList();
}

async function loadStats() {
    try {
        const res = await fetch(`${apiUrl}?stats=1`);
        const data = await res.json();
        if (!data.success) return;
        const s = data.stats || {};
        document.getElementById('statTotal').textContent     = (s.total_pos ?? 0).toLocaleString();
        document.getElementById('statTracked').textContent   = (s.tracked ?? 0).toLocaleString();
        document.getElementById('statUntracked').textContent = (s.untracked ?? 0).toLocaleString();
        document.getElementById('statHops').textContent      = s.dts_hops == null ? 'DTS offline' : s.dts_hops.toLocaleString();
    } catch (err) { console.error(err); }
}

document.addEventListener('DOMContentLoaded', () => {
    const today = new Date();
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    const iso = d => d.toISOString().slice(0, 10);
    document.getElementById('filterFrom').value = iso(first);
    document.getElementById('filterTo').value   = iso(today);

    document.getElementById('btnFilter').addEventListener('click', loadList);
    document.getElementById('btnClear').addEventListener('click', () => {
        document.getElementById('filterSearch').value = '';
        document.getElementById('filterFrom').value = iso(first);
        document.getElementById('filterTo').value = iso(today);
        setStatusFilter('');
    });
    document.getElementById('filterSearch').addEventListener('keypress', e => { if (e.key === 'Enter') loadList(); });
    document.getElementById('filterStatus').addEventListener('change', e => setStatusFilter(e.target.value));
    document.querySelectorAll('#countChips .po-chip').forEach(chip =>
        chip.addEventListener('click', () => setStatusFilter(chip.dataset.status || '')));

    const doLookup = () => {
        const code = document.getElementById('lookupBarcode').value.trim();
        if (code === '') return showToast('Enter a barcode first.', 'warning');
        dtsTrackBarcode(code);
    };
    document.getElementById('btnTrack').addEventListener('click', doLookup);
    // Barcode scanners type the value then send Enter, so this is the normal path.
    document.getElementById('lookupBarcode').addEventListener('keypress', e => { if (e.key === 'Enter') doLookup(); });

    loadStats();
    loadList();

    const q = new URLSearchParams(location.search);
    if (q.get('po_number')) dtsTrackPo(q.get('po_number'));
    else if (q.get('barcode')) dtsTrackBarcode(q.get('barcode'));
});
</script>
<?php include 'assistant.php'; ?>
</body>
</html>
