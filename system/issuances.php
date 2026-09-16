<?php
require_once 'config.php';
requireModule('issuance');
$currentUser = getCurrentUser();
$activePage  = 'issuances';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
$isSuperAdmin = $currentUser['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Issuance | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.iss-avail-row { display: flex; justify-content: space-between; align-items: center; padding: .4rem .1rem; border-bottom: 1px dashed var(--border); font-size: .85rem; }
.iss-line-row { display: grid; grid-template-columns: 1.6fr .7fr .9fr auto; gap: .5rem; margin-bottom: .4rem; align-items: center; }
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
                <div class="page-title">Issuance</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Issuance</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Sales / Stock Issuance</div>
                <button class="btn btn-primary-app btn-sm" onclick="openIssModal()"><i class="fas fa-plus me-1"></i>New Issuance</button>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Issuance #</th><th>Branch</th><th>Client</th><th>Total</th><th>Issued By</th><th>Date</th><th></th></tr></thead>
                    <tbody id="issBody"><tr><td colspan="7" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- New Issuance modal -->
<div class="modal fade" id="issModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Issuance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="issForm">
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <?php if ($isSuperAdmin): ?>
            <div class="col-6"><label class="form-label">Branch</label><select class="form-select" id="issBranch" onchange="onBranchPicked()" required></select></div>
            <?php else: ?>
            <div class="col-6"><label class="form-label">Branch</label><input type="text" class="form-control" value="<?= e($currentUser['branch_name'] ?? '') ?>" disabled></div>
            <?php endif; ?>
            <div class="col-6"><label class="form-label">Client</label><select class="form-select" id="issClient"><option value="">Walk-in / Retail</option></select></div>
            <div class="col-12"><label class="form-label">Fulfilling Outgoing PO (optional)</label>
                <select class="form-select" id="issPo" onchange="onIssPoPicked()"><option value="">None — walk-in / retail sale</option></select></div>
          </div>

          <div id="issItemsWrap" style="display:<?= $isSuperAdmin ? 'none' : '' ?>">
              <label class="form-label">Available Stock at Branch</label>
              <div id="issAvailList" class="mb-3"></div>

              <label class="form-label">Items to Issue</label>
              <div id="issLineRows"></div>
              <div class="text-end fw-bold mt-2">Total: <span id="issTotal">₱0.00</span></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Post Issuance</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Issuance modal -->
<div class="modal fade" id="issViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="issViewTitle">Issuance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="issViewBody"></div>
      <div class="modal-footer" id="issViewActions"></div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isAdminTier  = <?= $isAdminTier ? 'true' : 'false' ?>;
const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const myBranchId   = <?= $currentUser['branch_id'] !== null ? (int)$currentUser['branch_id'] : 'null' ?>;
let issCache = [], currentAvail = { units: [], stock: [] }, poItemFilter = null;

function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2});

