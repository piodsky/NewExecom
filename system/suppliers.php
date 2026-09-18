<?php
require_once 'config.php';
requireModule('suppliers');
$currentUser = getCurrentUser();
$activePage  = 'suppliers';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Suppliers | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.active   { background: #dcfce7; color: #166534; }
.badge-status.inactive { background: #fee2e2; color: #991b1b; }
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
                <div class="page-title">Suppliers</div>
                <div class="small text-muted">Maintain supplier profiles used for canvass, RFQ, and purchase orders.</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Suppliers</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="panel-title">EXECOM's Suppliers</div>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" id="supSearch" placeholder="Search…" style="width:200px" onkeyup="if(event.key==='Enter')loadSuppliers()">
                    <?php if ($isAdminTier): ?>
                    <button class="btn btn-primary-app btn-sm" onclick="openSupplierModal()"><i class="fas fa-plus me-1"></i>New Supplier</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Name</th><th>Contact Person</th><th>Number</th><th>Email</th><th>Terms</th><th>Status</th><th></th></tr></thead>
                    <tbody id="supBody"><tr><td colspan="7" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="supplierModalTitle">New Supplier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="supplierForm">
        <div class="modal-body">
          <input type="hidden" id="sId">
          <div class="mb-2"><label class="form-label">Name</label><input type="text" class="form-control" id="sName" required></div>
          <div class="mb-2"><label class="form-label">Address</label><input type="text" class="form-control" id="sAddress"></div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Contact Person</label><input type="text" class="form-control" id="sContactPerson"></div>
            <div class="col-6"><label class="form-label">Contact Number</label><input type="text" class="form-control" id="sContactNumber"></div>
            <div class="col-6"><label class="form-label">Email</label><input type="email" class="form-control" id="sEmail"></div>
            <div class="col-6"><label class="form-label">Payment Terms</label><input type="text" class="form-control" id="sTerms" placeholder="e.g. Net 30"></div>
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
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isAdminTier = <?= $isAdminTier ? 'true' : 'false' ?>;
let supCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function loadSuppliers() {
    const q = document.getElementById('supSearch').value.trim();
    const res  = await fetch('api/suppliers.php?q=' + encodeURIComponent(q));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    supCache = data.suppliers;
    document.getElementById('supBody').innerHTML = supCache.length ? supCache.map(s => `
        <tr>
            <td>${escapeHtml(s.name)}<div class="text-muted small">${escapeHtml(s.address || '')}</div></td>
            <td>${escapeHtml(s.contact_person || '—')}</td>
            <td>${escapeHtml(s.contact_number || '—')}</td>
            <td>${escapeHtml(s.email || '—')}</td>
            <td>${escapeHtml(s.payment_terms || '—')}</td>
            <td><span class="badge-status ${s.is_active ? 'active' : 'inactive'}">${s.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-end">${isAdminTier ? `
                <button class="btn-sm-action" onclick="editSupplier(${s.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn-sm-action" onclick="toggleSupplier(${s.id})" title="Toggle"><i class="fas fa-power-off"></i></button>` : ''}</td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No suppliers found.</td></tr>';
}

function openSupplierModal() {
    document.getElementById('supplierForm').reset();
    document.getElementById('sId').value = '';
    document.getElementById('supplierModalTitle').textContent = 'New Supplier';
    new bootstrap.Modal(document.getElementById('supplierModal')).show();
}
function editSupplier(id) {
    const s = supCache.find(x => x.id === id);
    if (!s) return;
    document.getElementById('sId').value = s.id;
    document.getElementById('sName').value = s.name;
    document.getElementById('sAddress').value = s.address || '';
    document.getElementById('sContactPerson').value = s.contact_person || '';
    document.getElementById('sContactNumber').value = s.contact_number || '';
    document.getElementById('sEmail').value = s.email || '';
    document.getElementById('sTerms').value = s.payment_terms || '';
    document.getElementById('supplierModalTitle').textContent = 'Edit Supplier';
    new bootstrap.Modal(document.getElementById('supplierModal')).show();
}
document.getElementById('supplierForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('sId').value;
    const payload = {
        action: id ? 'update' : 'create', id: id || undefined,
        name: document.getElementById('sName').value.trim(),
        address: document.getElementById('sAddress').value.trim(),
        contact_person: document.getElementById('sContactPerson').value.trim(),
        contact_number: document.getElementById('sContactNumber').value.trim(),
        email: document.getElementById('sEmail').value.trim(),
        payment_terms: document.getElementById('sTerms').value.trim(),
    };
    const res  = await fetch('api/suppliers.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('supplierModal')).hide();
    loadSuppliers();
});
async function toggleSupplier(id) {
    if (!confirm('Change this supplier\'s active status?')) return;
    const res  = await fetch('api/suppliers.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'toggle_active', id }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    loadSuppliers();
}
loadSuppliers();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
