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
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%232563eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' font-weight='700' fill='white' text-anchor='middle'%3EE%3C/text%3E%3C/svg%3E">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.dr-avail-row { display: flex; justify-content: space-between; align-items: center; padding: .4rem .1rem; border-bottom: 1px dashed var(--border); font-size: .85rem; }
.dr-line-row { display: grid; grid-template-columns: 1.6fr .8fr auto; gap: .5rem; margin-bottom: .4rem; align-items: center; }
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
                <div class="page-title">Delivery Reports</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Delivery Reports</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
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
            <div class="col-6"><label class="form-label">Date Delivered</label><input type="date" class="form-control" id="drDate" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-6"><label class="form-label">Forwarder</label><input type="text" class="form-control" id="drForwarder"></div>
            <div class="col-6"><label class="form-label">Received By (client signatory)</label><input type="text" class="form-control" id="drReceivedBy"></div>
            <div class="col-6"><label class="form-label">Date Accepted</label><input type="date" class="form-control" id="drAccepted"></div>
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
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
let drCache = [], currentPoItems = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function loadDrs() {
    const res  = await fetch('api/delivery_reports.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    drCache = data.delivery_reports;
    document.getElementById('drBody').innerHTML = drCache.length ? drCache.map(d => `
        <tr style="cursor:pointer" onclick="viewDr('${d.dr_number}')">
            <td><strong>${escapeHtml(d.dr_number)}</strong></td>
            <td>${escapeHtml(d.branch_name)}</td>
            <td>${escapeHtml(d.po_number)}</td>
            <td>${d.date_delivered || '—'}</td>
            <td>${escapeHtml(d.received_by || '—')}</td>
            <td>${d.date_accepted ? '<i class="fas fa-check text-success"></i> ' + d.date_accepted : '—'}</td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No delivery reports yet.</td></tr>';
}

async function openDrModal() {
    document.getElementById('drForm').reset();
    document.getElementById('drDate').value = new Date().toISOString().slice(0, 10);
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
    currentPoItems = data.po.items.filter(i => i.item_id).map(i => ({ ...i, remaining: i.quantity - i.delivered_qty }));

    document.getElementById('drAvailList').innerHTML = currentPoItems.map(i => `
        <div class="dr-avail-row">
            <span>${escapeHtml(i.item_name)} <span class="text-muted">(ordered ${i.quantity}, delivered ${i.delivered_qty})</span></span>
            <span>
                <strong class="me-2">${i.remaining} left</strong>
                ${i.remaining > 0 ? `<button type="button" class="btn btn-sm btn-light" onclick="addDrLine(${i.item_id})"><i class="fas fa-plus"></i> Add</button>` : ''}
            </span>
        </div>`).join('') || '<div class="text-muted small">This PO has no catalog-linked items to deliver.</div>';

    document.getElementById('drPoItemsWrap').style.display = '';
}

function addDrLine(itemId) {
    const item = currentPoItems.find(i => i.item_id === itemId);
    if (!item) return;
    const wrap = document.createElement('div');
    wrap.className = 'dr-line-row';
    wrap.dataset.itemId = itemId;
    wrap.innerHTML = `
        <span>${escapeHtml(item.item_name)}</span>
        <input type="number" step="0.01" class="form-control form-control-sm dr-qty" placeholder="Qty" max="${item.remaining}">
        <button type="button" class="btn btn-sm btn-light" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>`;
    document.getElementById('drLineRows').appendChild(wrap);
}

document.getElementById('drForm').addEventListener('submit', async (e) => {
    e.preventDefault();
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
    document.getElementById('drViewTitle').textContent = dr.dr_number;
    document.getElementById('drViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(dr.branch_name)}</div>
            <div class="col-6"><strong>PO Number:</strong> ${escapeHtml(dr.po_number)}</div>
            <div class="col-6"><strong>Date Delivered:</strong> ${dr.date_delivered || '—'}</div>
            <div class="col-6"><strong>Forwarder:</strong> ${escapeHtml(dr.forwarder || '—')}</div>
            <div class="col-6"><strong>Received By:</strong> ${escapeHtml(dr.received_by || '—')}</div>
            <div class="col-6"><strong>Date Accepted:</strong> ${dr.date_accepted || '—'}</div>
        </div>
        <table class="app-table table">
            <thead><tr><th>Item</th><th>Qty</th></tr></thead>
            <tbody>${dr.items.map(i => `<tr><td>${escapeHtml(i.item_name)}</td><td>${i.quantity}</td></tr>`).join('')}</tbody>
        </table>`;
    new bootstrap.Modal(document.getElementById('drViewModal')).show();
}

loadDrs();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
