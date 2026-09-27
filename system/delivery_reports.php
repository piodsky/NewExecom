<?php
require_once 'config.php';
requireModule('delivery_reports');
$currentUser = getCurrentUser();
$activePage  = 'delivery_reports';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delivery Reports | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.dr-avail-row { display: flex; justify-content: space-between; align-items: center; padding: .4rem .1rem; border-bottom: 1px dashed var(--border); font-size: .85rem; }
.dr-line-row { display: grid; grid-template-columns: 1.6fr .8fr auto; gap: .5rem; margin-bottom: .4rem; align-items: center; }
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
                    <h2 class="page-title">Delivery Reports</h2>
                    <div class="text-muted small">Record deliveries sent to clients from outgoing purchase orders.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Proof of Delivery to Clients</div>
                <?php if ($isAdminTier): ?>
                <button class="btn btn-primary-app btn-sm" onclick="openDrModal()"><i class="fas fa-plus me-1"></i>New Delivery Report</button>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>DR Number</th><th>Branch</th><th>PO Number</th><th>Date Delivered</th><th>Received By</th><th>Accepted</th><th></th></tr></thead>
                    <tbody id="drBody"><tr><td colspan="7" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- New Delivery Report modal -->
<div class="modal fade" id="drModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Delivery Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="drForm">
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Purchase Order</label><select class="form-select" id="drPo" onchange="onDrPoPicked()" required></select></div>
            <div class="col-6"><label class="form-label">Date &amp; Time Delivered</label><input type="datetime-local" step="1" class="form-control" id="drDate" value="<?= date('Y-m-d\TH:i:s') ?>"></div>
            <div class="col-6"><label class="form-label">Forwarder</label><input type="text" class="form-control" id="drForwarder"></div>
            <div class="col-6"><label class="form-label">Received By (client signatory)</label><input type="text" class="form-control" id="drReceivedBy"></div>
            <div class="col-6"><label class="form-label">Date &amp; Time Accepted</label><input type="datetime-local" step="1" class="form-control" id="drAccepted"></div>
          </div>

          <div id="drPoItemsWrap" style="display:none">
              <label class="form-label">Outstanding Items on this PO</label>
              <div id="drAvailList" class="mb-3"></div>

              <label class="form-label">Lines Delivered</label>
              <div id="drLineRows"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Post Delivery Report</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View DR modal -->
<div class="modal fade" id="drViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="drViewTitle">Delivery Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="drViewBody"></div>
      <div class="modal-footer"><a class="btn btn-outline-secondary btn-sm" id="drPrintLink" data-report-modal><i class="fas fa-print me-1"></i>Print / PDF</a></div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<script>
let drCache = [], currentPoItems = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const qty = n => Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2});
const inputQty = n => Number(n || 0).toFixed(2).replace(/\.?0+$/, '');

