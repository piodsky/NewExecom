<?php
require_once 'config.php';
requireModule('items');
$currentUser = getCurrentUser();
$activePage  = 'items';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Item Catalog | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.active   { background: #dcfce7; color: #166534; }
.badge-status.inactive { background: #fee2e2; color: #991b1b; }
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
                    <h2 class="page-title">Item Catalog</h2>
                    <div class="text-muted small">Maintain item names, units, pricing, and stock tracking setup.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><button class="nav-link active" id="tabItemsBtn" onclick="showTab('items')">Items</button></li>
            <li class="nav-item"><button class="nav-link" id="tabCatBtn" onclick="showTab('cat')">Categories</button></li>
        </ul>

        <div id="tabItems">
            <div class="panel">
                <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="panel-title">Products</div>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control form-control-sm" id="itmSearch" placeholder="Search…" style="width:200px" onkeyup="if(event.key==='Enter')loadItems()">
                        <?php if ($isAdminTier): ?>
                        <button class="btn btn-primary-app btn-sm" onclick="openItemModal()"><i class="fas fa-plus me-1"></i>New Item</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                    <table class="app-table table">
                        <thead><tr><th>SKU</th><th>Name</th><th>Category</th><th>Brand</th><th>Unit</th><th>Serialized</th><th>Cost</th><th>Price</th><th>Status</th><th></th></tr></thead>
                        <tbody id="itmBody"><tr><td colspan="10" class="text-muted small">Loading…</td></tr></tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="tabCat" style="display:none">
            <div class="panel">
                <div class="panel-header d-flex justify-content-between align-items-center">
                    <div class="panel-title">Categories</div>
                    <?php if ($isAdminTier): ?>
                    <button class="btn btn-primary-app btn-sm" onclick="openCatModal()"><i class="fas fa-plus me-1"></i>New Category</button>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                    <table class="app-table table">
                        <thead><tr><th>Name</th><th>Parent</th><th>Default: Serial-Tracked?</th><th>Status</th><th></th></tr></thead>
                        <tbody id="catBody"><tr><td colspan="4" class="text-muted small">Loading…</td></tr></tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="itemModalTitle">New Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="itemForm">
        <div class="modal-body">
          <input type="hidden" id="iId">
          <div class="row g-2">
            <div class="col-4"><label class="form-label">SKU</label><input type="text" class="form-control" id="iSku"></div>
            <div class="col-8"><label class="form-label">Name</label><input type="text" class="form-control" id="iName" required></div>
            <div class="col-6"><label class="form-label">Category</label><select class="form-select" id="iCategory"><option value="">—</option></select></div>
            <div class="col-6"><label class="form-label">Brand</label><input type="text" class="form-control" id="iBrand"></div>
            <div class="col-4"><label class="form-label">Unit</label><input type="text" class="form-control" id="iUnit" value="unit"></div>
            <div class="col-4"><label class="form-label">Default Cost</label><input type="number" step="0.01" class="form-control" id="iCost"></div>
            <div class="col-4"><label class="form-label">Default Price</label><input type="number" step="0.01" class="form-control" id="iPrice"></div>
            <div class="col-12 form-check mt-2">
                <input type="checkbox" class="form-check-input" id="iSerialized" onchange="toggleItemUnitConversion()">
                <label class="form-check-label" for="iSerialized">Track by serial number (individual units)</label>
            </div>
          </div>
          <div class="row g-2 mt-1" id="iUnitConversionRow">
            <div class="col-12"><div class="small text-muted">Buys in a different unit than it sells? (e.g. buy by box, sell by piece)</div></div>
            <div class="col-6"><label class="form-label">Purchase Unit</label><input type="text" class="form-control" id="iPurchaseUnit" placeholder="e.g. box"></div>
            <div class="col-6"><label class="form-label">Units per Purchase</label><input type="number" min="0.001" step="0.001" class="form-control" id="iConversionFactor" placeholder="e.g. 10"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="catModalTitle">New Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="catForm">
        <div class="modal-body">
          <input type="hidden" id="cgId">
          <div class="mb-2"><label class="form-label">Name</label><input type="text" class="form-control" id="cgName" required></div>
          <div class="mb-2"><label class="form-label">Parent Category</label><select class="form-select" id="cgParentId"><option value="">— Top level —</option></select></div>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="cgSerialized" checked>
            <label class="form-check-label" for="cgSerialized">Items in this category are serial-tracked by default</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<script>
const isAdminTier = <?= $isAdminTier ? 'true' : 'false' ?>;
let itemsCache = [], categoriesCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// Flattens the category list into a parent-first, indented order. `excludeId`
// (and everything under it) is left out — used when picking a NEW parent for
// a category being edited, so it can't be assigned itself or one of its own
// descendants as its own parent (the server enforces this too; this is just
// so the dropdown doesn't offer an obviously-invalid choice in the first place).
function buildCategoryTree(categories, excludeId) {
    const byParent = new Map();
    categories.forEach(c => {
        const key = c.parent_id || null;
        if (!byParent.has(key)) byParent.set(key, []);
        byParent.get(key).push(c);
    });
    const excluded = new Set();
    if (excludeId) {
        const collect = (id) => {
            excluded.add(id);
            (byParent.get(id) || []).forEach(child => collect(child.id));
        };
        collect(excludeId);
    }
    const out = [];
    const walk = (parentId, depth) => {
        (byParent.get(parentId) || []).forEach(c => {
            if (excluded.has(c.id)) return;
            out.push({ id: c.id, name: c.name, depth });
            walk(c.id, depth + 1);
        });
    };
    walk(null, 0);
    return out;
}

function showTab(tab) {
    document.getElementById('tabItems').style.display = tab === 'items' ? '' : 'none';
    document.getElementById('tabCat').style.display    = tab === 'cat'   ? '' : 'none';
    document.getElementById('tabItemsBtn').classList.toggle('active', tab === 'items');
    document.getElementById('tabCatBtn').classList.toggle('active', tab === 'cat');
}

async function loadItems() {
    const q = document.getElementById('itmSearch').value.trim();
    const res  = await fetch('api/items.php?q=' + encodeURIComponent(q));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    itemsCache = data.items; categoriesCache = data.categories;

    document.getElementById('iCategory').innerHTML = '<option value="">—</option>' +
        buildCategoryTree(categoriesCache).map(c => `<option value="${c.id}">${'&nbsp;&nbsp;'.repeat(c.depth)}${c.depth ? '&#8618; ' : ''}${escapeHtml(c.name)}</option>`).join('');

    document.getElementById('itmBody').innerHTML = itemsCache.length ? itemsCache.map(i => `
        <tr>
            <td>${escapeHtml(i.sku || '—')}</td>
            <td>${escapeHtml(i.name)}</td>
            <td>${escapeHtml(i.category_name || '—')}</td>
            <td>${escapeHtml(i.brand || '—')}</td>
            <td>${escapeHtml(i.unit)}${i.purchase_unit ? ` <span class="text-muted small">(${Number(i.purchase_conversion_factor)}/${escapeHtml(i.purchase_unit)})</span>` : ''}</td>
            <td>${i.is_serialized ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-minus text-muted"></i>'}</td>
            <td>${i.default_cost !== null ? Number(i.default_cost).toLocaleString(undefined,{minimumFractionDigits:2}) : '—'}</td>
            <td>${i.default_price !== null ? Number(i.default_price).toLocaleString(undefined,{minimumFractionDigits:2}) : '—'}</td>
            <td><span class="badge-status ${i.is_active ? 'active' : 'inactive'}">${i.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-end">${isAdminTier ? `
                <button class="btn-sm-action" onclick="editItem(${i.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn-sm-action" onclick="toggleItem(${i.id})" title="Toggle"><i class="fas fa-power-off"></i></button>` : ''}</td>
        </tr>`).join('') : '<tr><td colspan="10" class="text-muted small">No items found.</td></tr>';

    renderCategories();
}

function renderCategories() {
    const byId = new Map(categoriesCache.map(c => [c.id, c]));
    const tree = buildCategoryTree(categoriesCache);
    document.getElementById('catBody').innerHTML = tree.length ? tree.map(t => {
        const c = byId.get(t.id);
        const parent = c.parent_id ? byId.get(c.parent_id) : null;
        return `
        <tr>
            <td>${'&nbsp;&nbsp;'.repeat(t.depth)}${t.depth ? '&#8618; ' : ''}${escapeHtml(c.name)}</td>
            <td>${parent ? escapeHtml(parent.name) : '<span class="text-muted">— top level —</span>'}</td>
            <td>${c.is_serialized ? 'Yes' : 'No'}</td>
            <td><span class="badge-status ${c.is_active ? 'active' : 'inactive'}">${c.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-end">${isAdminTier ? `
                <button class="btn-sm-action" onclick="editCategory(${c.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn-sm-action" onclick="toggleCategory(${c.id})" title="Toggle"><i class="fas fa-power-off"></i></button>` : ''}</td>
        </tr>`;
    }).join('') : '<tr><td colspan="5" class="text-muted small">No categories found.</td></tr>';
}

function toggleItemUnitConversion() {
    document.getElementById('iUnitConversionRow').classList.toggle('d-none', document.getElementById('iSerialized').checked);
}

function openItemModal() {
    document.getElementById('itemForm').reset();
    document.getElementById('iId').value = '';
    document.getElementById('iUnit').value = 'unit';
    document.getElementById('iSerialized').checked = true;
    toggleItemUnitConversion();
    document.getElementById('itemModalTitle').textContent = 'New Item';
    new bootstrap.Modal(document.getElementById('itemModal')).show();
}
function editItem(id) {
    const i = itemsCache.find(x => x.id === id);
    if (!i) return;
    document.getElementById('iId').value = i.id;
    document.getElementById('iSku').value = i.sku || '';
    document.getElementById('iName').value = i.name;
    document.getElementById('iCategory').value = i.category_id || '';
    document.getElementById('iBrand').value = i.brand || '';
    document.getElementById('iUnit').value = i.unit;
    document.getElementById('iCost').value = i.default_cost ?? '';
    document.getElementById('iPrice').value = i.default_price ?? '';
    document.getElementById('iSerialized').checked = !!i.is_serialized;
    document.getElementById('iPurchaseUnit').value = i.purchase_unit || '';
    document.getElementById('iConversionFactor').value = i.purchase_unit ? i.purchase_conversion_factor : '';
    toggleItemUnitConversion();
    document.getElementById('itemModalTitle').textContent = 'Edit Item';
    new bootstrap.Modal(document.getElementById('itemModal')).show();
}
document.getElementById('itemForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('iId').value;
    const payload = {
        action: id ? 'update_item' : 'create_item', id: id || undefined,
        sku: document.getElementById('iSku').value.trim(),
        name: document.getElementById('iName').value.trim(),
        category_id: document.getElementById('iCategory').value,
        brand: document.getElementById('iBrand').value.trim(),
        unit: document.getElementById('iUnit').value.trim(),
        default_cost: document.getElementById('iCost').value,
        default_price: document.getElementById('iPrice').value,
        is_serialized: document.getElementById('iSerialized').checked,
        purchase_unit: document.getElementById('iPurchaseUnit').value.trim(),
        purchase_conversion_factor: document.getElementById('iConversionFactor').value || 1,
    };
    const res  = await fetch('api/items.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
    loadItems();
});
async function toggleItem(id) {
    if (!confirm('Change this item\'s active status?')) return;
    const res  = await fetch('api/items.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'toggle_item_active', id }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    loadItems();
}

function populateParentSelect(excludeId) {
    document.getElementById('cgParentId').innerHTML = '<option value="">— Top level —</option>' +
        buildCategoryTree(categoriesCache, excludeId).map(c => `<option value="${c.id}">${'&nbsp;&nbsp;'.repeat(c.depth)}${c.depth ? '&#8618; ' : ''}${escapeHtml(c.name)}</option>`).join('');
}

function openCatModal() {
    document.getElementById('catForm').reset();
    document.getElementById('cgId').value = '';
    document.getElementById('cgSerialized').checked = true;
    populateParentSelect(null);
    document.getElementById('catModalTitle').textContent = 'New Category';
    new bootstrap.Modal(document.getElementById('catModal')).show();
}
function editCategory(id) {
    const c = categoriesCache.find(x => x.id === id);
    if (!c) return;
    document.getElementById('cgId').value = c.id;
    document.getElementById('cgName').value = c.name;
    document.getElementById('cgSerialized').checked = !!c.is_serialized;
    populateParentSelect(id);
    document.getElementById('cgParentId').value = c.parent_id || '';
    document.getElementById('catModalTitle').textContent = 'Edit Category';
    new bootstrap.Modal(document.getElementById('catModal')).show();
}
document.getElementById('catForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('cgId').value;
    const payload = {
        action: id ? 'update_category' : 'create_category', id: id || undefined,
        name: document.getElementById('cgName').value.trim(),
        parent_id: document.getElementById('cgParentId').value,
        is_serialized: document.getElementById('cgSerialized').checked,
    };
    const res  = await fetch('api/items.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('catModal')).hide();
    loadItems();
});
async function toggleCategory(id) {
    if (!confirm('Change this category\'s active status?')) return;
    const res  = await fetch('api/items.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'toggle_category_active', id }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    loadItems();
}

loadItems();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
