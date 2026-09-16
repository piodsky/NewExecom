<?php
require_once 'config.php';
requireModule('purchase_requisition');
$currentUser = getCurrentUser();
$activePage  = 'purchase_requisitions';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Requisitions | <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%232563eb'/%3E%3Ctext x='16' y='22' font-family='Arial' font-size='18' font-weight='700' fill='white' text-anchor='middle'%3EE%3C/text%3E%3C/svg%3E">
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
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Purchase Requisitions</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
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
            <div class="col-6"><label class="form-label">Department</label><input type="text" class="form-control" id="prDepartment"></div>
            <div class="col-6"><label class="form-label">Needed Date</label><input type="date" class="form-control" id="prNeededDate"></div>
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

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isAdminTier = <?= $isAdminTier ? 'true' : 'false' ?>;
const currentUserId = <?= (int)$currentUser['id'] ?>;
let prCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const statusLabel = { draft: 'Draft', pending: 'Pending Approval', approved: 'Approved', rejected: 'Rejected', converted: 'Converted' };

async function loadPrs() {
    const res  = await fetch('api/purchase_requisitions.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    prCache = data.requisitions;
    document.getElementById('prBody').innerHTML = prCache.length ? prCache.map(p => `
        <tr style="cursor:pointer" onclick="viewPr('${p.pr_number}')">
            <td><strong>${escapeHtml(p.pr_number)}</strong></td>
            <td>${escapeHtml(p.branch_name)}</td>
            <td>${escapeHtml(p.purpose || '—')}</td>
            <td>${p.needed_date || '—'}</td>
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

function openPrModal() {
    document.getElementById('prForm').reset();
    document.getElementById('prItemRows').innerHTML = '';
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
            <div class="col-6"><strong>Needed Date:</strong> ${pr.needed_date || '—'}</div>
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
    document.getElementById('prViewFooter').innerHTML = footer || '<span class="text-muted small">No actions available.</span>';
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
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
