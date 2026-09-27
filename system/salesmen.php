<?php
require_once 'config.php';
requireModule('salesmen');
$currentUser = getCurrentUser();
$activePage  = 'salesmen';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Salesman List | <?= e(APP_NAME) ?></title>
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
                    <h2 class="page-title">Salesman List</h2>
                    <div class="text-muted small">Who to credit a sale to — tracked on Issuance, separate from system logins.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="panel-title">Salesmen</div>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" id="smSearch" placeholder="Search…" style="width:200px" onkeyup="if(event.key==='Enter')loadSalesmen()">
                    <?php if ($isAdminTier): ?>
                    <button class="btn btn-primary-app btn-sm" onclick="openSalesmanModal()"><i class="fas fa-plus me-1"></i>New Salesman</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Name</th><th>Code</th><th>Number</th><th>Email</th><th>Status</th><th></th></tr></thead>
                    <tbody id="smBody"><tr><td colspan="6" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="salesmanModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="salesmanModalTitle">New Salesman</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="salesmanForm">
        <div class="modal-body">
          <input type="hidden" id="smId">
          <div class="mb-2"><label class="form-label">Name</label><input type="text" class="form-control" id="smName" required></div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Code</label><input type="text" class="form-control" id="smCode" placeholder="optional"></div>
            <div class="col-6"><label class="form-label">Contact Number</label><input type="text" class="form-control" id="smContactNumber"></div>
            <div class="col-12"><label class="form-label">Email</label><input type="email" class="form-control" id="smEmail"></div>
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
let smCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function loadSalesmen() {
    const q = document.getElementById('smSearch').value.trim();
    const res  = await fetch('api/salesmen.php?q=' + encodeURIComponent(q));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    smCache = data.salesmen;
    document.getElementById('smBody').innerHTML = smCache.length ? smCache.map(s => `
        <tr>
            <td>${escapeHtml(s.name)}</td>
            <td>${escapeHtml(s.code || '—')}</td>
            <td>${escapeHtml(s.contact_number || '—')}</td>
            <td>${escapeHtml(s.email || '—')}</td>
            <td><span class="badge-status ${s.is_active ? 'active' : 'inactive'}">${s.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-end">${isAdminTier ? `
                <button class="btn-sm-action" onclick="editSalesman(${s.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn-sm-action" onclick="toggleSalesman(${s.id})" title="Toggle"><i class="fas fa-power-off"></i></button>` : ''}</td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted small">No salesmen found.</td></tr>';
}

function openSalesmanModal() {
    document.getElementById('salesmanForm').reset();
    document.getElementById('smId').value = '';
    document.getElementById('salesmanModalTitle').textContent = 'New Salesman';
    new bootstrap.Modal(document.getElementById('salesmanModal')).show();
}
function editSalesman(id) {
    const s = smCache.find(x => x.id === id);
    if (!s) return;
    document.getElementById('smId').value = s.id;
    document.getElementById('smName').value = s.name;
    document.getElementById('smCode').value = s.code || '';
    document.getElementById('smContactNumber').value = s.contact_number || '';
    document.getElementById('smEmail').value = s.email || '';
    document.getElementById('salesmanModalTitle').textContent = 'Edit Salesman';
    new bootstrap.Modal(document.getElementById('salesmanModal')).show();
}
document.getElementById('salesmanForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('smId').value;
    const payload = {
        action: id ? 'update' : 'create', id: id || undefined,
        name: document.getElementById('smName').value.trim(),
        code: document.getElementById('smCode').value.trim(),
        contact_number: document.getElementById('smContactNumber').value.trim(),
        email: document.getElementById('smEmail').value.trim(),
    };
    const res  = await fetch('api/salesmen.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('salesmanModal')).hide();
    loadSalesmen();
});
async function toggleSalesman(id) {
    if (!confirm('Change this salesman\'s active status?')) return;
    const res  = await fetch('api/salesmen.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'toggle_active', id }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    loadSalesmen();
}
loadSalesmen();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