async function loadDrs() {
    const res  = await fetch('api/delivery_reports.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    drCache = data.delivery_reports;
    document.getElementById('drBody').innerHTML = drCache.length ? drCache.map(d => `
        <tr style="cursor:pointer" onclick="viewDr('${d.dr_number}')">
            <td><span class="doc-number doc-dr">${escapeHtml(d.dr_number)}</span></td>
            <td>${escapeHtml(d.branch_name)}</td>
            <td>${escapeHtml(d.po_number)}</td>
            <td>${AppDateTime.format(d.date_delivered)}</td>
            <td>${escapeHtml(d.received_by || '—')}</td>
            <td>${d.date_accepted ? '<i class="fas fa-check text-success"></i> ' + AppDateTime.format(d.date_accepted) : '—'}</td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No delivery reports yet.</td></tr>';
}

async function openDrModal() {
    document.getElementById('drForm').reset();
    document.getElementById('drDate').value = AppDateTime.nowInput();
    document.getElementById('drPoItemsWrap').style.display = 'none';
    const res  = await fetch('api/po_outgoing.php?open_for_delivery=1');
    const data = await res.json();
    document.getElementById('drPo').innerHTML = '<option value="">Select a Purchase Order…</option>' +
        (data.success ? data.pos.map(p => `<option value="${p.po_number}">${p.po_number} — ${escapeHtml(p.client_name)}</option>`).join('') : '');
    new bootstrap.Modal(document.getElementById('drModal')).show();
}

async function onDrPoPicked() {
    const poNumber = document.getElementById('drPo').value;
    document.getElementById('drLineRows').innerHTML = '';
    if (!poNumber) { document.getElementById('drPoItemsWrap').style.display = 'none'; return; }

    const res  = await fetch('api/po_outgoing.php?id=' + encodeURIComponent(poNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    currentPoItems = data.po.items.filter(i => i.item_id).map(i => ({
        ...i,
        item_id: String(i.item_id),
        quantity: Number(i.quantity || 0),
        delivered_qty: Number(i.delivered_qty || 0),
        remaining: Math.max(0, Number(i.quantity || 0) - Number(i.delivered_qty || 0)),
    }));
    renderDrAvailableItems();
    document.getElementById('drPoItemsWrap').style.display = '';
}

function getDrItem(itemId) {
    return currentPoItems.find(i => String(i.item_id) === String(itemId));
}

function queuedDrQty(itemId, excludeRow = null) {
    return [...document.querySelectorAll('#drLineRows .dr-line-row')]
        .filter(row => row !== excludeRow && String(row.dataset.itemId) === String(itemId))
        .reduce((sum, row) => sum + Number(row.querySelector('.dr-qty').value || 0), 0);
}

function remainingDrQty(itemId, excludeRow = null) {
    const item = getDrItem(itemId);
    if (!item) return 0;
    return Math.max(0, item.remaining - queuedDrQty(itemId, excludeRow));
}

function renderDrAvailableItems() {
    const rows = currentPoItems.map(i => {
        const queued = queuedDrQty(i.item_id);
        const left = Math.max(0, i.remaining - queued);
        const disabled = left <= 0 ? 'disabled' : '';
        const label = left <= 0 ? 'All queued' : 'Add';
        return `
            <div class="dr-avail-row">
                <span>${escapeHtml(i.item_name)} <span class="text-muted">(ordered ${qty(i.quantity)}, delivered ${qty(i.delivered_qty)})</span></span>
                <span>
                    ${queued > 0 ? `<span class="text-muted me-2">${qty(queued)} queued</span>` : ''}
                    <strong class="me-2">${qty(left)} left</strong>
                    <button type="button" class="btn btn-sm btn-light" onclick="addDrLine('${escapeHtml(i.item_id)}')" ${disabled}><i class="fas fa-plus"></i> ${label}</button>
                </span>
            </div>`;
    }).join('');
    document.getElementById('drAvailList').innerHTML = rows || '<div class="text-muted small">This PO has no catalog-linked items to deliver.</div>';
}

function onDrQtyChanged(input) {
    const row = input.closest('.dr-line-row');
    if (!row) return;
    const max = remainingDrQty(row.dataset.itemId, row);
    const value = Number(input.value || 0);
    if (value > max) input.value = inputQty(max);
    if (Number(input.value || 0) < 0) input.value = '';
    renderDrAvailableItems();
}

function removeDrLine(button) {
    button.closest('.dr-line-row')?.remove();
    renderDrAvailableItems();
}

function addDrLine(itemId) {
    const item = getDrItem(itemId);
    if (!item) return;
    const left = remainingDrQty(itemId);
    if (left <= 0) {
        alert('No remaining quantity left to deliver for this item.');
        renderDrAvailableItems();
        return;
    }
    const wrap = document.createElement('div');
    wrap.className = 'dr-line-row';
    wrap.dataset.itemId = item.item_id;
    wrap.innerHTML = `
        <span>${escapeHtml(item.item_name)}</span>
        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm dr-qty" placeholder="Qty" value="${inputQty(left)}" max="${inputQty(left)}" oninput="onDrQtyChanged(this)">
        <button type="button" class="btn btn-sm btn-light" onclick="removeDrLine(this)"><i class="fas fa-trash"></i></button>`;
    document.getElementById('drLineRows').appendChild(wrap);
    renderDrAvailableItems();
}

document.getElementById('drForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const overQueued = currentPoItems.find(i => queuedDrQty(i.item_id) > i.remaining + 0.0001);
    if (overQueued) {
        return alert(`Cannot deliver more than the remaining ${qty(overQueued.remaining)} ordered for ${overQueued.item_name}.`);
    }
    const items = [...document.querySelectorAll('#drLineRows .dr-line-row')].map(row => ({
        item_id: row.dataset.itemId,
        quantity: row.querySelector('.dr-qty').value,
    })).filter(i => i.quantity);
    if (!items.length) return alert('Add at least one line to deliver.');

    const payload = {
        po_number: document.getElementById('drPo').value,
        date_delivered: document.getElementById('drDate').value,
        forwarder: document.getElementById('drForwarder').value.trim(),
        received_by: document.getElementById('drReceivedBy').value.trim(),
        date_accepted: document.getElementById('drAccepted').value,
        items,
    };
    const res  = await fetch('api/delivery_reports.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('drModal')).hide();
    loadDrs();
});

async function viewDr(drNumber) {
    const res  = await fetch('api/delivery_reports.php?id=' + encodeURIComponent(drNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const dr = data.dr;
    document.getElementById('drPrintLink').href = 'print_document.php?' + new URLSearchParams({ module: 'delivery_reports', id: dr.dr_number });
    document.getElementById('drViewTitle').textContent = dr.dr_number;
    document.getElementById('drViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(dr.branch_name)}</div>
            <div class="col-6"><strong>PO Number:</strong> ${escapeHtml(dr.po_number)}</div>
            <div class="col-6"><strong>Date &amp; Time Delivered:</strong> ${AppDateTime.format(dr.date_delivered)}</div>
            <div class="col-6"><strong>Forwarder:</strong> ${escapeHtml(dr.forwarder || '—')}</div>
            <div class="col-6"><strong>Received By:</strong> ${escapeHtml(dr.received_by || '—')}</div>
            <div class="col-6"><strong>Date &amp; Time Accepted:</strong> ${AppDateTime.format(dr.date_accepted)}</div>
        </div>
        <table class="app-table table">
            <thead><tr><th>Item</th><th>Qty</th></tr></thead>
            <tbody>${dr.items.map(i => `<tr><td>${escapeHtml(i.item_name)}</td><td>${i.quantity}</td></tr>`).join('')}</tbody>
        </table>`;
    new bootstrap.Modal(document.getElementById('drViewModal')).show();
}

loadDrs();

// Deep link from Order Tracking (order_tracking.php?view=... lands here as ?view=...).
const _viewDr = new URLSearchParams(location.search).get('view');
if (_viewDr) viewDr(_viewDr);
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
