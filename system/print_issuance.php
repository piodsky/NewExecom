<?php
// Printable receipt/issue-slip for a posted Issuance, shown as a real PDF
// preview (html2canvas -> jsPDF, via the shared js/print-pdf.js pipeline)
// rather than a plain "print this HTML page" view — same pattern as the
// legacy Purchase Order print preview this project was cloned from.
require_once 'config.php';
requireAuth();
requireModule('issuance');

$user  = getCurrentUser();
$scope = getUserBranchScope($user);
$db    = getDB();

$issuanceNumber = trim($_GET['id'] ?? '');
$stmt = $db->prepare(
    'SELECT iss.*, b.name AS branch_name, b.address AS branch_address,
            COALESCE(c.name, iss.walkin_name) AS client_name,
            COALESCE(c.address, iss.walkin_address) AS client_address,
            u.full_name AS issued_by_name, sm.name AS salesman_name
     FROM issuances iss
     JOIN branches b ON b.id = iss.branch_id
     LEFT JOIN clients c ON c.id = iss.client_id
     LEFT JOIN salesmen sm ON sm.id = iss.salesman_id
     JOIN users u ON u.id = iss.issued_by
     WHERE iss.issuance_number = ? AND iss.is_deleted = 0'
);
$stmt->execute([$issuanceNumber]);
$iss = $stmt->fetch();
if (!$iss) { http_response_code(404); die('Issuance not found.'); }
if ($scope !== null && (int)$iss['branch_id'] !== $scope) { http_response_code(403); die('Access denied.'); }

$itemStmt = $db->prepare(
    "SELECT ii.*, COALESCE(i1.name, i2.name) AS item_name, COALESCE(i1.unit, i2.unit) AS unit, iu.serial_number
     FROM issuance_items ii
     LEFT JOIN items i1 ON i1.id = ii.item_id
     LEFT JOIN inventory_units iu ON iu.id = ii.inventory_unit_id
     LEFT JOIN items i2 ON i2.id = iu.item_id
     WHERE ii.issuance_number = ? AND ii.is_deleted = 0 ORDER BY ii.id"
);
$itemStmt->execute([$issuanceNumber]);
$items = $itemStmt->fetchAll();

