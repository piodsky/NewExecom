<?php
require_once 'config.php';
requireModule('collections');
$currentUser = getCurrentUser();
$activePage  = 'collections';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collections | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.on_hand   { background: #e2e8f0; color: #475569; }
.badge-status.deposited { background: #dbeafe; color: #1e40af; }
.badge-status.cleared   { background: #dcfce7; color: #166534; }
.badge-status.bounced   { background: #fee2e2; color: #991b1b; }
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
                    <h2 class="page-title">Collections</h2>
                    <div class="text-muted small">Record customer payments (OR) and manage post-dated checks.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <ul class="nav nav-tabs mb-3" id="colTabs">
            <li class="nav-item"><button class="nav-link active" data-tab="or" onclick="switchTab('or')">Official Receipts</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="pdc" onclick="switchTab('pdc')">Post-Dated Checks</button></li>
        </ul>

        <div id="tabOr">
            <div class="panel">
                <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="panel-title">Collections Register</div>
                    <button class="btn btn-primary-app btn-sm" onclick="openOrModal()"><i class="fas fa-plus me-1"></i>Record Payment</button>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                    <table class="app-table table">
                        <thead><tr><th>OR #</th><th>Client</th><th>Date</th><th>Method</th><th>Gross</th><th>Withholding Tax</th><th>Net</th><th>Received By</th></tr></thead>
                        <tbody id="orBody"><tr><td colspan="8" class="text-muted small">Loading…</td></tr></tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="tabPdc" class="d-none">
            <div class="panel">
                <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="panel-title">Post-Dated Checks</div>
                    <button class="btn btn-primary-app btn-sm" onclick="openPdcModal()"><i class="fas fa-plus me-1"></i>Record Check</button>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                    <table class="app-table table">
                        <thead><tr><th>PDC #</th><th>Direction</th><th>Payee/Payor</th><th>Check #</th><th>Bank</th><th>Check Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
                        <tbody id="pdcBody"><tr><td colspan="9" class="text-muted small">Loading…</td></tr></tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="orModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Record Payment (OR)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="orForm">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Client</label><select class="form-select" id="oClientId" required onchange="loadOpenInvoices()"></select></div>
            <div class="col-3"><label class="form-label">Payment Date</label><input type="date" class="form-control" id="oDate" required></div>
            <div class="col-3"><label class="form-label">Method</label>
                <select class="form-select" id="oMethod"><option value="cash">Cash</option><option value="check">Check</option><option value="bank_transfer">Bank Transfer</option></select>
            </div>
            <div class="col-4"><label class="form-label">Gross Amount</label><input type="number" min="0" step="0.01" class="form-control" id="oGross" required oninput="recalcNet()"></div>
            <div class="col-4"><label class="form-label">Withholding Tax</label><input type="number" min="0" step="0.01" class="form-control" id="oWht" value="0" oninput="recalcNet()"></div>
            <div class="col-4"><label class="form-label">Net Amount</label><input type="text" class="form-control" id="oNet" disabled></div>
            <div class="col-12"><label class="form-label">Remarks</label><input type="text" class="form-control" id="oRemarks"></div>
          </div>

          <div class="mt-3">
            <label class="form-label">Apply to Invoices</label>
            <div class="small text-muted mb-1">Total applied must not exceed the net amount above.</div>
            <div class="table-responsive">
            <table class="app-table table table-sm">
                <thead><tr><th>Invoice #</th><th>Due Date</th><th>Balance</th><th>Apply</th></tr></thead>
                <tbody id="openInvBody"><tr><td colspan="4" class="text-muted small">Select a client first.</td></tr></tbody>
            </table>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Save Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="pdcModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Record Post-Dated Check</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="pdcForm">
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Direction</label>
            <select class="form-select" id="pDirection" onchange="togglePdcParty()">
                <option value="received">Received from Client</option>
                <option value="issued">Issued to Supplier</option>
            </select>
          </div>
          <div class="mb-2" id="pClientWrap"><label class="form-label">Client</label><select class="form-select" id="pClientId"></select></div>
          <div class="mb-2 d-none" id="pSupplierWrap"><label class="form-label">Supplier</label><select class="form-select" id="pSupplierId"></select></div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Check Number</label><input type="text" class="form-control" id="pCheckNumber" required></div>
            <div class="col-6"><label class="form-label">Bank</label><input type="text" class="form-control" id="pBank"></div>
            <div class="col-6"><label class="form-label">Check Date</label><input type="date" class="form-control" id="pCheckDate" required></div>
            <div class="col-6"><label class="form-label">Amount</label><input type="number" min="0" step="0.01" class="form-control" id="pAmount" required></div>
            <div class="col-12"><label class="form-label">Remarks</label><input type="text" class="form-control" id="pRemarks"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Save Check</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<script>
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function money(n) { return Number(n || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }

function switchTab(tab) {
    document.querySelectorAll('#colTabs .nav-link').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.getElementById('tabOr').classList.toggle('d-none', tab !== 'or');
    document.getElementById('tabPdc').classList.toggle('d-none', tab !== 'pdc');
    if (tab === 'pdc') loadPdcs();
}

async function loadOrs() {
    const params = new URLSearchParams(location.search);
    const clientId = params.get('client_id') || '';
    const res  = await fetch('api/collections.php' + (clientId ? '?client_id=' + encodeURIComponent(clientId) : ''));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    document.getElementById('orBody').innerHTML = data.collections.length ? data.collections.map(c => `
        <tr>
            <td>${escapeHtml(c.or_number)}</td>
            <td>${escapeHtml(c.client_name)}</td>
            <td>${c.payment_date}</td>
            <td>${escapeHtml(c.payment_method)}</td>
            <td>${money(c.gross_amount)}</td>
            <td>${money(c.withholding_tax_amount)}</td>
            <td>${money(c.net_amount)}</td>
            <td>${escapeHtml(c.received_by_name)}</td>
        </tr>`).join('') : '<tr><td colspan="8" class="text-muted small">No collections recorded yet.</td></tr>';
}

async function loadClientOptions(selectId) {
    const res = await fetch('api/clients.php?type=business');
    const data = await res.json();
    if (!data.success) return;
    document.getElementById(selectId).innerHTML = '<option value="">— Select —</option>' + data.clients.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}

function recalcNet() {
    const gross = parseFloat(document.getElementById('oGross').value) || 0;
    const wht   = parseFloat(document.getElementById('oWht').value) || 0;
    document.getElementById('oNet').value = money(Math.max(0, gross - wht));
}

async function openOrModal() {
    document.getElementById('orForm').reset();
    document.getElementById('oDate').value = new Date().toISOString().slice(0,10);
    await loadClientOptions('oClientId');
    document.getElementById('openInvBody').innerHTML = '<tr><td colspan="4" class="text-muted small">Select a client first.</td></tr>';
    recalcNet();
    new bootstrap.Modal(document.getElementById('orModal')).show();
}

async function loadOpenInvoices() {
    const clientId = document.getElementById('oClientId').value;
    if (!clientId) { document.getElementById('openInvBody').innerHTML = '<tr><td colspan="4" class="text-muted small">Select a client first.</td></tr>'; return; }
    const res = await fetch('api/collections.php?open_invoices_for=' + encodeURIComponent(clientId));
    const data = await res.json();
    if (!data.success) return;
    document.getElementById('openInvBody').innerHTML = data.invoices.length ? data.invoices.map(inv => `
        <tr>
            <td>${escapeHtml(inv.invoice_number)}</td>
            <td>${inv.due_date || '—'}</td>
            <td>${money(inv.balance)}</td>
            <td><input type="number" min="0" max="${inv.balance}" step="0.01" class="form-control form-control-sm apply-amt" data-invoice-id="${inv.id}" style="width:120px"></td>
        </tr>`).join('') : '<tr><td colspan="4" class="text-muted small">No open invoices for this client.</td></tr>';
}

document.getElementById('orForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const applications = [...document.querySelectorAll('.apply-amt')]
        .map(inp => ({ sales_invoice_id: inp.dataset.invoiceId, amount_applied: inp.value }))
        .filter(a => parseFloat(a.amount_applied) > 0);
    const payload = {
        action: 'create',
        client_id: document.getElementById('oClientId').value,
        payment_date: document.getElementById('oDate').value,
        payment_method: document.getElementById('oMethod').value,
        gross_amount: document.getElementById('oGross').value,
        withholding_tax_amount: document.getElementById('oWht').value || 0,
        remarks: document.getElementById('oRemarks').value.trim(),
        applications,
    };
    const res  = await fetch('api/collections.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('orModal')).hide();
    loadOrs();
});

function togglePdcParty() {
    const received = document.getElementById('pDirection').value === 'received';
    document.getElementById('pClientWrap').classList.toggle('d-none', !received);
    document.getElementById('pSupplierWrap').classList.toggle('d-none', received);
}
async function openPdcModal() {
    document.getElementById('pdcForm').reset();
    document.getElementById('pDirection').value = 'received';
    togglePdcParty();
    await loadClientOptions('pClientId');
    const res = await fetch('api/suppliers.php');
    const data = await res.json();
    if (data.success) {
        document.getElementById('pSupplierId').innerHTML = '<option value="">— Select —</option>' + data.suppliers.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
    }
    new bootstrap.Modal(document.getElementById('pdcModal')).show();
}
document.getElementById('pdcForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const direction = document.getElementById('pDirection').value;
    const payload = {
        action: 'pdc_create',
        direction,
        client_id: direction === 'received' ? document.getElementById('pClientId').value : '',
        supplier_id: direction === 'issued' ? document.getElementById('pSupplierId').value : '',
        check_number: document.getElementById('pCheckNumber').value.trim(),
        bank_name: document.getElementById('pBank').value.trim(),
        check_date: document.getElementById('pCheckDate').value,
        amount: document.getElementById('pAmount').value,
        remarks: document.getElementById('pRemarks').value.trim(),
    };
    const res  = await fetch('api/collections.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('pdcModal')).hide();
    loadPdcs();
});

async function loadPdcs() {
    const res = await fetch('api/collections.php?pdc=1');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    document.getElementById('pdcBody').innerHTML = data.checks.length ? data.checks.map(p => `
        <tr>
            <td>${escapeHtml(p.pdc_number)}</td>
            <td>${p.direction === 'received' ? 'Received' : 'Issued'}</td>
            <td>${escapeHtml(p.client_name || p.supplier_name || '—')}</td>
            <td>${escapeHtml(p.check_number)}</td>
            <td>${escapeHtml(p.bank_name || '—')}</td>
            <td>${p.check_date}</td>
            <td>${money(p.amount)}</td>
            <td><span class="badge-status ${p.status}">${p.status.replace('_',' ')}</span></td>
            <td class="text-end">
                ${p.status !== 'cleared' && p.status !== 'bounced' ? `
                <select class="form-select form-select-sm d-inline-block" style="width:130px" onchange="updatePdcStatus(${p.id}, this.value)">
                    <option value="">Change status…</option>
                    <option value="on_hand">On Hand</option>
                    <option value="deposited">Deposited</option>
                    <option value="cleared">Cleared</option>
                    <option value="bounced">Bounced</option>
                </select>` : ''}
            </td>
        </tr>`).join('') : '<tr><td colspan="9" class="text-muted small">No post-dated checks recorded yet.</td></tr>';
}
async function updatePdcStatus(id, status) {
    if (!status) return;
    const res  = await fetch('api/collections.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'pdc_update_status', id, status }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    loadPdcs();
}

loadOrs();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
