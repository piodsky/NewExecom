<?php
require_once 'config.php';
requireModule('billing');
$currentUser = getCurrentUser();
$activePage  = 'billing';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Billing | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.unpaid  { background: #fee2e2; color: #991b1b; }
.badge-status.partial { background: #fef3c7; color: #92400e; }
.badge-status.paid    { background: #dcfce7; color: #166534; }
.badge-status.void     { background: #e2e8f0; color: #475569; }
.aging-card { text-align: center; }
.aging-card .val { font-size: 1.15rem; font-weight: 700; }
.days-overdue { color: #991b1b; font-weight: 600; }
</style>
</head>
<body>
<?php include 'topnav.php'; ?>
<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row align-items-center">
                <div class="col">
                    <div class="page-pretitle">Home</div>
                    <h2 class="page-title">Billing</h2>
                    <div class="text-muted small">Accounts Receivable — invoices, balances, and aging.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <div class="row g-2 mb-3" id="agingRow"></div>

        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="panel-title">Sales Invoices</div>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="biStatusFilter" style="width:150px" onchange="loadInvoices()">
                        <option value="all">All statuses</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="partial">Partial</option>
                        <option value="paid">Paid</option>
                        <option value="void">Void</option>
                    </select>
                    <button class="btn btn-primary-app btn-sm" onclick="openCreateInvoiceModal()"><i class="fas fa-plus me-1"></i>Invoice an Issuance</button>
                </div>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Invoice #</th><th>Source</th><th>Client</th><th>Due Date</th><th>Total</th><th>Balance</th><th>Status</th><th>Overdue</th><th></th></tr></thead>
                    <tbody id="biBody"><tr><td colspan="9" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="createInvoiceModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Invoice an Issuance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="text-muted small">Only issuances made to a registered client (not walk-ins) and not already invoiced are listed here.</p>
        <div class="table-responsive">
        <table class="app-table table table-sm">
            <thead><tr><th>Issuance #</th><th>Client</th><th>Date</th><th>Amount</th><th></th></tr></thead>
            <tbody id="unbilledBody"><tr><td colspan="5" class="text-muted small">Loading…</td></tr></tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="invoiceDetailModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="invoiceDetailTitle">Invoice</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="invoiceDetailBody">Loading…</div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<script>
const isAdminTier = <?= $isAdminTier ? 'true' : 'false' ?>;
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function money(n) { return Number(n || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }

function renderAging(aging) {
    const labels = { current: 'Current', d1_30: '1–30 days', d31_60: '31–60 days', d61_90: '61–90 days', d90_plus: '90+ days' };
    const colors = { current: 'text-success', d1_30: 'text-warning', d31_60: 'text-warning', d61_90: 'text-danger', d90_plus: 'text-danger' };
    document.getElementById('agingRow').innerHTML = Object.keys(labels).map(k => `
        <div class="col"><div class="panel aging-card"><div class="panel-body py-2">
            <div class="small text-muted">${labels[k]}</div>
            <div class="val ${colors[k]}">${money(aging[k])}</div>
        </div></div></div>`).join('');
}

async function loadInvoices() {
    const status = document.getElementById('biStatusFilter').value;
    const res  = await fetch('api/billing.php?status=' + encodeURIComponent(status));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    renderAging(data.aging);
    document.getElementById('biBody').innerHTML = data.invoices.length ? data.invoices.map(inv => `
        <tr>
            <td>${escapeHtml(inv.invoice_number)}</td>
            <td>${inv.issuance_number ? escapeHtml(inv.issuance_number) : escapeHtml(inv.jo_number || '—')}</td>
            <td>${escapeHtml(inv.client_name || '—')}</td>
            <td>${inv.due_date || '—'}</td>
            <td>${money(inv.total_amount)}</td>
            <td>${money(inv.balance)}</td>
            <td><span class="badge-status ${inv.status}">${inv.status.charAt(0).toUpperCase()+inv.status.slice(1)}</span></td>
            <td>${inv.days_overdue > 0 && ['unpaid','partial'].includes(inv.status) ? `<span class="days-overdue">${inv.days_overdue}d</span>` : '—'}</td>
            <td class="text-end">
                <button class="btn-sm-action" onclick="viewInvoice('${inv.invoice_number}')" title="View"><i class="fas fa-eye"></i></button>
                ${isAdminTier && inv.status === 'unpaid' ? `<button class="btn-sm-action" onclick="voidInvoice(${inv.id})" title="Void"><i class="fas fa-ban"></i></button>` : ''}
            </td>
        </tr>`).join('') : '<tr><td colspan="9" class="text-muted small">No invoices found.</td></tr>';
}

async function openCreateInvoiceModal() {
    new bootstrap.Modal(document.getElementById('createInvoiceModal')).show();
    const res = await fetch('api/billing.php?unbilled_issuances=1');
    const data = await res.json();
    if (!data.success) return;
    document.getElementById('unbilledBody').innerHTML = data.issuances.length ? data.issuances.map(i => `
        <tr>
            <td>${escapeHtml(i.issuance_number)}</td>
            <td>${escapeHtml(i.client_name)}</td>
            <td>${i.date_created ? new Date(i.date_created.replace(' ','T')).toLocaleDateString() : '—'}</td>
            <td>${money(i.total_amount)}</td>
            <td><button class="btn btn-primary-app btn-sm" onclick="createInvoice('${i.issuance_number}')">Create Invoice</button></td>
        </tr>`).join('') : '<tr><td colspan="5" class="text-muted small">Nothing to invoice — every credit issuance is already billed.</td></tr>';
}
async function createInvoice(issuanceNumber) {
    const res  = await fetch('api/billing.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'create_from_issuance', issuance_number: issuanceNumber }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('createInvoiceModal')).hide();
    loadInvoices();
}

async function viewInvoice(invoiceNumber) {
    document.getElementById('invoiceDetailTitle').textContent = invoiceNumber;
    document.getElementById('invoiceDetailBody').innerHTML = 'Loading…';
    new bootstrap.Modal(document.getElementById('invoiceDetailModal')).show();
    const res  = await fetch('api/billing.php?id=' + encodeURIComponent(invoiceNumber));
    const data = await res.json();
    if (!data.success) { document.getElementById('invoiceDetailBody').innerHTML = escapeHtml(data.message); return; }
    const inv = data.invoice;
    document.getElementById('invoiceDetailBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><div class="small text-muted">Client</div><div>${escapeHtml(inv.client_name || '—')}</div></div>
            <div class="col-6"><div class="small text-muted">Due Date</div><div>${inv.due_date || '—'}</div></div>
            <div class="col-6"><div class="small text-muted">Total</div><div>${money(inv.total_amount)}</div></div>
            <div class="col-6"><div class="small text-muted">Balance</div><div class="fw-bold">${money(inv.balance)}</div></div>
        </div>
        <div class="small text-muted mb-1">Payment history</div>
        <table class="app-table table table-sm">
            <thead><tr><th>OR #</th><th>Date</th><th>Method</th><th>Applied</th></tr></thead>
            <tbody>
                ${inv.payments.length ? inv.payments.map(p => `
                    <tr><td>${escapeHtml(p.or_number)}</td><td>${p.payment_date}</td><td>${escapeHtml(p.payment_method)}</td><td>${money(p.amount_applied)}</td></tr>
                `).join('') : '<tr><td colspan="4" class="text-muted small">No payments recorded yet.</td></tr>'}
            </tbody>
        </table>
        <a href="collections.php?client_id=${inv.client_id}" class="btn btn-primary-app btn-sm">Go to Collections</a>
    `;
}

async function voidInvoice(id) {
    if (!confirm('Void this invoice? This cannot be undone.')) return;
    const res  = await fetch('api/billing.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'void', id }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    loadInvoices();
}

loadInvoices();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
