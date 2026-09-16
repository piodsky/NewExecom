<?php
require_once 'config.php';
requireModule('outgoing_rfq');
$currentUser = getCurrentUser();
$activePage  = 'outgoing_rfq';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RFQ (Outgoing) | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.open      { background: #dbeafe; color: #1e40af; }
.badge-status.quoted     { background: #fef3c7; color: #92400e; }
.badge-status.converted  { background: #dcfce7; color: #166534; }
.badge-status.cancelled  { background: #fee2e2; color: #991b1b; }
.orfq-item-row { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: .5rem; margin-bottom: .5rem; align-items: center; }
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
                <div class="page-title">RFQ (Outgoing)</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> RFQ (Outgoing)</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Canvasses Received from Clients</div>
                <?php if ($isAdminTier): ?>
                <button class="btn btn-primary-app btn-sm" onclick="openRfqModal()"><i class="fas fa-plus me-1"></i>Log RFQ</button>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>RFQ Number</th><th>Client</th><th>Received</th><th>Due</th><th>Status</th><th></th></tr></thead>
                    <tbody id="orfqBody"><tr><td colspan="6" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- New RFQ modal -->
<div class="modal fade" id="orfqModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Log an Outgoing RFQ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="orfqForm">
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Client</label><select class="form-select" id="orfqClient" required></select></div>
            <div class="col-3"><label class="form-label">Received Date</label><input type="date" class="form-control" id="orfqReceived" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-3"><label class="form-label">Due Date</label><input type="date" class="form-control" id="orfqDue"></div>
          </div>
          <label class="form-label">Items Being Canvassed</label>
          <div id="orfqItemRows"></div>
          <button type="button" class="btn btn-light btn-sm" onclick="addOrfqItemRow()"><i class="fas fa-plus me-1"></i>Add Item</button>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View RFQ modal -->
<div class="modal fade" id="orfqViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="orfqViewTitle">RFQ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="orfqViewBody"></div>
      <div class="modal-footer" id="orfqViewFooter"></div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isAdminTier = <?= $isAdminTier ? 'true' : 'false' ?>;
let orfqCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const statusLabel = { open: 'Open', quoted: 'Quoted', converted: 'Converted', cancelled: 'Cancelled' };

async function loadOrfqs() {
    const res  = await fetch('api/outgoing_rfq.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    orfqCache = data.rfqs;
    document.getElementById('orfqBody').innerHTML = orfqCache.length ? orfqCache.map(r => `
        <tr style="cursor:pointer" onclick="viewOrfq('${r.rfq_number}')">
            <td><strong>${escapeHtml(r.rfq_number)}</strong></td>
            <td>${escapeHtml(r.client_name)}</td>
            <td>${r.received_date || '—'}</td>
            <td>${r.due_date || '—'}</td>
            <td><span class="badge-status ${r.status}">${statusLabel[r.status] || r.status}</span></td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted small">No RFQs logged yet.</td></tr>';
}

async function loadClientOptions(selectId) {
    const res  = await fetch('api/clients.php');
    const data = await res.json();
    const clients = (data.success ? data.clients : []).filter(c => c.is_active == 1);
    document.getElementById(selectId).innerHTML = '<option value="">Select client…</option>' +
        clients.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}

function addOrfqItemRow(desc = '', qty = '', unit = '') {
    const wrap = document.createElement('div');
    wrap.className = 'orfq-item-row';
    wrap.innerHTML = `
        <input type="text" class="form-control form-control-sm orfq-desc" placeholder="Description" value="${escapeHtml(desc)}">
        <input type="number" step="0.01" class="form-control form-control-sm orfq-qty" placeholder="Qty" value="${escapeHtml(qty)}">
        <input type="text" class="form-control form-control-sm orfq-unit" placeholder="Unit" value="${escapeHtml(unit)}">
        <button type="button" class="btn btn-sm btn-light" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>`;
    document.getElementById('orfqItemRows').appendChild(wrap);
}

async function openRfqModal() {
    document.getElementById('orfqForm').reset();
    document.getElementById('orfqReceived').value = new Date().toISOString().slice(0, 10);
    document.getElementById('orfqItemRows').innerHTML = '';
    addOrfqItemRow();
    await loadClientOptions('orfqClient');
    new bootstrap.Modal(document.getElementById('orfqModal')).show();
}

document.getElementById('orfqForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const items = [...document.querySelectorAll('#orfqItemRows .orfq-item-row')].map(row => ({
        description: row.querySelector('.orfq-desc').value.trim(),
        quantity: row.querySelector('.orfq-qty').value,
        unit: row.querySelector('.orfq-unit').value.trim(),
    })).filter(i => i.description && i.quantity);
    if (!items.length) return alert('Add at least one item with a description and quantity.');

    const payload = {
        action: 'create',
        client_id: document.getElementById('orfqClient').value,
        received_date: document.getElementById('orfqReceived').value,
        due_date: document.getElementById('orfqDue').value,
        items,
    };
    const res  = await fetch('api/outgoing_rfq.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('orfqModal')).hide();
    loadOrfqs();
});

async function viewOrfq(rfqNumber) {
    const res  = await fetch('api/outgoing_rfq.php?id=' + encodeURIComponent(rfqNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const rfq = data.rfq;
    document.getElementById('orfqViewTitle').textContent = rfq.rfq_number + ' — ' + statusLabel[rfq.status];
    document.getElementById('orfqViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Client:</strong> ${escapeHtml(rfq.client_name)}</div>
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(rfq.branch_name)}</div>
            <div class="col-6"><strong>Received:</strong> ${rfq.received_date || '—'}</div>
            <div class="col-6"><strong>Due:</strong> ${rfq.due_date || '—'}</div>
        </div>
        <table class="app-table table">
            <thead><tr><th>Description</th><th>Qty</th><th>Unit</th></tr></thead>
            <tbody>${rfq.items.map(i => `<tr><td>${escapeHtml(i.description)}</td><td>${i.quantity}</td><td>${escapeHtml(i.unit || '—')}</td></tr>`).join('')}</tbody>
        </table>`;

    let footer = '';
    if (isAdminTier && rfq.status === 'open') {
        footer = `<button class="btn btn-danger btn-sm me-auto" onclick="orfqAction('${rfq.rfq_number}','cancel')">Cancel</button>
                   <a href="outgoing_quotations.php?from_rfq=${encodeURIComponent(rfq.rfq_number)}" class="btn btn-primary-app btn-sm">Create Quotation</a>`;
    }
    document.getElementById('orfqViewFooter').innerHTML = footer || '<span class="text-muted small">No actions available.</span>';
    new bootstrap.Modal(document.getElementById('orfqViewModal')).show();
}

async function orfqAction(rfqNumber, action) {
    if (action === 'cancel' && !confirm('Cancel this RFQ?')) return;
    const res  = await fetch('api/outgoing_rfq.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action, rfq_number: rfqNumber }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('orfqViewModal')).hide();
    loadOrfqs();
}

loadOrfqs();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
