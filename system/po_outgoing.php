<?php
require_once 'config.php';
requireModule('po_outgoing');
$currentUser = getCurrentUser();
$activePage  = 'po_outgoing';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Orders (Outgoing) | <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%232563eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' font-weight='700' fill='white' text-anchor='middle'%3EE%3C/text%3E%3C/svg%3E">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.open                 { background: #dbeafe; color: #1e40af; }
.badge-status.partially_delivered  { background: #ffedd5; color: #9a3412; }
.badge-status.delivered            { background: #fef3c7; color: #92400e; }
.badge-status.completed            { background: #dcfce7; color: #166534; }
.badge-status.cancelled            { background: #fee2e2; color: #991b1b; }
.poo-item-row { display: grid; grid-template-columns: 1.6fr 1.6fr .8fr .8fr .9fr auto; gap: .5rem; margin-bottom: .5rem; align-items: center; }
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
                <div class="page-title">Purchase Orders (Outgoing)</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> PO (Outgoing)</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Client Orders Awarded to EXECOM</div>
                <?php if ($isAdminTier): ?>
                <button class="btn btn-primary-app btn-sm" onclick="openPoModal()"><i class="fas fa-plus me-1"></i>New Purchase Order</button>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>PO Number</th><th>Branch</th><th>Client</th><th>From Quotation</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody id="pooBody"><tr><td colspan="7" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- New PO modal -->
<div class="modal fade" id="pooModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Outgoing Purchase Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="pooForm">
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Client</label><select class="form-select" id="pooClient" required></select></div>
            <div class="col-6"><label class="form-label">End User</label><input type="text" class="form-control" id="pooEndUser"></div>
            <div class="col-6"><label class="form-label">Place of Delivery</label><input type="text" class="form-control" id="pooPlace"></div>
            <div class="col-3"><label class="form-label">Delivery Term</label><input type="text" class="form-control" id="pooDeliveryTerm"></div>
            <div class="col-3"><label class="form-label">Payment Term</label><input type="text" class="form-control" id="pooPaymentTerm"></div>
          </div>
          <label class="form-label">Items</label>
          <div id="pooItemRows"></div>
          <button type="button" class="btn btn-light btn-sm" onclick="addPooItemRow()"><i class="fas fa-plus me-1"></i>Add Item</button>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Create Purchase Order</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View PO modal -->
<div class="modal fade" id="pooViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="pooViewTitle">Purchase Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="pooViewBody"></div>
      <div class="modal-footer" id="pooViewFooter"></div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isAdminTier = <?= $isAdminTier ? 'true' : 'false' ?>;
let pooCache = [], catalogItems = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const statusLabel = { open: 'Open', partially_delivered: 'Partially Delivered', delivered: 'Delivered', completed: 'Completed', cancelled: 'Cancelled' };
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2});

async function loadPos() {
    const res  = await fetch('api/po_outgoing.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    pooCache = data.pos;
    document.getElementById('pooBody').innerHTML = pooCache.length ? pooCache.map(p => `
        <tr style="cursor:pointer" onclick="viewPo('${p.po_number}')">
            <td><strong>${escapeHtml(p.po_number)}</strong></td>
            <td>${escapeHtml(p.branch_name)}</td>
            <td>${escapeHtml(p.client_name)}</td>
            <td>${escapeHtml(p.outgoing_quotation_number || '—')}</td>
            <td>${peso(p.total_amount)}</td>
            <td><span class="badge-status ${p.status}">${statusLabel[p.status] || p.status}</span></td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No purchase orders yet.</td></tr>';
}

async function openPoModal() {
    document.getElementById('pooForm').reset();
    document.getElementById('pooItemRows').innerHTML = '';
    const [cliRes, itmRes] = await Promise.all([fetch('api/clients.php'), fetch('api/items.php')]);
    const cliData = await cliRes.json(), itmData = await itmRes.json();
    document.getElementById('pooClient').innerHTML = '<option value="">Select…</option>' +
        (cliData.success ? cliData.clients.filter(c => c.is_active).map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('') : '');
    catalogItems = itmData.success ? itmData.items.filter(i => i.is_active) : [];
    addPooItemRow();
    new bootstrap.Modal(document.getElementById('pooModal')).show();
}

function addPooItemRow() {
    const wrap = document.createElement('div');
    wrap.className = 'poo-item-row';
    const itemOpts = catalogItems.map(i => `<option value="${i.id}" data-price="${i.default_price ?? ''}" data-unit="${escapeHtml(i.unit)}">${escapeHtml(i.name)}${i.sku ? ' ('+escapeHtml(i.sku)+')' : ''}</option>`).join('');
    wrap.innerHTML = `
        <select class="form-select form-select-sm poo-item-select" onchange="onPooItemPicked(this)">
            <option value="">— Custom (not in catalog) —</option>${itemOpts}
        </select>
        <input type="text" class="form-control form-control-sm poo-desc" placeholder="Description (if custom)">
        <input type="number" step="0.01" class="form-control form-control-sm poo-qty" placeholder="Qty">
        <input type="text" class="form-control form-control-sm poo-unit" placeholder="Unit">
        <input type="number" step="0.01" class="form-control form-control-sm poo-price" placeholder="Unit Price">
        <button type="button" class="btn btn-sm btn-light" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>`;
    document.getElementById('pooItemRows').appendChild(wrap);
}

function onPooItemPicked(select) {
    const row = select.closest('.poo-item-row');
    const opt = select.selectedOptions[0];
    const descField = row.querySelector('.poo-desc');
    if (select.value) {
        descField.value = opt.textContent.trim();
        descField.disabled = true;
        if (opt.dataset.unit) row.querySelector('.poo-unit').value = opt.dataset.unit;
        if (opt.dataset.price) row.querySelector('.poo-price').value = opt.dataset.price;
    } else {
        descField.value = '';
        descField.disabled = false;
    }
}

document.getElementById('pooForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const items = [...document.querySelectorAll('#pooItemRows .poo-item-row')].map(row => {
        const itemId = row.querySelector('.poo-item-select').value;
        return {
            item_id: itemId || undefined,
            description: row.querySelector('.poo-desc').value.trim(),
            quantity: row.querySelector('.poo-qty').value,
            unit: row.querySelector('.poo-unit').value.trim(),
            unit_price: row.querySelector('.poo-price').value,
        };
    }).filter(i => i.quantity && i.unit_price !== '' && i.description);
    if (!items.length) return alert('Add at least one item with quantity and unit price.');

    const payload = {
        action: 'create',
        client_id: document.getElementById('pooClient').value,
        end_user: document.getElementById('pooEndUser').value.trim(),
        place_of_delivery: document.getElementById('pooPlace').value.trim(),
        delivery_term: document.getElementById('pooDeliveryTerm').value.trim(),
        payment_term: document.getElementById('pooPaymentTerm').value.trim(),
        items,
    };
    const res  = await fetch('api/po_outgoing.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('pooModal')).hide();
    loadPos();
});

async function viewPo(poNumber) {
    const res  = await fetch('api/po_outgoing.php?id=' + encodeURIComponent(poNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const po = data.po;
    document.getElementById('pooViewTitle').textContent = po.po_number + ' — ' + statusLabel[po.status];
    document.getElementById('pooViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(po.branch_name)}</div>
            <div class="col-6"><strong>Client:</strong> ${escapeHtml(po.client_name)}</div>
            <div class="col-6"><strong>End User:</strong> ${escapeHtml(po.end_user || '—')}</div>
            <div class="col-6"><strong>Place of Delivery:</strong> ${escapeHtml(po.place_of_delivery || '—')}</div>
            <div class="col-6"><strong>Delivery Term:</strong> ${escapeHtml(po.delivery_term || '—')}</div>
            <div class="col-6"><strong>Payment Term:</strong> ${escapeHtml(po.payment_term || '—')}</div>
        </div>
        <table class="app-table table">
            <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Issued</th><th>Delivered</th></tr></thead>
            <tbody>${po.items.map(i => `<tr><td>${escapeHtml(i.item_name || i.description)}</td><td>${i.quantity}</td><td>${peso(i.unit_price)}</td><td>${peso(i.total_price)}</td><td>${i.issued_qty}/${i.quantity}</td><td>${i.delivered_qty}/${i.quantity}</td></tr>`).join('')}</tbody>
            <tfoot><tr><td colspan="3" class="text-end fw-bold">Total</td><td class="fw-bold">${peso(po.total_amount)}</td><td colspan="2"></td></tr></tfoot>
        </table>`;

    let footer = '';
    if (isAdminTier && po.status === 'open') {
        footer += `<button class="btn btn-danger btn-sm me-auto" onclick="poAction('${po.po_number}','cancel')">Cancel</button>`;
    }
    if (isAdminTier && po.status === 'delivered') {
        footer += `<button class="btn btn-primary-app btn-sm" onclick="poAction('${po.po_number}','complete')">Mark Completed</button>`;
    }
    document.getElementById('pooViewFooter').innerHTML = footer || '<span class="text-muted small">No actions available.</span>';
    new bootstrap.Modal(document.getElementById('pooViewModal')).show();
}

async function poAction(poNumber, action) {
    if (action === 'cancel' && !confirm('Cancel this Purchase Order?')) return;
    const res  = await fetch('api/po_outgoing.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action, po_number: poNumber }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('pooViewModal')).hide();
    loadPos();
}

loadPos();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
