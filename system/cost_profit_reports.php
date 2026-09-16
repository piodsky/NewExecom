<?php
require_once 'config.php';
requireModule('cost_profit_reports');
$currentUser = getCurrentUser();
$activePage  = 'cost_profit_reports';
$isSuperAdmin = $currentUser['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cost/Profit Reports | <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%232563eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' font-weight='700' fill='white' text-anchor='middle'%3EE%3C/text%3E%3C/svg%3E">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.margin-positive { color: #166534; font-weight: 600; }
.margin-negative { color: #991b1b; font-weight: 600; }
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
                <div class="page-title">Cost/Profit Reports</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Cost/Profit Reports</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel mb-3">
            <div class="panel-body d-flex flex-wrap gap-2 align-items-end">
                <div><label class="form-label">From</label><input type="date" class="form-control form-control-sm" id="cprFrom"></div>
                <div><label class="form-label">To</label><input type="date" class="form-control form-control-sm" id="cprTo"></div>
                <?php if ($isSuperAdmin): ?>
                <div><label class="form-label">Branch</label>
                    <select class="form-select form-select-sm" id="cprBranch"><option value="">All Branches</option></select></div>
                <?php endif; ?>
                <div><label class="form-label">Sale Type</label>
                    <select class="form-select form-select-sm" id="cprSaleType">
                        <option value="">All Sales</option>
                        <option value="internal">Internal (Walk-in / Retail)</option>
                        <option value="outgoing">Outgoing (Client Orders)</option>
                    </select></div>
                <button class="btn btn-primary-app btn-sm" onclick="loadReport()"><i class="fas fa-filter me-1"></i>Filter</button>
                <button class="btn btn-light btn-sm" onclick="exportCsv()"><i class="fas fa-file-csv me-1"></i>Export CSV</button>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-sack-dollar"></i></div>
                    <div class="stat-value" id="cprRevenue">—</div>
                    <div class="stat-label">Revenue</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon amber"><i class="fas fa-hand-holding-dollar"></i></div>
                    <div class="stat-value" id="cprCost">—</div>
                    <div class="stat-label">Matched Cost</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-value" id="cprMargin">—</div>
                    <div class="stat-label">Margin</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-percent"></i></div>
                    <div class="stat-value" id="cprMarginPct">—</div>
                    <div class="stat-label">Margin %</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <?php if ($isSuperAdmin): ?>
            <div class="col-md-6">
                <div class="panel h-100">
                    <div class="panel-header"><div class="panel-title">By Branch</div></div>
                    <div class="panel-body">
                        <table class="app-table table table-sm">
                            <thead><tr><th>Branch</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Margin</th></tr></thead>
                            <tbody id="cprByBranch"><tr><td colspan="4" class="text-muted small">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-md-6">
                <div class="panel h-100">
                    <div class="panel-header"><div class="panel-title">By Item Category</div></div>
                    <div class="panel-body">
                        <table class="app-table table table-sm">
                            <thead><tr><th>Category</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Margin</th></tr></thead>
                            <tbody id="cprByCategory"><tr><td colspan="4" class="text-muted small">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel h-100">
                    <div class="panel-header"><div class="panel-title">By Client</div></div>
                    <div class="panel-body">
                        <table class="app-table table table-sm">
                            <thead><tr><th>Client</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Margin</th></tr></thead>
                            <tbody id="cprByClient"><tr><td colspan="4" class="text-muted small">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel h-100">
                    <div class="panel-header"><div class="panel-title">By Sale Type</div></div>
                    <div class="panel-body">
                        <table class="app-table table table-sm">
                            <thead><tr><th>Type</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Margin</th></tr></thead>
                            <tbody id="cprBySaleType"><tr><td colspan="4" class="text-muted small">Loading…</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><div class="panel-title">Transactions</div></div>
            <div class="panel-body">
                <p class="text-muted small mb-2">Bulk-item cost is estimated from the item's standard cost (no per-unit cost history once receipts merge into pooled stock); serialized items use their exact recorded cost.</p>
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Date</th><th>Issuance #</th><th>Branch</th><th>Client</th><th>PO</th><th>Item</th><th>Qty</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Margin</th></tr></thead>
                    <tbody id="cprTxBody"><tr><td colspan="10" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
let txCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
const marginClass = n => n < 0 ? 'margin-negative' : 'margin-positive';

async function loadBranchOptions() {
    const res  = await fetch('api/stock_transfers.php?branches=1');
    const data = await res.json();
    const branches = data.success ? data.branches : [];
    document.getElementById('cprBranch').innerHTML = '<option value="">All Branches</option>' +
        branches.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
}

function rowHtml(label, r) {
    return `<tr><td>${escapeHtml(label)}</td><td class="text-end">${peso(r.revenue)}</td><td class="text-end">${peso(r.cost)}</td><td class="text-end ${marginClass(r.margin)}">${peso(r.margin)}</td></tr>`;
}

async function loadReport() {
    const params = new URLSearchParams();
    const from = document.getElementById('cprFrom').value, to = document.getElementById('cprTo').value;
    if (from) params.set('from', from);
    if (to)   params.set('to', to);
    if (isSuperAdmin) {
        const branchId = document.getElementById('cprBranch').value;
        if (branchId) params.set('branch_id', branchId);
    }
    const saleType = document.getElementById('cprSaleType').value;
    if (saleType) params.set('sale_type', saleType);

    const res  = await fetch('api/cost_profit_reports.php?' + params.toString());
    const data = await res.json();
    if (!data.success) return alert(data.message);

    document.getElementById('cprRevenue').textContent = peso(data.totals.revenue);
    document.getElementById('cprCost').textContent = peso(data.totals.cost);
    const marginEl = document.getElementById('cprMargin');
    marginEl.textContent = peso(data.totals.margin);
    marginEl.className = 'stat-value ' + marginClass(data.totals.margin);
    document.getElementById('cprMarginPct').textContent = data.totals.margin_pct + '%';

    if (isSuperAdmin) {
        document.getElementById('cprByBranch').innerHTML = data.by_branch.length
            ? data.by_branch.map(r => rowHtml(r.branch_name, r)).join('')
            : '<tr><td colspan="4" class="text-muted small">No data in this range.</td></tr>';
    }
    document.getElementById('cprByCategory').innerHTML = data.by_category.length
        ? data.by_category.map(r => rowHtml(r.category_name, r)).join('')
        : '<tr><td colspan="4" class="text-muted small">No data in this range.</td></tr>';
    document.getElementById('cprByClient').innerHTML = data.by_client.length
        ? data.by_client.map(r => rowHtml(r.client_name, r)).join('')
        : '<tr><td colspan="4" class="text-muted small">No data in this range.</td></tr>';
    document.getElementById('cprBySaleType').innerHTML = data.by_sale_type.length
        ? data.by_sale_type.map(r => rowHtml(r.label, r)).join('')
        : '<tr><td colspan="4" class="text-muted small">No data in this range.</td></tr>';

    txCache = data.transactions;
    document.getElementById('cprTxBody').innerHTML = txCache.length ? txCache.map(t => `
        <tr>
            <td>${t.date_created ? t.date_created.slice(0,10) : '—'}</td>
            <td>${escapeHtml(t.issuance_number)}</td>
            <td>${escapeHtml(t.branch_name)}</td>
            <td>${escapeHtml(t.client_name)}</td>
            <td>${escapeHtml(t.po_number || '—')}</td>
            <td>${escapeHtml(t.item_name)}</td>
            <td>${t.quantity}</td>
            <td class="text-end">${peso(t.revenue)}</td>
            <td class="text-end">${peso(t.cost)}</td>
            <td class="text-end ${marginClass(t.margin)}">${peso(t.margin)}</td>
        </tr>`).join('') : '<tr><td colspan="10" class="text-muted small">No transactions in this range.</td></tr>';
}

function exportCsv() {
    if (!txCache.length) return alert('Nothing to export.');
    const esc = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const csv = ['Date,Issuance,Branch,Client,PO,Item,Qty,Revenue,Cost,Margin']
        .concat(txCache.map(t => [t.date_created, t.issuance_number, t.branch_name, t.client_name, t.po_number, t.item_name, t.quantity, t.revenue, t.cost, t.margin].map(esc).join(',')))
        .join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'cost_profit_report.csv';
    a.click();
}

(async function init() {
    const today = new Date().toISOString().slice(0, 10);
    document.getElementById('cprFrom').value = today.slice(0, 8) + '01';
    document.getElementById('cprTo').value = today;
    if (isSuperAdmin) await loadBranchOptions();
    loadReport();
})();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
