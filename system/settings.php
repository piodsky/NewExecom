<?php
require_once 'config.php';
requireModule('settings');
$currentUser = getCurrentUser();
$activePage  = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management | <?= e(APP_NAME) ?></title>
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
                <div class="page-title">User Management</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Users</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Accounts</div>
                <button class="btn btn-primary-app btn-sm" onclick="openUserModal()">
                    <i class="fas fa-user-plus me-1"></i>New Account
                </button>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table" id="usersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="usersBody">
                        <tr><td colspan="7" class="text-muted small">Loading…</td></tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- User modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalTitle">New Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="userForm">
        <div class="modal-body">
          <input type="hidden" id="uId">
          <div class="row g-2">
            <div class="col-6"><label class="form-label">First Name</label>
                <input type="text" class="form-control" id="uFirstName" required></div>
            <div class="col-6"><label class="form-label">Middle Name</label>
                <input type="text" class="form-control" id="uMiddleName"></div>
            <div class="col-8"><label class="form-label">Last Name</label>
                <input type="text" class="form-control" id="uLastName" required></div>
            <div class="col-4"><label class="form-label">Suffix</label>
                <input type="text" class="form-control" id="uSuffix"></div>
            <div class="col-12"><label class="form-label">Department</label>
                <input type="text" class="form-control" id="uDepartment"></div>
            <div class="col-6"><label class="form-label">Username</label>
                <input type="text" class="form-control" id="uUsername" required></div>
            <div class="col-6" id="uPasswordWrap"><label class="form-label">Password</label>
                <input type="password" class="form-control" id="uPassword" placeholder="min. 8 characters"></div>
            <div class="col-6"><label class="form-label">Role</label>
                <select class="form-select" id="uRole"></select></div>
            <div class="col-6" id="uBranchWrap"><label class="form-label">Branch</label>
                <select class="form-select" id="uBranch"></select></div>
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

<!-- Reset password modal -->
<div class="modal fade" id="pwModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="pwForm">
        <div class="modal-body">
          <input type="hidden" id="pwUserId">
          <label class="form-label">New Password</label>
          <input type="password" class="form-control" id="pwNewPassword" placeholder="min. 8 characters" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Reset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
let usersCache = [], branchesCache = [], rolesCache = [];
const roleLabels = { super_admin: 'Super Administrator', branch_admin: 'Branch Administrator', branch_user: 'Branch User' };

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function loadUsers() {
    const res  = await fetch('api/settings.php');
    const data = await res.json();
    if (!data.success) { alert(data.message); return; }
    usersCache = data.users; branchesCache = data.branches; rolesCache = data.roles;

    const roleOpts = rolesCache.map(r => `<option value="${r}">${roleLabels[r] || r}</option>`).join('');
    document.getElementById('uRole').innerHTML = roleOpts;
    document.getElementById('uBranch').innerHTML =
        '<option value="">— None (Super Admin) —</option>' +
        branchesCache.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');

    document.getElementById('usersBody').innerHTML = usersCache.length ? usersCache.map(u => `
        <tr>
            <td>${escapeHtml(u.full_name)}</td>
            <td>${escapeHtml(u.username)}</td>
            <td>${roleLabels[u.role] || escapeHtml(u.role)}</td>
            <td>${escapeHtml(u.branch_name || '—')}</td>
            <td><span class="badge-status ${u.is_active ? 'active' : 'inactive'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>${u.last_login ? new Date(u.last_login).toLocaleString() : '—'}</td>
            <td class="text-end">
                <button class="btn-sm-action" onclick='editUser(${u.id})' title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn-sm-action" onclick="resetPassword(${u.id})" title="Reset Password"><i class="fas fa-key"></i></button>
                <button class="btn-sm-action" onclick="toggleActive(${u.id})" title="${u.is_active ? 'Deactivate' : 'Activate'}">
                    <i class="fas fa-${u.is_active ? 'user-slash' : 'user-check'}"></i>
                </button>
            </td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No accounts found.</td></tr>';
}

function openUserModal() {
    document.getElementById('userForm').reset();
    document.getElementById('uId').value = '';
    document.getElementById('userModalTitle').textContent = 'New Account';
    document.getElementById('uUsername').disabled = false;
    document.getElementById('uPasswordWrap').style.display = '';
    document.getElementById('uPassword').required = true;
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function editUser(id) {
    const u = usersCache.find(x => x.id === id);
    if (!u) return;
    document.getElementById('uId').value = u.id;
    document.getElementById('uFirstName').value  = u.first_name || '';
    document.getElementById('uMiddleName').value = u.middle_name || '';
    document.getElementById('uLastName').value   = u.last_name || '';
    document.getElementById('uSuffix').value     = u.suffix || '';
    document.getElementById('uDepartment').value = u.department || '';
    document.getElementById('uUsername').value   = u.username;
    document.getElementById('uUsername').disabled = true;
    document.getElementById('uRole').value   = u.role;
    document.getElementById('uBranch').value = u.branch_id || '';
    document.getElementById('uPasswordWrap').style.display = 'none';
    document.getElementById('uPassword').required = false;
    document.getElementById('userModalTitle').textContent = 'Edit Account';
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

document.getElementById('userForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('uId').value;
    const payload = {
        action: id ? 'update' : 'create',
        id: id || undefined,
        first_name:  document.getElementById('uFirstName').value.trim(),
        middle_name: document.getElementById('uMiddleName').value.trim(),
        last_name:   document.getElementById('uLastName').value.trim(),
        suffix:      document.getElementById('uSuffix').value.trim(),
        department:  document.getElementById('uDepartment').value.trim(),
        username:    document.getElementById('uUsername').value.trim(),
        password:    document.getElementById('uPassword').value,
        role:        document.getElementById('uRole').value,
        branch_id:   document.getElementById('uBranch').value || null,
    };
    const res  = await fetch('api/settings.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
    loadUsers();
});

function resetPassword(id) {
    document.getElementById('pwUserId').value = id;
    document.getElementById('pwNewPassword').value = '';
    new bootstrap.Modal(document.getElementById('pwModal')).show();
}
document.getElementById('pwForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = { action: 'reset_password', id: document.getElementById('pwUserId').value, password: document.getElementById('pwNewPassword').value };
    const res  = await fetch('api/settings.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('pwModal')).hide();
    alert('Password reset.');
});

async function toggleActive(id) {
    if (!confirm('Change this account\'s active status?')) return;
    const res  = await fetch('api/settings.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'toggle_active', id }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    loadUsers();
}

loadUsers();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
