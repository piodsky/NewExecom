<?php
require_once 'config.php';
requireModule('warehouse');
$currentUser = getCurrentUser();
$activePage  = 'warehouse';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
$isSuperAdmin = $currentUser['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Warehouse Map | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.active   { background: #dcfce7; color: #166534; }
.badge-status.inactive { background: #fee2e2; color: #991b1b; }

.wh-map {
    display: grid;
    gap: .75rem;
    padding: .25rem;
}
.wh-bin {
    border: 2px dashed var(--border);
    border-radius: 10px;
    background: #f8fafc;
    padding: .75rem;
    min-height: 90px;
    cursor: pointer;
    display: flex; flex-direction: column; justify-content: space-between;
    transition: transform .15s, box-shadow .15s;
}
.wh-bin:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.wh-bin.wh-occupied { border-style: solid; border-color: #93c5fd; background: #eff6ff; }
.wh-bin.wh-full { border-color: #fca5a5; background: #fef2f2; }
.wh-bin.wh-inactive { opacity: .45; cursor: not-allowed; }
.wh-bin-code { font-weight: 800; font-size: .95rem; color: var(--text); }
.wh-bin-label { font-size: .72rem; color: var(--muted); }
.wh-bin-count { font-size: .72rem; font-weight: 700; margin-top: .4rem; }
.wh-bin-count.wh-empty-text { color: var(--muted); font-weight: 500; }
.wh-bin-count.wh-has { color: #1e40af; }
.wh-bin-count.wh-full-text { color: #991b1b; }

.wh-legend { display: flex; gap: 1.25rem; flex-wrap: wrap; font-size: .78rem; color: var(--muted); }
.wh-legend span { display: inline-flex; align-items: center; gap: .4rem; }
.wh-legend i { width: 14px; height: 14px; border-radius: 4px; display: inline-block; }

.wh-item-row {
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    padding: .55rem 0; border-bottom: 1px solid #f1f5f9;
}
.wh-item-row:last-child { border-bottom: none; }
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
                    <h2 class="page-title">Warehouse Map</h2>
                    <div class="text-muted small">Where every in-stock item physically sits — put it away, find it, pick it back out.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <?php if ($isSuperAdmin): ?>
        <div class="panel mb-3">
            <div class="panel-body d-flex align-items-center gap-2 flex-wrap">
                <label class="form-label mb-0 fw-bold">Branch:</label>
                <select class="form-select" id="whBranchSelect" style="max-width:320px"></select>
            </div>
        </div>
        <?php endif; ?>

        <div id="whNotice" class="alert d-none" role="status"></div>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><button class="nav-link active" id="tabMapBtn" onclick="showWhTab('map')">Warehouse Map</button></li>
            <?php if ($isAdminTier): ?>
            <li class="nav-item"><button class="nav-link" id="tabBinsBtn" onclick="showWhTab('bins')">Manage Bins</button></li>
            <?php endif; ?>
        </ul>

        <div id="tabMap">
            <?php if ($isAdminTier): ?>
            <div class="panel mb-3">
                <div class="panel-header"><div class="panel-title">Unplaced — in stock, not yet put away</div></div>
                <div class="panel-body" id="whUnplaced">
                    <div class="text-muted small">Loading…</div>
                </div>
            </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="panel-title">Floor Plan</div>
                    <div class="wh-legend">
                        <span><i style="background:#f8fafc;border:2px dashed var(--border)"></i> Empty</span>
                        <span><i style="background:#eff6ff;border:2px solid #93c5fd"></i> Occupied</span>
                        <span><i style="background:#fef2f2;border:2px solid #fca5a5"></i> At capacity</span>
                    </div>
                </div>
                <div class="panel-body" id="whMapBody">
                    <div class="text-muted small">Loading…</div>
                </div>
            </div>
        </div>

        <?php if ($isAdminTier): ?>
        <div id="tabBins" style="display:none">
            <div class="panel">
                <div class="panel-header d-flex justify-content-between align-items-center">
                    <div class="panel-title">Bins</div>
                    <button class="btn btn-primary-app btn-sm" onclick="openBinModal()"><i class="fas fa-plus me-1"></i>New Bin</button>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                    <table class="app-table table">
                        <thead><tr><th>Code</th><th>Label</th><th>Row</th><th>Column</th><th>Capacity</th><th>Occupancy</th><th>Status</th><th></th></tr></thead>
                        <tbody id="binsBody"><tr><td colspan="8" class="text-muted small">Loading…</td></tr></tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Bin details / place item modal -->
<div class="modal fade" id="binDetailModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="binDetailTitle">Bin</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="binDetailBody"></div>
    </div>
  </div>
</div>

<!-- Place item modal (from Unplaced list) -->
<div class="modal fade" id="placeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="placeModalTitle">Place Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="placeItemName" class="fw-bold mb-3"></div>
        <label class="form-label">Bin</label>
        <select class="form-select" id="placeBinSelect"></select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary-app" id="placeConfirmBtn">Place</button>
      </div>
    </div>
  </div>
</div>

<?php if ($isAdminTier): ?>
<!-- Create/Edit Bin modal -->
<div class="modal fade" id="binModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="binModalTitle">New Bin</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="binForm">
        <div class="modal-body">
          <input type="hidden" id="binId">
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Code</label><input type="text" class="form-control" id="binCode" maxlength="20" placeholder="e.g. A-01" required></div>
            <div class="col-6"><label class="form-label">Label <span class="text-muted small">(optional)</span></label><input type="text" class="form-control" id="binLabel" maxlength="100" placeholder="e.g. Aisle A, Rack 1"></div>
            <div class="col-4"><label class="form-label">Row</label><input type="number" class="form-control" id="binRow" min="1" value="1" required></div>
            <div class="col-4"><label class="form-label">Column</label><input type="number" class="form-control" id="binCol" min="1" value="1" required></div>
            <div class="col-4"><label class="form-label">Capacity <span class="text-muted small">(optional)</span></label><input type="number" class="form-control" id="binCapacity" min="0" placeholder="Unlimited"></div>
          </div>
          <div class="form-text mt-2">Row/Column place this bin on the floor plan grid — they don't need to be exact, just relative to your other bins.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<script>
const isAdminTier  = <?= $isAdminTier ? 'true' : 'false' ?>;
const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

let whData = null, binsCache = [], placeCtx = null;

function showWhTab(tab) {
    document.getElementById('tabMap').style.display = tab === 'map' ? '' : 'none';
    document.getElementById('tabMapBtn').classList.toggle('active', tab === 'map');
    const binsTab = document.getElementById('tabBins');
    if (binsTab) {
        binsTab.style.display = tab === 'bins' ? '' : 'none';
        document.getElementById('tabBinsBtn').classList.toggle('active', tab === 'bins');
    }
}

function showWhNotice(message, type = 'danger') {
    const n = document.getElementById('whNotice');
    n.className = 'alert alert-' + type;
    n.textContent = message;
}
function clearWhNotice() {
    document.getElementById('whNotice').className = 'alert d-none';
}

async function whRequest(url, options) {
    const res = await fetch(url, options);
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Request failed.');
    return data;
}

function currentBranchId() {
    if (!isSuperAdmin) return '';
    const sel = document.getElementById('whBranchSelect');
    return sel ? sel.value : '';
}

async function initBranchPicker() {
    if (!isSuperAdmin) return;
    const res = await fetch('api/branches.php');
    const data = await res.json();
    const sel = document.getElementById('whBranchSelect');
    const params = new URLSearchParams(location.search);
    const preselect = params.get('branch_id') || '';
    sel.innerHTML = (data.success ? data.branches.filter(b => b.is_active == 1) : [])
        .map(b => `<option value="${b.id}" ${String(b.id) === preselect ? 'selected' : ''}>${escapeHtml(b.name)}</option>`).join('');
    sel.addEventListener('change', loadWarehouse);
}

async function loadWarehouse() {
    clearWhNotice();
    try {
        const branchId = currentBranchId();
        const url = 'api/warehouse.php' + (branchId ? ('?branch_id=' + encodeURIComponent(branchId)) : '');
        const data = await whRequest(url);
        whData = data;
        binsCache = data.locations || [];
        renderMap();
        renderUnplaced();
        if (window.renderBinsTable) renderBinsTable();
    } catch (err) {
        showWhNotice(err.message);
    }
}

function renderMap() {
    const box = document.getElementById('whMapBody');
    if (!whData || whData.need_branch) {
        box.innerHTML = '<div class="text-muted small">Select a branch above to see its floor plan.</div>';
        return;
    }
    if (!whData.locations.length) {
        box.innerHTML = isAdminTier
            ? '<div class="text-muted small">No bins defined yet. Add one from the "Manage Bins" tab to start mapping this warehouse.</div>'
            : '<div class="text-muted small">No bins defined yet for this branch.</div>';
        return;
    }
    const maxCol = Math.max(...whData.locations.map(l => l.grid_col));
    const map = document.createElement('div');
    map.className = 'wh-map';
    map.style.gridTemplateColumns = `repeat(${maxCol}, minmax(110px, 1fr))`;
    map.innerHTML = whData.locations.map(loc => {
        const unitCount = loc.unit_count;
        const stockQty  = loc.stock_qty;
        const isEmpty = unitCount === 0 && stockQty === 0;
        const atCapacity = loc.capacity !== null && unitCount >= loc.capacity;
        let cls = 'wh-bin';
        if (!loc.is_active) cls += ' wh-inactive';
        else if (atCapacity) cls += ' wh-full';
        else if (!isEmpty) cls += ' wh-occupied';
        const countClass = isEmpty ? 'wh-empty-text' : (atCapacity ? 'wh-full-text' : 'wh-has');
        const parts = [];
        if (unitCount > 0) parts.push(`${unitCount}${loc.capacity !== null ? ' / ' + loc.capacity : ''} unit${unitCount === 1 ? '' : 's'}`);
        if (stockQty > 0) parts.push(`${stockQty.toLocaleString()} bulk qty`);
        const countText = isEmpty ? 'Empty' : parts.join(' · ');
        return `
        <div class="${cls}" style="grid-row:${loc.grid_row};grid-column:${loc.grid_col}" onclick="${loc.is_active ? `openBinDetail(${loc.id})` : ''}">
            <div>
                <div class="wh-bin-code">${escapeHtml(loc.code)}</div>
                ${loc.label ? `<div class="wh-bin-label">${escapeHtml(loc.label)}</div>` : ''}
            </div>
            <div class="wh-bin-count ${countClass}">${countText}</div>
        </div>`;
    }).join('');
    box.innerHTML = '';
    box.appendChild(map);
}

function renderUnplaced() {
    const box = document.getElementById('whUnplaced');
    if (!box || !whData || whData.need_branch) return;
    const units = whData.unplaced_units || [];
    const stock = whData.unplaced_stock || [];
    if (!units.length && !stock.length) {
        box.innerHTML = '<div class="text-muted small">Everything in stock has a bin. Nothing waiting to be put away.</div>';
        return;
    }
    const unitRows = units.map(u => `
        <div class="wh-item-row">
            <div>${escapeHtml(u.item_name)}${u.serial_number ? ' <span class="text-muted small">(' + escapeHtml(u.serial_number) + ')</span>' : ''}</div>
            <button class="btn btn-sm-action btn-outline-primary" onclick='openPlaceModal(${escapeHtml(JSON.stringify({type:'unit', id:u.id, label:u.item_name + (u.serial_number ? ' — ' + u.serial_number : '')}))})'>Place</button>
        </div>`).join('');
    const stockRows = stock.map(s => `
        <div class="wh-item-row">
            <div>${escapeHtml(s.item_name)} <span class="text-muted small">(${Number(s.quantity_on_hand).toLocaleString()} ${escapeHtml(s.unit)})</span></div>
            <button class="btn btn-sm-action btn-outline-primary" onclick='openPlaceModal(${escapeHtml(JSON.stringify({type:'stock', id:s.item_id, label:s.item_name}))})'>Place</button>
        </div>`).join('');
    box.innerHTML = unitRows + stockRows;
}

function openPlaceModal(ctx) {
    placeCtx = ctx;
    document.getElementById('placeItemName').textContent = ctx.label;
    const activeBins = binsCache.filter(b => b.is_active);
    document.getElementById('placeBinSelect').innerHTML = activeBins.length
        ? activeBins.map(b => `<option value="${b.id}">${escapeHtml(b.code)}${b.label ? ' — ' + escapeHtml(b.label) : ''}</option>`).join('')
        : '<option value="">No bins available — add one first</option>';
    new bootstrap.Modal(document.getElementById('placeModal')).show();
}

document.getElementById('placeConfirmBtn').addEventListener('click', async () => {
    if (!placeCtx) return;
    const locationId = document.getElementById('placeBinSelect').value;
    if (!locationId) return;
    try {
        const payload = placeCtx.type === 'unit'
            ? { action: 'place_unit', unit_id: placeCtx.id, location_id: locationId }
            : { action: 'place_stock', item_id: placeCtx.id, branch_id: currentBranchId(), location_id: locationId };
        await whRequest('api/warehouse.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        bootstrap.Modal.getInstance(document.getElementById('placeModal')).hide();
        loadWarehouse();
    } catch (err) {
        showWhNotice(err.message);
    }
});

function openBinDetail(locId) {
    const loc = binsCache.find(b => b.id === locId);
    if (!loc) return;
    document.getElementById('binDetailTitle').textContent = loc.code + (loc.label ? ' — ' + loc.label : '');
    const unitRows = (loc.units || []).map(u => `
        <div class="wh-item-row">
            <div>${escapeHtml(u.item_name)}${u.serial_number ? ' <span class="text-muted small">(' + escapeHtml(u.serial_number) + ')</span>' : ''}</div>
            ${isAdminTier ? `<button class="btn btn-sm-action" onclick="unplaceUnit(${u.id})" title="Remove from bin"><i class="fas fa-arrow-turn-down"></i></button>` : ''}
        </div>`).join('');
    const stockRows = (loc.stock || []).map(s => `
        <div class="wh-item-row">
            <div>${escapeHtml(s.item_name)} <span class="text-muted small">(${Number(s.quantity_on_hand).toLocaleString()} ${escapeHtml(s.unit)})</span></div>
            ${isAdminTier ? `<button class="btn btn-sm-action" onclick="unplaceStock(${s.item_id})" title="Remove from bin"><i class="fas fa-arrow-turn-down"></i></button>` : ''}
        </div>`).join('');
    const body = document.getElementById('binDetailBody');
    if (!unitRows && !stockRows) {
        body.innerHTML = '<div class="text-muted small">Nothing stored here yet.</div>';
    } else {
        body.innerHTML = unitRows + stockRows;
    }
    new bootstrap.Modal(document.getElementById('binDetailModal')).show();
}

async function unplaceUnit(unitId) {
    try {
        await whRequest('api/warehouse.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'place_unit', unit_id: unitId, location_id: '' }) });
        bootstrap.Modal.getInstance(document.getElementById('binDetailModal')).hide();
        loadWarehouse();
    } catch (err) {
        showWhNotice(err.message);
    }
}
async function unplaceStock(itemId) {
    try {
        await whRequest('api/warehouse.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'place_stock', item_id: itemId, branch_id: currentBranchId(), location_id: '' }) });
        bootstrap.Modal.getInstance(document.getElementById('binDetailModal')).hide();
        loadWarehouse();
    } catch (err) {
        showWhNotice(err.message);
    }
}

<?php if ($isAdminTier): ?>
function renderBinsTable() {
    const body = document.getElementById('binsBody');
    if (!body) return;
    if (!whData || whData.need_branch) {
        body.innerHTML = '<tr><td colspan="8" class="text-muted small">Select a branch above first.</td></tr>';
        return;
    }
    body.innerHTML = binsCache.length ? binsCache.map(b => `
        <tr>
            <td><strong>${escapeHtml(b.code)}</strong></td>
            <td>${escapeHtml(b.label || '—')}</td>
            <td>${b.grid_row}</td>
            <td>${b.grid_col}</td>
            <td>${b.capacity !== null ? b.capacity : 'Unlimited'}</td>
            <td>${b.unit_count}${b.capacity !== null ? ' / ' + b.capacity : ''} unit${b.unit_count === 1 ? '' : 's'}${b.stock_qty > 0 ? ', ' + b.stock_qty.toLocaleString() + ' bulk qty' : ''}</td>
            <td><span class="badge-status ${b.is_active ? 'active' : 'inactive'}">${b.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-end">
                <button class="btn-sm-action" onclick="editBin(${b.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn-sm-action" onclick="toggleBin(${b.id})" title="Toggle"><i class="fas fa-power-off"></i></button>
            </td>
        </tr>`).join('') : '<tr><td colspan="8" class="text-muted small">No bins yet.</td></tr>';
}

function openBinModal() {
    document.getElementById('binForm').reset();
    document.getElementById('binId').value = '';
    document.getElementById('binModalTitle').textContent = 'New Bin';
    new bootstrap.Modal(document.getElementById('binModal')).show();
}
function editBin(id) {
    const b = binsCache.find(x => x.id === id);
    if (!b) return;
    document.getElementById('binId').value = b.id;
    document.getElementById('binCode').value = b.code;
    document.getElementById('binLabel').value = b.label || '';
    document.getElementById('binRow').value = b.grid_row;
    document.getElementById('binCol').value = b.grid_col;
    document.getElementById('binCapacity').value = b.capacity !== null ? b.capacity : '';
    document.getElementById('binModalTitle').textContent = 'Edit Bin';
    new bootstrap.Modal(document.getElementById('binModal')).show();
}
document.getElementById('binForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('binId').value;
    const payload = {
        action: id ? 'update_location' : 'create_location', id: id || undefined,
        branch_id: currentBranchId(),
        code: document.getElementById('binCode').value.trim(),
        label: document.getElementById('binLabel').value.trim(),
        grid_row: document.getElementById('binRow').value,
        grid_col: document.getElementById('binCol').value,
        capacity: document.getElementById('binCapacity').value,
    };
    try {
        await whRequest('api/warehouse.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        bootstrap.Modal.getInstance(document.getElementById('binModal')).hide();
        loadWarehouse();
    } catch (err) {
        showWhNotice(err.message);
    }
});
async function toggleBin(id) {
    if (!confirm('Change this bin\'s active status?')) return;
    try {
        await whRequest('api/warehouse.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'toggle_location_active', id }) });
        loadWarehouse();
    } catch (err) {
        showWhNotice(err.message);
    }
}
<?php endif; ?>

(async function init() {
    await initBranchPicker();
    loadWarehouse();
})();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
