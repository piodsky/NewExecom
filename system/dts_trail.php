<?php
// Shared Document Tracking trail viewer.
//
// Included by any page that wants a "where is this document now" popup —
// Document Tracking itself, and the Purchase Orders list. One copy so the two
// screens can never drift apart.
//
// Include it once, at the END of <body> (outside .top-navbar — same stacking
// rule as profile_modal.php), then call:
//     dtsTrackPo('PO-sys2026725591')   — resolves the barcode from the PO
//     dtsTrackBarcode('2026026449')    — straight barcode lookup
//
// It brings its own modal, styles and fetch logic, and uses DTS_* names
// throughout so it cannot collide with the host page's own variables.
?>
<style>
/* Vertical timeline: one entry per office-to-office movement. The rail and
   markers encode real state — filled means released onward, hollow amber means
   the document is still sitting in that office. */
.dts-trail { list-style: none; margin: 0; padding: 0; position: relative; }
.dts-trail::before {
    content: ''; position: absolute; left: 11px; top: 6px; bottom: 6px;
    width: 2px; background: #e2e8f0;
}
.dts-hop { position: relative; padding: 0 0 1.05rem 2.1rem; }
.dts-hop:last-child { padding-bottom: 0; }
.dts-hop::before {
    content: ''; position: absolute; left: 4px; top: 4px;
    width: 16px; height: 16px; border-radius: 50%;
    background: #fff; border: 3px solid #cbd5e1;
}
.dts-hop.done::before    { background: var(--primary); border-color: var(--primary); }
.dts-hop.current::before { background: #fff; border-color: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,.18); }
.dts-hop .route { font-weight: 700; color: var(--text); font-size: .87rem; line-height: 1.35; }
.dts-hop .route .to { color: var(--primary); }
.dts-hop .when { font-size: .75rem; color: var(--muted); margin-top: .15rem; font-variant-numeric: tabular-nums; }
.dts-hop .rmk  { font-size: .76rem; color: #475569; margin-top: .2rem; font-style: italic; }
.dts-hop .here,
.dts-po-head .here { display: inline-block; font-size: .68rem; font-weight: 700; color: #b45309; background: #fef3c7; padding: .1rem .4rem; border-radius: 20px; margin-left: .35rem; }

.dts-now {
    display: flex; align-items: flex-start; gap: .8rem; padding: .9rem 1.05rem;
    border-radius: 12px; background: #eff6ff; border: 1px solid #bfdbfe; margin-bottom: 1rem;
}
.dts-now .ico { width: 38px; height: 38px; border-radius: 10px; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.dts-now .k { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }
.dts-now .v { font-size: 1rem; font-weight: 700; color: var(--navy); }
.dts-now .sub { font-size: .76rem; color: var(--muted); margin-top: .1rem; }
/* Past a week without moving, the document is probably stuck — say so in the
   colour, not just the number. */
.dts-now.stale { background: #fffbeb; border-color: #fde68a; }
.dts-now.stale .ico { background: #d97706; }

.dts-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: .95rem 1.15rem; margin-bottom: 1.1rem; }
.dts-card:last-child { margin-bottom: 0; }
.dts-card-title { display: flex; align-items: center; gap: .55rem; font-weight: 700; color: var(--navy); font-size: .92rem; margin-bottom: .65rem; }
.dts-card-title i { color: var(--primary); }
.dts-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: .8rem; }
.dts-strip .cell { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: .55rem .75rem; }
.dts-strip .cell .k { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }
.dts-strip .cell .v { font-size: .87rem; font-weight: 600; color: var(--text); word-break: break-word; }
.dts-note { font-size: .74rem; color: var(--muted); margin-top: .4rem; }
.dts-purpose { font-size: .78rem; color: var(--muted); margin-top: .6rem; line-height: 1.4; }
/* One row per purchase order filed under this document. Several POs sharing a
   single tracked document is normal here, so this is a list even when it holds
   one item. */
.dts-po { padding: .5rem 0; border-top: 1px dashed #e2e8f0; }
.dts-po:first-of-type { border-top: 0; padding-top: 0; }
.dts-po.origin { position: relative; }
.dts-po-head { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; }
.dts-po-num {
    font-size: .84rem; font-weight: 700; color: #c2410c;
    background: #fff2e6; border: 1px solid #fed7aa;
    padding: .1rem .45rem; border-radius: 6px; white-space: nowrap;
}
.dts-po-pr {
    font-size: .74rem; font-weight: 700; color: #1d4ed8;
    background: #e8f0ff; border: 1px solid #bfdbfe;
    padding: .1rem .45rem; border-radius: 6px; white-space: nowrap;
}
.dts-po-sub { font-size: .76rem; color: var(--muted); margin-top: .2rem; line-height: 1.35; }
.dts-count {
    font-size: .7rem; font-weight: 700; color: var(--primary);
    background: #eff6ff; border: 1px solid #bfdbfe;
    padding: .05rem .4rem; border-radius: 20px;
}
.dts-empty { text-align: center; color: var(--muted); padding: 2.5rem 1rem; }
.dts-empty i { font-size: 2rem; opacity: .35; display: block; margin-bottom: .6rem; }
</style>

<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="dtsTrailModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-route me-2"></i>Document Trail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="dtsTrailBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
const DTS_API = 'api/document_tracking.php';

function dtsEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
function dtsBlankDate(v) { return !v || String(v).startsWith('0000'); }
function dtsDateTime(v) {
    if (dtsBlankDate(v)) return null;
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d)) return null;
    return d.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}
function dtsMoney(v) {
    const n = parseFloat(v) || 0;
    return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function dtsTrackPo(poNumber)  { dtsTrack({ po_number: poNumber }); }
function dtsTrackBarcode(code) { dtsTrack({ barcode: code }); }

async function dtsTrack(query) {
    const body = document.getElementById('dtsTrailBody');
    body.innerHTML = '<div class="dts-empty"><i class="fas fa-spinner fa-spin"></i>Looking up the document…</div>';
    const el = document.getElementById('dtsTrailModal');
    (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    try {
        const res = await fetch(`${DTS_API}?${new URLSearchParams(Object.assign({ document: '1' }, query))}`);
        const data = await res.json();
        if (!data.success) {
            body.innerHTML = `<div class="dts-empty"><i class="fas fa-circle-question"></i>${dtsEsc(data.message || 'Not found.')}</div>`;
            return;
        }
        dtsRender(data);
    } catch (err) {
        body.innerHTML = '<div class="dts-empty"><i class="fas fa-triangle-exclamation"></i>The Document Tracking system could not be reached.</div>';
        console.error(err);
    }
}

function dtsRender(d) {
    const h = d.header || {};
    const stale = (d.idle_days != null && d.idle_days >= 7);
    const sourceLabel = {
        typed:           'Looked up by barcode.',
        on_file:         'Barcode taken from the purchase order.',
        derived_from_pr: 'Barcode found from the PR number — it is not yet saved on the purchase order.',
    }[d.source] || '';

    const now = `
      <div class="dts-now ${stale ? 'stale' : ''}">
        <div class="ico"><i class="fas fa-${stale ? 'hourglass-half' : 'location-dot'}"></i></div>
        <div>
          <div class="k">Currently with</div>
          <div class="v">${dtsEsc(d.current) || 'Unknown'}</div>
          <div class="sub">${d.hops} movement${d.hops === 1 ? '' : 's'} on record${
            d.last_moved ? ' · last moved ' + dtsEsc(dtsDateTime(d.last_moved) || '') : ''}${
            d.idle_days != null ? ' · ' + d.idle_days + ' day' + (d.idle_days === 1 ? '' : 's') + ' ago' : ''}</div>
        </div>
      </div>`;

    const doc = `
      <div class="dts-card">
        <div class="dts-card-title"><i class="fas fa-file-lines"></i> Document</div>
        <div class="dts-strip">
          <div class="cell"><div class="k">Barcode</div><div class="v">${dtsEsc(d.barcode)}</div></div>
          <div class="cell"><div class="k">Type</div><div class="v">${dtsEsc(h.TPM) || '—'}</div></div>
          <div class="cell"><div class="k">Originating Office</div><div class="v">${dtsEsc(h.OfficeOrig) || '—'}</div></div>
          <div class="cell"><div class="k">Payee</div><div class="v">${dtsEsc(h.Payee) || '—'}</div></div>
          <div class="cell"><div class="k">Amount</div><div class="v">${h.Amount != null && h.Amount !== '' ? '₱' + dtsMoney(h.Amount) : '—'}</div></div>
          <div class="cell"><div class="k">Created</div><div class="v">${dtsEsc(dtsDateTime(h.DateCreated) || '—')}</div></div>
        </div>
        ${h.Purpose ? `<div class="dts-purpose"><strong>Purpose:</strong> ${dtsEsc(h.Purpose)}</div>` : ''}
        ${sourceLabel ? `<div class="dts-note"><i class="fas fa-circle-info me-1"></i>${dtsEsc(sourceLabel)}</div>` : ''}
      </div>`;

    // Answers "which purchase order is this?" — the first thing anyone asks
    // after seeing where a document sits. Populated in both directions: opened
    // from a PO, and looked up cold by barcode.
    const pos = (d.pos && d.pos.length) ? d.pos : (d.po ? [d.po] : []);
    const many = pos.length > 1;
    const po = pos.length ? `
      <div class="dts-card">
        <div class="dts-card-title"><i class="fas fa-file-invoice-dollar"></i>
          ${many ? `Purchase Orders <span class="dts-count">${pos.length}</span>` : 'Purchase Order'}</div>
        ${pos.map(p => `
          <div class="dts-po${p.PO_Number === d.origin_po ? ' origin' : ''}">
            <div class="dts-po-head">
              <span class="dts-po-num">${dtsEsc(p.PO_Number)}</span>
              ${p.PR_Number ? `<span class="dts-po-pr">PR ${dtsEsc(p.PR_Number)}</span>` : ''}
              ${many && p.PO_Number === d.origin_po ? '<span class="here">THIS PO</span>' : ''}
            </div>
            <div class="dts-po-sub">${[
                dtsEsc(p.Supplier_Name),
                dtsEsc(p.Department),
                dtsEsc(dtsDateTime(p.Date_Created) || ''),
            ].filter(Boolean).join(' &middot; ') || '&mdash;'}</div>
          </div>`).join('')}
        ${many ? '<div class="dts-note"><i class="fas fa-circle-info me-1"></i>'
               + 'These purchase orders are all filed under the same tracked document.</div>' : ''}
      </div>` : '';
    // The last hop with no release time is where the document still is.
    const openIdx = (d.trail || []).reduce((acc, r, i) => dtsBlankDate(r.DateOut) ? i : acc, -1);
    const hops = (d.trail || []).map((r, i) => {
        const isOpen = i === openIdx;
        const from = dtsEsc(r.OfficeFrom) || '—';
        const to   = dtsEsc(r.OfficeTo);
        const tIn  = dtsDateTime(r.DateIn);
        const tOut = dtsDateTime(r.DateOut);
        const when = [
            tIn  ? 'Received ' + tIn : null,
            tOut ? 'Released ' + tOut : (isOpen ? 'Not yet released' : null),
        ].filter(Boolean).join(' · ');
        return `<li class="dts-hop ${isOpen ? 'current' : 'done'}">
            <div class="route">${from}${to ? ' <span class="to">&rarr; ' + to + '</span>' : ''}${isOpen ? '<span class="here">HERE</span>' : ''}</div>
            <div class="when">${dtsEsc(when) || '—'}</div>
            ${r.Remarks ? `<div class="rmk">${dtsEsc(r.Remarks)}</div>` : ''}
        </li>`;
    }).join('');

    document.getElementById('dtsTrailBody').innerHTML = now + po + doc + `
      <div class="dts-card">
        <div class="dts-card-title"><i class="fas fa-timeline"></i> Routing Trail</div>
        ${hops ? `<ul class="dts-trail">${hops}</ul>`
               : '<div class="dts-empty"><i class="fas fa-inbox"></i>No movements recorded yet.</div>'}
      </div>`;
}
</script>
