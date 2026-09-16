<?php
require_once 'config.php';
requireModule('po_internal');
$currentUser = getCurrentUser();
$activePage  = 'po_internal';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Orders (Internal) | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.draft               { background: #f1f5f9; color: #475569; }
.badge-status.sent                { background: #fef3c7; color: #92400e; }
.badge-status.partially_received  { background: #ffedd5; color: #9a3412; }
.badge-status.received            { background: #dcfce7; color: #166534; }
.badge-status.cancelled           { background: #fee2e2; color: #991b1b; }
.po-item-row { display: grid; grid-template-columns: 1.6fr 1.6fr .8fr .8fr .9fr auto; gap: .5rem; margin-bottom: .5rem; align-items: center; }
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
                <div class="page-title">Purchase Orders (Internal)</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> PO (Internal)</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">EXECOM's Orders to Suppliers</div>
                <?php if ($isAdminTier): ?>
                <button class="btn btn-primary-app btn-sm" onclick="openPoModal()"><i class="fas fa-plus me-1"></i>New Purchase Order</button>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>PO Number</th><th>Branch</th><th>Supplier</th><th>From RFQ</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody id="poBody"><tr><td colspan="7" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- New PO modal -->
<div class="modal fade" id="poModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Purchase Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="poForm">
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Supplier</label><select class="form-select" id="poSupplier" required></select></div>
            <div class="col-6"><label class="form-label">Forwarder</label><input type="text" class="form-control" id="poForwarder"></div>
            <div class="col-12"><label class="form-label">Ship To Address</label><input type="text" class="form-control" id="poShipTo"></div>
          </div>
          <label class="form-label">Items</label>
          <div id="poItemRows"></div>
          <button type="button" class="btn btn-light btn-sm" onclick="addPoItemRow()"><i class="fas fa-plus me-1"></i>Add Item</button>
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
<div class="modal fade" id="poViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="poViewTitle">Purchase Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="poViewBody"></div>
      <div class="modal-footer" id="poViewFooter"></div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isAdminTier = <?= $isAdminTier ? 'true' : 'false' ?>;
let poCache = [], catalogItems = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const statusLabel = { draft: 'Draft', sent: 'Sent', partially_received: 'Partially Received', received: 'Received', cancelled: 'Cancelled' };
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2});

async function loadPos() {
    const res  = await fetch('api/po_internal.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    poCache = data.pos;
    document.getElementById('poBody').innerHTML = poCache.length ? poCache.map(p => `
        <tr style="cursor:pointer" onclick="viewPo('${p.po_number}')">
            <td><strong>${escapeHtml(p.po_number)}</strong></td>
            <td>${escapeHtml(p.branch_name)}</td>
            <td>${escapeHtml(p.supplier_name)}</td>
            <td>${escapeHtml(p.internal_rfq_number || '—')}</td>
            <td>${peso(p.total_amount)}</td>
            <td><span class="badge-status ${p.status}">${statusLabel[p.status] || p.status}</span></td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No purchase orders yet.</td></tr>';
}

async function openPoModal() {
    document.getElementById('poForm').reset();
    document.getElementById('poItemRows').innerHTML = '';
    const [supRes, itmRes] = await Promise.all([fetch('api/suppliers.php'), fetch('api/items.php')]);
    const supData = await supRes.json(), itmData = await itmRes.json();
    document.getElementById('poSupplier').innerHTML = '<option value="">Select…</option>' +
        (supData.success ? supData.suppliers.filter(s => s.is_active).map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('') : '');
    catalogItems = itmData.success ? itmData.items.filter(i => i.is_active) : [];
    addPoItemRow();
    new bootstrap.Modal(document.getElementById('poModal')).show();
}

function addPoItemRow() {
    const wrap = document.createElement('div');
    wrap.className = 'po-item-row';
    const itemOpts = catalogItems.map(i => `<option value="${i.id}" data-cost="${i.default_cost ?? ''}" data-unit="${escapeHtml(i.unit)}">${escapeHtml(i.name)}${i.sku ? ' ('+escapeHtml(i.sku)+')' : ''}</option>`).join('');
    wrap.innerHTML = `
        <select class="form-select form-select-sm po-item-select" onchange="onPoItemPicked(this)">
            <option value="">— Custom (not in catalog) —</option>${itemOpts}
        </select>
        <input type="text" class="form-control form-control-sm po-desc" placeholder="Description (if custom)">
        <input type="number" step="0.01" class="form-control form-control-sm po-qty" placeholder="Qty">
        <input type="text" class="form-control form-control-sm po-unit" placeholder="Unit">
        <input type="number" step="0.01" class="form-control form-control-sm po-cost" placeholder="Unit Cost">
        <button type="button" class="btn btn-sm btn-light" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>`;
    document.getElementById('poItemRows').appendChild(wrap);
}

function onPoItemPicked(select) {
    const row = select.closest('.po-item-row');
    const opt = select.selectedOptions[0];
    const descField = row.querySelector('.po-desc');
    if (select.value) {
        descField.value = opt.textContent.trim();
        descField.disabled = true;
        if (opt.dataset.unit) row.querySelector('.po-unit').value = opt.dataset.unit;
        if (opt.dataset.cost) row.querySelector('.po-cost').value = opt.dataset.cost;
    } else {
        descField.value = '';
        descField.disabled = false;
    }
}

document.getElementById('poForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const items = [...document.querySelectorAll('#poItemRows .po-item-row')].map(row => {
        const itemId = row.querySelector('.po-item-select').value;
        return {
            item_id: itemId || undefined,
            description: row.querySelector('.po-desc').value.trim(),
            quantity: row.querySelector('.po-qty').value,
            unit: row.querySelector('.po-unit').value.trim(),
            unit_cost: row.querySelector('.po-cost').value,
        };
    }).filter(i => i.quantity && i.unit_cost !== '' && i.description);
    if (!items.length) return alert('Add at least one item with quantity and unit cost.');

    const payload = {
        action: 'create',
        supplier_id: document.getElementById('poSupplier').value,
        forwarder: document.getElementById('poForwarder').value.trim(),
        ship_to_address: document.getElementById('poShipTo').value.trim(),
        items,
    };
    const res  = await fetch('api/po_internal.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('poModal')).hide();
    loadPos();
});

async function viewPo(poNumber) {
    const res  = await fetch('api/po_internal.php?id=' + encodeURIComponent(poNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const po = data.po;
    document.getElementById('poViewTitle').textContent = po.po_number + ' — ' + statusLabel[po.status];
    document.getElementById('poViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(po.branch_name)}</div>
            <div class="col-6"><strong>Supplier:</strong> ${escapeHtml(po.supplier_name)}</div>
            <div class="col-6"><strong>Forwarder:</strong> ${escapeHtml(po.forwarder || '—')}</div>
            <div class="col-6"><strong>Ship To:</strong> ${escapeHtml(po.ship_to_address || '—')}</div>
        </div>
        <table class="app-table table">
            <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Unit Cost</th><th>Total</th><th>Received</th></tr></thead>
            <tbody>${po.items.map(i => `<tr><td>${escapeHtml(i.item_name || i.description)}</td><td>${i.quantity}</td><td>${escapeHtml(i.unit || '—')}</td><td>${peso(i.unit_cost)}</td><td>${peso(i.total_cost)}</td><td>${i.received_qty}/${i.quantity}</td></tr>`).join('')}</tbody>
            <tfoot><tr><td colspan="4" class="text-end fw-bold">Total</td><td class="fw-bold">${peso(po.total_amount)}</td><td></td></tr></tfoot>
        </table>`;

    let footer = '';
    if (isAdminTier && po.status === 'draft') {
        footer += `<button class="btn btn-danger btn-sm me-auto" onclick="poAction('${po.po_number}','cancel')">Cancel</button>
                    <button class="btn btn-primary-app btn-sm" onclick="poAction('${po.po_number}','send')">Mark as Sent</button>`;
    } else if (isAdminTier && po.status === 'sent') {
        footer += `<button class="btn btn-danger btn-sm" onclick="poAction('${po.po_number}','cancel')">Cancel</button>`;
    }
    footer += `<a href="print_po_internal.php?po_number=${encodeURIComponent(po.po_number)}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i>Print</a>`;
    document.getElementById('poViewFooter').innerHTML = footer || '<span class="text-muted small">No actions available.</span>';
    new bootstrap.Modal(document.getElementById('poViewModal')).show();
}

async function poAction(poNumber, action) {
    if (action === 'cancel' && !confirm('Cancel this Purchase Order?')) return;
    const res  = await fetch('api/po_internal.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action, po_number: poNumber }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('poViewModal')).hide();
    loadPos();
}

loadPos();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