async function loadIssuances() {
    const res  = await fetch('api/issuances.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    issCache = data.issuances;
    document.getElementById('issBody').innerHTML = issCache.length ? issCache.map(i => `
        <tr style="cursor:pointer" onclick="viewIssuance('${i.issuance_number}')">
            <td><strong>${escapeHtml(i.issuance_number)}</strong></td>
            <td>${escapeHtml(i.branch_name)}</td>
            <td>${escapeHtml(i.client_name || 'Walk-in')}</td>
            <td>${peso(i.total_amount)}</td>
            <td>${escapeHtml(i.issued_by_name)}</td>
            <td>${i.date_created ? i.date_created.slice(0,10) : '—'}</td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No issuances yet.</td></tr>';
}

async function loadClientOptions() {
    const res  = await fetch('api/clients.php');
    const data = await res.json();
    const clients = (data.success ? data.clients : []).filter(c => c.is_active == 1);
    document.getElementById('issClient').innerHTML = '<option value="">Walk-in / Retail</option>' +
        clients.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}

async function loadBranchOptions() {
    const res  = await fetch('api/stock_transfers.php?branches=1');
    const data = await res.json();
    const branches = data.success ? data.branches : [];
    document.getElementById('issBranch').innerHTML = '<option value="">Select branch…</option>' +
        branches.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
}

async function openIssModal() {
    document.getElementById('issForm').reset();
    document.getElementById('issLineRows').innerHTML = '';
    document.getElementById('issAvailList').innerHTML = '';
    document.getElementById('issTotal').textContent = peso(0);
    document.getElementById('issClient').disabled = false;
    currentAvail = { units: [], stock: [] };
    poItemFilter = null;
    await loadClientOptions();
    await loadPoOptions();

    if (isSuperAdmin) {
        document.getElementById('issItemsWrap').style.display = 'none';
        await loadBranchOptions();
    } else {
        await fetchAvailable(myBranchId);
    }
    new bootstrap.Modal(document.getElementById('issModal')).show();
}

async function loadPoOptions() {
    const res  = await fetch('api/po_outgoing.php?open_for_issuance=1');
    const data = await res.json();
    const pos = data.success ? data.pos : [];
    document.getElementById('issPo').innerHTML = '<option value="">None — walk-in / retail sale</option>' +
        pos.map(p => `<option value="${p.po_number}">${escapeHtml(p.po_number)} — ${escapeHtml(p.client_name)}</option>`).join('');
}

async function onIssPoPicked() {
    const poNumber = document.getElementById('issPo').value;
    document.getElementById('issLineRows').innerHTML = '';
    if (!poNumber) {
        poItemFilter = null;
        document.getElementById('issClient').disabled = false;
        return renderAvailable();
    }
    const res  = await fetch('api/po_outgoing.php?id=' + encodeURIComponent(poNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    poItemFilter = data.po.items.filter(i => i.item_id).map(i => i.item_id);

    // Fulfilling a specific client order — that client is fixed, not a choice.
    await loadClientOptions();
    const clientSelect = document.getElementById('issClient');
    for (const opt of clientSelect.options) {
        if (opt.textContent === data.po.client_name) { clientSelect.value = opt.value; break; }
    }
    clientSelect.disabled = true;
    renderAvailable();
}

async function onBranchPicked() {
    const branchId = document.getElementById('issBranch').value;
    if (!branchId) { document.getElementById('issItemsWrap').style.display = 'none'; return; }
    await fetchAvailable(branchId);
    document.getElementById('issItemsWrap').style.display = '';
}

async function fetchAvailable(branchId) {
    const res  = await fetch('api/issuances.php?catalog_for=' + branchId);
    const data = await res.json();
    if (!data.success) return alert(data.message);
    currentAvail = data;
    renderAvailable();
}

function renderAvailable() {
    document.getElementById('issLineRows').innerHTML = '';
    const inFilter = itemId => poItemFilter === null || poItemFilter.includes(itemId);
    const unitRows = currentAvail.units.filter(u => inFilter(u.item_id)).map(u => `
        <div class="iss-avail-row">
            <span>${escapeHtml(u.item_name)} <span class="text-muted">(SN: ${escapeHtml(u.serial_number)})</span></span>
            <button type="button" class="btn btn-sm btn-light" onclick='addIssLine("unit", ${JSON.stringify(u).replace(/'/g,"&#39;")})'><i class="fas fa-plus"></i> Add</button>
        </div>`).join('');
    const stockRows = currentAvail.stock.filter(s => inFilter(s.item_id)).map(s => `
        <div class="iss-avail-row">
            <span>${escapeHtml(s.item_name)} <span class="text-muted">(${s.quantity_on_hand} ${escapeHtml(s.unit)} on hand)</span></span>
            <button type="button" class="btn btn-sm btn-light" onclick='addIssLine("stock", ${JSON.stringify(s).replace(/'/g,"&#39;")})'><i class="fas fa-plus"></i> Add</button>
        </div>`).join('');
    document.getElementById('issAvailList').innerHTML = (unitRows + stockRows) ||
        `<div class="text-muted small">${poItemFilter ? 'None of this PO\'s items are currently in stock at this branch.' : 'No sellable stock at this branch.'}</div>`;
}

function addIssLine(kind, item) {
    const wrap = document.createElement('div');
    wrap.className = 'iss-line-row';
    if (kind === 'unit') {
        wrap.dataset.inventoryUnitId = item.id;
        wrap.innerHTML = `
            <span>${escapeHtml(item.item_name)} (SN: ${escapeHtml(item.serial_number)})</span>
            <span class="text-muted small">1 unit</span>
            <input type="number" step="0.01" class="form-control form-control-sm iss-price" placeholder="Unit Price" value="${item.default_price ?? ''}" oninput="updateIssTotal()">
            <button type="button" class="btn btn-sm btn-light" onclick="this.parentElement.remove(); updateIssTotal()"><i class="fas fa-trash"></i></button>`;
    } else {
        wrap.dataset.itemId = item.item_id;
        wrap.innerHTML = `
            <span>${escapeHtml(item.item_name)}</span>
            <input type="number" step="0.01" class="form-control form-control-sm iss-qty" placeholder="Qty" max="${item.quantity_on_hand}" oninput="updateIssTotal()">
            <input type="number" step="0.01" class="form-control form-control-sm iss-price" placeholder="Unit Price" value="${item.default_price ?? ''}" oninput="updateIssTotal()">
            <button type="button" class="btn btn-sm btn-light" onclick="this.parentElement.remove(); updateIssTotal()"><i class="fas fa-trash"></i></button>`;
    }
    document.getElementById('issLineRows').appendChild(wrap);
}

function updateIssTotal() {
    let total = 0;
    document.querySelectorAll('#issLineRows .iss-line-row').forEach(row => {
        const price = parseFloat(row.querySelector('.iss-price')?.value || 0);
        const qty   = row.dataset.inventoryUnitId ? 1 : parseFloat(row.querySelector('.iss-qty')?.value || 0);
        total += price * qty;
    });
    document.getElementById('issTotal').textContent = peso(total);
}

document.getElementById('issForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const items = [...document.querySelectorAll('#issLineRows .iss-line-row')].map(row => {
        const unitPrice = row.querySelector('.iss-price').value;
        if (row.dataset.inventoryUnitId) return { inventory_unit_id: row.dataset.inventoryUnitId, unit_price: unitPrice };
        return { item_id: row.dataset.itemId, quantity: row.querySelector('.iss-qty').value, unit_price: unitPrice };
    }).filter(l => (l.inventory_unit_id || l.quantity) && l.unit_price !== '');
    if (!items.length) return alert('Add at least one item with a unit price.');

    const payload = {
        client_id: document.getElementById('issClient').value || null,
        po_number: document.getElementById('issPo').value || null,
        items,
    };
    if (isSuperAdmin) payload.branch_id = document.getElementById('issBranch').value;

    const res  = await fetch('api/issuances.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('issModal')).hide();
    loadIssuances();
});

async function viewIssuance(issuanceNumber) {
    const res  = await fetch('api/issuances.php?id=' + encodeURIComponent(issuanceNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const iss = data.issuance;
    document.getElementById('issViewTitle').textContent = iss.issuance_number;
    document.getElementById('issViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(iss.branch_name)}</div>
            <div class="col-6"><strong>Client:</strong> ${escapeHtml(iss.client_name || 'Walk-in')}</div>
            <div class="col-6"><strong>Issued By:</strong> ${escapeHtml(iss.issued_by_name)}</div>
            <div class="col-6"><strong>Date:</strong> ${iss.date_created ? iss.date_created.slice(0,10) : '—'}</div>
            ${iss.po_number ? `<div class="col-12"><strong>Fulfilling PO:</strong> ${escapeHtml(iss.po_number)}</div>` : ''}
        </div>
        <table class="app-table table">
            <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody>${iss.items.map(i => `<tr><td>${escapeHtml(i.item_name)}${i.serial_number ? ' (SN: '+escapeHtml(i.serial_number)+')' : ''}</td><td>${i.quantity}</td><td>${peso(i.unit_price)}</td><td>${peso(i.total_price)}</td></tr>`).join('')}</tbody>
            <tfoot><tr><th colspan="3" class="text-end">Total</th><th>${peso(iss.total_amount)}</th></tr></tfoot>
        </table>`;
    document.getElementById('issViewActions').innerHTML = isAdminTier
        ? `<button class="btn btn-outline-danger btn-sm" onclick="voidIssuance('${iss.issuance_number}')">Void</button>`
        : '';
    new bootstrap.Modal(document.getElementById('issViewModal')).show();
}

async function voidIssuance(issuanceNumber) {
    if (!confirm('Void this issuance and restore the stock?')) return;
    const res  = await fetch('api/issuances.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'void', issuance_number: issuanceNumber }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('issViewModal')).hide();
    loadIssuances();
}

loadIssuances();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
