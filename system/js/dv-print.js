/* ── Disbursement Voucher — build the printed form ────────────────────────────
 *
 * Replaces `vb/Inspection and Acceptance/Voucher_Print.vb` and the Crystal
 * report it drove, `Crystal Reports/Disbursement Voucher.rpt`. The layout lives
 * entirely in css/dv-print.css; this file only decides WHAT goes in each slot,
 * the same split js/iar-print.js uses, so the form stays checkable against
 * tools/rpt_reports/dv.layout.tsv without reading any JavaScript.
 *
 * Data contract — the nine SetParameterValue calls Voucher_Print.vb made, plus
 * the one figure it pushed in as a DataSet:
 *
 *   payee address tin department        {?Supplier} {?Address} {?TIN} {?Department}
 *   category explanation                {?Category} {?Category_Explanation}
 *   accountant treasurer mayor          {?Accountant} {?Treasurer} {?Mayor}
 *   amount                              SUM(Total_Cost) — the DataSet's only row
 *   billing {no, dateFrom, dateTo}      progress billing only; see below
 *   poNumber                            see the note on the reference line
 *
 * Everything else the form shows is fixed text or a ruled blank. The blanks are
 * blank on purpose: No., Obligation Request No., Code, Check No., Bank Name,
 * every Date, the JEV No. and all four tick boxes bind to nothing in the .rpt,
 * so they are filled in by hand at the counter and are reproduced that way.
 * ──────────────────────────────────────────────────────────────────────────── */
