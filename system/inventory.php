<?php
require_once 'config.php';
requireModule('inventory');
$currentUser = getCurrentUser();
$activePage  = 'inventory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory | <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%232563eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' font-weight='700' fill='white' text-anchor='middle'%3EE%3C/text%3E%3C/svg%3E">
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
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Inventory</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><button class="nav-link active" id="tabUnitsBtn" onclick="showInvTab('units')">Serialized Units</button></li>
            <li class="nav-item"><button class="nav-link" id="tabStockBtn" onclick="showInvTab('stock')">Bulk Stock</button></li>
        </ul>

        <div class="panel mb-3">
            <div class="panel-body d-flex flex-wrap gap-2 align-items-end">
                <div><label class="form-label">Search</label>
                    <input type="text" class="form-control form-control-sm" id="invSearch" placeholder="Item, SKU, serial…" onkeyup="if(event.key==='Enter')loadInventory()"></div>
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
                <button class="btn btn-primary-app btn-sm" onclick="loadInventory()"><i class="fas fa-filter me-1"></i>Filter</button>
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
    </div>
</div>
</div>

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
}

async function loadInventory() {
    const params = new URLSearchParams();
    const q = document.getElementById('invSearch').value.trim();
    const status = document.getElementById('invStatus').value;
    if (q) params.set('q', q);
    if (status) params.set('status', status);
    const res  = await fetch('api/inventory.php?' + params.toString());
    const data = await res.json();
    if (!data.success) return alert(data.message);

    document.getElementById('unitsBody').innerHTML = data.units.length ? data.units.map(u => `
        <tr>
            <td>${escapeHtml(u.item_name)}${u.sku ? ' <span class="text-muted small">('+escapeHtml(u.sku)+')</span>' : ''}</td>
            <td>${escapeHtml(u.category_name || '—')}</td>
            <td>${escapeHtml(u.serial_number || '—')}</td>
            <td>${escapeHtml(u.branch_name)}</td>
            <td><span class="badge-status ${u.status}">${statusLabel[u.status] || u.status}</span></td>
            <td>${peso(u.unit_cost)}</td>
            <td>${u.date_received || '—'}</td>
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

loadInventory();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
