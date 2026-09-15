/* ── Inspection & Acceptance Report — build and preview ───────────────────────
 *
 * Builds the printed form's HTML and drives it through the shared raster/slice
 * pipeline in js/print-pdf.js, the same one the Purchase Order print uses.
 *
 * The layout lives entirely in css/iar-print.css, traced object by object to
 * the Crystal report; this file only decides WHAT goes in each slot. Keeping
 * the split that way is what makes the form checkable against
 * tools/rpt_reports/ia.layout.tsv without reading any JavaScript.
 *
 * Data contract, matching what Inspect_Print.vb pushed into the report:
 *   header  po_number pr_number department supplier_name invoice_number
 *           purpose date_inspected accepted_by
 *           end_user1..5  inspected_by_1..4
 *   items   item_id unit items_description quantity unit_cost total_cost
 *   extra   initial (the operator's initials, a report parameter)
 *           inspectorOffice(name) -> the officer's office, which the report
 *           looked up from ref_signaturies and printed under each signature
 *           billing {no, dateFrom, dateTo} -> present only for a progress
 *           billing; see the note above buildSheet()
 * ──────────────────────────────────────────────────────────────────────────── */
(function (global) {
    'use strict';

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function money(v) {
        const n = parseFloat(v);
        return isFinite(n) ? n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
    }

    // Quantities print without forced decimals — "5", not "5.000" — but keep
    // real fractions, because a quantity of 2.5 metres is a quantity of 2.5.
    function qty(v) {
        const n = parseFloat(v);
        if (!isFinite(n)) return '';
        return Number.isInteger(n) ? String(n) : String(parseFloat(n.toFixed(3)));
    }

    // The report prints dates as mm/dd/yyyy. Anything unparseable prints blank
    // rather than as "Invalid Date" — a blank line on a form is a line somebody
    // fills in by hand, which is a recoverable state; a garbage date is not.
    function fmtDate(v) {
        if (!v) return '';
        const t = Date.parse(String(v).replace(' ', 'T'));
        if (isNaN(t)) return '';
        const d = new Date(t);
        return String(d.getMonth() + 1).padStart(2, '0') + '/'
             + String(d.getDate()).padStart(2, '0') + '/' + d.getFullYear();
    }

    // Crystal's Propercase(): used on the inspectorate offices. Applied to the
    // whole string including anything after a hyphen or a slash, which is how
    // "CGSO-DESIGNATE" becomes "Cgso-Designate" there too.
    function properCase(s) {
        return String(s == null ? '' : s).toLowerCase()
            .replace(/(^|[\s\-\/\(])([a-z])/g, (m, pre, ch) => pre + ch.toUpperCase());
    }

    // ── Amount in words ─────────────────────────────────────────────────────
    // Based on the report's `propercase(towords({@sum_total_cost},2)) + " Only"`.
    // towords(x, 2) spells the whole number and renders the two decimals as a
    // fraction over 100, which is how every Philippine government form words a
    // peso amount. One deliberate departure from that formula: "Pesos" is
    // inserted before the fraction (the .rpt's own text just says "... and
    // 00/100 Only" with the currency never spelled out) — user-requested
    // 2026-09-08, same change made in purchase_orders.php's own
    // amountInWords(). DvPrint delegates to this one, so fixing it here covers
    // the Disbursement Voucher too.
    const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    function under1000(n) {
        let out = '';
        if (n >= 100) { out += ONES[Math.floor(n / 100)] + ' Hundred'; n %= 100; if (n) out += ' '; }
        if (n >= 20) { out += TENS[Math.floor(n / 10)]; n %= 10; if (n) out += '-' + ONES[n]; }
        else if (n > 0) { out += ONES[n]; }
        return out;
    }

    function toWords(n) {
        n = Math.floor(Math.abs(n));
        if (n === 0) return 'Zero';
        const groups = [[1e12, 'Trillion'], [1e9, 'Billion'], [1e6, 'Million'], [1e3, 'Thousand']];
        let out = '';
        for (const [size, name] of groups) {
            if (n >= size) {
                out += (out ? ' ' : '') + under1000(Math.floor(n / size)) + ' ' + name;
                n %= size;
            }
        }
        if (n > 0) out += (out ? ' ' : '') + under1000(n);
        return out;
    }

    function amountInWords(amount) {
        const v = Math.abs(parseFloat(amount) || 0);
        const whole = Math.floor(v);
        // Rounded, not truncated: 0.005 short of a centavo is a centavo on a
        // form that also prints the figure to two places.
        const cents = Math.round((v - whole) * 100);
        return toWords(whole) + ' Pesos and ' + String(cents).padStart(2, '0') + '/100 Only';
    }

    // Is there actually a person here? '-' is the legacy placeholder for
    // "nobody" and is all over the live data — Inspected_by_4 is literally '-'
    // on most records — so a bare non-empty check is not enough.
    function hasName(v) {
        const t = String(v == null ? '' : v).trim();
        return t !== '' && t !== '-';
    }

    // ── The form ────────────────────────────────────────────────────────────

    // One end-user signature block: the heading, the name, then the three
    // ruled lines the person fills in by hand.
    function endUser(name) {
        return '<div class="iar-eu">'
             + '<div class="iar-eu-lbl">END USER/ WAREHOUSE :</div>'
             + '<div class="iar-eu-name">' + esc(name) + '</div>'
             + '<div class="iar-eu-rule">Signature over Printed Name</div>'
             + '<div class="iar-eu-rule">Title/Designation</div>'
             + '<div class="iar-eu-rule">Contact Number</div>'
             + '</div>';
    }

    // One inspectorate signature: name over a rule, office under it. The office
    // runs through Propercase() exactly as the report's formula does.
    function inspectorSign(name, office) {
        if (!hasName(name)) return '';
        return '<div class="iar-sign">'
             + '<div class="iar-sign-name">' + esc(name) + '</div>'
             + '<div class="iar-sign-rule"></div>'
             + '<div class="iar-sign-dept">' + esc(properCase(office || '')) + '</div>'
             + '</div>';
    }

    /* PROGRESS BILLING.
     *
     * `Inspection and Acceptance Progress Billing Report.rpt` is not a separate
     * form — it is this one with four changes, which is why it is a flag here
     * rather than a second builder. Diffed object by object against
     * tools/rpt_reports/ia.layout.tsv and iapb.layout.tsv; every coordinate
     * outside these four is identical:
     *
     *   1. a subtitle under the title (Text36, L=3.500 T=1.500), where the
     *      ordinary form has nothing;
     *   2. the billing period printed immediately below the last item row and
     *      ABOVE "***nothing follows***" — the whole lower half of the Crystal
     *      form is shifted down by exactly that line's height to make room
     *      (nothing-follows 0.083 -> 0.250, the totals rule 0.417 -> 0.740);
     *   3. Date carries the delivery date. On the ordinary form that cell is
     *      ruled but empty, because the report binds it to a column
     *      tbl_inspection does not have — on tbl_inspection_progress_billing
     *      that column DOES exist and Inspect_Print.vb selects it;
     *   4. three inspectorate signatures, not four. Inspect_Print.vb has the
     *      fourth commented out and the .rpt has no Inspectorate4 parameter.
     *      Not reproduced as a cap: the block already renders only the officers
     *      who have a name, so three names give the legacy result exactly, and
     *      a fourth prints rather than being silently dropped from a signed
     *      document.
     *
     * TWO DELIBERATE DEPARTURES, both because the legacy could not do better:
     *
     *   The billing NUMBER is printed. The Crystal subtitle is static text and
     *   the VB's SELECT never fetches Progress_Billing, so on paper the first
     *   and the sixth billing of a contract are distinguishable only by their
     *   date range — on a document that authorises a payment. It prints here.
     *
     *   Invoice No. is kept. The progress-billing .rpt drops the field the
     *   ordinary one has, and its SELECT does not fetch Invoice_Number, so the
     *   receipt backing the payment does not appear on it at all. We hold that
     *   value, so it prints.
     */
    /* A line with nothing delivered on it does not belong on the report.
     *
     * The inspection grid keeps every line of the purchase order, so a delivery
     * that covered only part of the order saves the rest at quantity 0 — and
     * those were printing as ruled, empty rows on a document certifying what
     * was received.
     *
     * The test is deliberately NOT "quantity is zero", because two kinds of row
     * legitimately have no quantity:
     *
     *   captions   "LOT 1" and friends — no quantity, no unit cost, no total.
     *              They are the form's structure and must stay.
     *   lump sums  no quantity, but a real Total Cost. They are money, and
     *              dropping one would change the figure at the bottom.
     *
     * What is dropped is a PRICED line that delivered nothing: a unit cost is
     * on file, so it is a real item, and both the quantity and the total are
     * zero. Nothing dropped this way can move the total, because everything
     * dropped is worth exactly zero. See the note on line shapes in
     * pbLineKind() in api/inspection_progress.php, which classifies the same
     * three shapes for the progress-billing screen.
     */
    function wasDelivered(it) {
        const q = parseFloat(it.quantity) || 0;
        const u = parseFloat(it.unit_cost) || 0;
        const t = parseFloat(it.total_cost) || 0;
        if (q > 0 || t !== 0) return true;   // delivered, or carries money
        return u <= 0;                        // no price on file at all -> a caption, keep it
    }

    function buildSheet(d, opts) {
        opts = opts || {};
        const billing = opts.billing || null;
        const items = (d.items || []).filter(wasDelivered);
        const officeOf = opts.inspectorOffice || (() => '');
        const total = items.reduce((sum, it) => sum + (parseFloat(it.total_cost) || 0), 0);

        // Rows are numbered the way Crystal's RecordNumber does it — counting
        // every row printed, description-only lines included.
        const rows = items.map((it, i) => {
            const priced = (parseFloat(it.total_cost) || 0) !== 0;
            return '<tr>'
                 + '<td class="center">' + (i + 1) + '</td>'
                 + '<td class="center">' + esc(priced ? qty(it.quantity) : '') + '</td>'
                 + '<td class="center">' + esc(priced ? (it.unit || '') : '') + '</td>'
                 + '<td class="desc">' + esc(it.items_description || '') + '</td>'
                 + '<td class="num">' + esc(priced ? money(it.unit_cost) : '') + '</td>'
                 + '<td class="num">' + esc(priced ? money(it.total_cost) : '') + '</td>'
                 + '</tr>';
        }).join('');

        // End-user blocks: the ones with a name, packed from the left, PLUS
        // opts.blankEndUsers extra blocks with no name typed in — just the
        // label and the three ruled lines to write on by hand.
        //
        // Requested 2026-09-09: the office often prints before anyone has told
        // them who the end user is, and typing a name in just to make the
        // block appear (only named blocks used to print at all) meant either
        // a placeholder that had to be remembered to fix later, or the office
        // typing junk text purely to get a blank line onto paper. Now a blank
        // block is a first-class thing to ask for, not a side effect of a fake
        // name — see iarBlankEndUsers in inspection.php.
        //
        // Named ones keep the original's slot order — End_User2..5 are
        // subreports laid left to right and End_User1 is the single block in
        // the main report, which is why End User 1 comes LAST. Blanks pad
        // after them, capped so five slots is still the most the row ever
        // shows (there is no sixth block in the .rpt this recreates).
        const namedEndUsers = ['end_user2', 'end_user3', 'end_user4', 'end_user5', 'end_user1']
            .map(k => d[k]).filter(hasName);
        const blankSlots = Math.max(0, Math.min(5 - namedEndUsers.length, parseInt(opts.blankEndUsers, 10) || 0));
        const endUsers = namedEndUsers.map(endUser).join('')
            + Array(blankSlots).fill('').map(endUser).join('');

        const inspectors = [d.inspected_by_1, d.inspected_by_2, d.inspected_by_3, d.inspected_by_4]
            .filter(hasName).map(n => inspectorSign(n, officeOf(n))).join('');

        // The billing period, printed between the last item row and
        // "***nothing follows***" — see difference 2 above. Built here rather
        // than inline in the concatenation below: that chain is one long
        // expression whose every line starts with a `+`, and a multi-line
        // ternary spliced into it turns `+ ? …` into a syntax error.
        const periodRow = (billing && (billing.dateFrom || billing.dateTo))
            ? '<tr><td colspan="6" class="iar-billing-period">Billing Period : '
              + '<span class="iar-period-date">' + (esc(fmtDate(billing.dateFrom)) || '__________') + '</span>'
              + ' to <span class="iar-period-date">' + (esc(fmtDate(billing.dateTo)) || '__________') + '</span>'
              + '</td></tr>'
            : '';

        // Purpose and Department, concatenated exactly as the report's formula
        // does it — one field, not two, so it wraps as one sentence.
        const purposeLine = [d.purpose, d.department]
            .filter(v => v && String(v).trim() !== '').map(v => String(v).trim()).join(' ');

        return ''
        + '<div class="iar-sheet"><div class="iar-frame">'

        // ── Letterhead ──────────────────────────────────────────────────────
        // City seal matches the Obligation Request (purchase_orders.php's
        // orq-letterhead): image/citylogo.png, not the smaller city-seal.png
        // this used before. Requested 2026-09-09, same reasoning as the DV
        // letterhead fix earlier that day — one seal, consistent across every
        // form this office prints together.
        + '<table class="iar-letterhead"><tr>'
        +   '<td class="iar-seal"><img src="image/citylogo.png" alt=""></td>'
        +   '<td><div class="iar-letterhead-text">'
        +     '<strong>CITY GOVERNMENT OF MALAYBALAY</strong><br>'
        +     '<strong>CITY GENERAL SERVICES OFFICE</strong><br>'
        +     'Casisang, Malaybalay City, Bukidnon 8700<br>'
        +     'Tel No. (008) 813-5689<br>'
        +     '<span class="email">malaybalaycgso@gmail.com</span>'
        +   '</div></td>'
        +   '<td class="iar-seal"><img src="image/CGSO NEW LOGO.png" alt=""></td>'
        + '</tr></table>'
        + '<hr class="iar-rule">'
        + '<div class="iar-title">INSPECTION &amp; ACCEPTANCE REPORT</div>'
        + (billing ? '<div class="iar-subtitle">PROGRESS BILLING'
            + (billing.no ? ' &ndash; ' + esc(String(billing.no)) : '') + '</div>' : '')

        // ── Identification ──────────────────────────────────────────────────
        //  DR No. is ruled but empty on purpose — the legacy report binds no
        //  field to it at all, so it is written on by hand when the delivery
        //  receipt is attached.
        //
        //  Date now prints on BOTH forms. It binds to tbl_inspection.Delivery_
        //  Date, a column that did not exist until the Date of Receipt field was
        //  added (system/database/inspection_delivery_date.sql), which is why
        //  this cell printed ruled-and-empty on every ordinary inspection while
        //  the same cell on the progress-billing form was filled. Records saved
        //  before that column existed hold NULL and still print blank.
        + '<table class="iar-grid">'
        +   '<tr><td class="lbl">Supplier</td><td class="val">' + esc(d.supplier_name || '') + '</td>'
        +       '<td class="lbl">DR No.</td><td class="val blank">&nbsp;</td></tr>'
        +   '<tr><td class="lbl">PO No.</td><td class="val">' + esc(d.po_number || '') + '</td>'
        +       '<td class="lbl">Invoice No.</td><td class="val">' + esc(d.invoice_number || '') + '</td></tr>'
        +   '<tr><td class="lbl">Requisitioning Office / Department</td>'
        +       '<td class="val">' + esc(d.department || '') + '</td>'
        +       '<td class="lbl">Date</td><td class="val">'
        +           (esc(fmtDate(d.delivery_date)) || '&nbsp;') + '</td></tr>'
        + '</table>'

        // ── Items ───────────────────────────────────────────────────────────
        + '<table class="iar-items">'
        +   '<thead><tr>'
        +     '<th style="width:5%">No.</th><th style="width:8%">QTY</th><th style="width:9%">Units</th>'
        +     '<th>Items Description</th><th style="width:13%">Unit Cost</th><th style="width:14%">Total Cost</th>'
        +   '</tr></thead>'
        +   '<tbody>' + rows
        +     periodRow
        +     '<tr><td colspan="6" class="iar-nothing-follows">***nothing follows***'
        +       (opts.initial ? '<span class="iar-initial">' + esc(opts.initial) + '</span>' : '')
        +     '</td></tr>'
        +   '</tbody>'
        + '</table>'

        // ── Money ───────────────────────────────────────────────────────────
        + '<div class="iar-summary-box">'
        +   '<table class="iar-totals"><tr>'
        +     '<td class="iar-words-label">Amount in Words:</td>'
        +     '<td>' + esc(amountInWords(total)) + '</td>'
        +     '<td class="iar-total-label">TOTAL:</td>'
        +     '<td class="iar-total-value">' + esc(money(total)) + '</td>'
        +   '</tr></table>'
        +   '<table class="iar-purpose-row"><tr>'
        +     '<td class="iar-purpose-label">PURPOSE :</td>'
        +     '<td>' + esc(purposeLine) + '</td>'
        +   '</tr></table>'
        + '</div>'

        // ── Certification and end users ─────────────────────────────────────
        + '<div class="iar-certify">I hereby CERTIFY to have received this '
        +   '<span class="iar-blank">&nbsp;</span> the above items in good conditions, '
        +   'as to quality, quantity and specification.</div>'
        + (endUsers ? '<div class="iar-endusers">' + endUsers + '</div>' : '')

        // ── Inspection / Acceptance ─────────────────────────────────────────
        + '<table class="iar-ia"><tr>'
        +   '<td>'
        +     '<div class="iar-ia-head">INSPECTION</div>'
        +     '<div class="iar-ia-body">'
        // Same value as the header's own "Date" cell above (d.delivery_date),
        // not tbl_inspection's separate date_inspected column — the two used
        // to disagree because date_inspected records when the inspection was
        // actually performed/saved, which can land on a different day than
        // the delivery this report is about.
        +       '<div class="iar-date-line">Date Inspected: '
        +         '<span class="iar-blank">' + (esc(fmtDate(d.delivery_date)) || '&nbsp;') + '</span></div>'
        +       '<div class="iar-check"><span class="iar-cbox"></span>'
        +         '<span class="iar-check-text">Inspected, verified and found OK as to quantity '
        +         'and specifications</span></div>'
        +       '<div class="iar-sign-grid">' + inspectors + '</div>'
        +     '</div>'
        +   '</td>'
        +   '<td class="ia-right">'
        +     '<div class="iar-ia-head">ACCEPTANCE</div>'
        +     '<div class="iar-ia-body">'
        +       '<div class="iar-date-line">Date Received: '
        +         '<span class="iar-blank">&nbsp;</span></div>'
        +       '<div class="iar-check"><span class="iar-cbox"></span>'
        +         '<span class="iar-check-text">COMPLETE</span></div>'
        +       '<div class="iar-check"><span class="iar-cbox"></span>'
        +         '<span class="iar-check-text">PARTIAL</span></div>'
        +       '<div class="iar-accept-sign">'
        +         '<div class="iar-sign-name">' + esc(d.accepted_by || '') + '</div>'
        +         '<div class="iar-sign-rule"></div>'
        +         '<div class="iar-accept-title">CEEDMO/GSO-DESIGNATE</div>'
        +       '</div>'
        +     '</div>'
        +   '</td>'
        + '</tr></table>'

        + '</div></div>';
    }

    global.IarPrint = {
        buildSheet,
        amountInWords,
        properCase,
        // Exposed so the page can say how many lines the printout will actually
        // carry, and so the rule can be tested on its own.
        wasDelivered,
        // Exposed so the page can show the same total it prints.
        totalOf: items => (items || []).reduce((s, it) => s + (parseFloat(it.total_cost) || 0), 0),
    };
})(window);