$peso = fn($n) => number_format((float)$n, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Issuance <?= e($iss['issuance_number']) ?> | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
html, body { height: 100%; }
/* Presented as a full-screen modal-style dialog — a dim backdrop with a
   centered card — even though this is its own page (reached by navigation
   from the POS "Issue & Print" flow and the issuance list's Print button),
   rather than a plain print stylesheet page. */
body { background: #0b1220; margin: 0; display: flex; align-items: center; justify-content: center; padding: 20px; }

.pv-card {
    background: #fff; border-radius: 16px; overflow: hidden;
    box-shadow: 0 30px 70px rgba(0,0,0,.45);
    width: min(1100px, 100%); height: min(92vh, 880px);
    display: flex; flex-direction: column;
}

.pv-header {
    flex: 0 0 auto; background: linear-gradient(135deg, var(--navy), #1e40af); color: #fff;
    padding: 1.1rem 1.5rem; display: flex; align-items: center; gap: 1rem;
}
.pv-header-icon {
    width: 46px; height: 46px; border-radius: 14px; flex: 0 0 auto;
    background: rgba(255,255,255,.18); display: flex; align-items: center; justify-content: center; font-size: 1.15rem;
}
.pv-header-text { min-width: 0; }
.pv-header-text h1 { font-size: 1.05rem; font-weight: 800; margin: 0; }
.pv-header-text small { display: block; opacity: .85; font-size: .78rem; margin-top: .1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pv-header-close {
    margin-left: auto; flex-shrink: 0; width: 34px; height: 34px; border-radius: 50%;
    background: rgba(255,255,255,.15); color: #fff; text-decoration: none;
    display: flex; align-items: center; justify-content: center; transition: background .15s;
}
.pv-header-close:hover { background: rgba(255,255,255,.28); color: #fff; }

.pv-toolbar {
    flex: 0 0 auto; background: #fff; border-bottom: 1px solid var(--border);
    padding: .65rem 1.25rem; display: flex; align-items: center; flex-wrap: wrap; gap: 1rem;
}
.pv-tool { display: flex; align-items: center; gap: .5rem; }
.pv-tool label { margin: 0; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); white-space: nowrap; }
.pv-tool select { width: auto; min-width: 12rem; font-size: .82rem; border: 1.5px solid #d1d5db; border-radius: 8px; padding: .3rem .6rem; }
.pv-layout-status { margin-left: auto; font-size: .8rem; color: #334155; display: flex; align-items: center; gap: .35rem; }
.pv-layout-status .pv-sheets { font-weight: 700; color: var(--navy); }
.pv-layout-status .pv-note { color: #b45309; }

.pv-footer {
    flex: 0 0 auto; background: #fff; border-top: 1px solid var(--border);
    padding: .85rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem;
}
.pv-footer-actions { display: flex; gap: .5rem; }

.pv-body { flex: 1 1 auto; min-height: 0; padding: 20px; position: relative; background: #e2e8f0; overflow: auto; }
/* Clipped to zero visible height rather than pushed off-screen with a large
   negative offset — html2canvas's bounding-box math can misfire on elements
   positioned far outside the viewport and produce a 0x0 canvas. The source
   keeps its own natural offsetHeight regardless of the parent's
   overflow:hidden, so html2canvas still measures and captures it correctly. */
.pp-offscreen-source { position: absolute; top: 0; left: 0; height: 0; overflow: hidden; }
.pdf-preview-frame { width: 100%; height: 100%; min-height: 78vh; border: none; background: #fff; display: block; }

/* ── The printable form itself — this is what gets rasterised, not shown
   directly. Scoped under .print-page so it never leaks into the toolbar. ── */
.print-page { font-family: Arial, Helvetica, sans-serif; color: #1e293b; background: #fff; width: 816px; max-width: 100%; margin: 0 auto; padding: 28px; font-size: 13px; }
.print-page h1 { text-align: center; font-size: 1.5rem; margin: .5rem 0 1.25rem; letter-spacing: .05em; }
.print-page .row { display: flex; justify-content: space-between; margin-bottom: .4rem; flex-wrap: wrap; gap: .5rem; }
.print-page table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
.print-page th, .print-page td { border: 1px solid #94a3b8; padding: .4rem .5rem; font-size: .85rem; }
.print-page th { background: #eef2ff; }
.print-page .total-row td { font-weight: 700; background: #fef9c3; }
.print-page .sign-block { display: flex; justify-content: space-between; margin-top: 3rem; gap: 2rem; }
.print-page .sign-col { flex: 1; }
.print-page .sign-line { border-top: 1px solid #1e293b; margin-top: 2.5rem; padding-top: .2rem; text-align: center; }
</style>
<link rel="stylesheet" href="css/report-preview.css">
<script>
const embeddedPreview = window.parent !== window && new URLSearchParams(location.search).get('embed') === '1';
if (embeddedPreview) {
    document.documentElement.classList.add('report-embedded');
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') window.parent.postMessage({ type: 'report-preview-close' }, location.origin);
    });
}
</script>
</head>
<body>

<div class="pv-card">
    <div class="pv-header">
        <div class="pv-header-icon"><i class="fas fa-print"></i></div>
        <div class="pv-header-text">
            <h1>Print Preview</h1>
            <small>Issuance Slip &mdash; <?= e($iss['issuance_number']) ?></small>
        </div>
        <a href="issuances.php" class="pv-header-close" title="Close"><i class="fas fa-times"></i></a>
    </div>

    <div class="pv-toolbar">
        <div class="pv-tool">
            <label for="pdfPaperSize"><i class="fas fa-file-lines me-1"></i>Paper</label>
            <select id="pdfPaperSize" onchange="generatePreview()">
                <option value="auto">Auto &mdash; smallest sheet that fits</option>
                <option value="a4">A4 &middot; 8.27 &times; 11.69 in</option>
                <option value="long">Long / Folio &middot; 8.5 &times; 13 in</option>
                <option value="legal">Legal &middot; 8.5 &times; 14 in</option>
                <option value="letter">Letter &middot; 8.5 &times; 11 in</option>
            </select>
        </div>
        <div class="pv-layout-status" id="pdfLayoutStatus"></div>
    </div>

    <div class="pv-body">
    <div id="printSource" class="pp-offscreen-source">
        <div class="print-page">
            <?php include 'print_letterhead.php'; ?>
            <h1>ISSUANCE SLIP</h1>

            <div class="row">
                <div><strong>Issuance No.:</strong> <?= e($iss['issuance_number']) ?></div>
                <div><strong>Date:</strong> <?= $iss['date_created'] ? date('m/d/Y', strtotime($iss['date_created'])) : '—' ?></div>
            </div>
            <div class="row">
                <div><strong>Branch:</strong> <?= e($iss['branch_name']) ?></div>
                <div><strong>Issued By:</strong> <?= e($iss['issued_by_name']) ?></div>
            </div>
            <div><strong>Client:</strong> <?= e($iss['client_name'] ?? 'Walk-in / Retail') ?></div>
            <?php if ($iss['client_address']): ?><div><strong>Address:</strong> <?= e($iss['client_address']) ?></div><?php endif; ?>
            <?php if ($iss['walkin_contact']): ?><div><strong>Contact:</strong> <?= e($iss['walkin_contact']) ?></div><?php endif; ?>
            <?php if ($iss['po_number']): ?><div><strong>Fulfilling PO:</strong> <?= e($iss['po_number']) ?></div><?php endif; ?>
            <?php if ($iss['salesman_name']): ?><div><strong>Salesman:</strong> <?= e($iss['salesman_name']) ?></div><?php endif; ?>

            <table>
                <thead><tr><th>Item</th><th>Serial No.</th><th>Qty</th><th>Unit</th><th>Unit Price</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['item_name']) ?></td>
                        <td><?= e($it['serial_number'] ?? '—') ?></td>
                        <td><?= number_format((float)($it['quantity'] ?? 1), 2) ?></td>
                        <td><?= e($it['unit'] ?? '') ?></td>
                        <td><?= $peso($it['unit_price']) ?></td>
                        <td><?= $peso($it['total_price']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ((float)$iss['discount_amount'] > 0): ?>
                    <tr><td colspan="5" style="text-align:right">Subtotal</td><td><?= $peso((float)$iss['total_amount'] + (float)$iss['discount_amount']) ?></td></tr>
                    <tr><td colspan="5" style="text-align:right">Discount</td><td>-<?= $peso($iss['discount_amount']) ?></td></tr>
                <?php endif; ?>
                    <tr class="total-row"><td colspan="5" style="text-align:right">TOTAL</td><td><?= $peso($iss['total_amount']) ?></td></tr>
                </tbody>
            </table>

            <div class="sign-block">
                <div class="sign-col">
                    <div class="sign-line">Issued By / <?= e($iss['issued_by_name']) ?></div>
                </div>
                <div class="sign-col">
                    <div class="sign-line">Received By / Printed Name &amp; Signature</div>
                </div>
            </div>
        </div>
    </div>

        <div id="pdfPreviewLoading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="small mt-2">Generating PDF preview…</div>
        </div>
        <iframe id="pdfPreviewFrame" class="pdf-preview-frame d-none" title="Issuance PDF preview"></iframe>
    </div>

    <div class="pv-footer">
        <a href="issuances.php" class="btn btn-secondary btn-sm">Close</a>
        <div class="pv-footer-actions">
            <button type="button" class="btn btn-outline-primary btn-sm" id="pdfDownloadButton" disabled onclick="downloadPdfPreview()"><i class="fas fa-download me-1"></i>Download PDF</button>
            <button type="button" class="btn btn-primary-app btn-sm" id="pdfPrintButton" disabled onclick="printPdfPreview()"><i class="fas fa-print me-1"></i>Print</button>
        </div>
    </div>
</div>

<script src="vendor/js/html2canvas.min.js"></script>
<script src="vendor/js/jspdf.umd.min.js"></script>
<script src="js/print-pdf.js"></script>
<script>
// Chrome/Firefox/Edge accept these viewer hints on a blob PDF URL; harmless
// where a browser's own PDF viewer ignores them (the fragment is just dropped).
const PDF_VIEW_PARAMS = '#navpanes=0&toolbar=1&view=FitH';
const fileBaseName = 'Issuance_<?= e($iss['issuance_number']) ?>';
let currentPdfBlobUrl = null;

function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function showLayoutStatus(layout) {
    const el = document.getElementById('pdfLayoutStatus');
    if (!layout) { el.innerHTML = ''; return; }
    const d = PrintPdf.describeLayout(layout);
    el.innerHTML = '<span class="pv-sheets">' + d.sheetsLabel + ' &middot; ' + escapeHtml(d.paper) + '</span>'
        + (d.note ? ' <span class="pv-note">(' + escapeHtml(d.note) + ')</span>' : '');
}

// Renders #printSource (via html2canvas) into a real PDF (via jsPDF) using the
// shared pipeline in js/print-pdf.js, then shows it in the iframe below — an
// actual PDF file the browser's own viewer displays/prints/downloads, not
// just an HTML page dressed up to look like one.
async function generatePreview() {
    const loading    = document.getElementById('pdfPreviewLoading');
    const frame      = document.getElementById('pdfPreviewFrame');
    const downloadBtn = document.getElementById('pdfDownloadButton');
    const printBtn    = document.getElementById('pdfPrintButton');
    const paperSel    = document.getElementById('pdfPaperSize');

    frame.classList.add('d-none');
    downloadBtn.disabled = true; printBtn.disabled = true;
    paperSel.disabled = true;
    loading.classList.remove('d-none');
    loading.innerHTML = '<div class="spinner-border text-primary" role="status"></div><div class="small mt-2">Generating PDF preview…</div>';
    showLayoutStatus(null);

    try {
        const source = document.querySelector('#printSource .print-page');
        const { blob, layout } = await PrintPdf.renderToPdf(source, { paper: paperSel.value, gridSelector: 'table' });
        showLayoutStatus(layout);

        if (currentPdfBlobUrl) URL.revokeObjectURL(currentPdfBlobUrl);
        currentPdfBlobUrl = URL.createObjectURL(blob);
        frame.src = currentPdfBlobUrl + PDF_VIEW_PARAMS;

        loading.classList.add('d-none');
        frame.classList.remove('d-none');
        downloadBtn.disabled = false; printBtn.disabled = false;
    } catch (err) {
        loading.innerHTML = '<div class="text-danger small px-3"><i class="fas fa-triangle-exclamation me-1"></i>Unable to generate PDF preview: '
            + escapeHtml(err.message || String(err)) + '</div>';
        console.error(err);
    } finally {
        paperSel.disabled = false;
    }
}

function downloadPdfPreview() {
    if (!currentPdfBlobUrl) return;
    const a = document.createElement('a');
    a.href = currentPdfBlobUrl;
    a.download = fileBaseName + '.pdf';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function printPdfPreview() {
    const frame = document.getElementById('pdfPreviewFrame');
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch (err) {
        document.getElementById('pdfLayoutStatus').textContent = 'Use the print icon in the PDF preview toolbar.';
    }
}

window.addEventListener('pagehide', () => {
    if (currentPdfBlobUrl) URL.revokeObjectURL(currentPdfBlobUrl);
});
generatePreview();
</script>
</body>
</html>
