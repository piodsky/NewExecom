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
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
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
<div class="wrapper">
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <div class="page-title">System Guide</div>
                <div class="small text-muted">Quick reference for modules, roles, workflows, and system rules.</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> System Guide</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">

        <div class="panel mb-3">
            <div class="panel-body">
                <p class="guide-lede mb-2">From a purchase requisition to a barcode scan at checkout — this is the document chain, the inventory model, and where serial numbers actually live in <?= e(APP_NAME) ?>.</p>
                <div class="d-flex guide-jump gap-2">
                    <a href="#flow" class="btn btn-light btn-sm"><i class="fas fa-route me-1"></i>Document chain</a>
                    <a href="#inventory" class="btn btn-light btn-sm"><i class="fas fa-warehouse me-1"></i>Inside Inventory</a>
                    <a href="#example" class="btn btn-light btn-sm"><i class="fas fa-desktop me-1"></i>Worked example</a>
                    <a href="#reference" class="btn btn-light btn-sm"><i class="fas fa-list-check me-1"></i>Module reference</a>
                </div>
            </div>
        </div>

        <div class="panel mb-3" id="flow">
            <div class="panel-header"><div class="panel-title">1. The document chain, end to end</div></div>
            <div class="panel-body">
                <p class="guide-lede">Two paper trails meet at one shelf. Buying brings stock <em>into</em> Inventory; selling and branch transfers take it back <em>out</em> — every serialized item passes through the same middle box.</p>

                <div class="guide-dgm">
                <svg viewBox="0 0 1230 470" role="img" aria-label="Purchase Requisition leads to Internal RFQ, then Internal PO, then a Receiving Report that assigns serial numbers into Inventory. From Inventory, stock either moves to another branch via Stock Transfer, or is scanned out at Issuance POS. Separately, an Outgoing RFQ leads to a Quotation and an Outgoing PO, which fulfills at Issuance and closes via a Delivery Report.">
                  <defs>
                    <marker id="arA" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                      <polygon points="0,0 10,5 0,10" fill="#94a3b8"/>
                    </marker>
                  </defs>

                  <text x="10" y="238" class="row-label">BUYING &#183; INTERNAL PROCUREMENT</text>
                  <text x="620" y="98" class="row-label">SELLING &#183; OUTGOING PAPERWORK</text>

                  <g>
                    <rect class="n-box" x="10" y="260" width="100" height="64" rx="9"/>
                    <text class="n-title" x="60" y="288" text-anchor="middle">PR</text>
                    <text class="n-sub" x="60" y="303" text-anchor="middle">Requisition</text>
                  </g>
                  <g>
                    <rect class="n-box" x="140" y="260" width="120" height="64" rx="9"/>
                    <text class="n-title" x="200" y="288" text-anchor="middle">RFQ</text>
                    <text class="n-sub" x="200" y="303" text-anchor="middle">Internal</text>
                  </g>
                  <g>
                    <rect class="n-box" x="290" y="260" width="120" height="64" rx="9"/>
                    <text class="n-title" x="350" y="288" text-anchor="middle">PO</text>
                    <text class="n-sub" x="350" y="303" text-anchor="middle">Internal</text>
                  </g>
                  <g>
                    <rect class="n-box" x="440" y="260" width="150" height="64" rx="9"/>
                    <text class="n-title" x="515" y="288" text-anchor="middle">Receiving Report</text>
                    <text class="n-sub" x="515" y="303" text-anchor="middle">serials entered here</text>
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

                  <line class="e-line" x1="110" y1="292" x2="136" y2="292" marker-end="url(#arA)"/>
                  <line class="e-line" x1="260" y1="292" x2="286" y2="292" marker-end="url(#arA)"/>
                  <line class="e-line" x1="410" y1="292" x2="436" y2="292" marker-end="url(#arA)"/>
                  <line class="e-line" x1="590" y1="292" x2="616" y2="292" marker-end="url(#arA)"/>
                  <text class="e-label" x="603" y="278" text-anchor="middle">serial in</text>
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

        <div class="panel mb-3" id="example">
            <div class="panel-header"><div class="panel-title">3. Worked example: monitors</div></div>
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

        <div class="panel mb-3" id="reference">
            <div class="panel-header"><div class="panel-title">4. Module quick reference</div></div>
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

                        <tr><td rowspan="5">Internal Procurement</td><td>Purchase Requisition</td><td class="guide-mono">purchase_requisitions.php</td><td class="guide-mono">purchase_requisition</td></tr>
                        <tr><td>RFQ (Internal)</td><td class="guide-mono">internal_rfq.php</td><td class="guide-mono">internal_rfq</td></tr>
                        <tr><td>PO (Internal)</td><td class="guide-mono">po_internal.php</td><td class="guide-mono">po_internal</td></tr>
                        <tr><td>Receiving Reports</td><td class="guide-mono">receiving_reports.php</td><td class="guide-mono">receiving_reports</td></tr>
                        <tr><td>Inventory</td><td class="guide-mono">inventory.php</td><td class="guide-mono">inventory</td></tr>

                        <tr><td rowspan="2">Branch Operations</td><td>Stock Transfer</td><td class="guide-mono">stock_transfers.php</td><td class="guide-mono">stock_transfer</td></tr>
                        <tr><td>Issuance (POS)</td><td class="guide-mono">issuances.php / issuance_pos.php</td><td class="guide-mono">issuance</td></tr>

                        <tr><td rowspan="4">Outgoing Sales</td><td>RFQ (Outgoing)</td><td class="guide-mono">outgoing_rfq.php</td><td class="guide-mono">outgoing_rfq</td></tr>
                        <tr><td>Quotations (Outgoing)</td><td class="guide-mono">outgoing_quotations.php</td><td class="guide-mono">outgoing_quotations</td></tr>
                        <tr><td>PO (Outgoing)</td><td class="guide-mono">po_outgoing.php</td><td class="guide-mono">po_outgoing</td></tr>
                        <tr><td>Delivery Reports</td><td class="guide-mono">delivery_reports.php</td><td class="guide-mono">delivery_reports</td></tr>

                        <tr><td rowspan="2">Reports</td><td>Cost/Profit Reports</td><td class="guide-mono">cost_profit_reports.php</td><td class="guide-mono">cost_profit_reports</td></tr>
                        <tr><td>Reports</td><td class="guide-mono">reports.php</td><td class="guide-mono">reports</td></tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

    </div>
</div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
