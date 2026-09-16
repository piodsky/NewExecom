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
            <div class="col-6"><label class="form-label">Date Received</label><input type="date" class="form-control" id="rrDate" value="<?= date('Y-m-d') ?>"></div>
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
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
let rrCache = [], currentPoItems = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2});

async function loadRrs() {
    const res  = await fetch('api/receiving_reports.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    rrCache = data.receiving_reports;
    document.getElementById('rrBody').innerHTML = rrCache.length ? rrCache.map(r => `
        <tr style="cursor:pointer" onclick="viewRr('${r.rr_number}')">
            <td><strong>${escapeHtml(r.rr_number)}</strong></td>
            <td>${escapeHtml(r.branch_name)}</td>
            <td>${escapeHtml(r.po_number)}</td>
            <td>${r.date_received || '—'}</td>
            <td>${escapeHtml(r.received_by_name)}</td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted small">No receiving reports yet.</td></tr>';
}

async function openRrModal() {
    document.getElementById('rrForm').reset();
    document.getElementById('rrDate').value = new Date().toISOString().slice(0, 10);
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
    currentPoItems = data.po.items.filter(i => i.item_id).map(i => ({ ...i, remaining: i.quantity - i.received_qty }));

    document.getElementById('rrAvailList').innerHTML = currentPoItems.map(i => `
        <div class="rr-avail-row">
            <span>${escapeHtml(i.item_name)} <span class="text-muted">(ordered ${i.quantity}, received ${i.received_qty})</span></span>
            <span>
                <strong class="me-2">${i.remaining} left</strong>
                ${i.remaining > 0 ? `<button type="button" class="btn btn-sm btn-light" onclick="addRrLine(${i.item_id})"><i class="fas fa-plus"></i> Add</button>` : ''}
            </span>
        </div>`).join('') || '<div class="text-muted small">This PO has no catalog-linked items to receive.</div>';

    document.getElementById('rrPoItemsWrap').style.display = '';
}

function addRrLine(itemId) {
    const item = currentPoItems.find(i => i.item_id === itemId);
    if (!item) return;
    const wrap = document.createElement('div');
    wrap.className = 'rr-line-row';
    wrap.dataset.itemId = itemId;
    wrap.dataset.serialized = item.is_serialized ? '1' : '0';
    wrap.innerHTML = `
        <span>${escapeHtml(item.item_name)}</span>
        <input type="number" step="0.01" class="form-control form-control-sm rr-qty" placeholder="Qty" value="${item.is_serialized ? 1 : ''}" ${item.is_serialized ? 'readonly' : ''} max="${item.remaining}">
        <input type="number" step="0.01" class="form-control form-control-sm rr-cost" placeholder="Unit Cost" value="${item.unit_cost ?? ''}">
        <input type="text" class="form-control form-control-sm rr-serial" placeholder="${item.is_serialized ? 'Serial number (required)' : 'Serial (n/a)'}" ${item.is_serialized ? '' : 'disabled'}>
        <button type="button" class="btn btn-sm btn-light" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>`;
    document.getElementById('rrLineRows').appendChild(wrap);
}

document.getElementById('rrForm').addEventListener('submit', async (e) => {
    e.preventDefault();
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
    document.getElementById('rrViewTitle').textContent = rr.rr_number;
    document.getElementById('rrViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(rr.branch_name)}</div>
            <div class="col-6"><strong>PO Number:</strong> ${escapeHtml(rr.po_number)}</div>
            <div class="col-6"><strong>Date Received:</strong> ${rr.date_received || '—'}</div>
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
