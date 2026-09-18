<?php
require_once 'config.php';
requireModule('stock_transfer');
$currentUser = getCurrentUser();
$activePage  = 'stock_transfers';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
$isSuperAdmin = $currentUser['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Transfer | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.pending    { background: #fef3c7; color: #92400e; }
.badge-status.in_transit { background: #dbeafe; color: #1e40af; }
.badge-status.received   { background: #dcfce7; color: #166534; }
.badge-status.cancelled  { background: #fee2e2; color: #991b1b; }
.st-avail-row { display: flex; justify-content: space-between; align-items: center; padding: .4rem .1rem; border-bottom: 1px dashed var(--border); font-size: .85rem; }
.st-line-row { display: grid; grid-template-columns: 1.8fr .8fr auto; gap: .5rem; margin-bottom: .4rem; align-items: center; }
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
                <div class="page-title">Stock Transfer</div>
                <div class="small text-muted">Move inventory from one branch to another with tracking.</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Stock Transfer</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Branch-to-Branch Transfers</div>
                <button class="btn btn-primary-app btn-sm" onclick="openTransferModal()"><i class="fas fa-plus me-1"></i>New Transfer</button>
            </div>
            <div class="panel-body">
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item"><button class="nav-link active" onclick="setDirection('')">All</button></li>
                    <li class="nav-item"><button class="nav-link" onclick="setDirection('outgoing')">Outgoing</button></li>
                    <li class="nav-item"><button class="nav-link" onclick="setDirection('incoming')">Incoming</button></li>
                </ul>
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Transfer #</th><th>From</th><th>To</th><th>Status</th><th>Requested By</th><th>Date</th><th></th></tr></thead>
                    <tbody id="stBody"><tr><td colspan="7" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- New Transfer modal -->
<div class="modal fade" id="stModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Stock Transfer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="stForm">
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <?php if ($isSuperAdmin): ?>
            <div class="col-6"><label class="form-label">From Branch</label><select class="form-select" id="stFromBranch" onchange="onFromBranchPicked()" required></select></div>
            <?php else: ?>
            <div class="col-6"><label class="form-label">From Branch</label><input type="text" class="form-control" value="<?= e($currentUser['branch_name'] ?? '') ?>" disabled></div>
            <?php endif; ?>
            <div class="col-6"><label class="form-label">To Branch</label><select class="form-select" id="stToBranch" required></select></div>
          </div>

          <div id="stItemsWrap" style="display:<?= $isSuperAdmin ? 'none' : '' ?>">
              <label class="form-label">Available Stock at Source Branch</label>
              <div id="stAvailList" class="mb-3"></div>

              <label class="form-label">Items to Transfer</label>
              <div id="stLineRows"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Transfer modal -->
<div class="modal fade" id="stViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="stViewTitle">Stock Transfer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="stViewBody"></div>
      <div class="modal-footer" id="stViewActions"></div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isAdminTier  = <?= $isAdminTier ? 'true' : 'false' ?>;
const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const myBranchId   = <?= $currentUser['branch_id'] !== null ? (int)$currentUser['branch_id'] : 'null' ?>;
let stCache = [], currentAvail = { units: [], stock: [] }, curDirection = '';

function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const statusLabel = { pending: 'Pending', in_transit: 'In Transit', received: 'Received', cancelled: 'Cancelled' };
const qty = n => Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
const inputQty = n => Number(n || 0).toFixed(2).replace(/\.?0+$/, '');

function setDirection(dir) {
    curDirection = dir;
    document.querySelectorAll('.nav-tabs .nav-link').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
    loadTransfers();
}

async function loadTransfers() {
    const params = curDirection ? ('?direction=' + curDirection) : '';
    const res  = await fetch('api/stock_transfers.php' + params);
    const data = await res.json();
    if (!data.success) return alert(data.message);
    stCache = data.transfers;
    document.getElementById('stBody').innerHTML = stCache.length ? stCache.map(t => `
        <tr style="cursor:pointer" onclick="viewTransfer('${t.transfer_number}')">
            <td><strong>${escapeHtml(t.transfer_number)}</strong></td>
            <td>${escapeHtml(t.from_branch_name)}</td>
            <td>${escapeHtml(t.to_branch_name)}</td>
            <td><span class="badge-status ${t.status}">${statusLabel[t.status] || t.status}</span></td>
            <td>${escapeHtml(t.requested_by_name)}</td>
            <td>${AppDateTime.format(t.date_created)}</td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No transfers yet.</td></tr>';
}

async function loadBranchOptions(selectEl, excludeId) {
    const res  = await fetch('api/stock_transfers.php?branches=1');
    const data = await res.json();
    const branches = (data.success ? data.branches : []).filter(b => b.id != excludeId);
    selectEl.innerHTML = '<option value="">Select branch…</option>' + branches.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
}

async function openTransferModal() {
    document.getElementById('stForm').reset();
    document.getElementById('stLineRows').innerHTML = '';
    document.getElementById('stAvailList').innerHTML = '';
    currentAvail = { units: [], stock: [] };

    if (isSuperAdmin) {
        document.getElementById('stItemsWrap').style.display = 'none';
        await loadBranchOptions(document.getElementById('stFromBranch'), '');
        await loadBranchOptions(document.getElementById('stToBranch'), '');
    } else {
        await loadBranchOptions(document.getElementById('stToBranch'), myBranchId);
        await fetchAvailable(myBranchId);
    }
    new bootstrap.Modal(document.getElementById('stModal')).show();
}

async function onFromBranchPicked() {
    const branchId = document.getElementById('stFromBranch').value;
    await loadBranchOptions(document.getElementById('stToBranch'), branchId);
    if (!branchId) { document.getElementById('stItemsWrap').style.display = 'none'; return; }
    await fetchAvailable(branchId);
    document.getElementById('stItemsWrap').style.display = '';
}

async function fetchAvailable(branchId) {
    const res  = await fetch('api/stock_transfers.php?available_at=' + branchId);
    const data = await res.json();
    if (!data.success) return alert(data.message);
    currentAvail = data;
    document.getElementById('stLineRows').innerHTML = '';
    renderStAvailableItems();
}

function queuedStStockQty(itemId, excludeRow = null) {
    return [...document.querySelectorAll('#stLineRows .st-line-row')]
        .filter(row => row !== excludeRow && String(row.dataset.itemId || '') === String(itemId))
        .reduce((sum, row) => sum + Number(row.querySelector('.st-qty')?.value || 0), 0);
}

function queuedStUnitIds() {
    return new Set([...document.querySelectorAll('#stLineRows .st-line-row')]
        .map(row => row.dataset.inventoryUnitId)
        .filter(Boolean)
        .map(String));
}

function remainingStStock(itemId, excludeRow = null) {
    const stock = currentAvail.stock.find(s => String(s.item_id) === String(itemId));
    if (!stock) return 0;
    return Math.max(0, Number(stock.quantity_on_hand || 0) - queuedStStockQty(itemId, excludeRow));
}

function renderStAvailableItems() {
    const pickedUnits = queuedStUnitIds();
    const unitRows = currentAvail.units.map(u => {
        const picked = pickedUnits.has(String(u.id));
        return `
            <div class="st-avail-row">
                <span>${escapeHtml(u.item_name)} <span class="text-muted">(SN: ${escapeHtml(u.serial_number)})</span></span>
                <button type="button" class="btn btn-sm btn-light" onclick='addStLine("unit", ${JSON.stringify(u).replace(/'/g,"&#39;")})' ${picked ? 'disabled' : ''}><i class="fas fa-plus"></i> ${picked ? 'Queued' : 'Add'}</button>
            </div>`;
    }).join('');
    const stockRows = currentAvail.stock.map(s => {
        const queued = queuedStStockQty(s.item_id);
        const left = Math.max(0, Number(s.quantity_on_hand || 0) - queued);
        return `
            <div class="st-avail-row">
                <span>${escapeHtml(s.item_name)} <span class="text-muted">(${qty(s.quantity_on_hand)} ${escapeHtml(s.unit)} on hand)</span></span>
                <span>
                    ${queued > 0 ? `<span class="text-muted me-2">${qty(queued)} queued</span>` : ''}
                    <strong class="me-2">${qty(left)} left</strong>
                    <button type="button" class="btn btn-sm btn-light" onclick='addStLine("stock", ${JSON.stringify(s).replace(/'/g,"&#39;")})' ${left <= 0 ? 'disabled' : ''}><i class="fas fa-plus"></i> ${left <= 0 ? 'All queued' : 'Add'}</button>
                </span>
            </div>`;
    }).join('');
    document.getElementById('stAvailList').innerHTML = (unitRows + stockRows) || '<div class="text-muted small">No available stock at this branch.</div>';
}

function onStQtyChanged(input) {
    const row = input.closest('.st-line-row');
    if (!row) return;
    const max = remainingStStock(row.dataset.itemId, row);
    if (Number(input.value || 0) > max) input.value = inputQty(max);
    if (Number(input.value || 0) < 0) input.value = '';
    renderStAvailableItems();
}

function removeStLine(button) {
    button.closest('.st-line-row')?.remove();
    renderStAvailableItems();
}

function addStLine(kind, item) {
    const wrap = document.createElement('div');
    wrap.className = 'st-line-row';
    if (kind === 'unit') {
        if (queuedStUnitIds().has(String(item.id))) {
            alert('This serial unit is already queued for transfer.');
            renderStAvailableItems();
            return;
        }
        wrap.dataset.inventoryUnitId = item.id;
        wrap.innerHTML = `<span>${escapeHtml(item.item_name)} (SN: ${escapeHtml(item.serial_number)})</span><span class="text-muted small">1 unit</span>
            <button type="button" class="btn btn-sm btn-light" onclick="removeStLine(this)"><i class="fas fa-trash"></i></button>`;
    } else {
        const left = remainingStStock(item.item_id);
        if (left <= 0) {
            alert('No remaining stock left to queue for this item.');
            renderStAvailableItems();
            return;
        }
        wrap.dataset.itemId = item.item_id;
        wrap.innerHTML = `<span>${escapeHtml(item.item_name)}</span>
            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm st-qty" placeholder="Qty" value="${inputQty(left)}" max="${inputQty(left)}" oninput="onStQtyChanged(this)">
            <button type="button" class="btn btn-sm btn-light" onclick="removeStLine(this)"><i class="fas fa-trash"></i></button>`;
    }
    document.getElementById('stLineRows').appendChild(wrap);
    renderStAvailableItems();
}

document.getElementById('stForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const overStock = currentAvail.stock.find(s => queuedStStockQty(s.item_id) > Number(s.quantity_on_hand || 0) + 0.0001);
    if (overStock) return alert(`Cannot transfer more than the ${qty(overStock.quantity_on_hand)} on hand for ${overStock.item_name}.`);
    const items = [...document.querySelectorAll('#stLineRows .st-line-row')].map(row => {
        if (row.dataset.inventoryUnitId) return { inventory_unit_id: row.dataset.inventoryUnitId };
        return { item_id: row.dataset.itemId, quantity: row.querySelector('.st-qty').value };
    }).filter(l => l.inventory_unit_id || l.quantity);
    if (!items.length) return alert('Add at least one item to transfer.');

    const payload = {
        to_branch_id: document.getElementById('stToBranch').value,
        items,
    };
    if (isSuperAdmin) payload.from_branch_id = document.getElementById('stFromBranch').value;

    const res  = await fetch('api/stock_transfers.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('stModal')).hide();
    loadTransfers();
});

async function viewTransfer(transferNumber) {
    const res  = await fetch('api/stock_transfers.php?id=' + encodeURIComponent(transferNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const t = data.transfer;
    document.getElementById('stViewTitle').textContent = t.transfer_number;
    document.getElementById('stViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>From:</strong> ${escapeHtml(t.from_branch_name)}</div>
            <div class="col-6"><strong>To:</strong> ${escapeHtml(t.to_branch_name)}</div>
            <div class="col-6"><strong>Status:</strong> <span class="badge-status ${t.status}">${statusLabel[t.status] || t.status}</span></div>
            <div class="col-6"><strong>Requested By:</strong> ${escapeHtml(t.requested_by_name)}</div>
        </div>
        <table class="app-table table">
            <thead><tr><th>Item</th><th>Qty / Serial</th></tr></thead>
            <tbody>${t.items.map(i => `<tr><td>${escapeHtml(i.description_snapshot)}</td><td>${i.serial_snapshot ? 'SN: ' + escapeHtml(i.serial_snapshot) : i.quantity}</td></tr>`).join('')}</tbody>
        </table>`;

    const actions = [`<a class="btn btn-outline-secondary btn-sm me-auto" href="print_document.php?module=stock_transfer&id=${encodeURIComponent(t.transfer_number)}" data-report-modal><i class="fas fa-print me-1"></i>Print / PDF</a>`];
    if (isAdminTier && t.status === 'pending' && (isSuperAdmin || myBranchId == t.from_branch_id)) {
        actions.push(`<button class="btn btn-primary-app btn-sm" onclick="doTransferAction('${t.transfer_number}','ship')">Ship</button>`);
        actions.push(`<button class="btn btn-outline-danger btn-sm" onclick="doTransferAction('${t.transfer_number}','cancel')">Cancel</button>`);
    }
    if (isAdminTier && t.status === 'in_transit' && (isSuperAdmin || myBranchId == t.to_branch_id)) {
        actions.push(`<button class="btn btn-primary-app btn-sm" onclick="doTransferAction('${t.transfer_number}','receive')">Receive</button>`);
    }
    document.getElementById('stViewActions').innerHTML = actions.join(' ') || '<span class="text-muted small">No actions available.</span>';
    new bootstrap.Modal(document.getElementById('stViewModal')).show();
}

async function doTransferAction(transferNumber, action) {
    if (action === 'cancel' && !confirm('Cancel this transfer request?')) return;
    const res  = await fetch('api/stock_transfers.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action, transfer_number: transferNumber }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('stViewModal')).hide();
    loadTransfers();
}

loadTransfers();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
