<?php
require_once 'config.php';
requireModule('po_outgoing');
$currentUser = getCurrentUser();
$activePage  = 'po_outgoing';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
$isSuperAdmin = $currentUser['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Orders (Outgoing) | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
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
.poo-progress-text { min-width: 6.5rem; font-size: .82rem; color: #475569; white-space: nowrap; }
.poo-progress .progress { height: .45rem; background: #e2e8f0; }
.poo-progress .progress-bar { background: #2563eb; }
.poo-progress.complete .progress-bar { background: #16a34a; }
.poo-progress.over .progress-bar { background: #dc2626; }
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
                <div class="small text-muted">Record client purchase orders awarded from quotations or direct sales.</div>
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
                    <thead><tr><th>PO Number</th><th>Branch</th><th>Client</th><th>From Quotation</th><th>Total</th><th>Delivered to Client</th><th>Status</th><th></th></tr></thead>
                    <tbody id="pooBody"><tr><td colspan="8" class="text-muted small">Loading…</td></tr></tbody>
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
	            <?php if ($isSuperAdmin): ?>
	            <div class="col-12"><label class="form-label">Branch</label><select class="form-select" id="pooBranch" required></select></div>
	            <?php endif; ?>
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
const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
let pooCache = [], catalogItems = [], branchOptions = null;
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const statusLabel = { open: 'Open', partially_delivered: 'Partially Delivered', delivered: 'Delivered', completed: 'Completed', cancelled: 'Cancelled' };
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
const qty = n => Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});

async function loadBranchOptions(selectId) {
    if (!isSuperAdmin) return;
    if (!branchOptions) {
        const res = await fetch('api/branches.php');
        const data = await res.json();
        branchOptions = data.success ? data.branches.filter(b => b.is_active == 1) : [];
    }
    document.getElementById(selectId).innerHTML = '<option value="">Select branch...</option>' +
        branchOptions.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
}

function progressPercent(done, total) {
    const ordered = Number(total || 0);
    if (ordered <= 0) return 0;
    return Math.min(100, Math.max(0, (Number(done || 0) / ordered) * 100));
}

function progressState(done, total) {
    const delivered = Number(done || 0);
    const ordered = Number(total || 0);
    if (ordered > 0 && delivered > ordered + 0.0001) return 'over';
    if (ordered > 0 && delivered + 0.0001 >= ordered) return 'complete';
    return '';
}

function deliveryProgressCell(done, total) {
    const percent = progressPercent(done, total);
    const label = `${qty(done)} / ${qty(total)}`;
    return `
        <div class="poo-progress ${progressState(done, total)}">
            <div class="d-flex align-items-center gap-2">
                <span class="poo-progress-text">${label}</span>
                <div class="progress flex-grow-1" role="progressbar" aria-label="Delivered to client" aria-valuenow="${Math.round(percent)}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: ${percent}%"></div>
                </div>
            </div>
            <div class="small text-muted">${Math.round(percent)}% delivered</div>
        </div>`;
}

async function loadPos() {
    const res  = await fetch('api/po_outgoing.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    pooCache = data.pos;
    document.getElementById('pooBody').innerHTML = pooCache.length ? pooCache.map(p => `
        <tr style="cursor:pointer" onclick="viewPo('${p.po_number}')">
            <td><span class="doc-number doc-poo">${escapeHtml(p.po_number)}</span></td>
            <td>${escapeHtml(p.branch_name)}</td>
            <td>${escapeHtml(p.client_name)}</td>
            <td>${escapeHtml(p.outgoing_quotation_number || '—')}</td>
            <td>${peso(p.total_amount)}</td>
            <td>${deliveryProgressCell(p.delivered_qty, p.ordered_qty)}</td>
            <td><span class="badge-status ${p.status}">${statusLabel[p.status] || p.status}</span></td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="8" class="text-muted small">No purchase orders yet.</td></tr>';
}

async function openPoModal() {
    document.getElementById('pooForm').reset();
    document.getElementById('pooItemRows').innerHTML = '';
    await loadBranchOptions('pooBranch');
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
	    if (isSuperAdmin) {
	        const branchId = document.getElementById('pooBranch').value;
	        if (!branchId) return alert('Select a branch.');
	        payload.branch_id = branchId;
	    }
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
        <div class="mb-2 fw-semibold">Item delivery progress</div>
        <table class="app-table table align-middle">
            <thead><tr><th>Description</th><th>Ordered</th><th>Issued</th><th>Delivered to Client</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody>${po.items.map(i => `<tr><td>${escapeHtml(i.item_name || i.description)}</td><td>${qty(i.quantity)}</td><td>${qty(i.issued_qty)} / ${qty(i.quantity)}</td><td>${deliveryProgressCell(i.delivered_qty, i.quantity)}</td><td>${peso(i.unit_price)}</td><td>${peso(i.total_price)}</td></tr>`).join('')}</tbody>
            <tfoot><tr><td colspan="5" class="text-end fw-bold">Total</td><td class="fw-bold">${peso(po.total_amount)}</td></tr></tfoot>
        </table>`;

    let footer = '';
    if (isAdminTier && po.status === 'open') {
        footer += `<button class="btn btn-danger btn-sm me-auto" onclick="poAction('${po.po_number}','cancel')">Cancel</button>`;
    }
    if (isAdminTier && po.status === 'delivered') {
        footer += `<button class="btn btn-primary-app btn-sm" onclick="poAction('${po.po_number}','complete')">Mark Completed</button>`;
    }
    footer += `<a class="btn btn-outline-secondary btn-sm" href="print_document.php?module=po_outgoing&id=${encodeURIComponent(po.po_number)}" data-report-modal><i class="fas fa-print me-1"></i>Print / PDF</a>`;
    document.getElementById('pooViewFooter').innerHTML = footer;
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
