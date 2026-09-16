<?php
require_once 'config.php';
requireModule('internal_rfq');
$currentUser = getCurrentUser();
$activePage  = 'internal_rfq';
$isAdminTier = in_array($currentUser['role'], ['super_admin', 'branch_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RFQ (Internal) | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.open      { background: #fef3c7; color: #92400e; }
.badge-status.converted { background: #dbeafe; color: #1e40af; }
.badge-status.cancelled { background: #fee2e2; color: #991b1b; }
.pr-item-row { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: .5rem; margin-bottom: .5rem; align-items: center; }
.quote-row { display: grid; grid-template-columns: 1.5fr 1fr 1fr auto; gap: .5rem; margin-bottom: .35rem; align-items: center; }
.quote-pick { font-size: .78rem; }
.quote-pick.selected { background: #dcfce7 !important; }
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
                <div class="page-title">RFQ (Internal)</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> RFQ (Internal)</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <div class="panel-title">Canvass to EXECOM's Suppliers</div>
                <?php if ($isAdminTier): ?>
                <button class="btn btn-primary-app btn-sm" onclick="openRfqModal()"><i class="fas fa-plus me-1"></i>New RFQ</button>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>RFQ Number</th><th>Branch</th><th>From PR</th><th>Sent Date</th><th>Created By</th><th>Status</th><th></th></tr></thead>
                    <tbody id="rfqBody"><tr><td colspan="7" class="text-muted small">Loading…</td></tr></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- New RFQ modal -->
<div class="modal fade" id="rfqModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">New RFQ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="rfqForm">
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">From an approved requisition (optional)</label>
            <select class="form-select" id="rfqFromPr" onchange="onPrPicked()"><option value="">— None, add items manually —</option></select>
          </div>
          <div class="mb-2"><label class="form-label">Sent Date</label><input type="date" class="form-control" id="rfqSentDate"></div>
          <div id="rfqManualItemsWrap">
              <label class="form-label">Items</label>
              <div id="rfqItemRows"></div>
              <button type="button" class="btn btn-light btn-sm" onclick="addRfqItemRow()"><i class="fas fa-plus me-1"></i>Add Item</button>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-app">Create RFQ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View/Manage RFQ modal -->
<div class="modal fade" id="rfqViewModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="rfqViewTitle">RFQ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="rfqViewBody"></div>
      <div class="modal-footer" id="rfqViewFooter"></div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isAdminTier = <?= $isAdminTier ? 'true' : 'false' ?>;
let rfqCache = [], suppliersCache = [];
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const statusLabel = { open: 'Open', converted: 'Converted', cancelled: 'Cancelled' };

async function loadRfqs() {
    const res  = await fetch('api/internal_rfq.php');
    const data = await res.json();
    if (!data.success) return alert(data.message);
    rfqCache = data.rfqs;
    document.getElementById('rfqBody').innerHTML = rfqCache.length ? rfqCache.map(r => `
        <tr style="cursor:pointer" onclick="viewRfq('${r.rfq_number}')">
            <td><strong>${escapeHtml(r.rfq_number)}</strong></td>
            <td>${escapeHtml(r.branch_name)}</td>
            <td>${escapeHtml(r.pr_number || '—')}</td>
            <td>${r.sent_date || '—'}</td>
            <td>${escapeHtml(r.created_by_name)}</td>
            <td><span class="badge-status ${r.status}">${statusLabel[r.status] || r.status}</span></td>
            <td class="text-end"><i class="fas fa-chevron-right text-muted"></i></td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-muted small">No RFQs yet.</td></tr>';
}

async function openRfqModal() {
    document.getElementById('rfqForm').reset();
    document.getElementById('rfqItemRows').innerHTML = '';
    addRfqItemRow();
    const res  = await fetch('api/internal_rfq.php?approved_prs=1');
    const data = await res.json();
    document.getElementById('rfqFromPr').innerHTML = '<option value="">— None, add items manually —</option>' +
        (data.success ? data.prs.map(p => `<option value="${p.pr_number}">${p.pr_number} — ${escapeHtml(p.purpose || '')}</option>`).join('') : '');
    new bootstrap.Modal(document.getElementById('rfqModal')).show();
}
function onPrPicked() {
    document.getElementById('rfqManualItemsWrap').style.display = document.getElementById('rfqFromPr').value ? 'none' : '';
}

function addRfqItemRow(desc = '', qty = '', unit = '') {
    const wrap = document.createElement('div');
    wrap.className = 'pr-item-row';
    wrap.innerHTML = `
        <input type="text" class="form-control form-control-sm rfq-desc" placeholder="Description" value="${escapeHtml(desc)}">
        <input type="number" step="0.01" class="form-control form-control-sm rfq-qty" placeholder="Qty" value="${escapeHtml(qty)}">
        <input type="text" class="form-control form-control-sm rfq-unit" placeholder="Unit" value="${escapeHtml(unit)}">
        <button type="button" class="btn btn-sm btn-light" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>`;
    document.getElementById('rfqItemRows').appendChild(wrap);
}

document.getElementById('rfqForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const prNumber = document.getElementById('rfqFromPr').value;
    const payload = { action: 'create', pr_number: prNumber || undefined, sent_date: document.getElementById('rfqSentDate').value };
    if (!prNumber) {
        const items = [...document.querySelectorAll('#rfqItemRows .pr-item-row')].map(row => ({
            description: row.querySelector('.rfq-desc').value.trim(),
            quantity: row.querySelector('.rfq-qty').value,
            unit: row.querySelector('.rfq-unit').value.trim(),
        })).filter(i => i.description && i.quantity);
        if (!items.length) return alert('Add at least one item with a description and quantity.');
        payload.items = items;
    }
    const res  = await fetch('api/internal_rfq.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('rfqModal')).hide();
    loadRfqs();
});

async function viewRfq(rfqNumber) {
    const res  = await fetch('api/internal_rfq.php?id=' + encodeURIComponent(rfqNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    const rfq = data.rfq;
    suppliersCache = data.suppliers;

    document.getElementById('rfqViewTitle').textContent = rfq.rfq_number + ' — ' + statusLabel[rfq.status];
    const supplierOpts = suppliersCache.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');

    document.getElementById('rfqViewBody').innerHTML = rfq.items.map(item => `
        <div class="panel mb-3">
            <div class="panel-body">
                <div class="fw-bold mb-1">${escapeHtml(item.item_name || item.description)} <span class="text-muted small">(${item.quantity} ${escapeHtml(item.unit || '')})</span></div>
                <div id="quotes-${item.id}">
                    ${item.quotes.map(q => `
                        <div class="quote-row quote-pick ${q.is_selected ? 'selected' : ''}" id="quote-${q.id}">
                            <span>${escapeHtml(q.supplier_name)}</span>
                            <span>₱${Number(q.quoted_price).toLocaleString(undefined,{minimumFractionDigits:2})}</span>
                            <span>${q.lead_time_days ? q.lead_time_days + ' days' : '—'}</span>
                            ${isAdminTier && rfq.status === 'open' ? `<button class="btn btn-sm ${q.is_selected ? 'btn-success' : 'btn-outline-success'}" onclick="selectQuote('${rfq.rfq_number}', ${q.id})">${q.is_selected ? 'Winner' : 'Pick'}</button>` : (q.is_selected ? '<span class="badge-status approved">Winner</span>' : '<span></span>')}
                        </div>`).join('') || '<div class="text-muted small mb-2">No quotes yet.</div>'}
                </div>
                ${isAdminTier && rfq.status === 'open' ? `
                <div class="quote-row mt-2">
                    <select class="form-select form-select-sm" id="qSupplier-${item.id}"><option value="">Supplier…</option>${supplierOpts}</select>
                    <input type="number" step="0.01" class="form-control form-control-sm" id="qPrice-${item.id}" placeholder="Price">
                    <input type="number" class="form-control form-control-sm" id="qLead-${item.id}" placeholder="Lead days">
                    <button class="btn btn-sm btn-light" onclick="addQuote('${rfq.rfq_number}', ${item.id})"><i class="fas fa-plus"></i></button>
                </div>` : ''}
            </div>
        </div>`).join('');

    let footer = '';
    if (isAdminTier && rfq.status === 'open') {
        footer = `<button class="btn btn-danger btn-sm me-auto" onclick="rfqAction('${rfq.rfq_number}','cancel')">Cancel RFQ</button>
                   <button class="btn btn-primary-app btn-sm" onclick="rfqAction('${rfq.rfq_number}','convert_to_po')">Convert Winning Quotes to PO</button>`;
    }
    document.getElementById('rfqViewFooter').innerHTML = footer || '<span class="text-muted small">No actions available.</span>';
    new bootstrap.Modal(document.getElementById('rfqViewModal')).show();
}

async function addQuote(rfqNumber, itemId) {
    const payload = {
        action: 'quote', rfq_number: rfqNumber, rfq_item_id: itemId,
        supplier_id: document.getElementById(`qSupplier-${itemId}`).value,
        quoted_price: document.getElementById(`qPrice-${itemId}`).value,
        lead_time_days: document.getElementById(`qLead-${itemId}`).value,
    };
    const res  = await fetch('api/internal_rfq.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    viewRfq(rfqNumber);
}
async function selectQuote(rfqNumber, quoteId) {
    const res  = await fetch('api/internal_rfq.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'select_quote', rfq_number: rfqNumber, quote_id: quoteId }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    viewRfq(rfqNumber);
}
async function rfqAction(rfqNumber, action) {
    if (action === 'convert_to_po' && !confirm('Convert all selected winning quotes into Internal Purchase Order(s)? This cannot be undone.')) return;
    if (action === 'cancel' && !confirm('Cancel this RFQ?')) return;
    const res  = await fetch('api/internal_rfq.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action, rfq_number: rfqNumber }) });
    const data = await res.json();
    if (!data.success) return alert(data.message);
    alert(data.message);
    bootstrap.Modal.getInstance(document.getElementById('rfqViewModal')).hide();
    loadRfqs();
}

loadRfqs();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
