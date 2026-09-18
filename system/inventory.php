<?php
require_once 'config.php';
requireModule('inventory');
$currentUser = getCurrentUser();
$activePage  = 'inventory';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
if ($isAdminTier && empty($_SESSION['inventory_csrf_token'])) {
    $_SESSION['inventory_csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.in_stock  { background: #dcfce7; color: #166534; }
.badge-status.reserved  { background: #fef3c7; color: #92400e; }
.badge-status.issued    { background: #e5e7eb; color: #374151; }
.badge-status.in_transit{ background: #dbeafe; color: #1e40af; }
.badge-status.returned  { background: #ede9fe; color: #5b21b6; }
.badge-status.defective { background: #fee2e2; color: #991b1b; }
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
                <div class="page-title">Inventory</div>
                <div class="small text-muted">View stock on hand and manually add received inventory when needed.</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Inventory</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <?php if ($isAdminTier): ?>
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-primary-app btn-sm" id="manualAddBtn" onclick="openManualInventory()"><i class="fas fa-plus me-1"></i>Manual Add Inventory</button>
        </div>
        <?php endif; ?>
        <div id="invNotice" class="alert d-none" role="status"></div>
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><button class="nav-link active" id="tabUnitsBtn" onclick="showInvTab('units')">Serialized Units</button></li>
            <li class="nav-item"><button class="nav-link" id="tabStockBtn" onclick="showInvTab('stock')">Bulk Stock</button></li>
            <?php if ($isAdminTier): ?>
            <li class="nav-item"><button class="nav-link" id="tabUnassignedBtn" onclick="showInvTab('unassigned')">Quick Scan <span class="badge bg-secondary" id="unassignedCount">0</span></button></li>
            <?php endif; ?>
        </ul>

        <div class="panel mb-3">
            <div class="panel-body d-flex flex-wrap gap-2 align-items-end">
                <div><label class="form-label">Search</label>
                    <input type="text" class="form-control form-control-sm" id="invSearch" placeholder="Item, SKU, serial…" onkeyup="if(event.key==='Enter')loadInventory().catch(error => showInventoryNotice(error.message))"></div>
                <div id="invStatusWrap"><label class="form-label">Status</label>
                    <select class="form-select form-select-sm" id="invStatus">
                        <option value="">All statuses</option>
                        <option value="in_stock">In Stock</option>
                        <option value="reserved">Reserved</option>
                        <option value="issued">Issued</option>
                        <option value="in_transit">In Transit</option>
                        <option value="returned">Returned</option>
                        <option value="defective">Defective</option>
                    </select></div>
                <button class="btn btn-primary-app btn-sm" onclick="loadInventory().catch(error => showInventoryNotice(error.message))"><i class="fas fa-filter me-1"></i>Filter</button>
            </div>
        </div>

        <div id="tabUnits">
            <div class="panel">
                <div class="panel-header"><div class="panel-title">Serial-Tracked Units</div></div>
                <div class="panel-body">
                    <div class="table-responsive">
                    <table class="app-table table">
                        <thead><tr><th>Item</th><th>Category</th><th>Serial</th><th>Branch</th><th>Status</th><th>Unit Cost</th><th>Received</th><th>Location</th></tr></thead>
                        <tbody id="unitsBody"><tr><td colspan="8" class="text-muted small">Loading…</td></tr></tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="tabStock" style="display:none">
            <div class="panel">
                <div class="panel-header"><div class="panel-title">Bulk / Non-Serialized Stock</div></div>
                <div class="panel-body">
                    <div class="table-responsive">
                    <table class="app-table table">
                        <thead><tr><th>Item</th><th>Category</th><th>Branch</th><th>Qty on Hand</th><th>Unit</th><th>Reorder Level</th></tr></thead>
                        <tbody id="stockBody"><tr><td colspan="6" class="text-muted small">Loading…</td></tr></tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isAdminTier): ?>
        <div id="tabUnassigned" style="display:none">
            <p class="text-muted small">Serials scanned without a master item yet. Select the ones from the same delivery and assign them to one item at once.</p>
            <div class="d-flex justify-content-end gap-2 mb-3">
                <button type="button" class="btn btn-outline-primary btn-sm" id="segregateBtn" onclick="openSegregateModal()" disabled><i class="fas fa-random me-1"></i>Segregate Selected</button>
                <button type="button" class="btn btn-primary-app btn-sm" id="quickScanBtn" onclick="openQuickScan()"><i class="fas fa-barcode me-1"></i>Quick Scan Serials</button>
            </div>
            <div class="panel">
                <div class="panel-header"><div class="panel-title">Scanned Serials Awaiting a Master Item</div></div>
                <div class="panel-body">
                    <div class="table-responsive">
                    <table class="app-table table">
                        <thead><tr><th style="width:36px"><input type="checkbox" id="unassignedSelectAll" aria-label="Select all"></th><th>Serial</th><th>Branch</th><th>Scanned</th><th>Scanned By</th><th>Remarks</th></tr></thead>
                        <tbody id="unassignedBody"><tr><td colspan="6" class="text-muted small">Loading…</td></tr></tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php if ($isAdminTier): ?>
<div class="modal fade" id="manualInventoryModal" tabindex="-1" aria-labelledby="manualInventoryTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manualInventoryTitle">Manual Add Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="manualInventoryForm" data-csrf-token="<?= e($_SESSION['inventory_csrf_token']) ?>" novalidate style="display:contents">
                <div class="modal-body">
                    <p class="text-muted small">Select an item, set its cost, then scan each serial number and press Enter. Switch items as needed, review the list, and save the whole arrival together.</p>
                    <div id="manualInvError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="manualInvBranch">Branch</label>
                            <select class="form-select" id="manualInvBranch" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="manualInvDate">Date &amp; Time Received</label>
                            <input type="datetime-local" step="1" class="form-control" id="manualInvDate" min="1000-01-01T00:00:00" max="9999-12-31T23:59:59" value="<?= date('Y-m-d\TH:i:s') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="manualInvRemarks">Reason / Reference for this arrival</label>
                            <input type="text" class="form-control" id="manualInvRemarks" maxlength="255" placeholder="e.g. supplier delivery reference or opening stock" required>
                        </div>
                    </div>
                    <div class="border rounded p-3 mb-3" id="manualInvComposer">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <strong id="manualInvEntryTitle">Add an item to the batch</strong>
                            <button type="button" class="btn btn-light btn-sm" id="manualInvResetLine">Clear entry</button>
                        </div>
                        <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="manualInvItemSearch">Find Item</label>
                            <input type="search" class="form-control" id="manualInvItemSearch" placeholder="Item name or SKU" autocomplete="off">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="manualInvItem">Item</label>
                            <select class="form-select" id="manualInvItem" required></select>
                            <div class="form-text" id="manualInvItemHint">Choose an active item from the Item Catalog.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="manualInvQuantity">Quantity to Add <span class="text-muted" id="manualInvUnit"></span></label>
                            <input type="number" class="form-control" id="manualInvQuantity" min="0.01" max="999999999999.99" step="0.01" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="manualInvCost">Unit Cost (₱)</label>
                            <input type="number" class="form-control" id="manualInvCost" min="0" max="9999999999999.99" step="0.01" value="0" required>
                        </div>
                        <div class="col-md-4 d-none" id="manualInvLocationWrap">
                            <label class="form-label" for="manualInvLocation">Location <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" id="manualInvLocation" maxlength="100" placeholder="Shelf / bin" disabled>
                        </div>
                        <div class="col-md-8 d-none" id="manualInvSerialWrap">
                            <label class="form-label" for="manualInvSerial">Scan / Enter Serial Number</label>
                            <input type="text" class="form-control" id="manualInvSerial" maxlength="100" placeholder="Scan serial barcode, then Enter" autocomplete="off" spellcheck="false" disabled>
                            <div class="form-text">Each scan adds one unit. The item, cost and location stay selected for the next scan.</div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-primary w-100" id="manualInvAddLine">Add to Batch</button>
                        </div>
                        </div>
                    </div>
                    <div id="manualInvFeedback" class="small text-success mb-2" role="status" aria-live="polite"></div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Items to Add</strong><span class="text-muted small" id="manualInvCount">0 / 500 rows</span>
                    </div>
                    <div class="table-responsive" style="max-height:300px">
                        <table class="app-table table table-sm mb-0">
                            <thead><tr><th>#</th><th>Item</th><th>Serial / Type</th><th>Qty</th><th>Unit Cost</th><th>Location</th><th>Actions</th></tr></thead>
                            <tbody id="manualInvRows"><tr><td colspan="7" class="text-muted small">Scan a serial or add a bulk quantity to begin.</td></tr></tbody>
                        </table>
                    </div>
                    <div class="form-text mt-2">Up to 500 rows per save. Every row is recorded in the activity log. If a row is invalid, the entire batch stays unsaved for correction.</div>
                </div>
                <div class="modal-footer">
                    <span class="me-auto small" id="manualInvSummary">No items queued.</span>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-app" id="manualInvSave" disabled>Save All to Inventory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="quickScanModal" tabindex="-1" aria-labelledby="quickScanTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickScanTitle">Quick Scan Serials</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickScanForm" data-csrf-token="<?= e($_SESSION['inventory_csrf_token']) ?>" novalidate style="display:contents">
                <div class="modal-body">
                    <p class="text-muted small">Scan each serial and press Enter — no item needed yet. Segregate them to a master item afterward from the Quick Scan tab.</p>
                    <div id="quickScanError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="quickScanBranch">Branch</label>
                            <select class="form-select" id="quickScanBranch" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="quickScanDate">Date &amp; Time Received</label>
                            <input type="datetime-local" step="1" class="form-control" id="quickScanDate" min="1000-01-01T00:00:00" max="9999-12-31T23:59:59" value="<?= date('Y-m-d\TH:i:s') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="quickScanRemarks">Reason / Reference <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" id="quickScanRemarks" maxlength="255" placeholder="e.g. supplier delivery reference">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="quickScanSerial">Scan / Enter Serial Number</label>
                            <input type="text" class="form-control" id="quickScanSerial" maxlength="100" placeholder="Scan serial barcode, then Enter" autocomplete="off" spellcheck="false">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Serials Scanned</strong><span class="text-muted small" id="quickScanCount">0 / 500</span>
                    </div>
                    <div class="table-responsive" style="max-height:260px">
                        <table class="app-table table table-sm mb-0">
                            <thead><tr><th>#</th><th>Serial</th><th>Actions</th></tr></thead>
                            <tbody id="quickScanRows"><tr><td colspan="3" class="text-muted small">Scan a serial to begin.</td></tr></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <span class="me-auto small" id="quickScanSummary">No serials scanned.</span>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-app" id="quickScanSave" disabled>Save Scanned Serials</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="segregateModal" tabindex="-1" aria-labelledby="segregateTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="segregateTitle">Segregate Scanned Serials</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="segregateForm" data-csrf-token="<?= e($_SESSION['inventory_csrf_token']) ?>" novalidate style="display:contents">
                <div class="modal-body">
                    <p class="text-muted small">Assign the selected scanned serials to one master item. Each becomes a normal serialized unit for that item.</p>
                    <div id="segregateError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="mb-3">
                        <strong>Selected serials</strong>
                        <div class="small text-muted" id="segregateSerialList"></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="segregateItemSearch">Find Item</label>
                            <input type="search" class="form-control" id="segregateItemSearch" placeholder="Item name or SKU" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="segregateItem">Master Item</label>
                            <select class="form-select" id="segregateItem" required></select>
                            <div class="form-text">Only serialized items are listed — scanned serials can't be assigned to a bulk item.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="segregateCost">Unit Cost (₱)</label>
                            <input type="number" class="form-control" id="segregateCost" min="0" max="9999999999999.99" step="0.01" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="segregateLocation">Location <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" id="segregateLocation" maxlength="100" placeholder="Shelf / bin">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="segregateRemarks">Remarks <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" id="segregateRemarks" maxlength="255" placeholder="Overrides each scan's own remarks">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary-app" id="segregateSave">Assign to Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
const statusLabel = { in_stock: 'In Stock', reserved: 'Reserved', issued: 'Issued', in_transit: 'In Transit', returned: 'Returned', defective: 'Defective' };

function showInvTab(tab) {
    document.getElementById('tabUnits').style.display = tab === 'units' ? '' : 'none';
    document.getElementById('tabStock').style.display  = tab === 'stock' ? '' : 'none';
    document.getElementById('tabUnitsBtn').classList.toggle('active', tab === 'units');
    document.getElementById('tabStockBtn').classList.toggle('active', tab === 'stock');
    document.getElementById('invStatusWrap').style.display = tab === 'units' ? '' : 'none';
    const unassignedTab = document.getElementById('tabUnassigned');
    if (unassignedTab) {
        unassignedTab.style.display = tab === 'unassigned' ? '' : 'none';
        document.getElementById('tabUnassignedBtn').classList.toggle('active', tab === 'unassigned');
        if (tab === 'unassigned' && window.loadUnassigned) window.loadUnassigned().catch(error => showInventoryNotice(error.message));
    }
}

async function loadInventory() {
    const params = new URLSearchParams();
    const q = document.getElementById('invSearch').value.trim();
    const status = document.getElementById('invStatus').value;
    if (q) params.set('q', q);
    if (status) params.set('status', status);
    const data = await inventoryRequest('api/inventory.php?' + params.toString());

    document.getElementById('unitsBody').innerHTML = data.units.length ? data.units.map(u => `
        <tr>
            <td>${escapeHtml(u.item_name)}${u.sku ? ' <span class="text-muted small">('+escapeHtml(u.sku)+')</span>' : ''}</td>
            <td>${escapeHtml(u.category_name || '—')}</td>
            <td>${escapeHtml(u.serial_number || '—')}</td>
            <td>${escapeHtml(u.branch_name)}</td>
            <td><span class="badge-status ${u.status}">${statusLabel[u.status] || u.status}</span></td>
            <td>${peso(u.unit_cost)}</td>
            <td>${AppDateTime.format(u.date_received)}</td>
            <td>${escapeHtml(u.location || '—')}</td>
        </tr>`).join('') : '<tr><td colspan="8" class="text-muted small">No units found.</td></tr>';

    document.getElementById('stockBody').innerHTML = data.stock.length ? data.stock.map(s => `
        <tr>
            <td>${escapeHtml(s.item_name)}${s.sku ? ' <span class="text-muted small">('+escapeHtml(s.sku)+')</span>' : ''}</td>
            <td>${escapeHtml(s.category_name || '—')}</td>
            <td>${escapeHtml(s.branch_name)}</td>
            <td>${Number(s.quantity_on_hand).toLocaleString()}</td>
            <td>${escapeHtml(s.unit)}</td>
            <td>${Number(s.reorder_level).toLocaleString()}</td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted small">No bulk stock found.</td></tr>';
}

async function inventoryRequest(url, options = {}) {
    const response = await fetch(url, options);
    let data;
    try {
        data = await response.json();
    } catch (error) {
        throw new Error('The server returned an unreadable response. Refresh inventory to check the current stock before trying again.');
    }
    if (!response.ok || !data.success) throw new Error(data.message || 'Unable to complete the inventory request.');
    return data;
}

function showInventoryNotice(message, type = 'danger') {
    const notice = document.getElementById('invNotice');
    notice.className = 'alert alert-' + type;
    notice.textContent = message;
}



loadInventory().catch(error => showInventoryNotice(error.message));
<?php if ($isAdminTier): ?>
document.addEventListener('DOMContentLoaded', () => { if (window.loadUnassigned) window.loadUnassigned().catch(() => {}); });
<?php endif; ?>
</script>
<?php if ($isAdminTier): ?>
<script src="js/manual-inventory.js"></script>
<script src="js/quick-scan-inventory.js"></script>
<?php endif; ?>
<?php include 'profile_modal.php'; ?>
</body>
</html>
