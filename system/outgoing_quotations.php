<?php
require_once 'config.php';
requireModule('outgoing_quotations');
$currentUser = getCurrentUser();
$activePage  = 'outgoing_quotations';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
$isSuperAdmin = $currentUser['role'] === 'super_admin';
$fromRfq = trim($_GET['from_rfq'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quotations (Outgoing) | <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%232563eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' font-weight='700' fill='white' text-anchor='middle'%3EE%3C/text%3E%3C/svg%3E">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.active     { background: #dbeafe; color: #1e40af; }
.badge-status.converted  { background: #dcfce7; color: #166534; }
.badge-status.cancelled  { background: #fee2e2; color: #991b1b; }
.oq-item-row { display: grid; grid-template-columns: 1.8fr .7fr .7fr .9fr auto; gap: .5rem; margin-bottom: .5rem; align-items: center; }
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
                <div class="page-title">Quotations (Outgoing)</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Quotations (Outgoing)</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">EXECOM's Priced Replies</div>
                <?php if ($isAdminTier): ?>
                <button class="btn btn-primary-app btn-sm" onclick="openQModal()"><i class="fas fa-plus me-1"></i>New Quotation</button>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Quotation #</th><th>Client</th><th>From RFQ</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody id="oqBody"><tr><td colspan="6" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- New Quotation modal -->
<div class="modal fade" id="oqModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="oqModalTitle">New Quotation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="oqForm">
        <div class="modal-body">
          <input type="hidden" id="oqRfqNumber">
          <div class="row g-2 mb-2">
            <div class="col-6" id="oqClientWrap"><label class="form-label">Client</label><select class="form-select" id="oqClient"></select></div>
            <div class="col-3"><label class="form-label">Price Validity (days)</label><input type="number" class="form-control" id="oqValidity"></div>
            <div class="col-3"><label class="form-label">Lead Time</label><input type="text" class="form-control" id="oqLeadTime" placeholder="e.g. 15 working days"></div>
          </div>
          <label class="form-label">Items &amp; Pricing</label>
          <div id="oqItemRows"></div>
          <button type="button" class="btn btn-light btn-sm" id="oqAddItemBtn" onclick="addOqItemRow()"><i class="fas fa-plus me-1"></i>Add Item</button>
          <div class="text-end fw-bold mt-2">Total: <span id="oqTotal">₱0.00</span></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Save Quotation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Quotation modal -->
<div class="modal fade" id="oqViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="oqViewTitle">Quotation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="oqViewBody"></div>
      <div class="modal-footer" id="oqViewFooter"></div>
    </div>
  </div>
</div>

<!-- Convert to PO modal -->
<div class="modal fade" id="oqConvertModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Convert to Outgoing PO</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="oqConvertForm">
        <div class="modal-body">
          <input type="hidden" id="oqcQuotationNumber">
          <div class="mb-2"><label class="form-label">End User</label><input type="text" class="form-control" id="oqcEndUser"></div>
          <div class="mb-2"><label class="form-label">Place of Delivery</label><input type="text" class="form-control" id="oqcPlace"></div>
          <div class="mb-2"><label class="form-label">Delivery Term</label><input type="text" class="form-control" id="oqcDeliveryTerm"></div>
          <div class="mb-2"><label class="form-label">Payment Term</label><input type="text" class="form-control" id="oqcPaymentTerm"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Create PO</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isAdminTier = <?= $isAdminTier ? 'true' : 'false' ?>;
const initialFromRfq = <?= json_encode($fromRfq) ?>;
let oqCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
const statusLabel = { active: 'Active', converted: 'Converted', cancelled: 'Cancelled' };

async function loadOqs() {
    const res  = await fetch('api/outgoing_quotations.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    oqCache = data.quotations;
    document.getElementById('oqBody').innerHTML = oqCache.length ? oqCache.map(q => `
        <tr style="cursor:pointer" onclick="viewOq('${q.quotation_number}')">
            <td><strong>${escapeHtml(q.quotation_number)}</strong></td>
            <td>${escapeHtml(q.client_name || '—')}</td>
            <td>${escapeHtml(q.rfq_number || '—')}</td>
            <td>${peso(q.total_amount)}</td>
            <td><span class="badge-status ${q.status}">${statusLabel[q.status] || q.status}</span></td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted small">No quotations yet.</td></tr>';
}

async function loadClientOptions() {
    const res  = await fetch('api/clients.php');
    const data = await res.json();
    const clients = (data.success ? data.clients : []).filter(c => c.is_active == 1);
    document.getElementById('oqClient').innerHTML = '<option value="">Select client…</option>' +
        clients.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}

function addOqItemRow(desc = '', qty = '', unit = '', price = '', itemId = null) {
    const wrap = document.createElement('div');
    wrap.className = 'oq-item-row';
    if (itemId) wrap.dataset.itemId = itemId;
    wrap.innerHTML = `
        <input type="text" class="form-control form-control-sm oq-desc" placeholder="Description" value="${escapeHtml(desc)}" ${itemId ? 'readonly' : ''}>
        <input type="number" step="0.01" class="form-control form-control-sm oq-qty" placeholder="Qty" value="${escapeHtml(qty)}" ${itemId ? 'readonly' : ''}>
        <input type="text" class="form-control form-control-sm oq-unit" placeholder="Unit" value="${escapeHtml(unit)}" ${itemId ? 'readonly' : ''}>
        <input type="number" step="0.01" class="form-control form-control-sm oq-price" placeholder="Unit Price" value="${escapeHtml(price)}" oninput="updateOqTotal()">
        <button type="button" class="btn btn-sm btn-light" onclick="this.parentElement.remove(); updateOqTotal()"><i class="fas fa-trash"></i></button>`;
    document.getElementById('oqItemRows').appendChild(wrap);
}

function updateOqTotal() {
    let total = 0;
    document.querySelectorAll('#oqItemRows .oq-item-row').forEach(row => {
        total += (parseFloat(row.querySelector('.oq-qty').value) || 0) * (parseFloat(row.querySelector('.oq-price').value) || 0);
    });
    document.getElementById('oqTotal').textContent = peso(total);
}

async function openQModal(rfqNumber) {
    document.getElementById('oqForm').reset();
    document.getElementById('oqItemRows').innerHTML = '';
    document.getElementById('oqTotal').textContent = peso(0);
    document.getElementById('oqRfqNumber').value = rfqNumber || '';

    if (rfqNumber) {
        document.getElementById('oqModalTitle').textContent = 'New Quotation for ' + rfqNumber;
        document.getElementById('oqClientWrap').style.display = 'none';
        document.getElementById('oqAddItemBtn').style.display = 'none';
        const res  = await fetch('api/outgoing_rfq.php?id=' + encodeURIComponent(rfqNumber));
        const data = await res.json();
        if (!data.success) return alert(data.message);
        data.rfq.items.forEach(i => addOqItemRow(i.description, i.quantity, i.unit || '', '', i.item_id));
    } else {
        document.getElementById('oqModalTitle').textContent = 'New Quotation';
        document.getElementById('oqClientWrap').style.display = '';
        document.getElementById('oqAddItemBtn').style.display = '';
        await loadClientOptions();
        addOqItemRow();
    }
    new bootstrap.Modal(document.getElementById('oqModal')).show();
}

document.getElementById('oqForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const rfqNumber = document.getElementById('oqRfqNumber').value;
    const items = [...document.querySelectorAll('#oqItemRows .oq-item-row')].map(row => ({
        item_id: row.dataset.itemId || null,
        description: row.querySelector('.oq-desc').value.trim(),
        quantity: row.querySelector('.oq-qty').value,
        unit: row.querySelector('.oq-unit').value.trim(),
        unit_price: row.querySelector('.oq-price').value,
    })).filter(i => i.description && i.quantity && i.unit_price !== '');
    if (!items.length) return alert('Add at least one item with quantity and unit price.');

    const payload = { action: 'create', items };
    if (rfqNumber) {
        payload.rfq_number = rfqNumber;
    } else {
        const clientId = document.getElementById('oqClient').value;
        if (!clientId) return alert('Select a client.');
        payload.client_id = clientId;
    }
    payload.price_validity_days = document.getElementById('oqValidity').value;
    payload.lead_time = document.getElementById('oqLeadTime').value.trim();

    const res  = await fetch('api/outgoing_quotations.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('oqModal')).hide();
    loadOqs();
});

async function viewOq(quotationNumber) {
    const res  = await fetch('api/outgoing_quotations.php?id=' + encodeURIComponent(quotationNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const q = data.quotation;
    document.getElementById('oqViewTitle').textContent = q.quotation_number + ' — ' + statusLabel[q.status];
    document.getElementById('oqViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Client:</strong> ${escapeHtml(q.client_name || '—')}</div>
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(q.branch_name)}</div>
            <div class="col-6"><strong>Price Validity:</strong> ${q.price_validity_days ? q.price_validity_days + ' days' : '—'}</div>
            <div class="col-6"><strong>Lead Time:</strong> ${escapeHtml(q.lead_time || '—')}</div>
        </div>
        <table class="app-table table">
            <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody>${q.items.map(i => `<tr><td>${escapeHtml(i.description)}</td><td>${i.quantity}</td><td>${escapeHtml(i.unit || '—')}</td><td>${peso(i.unit_price)}</td><td>${peso(i.total_price)}</td></tr>`).join('')}</tbody>
            <tfoot><tr><th colspan="4" class="text-end">Total</th><th>${peso(q.total_amount)}</th></tr></tfoot>
        </table>`;

    let footer = '';
    if (isAdminTier && q.status === 'active') {
        footer = `<button class="btn btn-danger btn-sm me-auto" onclick="oqAction('${q.quotation_number}','cancel')">Cancel</button>
                   <button class="btn btn-primary-app btn-sm" onclick="openConvertModal('${q.quotation_number}')">Convert to PO</button>`;
    }
    footer += `<a href="print_quotation.php?quotation_number=${encodeURIComponent(q.quotation_number)}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i>Print</a>`;
    document.getElementById('oqViewFooter').innerHTML = footer || '<span class="text-muted small">No actions available.</span>';
    new bootstrap.Modal(document.getElementById('oqViewModal')).show();
}

async function oqAction(quotationNumber, action) {
    if (action === 'cancel' && !confirm('Cancel this quotation?')) return;
    const res  = await fetch('api/outgoing_quotations.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action, quotation_number: quotationNumber }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('oqViewModal')).hide();
    loadOqs();
}

function openConvertModal(quotationNumber) {
    document.getElementById('oqConvertForm').reset();
    document.getElementById('oqcQuotationNumber').value = quotationNumber;
    bootstrap.Modal.getInstance(document.getElementById('oqViewModal'))?.hide();
    new bootstrap.Modal(document.getElementById('oqConvertModal')).show();
}

document.getElementById('oqConvertForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        action: 'convert_to_po',
        quotation_number: document.getElementById('oqcQuotationNumber').value,
        end_user: document.getElementById('oqcEndUser').value.trim(),
        place_of_delivery: document.getElementById('oqcPlace').value.trim(),
        delivery_term: document.getElementById('oqcDeliveryTerm').value.trim(),
        payment_term: document.getElementById('oqcPaymentTerm').value.trim(),
    };
    const res  = await fetch('api/outgoing_quotations.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('oqConvertModal')).hide();
    alert(data.message);
    loadOqs();
});

loadOqs();
if (initialFromRfq) openQModal(initialFromRfq);
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
