<?php
require_once 'config.php';
requireRole(['super_admin']);
$currentUser = getCurrentUser();
$activePage  = 'branches';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Branches | <?= e(APP_NAME) ?></title>
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
                <div class="page-title">Branches</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Branches</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Company Branches</div>
                <button class="btn btn-primary-app btn-sm" onclick="openBranchModal()"><i class="fas fa-plus me-1"></i>New Branch</button>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Code</th><th>Name</th><th>Address</th><th>Contact No.</th><th>Status</th><th></th></tr></thead>
                    <tbody id="branchBody"><tr><td colspan="6" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="branchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="branchModalTitle">New Branch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="branchForm">
        <div class="modal-body">
          <input type="hidden" id="bId">
          <div class="row g-2">
            <div class="col-4"><label class="form-label">Code</label><input type="text" class="form-control" id="bCode" maxlength="10" required></div>
            <div class="col-8"><label class="form-label">Name</label><input type="text" class="form-control" id="bName" required></div>
            <div class="col-12"><label class="form-label">Address</label><input type="text" class="form-control" id="bAddress"></div>
            <div class="col-12"><label class="form-label">Contact No.</label><input type="text" class="form-control" id="bContactNo"></div>
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
let branchCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function loadBranches() {
    const res  = await fetch('api/branches.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    branchCache = data.branches;
    document.getElementById('branchBody').innerHTML = branchCache.length ? branchCache.map(b => `
        <tr>
            <td><strong>${escapeHtml(b.code)}</strong></td>
            <td>${escapeHtml(b.name)}</td>
            <td>${escapeHtml(b.address || '—')}</td>
            <td>${escapeHtml(b.contact_no || '—')}</td>
            <td><span class="badge-status ${b.is_active ? 'active' : 'inactive'}">${b.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-end">
                <button class="btn-sm-action" onclick="editBranch(${b.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn-sm-action" onclick="toggleBranch(${b.id})" title="Toggle"><i class="fas fa-power-off"></i></button>
            </td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-muted small">No branches found.</td></tr>';
}

function openBranchModal() {
    document.getElementById('branchForm').reset();
    document.getElementById('bId').value = '';
    document.getElementById('branchModalTitle').textContent = 'New Branch';
    new bootstrap.Modal(document.getElementById('branchModal')).show();
}
function editBranch(id) {
    const b = branchCache.find(x => x.id === id);
    if (!b) return;
    document.getElementById('bId').value = b.id;
    document.getElementById('bCode').value = b.code;
    document.getElementById('bName').value = b.name;
    document.getElementById('bAddress').value = b.address || '';
    document.getElementById('bContactNo').value = b.contact_no || '';
    document.getElementById('branchModalTitle').textContent = 'Edit Branch';
    new bootstrap.Modal(document.getElementById('branchModal')).show();
}
document.getElementById('branchForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('bId').value;
    const payload = {
        action: id ? 'update' : 'create', id: id || undefined,
        code: document.getElementById('bCode').value.trim(),
        name: document.getElementById('bName').value.trim(),
        address: document.getElementById('bAddress').value.trim(),
        contact_no: document.getElementById('bContactNo').value.trim(),
    };
    const res  = await fetch('api/branches.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('branchModal')).hide();
    loadBranches();
});
async function toggleBranch(id) {
    if (!confirm('Change this branch\'s active status?')) return;
    const res  = await fetch('api/branches.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'toggle_active', id }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    loadBranches();
}
loadBranches();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
