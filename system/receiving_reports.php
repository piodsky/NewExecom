<?php
require_once 'config.php';
requireModule('receiving_reports');
$currentUser = getCurrentUser();
$activePage  = 'receiving_reports';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receiving Reports | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.rr-avail-row { display: flex; justify-content: space-between; align-items: center; padding: .4rem .1rem; border-bottom: 1px dashed var(--border); font-size: .85rem; }
.rr-line-row { display: grid; grid-template-columns: 1.6fr .7fr .8fr 1fr auto; gap: .5rem; margin-bottom: .4rem; align-items: center; }
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
                <div class="page-title">Receiving Reports</div>
                <div class="small text-muted">Record supplier deliveries and add accepted items to inventory.</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Receiving Reports</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Goods Received from Suppliers</div>
                <?php if ($isAdminTier): ?>
                <button class="btn btn-primary-app btn-sm" onclick="openRrModal()"><i class="fas fa-plus me-1"></i>New Receiving Report</button>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>RR Number</th><th>Branch</th><th>PO Number</th><th>Date Received</th><th>Received By</th><th></th></tr></thead>
                    <tbody id="rrBody"><tr><td colspan="6" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- New Receiving Report modal -->
<div class="modal fade" id="rrModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Receiving Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="rrForm">
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Purchase Order</label><select class="form-select" id="rrPo" onchange="onRrPoPicked()" required></select></div>
            <div class="col-6"><label class="form-label">Date &amp; Time Received</label><input type="datetime-local" step="1" class="form-control" id="rrDate" value="<?= date('Y-m-d\TH:i:s') ?>"></div>
            <div class="col-12"><label class="form-label">Condition Notes</label><input type="text" class="form-control" id="rrCondition" placeholder="e.g. all items in good condition"></div>
          </div>

          <div id="rrPoItemsWrap" style="display:none">
              <label class="form-label">Outstanding Items on this PO</label>
              <div id="rrAvailList" class="mb-3"></div>

              <label class="form-label">Lines to Receive</label>
              <div id="rrLineRows"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Post Receiving Report</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View RR modal -->
<div class="modal fade" id="rrViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="rrViewTitle">Receiving Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="rrViewBody"></div>
      <div class="modal-footer"><a class="btn btn-outline-secondary btn-sm" id="rrPrintLink" data-report-modal><i class="fas fa-print me-1"></i>Print / PDF</a></div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
let rrCache = [], currentPoItems = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
const qty = n => Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
const inputQty = n => Number(n || 0).toFixed(2).replace(/\.?0+$/, '');

