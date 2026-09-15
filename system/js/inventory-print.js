/* ── Inventory & Property — build the printed forms ───────────────────────────
 *
 * Six document shapes off one shared toolbox: PAR/ICS (buildIssuance),
 * ITR/PTR (buildTransfer), the Property Card in its two variants
 * (buildPropertyCard), and the Tag (buildTag). Layout lives in
 * css/inventory-print.css; this file only decides what goes in each slot —
 * the same split js/iar-print.js and js/dv-print.js use.
 *
 * Traced against the real Crystal Reports, now that the office has supplied
 * them (vb/Inventory/Crystal Reports): every field, label and table column
 * below is read off tools/rpt_reports/inv/*.layout.tsv (exact object
 * coordinates + field/formula bindings, extracted with the 32-bit
 * CrystalDecisions runtime — see tools/Extract-CrystalRpt.ps1) and the
 * paired .vb wrapper's SetParameterValue calls. Presentation is still styled
 * to match this app's own printouts (the 1.5px frame, serif body, blue
 * title, bordered item table — see the header note in inventory-print.css)
 * rather than reproduced pixel-for-pixel, the same choice iar-print.css
 * documents; content, field order and reading order are the real forms'.
 *
 * Two deliberate departures from the .rpt, both noted where they apply:
 *   - PAR's and ICS's signature blocks are mirrored in the real reports
 *     (PAR: Received by left / Issued by right — ICS: Received From left /
 *     Received by right). Standardised here to one order on both, since the
 *     mirroring reads as drift between two forms built at different times,
 *     not a meaningful distinction.
 *   - The Property Card's running Balance Qty is now real, computed from
 *     this app's own transfer history (tbl_inventory_transfers), not left
 *     blank — the legacy VB never had that history to draw on.
 *
 * Data contract per builder is documented above each one below.
 * ──────────────────────────────────────────────────────────────────────────── */
