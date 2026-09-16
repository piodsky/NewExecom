<?php
require_once 'config.php';
requireModule('clients');
$currentUser = getCurrentUser();
$activePage  = 'clients';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clients | <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%232563eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' font-weight='700' fill='white' text-anchor='middle'%3EE%3C/text%3E%3C/svg%3E">
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
                <div class="page-title">Clients</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Clients</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="panel-title">Government &amp; Other End-Clients</div>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" id="cliSearch" placeholder="Search…" style="width:200px" onkeyup="if(event.key==='Enter')loadClients()">
                    <?php if ($isAdminTier): ?>
                    <button class="btn btn-primary-app btn-sm" onclick="openClientModal()"><i class="fas fa-plus me-1"></i>New Client</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Name</th><th>Address</th><th>Contact Person</th><th>Number</th><th>Status</th><th></th></tr></thead>
                    <tbody id="cliBody"><tr><td colspan="6" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="clientModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="clientModalTitle">New Client</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="clientForm">
        <div class="modal-body">
          <input type="hidden" id="cId">
          <div class="mb-2"><label class="form-label">Name / Office</label><input type="text" class="form-control" id="cName" required></div>
          <div class="mb-2"><label class="form-label">Address</label><input type="text" class="form-control" id="cAddress"></div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Contact Person</label><input type="text" class="form-control" id="cContactPerson"></div>
            <div class="col-6"><label class="form-label">Contact Number</label><input type="text" class="form-control" id="cContactNumber"></div>
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
let cliCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function loadClients() {
    const q = document.getElementById('cliSearch').value.trim();
    const res  = await fetch('api/clients.php?q=' + encodeURIComponent(q));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    cliCache = data.clients;
    document.getElementById('cliBody').innerHTML = cliCache.length ? cliCache.map(c => `
        <tr>
            <td>${escapeHtml(c.name)}</td>
            <td>${escapeHtml(c.address || '—')}</td>
            <td>${escapeHtml(c.contact_person || '—')}</td>
            <td>${escapeHtml(c.contact_number || '—')}</td>
            <td><span class="badge-status ${c.is_active ? 'active' : 'inactive'}">${c.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-end">${isAdminTier ? `
                <button class="btn-sm-action" onclick="editClient(${c.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn-sm-action" onclick="toggleClient(${c.id})" title="Toggle"><i class="fas fa-power-off"></i></button>` : ''}</td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted small">No clients found.</td></tr>';
}

function openClientModal() {
    document.getElementById('clientForm').reset();
    document.getElementById('cId').value = '';
    document.getElementById('clientModalTitle').textContent = 'New Client';
    new bootstrap.Modal(document.getElementById('clientModal')).show();
}
function editClient(id) {
    const c = cliCache.find(x => x.id === id);
    if (!c) return;
    document.getElementById('cId').value = c.id;
    document.getElementById('cName').value = c.name;
    document.getElementById('cAddress').value = c.address || '';
    document.getElementById('cContactPerson').value = c.contact_person || '';
    document.getElementById('cContactNumber').value = c.contact_number || '';
    document.getElementById('clientModalTitle').textContent = 'Edit Client';
    new bootstrap.Modal(document.getElementById('clientModal')).show();
}
document.getElementById('clientForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('cId').value;
    const payload = {
        action: id ? 'update' : 'create', id: id || undefined,
        name: document.getElementById('cName').value.trim(),
        address: document.getElementById('cAddress').value.trim(),
        contact_person: document.getElementById('cContactPerson').value.trim(),
        contact_number: document.getElementById('cContactNumber').value.trim(),
    };
    const res  = await fetch('api/clients.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('clientModal')).hide();
    loadClients();
});
async function toggleClient(id) {
    if (!confirm('Change this client\'s active status?')) return;
    const res  = await fetch('api/clients.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'toggle_active', id }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    loadClients();
}
loadClients();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