(function (global) {
    'use strict';

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function money(v) {
        const n = parseFloat(v);
        return isFinite(n)
            ? n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '0.00';
    }

    // The .rpt's wordssumtotal1 is `propercase(towords({...},2)) + " Only"` —
    // character for character the formula the IAR uses, so it is the IAR's
    // implementation rather than a second copy that can drift from it.
    function amountInWords(v) {
        if (global.IarPrint && typeof IarPrint.amountInWords === 'function') {
            return IarPrint.amountInWords(v);
        }
        return '';
    }

    // A supplied value prints on the rule; anything blank leaves the rule empty
    // to be written on. '-' is the legacy placeholder for "nobody" and is all
    // over the live data, so it counts as blank. Never the string "undefined"
    // on a document somebody signs.
    function onRule(v, bold) {
        const t = String(v == null ? '' : v).trim();
        if (t === '' || t === '-') return '&nbsp;';
        return bold ? '<strong>' + esc(t) + '</strong>' : esc(t);
    }

    function multiline(v) {
        return esc(String(v == null ? '' : v).trim()).replace(/\r?\n/g, '<br>');
    }

    // Signature / Printed Name / Position — the stack the .rpt repeats in every
    // certification block. The position is the block's fixed caption, not a
    // value anyone types, exactly as Text48/49/50 are static text there.
    function signatureBlock(name, position) {
        return '<table class="dv-sign">'
             + '<tr><td class="k">Signature:</td><td class="v">&nbsp;</td></tr>'
             + '<tr><td class="k">Printed Name:</td><td class="v">' + onRule(name, true) + '</td></tr>'
             + '<tr><td class="k">Position:</td><td class="v plain">' + esc(position) + '</td></tr>'
             + '</table>';
    }

    /* ONE DELIBERATE DEPARTURE: the reference line under the title.
     *
     * The Crystal form carries no purchase order number anywhere — the only
     * field that could hold one, "Obligation Request No.", is unbound. On paper
     * a voucher is therefore tied to its PO only by the papers stapled to it,
     * and on a progress-billing contract the first and the sixth voucher are
     * otherwise identical documents for different money. Both are printed here,
     * quietly, under the title. The certification blocks are untouched.
     *
     * This is the same call js/iar-print.js makes for the billing number, and
     * for the same reason.
     */
    // ANOTHER DELIBERATE DEPARTURE, same shape as purchase_orders.php's
    // Obligation Request ("Payment" vs "Reimbursement" in
    // buildObligationPreviewHtml): Voucher_Print.vb's explanation line was
    // fixed text, "To payment of {?Category}", with no reimbursement wording
    // anywhere in the .rpt. A reimbursement voucher pays an employee back for
    // money they already spent, not the supplier on the PO — that is what the
    // Payee field is for (always free text here, typed over the supplier name
    // the same way the Obligation Request's payee already works), and the
    // explanation line now says so instead of claiming a reimbursement is a
    // payment. Requested 2026-09-09.
    function buildSheet(v) {
        v = v || {};
        const billing = v.billing || null;
        const total = parseFloat(v.amount) || 0;
        const actionWord = v.reportType === 'reimbursement' ? 'Reimbursement' : 'payment';

        const refBits = [];
        if (v.poNumber) refBits.push(esc(v.poNumber));
        if (billing && billing.no) refBits.push('Progress Billing - ' + esc(String(billing.no)));
        const reference = refBits.length
            ? '<div class="dv-subtitle">' + refBits.join('<span class="sep">&middot;</span>') + '</div>'
            : '';

        const explanation = String(v.explanation || '').trim()
            ? '<div>' + multiline(v.explanation) + '</div>'
            : '';

        return ''
        + '<div class="dv-sheet"><div class="dv-frame">'

        // ── Letterhead ──────────────────────────────────────────────────────
        // One seal only, same as the Obligation Request (purchase_orders.php's
        // orq-letterhead): image/citylogo.png, not the CGSO department logo
        // that used to sit on the right. Two different marks side by side read
        // as two different offices vouching for the same document — requested
        // 2026-09-09 so this form matches the Obligation Request it is always
        // filed alongside.
        + '<table class="dv-letterhead"><tr>'
        +   '<td class="dv-seal"><img src="image/citylogo.png" alt=""></td>'
        +   '<td><div class="dv-letterhead-text">'
        +     '<strong>CITY GOVERNMENT OF MALAYBALAY</strong><br>'
        +     '<strong>CITY GENERAL SERVICES OFFICE</strong><br>'
        +     'Casisang, Malaybalay City, Bukidnon 8700<br>'
        +     'Tel No. (008) 813-5689<br>'
        +     '<span class="email">malaybalaycgso@gmail.com</span>'
        +   '</div></td>'
        + '</tr></table>'
        + '<hr class="dv-rule">'

        // ── Title and the voucher number blank ──────────────────────────────
        + '<table class="dv-titlebar"><tr>'
        +   '<td class="dv-title">DISBURSEMENT VOUCHER</td>'
        +   '<td class="dv-no-lbl">No.</td><td class="dv-no-val">&nbsp;</td>'
        + '</tr></table>'
        + reference

        // ── Mode of payment, payee, TIN, obligation reference ───────────────
        + '<table class="dv-grid">'
        +   '<tr>'
        +     '<td class="lbl" style="width:12%">Mode of<br>Payment</td>'
        +     '<td class="dv-mode-cell" colspan="5">'
        +       '<div class="dv-mode-opts">'
        +         '<span class="dv-tick"><span class="dv-box"></span> Check</span>'
        +         '<span class="dv-tick"><span class="dv-box"></span> Cash</span>'
        +         '<span class="dv-tick"><span class="dv-box"></span> Others</span>'
        +       '</div>'
        +     '</td>'
        +   '</tr>'
        +   '<tr>'
        +     '<td class="lbl">Payee</td>'
        +     '<td class="val" style="width:32%">' + onRule(v.payee, true) + '</td>'
        +     '<td class="lbl" style="width:8%">TIN:</td>'
        +     '<td class="val" style="width:16%">' + onRule(v.tin) + '</td>'
        +     '<td class="lbl" style="width:18%">Obligation<br>Request No.</td>'
        +     '<td class="val" style="width:14%">&nbsp;</td>'
        +   '</tr>'
        +   '<tr>'
        +     '<td class="lbl">Address</td>'
        +     '<td class="val">' + onRule(v.address) + '</td>'
        +     '<td class="lbl">Office/Unit/<br>Project:</td>'
        +     '<td class="val">' + onRule(v.department) + '</td>'
        +     '<td class="lbl">Code:</td>'
        +     '<td class="val">&nbsp;</td>'
        +   '</tr>'
        + '</table>'

        // ── Explanation and amount ──────────────────────────────────────────
        + '<table class="dv-expl">'
        +   '<tr><th>EXPLANATION</th><th style="width:24%">AMOUNT</th></tr>'
        +   '<tr>'
        +     '<td class="dv-expl-body">'
        +       'To ' + actionWord + ' of <span class="dv-expl-category">' + onRule(v.category) + '</span>'
        +       explanation
        +       '<div class="dv-expl-note">As per approved supporting papers hereto attached.</div>'
        +       '<div>in the amount of <span class="dv-dots">. . . . . . . . . . . . . . . .</span></div>'
        +     '</td>'
        +     '<td class="dv-amount-cell">PHP&nbsp;' + esc(money(total)) + '</td>'
        +   '</tr>'
        +   '<tr>'
        +     '<td><table style="width:100%;border-collapse:collapse"><tr>'
        +       '<td class="dv-words-label" style="border:none;padding:0 6px 0 0">Amount in Words:</td>'
        +       '<td class="dv-words" style="border:none;padding:0">' + esc(amountInWords(total)) + '</td>'
        +     '</tr></table></td>'
        +     '<td class="dv-total-cell">&#8369;&nbsp;' + esc(money(total)) + '</td>'
        +   '</tr>'
        + '</table>'

        // ── A and B ─────────────────────────────────────────────────────────
        //  B's second line really is the single word "Funds" on the .rpt
        //  (Text27), not "Funds Available". Left as the office's form has it.
        + '<table class="dv-cert"><tr>'
        +   '<td>'
        +     '<span class="dv-letter">A</span><span class="dv-cert-head">Certified:</span>'
        +     '<div class="dv-check-row"><span class="dv-box"></span>'
        +       '<span>Allotment obligated for the purpose as indicated above</span></div>'
        +     '<div class="dv-check-row"><span class="dv-box"></span>'
        +       '<span>Supporting documents complete</span></div>'
        +     signatureBlock(v.accountant, 'City Accountant')
        +   '</td>'
        +   '<td>'
        +     '<span class="dv-letter">B</span><span class="dv-cert-head">Certified:</span>'
        // No tick box here, unlike A. The .rpt's only boxes in this band are
        // Box7/Box10/Box11/Box13 (the four letters) and Box8/Box9 (A's two
        // conditions); "Funds" is Text27, plain text with nothing to tick.
        +     '<div class="dv-plain-row">Funds</div>'
        +     signatureBlock(v.treasurer, 'City Treasurer')
        +   '</td>'
        + '</tr></table>'

        // ── C and D ─────────────────────────────────────────────────────────
        + '<table class="dv-cert"><tr>'
        +   '<td>'
        +     '<span class="dv-letter">C</span><span class="dv-cert-head">Approved for Payment</span>'
        +     signatureBlock(v.mayor, 'City Mayor')
        +   '</td>'
        +   '<td>'
        +     '<span class="dv-letter">D</span><span class="dv-cert-head">Received Payment</span>'
        +     '<table class="dv-inline"><tr>'
        +       '<td class="k">Check No.</td><td class="v">&nbsp;</td>'
        +       '<td class="k" style="padding-left:8px">Bank Name</td><td class="v">&nbsp;</td>'
        +       '<td class="k" style="padding-left:8px">Date</td><td class="v">&nbsp;</td>'
        +     '</tr></table>'
        +     '<table class="dv-sign">'
        +       '<tr><td class="k">Signature:</td><td class="v">&nbsp;</td></tr>'
        +       '<tr><td class="k">Printed Name:</td><td class="v">' + onRule(v.payee, true) + '</td></tr>'
        +     '</table>'
        +     '<table class="dv-inline"><tr>'
        +       '<td class="k">OR/Other Document<br>JEV No.</td><td class="v">&nbsp;</td>'
        +       '<td class="k" style="padding-left:8px">Date</td><td class="v">&nbsp;</td>'
        +     '</tr></table>'
        +   '</td>'
        + '</tr></table>'

        + '</div></div>';
    }

    global.DvPrint = { buildSheet, amountInWords };
})(window);
