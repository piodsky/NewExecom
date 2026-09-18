<?php
require_once 'config.php';
requireModule('issuance');
$currentUser = getCurrentUser();
$activePage  = 'issuances';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
$isSuperAdmin = $currentUser['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Issuance | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
</head>
<body>
<div class="wrapper">
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <div class="page-title">Issuance</div>
                <div class="small text-muted">Issue inventory to users or departments and reduce branch stock.</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Issuance</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Sales / Stock Issuance</div>
                <a href="issuance_pos.php" class="btn btn-primary-app btn-sm"><i class="fas fa-cash-register me-1"></i>New Issuance (POS)</a>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Issuance #</th><th>Branch</th><th>Client</th><th>Total</th><th>Issued By</th><th>Date</th><th></th></tr></thead>
                    <tbody id="issBody"><tr><td colspan="7" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- View Issuance modal -->
<div class="modal fade" id="issViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="issViewTitle">Issuance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="issViewBody"></div>
      <div class="modal-footer" id="issViewActions"></div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isAdminTier  = <?= $isAdminTier ? 'true' : 'false' ?>;
let issCache = [];

function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2});

// A registered walk-in buyer is tagged so it's not mistaken for a formal
// client on record — same "(Walk-in)" convention as the POS dropdown. An
// issuance with no name at all (predates this feature) just says "Walk-in".
function clientLabel(i) {
    if (!i.client_name) return 'Walk-in';
    return escapeHtml(i.client_name) + (i.client_type === 'walkin' ? ' <span class="text-muted small">(Walk-in)</span>' : '');
}

async function loadIssuances() {
    const res  = await fetch('api/issuances.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    issCache = data.issuances;
    document.getElementById('issBody').innerHTML = issCache.length ? issCache.map(i => `
        <tr style="cursor:pointer" onclick="viewIssuance('${i.issuance_number}')">
            <td><span class="doc-number doc-iss">${escapeHtml(i.issuance_number)}</span></td>
            <td>${escapeHtml(i.branch_name)}</td>
            <td>${clientLabel(i)}</td>
            <td>${peso(i.total_amount)}</td>
            <td>${escapeHtml(i.issued_by_name)}</td>
            <td>${AppDateTime.format(i.date_created)}</td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No issuances yet.</td></tr>';
}

async function viewIssuance(issuanceNumber) {
    const res  = await fetch('api/issuances.php?id=' + encodeURIComponent(issuanceNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const iss = data.issuance;
    document.getElementById('issViewTitle').textContent = iss.issuance_number;
    document.getElementById('issViewBody').innerHTML = `
        <div class="row g-2 mb-3">
            <div class="col-6"><strong>Branch:</strong> ${escapeHtml(iss.branch_name)}</div>
            <div class="col-6"><strong>Client:</strong> ${clientLabel(iss)}</div>
            <div class="col-6"><strong>Issued By:</strong> ${escapeHtml(iss.issued_by_name)}</div>
            <div class="col-6"><strong>Date &amp; Time:</strong> ${AppDateTime.format(iss.date_created)}</div>
            ${iss.po_number ? `<div class="col-6"><strong>Fulfilling PO:</strong> ${escapeHtml(iss.po_number)}</div>` : ''}
            ${iss.client_address ? `<div class="col-6"><strong>Address:</strong> ${escapeHtml(iss.client_address)}</div>` : ''}
            ${iss.walkin_contact ? `<div class="col-6"><strong>Contact:</strong> ${escapeHtml(iss.walkin_contact)}</div>` : ''}
        </div>
        <table class="app-table table">
            <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
            <tbody>${iss.items.map(i => `<tr><td>${escapeHtml(i.item_name)}${i.serial_number ? ' (SN: '+escapeHtml(i.serial_number)+')' : ''}</td><td>${i.quantity}</td><td>${peso(i.unit_price)}</td><td>${peso(i.total_price)}</td></tr>`).join('')}</tbody>
            <tfoot>
                ${Number(iss.discount_amount) > 0 ? `
                <tr><th colspan="3" class="text-end fw-normal text-muted">Subtotal</th><th class="fw-normal text-muted">${peso(Number(iss.total_amount) + Number(iss.discount_amount))}</th></tr>
                <tr><th colspan="3" class="text-end fw-normal text-muted">Discount</th><th class="fw-normal text-muted">-${peso(iss.discount_amount)}</th></tr>` : ''}
                <tr><th colspan="3" class="text-end">Total</th><th>${peso(iss.total_amount)}</th></tr>
            </tfoot>
        </table>`;
    document.getElementById('issViewActions').innerHTML =
        `<a class="btn btn-light btn-sm me-auto" href="print_issuance.php?id=${encodeURIComponent(iss.issuance_number)}" data-report-modal><i class="fas fa-print me-1"></i>Print</a>` +
        (isAdminTier ? `<button class="btn btn-outline-danger btn-sm" onclick="voidIssuance('${iss.issuance_number}')">Void</button>` : '');
    new bootstrap.Modal(document.getElementById('issViewModal')).show();
}

async function voidIssuance(issuanceNumber) {
    if (!confirm('Void this issuance and restore the stock?')) return;
    const res  = await fetch('api/issuances.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'void', issuance_number: issuanceNumber }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('issViewModal')).hide();
    loadIssuances();
}

loadIssuances();

// Deep link from Order Tracking (order_tracking.php?view=... lands here as ?view=...).
const _viewIssuance = new URLSearchParams(location.search).get('view');
if (_viewIssuance) viewIssuance(_viewIssuance);
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
