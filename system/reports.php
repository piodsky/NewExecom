<?php
require_once 'config.php';
require_once 'report_defs.php';
requireReportsAdmin();
$currentUser = getCurrentUser();
$activePage  = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Procurement Reports | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="vendor/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
/* ── Per-report accent colour ────────────────────────────────────────────
   Each of the four reports carries its own colour (see 'accent' in
   report_defs.php) so you can tell at a glance which one is on screen —
   the launcher card, the panel icons and the table header all read from
   these variables. Set as a data-attribute on a wrapper rather than a
   class-per-element so adding a fifth report means adding one CSS block,
   not touching every rule. */
[data-accent="blue"]   { --accent: #2563eb; --accent-dark: #1e3a8a; --accent-soft: rgba(37,99,235,.10); }
[data-accent="green"]  { --accent: #16a34a; --accent-dark: #166534; --accent-soft: rgba(22,163,74,.10); }
[data-accent="purple"] { --accent: #7c3aed; --accent-dark: #5b21b6; --accent-soft: rgba(124,58,237,.10); }
[data-accent="amber"]  { --accent: #d97706; --accent-dark: #92400e; --accent-soft: rgba(217,119,6,.12); }

/* ── Report launcher ── stacked full-width cards, mirroring the legacy
   PO_Choices-style picker screen (4 tall boxes in one column). */
.report-launcher-intro { margin-bottom: 1.25rem; }
.report-launcher-card {
    display: flex; align-items: center; gap: 1rem; width: 100%;
    background: #fff; border: 1px solid var(--border); border-left: 4px solid var(--accent);
    border-radius: 12px; padding: 1.1rem 1.4rem; margin-bottom: 1rem; text-align: left;
    box-shadow: var(--card-shadow); transition: border-color .15s, transform .1s, box-shadow .15s;
}
.report-launcher-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(0,0,0,.10); }
.report-launcher-icon {
    width: 48px; height: 48px; border-radius: 12px; flex: 0 0 auto;
    background: var(--accent-soft); color: var(--accent);
    display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
}
.report-launcher-title { font-size: 1.05rem; font-weight: 700; color: var(--text); }
.report-launcher-desc { font-size: .82rem; color: var(--muted); margin-top: .15rem; }
.report-launcher-card .rl-go { margin-left: auto; color: var(--accent); flex: 0 0 auto; }

/* ── Breadcrumb under the page title ── */
.page-breadcrumb { font-size: .78rem; color: var(--muted); margin: 0; }
.page-breadcrumb i { font-size: .6rem; margin: 0 .35rem; vertical-align: middle; }

.report-panel-icon {
    width: 36px; height: 36px; border-radius: 9px; flex: 0 0 auto;
    background: var(--accent-soft); color: var(--accent);
    display: flex; align-items: center; justify-content: center; font-size: 1rem;
}

/* ── Report table ── horizontal scroll (DataTables scrollX) since these
   carry up to 40 columns; every column the legacy report exported is kept
   rather than trimmed to a "nicer" subset, with Column Settings to hide
   what you don't need. Header takes the report's accent colour. */
#reportTable { white-space: nowrap; }
#reportTable td, #reportTable th { max-width: 260px; overflow: hidden; text-overflow: ellipsis; }
/* Scoped to the WRAPPER, not #reportTable: with scrollX, DataTables renders
   the visible header from a cloned table in .dataTables_scrollHead that does
   not carry the original id, so an #reportTable-anchored rule silently misses
   the only header the user actually sees. */
#reportTableWrap thead th {
    background: var(--accent-dark); color: #fff; border-bottom: none;
    text-transform: none; letter-spacing: normal; font-size: .74rem;
}
#reportTable tbody tr:nth-child(even) { background: #fafbfc; }
#reportTable tbody tr:hover { background: var(--accent-soft); }
#reportTable td.num { text-align: right; font-variant-numeric: tabular-nums; }
#reportTable td.is-blank { color: #cbd5e1; }
/* DataTables' sort arrows sit at opacity .125 — invisible on a dark header. */
#reportTableWrap thead th.sorting:before, #reportTableWrap thead th.sorting:after,
#reportTableWrap thead th.sorting_asc:before, #reportTableWrap thead th.sorting_asc:after,
#reportTableWrap thead th.sorting_desc:before, #reportTableWrap thead th.sorting_desc:after { opacity: .5; }
#reportTableWrap thead th.sorting_asc:before, #reportTableWrap thead th.sorting_desc:after { opacity: 1; }
/* Bootstrap 5's form-select needs room for its own caret; DataTables sizes
   this select to the text alone, which clips the "25" behind the arrow. */
#reportTableWrap .dataTables_length select { min-width: 5rem; }

.report-rowcount {
    background: var(--accent-soft); color: var(--accent-dark);
    border-radius: 999px; padding: .18rem .7rem; font-size: .76rem; font-weight: 700; white-space: nowrap;
}

/* ── Empty state ── a plain sibling block next to #reportTable, NOT a
   colspan'd row inside it: DataTables' scrollX/scrollY column-width sync
   crashed the renderer outright when handed a single wide colspan cell as
   the only body row. */
.report-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; padding: 3rem 1.5rem; white-space: normal;
}
.report-empty-icon {
    width: 74px; height: 74px; border-radius: 50%; background: var(--accent-soft); color: var(--accent);
    display: flex; align-items: center; justify-content: center; font-size: 1.7rem; margin-bottom: 1rem;
}
.report-empty-title { font-weight: 700; color: var(--text); margin-bottom: .2rem; }
.report-empty-sub { color: var(--muted); font-size: .88rem; }

#columnSettingsMenu .form-check { padding-left: 1.6rem; margin-bottom: .35rem; }
#columnSettingsMenu .form-check:last-child { margin-bottom: 0; }

/* ── PDF preview ── same official letterhead (.pp-*) as the real Purchase
   Order print in purchase_orders.php, so a report PDF carries the same city
   seal + BAC header a printed PO would. Landscape (the PO print is
   portrait) because these run wide. */
.pp-preview-body { background: #e2e8f0; padding: 24px; min-height: 60vh; }
.pp-offscreen-source { position: absolute; top: 0; left: 0; height: 0; overflow: hidden; }
.pdf-preview-frame { width: 100%; height: 70vh; border: none; background: #fff; }
.print-page {
    font-family: 'Times New Roman', Times, serif; font-size: 11.5px; color: #000;
    background: #fff; width: 1056px; max-width: 100%; margin: 0 auto;
    box-shadow: 0 4px 18px rgba(0,0,0,.18);
}
.pp-frame { border: 1.5px solid #000; margin: 14px; padding: 14px 20px 10px; }
.pp-letterhead { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
.pp-letterhead td { vertical-align: middle; text-align: center; }
.pp-seal { width: 90px; }
.pp-seal img { width: 82px; height: 82px; object-fit: contain; }
.pp-letterhead-text { font-size: 10.5px; line-height: 1.3; color: #1e3a8a; }
.pp-letterhead-text strong { color: #000; }
.pp-letterhead-text a { color: #1d4ed8; text-decoration: none; }
.pp-rule { border: none; border-top: 3px solid #000; margin: 4px 0 8px; }
.pp-title-center { text-align: center; font-size: 16px; font-weight: 700; letter-spacing: .04em; margin: 6px 0 8px; }
.pp-report-meta { text-align: center; font-size: 10.5px; margin-bottom: 8px; }
.pp-report-table { width: 100%; border-collapse: collapse; margin: 6px 0; }
.pp-report-table th, .pp-report-table td { border: 1px solid #000; padding: 3px 5px; font-size: 8.3px; vertical-align: top; }
.pp-report-table th { text-align: center; background: #e5e7eb; }
.pp-report-table td.num { text-align: right; white-space: nowrap; }
.pp-footer { display: flex; justify-content: space-between; align-items: flex-end; font-size: 9px; color: #000; margin-top: 10px; }
.pp-footer-time { color: #333; }

@media print {
    body * { visibility: hidden; }
    #printPreviewArea, #printPreviewArea * { visibility: visible; }
    #printPreviewArea { position: absolute; left: 0; top: 0; width: 100%; }
    .print-page { box-shadow: none; width: 100%; margin: 0; }
}
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
                <div class="page-title" id="pageTitle">Procurement Reports</div>
                <div class="page-breadcrumb" id="pageBreadcrumb">Home <i class="fas fa-chevron-right"></i> Procurement Reports</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <!-- Launcher — the 4 report choices. -->
        <div id="reportLauncher">
            <div class="report-launcher-intro">
                <div class="fw-bold">Choose a report</div>
                <div class="text-muted small">Each report covers a date range you pick, and can be exported to Excel, CSV or PDF.</div>
            </div>
            <div id="reportLauncherCards"></div>
        </div>

        <!-- Report view — hidden until a launcher card is picked. The accent
             colour for the whole view is set on this wrapper by openReport(). -->
        <div id="reportView" class="d-none" data-accent="blue">
            <button type="button" class="btn btn-outline-secondary btn-sm mb-3 no-print" onclick="showLauncher()"><i class="fas fa-arrow-left me-1"></i> Back to Reports</button>

            <div class="panel mb-3 no-print">
                <div class="panel-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="report-panel-icon"><i class="fas fa-filter"></i></span>
                        <div>
                            <div class="fw-bold">Filter Reports</div>
                            <div class="text-muted small">Select a date range, then click Apply Filters to generate this report.</div>
                        </div>
                    </div>
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-3">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" id="filterFrom">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" id="filterTo">
                        </div>
                        <div class="col-sm-auto ms-sm-auto d-flex gap-2">
                            <button class="btn btn-outline-secondary" onclick="resetReportFilters()"><i class="fas fa-rotate-left me-1"></i>Reset</button>
                            <button class="btn btn-primary-app" id="applyFiltersBtn" onclick="loadReport()"><i class="fas fa-magnifying-glass me-1"></i>Apply Filters</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="report-panel-icon"><i class="fas fa-table" id="reportViewIcon"></i></span>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="panel-title" id="reportViewTitle"></span>
                                <span class="report-rowcount d-none" id="reportRowCount"></span>
                            </div>
                            <div class="text-muted small" id="reportViewDesc"></div>
                        </div>
                    </div>
                    <!-- Export options are three plain buttons rather than one
                         dropdown — they are the point of this screen, so they
                         should be reachable in a single click each. -->
                    <div class="d-flex align-items-center gap-2 no-print">
                        <button class="btn btn-outline-success btn-sm" onclick="exportExcel()" title="Download as an Excel workbook (.xlsx)"><i class="fas fa-file-excel me-1"></i>Excel</button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportCSV()" title="Download as a comma-separated file (.csv)"><i class="fas fa-file-csv me-1"></i>CSV</button>
                        <button class="btn btn-outline-danger btn-sm" onclick="openReportPdfPreview()" title="Preview and print as a PDF with the official letterhead"><i class="fas fa-file-pdf me-1"></i>Preview PDF</button>
                        <div class="dropdown">
                            <button class="btn btn-primary-app btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"><i class="fas fa-table-columns me-1"></i>Columns</button>
                            <div class="dropdown-menu dropdown-menu-end p-2" id="columnSettingsMenu" style="max-height:320px; overflow-y:auto; min-width:250px;" onclick="event.stopPropagation()"></div>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="alert alert-warning py-2 px-3 small d-none no-print" id="reportTruncatedNotice">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        <span id="reportTruncatedText"></span>
                    </div>
                    <!-- Only shown when the DTS Location column could not be
                         filled. The report itself still stands; this says why
                         one column is empty rather than leaving people to
                         guess whether the paper really is nowhere. -->
                    <div class="alert alert-secondary py-2 px-3 small d-none no-print" id="reportDtsNotice">
                        <i class="fas fa-circle-info me-1"></i>
                        <span id="reportDtsText"></span>
                    </div>
                    <div id="reportEmptyState" class="report-empty d-none">
                        <div class="report-empty-icon"><i class="fas fa-folder-open"></i></div>
                        <div class="report-empty-title" id="reportEmptyTitle">No records found</div>
                        <div class="report-empty-sub">Try adjusting your date range or filters.</div>
                    </div>
                    <!-- No overflow-x here: DataTables' own scrollX already
                         gives the table a horizontal scrollbar, and wrapping
                         that in a second scrolling box produced two stacked
                         scrollbars (one at the table edge, one under the
                         pagination). The id is kept for CSS scoping and the
                         empty-state toggle. -->
                    <div id="reportTableWrap">
                        <table id="reportTable" class="app-table w-100">
                            <thead><tr id="reportHeadRow"></tr></thead>
                            <tbody id="reportBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Print Preview Modal — same pattern as purchase_orders.php's
     #poPrintPreviewModal: build the printable HTML off-screen into
     #printPreviewArea, snapshot it with html2canvas, lay the image into a
     real PDF with jsPDF (see generateReportPdfPreview()). -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="reportPrintPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header no-print">
        <h5 class="modal-title"><i class="fas fa-file-pdf me-2"></i>Report PDF Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pp-preview-body">
        <div id="printPreviewArea" class="pp-offscreen-source"></div>
        <div id="pdfPreviewLoading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="small mt-2">Generating PDF preview…</div>
        </div>
        <iframe id="pdfPreviewFrame" class="pdf-preview-frame d-none" title="Report PDF preview"></iframe>
      </div>
      <div class="modal-footer no-print">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-secondary" id="pdfOpenTabButton" disabled><i class="fas fa-up-right-from-square me-1"></i> Open in Tab</button>
        <button type="button" class="btn btn-outline-primary" id="pdfDownloadButton" disabled><i class="fas fa-download me-1"></i> Download PDF</button>
        <button type="button" class="btn btn-primary-app" id="pdfPrintButton" disabled><i class="fas fa-print me-1"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script src="vendor/js/html2canvas.min.js"></script>
<script src="vendor/js/jspdf.umd.min.js"></script>
<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/jquery.dataTables.min.js"></script>
<script src="vendor/js/dataTables.bootstrap5.min.js"></script>
<script>
const currentUserName = '<?= e($currentUser['full_name'] ?? 'Someone') ?>';

// Report definitions come from report_defs.php — the same PHP array the
// Excel exporter uses — so the table, CSV, PDF and .xlsx can never disagree
// about which columns exist or what they're called.
const REPORT_TYPES = <?= json_encode(reportDefs(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>;

function showToast(msg,type='success'){const id='t'+Date.now();const bg=type==='success'?'bg-success':type==='warning'?'bg-warning text-dark':'bg-danger';document.getElementById('toastContainer').insertAdjacentHTML('beforeend',`<div id="${id}" class="toast align-items-center text-white ${bg} border-0"><div class="d-flex"><div class="toast-body">${msg}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);const t=new bootstrap.Toast(document.getElementById(id),{delay:4000});t.show();document.getElementById(id).addEventListener('hidden.bs.toast',()=>document.getElementById(id)?.remove());}
function escapeHtml(value){if(value===null||value===undefined)return '';return String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function fmtDateTime(v){if(!v)return '';const d=new Date(String(v).replace(' ','T'));return isNaN(d)?v:d.toLocaleString('en-PH');}
function fmtDate(v){if(!v)return '';const d=new Date(String(v).length>10?v:v+'T00:00:00');return isNaN(d)?v:d.toLocaleDateString('en-PH');}
function fmtCurrency(v){const n=parseFloat(v);return isNaN(n)?'':'₱'+n.toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});}
function fmtNumber(v){const n=parseFloat(v);return isNaN(n)?'':n.toLocaleString('en-PH');}

// Swaps a button into a disabled "working…" state and back, stashing the
// original markup on the element so callers need not know its label/icon.
function setButtonBusy(btn, busy, busyLabel) {
    if (!btn) return;
    if (busy) {
        if (btn.dataset.idleHtml === undefined) btn.dataset.idleHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${busyLabel || 'Working…'}`;
    } else {
        btn.disabled = false;
        if (btn.dataset.idleHtml !== undefined) btn.innerHTML = btn.dataset.idleHtml;
    }
}

// 'fmt' arrives from PHP as a name, not a function — see report_defs.php.
const FORMATTERS = { currency: fmtCurrency, number: fmtNumber, datetime: fmtDateTime };
function isNumericCol(col) { return col.fmt === 'currency' || col.fmt === 'number'; }
function fmtCell(col, raw) {
    const f = col.fmt ? FORMATTERS[col.fmt] : null;
    return f ? f(raw) : (raw ?? '');
}

let dt = null;
let currentRows = [];
let currentType = null;
// Column indexes hidden via the Columns dropdown for the report currently
// open — reset in openReport() so a choice never leaks across report types.
let hiddenColumns = new Set();

// Measures the real gap between the table's top and the bottom of the
// viewport rather than guessing a fixed vh — a fixed guess either
// undershoots on a short window or leaves dead space on a tall one.
function calcReportTableScrollY() {
    const table = document.getElementById('reportTable');
    const top = table.getBoundingClientRect().top;
    // Measured before DataTables wraps the table, so this also has to leave
    // room for the "Show N entries / Search" row it inserts ABOVE the table
    // (~48px) plus the "Showing X of Y / pagination" footer below (~62px,
    // and the scrollX horizontal scrollbar).
    return Math.max(window.innerHeight - top - 175, 200) + 'px';
}

// Separated from loadReport() so a resize can re-measure against the rows
// already on screen without re-fetching. Column visibility is applied at
// init (not toggled after) so hidden columns survive a reload/resize.
function initReportDataTable() {
    if (dt) { dt.destroy(); dt = null; }
    const r = REPORT_TYPES[currentType];
    dt = $('#reportTable').DataTable({
        pageLength: 25, order: [], scrollX: true, scrollY: calcReportTableScrollY(), scrollCollapse: true,
        columns: r.columns.map((c, i) => ({ visible: !hiddenColumns.has(i) })),
        language: { search: '', searchPlaceholder: 'Search this report…' }
    });
}
let resizeDebounce = null;
window.addEventListener('resize', () => {
    if (!currentType || !currentRows.length) return;
    clearTimeout(resizeDebounce);
    resizeDebounce = setTimeout(initReportDataTable, 200);
});

function renderColumnSettings() {
    const r = REPORT_TYPES[currentType];
    document.getElementById('columnSettingsMenu').innerHTML =
        `<div class="d-flex justify-content-between align-items-center px-1 pb-2 mb-2 border-bottom">
            <span class="small fw-bold">Show columns</span>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="showAllColumns()">Show all</button>
         </div>` +
        r.columns.map((c, i) => `
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="colchk-${i}" ${hiddenColumns.has(i) ? '' : 'checked'} onchange="toggleReportColumn(${i}, this.checked)">
            <label class="form-check-label small" for="colchk-${i}">${escapeHtml(c.label)}</label>
        </div>`).join('');
}

function toggleReportColumn(index, visible) {
    if (visible) hiddenColumns.delete(index); else hiddenColumns.add(index);
    if (dt) dt.column(index).visible(visible);
}

function showAllColumns() {
    hiddenColumns.clear();
    renderColumnSettings();
    if (dt) REPORT_TYPES[currentType].columns.forEach((c, i) => dt.column(i).visible(true));
}

function renderLauncher() {
    document.getElementById('reportLauncherCards').innerHTML = Object.keys(REPORT_TYPES).map(key => {
        const r = REPORT_TYPES[key];
        return `<button type="button" class="report-launcher-card" data-accent="${r.accent}" onclick="openReport('${key}')">
            <span class="report-launcher-icon"><i class="fas ${r.icon}"></i></span>
            <span>
                <span class="report-launcher-title d-block">${escapeHtml(r.label)}</span>
                <span class="report-launcher-desc">${escapeHtml(r.desc)}</span>
            </span>
            <i class="fas fa-arrow-right rl-go"></i>
        </button>`;
    }).join('');
}

// Tears down the live DataTable and empties the table.
//
// Order matters and is the whole point of this helper: the reports have
// different column counts (8 to 40), and rewriting <thead>/<tbody> underneath
// a still-live DataTables instance leaves it holding references to cells that
// no longer exist. With scrollX that state is corrupt enough to take the
// browser's renderer down outright — switching between two reports reliably
// crashed the tab before this. Always destroy BEFORE touching the markup, and
// while the table is still visible (DataTables reads layout as it restores).
function destroyReportTable() {
    if (dt) { dt.destroy(); dt = null; }
    document.getElementById('reportBody').innerHTML = '';
    document.getElementById('reportHeadRow').innerHTML = '';
}

function showLauncher() {
    destroyReportTable();
    currentType = null;
    currentRows = [];
    document.getElementById('reportLauncher').classList.remove('d-none');
    document.getElementById('reportView').classList.add('d-none');
    document.getElementById('pageTitle').textContent = 'Procurement Reports';
    document.getElementById('pageBreadcrumb').innerHTML = 'Home <i class="fas fa-chevron-right"></i> Procurement Reports';
}

function defaultReportDateRange() {
    const today = new Date();
    const monthStart = new Date(); monthStart.setDate(1);
    document.getElementById('filterTo').value = today.toISOString().slice(0,10);
    document.getElementById('filterFrom').value = monthStart.toISOString().slice(0,10);
}

function resetReportFilters() {
    defaultReportDateRange();
    loadReport();
}

function openReport(type) {
    // Must come first — see destroyReportTable(). Opening a report while the
    // previous one's table is still live is what crashed the renderer.
    destroyReportTable();
    currentType = type;
    currentRows = [];
    hiddenColumns = new Set();
    const r = REPORT_TYPES[type];
    document.getElementById('reportLauncher').classList.add('d-none');
    const view = document.getElementById('reportView');
    view.classList.remove('d-none');
    view.setAttribute('data-accent', r.accent);
    document.getElementById('pageTitle').textContent = r.label;
    document.getElementById('pageBreadcrumb').innerHTML = `Home <i class="fas fa-chevron-right"></i> Procurement Reports <i class="fas fa-chevron-right"></i> ${escapeHtml(r.label)}`;
    document.getElementById('reportViewTitle').textContent = r.label;
    document.getElementById('reportViewDesc').textContent = r.desc;
    document.getElementById('reportViewIcon').className = 'fas ' + r.icon;
    document.getElementById('reportHeadRow').innerHTML = r.columns.map(c => `<th>${escapeHtml(c.label)}</th>`).join('');
    renderColumnSettings();

    if (!document.getElementById('filterFrom').value) defaultReportDateRange();
    loadReport();
}

async function loadReport() {
    if (!currentType) return;
    const r = REPORT_TYPES[currentType];
    const from = document.getElementById('filterFrom').value;
    const to   = document.getElementById('filterTo').value;
    if (!from || !to) return showToast('Please choose both From and To dates.', 'warning');
    if (from > to)    return showToast('"From Date" must be on or before "To Date".', 'warning');

    const countEl = document.getElementById('reportRowCount');
    countEl.classList.remove('d-none');
    countEl.textContent = 'Loading…';

    // A wide date range on POIAR takes several seconds server-side, so the
    // button has to show it is working — otherwise the page looks frozen and
    // gets clicked again, firing a second query against the live database.
    const applyBtn = document.getElementById('applyFiltersBtn');
    setButtonBusy(applyBtn, true, 'Loading…');

    let res, data;
    try { res = await fetch(`api/reports.php?type=${currentType}&from=${from}&to=${to}`); data = await res.json(); }
    catch (err) { countEl.classList.add('d-none'); showToast('Failed to load report.', 'error'); console.error(err); return; }
    finally { setButtonBusy(applyBtn, false); }
    if (!data.success) { countEl.classList.add('d-none'); return showToast(data.message || 'Failed to load report.', 'error'); }

    currentRows = data.rows;
    countEl.textContent = `${data.rows.length.toLocaleString('en-PH')} record${data.rows.length === 1 ? '' : 's'}`;

    // The API caps rows per report; say so rather than handing back a
    // silently cut-off list that looks complete.
    const notice = document.getElementById('reportTruncatedNotice');
    notice.classList.toggle('d-none', !data.truncated);
    if (data.truncated) {
        document.getElementById('reportTruncatedText').textContent =
            `Showing the first ${data.cap.toLocaleString('en-PH')} records only — there are more in this date range. Narrow the range to see the rest.`;
    }

    const dtsNotice = document.getElementById('reportDtsNotice');
    dtsNotice.classList.toggle('d-none', !data.dts_note);
    if (data.dts_note) document.getElementById('reportDtsText').textContent = data.dts_note;

    if (!data.rows.length) {
        if (dt) { dt.destroy(); dt = null; }
        document.getElementById('reportBody').innerHTML = '';
        document.getElementById('reportTableWrap').classList.add('d-none');
        document.getElementById('reportEmptyTitle').textContent = `No ${r.emptyLabel} records found`;
        document.getElementById('reportEmptyState').classList.remove('d-none');
        return;
    }
    document.getElementById('reportEmptyState').classList.add('d-none');
    document.getElementById('reportTableWrap').classList.remove('d-none');
    document.getElementById('reportBody').innerHTML = data.rows.map(row => {
        return '<tr>' + r.columns.map(c => {
            const val = fmtCell(c, row[c.key]);
            const cls = isNumericCol(c) ? 'num' : '';
            return val === '' || val === null
                ? `<td class="${cls} is-blank">—</td>`
                : `<td class="${cls}">${escapeHtml(val)}</td>`;
        }).join('') + '</tr>';
    }).join('');

    initReportDataTable();
}

// ── Exports ─────────────────────────────────────────────────────────────

// Excel goes through the server (api/reports.php?export=xlsx) rather than
// being built in the browser: it produces a real .xlsx from the same query
// and the same column definitions, with numbers kept as numbers so they
// stay sortable and summable in Excel.
function exportExcel() {
    if (!currentType) return;
    if (!currentRows.length) return showToast('Nothing to export.', 'warning');
    const from = document.getElementById('filterFrom').value;
    const to   = document.getElementById('filterTo').value;
    showToast('Preparing Excel file…');
    window.location = `api/reports.php?type=${currentType}&from=${from}&to=${to}&export=xlsx`;
}

function exportCSV() {
    if (!currentType) return;
    if (!currentRows.length) return showToast('Nothing to export.', 'warning');
    const r = REPORT_TYPES[currentType];
    const lines = [r.columns.map(c => `"${c.label}"`).join(',')];
    currentRows.forEach(row => {
        lines.push(r.columns.map(c => {
            const val = fmtCell(c, row[c.key]);
            return `"${String(val).replace(/"/g,'""')}"`;
        }).join(','));
    });
    // BOM so Excel opens the file as UTF-8 — without it the peso sign and
    // any accented supplier names come out as mojibake on a double-click.
    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `${currentType}-report-${document.getElementById('filterFrom').value}_to_${document.getElementById('filterTo').value}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

// ── PDF Preview ─────────────────────────────────────────────────────────
// Same official letterhead as the actual Purchase Order print (city seal +
// BAC header, see purchase_orders.php's buildPrintPreviewHtml()) wrapped
// around this report's current filtered rows.
function buildReportPreviewHtml(type, rows, from, to) {
    const r = REPORT_TYPES[type];
    const now = new Date();
    const printedDate = now.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
    const printedTime = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' }).toLowerCase().replace(/ /g, '');
    const rangeLabel = `${fmtDate(from)} &ndash; ${fmtDate(to)}`;

    // The PDF honours the Columns dropdown too — hiding a column on screen
    // and then printing should not bring it back.
    const cols = r.columns.filter((c, i) => !hiddenColumns.has(i));

    const headRow = '<tr>' + cols.map(c => `<th>${escapeHtml(c.label)}</th>`).join('') + '</tr>';
    const bodyRows = rows.map(row => '<tr>' + cols.map(c => {
        const val = fmtCell(c, row[c.key]);
        return `<td class="${isNumericCol(c) ? 'num' : ''}">${escapeHtml(val)}</td>`;
    }).join('') + '</tr>').join('')
        || `<tr><td colspan="${cols.length}" style="text-align:center;font-style:italic;">***no records in this range***</td></tr>`;

    return `
    <div class="print-page">
      <div class="pp-frame">
        <table class="pp-letterhead">
            <tr>
                <td class="pp-seal"><img src="image/citylogo.png" alt=""></td>
                <td class="pp-letterhead-text">
                    Republic of the Philippines<br>
                    Province of Bukidnon<br>
                    <strong>City of Malaybalay</strong><br>
                    <strong>Bids and Awards Committee</strong><br>
                    New City Hall, Barangay Casisang<br>
                    Malaybalay City, Bukidnon<br>
                    Tel (088) 813-476 1-2<br>
                    <a href="#">bacmalaybalay@gmail.com</a>
                </td>
                <td class="pp-seal"><img src="image/City%20BAC.png" alt=""></td>
            </tr>
        </table>
        <hr class="pp-rule">
        <div class="pp-title-center">${escapeHtml(r.pdfTitle)}</div>
        <div class="pp-report-meta">Date Range: ${rangeLabel} &nbsp;&bull;&nbsp; ${rows.length} record${rows.length===1?'':'s'}</div>

        <table class="pp-report-table">
            <thead>${headRow}</thead>
            <tbody>${bodyRows}</tbody>
        </table>

        <div class="pp-footer">
            <span>Generated by ${escapeHtml(currentUserName)}</span>
            <span class="pp-footer-time">Printed ${printedDate} ${printedTime}</span>
        </div>
      </div>
    </div>`;
}

function openReportPdfPreview() {
    if (!currentType) return;
    if (!currentRows.length) return showToast('Nothing to preview.', 'warning');
    const from = document.getElementById('filterFrom').value;
    const to   = document.getElementById('filterTo').value;
    document.getElementById('printPreviewArea').innerHTML = buildReportPreviewHtml(currentType, currentRows, from, to);
    openModal('reportPrintPreviewModal');
    const modalEl = document.getElementById('reportPrintPreviewModal');
    modalEl.addEventListener('shown.bs.modal', () => generateReportPdfPreview(currentType), { once: true });
}

function openModal(modalId) {
    const modalEl = document.getElementById(modalId);
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    return modal;
}

let currentPdfBlobUrl = null;
let currentPdfFileName = 'report.pdf';

// ── Row-aware page slicing ──────────────────────────────────────────────
// The printable page is rasterised into one tall canvas, then cut into pages.
// Cutting at a fixed page-height interval slices straight through whichever
// table row straddles the boundary, leaving half a row of text at the foot of
// one page and its other half at the top of the next. These cut at row edges.

// Y offsets (canvas px) where a cut is safe: the top of every row/major block.
function collectSafePageBreaks(source, canvas) {
    const pxScale = canvas.height / source.offsetHeight;
    const srcTop = source.getBoundingClientRect().top;
    const offsets = [];
    source.querySelectorAll('tr, .pp-title-center, .pp-report-meta, .pp-footer').forEach(el => {
        const top = el.getBoundingClientRect().top - srcTop;
        if (top > 0) offsets.push(Math.round(top * pxScale));
    });
    offsets.push(canvas.height);
    return [...new Set(offsets)].sort((a, b) => a - b);
}

// Draws the canvas into the PDF a page at a time, ending each page on the
// last safe break that still fits.
// Safe printable inset, in points. 18pt = 0.25in (6.35mm), comfortably
// outside the ~4-6mm non-printable band virtually every office printer has.
const PDF_PAGE_MARGIN_PT = 18;

// Paper size — keep in step with PDF_PAGE_FORMAT in purchase_orders.php, which
// carries the full explanation of why this is A4 and not Letter.
const PDF_PAGE_FORMAT = 'a4';

function addCanvasToPdfPaged(pdf, canvas, safeBreaks) {
    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();
    // Printers cannot mark the outermost few millimetres of a sheet. Drawing
    // the canvas edge-to-edge left the form frame only ~3.7mm in, inside that
    // non-printable band, so the top and right borders vanished on real
    // hardware even though they were present in the on-screen preview.
    // Insetting by PDF_PAGE_MARGIN_PT keeps the whole frame printable.
    const contentW = pageW - PDF_PAGE_MARGIN_PT * 2;
    const contentH = pageH - PDF_PAGE_MARGIN_PT * 2;
    const pxPerPt = canvas.width / contentW;    // canvas px per PDF point
    const maxSlicePx = Math.floor(contentH * pxPerPt);
    const MIN_SLICE_PX = 60;

    let y = 0;
    let firstPage = true;
    while (y < canvas.height) {
        let end = Math.min(y + maxSlicePx, canvas.height);
        if (end < canvas.height) {
            // If one row is taller than a whole page there is nothing to snap
            // to, so fall back to the hard cut rather than looping forever.
            const fit = safeBreaks.filter(b => b > y + MIN_SLICE_PX && b <= end).pop();
            if (fit) end = fit;
        }
        const sliceH = end - y;
        const slice = document.createElement('canvas');
        slice.width = canvas.width;
        slice.height = sliceH;
        const ctx = slice.getContext('2d');
        ctx.fillStyle = '#ffffff';   // JPEG has no alpha; unpainted area would go black
        ctx.fillRect(0, 0, slice.width, slice.height);
        ctx.drawImage(canvas, 0, y, canvas.width, sliceH, 0, 0, canvas.width, sliceH);

        if (!firstPage) pdf.addPage();
        pdf.addImage(slice.toDataURL('image/jpeg', 0.95), 'JPEG', PDF_PAGE_MARGIN_PT, PDF_PAGE_MARGIN_PT, contentW, sliceH / pxPerPt);
        firstPage = false;
        y = end;
    }
}

// Snapshots #printPreviewArea (html2canvas) into a real landscape-Letter PDF
// (jsPDF), sliced across as many pages as the table needs — same mechanism
// as purchase_orders.php's generatePdfPreview(), landscape because these
// reports run much wider than a PO print.
async function generateReportPdfPreview(type) {
    const loading = document.getElementById('pdfPreviewLoading');
    const frame = document.getElementById('pdfPreviewFrame');
    const downloadBtn = document.getElementById('pdfDownloadButton');
    const printBtn = document.getElementById('pdfPrintButton');
    const openTabBtn = document.getElementById('pdfOpenTabButton');

    frame.classList.add('d-none');
    downloadBtn.disabled = true;
    printBtn.disabled = true;
    if (openTabBtn) openTabBtn.disabled = true;
    loading.classList.remove('d-none');
    loading.innerHTML = '<div class="spinner-border text-primary" role="status"></div><div class="small mt-2">Generating PDF preview…</div>';

    try {
        const source = document.querySelector('#printPreviewArea .print-page');
        if (!source) throw new Error('Print content did not render — try closing and reopening the preview.');

        const MAX_CANVAS_PX = 14000;
        const scale = Math.min(2, MAX_CANVAS_PX / Math.max(source.offsetHeight, 1));

        const canvas = await html2canvas(source, {
            scale, backgroundColor: '#ffffff', useCORS: true, logging: false, imageTimeout: 15000
        });
        if (!canvas.width || !canvas.height) {
            throw new Error('Rendered page came out empty (0x0) — the print content may not have laid out correctly.');
        }

        // Probe exportability before slicing, so a tainted/oversized canvas
        // fails here with a clear message rather than part-way through.
        try {
            canvas.toDataURL('image/jpeg', 0.95);
        } catch (e) {
            throw new Error('Could not export the rendered page as an image (' + e.message + ').');
        }

        const pdf = new jspdf.jsPDF({ unit: 'pt', format: PDF_PAGE_FORMAT, orientation: 'landscape' });
        addCanvasToPdfPaged(pdf, canvas, collectSafePageBreaks(source, canvas));

        const blob = pdf.output('blob');
        if (currentPdfBlobUrl) URL.revokeObjectURL(currentPdfBlobUrl);
        currentPdfBlobUrl = URL.createObjectURL(blob);
        currentPdfFileName = `${type}-report-${document.getElementById('filterFrom').value}_to_${document.getElementById('filterTo').value}.pdf`;

        frame.src = currentPdfBlobUrl;
        loading.classList.add('d-none');
        frame.classList.remove('d-none');
        downloadBtn.disabled = false;
        printBtn.disabled = false;
        if (openTabBtn) openTabBtn.disabled = false;
    } catch (err) {
        loading.innerHTML = '<div class="text-danger small px-3"><i class="fas fa-triangle-exclamation me-1"></i>Unable to generate PDF preview: '
            + escapeHtml(err.message || String(err)) + '</div>';
        console.error(err);
    }
}

function downloadPdfPreview() {
    if (!currentPdfBlobUrl) return;
    const a = document.createElement('a');
    a.href = currentPdfBlobUrl;
    a.download = currentPdfFileName;
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
        window.open(currentPdfBlobUrl, '_blank');
    }
}

document.getElementById('pdfDownloadButton').addEventListener('click', downloadPdfPreview);
document.getElementById('pdfPrintButton').addEventListener('click', printPdfPreview);
document.getElementById('pdfOpenTabButton').addEventListener('click', () => { if (currentPdfBlobUrl) window.open(currentPdfBlobUrl, '_blank'); });

renderLauncher();
</script>
<?php include 'profile_modal.php'; ?>
<?php include 'assistant.php'; ?>
</body>
</html>
