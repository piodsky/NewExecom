<?php
require_once 'config.php';
requireModule('order_tracking');
$currentUser = getCurrentUser();
$activePage  = 'order_tracking';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Tracking | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.badge-status.draft { background: #f1f5f9; color: #475569; }
.badge-status.open, .badge-status.sent, .badge-status.quoted, .badge-status.active                        { background: #dbeafe; color: #1e40af; }
.badge-status.pending, .badge-status.partially_received, .badge-status.partially_delivered                { background: #fef3c7; color: #92400e; }
.badge-status.approved, .badge-status.received, .badge-status.delivered, .badge-status.completed,
.badge-status.converted                                                                                    { background: #dcfce7; color: #166534; }
.badge-status.rejected, .badge-status.cancelled                                                             { background: #fee2e2; color: #991b1b; }
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
                    <h2 class="page-title">Order Tracking</h2>
                    <div class="text-muted small">Follow one order's full journey, from request to completion.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><button class="nav-link active" id="tabIntBtn" onclick="showTab('int')">Internal Chain</button></li>
            <li class="nav-item"><button class="nav-link" id="tabOutBtn" onclick="showTab('out')">Outgoing Chain</button></li>
        </ul>

        <div id="tabInt">
            <div class="panel mb-3">
                <div class="panel-body d-flex align-items-center gap-2 flex-wrap">
                    <label class="form-label mb-0 fw-bold">Purchase Order:</label>
                    <select class="form-select" id="intRootSelect" style="max-width:420px" onchange="trackInternal(this.value)">
                        <option value="">Select a purchase order…</option>
                    </select>
                </div>
            </div>
            <div id="intTimeline">
                <div class="panel"><div class="panel-body text-muted small">Pick an internal Purchase Order above to see everything that happened to it — receiving included.</div></div>
            </div>
        </div>

        <div id="tabOut" style="display:none">
            <div class="panel mb-3">
                <div class="panel-body d-flex align-items-center gap-2 flex-wrap">
                    <label class="form-label mb-0 fw-bold">Client RFQ:</label>
                    <select class="form-select" id="outRootSelect" style="max-width:420px" onchange="trackOutgoing(this.value)">
                        <option value="">Select an RFQ…</option>
                    </select>
                </div>
            </div>
            <div id="outTimeline">
                <div class="panel"><div class="panel-body text-muted small">Pick a client RFQ above to see everything that happened to it — quotation, purchase order, delivery, and issuance.</div></div>
            </div>
        </div>
    </div>
</div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<script>
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
const statusLabels = {
    draft: 'Draft', pending: 'Pending Approval', approved: 'Approved', rejected: 'Rejected', converted: 'Converted',
    open: 'Open', sent: 'Sent', partially_received: 'Partially Received', received: 'Received', cancelled: 'Cancelled',
    quoted: 'Quoted', active: 'Active', partially_delivered: 'Partially Delivered', delivered: 'Delivered', completed: 'Completed',
};
function docLink(page, number, cssClass) {
    return `<a href="${page}?view=${encodeURIComponent(number)}" class="doc-number ${cssClass}" title="Open in ${page.replace('.php','')}">${escapeHtml(number)}</a>`;
}
function statusBadge(status) {
    if (!status) return '';
    const label = statusLabels[status] || status;
    return `<span class="badge-status ${escapeHtml(status)}">${escapeHtml(label)}</span>`;
}
function fmtDate(iso) {
    if (!iso) return '—';
    return (window.AppDateTime && AppDateTime.format) ? AppDateTime.format(iso) : String(iso);
}

function showTab(tab) {
    document.getElementById('tabInt').style.display = tab === 'int' ? '' : 'none';
    document.getElementById('tabOut').style.display = tab === 'out' ? '' : 'none';
    document.getElementById('tabIntBtn').classList.toggle('active', tab === 'int');
    document.getElementById('tabOutBtn').classList.toggle('active', tab === 'out');
}

function progressBar(moved, ordered) {
    const pct = ordered > 0 ? Math.min(100, Math.round(moved / ordered * 100)) : 0;
    return `<div>
        <div class="track-progress-track"><div class="track-progress-fill" style="width:${pct}%"></div></div>
        <div class="text-muted" style="font-size:.72rem;margin-top:.15rem">${Number(moved).toLocaleString()} / ${Number(ordered).toLocaleString()} units</div>
    </div>`;
}

function trackStage(iconClass, colorClass, title, cardsHtml, emptyText) {
    return `
        <div class="track-stage">
            <div class="track-stage-marker ${cardsHtml ? colorClass : 'empty'}"><i class="fas ${iconClass}"></i></div>
            <div class="track-stage-title">${escapeHtml(title)}</div>
            ${cardsHtml || `<div class="track-empty">${escapeHtml(emptyText)}</div>`}
        </div>`;
}

async function loadInternalRoots() {
    const res = await fetch('api/order_tracking.php?type=internal');
    const data = await res.json();
    if (!data.success) return;
    document.getElementById('intRootSelect').innerHTML = '<option value="">Select a purchase order…</option>' +
        data.roots.map(r => `<option value="${escapeHtml(r.po_number)}">${escapeHtml(r.po_number)} — ${escapeHtml(r.supplier_name || 'Supplier not set')} (${escapeHtml(statusLabels[r.status] || r.status)})</option>`).join('');
}

async function trackInternal(poNumber) {
    const box = document.getElementById('intTimeline');
    if (!poNumber) {
        box.innerHTML = '<div class="panel"><div class="panel-body text-muted small">Pick an internal Purchase Order above to see everything that happened to it — receiving included.</div></div>';
        return;
    }
    box.innerHTML = '<div class="panel"><div class="panel-body text-muted small">Loading…</div></div>';
    const res  = await fetch('api/order_tracking.php?type=internal&root=' + encodeURIComponent(poNumber));
    const data = await res.json();
    if (!data.success) { box.innerHTML = `<div class="panel"><div class="panel-body text-danger small">${escapeHtml(data.message)}</div></div>`; return; }
    box.innerHTML = renderInternalChain(data.chain);
}

function renderInternalChain(chain) {
    const po = chain.root;

    const rrCards = chain.receiving_reports.map(r => `
        <div class="track-doc-card">
            <div>${docLink('receiving_reports.php', r.rr_number, 'doc-rr')} <span class="text-muted small ms-2">Received ${fmtDate(r.date_received)}</span></div>
        </div>`).join('');

    return `
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">${docLink('po_internal.php', po.po_number, 'doc-poi')}</div>
            ${statusBadge(po.status)}
        </div>
        <div class="panel-body">
            <div class="row g-2 mb-4">
                <div class="col-6"><strong>Branch:</strong> ${escapeHtml(po.branch_name)}</div>
                <div class="col-6"><strong>Supplier:</strong> ${escapeHtml(po.supplier_name || '—')}</div>
                <div class="col-6"><strong>Created By:</strong> ${escapeHtml(po.created_by_name)}</div>
                <div class="col-6"><strong>Total:</strong> ${peso(po.total_amount)}</div>
            </div>
            ${progressBar(po.received_qty, po.ordered_qty)}
            <div class="track-line mt-3">
                ${trackStage('fa-truck-ramp-box', 'doc-rr', 'Receiving Report', rrCards, 'Nothing received yet.')}
            </div>
        </div>
    </div>`;
}

async function loadOutgoingRoots() {
    const res = await fetch('api/order_tracking.php?type=outgoing');
    const data = await res.json();
    if (!data.success) return;
    document.getElementById('outRootSelect').innerHTML = '<option value="">Select an RFQ…</option>' +
        data.roots.map(r => `<option value="${escapeHtml(r.rfq_number)}">${escapeHtml(r.rfq_number)} — ${escapeHtml(r.client_name || 'Walk-in')} (${escapeHtml(statusLabels[r.status] || r.status)})</option>`).join('');
}

async function trackOutgoing(rfqNumber) {
    const box = document.getElementById('outTimeline');
    if (!rfqNumber) {
        box.innerHTML = '<div class="panel"><div class="panel-body text-muted small">Pick a client RFQ above to see everything that happened to it — quotation, purchase order, delivery, and issuance.</div></div>';
        return;
    }
    box.innerHTML = '<div class="panel"><div class="panel-body text-muted small">Loading…</div></div>';
    const res  = await fetch('api/order_tracking.php?type=outgoing&root=' + encodeURIComponent(rfqNumber));
    const data = await res.json();
    if (!data.success) { box.innerHTML = `<div class="panel"><div class="panel-body text-danger small">${escapeHtml(data.message)}</div></div>`; return; }
    box.innerHTML = renderOutgoingChain(data.chain);
}

function renderOutgoingChain(chain) {
    const rfq = chain.root;

    const qCards = chain.quotations.map(q => `
        <div class="track-doc-card">
            <div>${docLink('outgoing_quotations.php', q.quotation_number, 'doc-oq')}</div>
            <div class="text-end">${statusBadge(q.status)} <span class="fw-bold small ms-2">${peso(q.total_amount)}</span></div>
        </div>`).join('');

    const poCards = chain.purchase_orders.map(p => `
        <div class="track-doc-card">
            <div>
                <div>${docLink('po_outgoing.php', p.po_number, 'doc-poo')}</div>
                ${progressBar(p.delivered_qty, p.ordered_qty)}
            </div>
            <div class="text-end">
                ${statusBadge(p.status)}
                <div class="fw-bold small mt-1">${peso(p.total_amount)}</div>
            </div>
        </div>`).join('');

    const drCards = chain.delivery_reports.map(d => `
        <div class="track-doc-card">
            <div>${docLink('delivery_reports.php', d.dr_number, 'doc-dr')} <span class="text-muted small ms-2">Delivered ${fmtDate(d.date_delivered)}</span></div>
            ${d.date_accepted ? '<span class="badge-status completed">Accepted</span>' : '<span class="badge-status pending">Awaiting Acceptance</span>'}
        </div>`).join('');

    const issCards = chain.issuances.map(i => `
        <div class="track-doc-card">
            <div>${docLink('issuances.php', i.issuance_number, 'doc-iss')} <span class="text-muted small ms-2">${fmtDate(i.date_created)}</span></div>
            <span class="fw-bold small">${peso(i.total_amount)}</span>
        </div>`).join('');

    return `
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">${docLink('outgoing_rfq.php', rfq.rfq_number, 'doc-orfq')}</div>
            ${statusBadge(rfq.status)}
        </div>
        <div class="panel-body">
            <div class="row g-2 mb-4">
                <div class="col-6"><strong>Branch:</strong> ${escapeHtml(rfq.branch_name)}</div>
                <div class="col-6"><strong>Client:</strong> ${escapeHtml(rfq.client_name || '—')}</div>
                <div class="col-6"><strong>Received:</strong> ${fmtDate(rfq.received_date)}</div>
                <div class="col-6"><strong>Due:</strong> ${fmtDate(rfq.due_date)}</div>
            </div>
            <div class="track-line">
                ${trackStage('fa-file-invoice-dollar', 'doc-oq', 'Quotation (Outgoing)', qCards, 'No quotation sent yet.')}
                ${trackStage('fa-file-contract', 'doc-poo', 'Purchase Order (Outgoing)', poCards, 'No client purchase order yet.')}
                ${trackStage('fa-truck', 'doc-dr', 'Delivery Report', drCards, 'Nothing delivered yet.')}
                ${trackStage('fa-cash-register', 'doc-iss', 'Issuance (fulfilled via POS)', issCards, 'Not fulfilled through Issuance.')}
            </div>
        </div>
    </div>`;
}

loadInternalRoots();
loadOutgoingRoots();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
