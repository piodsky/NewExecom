<?php
require_once 'config.php';
requireModule('system_guide');
$currentUser = getCurrentUser();
$activePage  = 'system_guide';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Guide | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.guide-lede { color: var(--muted); max-width: 70ch; }
.guide-mono { font-family: Consolas, 'SFMono-Regular', monospace; font-size: .82em; }

.guide-dgm { overflow-x: auto; }
.guide-dgm svg { display: block; min-width: 760px; }
.n-box       { fill: #f8fafc; stroke: var(--border); }
.n-box.emph  { fill: #dbeafe; stroke: var(--primary); }
.n-title     { font-size: 13px; font-weight: 600; fill: var(--text); }
.n-title.emph{ fill: var(--primary); }
.n-sub       { font-size: 10.5px; fill: var(--muted); }
.e-line      { stroke: #94a3b8; stroke-width: 1.6; fill: none; }
.e-line.dashed { stroke-dasharray: 4 4; }
.e-label     { font-family: Consolas, monospace; font-size: 10px; fill: var(--muted); }
.row-label   { font-family: Consolas, monospace; font-size: 11px; letter-spacing: .06em; fill: var(--muted); }

.st-box { stroke-width: 1.4; }
.st-in_stock   { fill: #dcfce7; stroke: #166534; }
.st-reserved   { fill: #fef3c7; stroke: #92400e; }
.st-issued     { fill: #e5e7eb; stroke: #374151; }
.st-in_transit { fill: #dbeafe; stroke: #1e40af; }
.st-returned   { fill: #ede9fe; stroke: #5b21b6; }
.st-defective  { fill: #fee2e2; stroke: #991b1b; }
.st-text       { font-size: 12px; font-weight: 600; }

.guide-note {
    border: 1px solid var(--border); border-left: 3px solid var(--primary);
    background: #f8fafc; border-radius: 0 8px 8px 0; padding: .9rem 1.1rem;
    font-size: .85rem; margin-top: .75rem;
}
.guide-jump { flex-wrap: wrap; }

.badge-status.in_stock  { background: #dcfce7; color: #166534; }
.badge-status.reserved  { background: #fef3c7; color: #92400e; }
.badge-status.issued    { background: #e5e7eb; color: #374151; }
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
                    <h2 class="page-title">System Guide</h2>
                    <div class="text-muted small">Quick reference for modules, roles, workflows, and system rules.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">

        <div class="panel mb-3">
            <div class="panel-body">
                <p class="guide-lede mb-2">From a purchase requisition to a barcode scan at checkout — this is the document chain, the inventory model, and where serial numbers actually live in <?= e(APP_NAME) ?>.</p>
                <div class="d-flex guide-jump gap-2">
                    <a href="#flow" class="btn btn-light btn-sm"><i class="fas fa-route me-1"></i>Document chain</a>
                    <a href="#inventory" class="btn btn-light btn-sm"><i class="fas fa-warehouse me-1"></i>Inside Inventory</a>
                    <a href="#warehousemap" class="btn btn-light btn-sm"><i class="fas fa-map-location-dot me-1"></i>Warehouse Map</a>
                    <a href="#example" class="btn btn-light btn-sm"><i class="fas fa-desktop me-1"></i>Worked example</a>
                    <a href="#billing" class="btn btn-light btn-sm"><i class="fas fa-file-invoice-dollar me-1"></i>Billing &amp; Payables</a>
                    <a href="#reference" class="btn btn-light btn-sm"><i class="fas fa-list-check me-1"></i>Module reference</a>
                </div>
            </div>
        </div>

        <div class="panel mb-3" id="flow">
            <div class="panel-header"><div class="panel-title">1. The document chain, end to end</div></div>
            <div class="panel-body">
                <p class="guide-lede">Two paper trails meet at one shelf. Buying brings stock <em>into</em> Inventory; selling and branch transfers take it back <em>out</em> — every serialized item passes through the same middle box.</p>

                <div class="guide-dgm">
                <svg viewBox="0 0 1230 470" role="img" aria-label="A Purchase Order (Internal) goes directly to a supplier, then a Receiving Report assigns serial numbers into Inventory. From Inventory, stock either moves to another branch via Stock Transfer, or is scanned out at Issuance POS. Separately, an Outgoing RFQ leads to a Quotation and an Outgoing PO, which fulfills at Issuance and closes via a Delivery Report.">
                  <defs>
                    <marker id="arA" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                      <polygon points="0,0 10,5 0,10" fill="#94a3b8"/>
                    </marker>
                  </defs>

                  <text x="10" y="238" class="row-label">BUYING &#183; DIRECT PURCHASE ORDER</text>
                  <text x="620" y="98" class="row-label">SELLING &#183; OUTGOING PAPERWORK</text>

                  <g>
                    <rect class="n-box" x="10" y="260" width="180" height="64" rx="9"/>
                    <text class="n-title" x="100" y="288" text-anchor="middle">PO</text>
                    <text class="n-sub" x="100" y="303" text-anchor="middle">Internal &#183; direct to supplier</text>
                  </g>
                  <g>
                    <rect class="n-box" x="250" y="260" width="200" height="64" rx="9"/>
                    <text class="n-title" x="350" y="288" text-anchor="middle">Receiving Report</text>
                    <text class="n-sub" x="350" y="303" text-anchor="middle">serials entered here</text>
                  </g>
                  <g>
                    <rect class="n-box emph" x="620" y="244" width="190" height="96" rx="10"/>
                    <text class="n-title emph" x="715" y="284" text-anchor="middle" font-size="14">INVENTORY</text>
                    <text class="n-sub" x="715" y="301" text-anchor="middle">item &#215; serial, per branch</text>
                    <text class="n-sub" x="715" y="315" text-anchor="middle">category &#8594; item &#8594; unit</text>
                  </g>
                  <g>
                    <rect class="n-box" x="840" y="260" width="170" height="64" rx="9"/>
                    <text class="n-title" x="925" y="288" text-anchor="middle">Issuance</text>
                    <text class="n-sub" x="925" y="303" text-anchor="middle">POS &#183; barcode scan-out</text>
                  </g>

                  <line class="e-line" x1="190" y1="292" x2="246" y2="292" marker-end="url(#arA)"/>
                  <line class="e-line" x1="450" y1="292" x2="616" y2="292" marker-end="url(#arA)"/>
                  <text class="e-label" x="533" y="278" text-anchor="middle">serial in</text>
                  <line class="e-line" x1="810" y1="292" x2="836" y2="292" marker-end="url(#arA)"/>
                  <text class="e-label" x="823" y="278" text-anchor="middle">scan out</text>

                  <g>
                    <rect class="n-box" x="620" y="110" width="110" height="56" rx="9"/>
                    <text class="n-title" x="675" y="135" text-anchor="middle">RFQ</text>
                    <text class="n-sub" x="675" y="149" text-anchor="middle">Outgoing</text>
                  </g>
                  <g>
                    <rect class="n-box" x="750" y="110" width="130" height="56" rx="9"/>
                    <text class="n-title" x="815" y="135" text-anchor="middle">Quotation</text>
                    <text class="n-sub" x="815" y="149" text-anchor="middle">priced offer</text>
                  </g>
                  <g>
                    <rect class="n-box" x="900" y="110" width="140" height="56" rx="9"/>
                    <text class="n-title" x="970" y="135" text-anchor="middle">PO</text>
                    <text class="n-sub" x="970" y="149" text-anchor="middle">Outgoing &#183; awarded</text>
                  </g>

                  <line class="e-line" x1="730" y1="138" x2="746" y2="138" marker-end="url(#arA)"/>
                  <line class="e-line" x1="880" y1="138" x2="896" y2="138" marker-end="url(#arA)"/>

                  <path class="e-line dashed" d="M 950 166 L 925 258" marker-end="url(#arA)"/>
                  <text class="e-label" x="905" y="215" text-anchor="middle">fulfills</text>
                  <path class="e-line dashed" d="M 990 166 L 1120 258" marker-end="url(#arA)"/>
                  <text class="e-label" x="1075" y="215" text-anchor="middle">closes</text>

                  <g>
                    <rect class="n-box" x="1040" y="260" width="170" height="64" rx="9"/>
                    <text class="n-title" x="1125" y="288" text-anchor="middle">Delivery Report</text>
                    <text class="n-sub" x="1125" y="303" text-anchor="middle">client sign-off</text>
                  </g>

                  <line class="e-line" x1="700" y1="340" x2="700" y2="376" marker-end="url(#arA)"/>
                  <text class="e-label" x="656" y="360" text-anchor="end">sends</text>
                  <line class="e-line" x1="730" y1="376" x2="730" y2="340" marker-end="url(#arA)"/>
                  <text class="e-label" x="774" y="360" text-anchor="start">receives</text>
                  <g>
                    <rect class="n-box" x="630" y="380" width="190" height="60" rx="9"/>
                    <text class="n-title" x="725" y="406" text-anchor="middle">Stock Transfer</text>
                    <text class="n-sub" x="725" y="421" text-anchor="middle">same unit, other branch</text>
                  </g>
                </svg>
                </div>
                <p class="text-muted small mt-2 mb-0">Solid arrows move stock or a serial number; dashed arrows are paperwork references only. Stock Transfer never creates a new inventory row — it just re-points an existing serialized unit at a different branch.</p>

                <div class="guide-note"><b>Worth knowing:</b> Issuance (the POS screen, barcode-driven) and Delivery Report (the signed proof-of-delivery document) are <em>siblings</em>, not a pipeline — both reference the same Outgoing PO, but neither is built from the other. Issuance is what actually flips a serial's status; Delivery Report is the paperwork that closes the sale.</div>

                <div class="guide-note"><b>Don't trace this by hand:</b> <a href="order_tracking.php">Order Tracking</a> picks up any PO (Internal) or Outgoing RFQ and renders its whole chain above automatically — every RFQ, PO, Receiving Report, Delivery Report and Issuance that came from it, with live status and quantities. Every document number it shows is color-coded by type (Internal PO violet, Outgoing PO orange, and so on) and clicking one jumps straight to that record on its own page.</div>

                <div class="guide-note"><b>Why there's no requisition/canvass step on the buying side:</b> EXECOM buys directly from its own known suppliers — there's no multi-department approval chain to route through before a PO goes out, unlike the outgoing side below, which exists specifically to satisfy LGU procurement law on the sales side. This system originally had one (Purchase Requisition &#8594; RFQ Internal &#8594; quote comparison), modeled after that same LGU process, but it was removed to match how the business actually buys — a direct Purchase Order, same as the old Globalchips system's own purchase entry.</div>
            </div>
        </div>

        <div class="panel mb-3" id="inventory">
            <div class="panel-header"><div class="panel-title">2. Inside Inventory: category &#8594; item &#8594; serialized unit</div></div>
            <div class="panel-body">
                <p class="guide-lede">Three levels, already in the schema: an <span class="guide-mono">item_categories</span> row (e.g. "Monitor"), an <span class="guide-mono">items</span> row per sellable model/brand, and an <span class="guide-mono">inventory_units</span> row per physical, serialized piece.</p>

                <div class="guide-dgm">
                <svg viewBox="0 0 960 420" role="img" aria-label="A Category such as Monitor contains many Items such as a 21 inch model and a 17 inch model, and each Item contains many serialized Units. On the right, a unit's status moves from in stock to reserved to issued, or from in stock to in transit and back, or from issued to returned to defective.">
                  <defs>
                    <marker id="arB" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                      <polygon points="0,0 10,5 0,10" fill="#94a3b8"/>
                    </marker>
                  </defs>

                  <text x="20" y="14" class="row-label">CATALOG HIERARCHY</text>
                  <g>
                    <rect class="n-box emph" x="30" y="22" width="220" height="50" rx="9"/>
                    <text class="n-title emph" x="140" y="43" text-anchor="middle">Category</text>
                    <text class="n-sub" x="140" y="58" text-anchor="middle">"Monitor"</text>
                  </g>

                  <line class="e-line" x1="110" y1="72" x2="82" y2="128" marker-end="url(#arB)"/>
                  <line class="e-line" x1="180" y1="72" x2="205" y2="128" marker-end="url(#arB)"/>
                  <text class="e-label" x="140" y="100" text-anchor="middle">1 &#8594; many</text>

                  <g>
                    <rect class="n-box" x="30" y="130" width="105" height="50" rx="8"/>
                    <text class="n-title" x="82" y="151" text-anchor="middle" font-size="12">Item &#183; 21&#8243;</text>
                    <text class="n-sub" x="82" y="166" text-anchor="middle" font-size="9.5">SKU MON-21-&#8230;</text>
                  </g>
                  <g>
                    <rect class="n-box" x="152" y="130" width="105" height="50" rx="8"/>
                    <text class="n-title" x="205" y="151" text-anchor="middle" font-size="12">Item &#183; 17&#8243;</text>
                    <text class="n-sub" x="205" y="166" text-anchor="middle" font-size="9.5">SKU MON-17-&#8230;</text>
                  </g>

                  <line class="e-line" x1="82" y1="180" x2="82" y2="222" marker-end="url(#arB)"/>
                  <line class="e-line" x1="205" y1="180" x2="205" y2="222" marker-end="url(#arB)"/>
                  <text class="e-label" x="140" y="205" text-anchor="middle">1 &#8594; many, serialized</text>

                  <rect class="n-box" x="42" y="236" width="80" height="36" rx="6"/>
                  <rect class="n-box" x="36" y="230" width="80" height="36" rx="6"/>
                  <rect class="n-box" x="30" y="224" width="80" height="36" rx="6"/>
                  <text class="n-sub" x="70" y="246" text-anchor="middle" font-size="9">SN 000114</text>

                  <rect class="n-box" x="165" y="236" width="80" height="36" rx="6"/>
                  <rect class="n-box" x="159" y="230" width="80" height="36" rx="6"/>
                  <rect class="n-box" x="153" y="224" width="80" height="36" rx="6"/>
                  <text class="n-sub" x="193" y="246" text-anchor="middle" font-size="9">SN 000201</text>

                  <text x="30" y="300" class="n-sub" font-size="11">Each physical unit gets its own row:</text>
                  <text x="30" y="316" class="n-sub" font-size="11">one serial number, one status, one branch.</text>

                  <text x="460" y="14" class="row-label">A UNIT'S STATUS</text>

                  <rect class="st-box st-in_stock" x="460" y="22" width="140" height="50" rx="10"/>
                  <text class="st-text" x="530" y="52" text-anchor="middle" fill="#166534">in_stock</text>

                  <rect class="st-box st-reserved" x="700" y="22" width="140" height="50" rx="10"/>
                  <text class="st-text" x="770" y="52" text-anchor="middle" fill="#92400e">reserved</text>

                  <rect class="st-box st-in_transit" x="460" y="132" width="140" height="50" rx="10"/>
                  <text class="st-text" x="530" y="162" text-anchor="middle" fill="#1e40af">in_transit</text>

                  <rect class="st-box st-issued" x="700" y="132" width="140" height="50" rx="10"/>
                  <text class="st-text" x="770" y="162" text-anchor="middle" fill="#374151">issued</text>

                  <rect class="st-box st-returned" x="700" y="242" width="140" height="50" rx="10"/>
                  <text class="st-text" x="770" y="272" text-anchor="middle" fill="#5b21b6">returned</text>

                  <rect class="st-box st-defective" x="700" y="322" width="140" height="50" rx="10"/>
                  <text class="st-text" x="770" y="352" text-anchor="middle" fill="#991b1b">defective</text>

                  <line class="e-line" x1="600" y1="47" x2="696" y2="47" marker-end="url(#arB)"/>
                  <text class="e-label" x="648" y="40" text-anchor="middle">reserved for order</text>

                  <line class="e-line" x1="770" y1="72" x2="770" y2="128" marker-end="url(#arB)"/>
                  <text class="e-label" x="784" y="103" text-anchor="start">scanned out</text>

                  <path class="e-line dashed" d="M 560 72 L 715 128" marker-end="url(#arB)"/>
                  <text class="e-label" x="600" y="112" text-anchor="middle">walk-in scan</text>

                  <line class="e-line" x1="510" y1="72" x2="510" y2="128" marker-end="url(#arB)"/>
                  <text class="e-label" x="466" y="103" text-anchor="end">transfer</text>
                  <line class="e-line" x1="550" y1="128" x2="550" y2="72" marker-end="url(#arB)"/>
                  <text class="e-label" x="594" y="103" text-anchor="start">arrived</text>

                  <line class="e-line" x1="770" y1="182" x2="770" y2="238" marker-end="url(#arB)"/>
                  <text class="e-label" x="784" y="213" text-anchor="start">customer return</text>

                  <line class="e-line" x1="770" y1="292" x2="770" y2="318" marker-end="url(#arB)"/>
                  <text class="e-label" x="784" y="308" text-anchor="start">failed inspection</text>
                </svg>
                </div>
                <p class="text-muted small mt-2 mb-0">Left: how "Monitor" fans out into sellable models and then into individually-serialized units. Right: the six statuses a unit actually carries in the database (<span class="guide-mono">inventory_units.status</span>) — this is what the Inventory page's status filter is filtering on.</p>

                <div class="guide-note"><b>Where the barcode actually is:</b> there's no separate barcode table or field — the printed label <em>is</em> the SKU (for a bulk item) or the serial number (for a serialized unit). Receiving Reports is where a serial gets typed in for the first time, one row per physical unit; Issuance POS is where a scanner types that same string back in to look the unit up and flip it to <span class="guide-mono">issued</span>.</div>
            </div>
        </div>

        <div class="panel mb-3" id="warehousemap">
            <div class="panel-header"><div class="panel-title">3. Warehouse Map: where a unit physically sits</div></div>
            <div class="panel-body">
                <p class="guide-lede">Inventory answers "how many, of what, at which branch." <a href="warehouse.php">Warehouse Map</a> answers the next question — "which shelf" — without changing how anything gets received. A unit or a pooled bulk line always becomes an inventory row first; placing it on the map is a separate step afterward, never the other way around.</p>

                <div class="guide-dgm">
                <svg viewBox="0 0 900 300" role="img" aria-label="A Receiving Report, Manual Add, or Quick Scan creates an inventory row that starts unplaced. Someone then places it into a bin on the Warehouse Map's floor plan. Once Issuance or Stock Transfer moves that unit out of stock, it automatically drops off the map with no extra step.">
                  <defs>
                    <marker id="arC" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                      <polygon points="0,0 10,5 0,10" fill="#94a3b8"/>
                    </marker>
                  </defs>

                  <text x="10" y="18" class="row-label">FROM RECEIVING TO A SHELF, AND BACK OUT</text>

                  <g>
                    <rect class="n-box" x="10" y="40" width="160" height="60" rx="9"/>
                    <text class="n-title" x="90" y="65" text-anchor="middle" font-size="12">Receiving Report /</text>
                    <text class="n-title" x="90" y="80" text-anchor="middle" font-size="12">Manual Add / Quick Scan</text>
                  </g>
                  <g>
                    <rect class="n-box" x="220" y="40" width="150" height="60" rx="9"/>
                    <text class="n-title" x="295" y="65" text-anchor="middle">Inventory row</text>
                    <text class="n-sub" x="295" y="82" text-anchor="middle">location_id = NULL</text>
                  </g>
                  <g>
                    <rect class="n-box emph" x="420" y="40" width="150" height="60" rx="9"/>
                    <text class="n-title emph" x="495" y="65" text-anchor="middle">"Unplaced" list</text>
                    <text class="n-sub" x="495" y="82" text-anchor="middle">on the Warehouse Map</text>
                  </g>
                  <g>
                    <rect class="n-box" x="620" y="40" width="150" height="60" rx="9"/>
                    <text class="n-title" x="695" y="65" text-anchor="middle">Bin</text>
                    <text class="n-sub" x="695" y="82" text-anchor="middle">e.g. A-01</text>
                  </g>

                  <line class="e-line" x1="170" y1="70" x2="216" y2="70" marker-end="url(#arC)"/>
                  <line class="e-line" x1="370" y1="70" x2="416" y2="70" marker-end="url(#arC)"/>
                  <line class="e-line" x1="570" y1="70" x2="616" y2="70" marker-end="url(#arC)"/>
                  <text class="e-label" x="593" y="56" text-anchor="middle">place</text>

                  <path class="e-line dashed" d="M 695 100 L 695 160 L 295 160 L 295 106" marker-end="url(#arC)"/>
                  <text class="e-label" x="495" y="150" text-anchor="middle">Issuance or Stock Transfer moves it out &#8594; drops off the map automatically, no extra step</text>

                  <text x="10" y="210" class="row-label">THE FLOOR PLAN ITSELF</text>
                  <g>
                    <rect x="10" y="228" width="90" height="50" rx="6" fill="#eff6ff" stroke="#93c5fd" stroke-width="2"/>
                    <text class="n-sub" x="55" y="248" text-anchor="middle" font-weight="700">A-01</text>
                    <text class="n-sub" x="55" y="263" text-anchor="middle" font-size="9">2 units</text>
                  </g>
                  <rect x="112" y="228" width="90" height="50" rx="6" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2" stroke-dasharray="4 3"/>
                  <text class="n-sub" x="157" y="253" text-anchor="middle" font-size="9">A-02 &#183; empty</text>
                  <rect x="214" y="228" width="90" height="50" rx="6" fill="#fef2f2" stroke="#fca5a5" stroke-width="2"/>
                  <text class="n-sub" x="259" y="248" text-anchor="middle" font-weight="700">A-03</text>
                  <text class="n-sub" x="259" y="263" text-anchor="middle" font-size="9">at capacity</text>
                  <rect x="316" y="228" width="90" height="50" rx="6" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2" stroke-dasharray="4 3"/>
                  <text class="n-sub" x="361" y="253" text-anchor="middle" font-size="9">B-01 &#183; empty</text>
                  <text x="440" y="250" class="n-sub" font-size="11">grid_row / grid_col place each bin here — they don't need to be exact,</text>
                  <text x="440" y="266" class="n-sub" font-size="11">just relative to the bins around them.</text>
                </svg>
                </div>

                <div class="guide-note"><b>Capacity only counts serialized units.</b> A bin's optional capacity limits how many <span class="guide-mono">inventory_units</span> rows it can hold — a bulk item's pooled quantity (e.g. "6 pc of RAM") is shown alongside for context but never blocks placement, since one bulk SKU isn't "one item, one slot" the way a serialized unit is.</div>
            </div>
        </div>

        <div class="panel mb-3" id="example">
            <div class="panel-header"><div class="panel-title">4. Worked example: monitors</div></div>
            <div class="panel-body">
                <p class="guide-lede">One category, two sellable models, and what a handful of physical units under one model would actually look like.</p>
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Level</th><th>Record</th><th>SKU / Serial</th><th>Branch</th><th>Status</th></tr></thead>
                    <tbody>
                        <tr><td>Category</td><td>Monitor <span class="text-muted small">(serial-tracked by default)</span></td><td class="guide-mono">&#8212;</td><td>&#8212;</td><td>&#8212;</td></tr>
                        <tr><td>Item</td><td>21&#8243; LED Monitor &#8212; Vixen</td><td class="guide-mono">MON-21-VX</td><td>&#8212;</td><td>&#8212;</td></tr>
                        <tr><td>Item</td><td>17&#8243; LED Monitor &#8212; Vixen</td><td class="guide-mono">MON-17-VX</td><td>&#8212;</td><td>&#8212;</td></tr>
                        <tr><td>Unit</td><td>&#8618; of MON-21-VX</td><td class="guide-mono">SN VX21-000114</td><td>Main Branch</td><td><span class="badge-status in_stock">In Stock</span></td></tr>
                        <tr><td>Unit</td><td>&#8618; of MON-21-VX</td><td class="guide-mono">SN VX21-000115</td><td>Main Branch</td><td><span class="badge-status reserved">Reserved &#183; PO-2026-0043</span></td></tr>
                        <tr><td>Unit</td><td>&#8618; of MON-21-VX</td><td class="guide-mono">SN VX21-000116</td><td>Cebu Branch</td><td><span class="badge-status issued">Issued</span></td></tr>
                    </tbody>
                </table>
                </div>
                <p class="text-muted small mt-2 mb-0">A third brand ("Gambit", or whatever's actually on the shelf) is just another <span class="guide-mono">items</span> row under the same "Monitor" category — its own SKU, its own default price, and every unit sold under it gets its own serial the moment it's receipted in.</p>
            </div>
        </div>

        <div class="panel mb-3" id="billing">
            <div class="panel-header"><div class="panel-title">5. Financial flow: Billing &amp; Payables</div></div>
            <div class="panel-body">
                <p class="guide-lede">Two more paper trails, added after the ones above: money clients owe <em>us</em>, and money <em>we</em> owe suppliers. Both follow the same shape — an invoice with a balance, then a payment applied against it — mirroring how the old Globalchips system tracked both sides.</p>

                <div class="guide-dgm">
                <svg viewBox="0 0 1150 320" role="img" aria-label="Accounts Payable: a Receiving Report becomes a Supplier Invoice, which is settled by a Disbursement, reaching Paid. Accounts Receivable: a credit Issuance or a released Job Order becomes a Sales Invoice, which is settled by a Collection (Official Receipt), reaching Paid. A shared Post-Dated Check register links to a Disbursement when issued to a supplier, or to a Collection when received from a client.">
                  <defs>
                    <marker id="arD" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                      <polygon points="0,0 10,5 0,10" fill="#94a3b8"/>
                    </marker>
                  </defs>

                  <text x="10" y="30" class="row-label">PAYABLES (AP) &#183; WHAT WE OWE SUPPLIERS</text>
                  <g>
                    <rect class="n-box" x="10" y="50" width="180" height="64" rx="9"/>
                    <text class="n-title" x="100" y="76" text-anchor="middle">Receiving Report</text>
                    <text class="n-sub" x="100" y="92" text-anchor="middle">goods arrive &#8212; see Diagram 1</text>
                  </g>
                  <g>
                    <rect class="n-box" x="230" y="50" width="180" height="64" rx="9"/>
                    <text class="n-title" x="320" y="76" text-anchor="middle">Supplier Invoice</text>
                    <text class="n-sub" x="320" y="92" text-anchor="middle">Payables &#183; doc PINV</text>
                  </g>
                  <g>
                    <rect class="n-box" x="450" y="50" width="180" height="64" rx="9"/>
                    <text class="n-title" x="540" y="76" text-anchor="middle">Disbursement</text>
                    <text class="n-sub" x="540" y="92" text-anchor="middle">cash / check / bank &#183; doc DV</text>
                  </g>
                  <g>
                    <rect class="n-box emph" x="670" y="50" width="110" height="64" rx="9"/>
                    <text class="n-title emph" x="725" y="76" text-anchor="middle">Paid</text>
                    <text class="n-sub" x="725" y="92" text-anchor="middle">balance = 0</text>
                  </g>
                  <line class="e-line" x1="190" y1="82" x2="226" y2="82" marker-end="url(#arD)"/>
                  <line class="e-line" x1="410" y1="82" x2="446" y2="82" marker-end="url(#arD)"/>
                  <line class="e-line" x1="630" y1="82" x2="666" y2="82" marker-end="url(#arD)"/>

                  <text x="10" y="200" class="row-label">COLLECTIONS (AR) &#183; WHAT CLIENTS OWE US</text>
                  <g>
                    <rect class="n-box" x="10" y="220" width="200" height="64" rx="9"/>
                    <text class="n-title" x="110" y="246" text-anchor="middle" font-size="12.5">Issuance (credit) /</text>
                    <text class="n-title" x="110" y="261" text-anchor="middle" font-size="12.5">Job Order released</text>
                  </g>
                  <g>
                    <rect class="n-box" x="250" y="220" width="180" height="64" rx="9"/>
                    <text class="n-title" x="340" y="246" text-anchor="middle">Sales Invoice</text>
                    <text class="n-sub" x="340" y="262" text-anchor="middle">Billing &#183; doc SI</text>
                  </g>
                  <g>
                    <rect class="n-box" x="470" y="220" width="190" height="64" rx="9"/>
                    <text class="n-title" x="565" y="246" text-anchor="middle">Collection (OR)</text>
                    <text class="n-sub" x="565" y="262" text-anchor="middle">gross &#8722; withholding = net</text>
                  </g>
                  <g>
                    <rect class="n-box emph" x="700" y="220" width="110" height="64" rx="9"/>
                    <text class="n-title emph" x="755" y="246" text-anchor="middle">Paid</text>
                    <text class="n-sub" x="755" y="262" text-anchor="middle">balance = 0</text>
                  </g>
                  <line class="e-line" x1="210" y1="252" x2="246" y2="252" marker-end="url(#arD)"/>
                  <line class="e-line" x1="430" y1="252" x2="466" y2="252" marker-end="url(#arD)"/>
                  <line class="e-line" x1="660" y1="252" x2="696" y2="252" marker-end="url(#arD)"/>

                  <g>
                    <rect class="n-box emph" x="880" y="130" width="200" height="70" rx="10"/>
                    <text class="n-title emph" x="980" y="158" text-anchor="middle">Post-Dated Checks</text>
                    <text class="n-sub" x="980" y="174" text-anchor="middle">shared register &#183; either direction</text>
                  </g>
                  <path class="e-line dashed" d="M 540 114 L 880 155" marker-end="url(#arD)"/>
                  <text class="e-label" x="770" y="128" text-anchor="middle">issued</text>
                  <path class="e-line dashed" d="M 880 175 L 565 220" marker-end="url(#arD)"/>
                  <text class="e-label" x="790" y="205" text-anchor="middle">received</text>
                </svg>
                </div>
                <p class="text-muted small mt-2 mb-0">Both sides use the same "many payments can settle many invoices" shape under the hood (one Collection or Disbursement can be split across several open invoices) — the diagram shows the common case of one paying one.</p>

                <div class="guide-note"><b>Only a registered client/order gets invoiced.</b> A walk-in Issuance or a Job Order with no client on file is assumed paid in cash on the spot and never gets a Sales Invoice row — same rule Payables follows in reverse: a receiving report only becomes a Supplier Invoice when you choose to invoice it, for suppliers actually on credit terms.</div>

                <div class="guide-note"><b>Withholding tax only appears on Collections, not Disbursements.</b> LGU clients are legally required to withhold tax when paying EXECOM (BIR Form 2307), so every Collection captures gross / withholding / net. That rule doesn't run the other way — EXECOM paying its own suppliers has no equivalent withholding step, so Disbursements record a plain amount.</div>

                <div class="guide-note">Pages: <a href="job_orders.php">Job Orders</a>, <a href="billing.php">Billing</a> and <a href="collections.php">Collections</a> (AR side); <a href="payables.php">Payables</a> and <a href="disbursements.php">Disbursements</a> (AP side). The Post-Dated Check register itself lives inside Collections regardless of direction — Disbursements just offers any on-hand issued check as a way to pay.</div>
            </div>
        </div>

        <div class="panel mb-3" id="reference">
            <div class="panel-header"><div class="panel-title">6. Module quick reference</div></div>
            <div class="panel-body">
                <p class="guide-lede">Where each piece of the flow above actually lives in the app.</p>
                <div class="table-responsive">
                <table class="app-table table">
                    <thead><tr><th>Group</th><th>Page</th><th>File</th><th>Access key</th></tr></thead>
                    <tbody>
                        <tr><td rowspan="4">Master Data</td><td>Item Catalog</td><td class="guide-mono">items.php</td><td class="guide-mono">items</td></tr>
                        <tr><td>Branches</td><td class="guide-mono">branches.php</td><td class="guide-mono">&#8212; (super_admin)</td></tr>
                        <tr><td>Suppliers</td><td class="guide-mono">suppliers.php</td><td class="guide-mono">suppliers</td></tr>
                        <tr><td>Clients</td><td class="guide-mono">clients.php</td><td class="guide-mono">clients</td></tr>

                        <tr><td rowspan="6">Internal Procurement</td><td>PO (Internal) <span class="text-muted small">(direct to supplier)</span></td><td class="guide-mono">po_internal.php</td><td class="guide-mono">po_internal</td></tr>
                        <tr><td>Receiving Reports</td><td class="guide-mono">receiving_reports.php</td><td class="guide-mono">receiving_reports</td></tr>
                        <tr><td>Payables (AP)</td><td class="guide-mono">payables.php</td><td class="guide-mono">payables</td></tr>
                        <tr><td>Disbursements</td><td class="guide-mono">disbursements.php</td><td class="guide-mono">payables</td></tr>
                        <tr><td>Inventory</td><td class="guide-mono">inventory.php</td><td class="guide-mono">inventory</td></tr>
                        <tr><td>Warehouse Map</td><td class="guide-mono">warehouse.php</td><td class="guide-mono">warehouse</td></tr>

                        <tr><td rowspan="2">Branch Operations</td><td>Stock Transfer</td><td class="guide-mono">stock_transfers.php</td><td class="guide-mono">stock_transfer</td></tr>
                        <tr><td>Issuance (POS)</td><td class="guide-mono">issuances.php / issuance_pos.php</td><td class="guide-mono">issuance</td></tr>

                        <tr><td rowspan="4">Outgoing Sales</td><td>RFQ (Outgoing)</td><td class="guide-mono">outgoing_rfq.php</td><td class="guide-mono">outgoing_rfq</td></tr>
                        <tr><td>Quotations (Outgoing)</td><td class="guide-mono">outgoing_quotations.php</td><td class="guide-mono">outgoing_quotations</td></tr>
                        <tr><td>PO (Outgoing)</td><td class="guide-mono">po_outgoing.php</td><td class="guide-mono">po_outgoing</td></tr>
                        <tr><td>Delivery Reports</td><td class="guide-mono">delivery_reports.php</td><td class="guide-mono">delivery_reports</td></tr>

                        <tr><td rowspan="3">Service &amp; Billing</td><td>Job Orders</td><td class="guide-mono">job_orders.php</td><td class="guide-mono">job_orders</td></tr>
                        <tr><td>Billing (AR)</td><td class="guide-mono">billing.php</td><td class="guide-mono">billing</td></tr>
                        <tr><td>Collections &amp; PDC</td><td class="guide-mono">collections.php</td><td class="guide-mono">collections</td></tr>

                        <tr><td rowspan="3">Reports</td><td>Order Tracking</td><td class="guide-mono">order_tracking.php</td><td class="guide-mono">order_tracking</td></tr>
                        <tr><td>Cost/Profit Reports</td><td class="guide-mono">cost_profit_reports.php</td><td class="guide-mono">cost_profit_reports</td></tr>
                        <tr><td>Reports</td><td class="guide-mono">reports.php</td><td class="guide-mono">reports</td></tr>

                        <tr><td rowspan="4">System</td><td>User Management</td><td class="guide-mono">settings.php</td><td class="guide-mono">settings</td></tr>
                        <tr><td>Access Levels</td><td class="guide-mono">access_levels.php</td><td class="guide-mono">&#8212; (super_admin)</td></tr>
                        <tr><td>Activity Log</td><td class="guide-mono">audit_logs.php</td><td class="guide-mono">&#8212; (super_admin / branch_admin)</td></tr>
                        <tr><td>System Guide</td><td class="guide-mono">system_guide.php</td><td class="guide-mono">system_guide</td></tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    </div>
</div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
