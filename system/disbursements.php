<?php
require_once 'config.php';
requireModule('payables');
$currentUser = getCurrentUser();
$activePage  = 'payables';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Disbursements | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
</head>
<body>
<?php include 'topnav.php'; ?>
<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row align-items-center">
                <div class="col">
                    <div class="page-pretitle">Payables</div>
                    <h2 class="page-title">Disbursements</h2>
                    <div class="text-muted small">Record payments (DV) to suppliers and apply them to open purchase invoices.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="panel-title">Disbursements Register</div>
                <div class="d-flex gap-2">
                    <a href="payables.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-file-invoice-dollar me-1"></i>Payables</a>
                    <a href="collections.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-money-check-dollar me-1"></i>Post-Dated Checks</a>
                    <button class="btn btn-primary-app btn-sm" onclick="openDvModal()"><i class="fas fa-plus me-1"></i>Record Payment</button>
                </div>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>DV #</th><th>Supplier</th><th>Date</th><th>Method</th><th>Amount</th><th>Paid By</th></tr></thead>
                    <tbody id="dvBody"><tr><td colspan="6" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="dvModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Record Payment (DV)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="dvForm">
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Supplier</label><select class="form-select" id="dSupplierId" required onchange="onSupplierChange()"></select></div>
            <div class="col-3"><label class="form-label">Payment Date</label><input type="date" class="form-control" id="dDate" required></div>
            <div class="col-3"><label class="form-label">Method</label>
                <select class="form-select" id="dMethod" onchange="onMethodChange()"><option value="cash">Cash</option><option value="check">Check</option><option value="bank_transfer">Bank Transfer</option></select>
            </div>
            <div class="col-6 d-none" id="dPdcWrap">
                <label class="form-label">Pay via existing issued check</label>
                <select class="form-select" id="dPdcId" onchange="onPdcChosen()"><option value="">— None, new payment —</option></select>
            </div>
            <div class="col-6"><label class="form-label">Amount</label><input type="number" min="0" step="0.01" class="form-control" id="dAmount" required></div>
            <div class="col-12"><label class="form-label">Remarks</label><input type="text" class="form-control" id="dRemarks"></div>
          </div>

          <div class="mt-3">
            <label class="form-label">Apply to Invoices</label>
            <div class="small text-muted mb-1">Total applied must not exceed the amount above.</div>
            <div class="table-responsive">
            <table class="app-table table table-sm">
                <thead><tr><th>Invoice #</th><th>Due Date</th><th>Balance</th><th>Apply</th></tr></thead>
                <tbody id="openInvBody"><tr><td colspan="4" class="text-muted small">Select a supplier first.</td></tr></tbody>
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

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<script>
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function money(n) { return Number(n || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }

async function loadDvs() {
    const params = new URLSearchParams(location.search);
    const supplierId = params.get('supplier_id') || '';
    const res  = await fetch('api/disbursements.php' + (supplierId ? '?supplier_id=' + encodeURIComponent(supplierId) : ''));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    document.getElementById('dvBody').innerHTML = data.disbursements.length ? data.disbursements.map(d => `
        <tr>
            <td>${escapeHtml(d.dv_number)}</td>
            <td>${escapeHtml(d.supplier_name)}</td>
            <td>${d.payment_date}</td>
            <td>${escapeHtml(d.payment_method)}</td>
            <td>${money(d.amount)}</td>
            <td>${escapeHtml(d.paid_by_name)}</td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted small">No disbursements recorded yet.</td></tr>';
}

async function loadSupplierOptions() {
    const res = await fetch('api/suppliers.php');
    const data = await res.json();
    if (!data.success) return;
    document.getElementById('dSupplierId').innerHTML = '<option value="">— Select —</option>' + data.suppliers.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
}

async function openDvModal() {
    document.getElementById('dvForm').reset();
    document.getElementById('dDate').value = new Date().toISOString().slice(0,10);
    await loadSupplierOptions();
    document.getElementById('openInvBody').innerHTML = '<tr><td colspan="4" class="text-muted small">Select a supplier first.</td></tr>';
    document.getElementById('dPdcWrap').classList.add('d-none');
    document.getElementById('dPdcId').innerHTML = '<option value="">— None, new payment —</option>';
    new bootstrap.Modal(document.getElementById('dvModal')).show();
}

function onMethodChange() {
    const isCheck = document.getElementById('dMethod').value === 'check';
    document.getElementById('dPdcWrap').classList.toggle('d-none', !isCheck);
    if (isCheck) loadAvailablePdcs();
}

async function onSupplierChange() {
    loadOpenInvoices();
    if (document.getElementById('dMethod').value === 'check') loadAvailablePdcs();
}

async function loadAvailablePdcs() {
    const supplierId = document.getElementById('dSupplierId').value;
    const sel = document.getElementById('dPdcId');
    sel.innerHTML = '<option value="">— None, new payment —</option>';
    if (!supplierId) return;
    const res = await fetch('api/disbursements.php?available_pdcs_for=' + encodeURIComponent(supplierId));
    const data = await res.json();
    if (!data.success) return;
    data.checks.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.dataset.amount = c.amount;
        opt.textContent = `${c.pdc_number} — ${c.check_number} (${money(c.amount)}, ${c.check_date})`;
        sel.appendChild(opt);
    });
}

function onPdcChosen() {
    const sel = document.getElementById('dPdcId');
    const opt = sel.selectedOptions[0];
    if (opt && opt.dataset.amount) document.getElementById('dAmount').value = opt.dataset.amount;
}

async function loadOpenInvoices() {
    const supplierId = document.getElementById('dSupplierId').value;
    if (!supplierId) { document.getElementById('openInvBody').innerHTML = '<tr><td colspan="4" class="text-muted small">Select a supplier first.</td></tr>'; return; }
    const res = await fetch('api/disbursements.php?open_invoices_for=' + encodeURIComponent(supplierId));
    const data = await res.json();
    if (!data.success) return;
    document.getElementById('openInvBody').innerHTML = data.invoices.length ? data.invoices.map(inv => `
        <tr>
            <td>${escapeHtml(inv.invoice_number)}</td>
            <td>${inv.due_date || '—'}</td>
            <td>${money(inv.balance)}</td>
            <td><input type="number" min="0" max="${inv.balance}" step="0.01" class="form-control form-control-sm apply-amt" data-invoice-id="${inv.id}" style="width:120px"></td>
        </tr>`).join('') : '<tr><td colspan="4" class="text-muted small">No open invoices for this supplier.</td></tr>';
}

document.getElementById('dvForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const applications = [...document.querySelectorAll('.apply-amt')]
        .map(inp => ({ supplier_invoice_id: inp.dataset.invoiceId, amount_applied: inp.value }))
        .filter(a => parseFloat(a.amount_applied) > 0);
    const payload = {
        action: 'create',
        supplier_id: document.getElementById('dSupplierId').value,
        payment_date: document.getElementById('dDate').value,
        payment_method: document.getElementById('dMethod').value,
        amount: document.getElementById('dAmount').value,
        remarks: document.getElementById('dRemarks').value.trim(),
        pdc_id: document.getElementById('dPdcId').value || null,
        applications,
    };
    const res  = await fetch('api/disbursements.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('dvModal')).hide();
    loadDvs();
});

loadDvs();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
