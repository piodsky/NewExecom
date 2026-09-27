<?php
require_once 'config.php';
requireModule('job_orders');
$currentUser = getCurrentUser();
$activePage  = 'job_orders';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
$isSuperAdmin = $currentUser['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Job Orders | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.received       { background: #dbeafe; color: #1e40af; }
.badge-status.in_diagnosis   { background: #ede9fe; color: #5b21b6; }
.badge-status.awaiting_parts { background: #fef3c7; color: #92400e; }
.badge-status.repaired       { background: #cffafe; color: #155e75; }
.badge-status.released       { background: #dcfce7; color: #166534; }
.badge-status.cancelled      { background: #fee2e2; color: #991b1b; }
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
                    <h2 class="page-title">Job Orders</h2>
                    <div class="text-muted small">Repair &amp; service tickets — intake, parts, and release.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="panel-title">Repair / Service Tickets</div>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="joStatusFilter" style="width:170px" onchange="loadJobOrders()">
                        <option value="all">All statuses</option>
                        <option value="received">Received</option>
                        <option value="in_diagnosis">In Diagnosis</option>
                        <option value="awaiting_parts">Awaiting Parts</option>
                        <option value="repaired">Repaired</option>
                        <option value="released">Released</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button class="btn btn-primary-app btn-sm" onclick="openCreateModal()"><i class="fas fa-plus me-1"></i>New Job Order</button>
                </div>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>JO #</th><th>Received</th><th>Customer</th><th>Unit</th><th>Technician</th><th>Status</th><th></th></tr></thead>
                    <tbody id="joBody"><tr><td colspan="7" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- New Job Order -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New Job Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="createForm">
        <div class="modal-body">
          <?php if ($isSuperAdmin): ?>
          <div class="mb-2"><label class="form-label">Branch</label>
            <select class="form-select" id="jBranchSelect" required><option value="">Select branch…</option></select>
          </div>
          <?php endif; ?>
          <div class="mb-2">
            <label class="form-label">Customer</label>
            <div class="d-flex gap-2 mb-2">
              <div class="form-check"><input class="form-check-input" type="radio" name="custType" id="custClient" value="client" checked onchange="toggleCustType()"><label class="form-check-label" for="custClient">Registered Client</label></div>
              <div class="form-check"><input class="form-check-input" type="radio" name="custType" id="custWalkin" value="walkin" onchange="toggleCustType()"><label class="form-check-label" for="custWalkin">Walk-in</label></div>
            </div>
            <select class="form-select" id="jClientId"></select>
            <div class="row g-2 d-none" id="walkinFields">
              <div class="col-6 mt-2"><input type="text" class="form-control" id="jWalkinName" placeholder="Customer name"></div>
              <div class="col-6 mt-2"><input type="text" class="form-control" id="jWalkinContact" placeholder="Contact number"></div>
            </div>
          </div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Unit Description</label><input type="text" class="form-control" id="jUnit" required placeholder="e.g. Acer Aspire Go AG15"></div>
            <div class="col-6"><label class="form-label">Serial Number</label><input type="text" class="form-control" id="jSerial"></div>
            <div class="col-12"><label class="form-label">Problem / Issue</label><input type="text" class="form-control" id="jProblem"></div>
            <div class="col-6"><label class="form-label">Accessories Received</label><input type="text" class="form-control" id="jAccessories"></div>
            <div class="col-6"><label class="form-label">Technician</label><select class="form-select" id="jTechnician"><option value="">— Unassigned —</option></select></div>
            <div class="col-12"><label class="form-label">Remarks</label><input type="text" class="form-control" id="jRemarks"></div>
            <div class="col-12">
              <div class="form-check"><input class="form-check-input" type="checkbox" id="jWarranty"><label class="form-check-label" for="jWarranty">Under warranty</label></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Create Job Order</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Manage Job Order -->
<div class="modal fade" id="manageModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="manageTitle">Job Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="manageBody">Loading…</div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<script>
const isAdminTier = <?= $isAdminTier ? 'true' : 'false' ?>;
const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const myBranchId   = <?= $currentUser['branch_id'] !== null ? (int)$currentUser['branch_id'] : 'null' ?>;
function currentBranchId() { return isSuperAdmin ? (document.getElementById('jBranchSelect')?.value || null) : myBranchId; }
async function loadBranchOptions() {
    if (!isSuperAdmin) return;
    const res = await fetch('api/branches.php');
    const data = await res.json();
    if (!data.success) return;
    document.getElementById('jBranchSelect').innerHTML = '<option value="">Select branch…</option>' +
        data.branches.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
}
let joCache = [];
let currentJoNumber = null;
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function money(n) { return Number(n || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }
function statusLabel(s) { return s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()); }

async function loadJobOrders() {
    const status = document.getElementById('joStatusFilter').value;
    const res  = await fetch('api/job_orders.php?status=' + encodeURIComponent(status));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    joCache = data.job_orders;
    document.getElementById('joBody').innerHTML = joCache.length ? joCache.map(j => `
        <tr>
            <td>${escapeHtml(j.jo_number)}</td>
            <td>${j.date_received ? new Date(j.date_received.replace(' ','T')).toLocaleDateString() : '—'}</td>
            <td>${escapeHtml(j.client_name || '—')}</td>
            <td>${escapeHtml(j.unit_description)}<div class="text-muted small">${escapeHtml(j.serial_number || '')}</div></td>
            <td>${escapeHtml(j.technician_name || '—')}</td>
            <td><span class="badge-status ${j.status}">${statusLabel(j.status)}</span></td>
            <td class="text-end">
                <button class="btn-sm-action" onclick="openManageModal('${j.jo_number}')" title="Manage"><i class="fas fa-screwdriver-wrench"></i></button>
                <a class="btn-sm-action" href="print_job_order.php?jo=${encodeURIComponent(j.jo_number)}" target="_blank" title="Print"><i class="fas fa-print"></i></a>
            </td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No job orders found.</td></tr>';
}

async function loadClientOptions() {
    const res = await fetch('api/clients.php?type=business');
    const data = await res.json();
    if (!data.success) return;
    const sel = document.getElementById('jClientId');
    sel.innerHTML = data.clients.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}
async function loadTechnicianOptions() {
    const res = await fetch('api/job_orders.php?technicians=1');
    const data = await res.json();
    if (!data.success) return;
    const sel = document.getElementById('jTechnician');
    sel.innerHTML = '<option value="">— Unassigned —</option>' + data.technicians.map(t => `<option value="${t.id}">${escapeHtml(t.full_name)}</option>`).join('');
}
function toggleCustType() {
    const isWalkin = document.getElementById('custWalkin').checked;
    document.getElementById('jClientId').classList.toggle('d-none', isWalkin);
    document.getElementById('walkinFields').classList.toggle('d-none', !isWalkin);
}

function openCreateModal() {
    document.getElementById('createForm').reset();
    document.getElementById('custClient').checked = true;
    toggleCustType();
    loadClientOptions();
    loadTechnicianOptions();
    loadBranchOptions();
    new bootstrap.Modal(document.getElementById('createModal')).show();
}
document.getElementById('createForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const branchId = currentBranchId();
    if (!branchId) return alert('Select a branch.');
    const isWalkin = document.getElementById('custWalkin').checked;
    const payload = {
        action: 'create',
        branch_id: branchId,
        client_id: isWalkin ? '' : document.getElementById('jClientId').value,
        walkin_name: isWalkin ? document.getElementById('jWalkinName').value.trim() : '',
        walkin_contact: isWalkin ? document.getElementById('jWalkinContact').value.trim() : '',
        unit_description: document.getElementById('jUnit').value.trim(),
        serial_number: document.getElementById('jSerial').value.trim(),
        problem_description: document.getElementById('jProblem').value.trim(),
        accessories_received: document.getElementById('jAccessories').value.trim(),
        technician_id: document.getElementById('jTechnician').value,
        is_under_warranty: document.getElementById('jWarranty').checked,
        remarks: document.getElementById('jRemarks').value.trim(),
    };
    const res  = await fetch('api/job_orders.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
    loadJobOrders();
});

const STATUS_FLOW = ['received', 'in_diagnosis', 'awaiting_parts', 'repaired'];

async function openManageModal(joNumber) {
    currentJoNumber = joNumber;
    document.getElementById('manageBody').innerHTML = 'Loading…';
    new bootstrap.Modal(document.getElementById('manageModal')).show();
    await renderManage();
}

async function renderManage() {
    const res = await fetch('api/job_orders.php?id=' + encodeURIComponent(currentJoNumber));
    const data = await res.json();
    if (!data.success) { document.getElementById('manageBody').innerHTML = escapeHtml(data.message); return; }
    const jo = data.job_order;
    document.getElementById('manageTitle').textContent = `${jo.jo_number} — ${jo.client_name || 'Walk-in'}`;
    const closed = jo.status === 'released' || jo.status === 'cancelled';

    const catRes = await fetch(`api/job_orders.php?catalog_for=${jo.branch_id}`);
    const catalog = await catRes.json();
    const unitOptions = (catalog.units || []).map(u => `<option value="unit:${u.id}" data-price="${u.default_price || 0}">${escapeHtml(u.item_name)} — SN:${escapeHtml(u.serial_number)}</option>`).join('');
    const stockOptions = (catalog.stock || []).map(s => `<option value="item:${s.item_id}" data-price="${s.default_price || 0}" data-max="${s.quantity_on_hand}">${escapeHtml(s.item_name)} (${s.quantity_on_hand} on hand)</option>`).join('');

    const statusButtons = STATUS_FLOW.map(s => `
        <button class="btn btn-sm ${jo.status === s ? 'btn-primary-app' : 'btn-outline-secondary'}" ${closed ? 'disabled' : ''} onclick="setStatus('${s}')">${statusLabel(s)}</button>
    `).join('');

    document.getElementById('manageBody').innerHTML = `
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="small text-muted">Customer</div><div>${escapeHtml(jo.client_name || 'Walk-in')} ${jo.walkin_contact ? '('+escapeHtml(jo.walkin_contact)+')' : ''}</div>
                <div class="small text-muted mt-2">Unit</div><div>${escapeHtml(jo.unit_description)} ${jo.serial_number ? '— SN:'+escapeHtml(jo.serial_number) : ''}</div>
                <div class="small text-muted mt-2">Problem</div><div>${escapeHtml(jo.problem_description || '—')}</div>
                <div class="small text-muted mt-2">Accessories</div><div>${escapeHtml(jo.accessories_received || '—')}</div>
            </div>
            <div class="col-md-6">
                <div class="small text-muted">Status</div>
                <div class="d-flex flex-wrap gap-1 mb-2">${statusButtons}</div>
                ${jo.sales_invoice ? `<div class="alert alert-info py-2 px-3 small mb-2">Billed as <strong>${escapeHtml(jo.sales_invoice.invoice_number)}</strong> — ${escapeHtml(jo.sales_invoice.status)}, balance ${money(jo.sales_invoice.balance)}</div>` : ''}
                ${jo.status === 'released' ? `<div class="alert alert-success py-2 px-3 small mb-2">Released ${jo.date_released ? new Date(jo.date_released.replace(' ','T')).toLocaleString() : ''}</div>` : ''}
                ${jo.status === 'cancelled' ? `<div class="alert alert-danger py-2 px-3 small mb-2">Cancelled</div>` : ''}
                <div class="d-flex gap-2">
                    ${!closed ? `<button class="btn btn-success btn-sm" ${jo.status !== 'repaired' ? 'disabled title="Mark Repaired first"' : ''} onclick="releaseJo()"><i class="fas fa-right-from-bracket me-1"></i>Release${jo.client_id && jo.grand_total > 0 ? ' & Bill' : ''}</button>` : ''}
                    ${!closed && isAdminTier ? `<button class="btn btn-outline-danger btn-sm" onclick="cancelJo()">Cancel Job Order</button>` : ''}
                </div>
            </div>
        </div>

        <div class="panel mb-3">
            <div class="panel-header"><div class="panel-title">Parts Used</div></div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table table-sm">
                    <thead><tr><th>Part</th><th>Qty</th><th>Price</th><th>Total</th><th></th></tr></thead>
                    <tbody>
                        ${jo.parts.length ? jo.parts.map(p => `
                            <tr>
                                <td>${escapeHtml(p.item_name)}${p.serial_number ? ' — SN:'+escapeHtml(p.serial_number) : ''}</td>
                                <td>${p.quantity || 1}</td>
                                <td>${money(p.unit_price)}</td>
                                <td>${money(p.unit_price * (p.quantity || 1))}</td>
                                <td class="text-end">${!closed ? `<button class="btn-sm-action" onclick="removePart(${p.id})"><i class="fas fa-trash"></i></button>` : ''}</td>
                            </tr>`).join('') : '<tr><td colspan="5" class="text-muted small">No parts added yet.</td></tr>'}
                    </tbody>
                </table>
                </div>
                ${!closed ? `
                <div class="row g-2 align-items-end mt-2">
                    <div class="col-md-5"><label class="form-label small mb-1">Add part</label>
                        <select class="form-select form-select-sm" id="partSelect" onchange="onPartSelect()">
                            <option value="">— Select —</option>
                            ${unitOptions ? `<optgroup label="Serialized">${unitOptions}</optgroup>` : ''}
                            ${stockOptions ? `<optgroup label="Bulk stock">${stockOptions}</optgroup>` : ''}
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label small mb-1">Qty</label><input type="number" min="1" step="1" class="form-control form-control-sm" id="partQty" value="1"></div>
                    <div class="col-md-2"><label class="form-label small mb-1">Price</label><input type="number" min="0" step="0.01" class="form-control form-control-sm" id="partPrice"></div>
                    <div class="col-md-3"><button class="btn btn-primary-app btn-sm w-100" onclick="addPart()"><i class="fas fa-plus me-1"></i>Add</button></div>
                </div>` : ''}
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <label class="form-label small mb-0">Labor Fee</label>
                <input type="number" min="0" step="0.01" class="form-control form-control-sm" style="width:140px" id="laborFeeInput" value="${jo.labor_fee}" ${closed ? 'disabled' : ''}>
                ${!closed ? `<button class="btn btn-sm btn-light" onclick="saveLaborFee()">Save</button>` : ''}
            </div>
            <div class="text-end">
                <div class="small text-muted">Parts: ${money(jo.parts_total)}  +  Labor: ${money(jo.labor_fee)}</div>
                <div class="fw-bold">Grand Total: ${money(jo.grand_total)}</div>
            </div>
        </div>
    `;
}

function onPartSelect() {
    const sel = document.getElementById('partSelect');
    const opt = sel.selectedOptions[0];
    if (!opt) return;
    document.getElementById('partPrice').value = opt.dataset.price || 0;
    const isUnit = sel.value.startsWith('unit:');
    document.getElementById('partQty').disabled = isUnit;
    if (isUnit) document.getElementById('partQty').value = 1;
}

async function jo(action, extra) {
    const res  = await fetch('api/job_orders.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action, jo_number: currentJoNumber, ...extra }) });
    const data = await res.json();
    if (!data.success) alert(data.message);
    return data;
}
async function setStatus(status) { await jo('update_status', { status }); renderManage(); }
async function saveLaborFee() { await jo('set_labor_fee', { labor_fee: document.getElementById('laborFeeInput').value }); renderManage(); }
async function addPart() {
    const sel = document.getElementById('partSelect');
    if (!sel.value) return alert('Select a part first.');
    const [kind, id] = sel.value.split(':');
    const extra = { unit_price: document.getElementById('partPrice').value };
    if (kind === 'unit') extra.inventory_unit_id = id;
    else { extra.item_id = id; extra.quantity = document.getElementById('partQty').value; }
    const data = await jo('add_part', extra);
    if (data.success) renderManage();
}
async function removePart(partId) {
    if (!confirm('Remove this part and restock it?')) return;
    await jo('remove_part', { part_id: partId });
    renderManage();
}
async function releaseJo() {
    if (!confirm('Release this job order? This closes it for edits.')) return;
    const data = await jo('release', {});
    if (data.success) { alert(data.message); renderManage(); loadJobOrders(); }
}
async function cancelJo() {
    if (!confirm('Cancel this job order and restock all parts?')) return;
    const data = await jo('cancel', {});
    if (data.success) { renderManage(); loadJobOrders(); }
}

loadJobOrders();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
