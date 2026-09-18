<?php
require_once 'config.php';
requireModule('purchase_requisition');
$currentUser = getCurrentUser();
$activePage  = 'purchase_requisitions';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
$isSuperAdmin = $currentUser['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Requisitions | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.draft     { background: #f1f5f9; color: #475569; }
.badge-status.pending   { background: #fef3c7; color: #92400e; }
.badge-status.approved  { background: #dcfce7; color: #166534; }
.badge-status.rejected  { background: #fee2e2; color: #991b1b; }
.badge-status.converted { background: #dbeafe; color: #1e40af; }
.pr-item-row { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: .5rem; margin-bottom: .5rem; align-items: center; }
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
                <div class="page-title">Purchase Requisitions</div>
                <div class="small text-muted">Request items needed by a branch before canvassing and purchasing.</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Purchase Requisitions</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><button class="nav-link active" id="tabPrBtn" onclick="showTab('pr')">Requisitions</button></li>
            <li class="nav-item"><button class="nav-link" id="tabDeptBtn" onclick="showTab('dept')">Departments</button></li>
        </ul>

        <div id="tabPr">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Requisitions<?= $currentUser['branch_name'] ? ' — ' . e($currentUser['branch_name']) : '' ?></div>
                <button class="btn btn-primary-app btn-sm" onclick="openPrModal()"><i class="fas fa-plus me-1"></i>New Requisition</button>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>PR Number</th><th>Branch</th><th>Purpose</th><th>Needed Date</th><th>Requested By</th><th>Status</th><th></th></tr></thead>
                    <tbody id="prBody"><tr><td colspan="7" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
        </div>

        <div id="tabDept" style="display:none">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Departments</div>
                <?php if ($isAdminTier): ?>
                <button class="btn btn-primary-app btn-sm" onclick="openDeptModal()"><i class="fas fa-plus me-1"></i>New Department</button>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Name</th><th>Status</th><th></th></tr></thead>
                    <tbody id="deptBody"><tr><td colspan="3" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>
</div>

<!-- New Requisition modal -->
<div class="modal fade" id="prModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Requisition</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
	      <form id="prForm">
	        <div class="modal-body">
	          <div class="row g-2 mb-2">
	            <?php if ($isSuperAdmin): ?>
	            <div class="col-12"><label class="form-label">Branch</label><select class="form-select" id="prBranch" required></select></div>
	            <?php endif; ?>
	            <div class="col-6"><label class="form-label">Department</label><select class="form-select" id="prDepartment"><option value="">—</option></select></div>
            <div class="col-6"><label class="form-label">Date &amp; Time Needed</label><input type="datetime-local" step="1" class="form-control" id="prNeededDate"></div>
            <div class="col-12"><label class="form-label">Purpose</label><input type="text" class="form-control" id="prPurpose"></div>
          </div>
          <label class="form-label">Items</label>
          <div id="prItemRows"></div>
          <button type="button" class="btn btn-light btn-sm" onclick="addPrItemRow()"><i class="fas fa-plus me-1"></i>Add Item</button>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-outline-secondary" data-submit="0">Save Draft</button>
          <button type="submit" class="btn btn-primary-app" data-submit="1">Save &amp; Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Requisition modal -->
<div class="modal fade" id="prViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="prViewTitle">Requisition</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="prViewBody"></div>
      <div class="modal-footer" id="prViewFooter"></div>
    </div>
  </div>
</div>

<!-- Department modal -->
<div class="modal fade" id="deptModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="deptModalTitle">New Department</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="deptForm">
        <div class="modal-body">
          <input type="hidden" id="dpId">
          <div class="mb-2"><label class="form-label">Name</label><input type="text" class="form-control" id="dpName" required></div>
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
const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const currentUserId = <?= (int)$currentUser['id'] ?>;
let prCache = [], branchOptions = null, deptCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const statusLabel = { draft: 'Draft', pending: 'Pending Approval', approved: 'Approved', rejected: 'Rejected', converted: 'Converted' };

function showTab(tab) {
    document.getElementById('tabPr').style.display   = tab === 'pr'   ? '' : 'none';
    document.getElementById('tabDept').style.display = tab === 'dept' ? '' : 'none';
    document.getElementById('tabPrBtn').classList.toggle('active', tab === 'pr');
    document.getElementById('tabDeptBtn').classList.toggle('active', tab === 'dept');
}

function renderDepartments() {
    document.getElementById('deptBody').innerHTML = deptCache.length ? deptCache.map(d => `
        <tr>
            <td>${escapeHtml(d.name)}</td>
            <td><span class="badge-status ${d.is_active ? 'active' : 'inactive'}">${d.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-end">${isAdminTier ? `
                <button class="btn-sm-action" onclick="editDept(${d.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn-sm-action" onclick="toggleDept(${d.id})" title="Toggle"><i class="fas fa-power-off"></i></button>` : ''}</td>
        </tr>`).join('') : '<tr><td colspan="3" class="text-muted small">No departments found.</td></tr>';

    document.getElementById('prDepartment').innerHTML = '<option value="">—</option>' +
        deptCache.filter(d => d.is_active).map(d => `<option value="${escapeHtml(d.name)}">${escapeHtml(d.name)}</option>`).join('');
}

function openDeptModal() {
    document.getElementById('deptForm').reset();
    document.getElementById('dpId').value = '';
    document.getElementById('deptModalTitle').textContent = 'New Department';
    new bootstrap.Modal(document.getElementById('deptModal')).show();
}
function editDept(id) {
    const d = deptCache.find(x => x.id === id);
    if (!d) return;
    document.getElementById('dpId').value = d.id;
    document.getElementById('dpName').value = d.name;
    document.getElementById('deptModalTitle').textContent = 'Edit Department';
    new bootstrap.Modal(document.getElementById('deptModal')).show();
}
document.getElementById('deptForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('dpId').value;
    const payload = {
        action: id ? 'update_department' : 'create_department', id: id || undefined,
        name: document.getElementById('dpName').value.trim(),
    };
    const res  = await fetch('api/purchase_requisitions.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('deptModal')).hide();
    loadPrs();
});
async function toggleDept(id) {
    if (!confirm('Change this department\'s active status?')) return;
    const res  = await fetch('api/purchase_requisitions.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'toggle_department_active', id }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    loadPrs();
}

async function loadBranchOptions(selectId) {
    if (!isSuperAdmin) return;
    if (!branchOptions) {
        const res = await fetch('api/branches.php');
        const data = await res.json();
        branchOptions = data.success ? data.branches.filter(b => b.is_active == 1) : [];
    }
    document.getElementById(selectId).innerHTML = '<option value="">Select branch...</option>' +
        branchOptions.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
}

async function loadPrs() {
    const res  = await fetch('api/purchase_requisitions.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    prCache = data.requisitions;
    deptCache = data.departments;
    renderDepartments();
    document.getElementById('prBody').innerHTML = prCache.length ? prCache.map(p => `
        <tr style="cursor:pointer" onclick="viewPr('${p.pr_number}')">
            <td><span class="doc-number doc-pr">${escapeHtml(p.pr_number)}</span></td>
            <td>${escapeHtml(p.branch_name)}</td>
            <td>${escapeHtml(p.purpose || '—')}</td>
            <td>${AppDateTime.format(p.needed_date)}</td>
            <td>${escapeHtml(p.requested_by_name)}</td>
            <td><span class="badge-status ${p.status}">${statusLabel[p.status] || p.status}</span></td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No requisitions yet.</td></tr>';
}

function addPrItemRow(desc = '', qty = '', unit = '') {
    const wrap = document.createElement('div');
    wrap.className = 'pr-item-row';
    wrap.innerHTML = `
        <input type="text" class="form-control form-control-sm pr-desc" placeholder="Description" value="${escapeHtml(desc)}">
        <input type="number" step="0.01" class="form-control form-control-sm pr-qty" placeholder="Qty" value="${escapeHtml(qty)}">
        <input type="text" class="form-control form-control-sm pr-unit" placeholder="Unit" value="${escapeHtml(unit)}">
        <button type="button" class="btn btn-sm btn-light" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>`;
    document.getElementById('prItemRows').appendChild(wrap);
}

async function openPrModal() {
	    document.getElementById('prForm').reset();
	    document.getElementById('prItemRows').innerHTML = '';
	    await loadBranchOptions('prBranch');
	    addPrItemRow();
	    new bootstrap.Modal(document.getElementById('prModal')).show();
}

document.querySelectorAll('#prForm button[type=submit]').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const items = [...document.querySelectorAll('#prItemRows .pr-item-row')].map(row => ({
            description: row.querySelector('.pr-desc').value.trim(),
            quantity: row.querySelector('.pr-qty').value,
            unit: row.querySelector('.pr-unit').value.trim(),
        })).filter(i => i.description && i.quantity);
        if (!items.length) return alert('Add at least one item with a description and quantity.');

	        const payload = {
	            action: 'create',
            department: document.getElementById('prDepartment').value.trim(),
            purpose: document.getElementById('prPurpose').value.trim(),
            needed_date: document.getElementById('prNeededDate').value,
            items,
	            submit: btn.dataset.submit === '1',
	        };
	        if (isSuperAdmin) {
	            const branchId = document.getElementById('prBranch').value;
	            if (!branchId) return alert('Select a branch.');
	            payload.branch_id = branchId;
	        }
	        const res  = await fetch('api/purchase_requisitions.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if (!data.success) return alert(data.message);
        bootstrap.Modal.getInstance(document.getElementById('prModal')).hide();
        loadPrs();
    });
});

async function viewPr(prNumber) {
    const res  = await fetch('api/purchase_requisitions.php?id=' + encodeURIComponent(prNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const pr = data.pr;
    document.getElementById('prViewTitle').textContent = pr.pr_number + ' — ' + statusLabel[pr.status];
    document.getElementById('prViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(pr.branch_name)}</div>
            <div class="col-6"><strong>Requested By:</strong> ${escapeHtml(pr.requested_by_name)}</div>
            <div class="col-6"><strong>Department:</strong> ${escapeHtml(pr.department || '—')}</div>
            <div class="col-6"><strong>Date &amp; Time Needed:</strong> ${AppDateTime.format(pr.needed_date)}</div>
            <div class="col-12"><strong>Purpose:</strong> ${escapeHtml(pr.purpose || '—')}</div>
            ${pr.remarks ? `<div class="col-12"><strong>Remarks:</strong> ${escapeHtml(pr.remarks)}</div>` : ''}
        </div>
        <table class="app-table table">
            <thead><tr><th>Description</th><th>Qty</th><th>Unit</th></tr></thead>
            <tbody>${pr.items.map(i => `<tr><td>${escapeHtml(i.description)}</td><td>${i.quantity}</td><td>${escapeHtml(i.unit || '—')}</td></tr>`).join('')}</tbody>
        </table>`;

    const canManage = isAdminTier || pr.requested_by === currentUserId;
    let footer = '';
    if (pr.status === 'draft' && canManage) {
        footer += `<button class="btn btn-danger btn-sm me-auto" onclick="prAction('${pr.pr_number}','delete')">Delete</button>
                    <button class="btn btn-primary-app btn-sm" onclick="prAction('${pr.pr_number}','submit')">Submit for Approval</button>`;
    }
    if (pr.status === 'pending' && isAdminTier) {
        footer += `<button class="btn btn-danger btn-sm" onclick="promptReject('${pr.pr_number}')">Reject</button>
                    <button class="btn btn-primary-app btn-sm" onclick="prAction('${pr.pr_number}','approve')">Approve</button>`;
    }
    footer += `<a class="btn btn-outline-secondary btn-sm" href="print_document.php?module=purchase_requisition&id=${encodeURIComponent(pr.pr_number)}" data-report-modal><i class="fas fa-print me-1"></i>Print / PDF</a>`;
    document.getElementById('prViewFooter').innerHTML = footer;
    new bootstrap.Modal(document.getElementById('prViewModal')).show();
}

async function prAction(prNumber, action, remarks) {
    const payload = { action, pr_number: prNumber };
    if (remarks !== undefined) payload.remarks = remarks;
    const res  = await fetch('api/purchase_requisitions.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('prViewModal')).hide();
    loadPrs();
}
function promptReject(prNumber) {
    const remarks = prompt('Reason for rejection (optional):') || '';
    prAction(prNumber, 'reject', remarks);
}

loadPrs();

// Deep link from Order Tracking (order_tracking.php?view=... lands here as ?view=...).
const _viewPr = new URLSearchParams(location.search).get('view');
if (_viewPr) viewPr(_viewPr);
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