(function (global) {
    'use strict';

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function money(v) {
        const n = parseFloat(v);
        return isFinite(n) ? n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00';
    }
    function peso(v) { return '₱' + money(v); }
    function qty(v) {
        const n = parseFloat(v);
        if (!isFinite(n)) return '';
        return Number.isInteger(n) ? String(n) : String(parseFloat(n.toFixed(3)));
    }
    // Accepts either a DB datetime string ("2026-09-15 10:30:00" — the space
    // becomes 'T' so Date.parse reads it as ISO) or an actual Date object,
    // used directly for "today" (every doc type here stamps `new Date()` as
    // its own Date field). String(aDateObject).replace(' ', 'T') used to be
    // run on the latter too — that mangles a Date's own verbose toString()
    // ("Tue Sep 15 2026 ...") into something Date.parse can't read, which is
    // why every doc type's own "Date" field printed blank until this branch
    // was added.
    function fmtDate(v) {
        if (!v) return '';
        const t = v instanceof Date ? v.getTime() : Date.parse(String(v).replace(' ', 'T'));
        if (isNaN(t)) return '';
        const d = new Date(t);
        return String(d.getMonth() + 1).padStart(2, '0') + '/' + String(d.getDate()).padStart(2, '0') + '/' + d.getFullYear();
    }
    function onRule(v, bold) {
        const t = String(v == null ? '' : v).trim();
        if (t === '' || t === '-') return '&nbsp;';
        return bold ? '<strong>' + esc(t) + '</strong>' : esc(t);
    }
    function rowTotal(r) {
        const t = parseFloat(r.total_cost);
        if (isFinite(t) && t !== 0) return t;
        const q = parseFloat(r.quantity) || 0, c = parseFloat(r.unit_cost) || 0;
        return q * c;
    }

    // The letterhead every printout in this app shares — same seal/logo
    // images, same five address lines, same 3px rule. The real .rpt embeds a
    // third picture between these two (a "Bagong Pilipinas" mark) that this
    // office's other printed forms (IAR, DV, the PO itself) don't carry —
    // left out here too, so every document leaving this office reads as one
    // family rather than the Inventory module alone picking up a third seal.
    function letterhead() {
        return '<table class="inv-letterhead"><tr>'
        +   '<td class="inv-seal"><img src="image/citylogo.png" alt=""></td>'
        +   '<td><div class="inv-letterhead-text">'
        +     '<strong>CITY GOVERNMENT OF MALAYBALAY</strong><br>'
        +     '<strong>CITY GENERAL SERVICES OFFICE</strong><br>'
        +     'Casisang, Malaybalay City, Bukidnon 8700<br>'
        +     'Tel No. (008) 813-5689<br>'
        +     '<span class="email">malaybalaycgso@gmail.com</span>'
        +   '</div></td>'
        +   '<td class="inv-seal"><img src="image/cgso-logo.png" alt=""></td>'
        + '</tr></table><hr class="inv-rule">';
    }

    // Signature/Printed Name/Title block used on PAR/ICS's two-column layout.
    function signBlock(heading, name, title, extraCaption) {
        return '<td>'
        + '<div class="inv-sign-head">' + esc(heading) + '</div>'
        + '<div class="inv-sign-line"></div>'
        + '<div class="inv-sign-name">' + onRule(name, true) + '</div>'
        + (extraCaption ? '<div class="inv-sign-caption">' + esc(extraCaption) + '</div>' : '')
        + '<div class="inv-sign-title">' + esc(title || '') + '</div>'
        + '<div class="inv-sign-date">Date: __________________</div>'
        + '</td>';
    }

    // One label:value line in the stacked info block under the item table —
    // PO Number / Date Acquired / Total Amount / Classification / Acquired
    // from / Purpose / Remarks, in the real report's own reading order.
    function infoRow(label, value, opts) {
        opts = opts || {};
        return '<tr><td class="lbl" style="width:22%">' + esc(label) + '</td>'
             + '<td class="val"' + (opts.tall ? ' style="min-height:2.4em"' : '') + '>'
             + (opts.bold ? onRule(value, true) : onRule(value)) + '</td></tr>';
    }

    /* ── PAR / ICS ──────────────────────────────────────────────────────────
     * docType: 'par' | 'ics'
     * payload: {
     *   rows: [{items_description, unit, quantity, unit_cost, total_cost,
     *           tag_number, serial_number, estimated_life, accountable_officer,
     *           position, location, classification, condition, remarks}],
     *   number, layout: 'standard' | 'lot', bucket: 'HIGH VALUE' | 'LOW VALUE',
     *   poNumber, supplier, purpose, sourceOfFund, dateAcquired, initial,
     *   cgso: { name, position },
     * }
     */
    function buildIssuance(docType, payload) {
        const rows = payload.rows || [];
        const total = rows.reduce((s, r) => s + rowTotal(r), 0);
        const first = rows[0] || {};
        const title = docType === 'par' ? 'PROPERTY ACKNOWLEDGEMENT RECEIPT' : 'INVENTORY CUSTODIAN SLIP';

        let n = 0;
        const itemRows = rows.map(r => {
            n++;
            return '<tr>'
            + '<td class="center">' + n + '</td>'
            + '<td class="center">' + esc(qty(r.quantity)) + '</td>'
            + '<td class="center">' + esc(r.unit || '') + '</td>'
            + '<td class="desc">' + esc(r.items_description || '') + '</td>'
            + '<td class="num">' + esc(money(r.unit_cost)) + '</td>'
            + '<td class="center">' + esc(r.serial_number || '') + '</td>'
            + '<td class="center">' + esc(r.tag_number || '') + '</td>'
            + '<td class="center">' + esc(r.estimated_life || '') + '</td>'
            + '</tr>';
        }).join('');

        return '<div class="inv-sheet"><div class="inv-frame">'
        + letterhead()
        + '<div class="inv-title">' + title + '</div>'
        + (docType === 'ics' ? '<div class="inv-subtitle">' + esc(payload.bucket || 'HIGH VALUE') + '</div>' : '')
        + (payload.layout === 'lot' ? '<div class="inv-subtitle">GROUPED BY LOT</div>' : '')

        + '<table class="inv-grid">'
        +   '<tr><td class="lbl" style="width:16%">Entity Name</td><td class="val" style="width:34%">City Government of Malaybalay</td>'
        +       '<td class="lbl" style="width:16%">' + (docType === 'par' ? 'PAR' : 'ICS') + ' No.</td>'
        +       '<td class="val" style="width:34%"><strong>' + esc(payload.number || '') + '</strong></td></tr>'
        +   '<tr><td class="lbl">Source of Fund</td><td class="val">' + onRule(payload.sourceOfFund) + '</td>'
        +       '<td class="lbl">Date</td><td class="val">' + esc(fmtDate(new Date())) + '</td></tr>'
        + '</table>'

        + '<table class="inv-items"><thead><tr>'
        +   '<th style="width:5%">No.</th><th style="width:7%">Qty</th><th style="width:7%">Unit</th>'
        +   '<th>Description</th><th style="width:11%">Unit Cost</th>'
        +   '<th style="width:11%">Serial No.</th><th style="width:11%">Tag No.</th><th style="width:8%">Est.<br>Life</th>'
        + '</tr></thead><tbody>' + itemRows
        +   '<tr><td colspan="8" class="inv-nothing-follows">***nothing follows***'
        +     (payload.initial ? '<span class="inv-initial">' + esc(payload.initial) + '</span>' : '')
        +   '</td></tr>'
        + '</tbody></table>'

        + '<table class="inv-info-list">'
        +   infoRow('PO Number:', payload.poNumber)
        +   infoRow('Date Acquired:', fmtDate(payload.dateAcquired))
        +   infoRow('Total Amount:', peso(total), { bold: true })
        +   infoRow('Classification:', first.classification)
        +   infoRow('Acquired from:', payload.supplier)
        +   infoRow('Purpose:', payload.purpose, { tall: true })
        +   infoRow('Remarks:', first.remarks, { tall: true })
        + '</table>'

        + '<div class="inv-ack">I acknowledge to have received the property/ies described above, in good order and condition, and I obligate '
        + 'myself to be accountable for their proper custody, use and disposal in accordance with existing government accounting and auditing rules '
        + 'and regulations. Any loss or damage shall be my responsibility.</div>'

        + '<table class="inv-sign-grid"><tr>'
        +   signBlock('Received by:', first.accountable_officer, first.position, 'Accountable Officer')
        +   signBlock('Issued by:', payload.cgso ? payload.cgso.name : '', payload.cgso ? payload.cgso.position : '', 'CGSO Officer')
        + '</tr></table>'

        + '</div></div>';
    }

    /* ── ITR / PTR (transfer) ─────────────────────────────────────────────
     * docType: 'itr' | 'ptr'
     * payload: { rows, number, from, to, toOfficer, toPosition, toLocation,
     *            department, fundCluster, reason, transferKind, poNumber,
     *            supplier, approvedBy, approvedPosition, issuedBy,
     *            issuedPosition, cgso }
     */
    const TRANSFER_KINDS = ['Donation', 'Reassignment', 'Relocation', 'Other'];

    function buildTransfer(docType, payload) {
        const rows = payload.rows || [];
        const total = rows.reduce((s, r) => s + rowTotal(r), 0);
        const title = docType === 'itr' ? 'INVENTORY TRANSFER REPORT' : 'PROPERTY TRANSFER REPORT';
        const poNumber = payload.poNumber || (rows[0] || {}).po_number || '';

        // "Transfer Type (check only one)" — the real form's four checkboxes,
        // rendered as a marked box rather than a real form control on a
        // document meant to be printed and filed.
        const kindBoxes = TRANSFER_KINDS.map(k =>
            '<span class="inv-check-item"><span class="inv-check-box">' + (payload.transferKind === k ? '✓' : '') + '</span> ' + esc(k) + '</span>'
        ).join('');

        let n = 0;
        let lastDate = null;
        const itemRows = rows.map(r => {
            n++;
            // The real report suppresses a repeated Date Acquired when it is
            // the same as the row above — a running ledger convention, not a
            // missing value.
            const d = fmtDate(payload.dateAcquired || r.date_acquired);
            const showDate = d !== lastDate; lastDate = d;
            return '<tr>'
            + '<td class="center">' + (showDate ? esc(d) : '') + '</td>'
            + '<td class="center">' + n + '</td>'
            + '<td class="center">' + esc(qty(r.quantity)) + '</td>'
            + '<td class="center">' + esc(r.unit || '') + '</td>'
            + '<td class="desc">' + esc(r.items_description || '') + '</td>'
            + '<td class="num">' + esc(money(r.unit_cost)) + '</td>'
            + '<td class="center">' + esc(r.condition || '') + '</td>'
            + '</tr>';
        }).join('');

        return '<div class="inv-sheet"><div class="inv-frame">'
        + letterhead()
        + '<div class="inv-title">' + title + '</div>'

        + '<table class="inv-grid">'
        +   '<tr><td class="lbl" style="width:16%">Entity Name</td><td class="val" style="width:34%">' + onRule(payload.department || 'City Government of Malaybalay') + '</td>'
        +       '<td class="lbl" style="width:16%">Fund Cluster</td><td class="val" style="width:34%">' + onRule(payload.fundCluster) + '</td></tr>'
        +   '<tr><td class="lbl">' + (docType === 'itr' ? 'ITR' : 'PTR') + ' No.</td><td class="val"><strong>' + esc(payload.number || '') + '</strong></td>'
        +       '<td class="lbl">Date</td><td class="val">' + esc(fmtDate(new Date())) + '</td></tr>'
        +   '<tr><td class="lbl">From — Officer / Agency</td><td class="val">' + onRule(payload.from) + '</td>'
        +       '<td class="lbl">P.O. No.</td><td class="val">' + onRule(poNumber) + '</td></tr>'
        +   '<tr><td class="lbl">To — Officer / Agency</td><td class="val" colspan="3"><strong>' + onRule(payload.to) + '</strong></td></tr>'
        + '</table>'

        + '<div class="inv-transfer-kind"><span class="k">Transfer Type (check only one):</span> ' + kindBoxes + '</div>'

        + '<table class="inv-info-list">'
        +   infoRow('Acquired from:', payload.supplier)
        +   infoRow('Reason/s for Transfer:', payload.reason, { tall: true })
        + '</table>'

        + '<table class="inv-items"><thead><tr>'
        +   '<th style="width:9%">Date<br>Acquired</th><th style="width:5%">No.</th><th style="width:7%">Qty</th><th style="width:7%">Unit</th>'
        +   '<th>Description</th><th style="width:12%">Unit Cost</th><th style="width:12%">Condition</th>'
        + '</tr></thead><tbody>' + itemRows
        +   '<tr><td colspan="7" class="inv-nothing-follows">***nothing follows***</td></tr>'
        + '</tbody><tfoot><tr><td colspan="5" style="text-align:right">Total Cost</td><td class="num" colspan="2">' + esc(money(total)) + '</td></tr></tfoot></table>'

        + '<table class="inv-sign-grid3"><tr>'
        +   '<td class="head">Approved by:</td><td class="head">Released/Issued by:</td><td class="head">Received by:</td></tr><tr>'
        +   '<td>Printed Name: ' + onRule(payload.cgso ? payload.cgso.name : '', true) + '</td>'
        +   '<td>Printed Name: ' + onRule(payload.issuedBy, true) + '</td>'
        +   '<td>Printed Name: ' + onRule(payload.toOfficer, true) + '</td></tr><tr>'
        +   '<td>Signature: <span class="inv-sign-blank"></span></td>'
        +   '<td>Signature: <span class="inv-sign-blank"></span></td>'
        +   '<td>Signature: <span class="inv-sign-blank"></span></td></tr><tr>'
        +   '<td>Designation: ' + onRule(payload.cgso ? payload.cgso.position : '') + '</td>'
        +   '<td>Designation: ' + onRule(payload.issuedPosition) + '</td>'
        +   '<td>Designation: ' + onRule(payload.toOfficerPosition) + '</td></tr><tr>'
        +   '<td>Date: __________</td><td>Date: __________</td><td>Date: __________</td></tr>'
        + '</table>'

        + '</div></div>';
    }

    /* ── Property Card ─────────────────────────────────────────────────────
     * A running quantity ledger for ONE item line — Receipt Qty at intake,
     * then one row per later transfer with its Transfer Qty, running down to
     * a Balance Qty. Populated from payload.movements (from
     * api/inventory_issuance.php's ?transfers=1, pre-filtered/sorted to this
     * item and carrying THIS item's own quantity/cost off that transfer) —
     * the transfer log this module now keeps is exactly what a COA Property
     * Card's body wants, and unlike the legacy form, the balance is real.
     * payload: { rows: [oneRow], movements: [...], poNumber }
     */
    function buildPropertyCard(payload, isSemi) {
        const item = (payload.rows || [])[0] || {};
        const movements = payload.movements || [];
        const receiptQty = parseFloat(item.quantity) || 0;
        let balance = receiptQty;

        if (isSemi) return buildSemiExpendableCard(payload);

        const openingRow = '<tr>'
            + '<td class="center">' + esc(fmtDate(item.date_acquired)) + '</td>'
            + '<td class="center">' + esc(item.par_ics_number || '') + '</td>'
            + '<td class="desc">' + esc(item.items_description || '') + '</td>'
            + '<td class="num">' + esc(money(item.unit_cost)) + '</td>'
            + '<td class="num">' + esc(qty(receiptQty)) + '</td>'
            + '<td></td><td></td>'
            + '<td class="num">' + esc(qty(balance)) + '</td>'
            + '</tr>';

        const moveRows = movements.map(m => {
            const moveQty = parseFloat(m.quantity) || 0;
            balance -= moveQty;
            return '<tr>'
            + '<td class="center">' + esc(fmtDate(m.date_transferred)) + '</td>'
            + '<td class="center">' + esc(m.transfer_number || '') + '</td>'
            + '<td class="desc"></td>'
            + '<td class="num"></td>'
            + '<td class="num"></td>'
            + '<td class="num">' + esc(qty(moveQty)) + '</td>'
            + '<td>' + esc(m.to_officer || '') + (m.to_location ? ' / ' + esc(m.to_location) : '') + '</td>'
            + '<td class="num">' + esc(qty(balance)) + '</td>'
            + '</tr>';
        }).join('');

        return '<div class="inv-sheet"><div class="inv-frame">'
        + letterhead()
        + '<div class="inv-title">PROPERTY CARD</div>'

        + '<table class="inv-card-head">'
        +   '<tr><td style="width:20%"><strong>Entity Name:</strong></td><td style="width:30%">City Government of Malaybalay</td>'
        +       '<td style="width:20%"><strong>PO Number:</strong></td><td style="width:30%">' + onRule(payload.poNumber || item.po_number) + '</td></tr>'
        +   '<tr><td><strong>Plant, Property &amp; Equipment:</strong></td><td colspan="3">' + onRule(item.items_description) + '</td></tr>'
        +   '<tr><td><strong>Property Classification No.:</strong></td><td>' + onRule(item.classification) + '</td>'
        +       '<td><strong>Unit Cost:</strong></td><td>' + esc(money(item.unit_cost)) + '</td></tr>'
        +   '<tr><td><strong>Name of Custodian:</strong></td><td>' + onRule(item.accountable_officer) + '</td>'
        +       '<td><strong>Designation:</strong></td><td>' + onRule(item.position) + '</td></tr>'
        +   '<tr><td><strong>Office / Department:</strong></td><td colspan="3">' + onRule(item.location) + '</td></tr>'
        + '</table>'

        + '<table class="inv-ledger"><thead><tr>'
        +   '<th rowspan="2" style="width:10%">Date</th><th rowspan="2" style="width:14%">Reference /<br>Property No.</th>'
        +   '<th rowspan="2">Items Description</th><th rowspan="2" style="width:11%">Unit Cost</th>'
        +   '<th rowspan="2" style="width:9%">Receipt<br>Qty</th>'
        +   '<th colspan="2">Transfers / etc.</th><th rowspan="2" style="width:9%">Balance<br>Qty</th>'
        + '</tr><tr><th style="width:8%">Qty</th><th style="width:17%">Office / Officer</th></tr></thead>'
        + '<tbody>' + openingRow + moveRows
        +   (movements.length ? '' : '<tr><td colspan="8" class="inv-ledger-empty">No transfers recorded yet — this item is still with its original custodian.</td></tr>')
        + '</tbody></table>'

        + '</div></div>';
    }

    // The COA semi-expendable card additionally tracks cost through the
    // ledger (Receipt/Balance carry Unit Cost + Total/Amount, not just Qty) —
    // a separate, slightly richer real form, not a variant flag on the
    // ordinary Property Card's.
    function buildSemiExpendableCard(payload) {
        const item = (payload.rows || [])[0] || {};
        const movements = payload.movements || [];
        const unitCost = parseFloat(item.unit_cost) || 0;
        const receiptQty = parseFloat(item.quantity) || 0;
        let balanceQty = receiptQty, balanceAmt = receiptQty * unitCost;

        const openingRow = '<tr>'
            + '<td class="center">' + esc(fmtDate(item.date_acquired)) + '</td>'
            + '<td class="center">' + esc(item.par_ics_number || '') + '</td>'
            + '<td class="num">' + esc(qty(receiptQty)) + '</td>'
            + '<td class="num">' + esc(money(unitCost)) + '</td>'
            + '<td class="num">' + esc(money(balanceAmt)) + '</td>'
            + '<td></td><td></td>'
            + '<td class="num">' + esc(qty(balanceQty)) + '</td>'
            + '<td class="num">' + esc(money(balanceAmt)) + '</td>'
            + '<td></td>'
            + '</tr>';

        const moveRows = movements.map(m => {
            const moveQty = parseFloat(m.quantity) || 0;
            const moveAmt = moveQty * unitCost;
            balanceQty -= moveQty; balanceAmt -= moveAmt;
            return '<tr>'
            + '<td class="center">' + esc(fmtDate(m.date_transferred)) + '</td>'
            + '<td class="center">' + esc(m.transfer_number || '') + '</td>'
            + '<td class="num"></td><td class="num"></td><td class="num"></td>'
            + '<td class="num">' + esc(qty(moveQty)) + '</td>'
            + '<td>' + esc(m.to_officer || '') + (m.to_location ? ' / ' + esc(m.to_location) : '') + '</td>'
            + '<td class="num">' + esc(qty(balanceQty)) + '</td>'
            + '<td class="num">' + esc(money(balanceAmt)) + '</td>'
            + '<td>' + esc(m.reason || '') + '</td>'
            + '</tr>';
        }).join('');

        return '<div class="inv-sheet"><div class="inv-frame">'
        + letterhead()
        + '<div class="inv-title">SEMI-EXPENDABLE PROPERTY CARD</div>'

        + '<table class="inv-card-head">'
        +   '<tr><td style="width:20%"><strong>Entity Name:</strong></td><td style="width:30%">City Government of Malaybalay</td>'
        +       '<td style="width:20%"><strong>Fund Cluster:</strong></td><td style="width:30%">&nbsp;</td></tr>'
        +   '<tr><td><strong>Semi-expendable Property:</strong></td><td colspan="3">' + onRule(item.items_description) + '</td></tr>'
        +   '<tr><td><strong>Description:</strong></td><td colspan="3">' + onRule(item.items_description) + '</td></tr>'
        +   '<tr><td><strong>Semi-expendable Property Number:</strong></td><td colspan="3">' + onRule(item.tag_number) + '</td></tr>'
        + '</table>'

        + '<table class="inv-ledger"><thead><tr>'
        +   '<th rowspan="2" style="width:8%">Date</th><th rowspan="2" style="width:10%">Reference /<br>Property No.</th>'
        +   '<th colspan="3">Receipt</th>'
        +   '<th colspan="2">Issued / Transfer / Disposal</th>'
        +   '<th colspan="2">Balance</th><th rowspan="2" style="width:11%">Remarks</th>'
        + '</tr><tr>'
        +   '<th style="width:6%">Qty</th><th style="width:8%">Unit Cost</th><th style="width:8%">Total Cost</th>'
        +   '<th style="width:6%">Qty</th><th style="width:14%">Office/Officer</th>'
        +   '<th style="width:6%">Qty</th><th style="width:8%">Amount</th>'
        + '</tr></thead>'
        + '<tbody>' + openingRow + moveRows
        +   (movements.length ? '' : '<tr><td colspan="10" class="inv-ledger-empty">No transfers recorded yet — this item is still with its original custodian.</td></tr>')
        + '</tbody></table>'

        + '</div></div>';
    }

    /* ── Tag (physical label) ────────────────────────────────────────────────
     * payload: { rows: [oneRow], barcodePng, barcodeWidth, cgso }
     */
    function buildTag(payload, small) {
        const item = (payload.rows || [])[0] || {};
        const cls = small ? 'inv-sheet inv-tag-sheet inv-tag-small' : 'inv-sheet inv-tag-sheet';
        const barcode = payload.barcodePng
            ? '<div class="inv-tag-barcode"><img src="' + payload.barcodePng + '" style="width:' + (payload.barcodeWidth || 160) + 'px" alt="">'
              + '<div class="num">' + esc(item.par_ics_number || '') + '</div></div>'
            : '';

        return '<div class="' + cls + '"><div class="inv-tag-frame">'
        + '<div class="inv-tag-title">CITY GENERAL SERVICES OFFICE</div>'
        + '<div class="inv-tag-property-no">' + esc(item.tag_number || item.par_ics_number || '') + '</div>'
        + '<div class="inv-tag-row"><span class="k">P.O. Number</span><span class="v">' + esc(item.po_number || '') + '</span></div>'
        + '<div class="inv-tag-row"><span class="k">Description</span><span class="v">' + esc(item.items_description || '') + '</span></div>'
        + '<div class="inv-tag-row"><span class="k">Date Acquired</span><span class="v">' + esc(fmtDate(item.date_acquired || new Date())) + '</span></div>'
        + '<div class="inv-tag-row"><span class="k">Serial No.</span><span class="v">' + esc(item.serial_number || '') + '</span></div>'
        + '<div class="inv-tag-row"><span class="k">Qty / Unit</span><span class="v">' + esc(qty(item.quantity)) + ' ' + esc(item.unit || '') + '</span></div>'
        + '<div class="inv-tag-row"><span class="k">Condition</span><span class="v">' + esc(item.condition || '') + '</span></div>'
        + '<div class="inv-tag-row"><span class="k">Acquisition Cost</span><span class="v">' + peso(item.unit_cost) + '</span></div>'
        + '<div class="inv-tag-row"><span class="k">Accountable Officer</span><span class="v">' + esc(item.accountable_officer || '') + '</span></div>'
        + '<div class="inv-tag-row"><span class="k">Position</span><span class="v">' + esc(item.position || '') + '</span></div>'
        + (payload.cgso ? '<div class="inv-tag-row"><span class="k">CGSO</span><span class="v">' + esc(payload.cgso.name || '') + '</span></div>' : '')
        + barcode
        + '<div class="inv-tag-warning">TAMPERING OF THIS STICKER IS PUNISHABLE BY LAW</div>'
        + '</div></div>';
    }

    /* ── Personnel Memorandum Receipt (MR) ────────────────────────────────────
     * No legacy .rpt to trace — the old VB.NET app never had a per-person
     * custody document, only per-PO ones (PAR/ICS/Card/Tag above). Designed
     * fresh against COA's standard Memorandum Receipt for Equipment content
     * (entity/MR-No. header, an item table, total, acknowledgement) rather
     * than reproduced from any single source — but styled with the exact same
     * building blocks (letterhead(), .inv-grid, .inv-items, signBlock()) as
     * every document above it, so it reads as one family rather than a
     * one-off.
     *
     * payload: {
     *   officer, position, department,
     *   rows: [{items_description, unit, quantity, unit_cost, total_cost,
     *           tag_number, serial_number, po_number, location, condition,
     *           source}],   // source: 'line' | 'unit' — see api/inventory.php's
     *                       // invMemorandumReceipt(), not shown on the form itself
     *   cgso: { name, position },
     * }
     */
    function buildMR(payload) {
        const rows = payload.rows || [];
        const total = rows.reduce((s, r) => s + rowTotal(r), 0);

        let n = 0;
        const itemRows = rows.map(r => {
            n++;
            return '<tr>'
            + '<td class="center">' + n + '</td>'
            + '<td class="center">' + esc(qty(r.quantity)) + '</td>'
            + '<td class="center">' + esc(r.unit || '') + '</td>'
            + '<td class="desc">' + esc(r.items_description || '') + '</td>'
            + '<td class="center">' + esc(r.tag_number || r.serial_number || '') + '</td>'
            + '<td class="num">' + esc(money(r.unit_cost)) + '</td>'
            + '<td class="num">' + esc(money(rowTotal(r))) + '</td>'
            + '<td class="center">' + esc(r.po_number || '') + '</td>'
            + '</tr>';
        }).join('');

        return '<div class="inv-sheet mr-sheet"><div class="inv-frame">'
        + letterhead()
        + '<div class="inv-title">MEMORANDUM RECEIPT</div>'
        + '<div class="inv-subtitle">FOR EQUIPMENT / PROPERTY</div>'

        + '<table class="inv-grid">'
        +   '<tr><td class="lbl" style="width:16%">Entity Name</td><td class="val" style="width:34%">City Government of Malaybalay</td>'
        +       '<td class="lbl" style="width:16%">MR No.</td>'
        +       '<td class="val" style="width:34%">&nbsp;</td></tr>'
        +   '<tr><td class="lbl">Accountable Officer</td><td class="val"><strong>' + esc(payload.officer || '') + '</strong></td>'
        +       '<td class="lbl">Date</td><td class="val">' + esc(fmtDate(new Date())) + '</td></tr>'
        +   '<tr><td class="lbl">Position/Office</td><td class="val" colspan="3">' + onRule(payload.position || payload.department) + '</td></tr>'
        + '</table>'

        + '<table class="inv-items"><thead><tr>'
        +   '<th style="width:5%">No.</th><th style="width:7%">Qty</th><th style="width:7%">Unit</th>'
        +   '<th>Description</th><th style="width:11%">Property/<br>Tag No.</th>'
        +   '<th style="width:11%">Unit Cost</th><th style="width:11%">Total Cost</th><th style="width:11%">PO Number</th>'
        + '</tr></thead><tbody>' + itemRows
        +   '<tr><td colspan="8" class="inv-nothing-follows">***nothing follows***</td></tr>'
        + '</tbody></table>'

        + '<table class="inv-info-list">'
        +   infoRow('Total Amount:', peso(total), { bold: true })
        +   infoRow('Total Items:', String(rows.length))
        + '</table>'

        + '<div class="inv-ack">I acknowledge to have received the property/ies listed above, in good order and condition, for use in the '
        + 'performance of my official duties. I obligate myself to be accountable for their proper custody, use and disposal in accordance with '
        + 'existing government accounting and auditing rules and regulations, and to return them when no longer needed or upon demand. Any loss '
        + 'or damage while in my custody shall be my responsibility.</div>'

        + '<table class="inv-sign-grid"><tr>'
        +   signBlock('Received by:', payload.officer, payload.position || payload.department, 'Accountable Officer')
        +   signBlock('Issued by:', payload.cgso ? payload.cgso.name : '', payload.cgso ? payload.cgso.position : '', 'CGSO Officer')
        + '</tr></table>'

        + '</div></div>';
    }

    /* ── Property Return Slip (PRS) ────────────────────────────────────────
     * No legacy .rpt to trace (checked tools/rpt_reports/inv/ and
     * vb/Inventory/Crystal Reports — nothing for "return"/"PRS"), same
     * situation buildMR was in. Recorded through the same
     * tbl_inventory_transfers/tbl_inventory_transfer_items tables ITR/PTR
     * use (Transfer_Type='PRS' — see api/inventory_issuance.php), since a
     * return is structurally a transfer whose destination is GSO itself; the
     * printed form is its own shape rather than a relabeled buildTransfer()
     * output, because it drops the Donation/Reassignment/Relocation/Other
     * checkboxes (meaningless for a return) and carries a Condition/Reason-
     * for-Return line and different signature captions instead.
     *
     * payload: {
     *   rows: [{items_description, unit, quantity, unit_cost, tag_number,
     *           serial_number, po_number}],
     *   number, from (returning officer · location), reason, condition,
     *   returnedTo, receivedBy, receivedPosition, approvedBy, approvedPosition,
     *   department, supplier, poNumber, cgso: { name, position },
     * }
     */
    function buildPRS(payload) {
        const rows = payload.rows || [];
        const total = rows.reduce((s, r) => s + rowTotal(r), 0);

        let n = 0;
        const itemRows = rows.map(r => {
            n++;
            return '<tr>'
            + '<td class="center">' + n + '</td>'
            + '<td class="center">' + esc(qty(r.quantity)) + '</td>'
            + '<td class="center">' + esc(r.unit || '') + '</td>'
            + '<td class="desc">' + esc(r.items_description || '') + '</td>'
            + '<td class="center">' + esc(r.tag_number || r.serial_number || '') + '</td>'
            + '<td class="num">' + esc(money(r.unit_cost)) + '</td>'
            + '<td class="num">' + esc(money(rowTotal(r))) + '</td>'
            + '</tr>';
        }).join('');

        return '<div class="inv-sheet"><div class="inv-frame">'
        + letterhead()
        + '<div class="inv-title">PROPERTY RETURN SLIP</div>'

        + '<table class="inv-grid">'
        +   '<tr><td class="lbl" style="width:16%">Entity Name</td><td class="val" style="width:34%">City Government of Malaybalay</td>'
        +       '<td class="lbl" style="width:16%">PRS No.</td>'
        +       '<td class="val" style="width:34%"><strong>' + esc(payload.number || '') + '</strong></td></tr>'
        +   '<tr><td class="lbl">Returned by</td><td class="val"><strong>' + esc(payload.from || '') + '</strong></td>'
        +       '<td class="lbl">Date</td><td class="val">' + esc(fmtDate(new Date())) + '</td></tr>'
        +   '<tr><td class="lbl">PO Number</td><td class="val" colspan="3">' + onRule(payload.poNumber) + '</td></tr>'
        + '</table>'

        + '<table class="inv-items"><thead><tr>'
        +   '<th style="width:5%">No.</th><th style="width:7%">Qty</th><th style="width:7%">Unit</th>'
        +   '<th>Description</th><th style="width:12%">Property/<br>Tag No.</th>'
        +   '<th style="width:12%">Unit Cost</th><th style="width:12%">Total Cost</th>'
        + '</tr></thead><tbody>' + itemRows
        +   '<tr><td colspan="7" class="inv-nothing-follows">***nothing follows***</td></tr>'
        + '</tbody></table>'

        + '<table class="inv-info-list">'
        +   infoRow('Total Amount:', peso(total), { bold: true })
        +   infoRow('Condition:', payload.condition)
        +   infoRow('Reason for Return:', payload.reason, { tall: true })
        +   infoRow('Returned To:', payload.returnedTo)
        + '</table>'

        + '<div class="inv-ack">The property/ies listed above are returned in the condition stated, released back into City General Services '
        + 'Office custody. Any discrepancy from what is described above is noted at the time of this return, not assumed afterward.</div>'

        + '<table class="inv-sign-grid"><tr>'
        +   signBlock('Released by:', payload.from ? payload.from.split(' · ')[0] : '', '', 'Returning Officer')
        +   signBlock('Received by:', payload.receivedBy, payload.receivedPosition, 'GSO – Property Custodian')
        + '</tr></table>'

        + '</div></div>';
    }

    global.InventoryPrint = { buildIssuance, buildTransfer, buildPropertyCard, buildTag, buildMR, buildPRS };
})(window);
