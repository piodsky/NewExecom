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
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.active   { background: #dcfce7; color: #166534; }
.badge-status.inactive { background: #fee2e2; color: #991b1b; }
.badge-status.business { background: #dbeafe; color: #1e40af; }
.badge-status.walkin   { background: #ede9fe; color: #5b21b6; }
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
                    <h2 class="page-title">Clients</h2>
                    <div class="text-muted small">Maintain client records used for outgoing RFQs, quotations, and deliveries.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="panel-title">Clients &amp; Buyers</div>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="cliTypeFilter" style="width:170px" onchange="loadClients()">
                        <option value="all">All types</option>
                        <option value="business">Business / Government</option>
                        <option value="walkin">Walk-in buyers</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" id="cliSearch" placeholder="Search…" style="width:200px" onkeyup="if(event.key==='Enter')loadClients()">
                    <?php if ($isAdminTier): ?>
                    <button class="btn btn-primary-app btn-sm" onclick="openClientModal()"><i class="fas fa-plus me-1"></i>New Client</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel-body">
                <p class="text-muted small mb-2">Business/Government clients are the formal end-clients used on RFQ, Quotation, and PO documents. Walk-in buyers are registered automatically the first time their name is used on an Issuance (POS) sale.</p>
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Name</th><th>Type</th><th>Address</th><th>Contact Person</th><th>Number</th><th>Terms</th><th>Credit Limit</th><th>Status</th><th></th></tr></thead>
                    <tbody id="cliBody"><tr><td colspan="9" class="text-muted small">Loading…</td></tr></tbody>
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
          <div class="mb-2"><label class="form-label">Type</label>
              <select class="form-select" id="cType">
                  <option value="business">Business / Government</option>
                  <option value="walkin">Walk-in buyer</option>
              </select>
          </div>
          <div class="mb-2"><label class="form-label">Address</label><input type="text" class="form-control" id="cAddress"></div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Contact Person</label><input type="text" class="form-control" id="cContactPerson"></div>
            <div class="col-6"><label class="form-label">Contact Number</label><input type="text" class="form-control" id="cContactNumber"></div>
            <div class="col-6"><label class="form-label">Terms (days)</label><input type="number" min="0" class="form-control" id="cTermsDays" placeholder="e.g. 30"></div>
            <div class="col-6"><label class="form-label">Credit Limit</label><input type="number" min="0" step="0.01" class="form-control" id="cCreditLimit" value="0"></div>
            <div class="col-12"><label class="form-label">Default Salesman</label><select class="form-select" id="cSalesmanId"><option value="">—</option></select></div>
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
let cliCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function loadClients() {
    const q    = document.getElementById('cliSearch').value.trim();
    const type = document.getElementById('cliTypeFilter').value;
    const res  = await fetch('api/clients.php?q=' + encodeURIComponent(q) + '&type=' + encodeURIComponent(type));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    cliCache = data.clients;
    document.getElementById('cliBody').innerHTML = cliCache.length ? cliCache.map(c => `
        <tr>
            <td>${escapeHtml(c.name)}</td>
            <td><span class="badge-status ${c.client_type}">${c.client_type === 'walkin' ? 'Walk-in' : 'Business'}</span></td>
            <td>${escapeHtml(c.address || '—')}</td>
            <td>${escapeHtml(c.contact_person || '—')}</td>
            <td>${escapeHtml(c.contact_number || '—')}</td>
            <td>${c.payment_terms_days ? `Net ${c.payment_terms_days}` : '—'}</td>
            <td>${Number(c.credit_limit) > 0 ? Number(c.credit_limit).toLocaleString(undefined, {minimumFractionDigits:2}) : '—'}</td>
            <td><span class="badge-status ${c.is_active ? 'active' : 'inactive'}">${c.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-end">${isAdminTier ? `
                <button class="btn-sm-action" onclick="editClient(${c.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="btn-sm-action" onclick="toggleClient(${c.id})" title="Toggle"><i class="fas fa-power-off"></i></button>` : ''}</td>
        </tr>`).join('') : '<tr><td colspan="9" class="text-muted small">No clients found.</td></tr>';
}

async function loadSalesmanOptions(selectedId) {
    const res = await fetch('api/salesmen.php');
    const data = await res.json();
    if (!data.success) return;
    document.getElementById('cSalesmanId').innerHTML = '<option value="">—</option>' +
        data.salesmen.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
    document.getElementById('cSalesmanId').value = selectedId || '';
}

function openClientModal() {
    document.getElementById('clientForm').reset();
    document.getElementById('cId').value = '';
    document.getElementById('cType').value = 'business';
    loadSalesmanOptions(null);
    document.getElementById('clientModalTitle').textContent = 'New Client';
    new bootstrap.Modal(document.getElementById('clientModal')).show();
}
function editClient(id) {
    const c = cliCache.find(x => x.id === id);
    if (!c) return;
    document.getElementById('cId').value = c.id;
    document.getElementById('cName').value = c.name;
    document.getElementById('cType').value = c.client_type || 'business';
    document.getElementById('cAddress').value = c.address || '';
    document.getElementById('cContactPerson').value = c.contact_person || '';
    document.getElementById('cContactNumber').value = c.contact_number || '';
    document.getElementById('cTermsDays').value = c.payment_terms_days ?? '';
    document.getElementById('cCreditLimit').value = c.credit_limit ?? 0;
    loadSalesmanOptions(c.default_salesman_id);
    document.getElementById('clientModalTitle').textContent = 'Edit Client';
    new bootstrap.Modal(document.getElementById('clientModal')).show();
}
document.getElementById('clientForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('cId').value;
    const payload = {
        action: id ? 'update' : 'create', id: id || undefined,
        name: document.getElementById('cName').value.trim(),
        client_type: document.getElementById('cType').value,
        address: document.getElementById('cAddress').value.trim(),
        contact_person: document.getElementById('cContactPerson').value.trim(),
        contact_number: document.getElementById('cContactNumber').value.trim(),
        payment_terms_days: document.getElementById('cTermsDays').value,
        credit_limit: document.getElementById('cCreditLimit').value || 0,
        default_salesman_id: document.getElementById('cSalesmanId').value,
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