async function loadRrs() {
    const res  = await fetch('api/receiving_reports.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    rrCache = data.receiving_reports;
    document.getElementById('rrBody').innerHTML = rrCache.length ? rrCache.map(r => `
        <tr style="cursor:pointer" onclick="viewRr('${r.rr_number}')">
            <td><span class="doc-number doc-rr">${escapeHtml(r.rr_number)}</span></td>
            <td>${escapeHtml(r.branch_name)}</td>
            <td>${escapeHtml(r.po_number)}</td>
            <td>${AppDateTime.format(r.date_received)}</td>
            <td>${escapeHtml(r.received_by_name)}</td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted small">No receiving reports yet.</td></tr>';
}

async function openRrModal() {
    document.getElementById('rrForm').reset();
    document.getElementById('rrDate').value = AppDateTime.nowInput();
    document.getElementById('rrPoItemsWrap').style.display = 'none';
    const res  = await fetch('api/po_internal.php?open_for_receiving=1');
    const data = await res.json();
    document.getElementById('rrPo').innerHTML = '<option value="">Select a Purchase Order…</option>' +
        (data.success ? data.pos.map(p => `<option value="${p.po_number}">${p.po_number} — ${escapeHtml(p.supplier_name)}</option>`).join('') : '');
    new bootstrap.Modal(document.getElementById('rrModal')).show();
}

async function onRrPoPicked() {
    const poNumber = document.getElementById('rrPo').value;
    document.getElementById('rrLineRows').innerHTML = '';
    if (!poNumber) { document.getElementById('rrPoItemsWrap').style.display = 'none'; return; }

    const res  = await fetch('api/po_internal.php?id=' + encodeURIComponent(poNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    currentPoItems = data.po.items.filter(i => i.item_id).map(i => ({
        ...i,
        item_id: String(i.item_id),
        quantity: Number(i.quantity || 0),
        received_qty: Number(i.received_qty || 0),
        remaining: Math.max(0, Number(i.quantity || 0) - Number(i.received_qty || 0)),
        is_serialized: Number(i.is_serialized || 0) === 1,
    }));
    renderRrAvailableItems();
    document.getElementById('rrPoItemsWrap').style.display = '';
}

function getRrItem(itemId) {
    return currentPoItems.find(i => String(i.item_id) === String(itemId));
}

function queuedRrQty(itemId, excludeRow = null) {
    return [...document.querySelectorAll('#rrLineRows .rr-line-row')]
        .filter(row => row !== excludeRow && String(row.dataset.itemId) === String(itemId))
        .reduce((sum, row) => sum + Number(row.querySelector('.rr-qty').value || 0), 0);
}

function remainingToQueue(itemId, excludeRow = null) {
    const item = getRrItem(itemId);
    if (!item) return 0;
    return Math.max(0, item.remaining - queuedRrQty(itemId, excludeRow));
}

function renderRrAvailableItems() {
    const rows = currentPoItems.map(i => {
        const queued = queuedRrQty(i.item_id);
        const left = Math.max(0, i.remaining - queued);
        const disabled = left <= 0 ? 'disabled' : '';
        const label = left <= 0 ? 'All queued' : 'Add';
        return `
            <div class="rr-avail-row">
                <span>${escapeHtml(i.item_name)} <span class="text-muted">(ordered ${qty(i.quantity)}, received ${qty(i.received_qty)})</span></span>
                <span>
                    ${queued > 0 ? `<span class="text-muted me-2">${qty(queued)} queued</span>` : ''}
                    <strong class="me-2">${qty(left)} left</strong>
                    <button type="button" class="btn btn-sm btn-light" onclick="addRrLine('${escapeHtml(i.item_id)}')" ${disabled}><i class="fas fa-plus"></i> ${label}</button>
                </span>
            </div>`;
    }).join('');
    document.getElementById('rrAvailList').innerHTML = rows || '<div class="text-muted small">This PO has no catalog-linked items to receive.</div>';
}

function onRrQtyChanged(input) {
    const row = input.closest('.rr-line-row');
    if (!row) return;
    const max = remainingToQueue(row.dataset.itemId, row);
    const value = Number(input.value || 0);
    if (value > max) input.value = inputQty(max);
    if (Number(input.value || 0) < 0) input.value = '';
    renderRrAvailableItems();
}

function removeRrLine(button) {
    button.closest('.rr-line-row')?.remove();
    renderRrAvailableItems();
}

function addRrLine(itemId) {
    const item = getRrItem(itemId);
    if (!item) return;
    const left = remainingToQueue(itemId);
    if (left <= 0) {
        alert('No remaining quantity left to receive for this item.');
        renderRrAvailableItems();
        return;
    }
    const qtyValue = item.is_serialized ? 1 : inputQty(left);
    const wrap = document.createElement('div');
    wrap.className = 'rr-line-row';
    wrap.dataset.itemId = item.item_id;
    wrap.dataset.serialized = item.is_serialized ? '1' : '0';
    wrap.innerHTML = `
        <span>${escapeHtml(item.item_name)}</span>
        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm rr-qty" placeholder="Qty" value="${qtyValue}" ${item.is_serialized ? 'readonly' : ''} max="${inputQty(left)}" oninput="onRrQtyChanged(this)">
        <input type="number" step="0.01" class="form-control form-control-sm rr-cost" placeholder="Unit Cost" value="${item.unit_cost ?? ''}">
        <input type="text" class="form-control form-control-sm rr-serial" placeholder="${item.is_serialized ? 'Serial number (required)' : 'Serial (n/a)'}" ${item.is_serialized ? '' : 'disabled'}>
        <button type="button" class="btn btn-sm btn-light" onclick="removeRrLine(this)"><i class="fas fa-trash"></i></button>`;
    document.getElementById('rrLineRows').appendChild(wrap);
    renderRrAvailableItems();
}

document.getElementById('rrForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const overQueued = currentPoItems.find(i => queuedRrQty(i.item_id) > i.remaining + 0.0001);
    if (overQueued) {
        return alert(`Cannot receive more than the remaining ${qty(overQueued.remaining)} ordered for ${overQueued.item_name}.`);
    }
    const items = [...document.querySelectorAll('#rrLineRows .rr-line-row')].map(row => ({
        item_id: row.dataset.itemId,
        quantity: row.querySelector('.rr-qty').value,
        unit_cost: row.querySelector('.rr-cost').value,
        serial_number: row.querySelector('.rr-serial').value.trim(),
    })).filter(i => i.quantity);
    if (!items.length) return alert('Add at least one line to receive.');

    const payload = {
        po_number: document.getElementById('rrPo').value,
        date_received: document.getElementById('rrDate').value,
        condition_notes: document.getElementById('rrCondition').value.trim(),
        items,
    };
    const res  = await fetch('api/receiving_reports.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('rrModal')).hide();
    loadRrs();
});

async function viewRr(rrNumber) {
    const res  = await fetch('api/receiving_reports.php?id=' + encodeURIComponent(rrNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const rr = data.rr;
    document.getElementById('rrPrintLink').href = 'print_document.php?' + new URLSearchParams({ module: 'receiving_reports', id: rr.rr_number });
    document.getElementById('rrViewTitle').textContent = rr.rr_number;
    document.getElementById('rrViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(rr.branch_name)}</div>
            <div class="col-6"><strong>PO Number:</strong> ${escapeHtml(rr.po_number)}</div>
            <div class="col-6"><strong>Date &amp; Time Received:</strong> ${AppDateTime.format(rr.date_received)}</div>
            <div class="col-6"><strong>Received By:</strong> ${escapeHtml(rr.received_by_name)}</div>
            <div class="col-12"><strong>Condition Notes:</strong> ${escapeHtml(rr.condition_notes || '—')}</div>
        </div>
        <table class="app-table table">
            <thead><tr><th>Item</th><th>Qty</th><th>Unit Cost</th><th>Serial</th></tr></thead>
            <tbody>${rr.items.map(i => `<tr><td>${escapeHtml(i.item_name)}</td><td>${i.quantity}</td><td>${peso(i.unit_cost)}</td><td>${escapeHtml(i.serial_number || '—')}</td></tr>`).join('')}</tbody>
        </table>`;
    new bootstrap.Modal(document.getElementById('rrViewModal')).show();
}

loadRrs();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
