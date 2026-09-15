<?php
require_once 'config.php';
requireAuth();
requireModule('inventory');
$currentUser = getCurrentUser();
$activePage  = 'inventory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory &amp; Property | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="vendor/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?= asset('css/base.css') ?>">
<link rel="stylesheet" href="<?= asset('css/inventory-print.css') ?>">
<style>
/* Same card/field/chip vocabulary as Inspection & Acceptance and Purchase
   Orders, copied rather than shared, matching how those two pages already
   do it — each screen in this app is self-contained. Only what this page
   adds beyond that vocabulary is defined past the shared block below. */
.table-actions { display: flex; gap: .35rem; flex-wrap: wrap; }
.po-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem 1.4rem; margin-bottom: 1.1rem; }
.po-card-compact { padding: .95rem 1.15rem; }
.po-card-title { display: flex; align-items: center; gap: .55rem; font-weight: 700; color: var(--navy); font-size: 1rem; margin-bottom: 1.1rem; }
.po-card-compact .po-card-title { margin-bottom: .65rem; font-size: .92rem; }
.po-card-title i {
    width: 26px; height: 26px; border-radius: 8px; flex: 0 0 auto;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .8rem; background: #e8f0ff; color: var(--primary);
}
.po-card.sec-order   .po-card-title i { background: #e8f0ff; color: #1d4ed8; }
.po-card.sec-receipt .po-card-title i { background: #fff2e6; color: #c2410c; }
.po-card.sec-people  .po-card-title i { background: #eef7ee; color: #15803d; }
.po-card.sec-order   { border-top: 3px solid #bfdbfe; }
.po-card.sec-receipt { border-top: 3px solid #fed7aa; }
.po-card.sec-people  { border-top: 3px solid #bbf7d0; }

.po-field-group { margin-bottom: 1rem; }
.tight-field-row .po-field-group { margin-bottom: .6rem; }
.po-field-group > label { font-size: .8rem; font-weight: 600; color: var(--muted); margin-bottom: .3rem; display: block; }
.po-input-wrap { position: relative; }
.po-input-wrap > .po-input-icon {
    position: absolute; left: 5px; top: 50%; transform: translateY(-50%);
    width: 28px; height: 28px; border-radius: 8px; background: rgba(37,99,235,.1); color: var(--primary);
    display: flex; align-items: center; justify-content: center; font-size: .78rem; pointer-events: none;
}
.po-input-wrap > .form-control, .po-input-wrap > .form-select {
    padding: .4rem .6rem .4rem 40px; min-height: 38px; font-size: .87rem;
}
.po-card-compact .po-input-wrap > .form-control,
.po-card-compact .po-input-wrap > .form-select { padding: .3rem .5rem .3rem 32px; min-height: 32px; font-size: .8rem; }
.po-card-compact .po-input-wrap > .po-input-icon { width: 22px; height: 22px; font-size: .68rem; }
.po-field-hint { font-size: .72rem; color: var(--muted); margin-top: .25rem; line-height: 1.35; }
.po-field-hint:empty { display: none; }
.po-field-hint-warn { color: #92400e; }

.po-chip {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .3rem .7rem; border-radius: 20px;
    font-size: .75rem; font-weight: 600; white-space: nowrap;
    border: 1px solid var(--border); background: #fff; color: var(--muted);
    cursor: pointer; transition: background .15s, border-color .15s, color .15s;
}
.po-chip:hover { background: #f8fafc; }
.po-chip-num { font-weight: 700; color: var(--text); font-variant-numeric: tabular-nums; }
.po-chip.active { background: #eff6ff; border-color: var(--primary); color: var(--primary); }
.po-chip.active .po-chip-num { color: var(--primary); }

/* Same sizing/colors as the PO/PR pills in purchase_orders.php's own list —
   a PO number carried over into Inventory should read as the identical
   identifier, not a smaller lookalike. */
.num-pill {
    display: inline-block; padding: .18rem .55rem; border-radius: 6px;
    font-size: .8rem; font-weight: 700; letter-spacing: .01em;
    border: 1px solid transparent; white-space: nowrap;
}
.po-num-pill { color: #c2410c; background: #fff2e6; border-color: #fed7aa; }
.pr-num-pill { color: #1d4ed8; background: #e8f0ff; border-color: #bfdbfe; }
/* A PO can cite several PRs — one pill each, same as purchase_orders.php. */
.pr-num-group { display: inline-flex; flex-wrap: wrap; gap: .25rem; }

.status-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 700; padding: .18rem .55rem; border-radius: 20px; }
.status-issued   { background: #dcfce7; color: #15803d; }
.status-awaiting  { background: #fef3c7; color: #b45309; }
/* Legacy property entered directly, with no purchase order behind it — see
   btnAddManually in the script below. Purple rather than the amber/green
   Awaiting/Issued pair: this is provenance, not a workflow state, and must
   not read as a third status option in that same traffic-light language. */
.status-manual   { background: #ede9fe; color: #6d28d9; }
.status-partial   { background: #e0e7ff; color: #4338ca; }

.readonly-banner {
    display: flex; align-items: center; gap: .6rem; padding: .6rem .9rem; border-radius: 10px;
    background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: .82rem; margin-bottom: 1rem;
}
.po-summary-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: .8rem; }
.po-summary-strip .cell {
    background: linear-gradient(180deg, #f8fbff, #f1f5f9);
    border: 1px solid #dbe3ee; border-left: 3px solid #93b4f5;
    border-radius: 10px; padding: .55rem .75rem;
}
.po-summary-strip .cell .v .num-pill { font-size: .8rem; }
.po-summary-strip .cell .k { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }
.po-summary-strip .cell .v { font-size: .87rem; font-weight: 600; color: var(--text); word-break: break-word; }

.insp-modal-header {
    background: linear-gradient(135deg, var(--navy), #1e40af);
    color: #fff; border-bottom: none; padding: 1.15rem 1.4rem;
    display: flex; align-items: center; gap: 1rem;
}
.insp-header-icon {
    width: 46px; height: 46px; border-radius: 14px; flex: 0 0 auto;
    background: rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #fff;
}
.insp-header-text { flex: 1 1 auto; min-width: 0; }
.insp-header-text .modal-title { color: #fff; font-weight: 700; font-size: 1.15rem; margin: 0; }
.insp-header-text small { display: block; color: rgba(255,255,255,.72); font-size: .8rem; margin-top: .1rem; }
.insp-header-close {
    width: 38px; height: 38px; flex: 0 0 auto; border-radius: 12px;
    border: 1px solid rgba(255,255,255,.3); background: rgba(255,255,255,.12); color: #fff;
    display: flex; align-items: center; justify-content: center; transition: background .15s;
}
.insp-header-close:hover { background: rgba(255,255,255,.25); color: #fff; }
.modal-insp-wide { max-width: min(1600px, 96vw); }

/* ── Item grid ──────────────────────────────────────────────────────────── */
.insp-items-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.insp-items-table th {
    background: linear-gradient(180deg, #eaf1fd, #dfe9fa);
    font-size: .68rem; text-transform: uppercase; letter-spacing: .03em;
    color: var(--navy); font-weight: 700;
    padding: .45rem .5rem; text-align: left; white-space: nowrap;
    border-bottom: 1px solid #c7d8f5; position: sticky; top: 0; z-index: 1;
}
.insp-items-table tbody tr:nth-child(even) { background: #fafcff; }
.insp-items-table tbody tr:hover { background: #eef4fe; }
.insp-items-table td { padding: .22rem .32rem; border-bottom: 1px solid #eef2f7; vertical-align: top; }
.insp-items-table input, .insp-items-table select {
    width: 100%; border: 1px solid transparent; background: transparent;
    padding: .28rem .35rem; border-radius: 6px; font-size: .8rem;
}
/* Every free-text column in this grid grows the same way Description does —
   .item-desc keeps its own name (the title-row bold/navy override below
   still targets it specifically), .inv-grow is the shared class for the rest
   (Unit, Tag No., Serial No., Accountable Officer, Position, Location,
   Classification, Condition, Remarks). */
.insp-items-table textarea.item-desc,
.insp-items-table textarea.inv-grow {
    width: 100%; border: 1px solid transparent; background: transparent;
    padding: .28rem .35rem; border-radius: 6px; font-size: .8rem;
    resize: none; overflow: hidden; line-height: 1.35; display: block; min-height: 26px; font-family: inherit;
}
.insp-items-table input:focus, .insp-items-table select:focus,
.insp-items-table textarea.item-desc:focus,
.insp-items-table textarea.inv-grow:focus { border-color: var(--primary); background: #fff; outline: none; }
.insp-items-table input[readonly] { color: var(--muted); }
.insp-items-table td.num input { text-align: right; font-variant-numeric: tabular-nums; }
.insp-items-scroll { overflow-y: auto; min-height: 0; border: 1px solid #cddcf7; border-radius: 10px; }

/* Shared floating suggestion box for the grid's textarea columns that used to
   carry a native <datalist> (Accountable Officer, Position, Location,
   Classification, Condition) — a <textarea> can't drive a datalist the way an
   <input> could, so switching those to textareas for auto-grow meant rebuilding
   the picklist as a popup instead, same idea as inspRemarks's own suggestion
   box in inspection.php. One shared instance repositioned per field (via
   position:fixed + getBoundingClientRect(), so it isn't thrown off by
   .insp-items-scroll's own internal scrolling) rather than one per row — this
   grid can run to dozens of rows and each would otherwise need its own copy
   of every suggestable column's list. */
.inv-suggest-box {
    position: fixed; z-index: 2000; max-height: 220px; overflow-y: auto;
    background: #fff; border: 1px solid var(--border); border-radius: 8px;
    box-shadow: 0 6px 18px rgba(0,0,0,.12);
}
.inv-suggest-box .inv-suggest-item { padding: .45rem .7rem; cursor: pointer; font-size: .82rem; }
.inv-suggest-box .inv-suggest-item:hover { background: var(--primary); color: #fff; }
.insp-left-col-scroll { overflow-y: auto; min-height: 0; padding-right: .3rem; }

/* Classification pill inside a grid cell — read-only, computed server-side,
   coloured the same way the worklist badge is so the two never look like
   they disagree. */
.inv-cat-pill {
    display: inline-block; padding: .15rem .5rem; border-radius: 20px;
    font-size: .68rem; font-weight: 700; white-space: nowrap;
}
.inv-cat-pill.par  { background: #ede9fe; color: #6d28d9; }
.inv-cat-pill.high { background: #dbeafe; color: #1d4ed8; }
.inv-cat-pill.low   { background: #f1f5f9; color: #475569; }
.inv-number-pill {
    display: inline-block; padding: .15rem .5rem; border-radius: 6px;
    font-size: .72rem; font-weight: 700; white-space: nowrap;
    background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;
}
.inv-number-pill.empty { background: #fff7ed; color: #b45309; border-color: #fed7aa; font-weight: 600; }

/* A heading row carried over from the purchase order — "SET 1", "LOT 1" — is
   a label, not a stocked item: it never had a unit, quantity or cost against
   it there either, so it cannot be classified, issued a PAR/ICS number, or
   selected for issuance here. Same "blank" test the PO item grid uses (see
   its own .po-item-title note) and the same presentation-only treatment —
   the empty inputs stay in the DOM so saving keeps reading the row exactly
   as before. */
.inv-item-title > td { background: #f1f5f9; }
.inv-item-title input, .inv-item-title textarea.inv-grow { display: none; }
.inv-item-title .item-desc {
    font-weight: 700; color: var(--navy); background: #f1f5f9;
    border-color: transparent; box-shadow: none;
}
.inv-item-title .inv-cat-pill, .inv-item-title .inv-number-pill { display: none; }
.inv-item-title td.no-print .btn { display: none; }
.inv-title-dash { display: none; color: #cbd5e1; text-align: center; font-size: .8rem; }
.inv-item-title .inv-title-dash { display: block; }

.insp-total-row {
    display: flex; justify-content: flex-end; gap: 2rem; font-size: .88rem;
    margin-top: .6rem; padding: .55rem .9rem;
    background: linear-gradient(135deg, #eef4fe, #e6edfb);
    border: 1px solid #cddcf7; border-radius: 10px;
}
.insp-total-row .lbl { color: var(--muted); font-weight: 600; }
.insp-total-row .val { font-weight: 700; color: var(--navy); font-variant-numeric: tabular-nums; }

#inventoryModal .modal-body   { background: #f5f8fd; }
#inventoryModal .modal-footer { background: #f5f8fd; border-top: 1px solid #dbe3ee; }
#inventoryModal .po-card      { box-shadow: 0 1px 3px rgba(30,58,138,.06); }

/* Purpose is free text and often a full sentence — same treatment as every
   other list in this app: shown in full, wrapping onto as many lines as it
   needs, capped in width only. */
.inv-purpose-cell { display: block; max-width: 300px; min-width: 160px; white-space: normal; word-break: break-word; }
#inventoryTable tbody td { vertical-align: top; }

/* ── The PO picker (create mode only) ───────────────────────────────────── */
.inv-po-pick-results { max-height: 260px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px; margin-top: .4rem; }
.inv-po-pick-row { padding: .5rem .7rem; border-bottom: 1px solid #f1f5f9; cursor: pointer; font-size: .82rem; }
.inv-po-pick-row:last-child { border-bottom: none; }
.inv-po-pick-row:hover { background: #eff6ff; }
.inv-po-pick-row .meta { color: var(--muted); font-size: .74rem; }

/* ── Issuance / Transfer dialogs ─────────────────────────────────────────── */
.inv-doc-choice { display: flex; gap: .6rem; flex-wrap: wrap; }
.inv-doc-choice .form-check { border: 1px solid var(--border); border-radius: 10px; padding: .5rem .8rem .5rem 2.2rem; flex: 1 1 140px; }
.inv-doc-choice .form-check-input:checked ~ .form-check-label { color: var(--primary); font-weight: 700; }
.inv-issued-result {
    display: flex; align-items: center; gap: .6rem; padding: .7rem .9rem; border-radius: 10px;
    background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; font-weight: 600; margin-top: .8rem;
}
.inv-issued-result .num { font-size: 1.05rem; font-family: monospace; }

/* ── Transfer history timeline ────────────────────────────────────────────── */
.inv-hist-item { border-left: 3px solid #93b4f5; padding: .5rem 0 .5rem .9rem; margin-bottom: .5rem; position: relative; }
.inv-hist-item::before { content: ''; position: absolute; left: -6px; top: .65rem; width: 10px; height: 10px; border-radius: 50%; background: var(--primary); }
.inv-hist-num { font-weight: 700; color: var(--navy); }
.inv-hist-meta { font-size: .78rem; color: var(--muted); margin-top: .15rem; }
.inv-hist-flow { font-size: .84rem; margin-top: .3rem; }
.inv-hist-flow .from { color: #b45309; } .inv-hist-flow .to { color: #15803d; font-weight: 600; }
.inv-empty { text-align: center; color: var(--muted); padding: 1.4rem; border: 1px dashed var(--border); border-radius: 10px; }

@media print { .sidebar, .top-navbar, .no-print { display: none !important; } }
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
                <div class="page-title">Inventory &amp; Property</div>
                <div class="page-subtitle">Receive purchase orders into custody, classify PAR/ICS, issue and transfer property.</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="row g-3 mb-3 no-print">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-dolly"></i></div>
                    <div><div class="stat-value" id="tileToday">—</div><div class="stat-label">Received Today</div></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon teal"><i class="fas fa-calendar-check"></i></div>
                    <div><div class="stat-value" id="tileMonth">—</div><div class="stat-label">Received This Month</div></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-peso-sign"></i></div>
                    <div><div class="stat-value" id="tileValue" style="font-size:1.15rem;">—</div><div class="stat-label">Value This Month</div></div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon amber"><i class="fas fa-triangle-exclamation"></i></div>
                    <div><div class="stat-value" id="tileAwaiting">—</div><div class="stat-label">Lines Awaiting PAR/ICS</div></div>
                </div>
            </div>
        </div>

        <div class="po-card no-print">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-5 po-field-group mb-0">
                    <label>Search</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="text" id="filterSearch" class="form-control" placeholder="PO/PR number, supplier, department"></div>
                </div>
                <div class="col-sm-6 col-md-3 po-field-group mb-0">
                    <label>From</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar"></i></span>
                    <input type="date" id="filterFrom" class="form-control"></div>
                </div>
                <div class="col-sm-6 col-md-3 po-field-group mb-0">
                    <label>To</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar"></i></span>
                    <input type="date" id="filterTo" class="form-control"></div>
                </div>
                <div class="col-sm-6 col-md-1 po-field-group mb-0">
                    <button class="btn btn-primary-app w-100" id="btnFilter" title="Filter"><i class="fas fa-filter"></i></button>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="panel-title"><i class="fas fa-warehouse me-2"></i>Inventory Records</h6>
                <div class="d-flex align-items-center gap-2">
                    <span class="po-chip active" id="chipAll" onclick="setStatusFilter('')">Total <span class="po-chip-num" id="chipAllNum">0</span></span>
                    <span class="po-chip" id="chipAwaiting" onclick="setStatusFilter('awaiting')" style="border-color:#fcd34d;color:#b45309;">Awaiting Issuance <span class="po-chip-num" id="chipAwaitingNum">0</span></span>
                    <span class="po-chip" id="chipIssued" onclick="setStatusFilter('issued')" style="border-color:#86efac;color:#15803d;">Fully Issued <span class="po-chip-num" id="chipIssuedNum">0</span></span>
                    <button class="btn btn-outline-secondary btn-sm ms-2" id="btnPersonnelMr"><i class="fas fa-id-card me-1"></i>Personnel MR</button>
                    <button class="btn btn-outline-secondary btn-sm" id="btnPrsList"><i class="fas fa-rotate-left me-1"></i>Property Returns</button>
                    <button class="btn btn-outline-secondary btn-sm" id="btnAddManually"><i class="fas fa-pen me-1"></i>Add Manually</button>
                    <button class="btn btn-primary-app btn-sm" id="btnReceive"><i class="fas fa-plus me-1"></i>Receive PO</button>
                </div>
            </div>
            <div class="panel-body">
                <table id="inventoryTable" class="app-table table table-hover table-bordered mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th>PO Number</th><th>PR Number</th><th>Supplier</th>
                            <th>Department</th><th>Purpose</th><th>Received</th>
                            <th>Items</th><th>Value</th><th>Status</th><th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Receive / View / Revise
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="inventoryModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-insp-wide modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header insp-modal-header">
        <div class="insp-header-icon"><i class="fas fa-warehouse"></i></div>
        <div class="insp-header-text">
            <h5 class="modal-title" id="invModalTitle">Receive into Inventory</h5>
            <small id="invModalSubtitle">Bring a purchase order's items into property custody.</small>
        </div>
        <button type="button" class="insp-header-close" data-bs-dismiss="modal"><i class="fas fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="readonly-banner d-none" id="invReadonlyBanner">
            <i class="fas fa-lock"></i>
            <div>This record is already on file. Choose <strong>Revise</strong> to change it — the current version is kept as history.</div>
        </div>

        <div id="invPoPicker" class="po-card sec-order">
            <div class="po-card-title"><i class="fas fa-magnifying-glass"></i> Select Purchase Order</div>
            <div class="po-field-group mb-0">
                <input type="text" id="invPoSearch" class="form-control" placeholder="Search by PO number, PR number, supplier or department…" autocomplete="off">
                <div class="po-field-hint">Press F3 or start typing. Only purchase orders not already in Inventory are useful here — an existing one opens for Revise instead from the list.</div>
                <div class="inv-po-pick-results d-none" id="invPoResults"></div>
            </div>
        </div>

        <!-- Manual entry — legacy property with no purchase order behind it
             (acquired years before this system existed). Stands in for the
             "Select Purchase Order" card above; see btnAddManually below and
             the `manual` branch of api/inventory.php's save handler. -->
        <div id="invManualHeader" class="po-card sec-order" style="display:none">
            <div class="po-card-title"><i class="fas fa-pen"></i> Manual Entry Details</div>
            <div class="po-field-hint mb-2">No purchase order for this property — supplied here instead, all optional.</div>
            <div class="row g-2 tight-field-row">
                <div class="col-md-6 po-field-group">
                    <label>Supplier Name</label>
                    <input type="text" id="manualSupplier" class="form-control">
                </div>
                <div class="col-md-6 po-field-group">
                    <label>Department</label>
                    <input type="text" id="manualDept" class="form-control">
                </div>
                <div class="col-md-6 po-field-group">
                    <label>Category</label>
                    <input type="text" id="manualCategory" class="form-control">
                </div>
                <div class="col-md-6 po-field-group">
                    <label>Reference / PR No.</label>
                    <input type="text" id="manualPr" class="form-control">
                </div>
            </div>
        </div>

        <div class="row g-3" id="invEntryBody" style="display:none">
        <div class="col-12">

        <div class="po-card po-card-compact sec-order" id="invPoSummaryCard">
          <div class="po-card-title"><i class="fas fa-file-invoice"></i> Purchase Order</div>
          <div class="po-summary-strip">
            <div class="cell"><div class="k">PO Number</div><div class="v" id="sumPo">—</div></div>
            <div class="cell"><div class="k">PR Number</div><div class="v" id="sumPr">—</div></div>
            <div class="cell"><div class="k">Supplier</div><div class="v" id="sumSupplier">—</div></div>
            <div class="cell"><div class="k">Department</div><div class="v" id="sumDept">—</div></div>
            <div class="cell"><div class="k">Category</div><div class="v" id="sumCategory">—</div></div>
          </div>
        </div>

        <div class="row g-3">
        <div class="col-lg-7">
        <div class="po-card po-card-compact sec-receipt h-100">
          <div class="po-card-title"><i class="fas fa-clipboard-list"></i> Intake Details</div>
          <div class="row g-2 tight-field-row">
            <div class="col-md-6 po-field-group">
              <label>Date Entry</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar-day"></i></span>
              <input type="datetime-local" id="invDateEntry" class="form-control"></div>
            </div>
            <div class="col-md-6 po-field-group">
              <label>Initial</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-signature"></i></span>
              <input type="text" id="invInitial" class="form-control" maxlength="20"></div>
            </div>
            <div class="col-md-6 po-field-group">
              <label>Mode of Procurement</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-file-contract"></i></span>
              <input type="text" id="invMode" class="form-control"></div>
            </div>
            <div class="col-md-6 po-field-group">
              <label>Source of Fund</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-sack-dollar"></i></span>
              <input type="text" id="invSource" class="form-control" placeholder="e.g. General Fund"></div>
            </div>
            <div class="col-12 po-field-group">
              <label>Purpose</label>
              <div class="po-input-wrap"><span class="po-input-icon" style="top:19px;transform:none;"><i class="fas fa-bullseye"></i></span>
              <textarea id="invPurpose" class="form-control" rows="2"></textarea></div>
            </div>
          </div>
        </div>
        </div>

        <div class="col-lg-5">
        <div class="po-card po-card-compact sec-people h-100">
          <div class="po-card-title"><i class="fas fa-user-shield"></i> Signing as CGSO Officer</div>
          <div class="row g-2 tight-field-row">
            <div class="col-md-6 po-field-group">
              <label>CGSO Officer</label>
              <input type="text" id="invCgsoOfficer" class="form-control" list="invDlCgso" autocomplete="off">
            </div>
            <div class="col-md-6 po-field-group">
              <label>Position</label>
              <input type="text" id="invCgsoPosition" class="form-control">
            </div>
          </div>
          <datalist id="invDlCgso"></datalist>
        </div>
        </div>
        </div>

        <div class="po-card po-card-compact sec-people">
          <div class="po-card-title d-flex justify-content-between align-items-center">
            <span><i class="fas fa-boxes-stacked"></i> Items</span>
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-secondary btn-sm po-edit-only" id="btnInvAddRow"><i class="fas fa-plus me-1"></i>Add Item Row</button>
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="checkbox" id="invCheckAll">
                    <label class="form-check-label small" for="invCheckAll">Select all</label>
                </div>
            </div>
          </div>
          <div class="insp-items-scroll" style="max-height:min(55vh, 640px); min-height:320px">
            <table class="insp-items-table">
                <thead>
                    <tr>
                        <th style="width:26px"></th><th style="width:26px">#</th>
                        <th style="min-width:220px">Description</th>
                        <th style="width:64px">Unit</th><th style="width:64px">Qty</th>
                        <th style="width:88px">Unit Cost</th><th style="width:92px">Total</th>
                        <th style="width:96px">Tag No.</th><th style="width:96px">Serial No.</th>
                        <th style="width:96px">Category</th><th style="width:120px">PAR/ICS No.</th>
                        <th style="min-width:150px">Accountable Officer</th><th style="min-width:130px">Position</th>
                        <th style="min-width:150px">Location</th><th style="min-width:150px">Classification</th>
                        <th style="width:110px">Condition</th><th style="min-width:120px">Remarks</th>
                        <th style="width:40px" class="no-print">Lot</th><th style="width:70px" class="no-print">Est. Life</th>
                        <th style="width:36px" class="no-print"></th>
                    </tr>
                </thead>
                <tbody id="invItemsBody"></tbody>
            </table>
          </div>
          <div class="insp-total-row">
            <div><span class="lbl">Items:</span> <span class="val" id="invTotalItems">0</span></div>
            <div><span class="lbl">Total Value:</span> <span class="val" id="invTotalValue">₱0.00</span></div>
          </div>
        </div>

        </div>
      </div>
      <!-- Missing until 2026-09-15: the closing tag for .modal-body above
           meant #inventoryModal's own outer closing tag never arrived
           either — every modal defined later in this file (invSubModal,
           invHistoryModal, invPrintModal, invPreviewModal, invMrModal) was a
           literal DOM descendant of this one. That never mattered for the
           others because they're only ever opened from JS while
           #inventoryModal is already open (backdrop already up, so the
           nesting was invisible in practice) — Personnel MR was the first
           entry point that opens straight from the page, and an ancestor
           that's display:none (this modal, when closed) hides everything
           nested inside it too, so invMrModal could never actually show. -->
      </div>
      <div class="modal-footer flex-wrap gap-2">
        <div class="me-auto d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm d-none" id="btnInvIssue"><i class="fas fa-stamp me-1"></i>Issue PAR/ICS</button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnInvTransfer"><i class="fas fa-right-left me-1"></i>Transfer</button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnInvReturn"><i class="fas fa-rotate-left me-1"></i>Return to Stock</button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnInvHistory"><i class="fas fa-clock-rotate-left me-1"></i>Transfer History</button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnInvSubDetails"><i class="fas fa-list-ul me-1"></i>Unit Breakdown</button>
            <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnInvPrint"><i class="fas fa-print me-1"></i>Print Card / Tag</button>
        </div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-danger d-none" id="btnInvUndo"><i class="fas fa-rotate-left me-1"></i>Undo</button>
        <button type="button" class="btn btn-outline-primary d-none" id="btnInvRevise">Revise</button>
        <button type="button" class="btn btn-primary-app d-none" id="btnInvSave"><i class="fas fa-save me-1"></i><span id="invSaveLabel">Save</span></button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Unit breakdown (sub-details)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="invSubModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-list-ul me-2"></i>Unit Breakdown</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="po-field-hint mb-2">One inventory line can cover several physically distinct units — five identical desktop computers under one line, say — each with its own serial number, tag, and (if it moves separately later) its own custodian.</div>
        <div class="table-responsive">
            <table class="insp-items-table" id="invSubTable">
                <thead><tr>
                    <th>Description</th><th style="width:90px">Unit Cost</th>
                    <th style="width:110px">Serial No.</th><th style="width:110px">Tag No.</th>
                    <th style="min-width:140px">Accountable Officer</th><th style="min-width:140px">Location</th>
                    <th style="min-width:110px">Condition</th><th style="min-width:120px">Remarks</th><th style="width:32px"></th>
                </tr></thead>
                <tbody id="invSubBody"></tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnInvSubAdd"><i class="fas fa-plus me-1"></i>Add Row</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary-app" id="btnInvSubSave">Save Breakdown</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Issue PAR / ICS
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="invIssueModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-stamp me-2"></i>Issue Property Document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="po-field-hint mb-2" id="invIssueScope"></div>

        <label class="form-label small fw-semibold">Document Type</label>
        <div class="inv-doc-choice mb-3">
            <div class="form-check"><input class="form-check-input" type="radio" name="invDocType" id="invDocPar" value="par">
                <label class="form-check-label" for="invDocPar">PAR</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invDocType" id="invDocIcs" value="ics">
                <label class="form-check-label" for="invDocIcs">ICS</label></div>
        </div>

        <label class="form-label small fw-semibold">Layout</label>
        <div class="inv-doc-choice mb-3">
            <div class="form-check"><input class="form-check-input" type="radio" name="invLayout" id="invLayoutStd" value="standard" checked>
                <label class="form-check-label" for="invLayoutStd">Standard</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invLayout" id="invLayoutLot" value="lot">
                <label class="form-check-label" for="invLayoutLot">By LOT</label></div>
        </div>

        <label class="form-label small fw-semibold">Mode</label>
        <div class="inv-doc-choice mb-3">
            <div class="form-check"><input class="form-check-input" type="radio" name="invMode2" id="invModeNew" value="new" checked>
                <label class="form-check-label" for="invModeNew">New</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invMode2" id="invModeReprint" value="reprint">
                <label class="form-check-label" for="invModeReprint">Reprint</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invMode2" id="invModeRenew" value="renew">
                <label class="form-check-label" for="invModeRenew">Renew</label></div>
        </div>

        <div class="row g-2">
            <div class="col-md-6 po-field-group">
                <label>CGSO Officer</label>
                <input type="text" id="invIssueCgso" class="form-control" list="invDlCgso">
            </div>
            <div class="col-md-6 po-field-group">
                <label>CGSO Position</label>
                <input type="text" id="invIssueCgsoPosition" class="form-control">
            </div>
        </div>

        <div class="inv-issued-result d-none" id="invIssuedResult">
            <i class="fas fa-circle-check"></i>
            <div>Issued <span class="num" id="invIssuedNumber"></span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary-app" id="btnInvIssueGo"><i class="fas fa-file-pdf me-1"></i>Generate &amp; Preview</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Transfer
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="invTransferModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-right-left me-2"></i>Transfer Property</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="po-field-hint mb-2" id="invTransferScope"></div>

        <label class="form-label small fw-semibold">Document Type</label>
        <div class="inv-doc-choice mb-3">
            <div class="form-check"><input class="form-check-input" type="radio" name="invTransferType" id="invTypeItr" value="itr" checked>
                <label class="form-check-label" for="invTypeItr">ITR — Inventory Transfer</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invTransferType" id="invTypePtr" value="ptr">
                <label class="form-check-label" for="invTypePtr">PTR — Property Transfer</label></div>
        </div>

        <label class="form-label small fw-semibold">Transfer Type</label>
        <div class="inv-doc-choice mb-3">
            <div class="form-check"><input class="form-check-input" type="radio" name="invTransferKind" id="invKindReassign" value="Reassignment" checked>
                <label class="form-check-label" for="invKindReassign">Reassignment</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invTransferKind" id="invKindRelocation" value="Relocation">
                <label class="form-check-label" for="invKindRelocation">Relocation</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invTransferKind" id="invKindDonation" value="Donation">
                <label class="form-check-label" for="invKindDonation">Donation</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invTransferKind" id="invKindOther" value="Other">
                <label class="form-check-label" for="invKindOther">Other</label></div>
        </div>

        <div class="row g-2">
            <div class="col-12 po-field-group">
                <label>From (current custodian)</label>
                <input type="text" id="invTransferFrom" class="form-control" readonly>
            </div>
            <div class="col-md-6 po-field-group">
                <label>To — Accountable Officer <span class="text-danger">*</span></label>
                <input type="text" id="invTransferToOfficer" class="form-control" list="invDlOfficers" autocomplete="off">
            </div>
            <div class="col-md-6 po-field-group">
                <label>To — Position</label>
                <input type="text" id="invTransferToPosition" class="form-control" list="invDlPositions" autocomplete="off">
            </div>
            <div class="col-md-6 po-field-group">
                <label>To — Location</label>
                <input type="text" id="invTransferToLocation" class="form-control" list="invDlLocations" autocomplete="off">
            </div>
            <div class="col-md-6 po-field-group">
                <label>Fund Cluster</label>
                <input type="text" id="invTransferFundCluster" class="form-control">
            </div>
            <div class="col-12 po-field-group">
                <label>Reason for Transfer</label>
                <textarea id="invTransferReason" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6 po-field-group">
                <label>Approved By (CGSO)</label>
                <input type="text" id="invTransferApprovedBy" class="form-control" list="invDlCgso">
            </div>
            <div class="col-md-6 po-field-group">
                <label>Issued By (Warehouse Officer)</label>
                <input type="text" id="invTransferIssuedBy" class="form-control" list="invDlCgso">
            </div>
            <div class="col-md-6 po-field-group">
                <label>Issued By — Position</label>
                <input type="text" id="invTransferIssuedPosition" class="form-control" list="invDlPositions" autocomplete="off">
            </div>
        </div>
        <datalist id="invDlOfficers"></datalist>
        <datalist id="invDlPositions"></datalist>
        <datalist id="invDlLocations"></datalist>

        <div class="inv-issued-result d-none" id="invTransferResult">
            <i class="fas fa-circle-check"></i>
            <div>Recorded as <span class="num" id="invTransferNumber"></span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary-app" id="btnInvTransferGo"><i class="fas fa-file-pdf me-1"></i>Record &amp; Preview</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Return to Stock (Property Return Slip) — structurally a transfer whose
     destination is GSO itself; see api/inventory_issuance.php's 'transfer'
     action note. No Transfer Type / Transfer Kind radios here — Donation/
     Reassignment/Relocation/Other describe a move between two employees,
     which a return isn't.
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="invReturnModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-rotate-left me-2"></i>Return to Stock (PRS)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="po-field-hint mb-2" id="invReturnScope"></div>

        <div class="row g-2">
            <div class="col-12 po-field-group">
                <label>From (returning officer)</label>
                <input type="text" id="invReturnFrom" class="form-control" readonly>
            </div>
            <div class="col-md-6 po-field-group">
                <label>Condition</label>
                <input type="text" id="invReturnCondition" class="form-control" list="invDlCondition" autocomplete="off">
            </div>
            <div class="col-md-6 po-field-group">
                <label>Returned To</label>
                <input type="text" id="invReturnToLocation" class="form-control" value="GSO Storage">
            </div>
            <div class="col-12 po-field-group">
                <label>Reason for Return</label>
                <textarea id="invReturnReason" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6 po-field-group">
                <label>Received By (GSO)</label>
                <input type="text" id="invReturnReceivedBy" class="form-control" list="invDlCgso">
            </div>
            <div class="col-md-6 po-field-group">
                <label>Received By — Position</label>
                <input type="text" id="invReturnReceivedPosition" class="form-control" list="invDlPositions" autocomplete="off">
            </div>
            <div class="col-md-6 po-field-group">
                <label>Approved By (CGSO)</label>
                <input type="text" id="invReturnApprovedBy" class="form-control" list="invDlCgso">
            </div>
        </div>

        <div class="inv-issued-result d-none" id="invReturnResult">
            <i class="fas fa-circle-check"></i>
            <div>Recorded as <span class="num" id="invReturnNumber"></span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary-app" id="btnInvReturnGo"><i class="fas fa-file-pdf me-1"></i>Record &amp; Preview</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Transfer history
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="invHistoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-clock-rotate-left me-2"></i>Transfer History</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="invHistoryBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Print: Property Card / Tag (no number issued, straight to preview)
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="invPrintModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-print me-2"></i>Print</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="po-field-hint mb-2" id="invPrintScope"></div>
        <div class="inv-doc-choice">
            <div class="form-check"><input class="form-check-input" type="radio" name="invPrintType" id="invPrintCard" value="card" checked>
                <label class="form-check-label" for="invPrintCard">Property Card</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invPrintType" id="invPrintCardSemi" value="card_semi">
                <label class="form-check-label" for="invPrintCardSemi">Property Card (Semi-Expendable)</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invPrintType" id="invPrintTagBig" value="tag_big">
                <label class="form-check-label" for="invPrintTagBig">Property Tag (Big)</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="invPrintType" id="invPrintTagSmall" value="tag_small">
                <label class="form-check-label" for="invPrintTagSmall">Property Tag (Small)</label></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary-app" id="btnInvPrintGo"><i class="fas fa-file-pdf me-1"></i>Preview</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Shared PDF preview — same pipeline as every other printout in this app.
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="invPreviewModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="invPreviewTitle"><i class="fas fa-file-pdf me-2"></i>Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="pp-toolbar">
            <div class="pp-tool">
                <label for="invPaperSize">Paper</label>
                <select id="invPaperSize" class="form-select form-select-sm">
                    <option value="auto">Auto — fewest pages</option>
                    <option value="a4">A4</option>
                    <option value="long">Long / Folio</option>
                    <option value="legal">Legal</option>
                    <option value="letter">Letter</option>
                </select>
            </div>
            <div class="pp-layout-status" id="invLayoutStatus"></div>
        </div>
        <div id="invPreviewLoading" class="text-center py-5"></div>
        <iframe id="invPreviewFrame" class="pdf-preview-frame d-none" title="Report preview"></iframe>
        <div class="inv-offscreen"><div id="invSheetArea"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-secondary" id="invOpenTab" disabled><i class="fas fa-up-right-from-square me-1"></i> Open in Tab</button>
        <button type="button" class="btn btn-outline-primary" id="invDownload" disabled><i class="fas fa-download me-1"></i> Download</button>
        <button type="button" class="btn btn-primary-app" id="invPrint" disabled><i class="fas fa-print me-1"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Personnel Memorandum Receipt — everything currently in ONE person's
     custody, across every PO (unlike every other document here, which is
     scoped to a single PO). Renders through the same invPreviewModal/
     doRenderInv('mr', ...) pipeline as PAR/ICS/Card/Tag once a name is
     picked — see js/inventory-print.js's buildMR().
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="invMrModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-id-card me-2"></i>Personnel Memorandum Receipt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="po-field-group mb-3">
          <label>Accountable Officer</label>
          <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
            <input type="text" id="mrOfficerSearch" class="form-control" placeholder="Search by name…" autocomplete="off"
                   oninput="invFieldSuggest(this, 'officers', mrSelectOfficer)"
                   onfocus="invFieldSuggest(this, 'officers', mrSelectOfficer)"
                   onblur="invSuggestHide()">
          </div>
        </div>
        <div id="mrEmpty" class="text-center text-muted py-5">
          <i class="fas fa-id-card fa-2x mb-2 d-block"></i>Search for a person to see everything currently in their custody.
        </div>
        <div id="mrResult" class="d-none">
          <div class="po-summary-strip mb-3">
            <div class="cell"><div class="k">Officer</div><div class="v" id="mrOfficerName">—</div></div>
            <div class="cell"><div class="k">Items</div><div class="v" id="mrItemCount">0</div></div>
            <div class="cell"><div class="k">Total Value</div><div class="v" id="mrTotalValue">₱0.00</div></div>
          </div>
          <div class="table-responsive">
            <table class="app-table table table-bordered mb-0">
              <thead><tr>
                <th>Description</th><th>Unit</th><th>Qty</th><th>Unit Cost</th><th>Total</th>
                <th>Property/Tag No.</th><th>PO Number</th><th>Location</th>
              </tr></thead>
              <tbody id="mrItemsBody"></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary-app" id="btnMrPrint" disabled><i class="fas fa-print me-1"></i>Preview / Print</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Property Returns — every PRS ever issued, across every PO (unlike the
     per-PO Transfer History modal above). Read-only list with a Reprint
     action per row; see api/inventory_issuance.php's ?prs_list=1.
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="invPrsListModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-rotate-left me-2"></i>Property Returns</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="invPrsListBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script src="vendor/js/jquery.dataTables.min.js"></script>
<script src="vendor/js/dataTables.bootstrap5.min.js"></script>
<script src="vendor/js/html2canvas.min.js"></script>
<script src="vendor/js/jspdf.umd.min.js"></script>
<script src="<?= asset('js/print-pdf.js') ?>"></script>
<script src="<?= asset('js/inventory-print.js') ?>"></script>
<script>
const apiUrl = 'api/inventory.php';
const issuanceUrl = 'api/inventory_issuance.php';

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function money(v) { const n = parseFloat(v); return isFinite(n) ? n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) : '0.00'; }
// Same test purchase_orders.php's item grid uses to tell "really zero" from
// "nothing entered" — a bare falsy check would treat a genuine 0 the same as
// blank, which is exactly the distinction a title-row test needs.
function itemHasValue(v) {
    return v !== null && v !== undefined && String(v).trim() !== '' && parseFloat(v) !== 0;
}
function qty(v) { const n = parseFloat(v); if (!isFinite(n)) return ''; return Number.isInteger(n) ? String(n) : String(parseFloat(n.toFixed(3))); }
function fmtDate(v) {
    if (!v) return '';
    const t = Date.parse(String(v).replace(' ', 'T'));
    if (isNaN(t)) return '';
    const d = new Date(t);
    return String(d.getMonth()+1).padStart(2,'0') + '/' + String(d.getDate()).padStart(2,'0') + '/' + d.getFullYear();
}
function toLocalInput(v) {
    if (!v) return '';
    const t = Date.parse(String(v).replace(' ', 'T'));
    if (isNaN(t)) return '';
    const d = new Date(t);
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0')
         + 'T' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}
function openModal(id) { bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).show(); }
function showToast(msg, type) {
    // Reuses the same lightweight toast markup every other page in this app
    // already defines locally.
    const id = 't' + Date.now();
    const bg = type === 'error' || type === 'danger' ? 'bg-danger' : type === 'warning' ? 'bg-warning text-dark' : 'bg-success';
    let box = document.getElementById('toastContainer');
    if (!box) {
        box = document.createElement('div');
        box.id = 'toastContainer';
        box.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(box);
    }
    box.insertAdjacentHTML('beforeend', `<div id="${id}" class="toast align-items-center text-white ${bg} border-0"><div class="d-flex"><div class="toast-body">${escapeHtml(msg)}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);
    const t = new bootstrap.Toast(document.getElementById(id), { delay: 4500 });
    t.show();
    document.getElementById(id).addEventListener('hidden.bs.toast', () => document.getElementById(id)?.remove());
}

let officeList = [];       // {name, position} candidates for signatory pickers
let cgsoOfficer = null;
let classificationList = [];
let conditionList = [];
let locationList = [];
let positionList = [];
let officerList = [];

async function loadSignatories() {
    try {
        const res = await fetch(`${apiUrl}?signatories=1`);
        const data = await res.json();
        if (!data.success) return;
        officerList = data.officers || [];
        positionList = data.positions || [];
        locationList = data.locations || [];
        classificationList = data.classifications || [];
        conditionList = data.conditions || [];
        cgsoOfficer = data.cgso_officer || null;

        document.getElementById('invDlCgso').innerHTML =
            (data.cgso_staff || []).map(p => `<option value="${escapeHtml(p.name)}">${escapeHtml(p.position||'')}</option>`).join('');
        document.getElementById('invDlOfficers').innerHTML = officerList.map(n => `<option value="${escapeHtml(n)}">`).join('');
        document.getElementById('invDlPositions').innerHTML = positionList.map(n => `<option value="${escapeHtml(n)}">`).join('');
        document.getElementById('invDlLocations').innerHTML = locationList.map(n => `<option value="${escapeHtml(n)}">`).join('');
    } catch (err) { console.error(err); }
}

async function loadStats() {
    try {
        const res = await fetch(`${apiUrl}?stats=1`);
        const data = await res.json();
        if (!data.success) return;
        const s = data.stats;
        document.getElementById('tileToday').textContent    = s.received_today || 0;
        document.getElementById('tileMonth').textContent    = s.received_month || 0;
        document.getElementById('tileValue').textContent    = '₱' + money(s.value_month);
        document.getElementById('tileAwaiting').textContent = s.awaiting_issuance || 0;
    } catch (err) { console.error(err); }
}

// ── List ───────────────────────────────────────────────────────────────────
let invTable = null;
let invStatusFilter = '';

function setStatusFilter(status) {
    invStatusFilter = status;
    document.querySelectorAll('#chipAll,#chipAwaiting,#chipIssued').forEach(c => c.classList.remove('active'));
    (status === 'awaiting' ? document.getElementById('chipAwaiting')
        : status === 'issued' ? document.getElementById('chipIssued')
        : document.getElementById('chipAll')).classList.add('active');
    loadList();
}

async function loadList() {
    const params = new URLSearchParams({
        search: document.getElementById('filterSearch').value.trim(),
        from:   document.getElementById('filterFrom').value,
        to:     document.getElementById('filterTo').value,
        status: invStatusFilter,
    });
    try {
        const res = await fetch(`${apiUrl}?${params}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load Inventory.', 'error');
        renderRows(data.records || []);
        const c = data.counts || {};
        document.getElementById('chipAllNum').textContent = c.total || 0;
        document.getElementById('chipAwaitingNum').textContent = c.awaiting || 0;
        document.getElementById('chipIssuedNum').textContent = c.issued || 0;
    } catch (err) {
        showToast('Unable to load Inventory.', 'error');
        console.error(err);
    }
}

// Same split as purchase_orders.php's own PR Number column — a PO can cite
// several PRs ("20251936, 20251919, …"), and one pill each reads far better
// than a single long comma-separated blob. Splits on comma/newline only:
// the value passed in is already HTML-escaped, so splitting on ';' too would
// tear an entity like &amp; in half.
function renderPrPills(prNumber) {
    if (!prNumber) return '<span class="text-muted">—</span>';
    const parts = escapeHtml(prNumber).split(/[,\n\r]+/).map(s => s.trim()).filter(Boolean);
    if (!parts.length) return '<span class="text-muted">—</span>';
    return `<span class="pr-num-group">${parts.map(p => `<span class="num-pill pr-num-pill">${p}</span>`).join('')}</span>`;
}

function renderRows(rows) {
    const body = document.getElementById('inventoryBody');
    if (invTable) { invTable.destroy(); invTable = null; }
    body.innerHTML = rows.map(r => {
        const badge = r.awaiting_issuance
            ? `<span class="status-badge status-awaiting"><i class="fas fa-hourglass-half"></i> ${r.issued_count}/${r.item_count} issued</span>`
            : `<span class="status-badge status-issued"><i class="fas fa-check"></i> Fully issued</span>`;
        const manualBadge = r.is_manual ? `<span class="status-badge status-manual ms-1"><i class="fas fa-pen"></i> Manual Entry</span>` : '';
        return `<tr>
            <td><span class="num-pill po-num-pill">${escapeHtml(r.po_number)}</span>${manualBadge}</td>
            <td>${renderPrPills(r.pr_number)}</td>
            <td>${escapeHtml(r.supplier_name) || '<span class="text-muted">—</span>'}</td>
            <td>${escapeHtml(r.department) || '<span class="text-muted">—</span>'}</td>
            <td>${r.purpose ? `<span class="inv-purpose-cell">${escapeHtml(r.purpose)}</span>` : '<span class="text-muted">—</span>'}</td>
            <td>${fmtDate(r.date_created)}</td>
            <td>${r.item_count}</td>
            <td>₱${money(r.total_value)}</td>
            <td>${badge}</td>
            <td class="no-print"><div class="table-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="openInventory('${escapeHtml(r.po_number)}','view')" title="View"><i class="fas fa-eye"></i></button>
            </div></td>
        </tr>`;
    }).join('');

    invTable = $('#inventoryTable').DataTable({
        pageLength: 10, lengthMenu: [10, 25, 50, 100], order: [],
        language: { search: '', searchPlaceholder: 'Search table…' },
    });
}

// ── Record / View / Revise ──────────────────────────────────────────────
let invMode = 'create';       // create | view | revise
let invPoNumber = '';
let invHeaderId = null;
let invPrevious = null;

// mode: 'picker' (search for a real PO — "Receive PO", items grid hidden
// until one is picked), 'manual' (no PO — "Add Manually": the Manual Entry
// Details card AND the items grid show together, there is nothing to search
// for first), or 'entry' (a PO has been picked, or this is view/revise —
// just the summary strip / items grid, same as 'manual' minus its own card).
function invSetPoPickerVisible(mode) {
    document.getElementById('invPoPicker').style.display = mode === 'picker' ? '' : 'none';
    document.getElementById('invManualHeader').style.display = mode === 'manual' ? '' : 'none';
    document.getElementById('invEntryBody').style.display = (mode === 'entry' || mode === 'manual') ? '' : 'none';
    // The read-only "Purchase Order" summary strip only has anything to show
    // once a real PO has been picked (or for view/revise) — for manual
    // entry, the Manual Entry Details card above already covers that ground.
    document.getElementById('invPoSummaryCard').style.display = mode === 'manual' ? 'none' : '';
}

function invResetForNewEntry() {
    invHeaderId = null; invPrevious = null;
    document.getElementById('invReadonlyBanner').classList.add('d-none');
    ['btnInvIssue','btnInvTransfer','btnInvReturn','btnInvHistory','btnInvSubDetails','btnInvPrint','btnInvUndo','btnInvRevise'].forEach(id =>
        document.getElementById(id).classList.add('d-none'));
    document.getElementById('btnInvAddRow').classList.remove('d-none');
    document.getElementById('btnInvSave').classList.remove('d-none');
    document.getElementById('invSaveLabel').textContent = 'Save';
    document.getElementById('invItemsBody').innerHTML = '';
    invItems = [];
    updateInvTotals();
    document.getElementById('invCgsoOfficer').value = cgsoOfficer ? cgsoOfficer.name : '';
    document.getElementById('invCgsoPosition').value = cgsoOfficer ? (cgsoOfficer.position || '') : '';
    openModal('inventoryModal');
}

document.getElementById('btnReceive').addEventListener('click', () => {
    invMode = 'create'; invPoNumber = '';
    document.getElementById('invModalTitle').textContent = 'Receive into Inventory';
    document.getElementById('invModalSubtitle').textContent = "Bring a purchase order's items into property custody.";
    document.getElementById('invPoSearch').value = '';
    document.getElementById('invPoResults').innerHTML = '';
    document.getElementById('invPoResults').classList.add('d-none');
    invSetPoPickerVisible('picker');
    invResetForNewEntry();
    setTimeout(() => document.getElementById('invPoSearch').focus(), 300);
});

document.getElementById('btnAddManually').addEventListener('click', () => {
    invMode = 'manual'; invPoNumber = '';
    document.getElementById('invModalTitle').textContent = 'Add Property Manually';
    document.getElementById('invModalSubtitle').textContent = 'Manually entered — no linked purchase order.';
    document.getElementById('manualSupplier').value = '';
    document.getElementById('manualDept').value = '';
    document.getElementById('manualCategory').value = '';
    document.getElementById('manualPr').value = '';
    invSetPoPickerVisible('manual');
    invResetForNewEntry();
    setTimeout(() => document.getElementById('manualSupplier').focus(), 300);
});

document.getElementById('btnInvAddRow').addEventListener('click', () => {
    invItems.push({
        inv_id: null, items_description: '', unit: '', quantity: '', unit_cost: '', total_cost: '',
        tag_number: '', serial_number: '', inventory_category: '', par_ics_number: '',
        accountable_officer: '', position: '', location: '', classification: '', condition: '',
        remarks: '', number_series: '', estimated_life: '', sub_details: [],
    });
    renderInvItems(invItems);
});

// ── Personnel Memorandum Receipt ────────────────────────────────────────
let mrOfficer = null; // { name, rows, total_value } for whoever is currently loaded

document.getElementById('btnPersonnelMr').addEventListener('click', () => {
    mrOfficer = null;
    document.getElementById('mrOfficerSearch').value = '';
    document.getElementById('mrEmpty').classList.remove('d-none');
    document.getElementById('mrResult').classList.add('d-none');
    document.getElementById('btnMrPrint').disabled = true;
    openModal('invMrModal');
    setTimeout(() => document.getElementById('mrOfficerSearch').focus(), 300);
});

// Passed to invFieldSuggest() as its onSelect callback — runs once a name is
// actually picked from the popup, not on every keystroke.
async function mrSelectOfficer(name) {
    document.getElementById('mrOfficerSearch').value = name;
    try {
        const res = await fetch(`${apiUrl}?mr=1&officer=${encodeURIComponent(name)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load records.', 'error');
        mrOfficer = { name, position: data.position || '', department: data.department || '',
                      rows: data.rows || [], total_value: data.total_value || 0 };
        document.getElementById('mrEmpty').classList.add('d-none');
        document.getElementById('mrResult').classList.remove('d-none');
        document.getElementById('mrOfficerName').textContent = name;
        document.getElementById('mrItemCount').textContent = mrOfficer.rows.length;
        document.getElementById('mrTotalValue').textContent = '₱' + money(mrOfficer.total_value);
        document.getElementById('mrItemsBody').innerHTML = mrOfficer.rows.length ? mrOfficer.rows.map(r => `
            <tr>
                <td>${escapeHtml(r.items_description || '')}</td>
                <td>${escapeHtml(r.unit || '')}</td>
                <td>${r.quantity ?? ''}</td>
                <td>${money(r.unit_cost)}</td>
                <td>${money(r.total_cost)}</td>
                <td>${escapeHtml(r.tag_number || r.serial_number || '') || '<span class="text-muted">—</span>'}</td>
                <td>${escapeHtml(r.po_number || '')}</td>
                <td>${escapeHtml(r.location || '') || '<span class="text-muted">—</span>'}</td>
            </tr>`).join('')
            : '<tr><td colspan="8" class="text-center text-muted py-3">No items currently on file for this person.</td></tr>';
        document.getElementById('btnMrPrint').disabled = mrOfficer.rows.length === 0;
    } catch (err) {
        showToast('Unable to load records.', 'error');
        console.error(err);
    }
}

document.getElementById('btnMrPrint').addEventListener('click', async () => {
    if (!mrOfficer || !mrOfficer.rows.length) return;
    bootstrap.Modal.getInstance(document.getElementById('invMrModal'))?.hide();
    await renderInvDocument('mr', {
        officer: mrOfficer.name, position: mrOfficer.position, department: mrOfficer.department,
        rows: mrOfficer.rows, cgso: cgsoOfficer,
    });
});

let invPoSearchTimer = null;
document.getElementById('invPoSearch').addEventListener('input', (e) => {
    clearTimeout(invPoSearchTimer);
    const term = e.target.value.trim();
    if (term.length < 2) { document.getElementById('invPoResults').classList.add('d-none'); return; }
    invPoSearchTimer = setTimeout(() => invSearchPo(term), 300);
});

async function invSearchPo(term) {
    try {
        const res = await fetch(`${apiUrl}?search_po=${encodeURIComponent(term)}`);
        const data = await res.json();
        const results = document.getElementById('invPoResults');
        const orders = (data.orders || []).slice(0, 20);
        if (!orders.length) {
            results.innerHTML = '<div class="inv-po-pick-row text-muted">No matching purchase orders.</div>';
            results.classList.remove('d-none');
            return;
        }
        results.innerHTML = orders.map(o => `
            <div class="inv-po-pick-row" onclick="invPickPo('${escapeHtml(o.po_number)}')">
                <div><span class="num-pill po-num-pill">${escapeHtml(o.po_number)}</span> ${escapeHtml(o.supplier_name || '')}</div>
                <div class="meta">${escapeHtml(o.department || '')} · ${escapeHtml(o.purpose || '').slice(0,80)}</div>
            </div>`).join('');
        results.classList.remove('d-none');
    } catch (err) { console.error(err); }
}

async function invPickPo(poNumber) {
    try {
        const res = await fetch(`${apiUrl}?preview_po=${encodeURIComponent(poNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load this purchase order.', 'error');
        invPoNumber = poNumber;
        const p = data.preview;
        document.getElementById('sumPo').innerHTML = `<span class="num-pill po-num-pill">${escapeHtml(p.po_number)}</span>`;
        document.getElementById('sumPr').innerHTML = p.pr_number ? `<span class="num-pill pr-num-pill">${escapeHtml(p.pr_number)}</span>` : '—';
        document.getElementById('sumSupplier').textContent = p.supplier_name || '—';
        document.getElementById('sumDept').textContent = p.department || '—';
        document.getElementById('sumCategory').textContent = p.category || '—';
        document.getElementById('invMode').value = p.mode_of_procurement || '';
        document.getElementById('invSource').value = '';
        document.getElementById('invPurpose').value = p.purpose || '';
        document.getElementById('invDateEntry').value = toLocalInput(new Date().toISOString());

        renderInvItems((p.items || []).map(it => ({
            item_id: it.Item_ID, items_description: it.Items_Description, unit: it.Unit,
            quantity: it.Quantity, unit_cost: it.Unit_Cost, total_cost: it.Total_Cost,
            inventory_category: it.inventory_category, number_series: it.NumberSeries,
            tag_number: '', serial_number: '', par_ics_number: '',
            accountable_officer: '', position: '', location: '', classification: '',
            condition: 'IN GOOD CONDITION', remarks: '', estimated_life: '',
        })));
        invSetPoPickerVisible('entry');
        applyInvMode();
    } catch (err) {
        showToast('Unable to load this purchase order.', 'error');
        console.error(err);
    }
}

async function openInventory(poNumber, mode) {
    invMode = mode; invPoNumber = poNumber;
    try {
        const res = await fetch(`${apiUrl}?po_number=${encodeURIComponent(poNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load.', 'error');

        const h = data.header;
        invHeaderId = h.header_id;
        invPrevious = data.previous || null;

        document.getElementById('invModalTitle').textContent = mode === 'view' ? 'Inventory Record' : 'Revise Inventory Record';
        document.getElementById('invModalSubtitle').textContent = h.is_manual
            ? 'Manually entered — no linked purchase order.'
            : (mode === 'view'
                ? 'What is currently on file for this purchase order.'
                : 'Saving keeps the current version on file and files this as a new one.');
        document.getElementById('invReadonlyBanner').classList.toggle('d-none', mode !== 'view');
        invSetPoPickerVisible('entry');

        document.getElementById('sumPo').innerHTML = `<span class="num-pill po-num-pill">${escapeHtml(h.po_number)}</span>`;
        document.getElementById('sumPr').innerHTML = h.pr_number ? `<span class="num-pill pr-num-pill">${escapeHtml(h.pr_number)}</span>` : '—';
        document.getElementById('sumSupplier').textContent = h.supplier_name || '—';
        document.getElementById('sumDept').textContent = h.department || '—';
        document.getElementById('sumCategory').textContent = h.category || '—';
        document.getElementById('invMode').value = h.mode_of_procurement || '';
        document.getElementById('invSource').value = h.source_of_fund || '';
        document.getElementById('invPurpose').value = h.purpose || '';
        document.getElementById('invDateEntry').value = toLocalInput(h.date_entry);
        document.getElementById('invInitial').value = h.initial || '';
        document.getElementById('invCgsoOfficer').value = cgsoOfficer ? cgsoOfficer.name : '';
        document.getElementById('invCgsoPosition').value = cgsoOfficer ? (cgsoOfficer.position || '') : '';

        renderInvItems((data.items || []).map(it => ({
            inv_id: it.inv_id, item_id: it.item_id, items_description: it.items_description, unit: it.unit,
            quantity: it.quantity, unit_cost: it.unit_cost, total_cost: it.total_cost,
            tag_number: it.tag_number, serial_number: it.serial_number,
            inventory_category: it.inventory_category, par_ics_number: it.par_ics_number,
            accountable_officer: it.accountable_officer, position: it.position, location: it.location,
            classification: it.classification, condition: it.condition, remarks: it.remarks,
            number_series: it.number_series, estimated_life: it.estimated_life,
            sub_details: it.sub_details || [],
        })));

        applyInvMode();
        openModal('inventoryModal');
    } catch (err) {
        showToast('Unable to load.', 'error');
        console.error(err);
    }
}

function applyInvMode() {
    const readOnly = invMode === 'view';
    // 'manual' is an unsaved state exactly like 'create' — the record has no
    // Inv_ID yet, so nothing that acts on an already-saved record (Issue,
    // Transfer, Return to Stock, History, Unit Breakdown, Print) applies.
    const unsaved = invMode === 'create' || invMode === 'manual';
    document.querySelectorAll('#inventoryModal input, #inventoryModal textarea, #inventoryModal select').forEach(el => {
        if (el.id === 'invPoSearch') return;
        el.readOnly = readOnly;
        if (el.tagName === 'SELECT') el.disabled = readOnly;
    });
    document.getElementById('btnInvSave').classList.toggle('d-none', readOnly);
    document.getElementById('btnInvRevise').classList.toggle('d-none', !readOnly);
    document.getElementById('btnInvUndo').classList.toggle('d-none', !readOnly);
    document.getElementById('btnInvIssue').classList.toggle('d-none', unsaved);
    document.getElementById('btnInvTransfer').classList.toggle('d-none', unsaved);
    document.getElementById('btnInvReturn').classList.toggle('d-none', unsaved);
    document.getElementById('btnInvHistory').classList.toggle('d-none', unsaved);
    document.getElementById('btnInvSubDetails').classList.toggle('d-none', unsaved);
    document.getElementById('btnInvPrint').classList.toggle('d-none', unsaved);
    document.getElementById('btnInvAddRow').classList.toggle('d-none', readOnly);
    document.getElementById('invSaveLabel').textContent = invMode === 'revise' ? 'Save Revision' : 'Save';
}

document.getElementById('btnInvRevise').addEventListener('click', () => {
    invMode = 'revise';
    document.getElementById('invModalTitle').textContent = 'Revise Inventory Record';
    document.getElementById('invModalSubtitle').textContent = 'Saving keeps the current version on file and files this as a new one.';
    document.getElementById('invReadonlyBanner').classList.add('d-none');
    applyInvMode();
});

// ── Items grid ─────────────────────────────────────────────────────────
let invItems = [];

function invCatPillClass(cat) {
    if (cat === 'PAR') return 'par';
    if (cat === 'ICS HIGH VALUE') return 'high';
    return 'low';
}

// A heading row carried over from the purchase order — no unit, quantity or
// cost was ever entered against it there either. Same test invIsTitleRow()
// applies server-side, kept identical so the grid and the API never disagree
// about which rows are labels.
function invRowIsTitle(it) {
    return !String(it.unit || '').trim()
        && !itemHasValue(it.quantity) && !itemHasValue(it.unit_cost) && !itemHasValue(it.total_cost);
}

// One auto-growing textarea cell, optionally wired to the shared suggestion
// popup (invFieldSuggest()) for the columns that used to be an <input> with a
// native <datalist> — a <textarea> has no datalist support, so those five
// (Accountable Officer, Position, Location, Classification, Condition) need
// the popup to keep suggesting at all; the rest (Unit, Tag No., Serial No.,
// Remarks) never had suggestions and just grow.
function invGrowCell(field, value, suggestKey) {
    const events = suggestKey
        ? ` oninput="autoGrowInv(this); invFieldSuggest(this, '${suggestKey}')" onfocus="invFieldSuggest(this, '${suggestKey}')" onblur="invSuggestHide()"`
        : ` oninput="autoGrowInv(this)"`;
    return `<textarea rows="1" class="inv-grow" data-f="${field}"${events}>${escapeHtml(value || '')}</textarea>`;
}

function renderInvItems(items) {
    invItems = items;
    const body = document.getElementById('invItemsBody');
    // The # column counts stocked items, not label rows — a "SET 1" divider
    // does not take up a number, the same way it does not count toward
    // item_count on the server (see invIsTitleRow()'s note in api/inventory.php).
    let itemNo = 0;
    body.innerHTML = items.map((it, i) => {
        const cat = it.inventory_category || '';
        const numberPill = it.par_ics_number
            ? `<span class="inv-number-pill">${escapeHtml(it.par_ics_number)}</span>`
            : `<span class="inv-number-pill empty">Not issued</span>`;
        const isTitle = invRowIsTitle(it);
        const dash = '<span class="inv-title-dash">&mdash;</span>';
        return `<tr data-idx="${i}" data-inv-id="${it.inv_id || ''}" class="${isTitle ? 'inv-item-title' : ''}">
            <td><input type="checkbox" class="inv-row-check"></td>
            <td class="text-muted">${isTitle ? dash : ++itemNo}</td>
            <td><textarea rows="1" class="item-desc" data-f="items_description" oninput="autoGrowInv(this)">${escapeHtml(it.items_description||'')}</textarea></td>
            <td>${invGrowCell('unit', it.unit)}${dash}</td>
            <td class="num"><input type="number" step="0.001" value="${it.quantity ?? ''}" data-f="quantity" oninput="recalcInvRow(this)">${dash}</td>
            <td class="num"><input type="number" step="0.01" value="${it.unit_cost ?? ''}" data-f="unit_cost" oninput="recalcInvRow(this)">${dash}</td>
            <td class="num"><input type="text" value="${money(it.total_cost)}" data-f="total_cost" readonly>${dash}</td>
            <td>${invGrowCell('tag_number', it.tag_number)}${dash}</td>
            <td>${invGrowCell('serial_number', it.serial_number)}${dash}</td>
            <td>${isTitle ? dash : `<span class="inv-cat-pill ${invCatPillClass(cat)}" data-f="inventory_category">${escapeHtml(cat)}</span>`}</td>
            <td>${isTitle ? dash : numberPill}</td>
            <td>${invGrowCell('accountable_officer', it.accountable_officer, 'officers')}${dash}</td>
            <td>${invGrowCell('position', it.position, 'positions')}${dash}</td>
            <td>${invGrowCell('location', it.location, 'locations')}${dash}</td>
            <td>${invGrowCell('classification', it.classification, 'classification')}${dash}</td>
            <td>${invGrowCell('condition', it.condition, 'condition')}${dash}</td>
            <td>${invGrowCell('remarks', it.remarks)}${dash}</td>
            <td class="no-print"><input type="text" value="${escapeHtml(it.number_series||'')}" data-f="number_series">${dash}</td>
            <td class="no-print"><input type="text" value="${escapeHtml(it.estimated_life||'')}" data-f="estimated_life">${dash}</td>
            <td class="no-print">${isTitle ? '' : `<button type="button" class="btn btn-sm btn-outline-secondary" onclick="invOpenSubDetails(${i})" title="Unit breakdown"><i class="fas fa-list-ul"></i></button>`}</td>
        </tr>`;
    }).join('');
    autoGrowInvBatch(document.querySelectorAll('#invItemsBody .item-desc, #invItemsBody .inv-grow'));
    updateInvTotals();
    invRefreshSuggestLists();
}

function invRefreshSuggestLists() {
    const dl = (id, list) => { const el = document.getElementById(id); if (el) el.innerHTML = list.map(v => `<option value="${escapeHtml(v)}">`).join(''); };
    if (!document.getElementById('invDlClassification')) {
        const d1 = document.createElement('datalist'); d1.id = 'invDlClassification'; document.body.appendChild(d1);
        const d2 = document.createElement('datalist'); d2.id = 'invDlCondition'; document.body.appendChild(d2);
    }
    dl('invDlClassification', classificationList);
    dl('invDlCondition', conditionList);
}

function autoGrowInv(el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; }

// Calling autoGrowInv() in a tight loop — once per every growable column
// (Description, Unit, Tag No., Serial No., Accountable Officer, Position,
// Location, Classification, Condition, Remarks) on every row — is what was
// actually lagging the grid on a PO with a lot of items: each call both
// WRITES a height and immediately READS scrollHeight, and interleaving those
// forces the browser to run a full synchronous layout on every single
// iteration (a 50-item PO is 500 growable fields, so 500 forced reflows back
// to back). Splitting into two passes — collapse everything first, THEN
// measure and set every final height — still does the same amount of work
// but only forces the browser to actually lay things out twice total,
// regardless of how many fields there are.
function autoGrowInvBatch(elements) {
    const els = Array.from(elements);
    els.forEach(el => { el.style.height = 'auto'; });
    els.forEach(el => { el.style.height = el.scrollHeight + 'px'; });
}

// One shared popup for every suggestable grid textarea (Accountable Officer,
// Position, Location, Classification, Condition) — see the CSS comment on
// .inv-suggest-box for why one shared instance rather than one per row.
// position:fixed + a fresh getBoundingClientRect() on every open means it
// tracks the actual field on screen even though .insp-items-scroll can be
// scrolled independently of the page.
function invSuggestBox() {
    let box = document.getElementById('invGridSuggest');
    if (!box) {
        box = document.createElement('div');
        box.id = 'invGridSuggest';
        box.className = 'inv-suggest-box d-none';
        document.body.appendChild(box);
    }
    return box;
}
function invSuggestHide() { invSuggestBox().classList.add('d-none'); }
// Hides on any scroll (the grid's own internal one included) rather than
// tracking to follow it — a suggestion list drifting away from the field
// mid-scroll would be worse than it just closing.
document.addEventListener('scroll', invSuggestHide, true);

// onSelect (optional): called after el.value is set, for a caller that needs
// to react to the pick itself — the Personnel MR search box, for one, needs
// to actually run the search, not just fill its own field like a grid cell
// does. autoGrowInv only makes sense for the grid's own textareas, hence the
// tagName guard — a plain <input> (the MR search box) has no such thing to
// grow, and forcing a height style onto one would just look wrong.
function invFieldSuggest(el, listKey, onSelect) {
    const lists = {
        officers: officerList, positions: positionList, locations: locationList,
        classification: classificationList, condition: conditionList,
    };
    const list = lists[listKey] || [];
    const box = invSuggestBox();
    const q = el.value.trim().toLowerCase();
    // Capped at 50 for the same reason purchase_orders.php's own field
    // suggestions are — Accountable Officer alone is the full HRMIS personnel
    // list (9,000+ names), and dumping all of it into the DOM on focus before
    // anything is typed is the slow part, not the filtering.
    const matches = (q ? list.filter(v => v.toLowerCase().includes(q)) : list).slice(0, 50);
    if (!matches.length) { box.classList.add('d-none'); return; }
    box.innerHTML = matches.map(v => `<div class="inv-suggest-item"></div>`).join('');
    box.querySelectorAll('.inv-suggest-item').forEach((item, i) => {
        item.textContent = matches[i];
        // mousedown, not click — it fires before the textarea's own blur, so
        // the value is set while the field still technically has focus rather
        // than after invSuggestHide() has already run on blur.
        item.addEventListener('mousedown', (e) => {
            e.preventDefault();
            el.value = matches[i];
            if (el.tagName === 'TEXTAREA') autoGrowInv(el);
            box.classList.add('d-none');
            if (onSelect) onSelect(matches[i]);
        });
    });
    const r = el.getBoundingClientRect();
    box.style.left  = Math.round(r.left) + 'px';
    box.style.top   = Math.round(r.bottom + 2) + 'px';
    box.style.width = Math.round(Math.max(r.width, 220)) + 'px';
    box.classList.remove('d-none');
}

// renderInvItems() runs its own autoGrowInv() pass before openModal() ever
// shows #inventoryModal — the textareas are still display:none at that point
// (Bootstrap hasn't started the fade-in yet), so scrollHeight reads back 0
// and every row locks in at one line regardless of how long its Description
// actually is. Re-measuring once the modal has actually finished showing
// fixes the height for real, without touching the render-time pass that
// still matters for a description edited after the modal is already open.
document.getElementById('inventoryModal').addEventListener('shown.bs.modal', () => {
    autoGrowInvBatch(document.querySelectorAll('#invItemsBody .item-desc, #invItemsBody .inv-grow'));
});

function recalcInvRow(el) {
    const tr = el.closest('tr');
    const qtyEl = tr.querySelector('[data-f="quantity"]');
    const costEl = tr.querySelector('[data-f="unit_cost"]');
    const totalEl = tr.querySelector('[data-f="total_cost"]');
    const q = parseFloat(qtyEl.value) || 0;
    const c = parseFloat(costEl.value) || 0;
    const total = q * c;
    totalEl.value = money(total);

    // Classification recomputes live, in front of the operator, using the
    // SAME continuous cutoffs the server applies on save — never left
    // unclassified the way the legacy grid left every row but the last one.
    const pill = tr.querySelector('[data-f="inventory_category"]');
    let cat;
    if (c < 5000) cat = 'ICS LOW VALUE';
    else if (c < 49001) cat = 'ICS HIGH VALUE';
    else cat = 'PAR';
    pill.textContent = cat;
    pill.className = 'inv-cat-pill ' + invCatPillClass(cat);

    updateInvTotals();
}

function updateInvTotals() {
    // Title rows ('SET 1') don't count as items — same reasoning as the
    // worklist's item_count on the server.
    const rows = document.querySelectorAll('#invItemsBody tr:not(.inv-item-title)');
    let total = 0;
    rows.forEach(tr => { total += parseFloat((tr.querySelector('[data-f="total_cost"]').value || '0').replace(/,/g,'')) || 0; });
    document.getElementById('invTotalItems').textContent = rows.length;
    document.getElementById('invTotalValue').textContent = '₱' + money(total);
}

document.getElementById('invCheckAll').addEventListener('change', (e) => {
    // Title rows are excluded — their checkbox is hidden (.inv-item-title
    // input{display:none}) and there is nothing on one that Issue/Transfer
    // could ever act on.
    document.querySelectorAll('#invItemsBody tr:not(.inv-item-title) .inv-row-check')
        .forEach(c => c.checked = e.target.checked);
});

function invCollectItems() {
    return Array.from(document.querySelectorAll('#invItemsBody tr')).map(tr => ({
        item_id: tr.dataset.invId ? invItems[parseInt(tr.dataset.idx,10)].item_id : (invItems[parseInt(tr.dataset.idx,10)] || {}).item_id,
        items_description: tr.querySelector('[data-f="items_description"]').value.trim(),
        unit: tr.querySelector('[data-f="unit"]').value.trim(),
        quantity: tr.querySelector('[data-f="quantity"]').value,
        unit_cost: tr.querySelector('[data-f="unit_cost"]').value,
        tag_number: tr.querySelector('[data-f="tag_number"]').value.trim(),
        serial_number: tr.querySelector('[data-f="serial_number"]').value.trim(),
        accountable_officer: tr.querySelector('[data-f="accountable_officer"]').value.trim(),
        position: tr.querySelector('[data-f="position"]').value.trim(),
        location: tr.querySelector('[data-f="location"]').value.trim(),
        classification: tr.querySelector('[data-f="classification"]').value.trim(),
        condition: tr.querySelector('[data-f="condition"]').value.trim(),
        remarks: tr.querySelector('[data-f="remarks"]').value.trim(),
        number_series: tr.querySelector('[data-f="number_series"]').value.trim(),
        estimated_life: tr.querySelector('[data-f="estimated_life"]').value.trim(),
    }));
}

function invCheckedInvIds() {
    return Array.from(document.querySelectorAll('#invItemsBody tr'))
        .filter(tr => tr.querySelector('.inv-row-check').checked && tr.dataset.invId)
        .map(tr => parseInt(tr.dataset.invId, 10));
}

// ── Save ───────────────────────────────────────────────────────────────
document.getElementById('btnInvSave').addEventListener('click', async () => {
    if (invMode === 'create' && !invPoNumber) return showToast('Select a purchase order first.', 'warning');
    const items = invCollectItems();
    if (!items.length) return showToast('There are no items to save.', 'warning');

    const isManual = invMode === 'manual';
    const payload = {
        po_number: invPoNumber,
        manual: isManual,
        pr_number: isManual ? document.getElementById('manualPr').value.trim() : document.getElementById('sumPr').textContent.trim(),
        department: isManual ? document.getElementById('manualDept').value.trim() : document.getElementById('sumDept').textContent.trim(),
        category: isManual ? document.getElementById('manualCategory').value.trim() : document.getElementById('sumCategory').textContent.trim(),
        mode_of_procurement: document.getElementById('invMode').value.trim(),
        supplier_name: isManual ? document.getElementById('manualSupplier').value.trim() : document.getElementById('sumSupplier').textContent.trim(),
        supplier_address: '',
        purpose: document.getElementById('invPurpose').value.trim(),
        source_of_fund: document.getElementById('invSource').value.trim(),
        date_entry: document.getElementById('invDateEntry').value,
        initial: document.getElementById('invInitial').value.trim(),
        items,
    };

    try {
        const res = await fetch(apiUrl, {
            method: invMode === 'revise' ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const out = await res.json();
        if (!out.success) return showToast(out.message || 'Save failed.', 'error');
        showToast(out.message || 'Saved.', 'success');
        bootstrap.Modal.getInstance(document.getElementById('inventoryModal'))?.hide();
        loadList(); loadStats();
    } catch (err) {
        showToast('Save failed.', 'error');
        console.error(err);
    }
});

// ── Undo ──────────────────────────────────────────────────────────────
document.getElementById('btnInvUndo').addEventListener('click', async () => {
    const msg = invPrevious
        ? `Undo the last revision on PO ${invPoNumber}? This restores the version saved on ${fmtDate(invPrevious.date_created)}.`
        : `Undo — this removes PO ${invPoNumber} from Inventory entirely, and it will show as not yet received. Continue?`;
    if (!confirm(msg)) return;
    try {
        const res = await fetch(apiUrl, {
            method: 'PATCH', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'undo', po_number: invPoNumber }),
        });
        const out = await res.json();
        if (!out.success) return showToast(out.message || 'Undo failed.', 'error');
        showToast(out.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('inventoryModal'))?.hide();
        loadList(); loadStats();
    } catch (err) {
        showToast('Undo failed.', 'error');
        console.error(err);
    }
});

// ── Sub-details (unit breakdown) ────────────────────────────────────────
let invSubItemIdx = null;

function invOpenSubDetails(idx) {
    invSubItemIdx = idx;
    const it = invItems[idx];
    renderInvSubRows(it.sub_details && it.sub_details.length ? it.sub_details.map(s => ({
        items_description: s.items_description, unit_cost: s.unit_cost, serial_number: s.serial_number,
        tag_number: s.tag_number, accountable_officer: s.accountable_officer, location: s.location,
        condition: s.condition, remarks: s.remarks,
    })) : []);
    openModal('invSubModal');
}

document.getElementById('btnInvSubDetails').addEventListener('click', () => {
    const checked = Array.from(document.querySelectorAll('#invItemsBody tr')).findIndex(tr => tr.querySelector('.inv-row-check').checked);
    if (checked < 0) return showToast('Check one item row first.', 'warning');
    invOpenSubDetails(checked);
});

function renderInvSubRows(rows) {
    document.getElementById('invSubBody').innerHTML = rows.map(r => invSubRowHtml(r)).join('');
}
function invSubRowHtml(r) {
    r = r || {};
    return `<tr>
        <td><input type="text" value="${escapeHtml(r.items_description||'')}" data-sf="items_description"></td>
        <td><input type="number" step="0.01" value="${r.unit_cost ?? ''}" data-sf="unit_cost"></td>
        <td><input type="text" value="${escapeHtml(r.serial_number||'')}" data-sf="serial_number"></td>
        <td><input type="text" value="${escapeHtml(r.tag_number||'')}" data-sf="tag_number"></td>
        <td><input type="text" value="${escapeHtml(r.accountable_officer||'')}" data-sf="accountable_officer" list="invDlOfficers"></td>
        <td><input type="text" value="${escapeHtml(r.location||'')}" data-sf="location" list="invDlLocations"></td>
        <td><input type="text" value="${escapeHtml(r.condition||'')}" data-sf="condition" list="invDlCondition"></td>
        <td><input type="text" value="${escapeHtml(r.remarks||'')}" data-sf="remarks"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-trash"></i></button></td>
    </tr>`;
}
document.getElementById('btnInvSubAdd').addEventListener('click', () => {
    document.getElementById('invSubBody').insertAdjacentHTML('beforeend', invSubRowHtml({}));
});

document.getElementById('btnInvSubSave').addEventListener('click', async () => {
    const it = invItems[invSubItemIdx];
    if (!it || !it.inv_id) return showToast('Save the record first, then add a unit breakdown.', 'warning');
    const rows = Array.from(document.querySelectorAll('#invSubBody tr')).map(tr => ({
        items_description: tr.querySelector('[data-sf="items_description"]').value.trim(),
        unit_cost: tr.querySelector('[data-sf="unit_cost"]').value,
        serial_number: tr.querySelector('[data-sf="serial_number"]').value.trim(),
        tag_number: tr.querySelector('[data-sf="tag_number"]').value.trim(),
        accountable_officer: tr.querySelector('[data-sf="accountable_officer"]').value.trim(),
        location: tr.querySelector('[data-sf="location"]').value.trim(),
        condition: tr.querySelector('[data-sf="condition"]').value.trim(),
        remarks: tr.querySelector('[data-sf="remarks"]').value.trim(),
    }));
    try {
        const res = await fetch(apiUrl, {
            method: 'PATCH', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sub_details', inv_id: it.inv_id, po_number: invPoNumber, rows }),
        });
        const out = await res.json();
        if (!out.success) return showToast(out.message || 'Save failed.', 'error');
        showToast('Unit breakdown saved.', 'success');
        it.sub_details = rows;
        bootstrap.Modal.getInstance(document.getElementById('invSubModal'))?.hide();
    } catch (err) {
        showToast('Save failed.', 'error');
        console.error(err);
    }
});

// ── Issue PAR / ICS ──────────────────────────────────────────────────────
document.getElementById('btnInvIssue').addEventListener('click', () => {
    const invIds = invCheckedInvIds();
    if (!invIds.length) return showToast('Check at least one item row first.', 'warning');

    const rows = Array.from(document.querySelectorAll('#invItemsBody tr')).filter(tr => invIds.includes(parseInt(tr.dataset.invId,10)));
    const cats = new Set(rows.map(tr => tr.querySelector('[data-f="inventory_category"]').textContent.trim()));
    document.getElementById('invIssueScope').textContent = `${invIds.length} item(s) selected on PO ${invPoNumber}.`;

    if (cats.size === 1) {
        const cat = [...cats][0];
        document.getElementById(cat === 'PAR' ? 'invDocPar' : 'invDocIcs').checked = true;
    }
    const numbers = new Set(rows.map(tr => {
        const p = tr.querySelector('.inv-number-pill'); return p && !p.classList.contains('empty') ? p.textContent.trim() : '';
    }).filter(Boolean));
    document.getElementById('invModeNew').checked = numbers.size === 0;
    document.getElementById('invModeReprint').disabled = numbers.size !== 1;
    document.getElementById('invModeRenew').disabled = numbers.size !== 1;
    if (numbers.size === 1) document.getElementById('invModeReprint').checked = true;

    document.getElementById('invIssueCgso').value = cgsoOfficer ? cgsoOfficer.name : '';
    document.getElementById('invIssueCgsoPosition').value = cgsoOfficer ? (cgsoOfficer.position || '') : '';
    document.getElementById('invIssuedResult').classList.add('d-none');
    document.getElementById('invIssueModal').dataset.invIds = JSON.stringify(invIds);
    openModal('invIssueModal');
});

document.getElementById('btnInvIssueGo').addEventListener('click', async () => {
    const invIds = JSON.parse(document.getElementById('invIssueModal').dataset.invIds || '[]');
    const docType = document.querySelector('input[name="invDocType"]:checked').value;
    const layout = document.querySelector('input[name="invLayout"]:checked').value;
    const mode = document.querySelector('input[name="invMode2"]:checked').value;

    try {
        const res = await fetch(issuanceUrl, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'issue', doc_type: docType, mode, po_number: invPoNumber, inv_ids: invIds }),
        });
        const out = await res.json();
        if (!out.success) return showToast(out.message || 'Could not issue.', 'error');

        document.getElementById('invIssuedNumber').textContent = out.number;
        document.getElementById('invIssuedResult').classList.remove('d-none');
        showToast(out.message, 'success');

        // Reflect the new/renewed number straight into the grid without a
        // full reload, then render the printable document from the same data.
        invIds.forEach(id => {
            const tr = document.querySelector(`#invItemsBody tr[data-inv-id="${id}"]`);
            if (!tr) return;
            const cell = tr.querySelector('td:nth-child(11)');
            cell.innerHTML = `<span class="inv-number-pill">${escapeHtml(out.number)}</span>`;
        });

        const rows = invIds.map(id => {
            const tr = document.querySelector(`#invItemsBody tr[data-inv-id="${id}"]`);
            return invRowToPrintData(tr);
        });
        const cgso = { name: document.getElementById('invIssueCgso').value.trim(), position: document.getElementById('invIssueCgsoPosition').value.trim() };
        // ICS carries a HIGH VALUE / LOW VALUE subtitle on the real form —
        // read straight off the rows being issued rather than asking the
        // operator to say it twice.
        const bucket = (rows[0] || {}).inventory_category === 'ICS LOW VALUE' ? 'LOW VALUE' : 'HIGH VALUE';
        await renderInvDocument(docType === 'par' ? 'par' : 'ics', {
            rows, number: out.number, layout, cgso, bucket,
            poNumber: invPoNumber,
            supplier: document.getElementById('sumSupplier').textContent.trim(),
            purpose: document.getElementById('invPurpose').value.trim(),
            sourceOfFund: document.getElementById('invSource').value.trim(),
            dateAcquired: document.getElementById('invDateEntry').value,
            initial: document.getElementById('invInitial').value.trim(),
        });
        loadList(); loadStats();
    } catch (err) {
        showToast('Could not issue.', 'error');
        console.error(err);
    }
});

// ── Transfer ──────────────────────────────────────────────────────────
document.getElementById('btnInvTransfer').addEventListener('click', () => {
    const invIds = invCheckedInvIds();
    if (!invIds.length) return showToast('Check at least one item row first.', 'warning');
    const rows = Array.from(document.querySelectorAll('#invItemsBody tr')).filter(tr => invIds.includes(parseInt(tr.dataset.invId,10)));
    document.getElementById('invTransferScope').textContent = `${invIds.length} item(s) selected on PO ${invPoNumber}.`;
    document.getElementById('invTransferFrom').value = rows[0].querySelector('[data-f="accountable_officer"]').value + ' · ' + rows[0].querySelector('[data-f="location"]').value;
    document.getElementById('invTransferToOfficer').value = '';
    document.getElementById('invTransferToPosition').value = '';
    document.getElementById('invTransferToLocation').value = '';
    document.getElementById('invTransferFundCluster').value = '';
    document.getElementById('invTransferReason').value = '';
    document.getElementById('invKindReassign').checked = true;
    document.getElementById('invTransferApprovedBy').value = cgsoOfficer ? cgsoOfficer.name : '';
    document.getElementById('invTransferIssuedBy').value = '';
    document.getElementById('invTransferIssuedPosition').value = '';
    document.getElementById('invTransferResult').classList.add('d-none');
    document.getElementById('invTransferModal').dataset.invIds = JSON.stringify(invIds);
    openModal('invTransferModal');
});

document.getElementById('btnInvTransferGo').addEventListener('click', async () => {
    const invIds = JSON.parse(document.getElementById('invTransferModal').dataset.invIds || '[]');
    const docType = document.querySelector('input[name="invTransferType"]:checked').value;
    const transferKind = document.querySelector('input[name="invTransferKind"]:checked').value;
    const toOfficer = document.getElementById('invTransferToOfficer').value.trim();
    if (!toOfficer) return showToast('The receiving officer is required.', 'warning');

    // The real ITR/PTR forms check one box — Donation / Reassignment /
    // Relocation / Other — separately from the free-text reason. Rather than
    // a new column, the checked box rides along as a bracketed tag on the
    // same Reason_for_Transfer text the transfer table already has.
    const reasonText = document.getElementById('invTransferReason').value.trim();
    const reason = '[' + transferKind + ']' + (reasonText ? ' ' + reasonText : '');

    const payload = {
        action: 'transfer', doc_type: docType, po_number: invPoNumber, inv_ids: invIds,
        to_officer: toOfficer,
        to_position: document.getElementById('invTransferToPosition').value.trim(),
        to_location: document.getElementById('invTransferToLocation').value.trim(),
        fund_cluster: document.getElementById('invTransferFundCluster').value.trim(),
        reason,
        approved_by: document.getElementById('invTransferApprovedBy').value.trim(),
        approved_position: cgsoOfficer ? (cgsoOfficer.position || '') : '',
        issued_by: document.getElementById('invTransferIssuedBy').value.trim(),
        issued_position: document.getElementById('invTransferIssuedPosition').value.trim(),
    };

    try {
        const res = await fetch(issuanceUrl, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
        });
        const out = await res.json();
        if (!out.success) return showToast(out.message || 'Transfer failed.', 'error');
        document.getElementById('invTransferNumber').textContent = out.number;
        document.getElementById('invTransferResult').classList.remove('d-none');
        showToast(out.message, 'success');

        const rows = invIds.map(id => {
            const tr = document.querySelector(`#invItemsBody tr[data-inv-id="${id}"]`);
            const d = invRowToPrintData(tr);
            return d;
        });
        await renderInvDocument(docType, {
            rows, number: out.number,
            from: document.getElementById('invTransferFrom').value,
            to: `${toOfficer} · ${document.getElementById('invTransferToLocation').value.trim()}`,
            toOfficer, toPosition: payload.to_position, toLocation: payload.to_location,
            fundCluster: payload.fund_cluster, reason: reasonText, transferKind,
            department: document.getElementById('sumDept').textContent.trim(),
            supplier: document.getElementById('sumSupplier').textContent.trim(),
            approvedBy: payload.approved_by, approvedPosition: payload.approved_position,
            issuedBy: payload.issued_by, issuedPosition: payload.issued_position,
            toOfficerPosition: payload.to_position,
            cgso: cgsoOfficer,
        });
        loadList(); loadStats();
    } catch (err) {
        showToast('Transfer failed.', 'error');
        console.error(err);
    }
});

// ── Return to Stock (PRS) ────────────────────────────────────────────────
document.getElementById('btnInvReturn').addEventListener('click', () => {
    const invIds = invCheckedInvIds();
    if (!invIds.length) return showToast('Check at least one item row first.', 'warning');
    const rows = Array.from(document.querySelectorAll('#invItemsBody tr')).filter(tr => invIds.includes(parseInt(tr.dataset.invId,10)));
    document.getElementById('invReturnScope').textContent = `${invIds.length} item(s) selected on PO ${invPoNumber}.`;
    document.getElementById('invReturnFrom').value = rows[0].querySelector('[data-f="accountable_officer"]').value + ' · ' + rows[0].querySelector('[data-f="location"]').value;
    document.getElementById('invReturnCondition').value = rows[0].querySelector('[data-f="condition"]').value || '';
    document.getElementById('invReturnToLocation').value = 'GSO Storage';
    document.getElementById('invReturnReason').value = '';
    document.getElementById('invReturnReceivedBy').value = cgsoOfficer ? cgsoOfficer.name : '';
    document.getElementById('invReturnReceivedPosition').value = cgsoOfficer ? (cgsoOfficer.position || '') : '';
    document.getElementById('invReturnApprovedBy').value = cgsoOfficer ? cgsoOfficer.name : '';
    document.getElementById('invReturnResult').classList.add('d-none');
    document.getElementById('invReturnModal').dataset.invIds = JSON.stringify(invIds);
    openModal('invReturnModal');
});

document.getElementById('btnInvReturnGo').addEventListener('click', async () => {
    const invIds = JSON.parse(document.getElementById('invReturnModal').dataset.invIds || '[]');
    const returnedTo = document.getElementById('invReturnToLocation').value.trim();
    if (!returnedTo) return showToast('Where the property is returned to is required.', 'warning');
    const fromValue = document.getElementById('invReturnFrom').value;
    const reasonText = document.getElementById('invReturnReason').value.trim();
    const condition = document.getElementById('invReturnCondition').value.trim();
    const receivedBy = document.getElementById('invReturnReceivedBy').value.trim();
    const receivedPosition = document.getElementById('invReturnReceivedPosition').value.trim();

    const payload = {
        action: 'prs', po_number: invPoNumber, inv_ids: invIds,
        returned_to: returnedTo, condition, reason: reasonText,
        received_by: receivedBy, received_position: receivedPosition,
        approved_by: document.getElementById('invReturnApprovedBy').value.trim(),
        approved_position: cgsoOfficer ? (cgsoOfficer.position || '') : '',
    };

    try {
        const res = await fetch(issuanceUrl, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
        });
        const out = await res.json();
        if (!out.success) return showToast(out.message || 'Return failed.', 'error');
        document.getElementById('invReturnNumber').textContent = out.number;
        document.getElementById('invReturnResult').classList.remove('d-none');
        showToast(out.message, 'success');

        const rows = invIds.map(id => {
            const tr = document.querySelector(`#invItemsBody tr[data-inv-id="${id}"]`);
            return invRowToPrintData(tr);
        });
        await renderInvDocument('prs', {
            rows, number: out.number, from: fromValue, reason: reasonText, condition,
            returnedTo, receivedBy, receivedPosition,
            approvedBy: payload.approved_by, approvedPosition: payload.approved_position,
            department: document.getElementById('sumDept').textContent.trim(),
            supplier: document.getElementById('sumSupplier').textContent.trim(),
            poNumber: invPoNumber,
            cgso: cgsoOfficer,
        });
        loadList(); loadStats();
    } catch (err) {
        showToast('Return failed.', 'error');
        console.error(err);
    }
});

// ── Transfer history ─────────────────────────────────────────────────────
document.getElementById('btnInvHistory').addEventListener('click', async () => {
    const body = document.getElementById('invHistoryBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    openModal('invHistoryModal');
    try {
        const res = await fetch(`${issuanceUrl}?transfers=1&po_number=${encodeURIComponent(invPoNumber)}`);
        const data = await res.json();
        if (!data.success) { body.innerHTML = `<div class="inv-empty">${escapeHtml(data.message||'Unable to load history.')}</div>`; return; }
        const transfers = data.transfers || [];
        if (!transfers.length) { body.innerHTML = '<div class="inv-empty">No transfers recorded yet for this purchase order.</div>'; return; }
        body.innerHTML = transfers.map(t => `
            <div class="inv-hist-item">
                <div class="inv-hist-num">${escapeHtml(t.transfer_type)} ${escapeHtml(t.transfer_number)}</div>
                <div class="inv-hist-meta">${fmtDate(t.date_transferred)} · printed by ${escapeHtml(t.printed_by||'—')}</div>
                <div class="inv-hist-flow"><span class="from">${escapeHtml(t.from_officer||'—')} (${escapeHtml(t.from_location||'—')})</span>
                    <i class="fas fa-arrow-right mx-1"></i>
                    <span class="to">${escapeHtml(t.to_officer||'—')} (${escapeHtml(t.to_location||'—')})</span></div>
                ${t.reason ? `<div class="inv-hist-meta">Reason: ${escapeHtml(t.reason)}</div>` : ''}
                <div class="inv-hist-meta">${(t.items||[]).length} item(s): ${(t.items||[]).map(i => escapeHtml(i.items_description)).join(', ')}</div>
            </div>`).join('');
    } catch (err) {
        body.innerHTML = '<div class="inv-empty">Unable to load history.</div>';
        console.error(err);
    }
});

// ── Property Returns list (cross-PO) ─────────────────────────────────────
let prsListData = [];
document.getElementById('btnPrsList').addEventListener('click', async () => {
    const body = document.getElementById('invPrsListBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    openModal('invPrsListModal');
    try {
        const res = await fetch(`${issuanceUrl}?prs_list=1`);
        const data = await res.json();
        if (!data.success) { body.innerHTML = `<div class="inv-empty">${escapeHtml(data.message||'Unable to load returns.')}</div>`; return; }
        prsListData = data.prs || [];
        if (!prsListData.length) { body.innerHTML = '<div class="inv-empty">No property has been returned to stock yet.</div>'; return; }
        body.innerHTML = prsListData.map((p, i) => `
            <div class="inv-hist-item">
                <div class="inv-hist-num">${escapeHtml(p.prs_number)}
                    <button type="button" class="btn btn-outline-secondary btn-sm float-end" onclick="prsReprint(${i})"><i class="fas fa-print me-1"></i>Reprint</button>
                </div>
                <div class="inv-hist-meta">${fmtDate(p.date_returned)} · PO ${escapeHtml(p.po_number||'—')} · printed by ${escapeHtml(p.printed_by||'—')}</div>
                <div class="inv-hist-flow"><span class="from">${escapeHtml(p.from_officer||'—')} (${escapeHtml(p.from_location||'—')})</span>
                    <i class="fas fa-arrow-right mx-1"></i>
                    <span class="to">${escapeHtml(p.returned_to||'—')}</span></div>
                ${p.reason ? `<div class="inv-hist-meta">${escapeHtml(p.reason)}</div>` : ''}
                <div class="inv-hist-meta">${(p.items||[]).length} item(s): ${(p.items||[]).map(x => escapeHtml(x.items_description)).join(', ')}</div>
            </div>`).join('');
    } catch (err) {
        body.innerHTML = '<div class="inv-empty">Unable to load returns.</div>';
        console.error(err);
    }
});

// Rebuilds a PRS's print payload entirely from the stored list data — this
// list is cross-PO, so unlike every other reprint path in this file there is
// no guarantee the item grid behind it (if any is even open) belongs to the
// same purchase order.
function prsReprint(idx) {
    const p = prsListData[idx];
    if (!p) return;
    const rows = (p.items || []).map(it => ({
        items_description: it.items_description, unit: '', quantity: it.quantity,
        unit_cost: it.unit_cost, tag_number: it.tag_number, serial_number: it.serial_number,
        po_number: p.po_number,
    }));
    renderInvDocument('prs', {
        rows, number: p.prs_number,
        from: `${p.from_officer || ''} · ${p.from_location || ''}`,
        reason: p.reason || '', condition: p.condition_on_return || '',
        returnedTo: p.returned_to || '', receivedBy: p.received_by || '', receivedPosition: p.received_position || '',
        approvedBy: p.approved_by || '', approvedPosition: p.approved_position || '',
        department: '', supplier: '', poNumber: p.po_number || '',
        cgso: cgsoOfficer,
    });
}

// ── Print: Property Card / Tag ──────────────────────────────────────────
document.getElementById('btnInvPrint').addEventListener('click', () => {
    const invIds = invCheckedInvIds();
    if (!invIds.length) return showToast('Check at least one item row first.', 'warning');
    document.getElementById('invPrintScope').textContent = `${invIds.length} item(s) selected on PO ${invPoNumber}.`;
    document.getElementById('invPrintModal').dataset.invIds = JSON.stringify(invIds);
    openModal('invPrintModal');
});

document.getElementById('btnInvPrintGo').addEventListener('click', async () => {
    const invIds = JSON.parse(document.getElementById('invPrintModal').dataset.invIds || '[]');
    const type = document.querySelector('input[name="invPrintType"]:checked').value;
    const rows = invIds.map(id => invRowToPrintData(document.querySelector(`#invItemsBody tr[data-inv-id="${id}"]`)));
    bootstrap.Modal.getInstance(document.getElementById('invPrintModal'))?.hide();
    await renderInvDocument(type, { rows, cgso: cgsoOfficer });
});

// ── Row -> print-data bridge ──────────────────────────────────────────────
function invRowToPrintData(tr) {
    if (!tr) return {};
    // Most cells are inputs (.value); the category cell is a plain pill span
    // (.textContent) — falling back covers both without two helpers.
    const f = (name) => {
        const el = tr.querySelector(`[data-f="${name}"]`);
        if (!el) return '';
        return ('value' in el) ? el.value : el.textContent.trim();
    };
    const pill = tr.querySelector('.inv-number-pill');
    return {
        inv_id: tr.dataset.invId ? parseInt(tr.dataset.invId, 10) : null,
        po_number: invPoNumber,
        par_ics_number: pill && !pill.classList.contains('empty') ? pill.textContent.trim() : '',
        inventory_category: f('inventory_category'),
        items_description: f('items_description'), unit: f('unit'), quantity: f('quantity'),
        unit_cost: f('unit_cost'), total_cost: (tr.querySelector('[data-f="total_cost"]')||{}).value,
        tag_number: f('tag_number'), serial_number: f('serial_number'),
        accountable_officer: f('accountable_officer'), position: f('position'), location: f('location'),
        classification: f('classification'), condition: f('condition'), remarks: f('remarks'),
        estimated_life: f('estimated_life'),
        date_acquired: document.getElementById('invDateEntry').value,
    };
}

// ── PDF rendering (shared) ──────────────────────────────────────────────
let invBlobUrl = null, invFileName = 'inventory.pdf';

async function renderInvDocument(docType, payload) {
    const titles = { par: 'Property Acknowledgement Receipt', ics: 'Inventory Custodian Slip',
        itr: 'Inventory Transfer Report', ptr: 'Property Transfer Report',
        card: 'Property Card', card_semi: 'Property Card (Semi-Expendable)',
        tag_big: 'Property Tag', tag_small: 'Property Tag (Small)',
        mr: 'Memorandum Receipt', prs: 'Property Return Slip' };
    document.getElementById('invPreviewTitle').innerHTML = '<i class="fas fa-file-pdf me-2"></i>' + escapeHtml(titles[docType] || 'Preview');
    document.getElementById('invPaperSize').value = 'auto';
    openModal('invPreviewModal');
    document.getElementById('invPreviewModal').addEventListener('shown.bs.modal', () => doRenderInv(docType, payload), { once: true });
}

async function doRenderInv(docType, payload) {
    const loading = document.getElementById('invPreviewLoading');
    const frame = document.getElementById('invPreviewFrame');
    const buttons = ['invDownload', 'invPrint', 'invOpenTab'].map(id => document.getElementById(id));
    frame.classList.add('d-none');
    buttons.forEach(b => b.disabled = true);
    loading.classList.remove('d-none');
    loading.innerHTML = '<div class="spinner-border text-primary" role="status"></div><div class="small mt-2">Generating PDF preview…</div>';
    document.getElementById('invLayoutStatus').innerHTML = '';

    try {
        let html;
        if (docType === 'par' || docType === 'ics') {
            html = InventoryPrint.buildIssuance(docType, payload);
        } else if (docType === 'itr' || docType === 'ptr') {
            html = InventoryPrint.buildTransfer(docType, payload);
        } else if (docType === 'card' || docType === 'card_semi') {
            // The card's ledger is the item's own transfer history — the same
            // record kept for the Transfer History dialog, not a second copy.
            const invId = (payload.rows[0] || {}).inv_id;
            let movements = [];
            if (invId && invPoNumber) {
                try {
                    const histRes = await fetch(`${issuanceUrl}?transfers=1&po_number=${encodeURIComponent(invPoNumber)}`);
                    const hist = await histRes.json();
                    // Loose equality: PDO can hand this back as a numeric
                    // string, and invId here is always a real number. Each
                    // transfer can cover several items — fold in just the
                    // quantity/cost this ONE item carried on that transfer,
                    // so the card's running balance is this item's, not the
                    // whole transfer's.
                    movements = (hist.transfers || [])
                        .map(t => ({ t, item: (t.items || []).find(i => i.inv_id == invId) }))
                        .filter(x => x.item)
                        .sort((a, b) => new Date(a.t.date_transferred) - new Date(b.t.date_transferred))
                        .map(x => Object.assign({}, x.t, { quantity: x.item.quantity, unit_cost: x.item.unit_cost }));
                } catch (e) { /* the card still prints without a ledger */ }
            }
            html = InventoryPrint.buildPropertyCard(Object.assign({}, payload, { movements }), docType === 'card_semi');
        } else if (docType === 'mr') {
            html = InventoryPrint.buildMR(payload);
        } else if (docType === 'prs') {
            html = InventoryPrint.buildPRS(payload);
        } else {
            // The tag's barcode encodes the PAR/ICS number — same rule the
            // legacy tag button used (fontencoder.Code128 on that same cell).
            const num = (payload.rows[0] || {}).par_ics_number;
            let barcodePng = '', barcodeWidth = 0;
            if (num) {
                try {
                    const bRes = await fetch(`${apiUrl}?barcode=${encodeURIComponent(num)}`);
                    const b = await bRes.json();
                    if (b.success) { barcodePng = b.png; barcodeWidth = b.width; }
                } catch (e) { /* the tag still prints without a scannable barcode */ }
            }
            html = InventoryPrint.buildTag(Object.assign({}, payload, { barcodePng, barcodeWidth }), docType === 'tag_small');
        }

        document.getElementById('invSheetArea').innerHTML = html;
        if (!window.PrintPdf || typeof PrintPdf.renderToPdf !== 'function') {
            throw new Error('the print engine did not load, or your browser is holding an old copy of it. Reload with Ctrl+F5 and try again.');
        }
        const source = document.querySelector('#invSheetArea .inv-sheet');
        const result = await PrintPdf.renderToPdf(source, {
            paper: document.getElementById('invPaperSize').value,
            breakSelector: '.inv-items tr',
            gridSelector: '.inv-items',
        });
        const info = PrintPdf.describeLayout(result.layout);
        document.getElementById('invLayoutStatus').innerHTML =
            '<span>' + escapeHtml(info.paper) + '</span><span class="pp-sheets">' + escapeHtml(info.sheetsLabel) + '</span>'
            + (info.note ? '<span class="pp-layout-note">' + escapeHtml(info.note) + '</span>' : '');

        if (invBlobUrl) URL.revokeObjectURL(invBlobUrl);
        invBlobUrl = URL.createObjectURL(result.blob);
        // MR isn't scoped to a PO the way every other doc type here is —
        // invPoNumber would be whatever (if anything) was last open in the
        // Receive/View modal, not this document's own subject.
        invFileName = docType === 'mr'
            ? 'MR_' + (payload.officer || 'personnel').replace(/[^A-Za-z0-9]+/g, '_') + '.pdf'
            : docType.toUpperCase() + '_' + (invPoNumber || 'inventory') + '.pdf';
        frame.src = invBlobUrl + '#navpanes=0&toolbar=1&view=FitH';
        loading.classList.add('d-none');
        frame.classList.remove('d-none');
        buttons.forEach(b => b.disabled = false);
    } catch (err) {
        loading.innerHTML = '<div class="text-danger small px-3"><i class="fas fa-triangle-exclamation me-1"></i>Unable to generate the PDF: ' + escapeHtml(err.message||String(err)) + '</div>';
        console.error(err);
    }
}
document.getElementById('invPaperSize').addEventListener('change', () => {
    // Re-render is cheap and the data is already in the sheet area; simplest
    // to just leave the current render — paper choice mainly matters at
    // export/print time via the same recompute the other pages use.
});
document.getElementById('invDownload').addEventListener('click', () => {
    if (!invBlobUrl) return;
    const a = document.createElement('a'); a.href = invBlobUrl; a.download = invFileName;
    document.body.appendChild(a); a.click(); a.remove();
});
document.getElementById('invPrint').addEventListener('click', () => {
    const frame = document.getElementById('invPreviewFrame');
    try { frame.contentWindow.focus(); frame.contentWindow.print(); }
    catch (err) { if (invBlobUrl) window.open(invBlobUrl, '_blank'); }
});
document.getElementById('invOpenTab').addEventListener('click', () => { if (invBlobUrl) window.open(invBlobUrl, '_blank'); });

// ── Boot ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnFilter').addEventListener('click', loadList);
    document.getElementById('filterSearch').addEventListener('keydown', (e) => { if (e.key === 'Enter') loadList(); });

    const q = new URLSearchParams(location.search);
    if (q.get('po_number')) openInventory(q.get('po_number'), q.get('view') ? 'view' : 'view');

    loadSignatories();
    loadStats();
    loadList();
});
</script>
<?php include 'assistant.php'; ?>
</body>
</html>
