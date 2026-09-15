<?php
require_once 'config.php';
requireAuth();
requireModule('competitive_bidding');
$currentUser = getCurrentUser();
$activePage  = 'competitive_bidding';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Competitive Bidding | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="vendor/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/item-grid.css">
<style>
/* Same card / field / chip vocabulary as Purchase Orders and Document
   Tracking, so all three screens read as one system. Only what this page
   adds is here. */
.table-actions { display: flex; gap: .35rem; flex-wrap: wrap; }
.po-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem 1.4rem; margin-bottom: 1.1rem; }
.po-card-title { display: flex; align-items: center; gap: .55rem; font-weight: 700; color: var(--navy); font-size: 1rem; margin-bottom: 1.1rem; }
.po-card-title i { color: var(--primary); font-size: 1.05rem; }
.po-field-group { margin-bottom: 1rem; }
.po-field-group > label { font-size: .8rem; font-weight: 600; color: var(--muted); margin-bottom: .3rem; display: block; }
.po-field-group .form-control, .po-field-group .form-select { font-size: .87rem; min-height: 38px; }
/* These grow to fit their own content (autoGrowField), so the manual resize
   grabber is both redundant and a way to leave a field stuck at the wrong
   height. overflow:hidden stops a scrollbar flashing up mid-grow. */
.cb-autogrow { resize: none; overflow: hidden; }
.po-field-hint { font-size: .72rem; color: var(--muted); margin-top: .25rem; line-height: 1.35; }
.req::after { content: ' *'; color: #dc2626; font-weight: 700; }
/* The statutory rate, sitting on the label so it reads as part of the field's
   name rather than as advice that can be scrolled past. */
.cb-pct-tag {
    font-weight: 700; font-size: .64rem; text-transform: none; letter-spacing: 0;
    color: #1d4ed8; background: #e8f0ff; border: 1px solid #bfdbfe;
    border-radius: 20px; padding: .04rem .4rem; margin-left: .3rem; white-space: nowrap;
}
/* Amber when the typed figure is not the statutory rate — a look-twice, not
   an error: a bond agreed at another rate is legitimate, just worth seeing. */
.cb-pct-off { color: #92400e; font-weight: 600; }

/* css/base.css defines .badge-status but not its state modifiers — the PO
   page carries those inline. Same values here so a status reads identically
   on both screens. */
.badge-status.pending  { background: linear-gradient(135deg,#d97706,#f59e0b); color: #fff; }
.badge-status.complete { background: linear-gradient(135deg,#16a34a,#22c55e); color: #fff; }
/* The stage ladder. Deliberately one hue walking from amber through blue to
   green rather than six unrelated colours: the point of the badge is that a
   glance down the column shows how far along each set is, and six arbitrary
   colours would have to be memorised before they meant anything.
   Grey = nothing has happened yet, green = it has left this office. */
/* The subtitle carries the full contract name, which on a consolidated bid
   is several hundred characters. Two lines, then an ellipsis — the whole
   string is on the element's title attribute for anyone who needs it. */
#cbModal.cb-viewing #cbItemsTable th:last-child,
#cbModal.cb-viewing #cbItemsTable td:last-child { display: none; }
.cb-modal-subtitle {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.badge-status.st-draft   { background: linear-gradient(135deg,#64748b,#94a3b8); color: #fff; }
.badge-status.st-awarded { background: linear-gradient(135deg,#d97706,#f59e0b); color: #fff; }
.badge-status.st-bond    { background: linear-gradient(135deg,#c2410c,#ea580c); color: #fff; }
.badge-status.st-signing { background: linear-gradient(135deg,#7c3aed,#a78bfa); color: #fff; }
.badge-status.st-signed  { background: linear-gradient(135deg,#1d4ed8,#3b82f6); color: #fff; }
.badge-status.st-ntp     { background: linear-gradient(135deg,#0e7490,#06b6d4); color: #fff; }
.badge-status.st-cgso    { background: linear-gradient(135deg,#15803d,#22c55e); color: #fff; }
/* Six stages will not sit on one line on a laptop; let them wrap. */
.po-count-chips { flex-wrap: wrap; }
.po-chip.chip-on { outline: 2px solid var(--primary); outline-offset: 1px; }
/* .badge-status is nowrap by default, which suits a one-word status and cuts
   "Submission of Performance Bond" off mid-word. Capped and wrapped instead:
   two short lines read fine in a table cell, a truncated one does not. */
#cbTable .badge-status {
    white-space: normal; display: inline-block; max-width: 150px;
    line-height: 1.25; text-align: center;
}

/* Count chips — readouts and controls at once, exactly as on the PO list. */
.po-count-chips { display: flex; gap: .4rem; flex-wrap: wrap; }
.po-chip {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .3rem .7rem; border-radius: 20px;
    font-size: .75rem; font-weight: 600; white-space: nowrap;
    border: 1px solid var(--border); background: #fff; color: var(--muted);
    cursor: pointer; transition: background .15s, border-color .15s, color .15s;
}
.po-chip:hover { background: #f8fafc; }
.po-chip-num { font-weight: 700; color: var(--text); font-variant-numeric: tabular-nums; }
/* One chip per stage, each tinted with its badge's hue so a chip and the
   badges it filters to are recognisably the same thing. Same walk from grey
   through amber and blue to green as .badge-status.st-* below. */
.po-chip-draft   { border-color: #cbd5e1; color: #475569; }
.po-chip-awarded { border-color: #fcd34d; color: #b45309; }
.po-chip-bond    { border-color: #fdba74; color: #c2410c; }
.po-chip-signing { border-color: #ddd6fe; color: #6d28d9; }
.po-chip-signed  { border-color: #bfdbfe; color: #1d4ed8; }
.po-chip-ntp     { border-color: #a5f3fc; color: #0e7490; }
.po-chip-cgso    { border-color: #86efac; color: #15803d; }
.po-chip-draft   .po-chip-num { color: #475569; }
.po-chip-awarded .po-chip-num { color: #b45309; }
.po-chip-bond    .po-chip-num { color: #c2410c; }
.po-chip-signing .po-chip-num { color: #6d28d9; }
.po-chip-signed  .po-chip-num { color: #1d4ed8; }
.po-chip-ntp     .po-chip-num { color: #0e7490; }
.po-chip-cgso    .po-chip-num { color: #15803d; }

.po-chip.is-active { background: linear-gradient(135deg,#1e3a8a,#2563eb); border-color: transparent; color: #fff; }
.po-chip.is-active .po-chip-num { color: #fff; }
.po-chip-draft.is-active   { background: linear-gradient(135deg,#64748b,#94a3b8); }
.po-chip-awarded.is-active { background: linear-gradient(135deg,#d97706,#f59e0b); }
.po-chip-bond.is-active    { background: linear-gradient(135deg,#c2410c,#ea580c); }
.po-chip-signing.is-active { background: linear-gradient(135deg,#7c3aed,#a78bfa); }
.po-chip-signed.is-active  { background: linear-gradient(135deg,#1d4ed8,#3b82f6); }
.po-chip-ntp.is-active     { background: linear-gradient(135deg,#0e7490,#06b6d4); }
.po-chip-cgso.is-active    { background: linear-gradient(135deg,#15803d,#22c55e); }

/* Bid / contract numbers, tinted the way the PO list tints its own. */
.bid-num-pill, .contract-num-pill {
    display: inline-block; padding: .18rem .55rem; border-radius: 6px;
    font-size: .8rem; font-weight: 700; white-space: nowrap; border: 1px solid transparent;
}
.bid-num-pill      { color: #6d28d9; background: #f3ebff; border-color: #ddd0fb; }
.contract-num-pill { color: #1d4ed8; background: #e8f0ff; border-color: #bfdbfe; }
.cb-empty { color: var(--muted); }

/* Contract name and location are free text and often a full sentence — shown
   in full, wrapping onto as many lines as they need, capped in width so they
   wrap instead of pushing the rest of the columns off to the right. */
#cbTable tbody td { vertical-align: top; }
.cb-wrap-cell { display: block; max-width: 240px; min-width: 150px; white-space: normal; word-break: break-word; }
/* PR Amount and Savings pushed the grid two columns wider, and the six action
   buttons are the one cell with no reason to give ground: left to wrap they
   stacked into a vertical strip and every row grew to match. The table scrolls
   sideways instead, which is the right thing for it to do. */
#cbTable .table-actions { flex-wrap: nowrap; }
.cb-amount { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; font-weight: 600; }
.cb-amount.muted { font-weight: 500; color: var(--muted); }
/* Savings in the list. Green reads as money kept, amber as money over — and
   the sub-line carries the percentage, because ₱40,000 saved means nothing
   until you know whether it came off ₱50,000 or off ₱50,000,000. */
.cb-save      { display: block; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.cb-save-val  { font-weight: 700; color: #15803d; }
.cb-save-pct  { display: block; font-size: .68rem; color: var(--muted); font-weight: 600; }
.cb-save.over .cb-save-val { color: #b45309; }
.cb-save.none .cb-save-val { color: var(--muted); font-weight: 500; }

/* The savings band under the two amounts in the form: the whole reason both
   figures are on this screen, so it is stated rather than left to be worked
   out from the two numbers above it. */
.cb-savings-band {
    border-radius: 10px; padding: .55rem .75rem; border: 1px solid #bbf7d0;
    background: #f0fdf4; display: flex; align-items: baseline; gap: .55rem; flex-wrap: wrap;
}
.cb-savings-band .lab { font-size: .72rem; font-weight: 700; text-transform: uppercase;
                        letter-spacing: .04em; color: #166534; }
.cb-savings-band .val { font-size: 1.05rem; font-weight: 800; color: #15803d;
                        font-variant-numeric: tabular-nums; }
.cb-savings-band .sub { font-size: .74rem; color: #166534; }
.cb-savings-band.over { border-color: #fde68a; background: #fffbeb; }
.cb-savings-band.over .lab, .cb-savings-band.over .sub { color: #92400e; }
.cb-savings-band.over .val { color: #b45309; }
.cb-savings-band.idle { border-color: var(--border); background: #f8fafc; }
.cb-savings-band.idle .lab, .cb-savings-band.idle .sub { color: var(--muted); }
.cb-savings-band.idle .val { color: var(--muted); font-size: .9rem; font-weight: 600; }

/* The modal's left column and the item grid each scroll on their own, so a
   long specification list never pushes Save off the bottom. */
.cb-form-scroll { overflow-y: auto; max-height: 62vh; padding-right: .4rem; }
.cb-section-sep { border-top: 1px dashed var(--border); margin: .2rem 0 1.1rem; }
#cbSubtotal { font-size: 1.4rem; font-weight: 700; color: var(--navy); }

/* ── Modal chrome, lifted from the Create Purchase Order modal so the two
      screens are the same piece of software. Values are copied rather than
      shared: purchase_orders.php keeps its own inline copy (see the note in
      css/item-grid.css for why that page is left alone), and these are pinned
      to fixed colours rather than --text/--muted so the cards stay readable on
      the white modal background however those vars are retuned. ── */
.modal-cb-wide { max-width: min(1600px, 96vw); }

.po-modal-header-v2 {
    background: linear-gradient(135deg, var(--navy), #1e40af);
    color: #fff; border-bottom: none;
    padding: 1.25rem 1.5rem;
    display: flex; align-items: center; gap: 1rem;
}
.po-header-icon {
    width: 46px; height: 46px; border-radius: 14px; flex: 0 0 auto;
    background: rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #fff;
}
.po-header-text { flex: 1 1 auto; min-width: 0; }
.po-header-text .modal-title { color: #fff; font-weight: 700; font-size: 1.15rem; margin: 0; }
.po-header-text small { display: block; color: rgba(255,255,255,.72); font-size: .8rem; margin-top: .1rem; }
.po-header-close {
    width: 38px; height: 38px; flex: 0 0 auto; border-radius: 12px;
    border: 1px solid rgba(255,255,255,.3); background: rgba(255,255,255,.12); color: #fff;
    display: flex; align-items: center; justify-content: center; transition: background .15s;
}
.po-header-close:hover { background: rgba(255,255,255,.25); color: #fff; }
#cbModal .modal-content { color: #1e293b; }

/* Compact card + field density, so 31 fields fit without the modal turning
   into a scroll marathon. */
.po-card-compact { padding: .95rem 1.15rem; }
.po-card-compact .po-card-title { margin-bottom: .65rem; font-size: .92rem; }
.po-card-compact .po-field-group { margin-bottom: .4rem; }
.po-card-compact .po-field-group > label { font-size: .72rem; margin-bottom: .15rem; }
.tight-field-row .po-field-group { margin-bottom: .6rem; }

/* Icon-prefixed inputs. The icon is pointer-events:none so clicking it still
   focuses the field underneath rather than swallowing the click. */
.po-input-wrap { position: relative; }
.po-input-wrap > .po-input-icon {
    position: absolute; left: 5px; top: 50%; transform: translateY(-50%);
    width: 28px; height: 28px; border-radius: 8px; background: rgba(37,99,235,.1); color: var(--primary);
    display: flex; align-items: center; justify-content: center; font-size: .78rem; pointer-events: none;
}
.po-input-wrap > .form-control, .po-input-wrap > .form-select { padding-left: 40px; }
.po-input-wrap > textarea.form-control { padding-top: .55rem; }
/* A textarea grows downward, so its icon is pinned near the top line instead
   of centred on a box whose height keeps changing. */
.po-input-wrap > textarea.form-control ~ .po-input-icon { top: 19px; transform: none; }
.po-card-compact .po-input-wrap > .po-input-icon { width: 22px; height: 22px; font-size: .68rem; }
.po-card-compact .po-input-wrap > .form-control,
.po-card-compact .po-input-wrap > .form-select { padding: .3rem .5rem .3rem 32px; min-height: 32px; font-size: .8rem; }
.po-card-compact .po-input-wrap > textarea.form-control ~ .po-input-icon { top: 5px; }

/* Empty item grid. */
.po-items-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; padding: 3rem 1.5rem;
}
.po-items-empty-icon {
    width: 74px; height: 74px; border-radius: 50%; background: rgba(37,99,235,.1); color: var(--primary);
    display: flex; align-items: center; justify-content: center; font-size: 1.7rem; margin-bottom: 1rem;
}
.po-items-empty-title { font-weight: 700; color: #1e293b; margin-bottom: .2rem; }
.po-items-empty-sub { color: #64748b; font-size: .88rem; }

.po-add-row-link {
    display: block; text-align: center; padding: .6rem; font-size: .85rem; font-weight: 600;
    color: var(--primary); border: 1px dashed var(--border); border-radius: 8px; margin-top: .6rem;
    background: none; width: 100%; cursor: pointer;
}
.po-add-row-link:hover { background: rgba(37,99,235,.05); }

.po-items-footer-bar {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem;
    background: rgba(37,99,235,.05); border: 1px solid #e2e8f0; border-radius: 10px;
    padding: .7rem 1.1rem; margin-top: .75rem;
}
.po-items-footer-bar .po-items-count { color: #475569; font-size: .9rem; }
.po-items-footer-bar .po-items-count strong { color: #1e293b; }

/* Import guide — same vocabulary as the Purchase Order modal's, so the file
   format is explained the same way on both screens. */
.import-guide {
    background: rgba(37,99,235,.06); border: 1px solid rgba(37,99,235,.2);
    border-radius: 8px; padding: .65rem .9rem; font-size: .82rem; color: #1e293b;
}
.import-guide code { font-size: .8rem; }
.imp-eg { width: 100%; border-collapse: collapse; font-size: .74rem; margin: .45rem 0 .2rem; }
.imp-eg th, .imp-eg td { border: 1px solid #d7dee8; padding: .22rem .45rem; white-space: nowrap; }
.imp-eg th { background: #eef2f7; font-weight: 700; color: var(--navy); font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; }
.imp-eg td.blank { background: #f8fafc; }
.imp-eg tr.eg-title td { background: #eef6ff; font-weight: 700; color: #1d4ed8; }
.imp-eg tr.eg-bad td { background: #fff5f5; }
.imp-eg .tag { font-size: .66rem; font-weight: 700; padding: .04rem .3rem; border-radius: 20px; white-space: nowrap; }
.imp-eg .tag.ok  { background: #dcfce7; color: #15803d; }
.imp-eg .tag.no  { background: #fee2e2; color: #b91c1c; }
.imp-eg td.note  { white-space: normal; border: 0; background: none; font-size: .7rem; color: var(--muted); padding-left: .5rem; }

/* ── Contract Agreement printout ──────────────────────────────────────
   A recreation of the BAC's actual printed contract. Everything here is
   measured off the scanned original rather than invented, so the generated
   copy sits alongside the paper ones without looking like a different form.
   Rasterised by html2canvas (see js/print-pdf.js), so plain box offsets are
   used throughout — transforms do not rasterise identically everywhere. */
.cp-preview-body { background: #e2e8f0; padding: 24px; min-height: 60vh; }
/* Off-screen but still laid out. NOT display:none — html2canvas needs a real
   render box, and scrollHeight/getBoundingClientRect are meaningless without
   one. */
.cp-offscreen-source { position: absolute; top: 0; left: 0; height: 0; overflow: hidden; }
.pdf-preview-frame { width: 100%; height: 72vh; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }

.cp-page {
    width: 794px; background: #fff; color: #000;
    font-family: 'Times New Roman', Times, serif; font-size: 12.5px; line-height: 1.45;
}
/* No border here — the source is one continuous flow sliced into 20+ PDF
   pages (see js/print-pdf.js), so a border baked into this HTML only ever
   drew as open-ended vertical lines running down every page with no top/bottom
   rule closing it off. The blue double rule the original's pre-bordered stock
   has is drawn instead as real PDF furniture, once per page, in
   drawContractFurniture() below — that's the only place a "per page" border
   can actually be closed on each sheet. */
.cp-frame { padding: 2px; margin: 10px; }
.cp-frame-inner { padding: 16px 22px 18px; }

.cp-letterhead { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
.cp-letterhead td { vertical-align: middle; text-align: center; }
.cp-seal { width: 86px; }
.cp-seal img { width: 78px; height: 78px; object-fit: contain; }
.cp-lh-text { font-size: 11px; line-height: 1.35; color: #1f3f8f; }
.cp-lh-text .cp-lh-city { font-size: 16px; font-weight: 700; color: #1f3f8f; }
.cp-lh-text .cp-lh-rep { font-style: italic; }
.cp-rule { border: none; border-top: 2px solid #1f3f8f; margin: 5px 0 10px; }

.cp-contract-no { text-align: right; font-weight: 700; margin: 6px 0 14px; }
.cp-contract-no .cp-val { border-bottom: 1px solid #000; padding: 0 26px; display: inline-block; min-width: 120px; }

/* Boxed title block. */
.cp-title-box { border: 1px solid #1f3f8f; padding: 12px 16px; text-align: center; margin-bottom: 18px; }
.cp-title-kicker { font-weight: 700; letter-spacing: .06em; margin-bottom: 8px; }
.cp-title-name { font-size: 15px; font-weight: 700; color: #1f3f8f; line-height: 1.35; }

.cp-h { text-align: center; font-weight: 700; margin: 16px 0 12px; letter-spacing: .02em; }
.cp-p { margin: 0 0 10px; text-align: justify; }
.cp-blank { border-bottom: 1px solid #000; display: inline-block; min-width: 60px; }
.cp-center { text-align: center; margin: 6px 0; }
/* The numbered clauses and their sub-lists. Padding rather than list markers,
   so the numbering matches the original exactly (1., 2., a., i., ii., …). */
.cp-list { margin: 0 0 10px; padding-left: 26px; }
.cp-list > div { margin-bottom: 6px; text-align: justify; }
.cp-sublist { padding-left: 26px; margin-top: 6px; }
.cp-sublist > div { margin-bottom: 3px; }
.cp-marker { display: inline-block; width: 22px; margin-left: -22px; }

/* Item grid — same two-shape rule the app enforces, printed the way the
   original prints it. */
.cp-items { width: 100%; border-collapse: collapse; margin: 12px 0 0; }
.cp-items th, .cp-items td { border: 1px solid #000; padding: 4px 6px; font-size: 11.5px; }
.cp-items th { background: #eaf0fb; text-align: center; font-weight: 700; color: #1f3f8f; }
.cp-items td { vertical-align: top; }
.cp-items td.num { text-align: right; }
.cp-items td.center { text-align: center; }
/* pre-wrap: a description now routinely holds a whole specification block on
   one row, and its line breaks are the buyer's own formatting. */
.cp-items td.desc { white-space: pre-wrap; }
.cp-items tr.cp-priced td.desc { font-weight: 700; }
.cp-nothing { text-align: center; font-style: italic; font-weight: 700; background: #fdf3d3; }
.cp-grand td { background: #fdf3d3; font-weight: 700; }
.cp-grand .cp-grand-label { text-align: center; }
.cp-delivery td { font-weight: 700; }
.cp-penalty { font-size: 11px; margin: 8px 0 14px; text-align: justify; }

/* Signature and notary blocks. */
.cp-sign { width: 100%; border-collapse: collapse; margin-top: 26px; }
.cp-sign td { width: 50%; vertical-align: top; padding: 0 10px 26px; }
.cp-sign-role { margin-bottom: 46px; }
.cp-sign-name { font-weight: 700; }
.cp-sign-title { font-style: italic; }
.cp-sign-date { margin-top: 4px; }
.cp-ack-line { margin: 4px 0; }
.cp-notary-grid { margin-top: 26px; line-height: 1.9; }

/* ── Notice of Award / Notice to Proceed ──────────────────────────────
   Both are single-page letters on the BAC's letterhead — two seals, an email
   line, and no frame. Distinct enough from the contract to keep their own
   block rather than being bent out of .cp-*. */
/* Spacing here is deliberately tight. Both letters are one page on the paper
   original, and the Notice of Award is the fuller of the two — letterhead,
   reference block, four paragraphs, the bond table, the expiry note and two
   signature blocks. Every margin below is the smallest that still reads as a
   formal letter rather than a cramped one. */
.cl-page {
    width: 794px; background: #fff; color: #000; padding: 18px 46px 20px;
    font-family: Arial, Helvetica, sans-serif; font-size: 12.5px; line-height: 1.42;
}
.cl-letterhead { width: 100%; border-collapse: collapse; }
.cl-letterhead td { vertical-align: middle; text-align: center; }
.cl-seal { width: 88px; }
.cl-seal img { width: 80px; height: 80px; object-fit: contain; }
.cl-lh-text { font-size: 11px; line-height: 1.35; color: #1f3f8f; }
.cl-lh-rep { font-style: italic; font-family: 'Times New Roman', Times, serif; font-size: 13px; }
.cl-lh-city { font-size: 17px; font-weight: 700; }
.cl-lh-bac { font-size: 18px; font-weight: 700; font-style: italic; font-family: 'Times New Roman', Times, serif; }
.cl-lh-mail { color: #1d4ed8; }
/* The standard-form number sits above the rule on the right, in grey — it is
   the form's identity, not part of the letter. */
.cl-sf { text-align: right; font-size: 8.5px; color: #333; line-height: 1.3; margin-top: -14px; }
.cl-rule { border: none; border-top: 3px solid #1f3f8f; margin: 4px 0 12px; }

/* The three-line reference block under the rule: italic labels, bold values. */
.cl-ref { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
.cl-ref td { vertical-align: top; padding: 1px 0; }
.cl-ref .cl-ref-label { font-style: italic; font-size: 11px; width: 168px; white-space: nowrap; }
.cl-ref .cl-ref-value { font-weight: 700; }

.cl-title { text-align: center; font-weight: 700; letter-spacing: .04em; margin: 16px 0 14px; }
.cl-date { border-bottom: 1px solid #000; display: inline-block; min-width: 210px; padding: 0 4px 2px; margin-bottom: 16px; }
.cl-addr { margin-bottom: 14px; line-height: 1.4; }
.cl-addr .cl-addr-name { font-weight: 700; }
.cl-addr .cl-addr-company { font-weight: 700; }
.cl-p { margin: 0 0 10px; text-align: justify; }
.cl-blank { border-bottom: 1px solid #000; display: inline-block; min-width: 60px; }

/* Performance-security table on the Notice of Award. */
.cl-bond { border-collapse: collapse; margin: 12px auto; width: 74%; }
.cl-bond td { border: 1px solid #000; padding: 5px 9px; font-size: 11px; vertical-align: middle; }
.cl-bond td.cl-bond-amt { text-align: center; font-weight: 700; white-space: nowrap; width: 40%; }
.cl-note { text-align: center; font-weight: 700; color: #c00; font-size: 11px; margin: 8px 0 14px; }

.cl-sign-block { margin-top: 16px; }
.cl-sign-name { font-weight: 700; margin-top: 34px; }
.cl-ack { margin-top: 26px; line-height: 2.1; }
.cl-ack .cl-sub { font-size: 10.5px; font-style: italic; }
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
                <div class="page-title">Competitive Bidding</div>
                <div class="page-subtitle">Notice of Award, Contract Agreement and Notice to Proceed &mdash; one record, three documents.</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="d-flex gap-2 no-print">
                <button class="btn btn-primary-app btn-sm" id="btnAddBid"><i class="fas fa-plus me-1"></i> New Bid Document</button>
            </div>
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="panel mb-3 no-print">
            <div class="panel-body">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" id="searchText" class="form-control"
                               placeholder="Bid no., contract no., name, supplier"
                               onkeydown="if(event.key==='Enter')loadBids()">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">From</label>
                        <input type="date" id="filterFrom" class="form-control">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">To</label>
                        <input type="date" id="filterTo" class="form-control">
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">Status</label>
                        <!-- Every stage is derived from the tracking dates on
                             Additional Details — there is no status column on
                             the table, so a set cannot be marked one thing
                             while its dates say another. -->
                        <select id="filterStatus" class="form-select" onchange="loadBids()">
                            <option value="">All</option>
                            <option value="draft">Draft</option>
                            <option value="awarded">Awarded</option>
                            <option value="bond">Submission of Performance Bond</option>
                            <option value="signing">For Contract Signing</option>
                            <option value="signed">Signed</option>
                            <option value="ntp">Notice to Proceed</option>
                            <option value="cgso">Forwarded to CGSO</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3 d-flex gap-2">
                        <button class="btn btn-primary-app w-100" onclick="loadBids()"><i class="fas fa-search me-1"></i> Filter</button>
                        <button class="btn btn-outline-secondary w-100" onclick="resetFilters()"><i class="fas fa-eraser me-1"></i> Clear</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="panel-title"><i class="fas fa-gavel me-2 text-primary"></i>Bid Documents</span>
                <div class="po-count-chips no-print">
                    <button type="button" class="po-chip" data-status="" onclick="setStatusFilter('')">
                        Total <span class="po-chip-num" id="countTotal">&mdash;</span>
                    </button>
                    <!-- One chip per stage, rendered from CB_STAGES so the
                         chips cannot drift out of step with the badges. -->
                    <span id="cbStageChips"></span>
                </div>
            </div>
            <div class="panel-body">
                <div id="cbListLoading" class="text-center text-muted py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="small mt-2">Loading bid documents&hellip;</div>
                </div>
                <table id="cbTable" class="app-table table table-hover table-bordered d-none" style="width:100%">
                    <thead>
                        <tr>
                            <th>Bid No.</th>
                            <th>Contract No.</th>
                            <th>Name of the Contract</th>
                            <th>Location</th>
                            <th>Supplier</th>
                            <th>PR Amount</th>
                            <th>Contract Amount</th>
                            <th>Savings</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

<!-- Bid document modal. Deliberately the same shape as the Create Purchase
     Order modal: branded header with an icon badge, a compact icon-prefixed
     field grid on the left, the item grid on the right, and a status/actions
     footer. Same software, same vocabulary. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="cbModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-cb-wide modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header po-modal-header-v2">
        <div class="po-header-icon"><i class="fas fa-gavel"></i></div>
        <div class="po-header-text">
            <h5 class="modal-title" id="cbModalTitle">Create Bid Document</h5>
            <small id="cbModalSubtitle" class="cb-modal-subtitle">Fill in the details below to create a new Bid Document.</small>
        </div>
        <button type="button" class="po-header-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body">
        <form id="cbForm" onsubmit="return false;">
          <input type="hidden" id="cbId">

          <div class="row g-4">
            <!-- Left column — the form. Its own scroll region, so a document
                 with a lot of filled-in fields scrolls internally instead of
                 growing the whole modal and pushing Save off the bottom. -->
            <div class="col-lg-5">
              <div class="cb-form-scroll">

                <div class="po-card po-card-compact">
                  <div class="po-card-title"><i class="fas fa-file-signature"></i> Contract Identity</div>
                  <div class="row g-2 tight-field-row">
                    <div class="col-md-6 po-field-group">
                      <label class="req">Bid Number</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-hashtag"></i></span><input type="text" id="bid_number" class="form-control" placeholder="2026-055" autocomplete="off"></div>
                      <div class="po-field-hint">BAC's own numbering, from their paperwork &mdash; typed in, not generated here.</div>
                    </div>
                    <div class="col-md-6 po-field-group">
                      <label>Contract Number</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-file-contract"></i></span><input type="text" id="contract_number" class="form-control" placeholder="2026-130" autocomplete="off"></div>
                    </div>
                    <div class="col-12 po-field-group">
                      <label class="req">Name of the Contract</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-file-lines"></i></span><textarea id="contract_name" class="form-control cb-autogrow" rows="1" placeholder="PURCHASE OF 1 UNIT WHEEL LOADER"></textarea></div>
                    </div>
                    <div class="col-12 po-field-group">
                      <label>Location of the Contract</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-location-dot"></i></span><textarea id="contract_location" class="form-control cb-autogrow" rows="1" placeholder="CDRRMO, MALAYBALAY CITY"></textarea></div>
                    </div>
                  </div>
                </div>

                <div class="po-card po-card-compact">
                  <div class="po-card-title"><i class="fas fa-building"></i> Supplier / Winning Bidder</div>
                  <div class="row g-2 tight-field-row">
                    <div class="col-12 po-field-group">
                      <label>Supplier Name</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-users"></i></span><textarea id="supplier_name" class="form-control cb-autogrow" rows="1" placeholder="MONARK EQUIPMENT CORPORATION"></textarea></div>
                    </div>
                    <div class="col-12 po-field-group">
                      <label>Address</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-location-dot"></i></span><textarea id="supplier_address" class="form-control cb-autogrow" rows="1" placeholder="13 Economia St. Bagumbayan, Quezon City"></textarea></div>
                    </div>
                    <div class="col-12 po-field-group">
                      <label>Second Address Line</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-map-pin"></i></span><input type="text" id="supplier_address2" class="form-control" placeholder="w/ Branch office at El Salvador, Misamis Oriental"></div>
                      <div class="po-field-hint">Printed under the address on the Notice of Award and Notice to Proceed. Leave blank if there is none.</div>
                    </div>
                    <div class="col-12 po-field-group">
                      <label>Organized Under the Laws Of</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-scale-balanced"></i></span><input type="text" id="supplier_org_place" class="form-control" placeholder="Quezon City"></div>
                      <div class="po-field-hint">Fills the &ldquo;duly organized and existing under the laws of ___&rdquo; clause in the Contract.</div>
                    </div>
                    <div class="col-md-7 po-field-group">
                      <label>Representative</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user-tie"></i></span><input type="text" id="representative_name" class="form-control" placeholder="REYNARD MORENO"></div>
                    </div>
                    <div class="col-md-5 po-field-group">
                      <label>Position</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-id-badge"></i></span><input type="text" id="representative_title" class="form-control" placeholder="Account Manager"></div>
                    </div>
                  </div>
                </div>

                <div class="po-card po-card-compact">
                  <div class="po-card-title"><i class="fas fa-peso-sign"></i> Amounts &amp; Delivery</div>
                  <div class="row g-2 tight-field-row">
                    <!-- The Purchase Request side of the pair. Kept next to the
                         contract amount rather than filed under Dates, because
                         the two figures only mean anything read together: the
                         difference between them is what the bidding saved. -->
                    <div class="col-md-6 po-field-group">
                      <label>PR Amount <span class="cb-pct-tag">approved budget</span></label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-clipboard-list"></i></span><input type="text" id="pr_amount" class="form-control" placeholder="12,000,000.00" inputmode="decimal"></div>
                      <div class="po-field-hint">What the Purchase Request was budgeted at &mdash; the approved budget the bidding was run against.</div>
                    </div>
                    <div class="col-md-6 po-field-group">
                      <label>PR Date</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar-day"></i></span><input type="date" id="date_pr" class="form-control"></div>
                      <div class="po-field-hint">When the request was raised &mdash; the start of the clock every lead-time figure is measured from.</div>
                    </div>
                    <div class="col-12 po-field-group">
                      <label>Contract Amount</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-peso-sign"></i></span><input type="text" id="contract_amount" class="form-control" placeholder="10,650,000.00" inputmode="decimal"></div>
                      <div class="po-field-hint">
                        The awarded total. Stored on the record rather than summed from the items, so editing an item
                        can't silently restate an executed contract.
                        <a href="#" onclick="useItemsTotal();return false;">Use the items total instead</a>.
                      </div>
                    </div>
                    <!-- Stated, not left to be worked out from the two numbers
                         above it. Blank until both are filled in: a saving
                         computed against a PR amount nobody entered would read
                         as a 100% saving on every draft in the system. -->
                    <div class="col-12"><div class="cb-savings-band idle" id="cbSavings">
                        <span class="lab">Savings</span><span class="val">&mdash;</span>
                        <span class="sub">Fill in both the PR amount and the contract amount.</span>
                    </div></div>
                    <div class="col-md-6 po-field-group">
                      <label>Cash Bond <span class="cb-pct-tag">5% of contract</span></label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-money-bill-wave"></i></span><input type="text" id="cash_bond_amount" class="form-control" placeholder="532,500.00" inputmode="decimal"></div>
                      <div class="po-field-hint" id="cashBondPct">&nbsp;</div>
                    </div>
                    <div class="col-md-6 po-field-group">
                      <label>Surety Bond <span class="cb-pct-tag">30% of contract</span></label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-shield-halved"></i></span><input type="text" id="surety_bond_amount" class="form-control" placeholder="3,195,000.00" inputmode="decimal"></div>
                      <div class="po-field-hint" id="suretyBondPct">&nbsp;</div>
                    </div>
                    <div class="col-12">
                      <div class="po-field-hint mb-2">
                        Performance security under the 2016 IRR of RA 9184, &sect;39.2: a <strong>Cash Bond</strong>
                        (cashier's or manager's check, bank draft/guarantee, or irrevocable letter of credit) is
                        <strong>5%</strong> of the contract price, and a <strong>Surety Bond</strong> callable on demand
                        is <strong>30%</strong>. The supplier posts one or the other, not both &mdash; the Notice of
                        Award prints the pair so they can choose.
                        <a href="#" onclick="computeBonds();return false;">Work both out from the contract amount</a>.
                        The readout under each field shows what the figure you typed actually comes to, so a bond set at
                        a different rate is visible rather than silently wrong.
                      </div>
                    </div>
                    <div class="col-md-7 po-field-group">
                      <label>Place of Delivery</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-truck"></i></span><input type="text" id="place_of_delivery" class="form-control" placeholder="NEW CITY HALL, BARANGAY CASISANG"></div>
                    </div>
                    <div class="col-md-5 po-field-group">
                      <label>Delivery Term</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-clock"></i></span><input type="text" id="delivery_term" class="form-control" placeholder="30 C.D."></div>
                    </div>
                  </div>
                </div>

                <div class="po-card po-card-compact">
                  <div class="po-card-title"><i class="fas fa-calendar-days"></i> Dates</div>
                  <div class="row g-2 tight-field-row">
                    <div class="col-md-6 po-field-group">
                      <label>Bid Date</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar-check"></i></span><input type="date" id="bid_date" class="form-control"></div>
                      <div class="po-field-hint">The bid being accepted, quoted on the Notice of Award.</div>
                    </div>
                    <div class="col-md-6 po-field-group">
                      <label>Notice of Award Date</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-award"></i></span><input type="date" id="noa_date" class="form-control"></div>
                      <div class="po-field-hint">The date printed on the award. When the supplier <em>received</em> it is under Additional Details.</div>
                    </div>
                  </div>
                </div>

                <div class="po-card po-card-compact">
                  <div class="po-card-title"><i class="fas fa-pen-nib"></i> Signatories</div>
                  <div class="row g-2 tight-field-row">
                    <div class="col-md-7 po-field-group">
                      <label>Head of the Procuring Entity</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user-shield"></i></span><input type="text" id="signatory_name" class="form-control" list="dlSignatories" autocomplete="off" placeholder="JAY WARREN R. PABILLARAN" oninput="fillSignatoryTitle('signatory_name','signatory_title')"></div>
                    </div>
                    <div class="col-md-5 po-field-group">
                      <label>Title</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-id-badge"></i></span><input type="text" id="signatory_title" class="form-control" placeholder="City Mayor"></div>
                    </div>
                    <div class="col-md-7 po-field-group">
                      <label>Witness &mdash; Procuring Entity</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user-check"></i></span><input type="text" id="witness_entity_name" class="form-control" list="dlSignatories" autocomplete="off" placeholder="BELLA STEPHANIE B. SAAVEDRA" oninput="fillSignatoryTitle('witness_entity_name','witness_entity_title')"></div>
                    </div>
                    <div class="col-md-5 po-field-group">
                      <label>Title</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-id-badge"></i></span><input type="text" id="witness_entity_title" class="form-control" placeholder="City Treasurer"></div>
                    </div>
                    <div class="col-12 po-field-group">
                      <label>Witness &mdash; Supplier</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user-pen"></i></span><input type="text" id="witness_supplier_name" class="form-control"></div>
                      <div class="po-field-hint">Often signed on the day and left blank here &mdash; the printout rules a line for it either way.</div>
                    </div>
                  </div>
                </div>

                <div class="po-card po-card-compact">
                  <div class="po-card-title"><i class="fas fa-stamp"></i> Notary (Acknowledgment page)</div>
                  <div class="po-field-hint mb-2">
                    Every one of these is written by hand on the paper copy. Anything left blank prints as the ruled line
                    the notary fills in, so it is safe to save this section empty.
                  </div>
                  <div class="row g-2 tight-field-row">
                    <div class="col-md-7 po-field-group">
                      <label>Notary Public</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user-tie"></i></span><input type="text" id="notary_name" class="form-control" placeholder="ATTY. JAY S. ALBARECE"></div>
                    </div>
                    <div class="col-md-5 po-field-group">
                      <label>Place</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-location-dot"></i></span><input type="text" id="notary_place" class="form-control" placeholder="MANOLO FORTICH, BUKIDNON"></div>
                    </div>
                    <div class="col-md-3 po-field-group">
                      <label>Doc. No.</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-hashtag"></i></span><input type="text" id="notary_doc_no" class="form-control"></div>
                    </div>
                    <div class="col-md-3 po-field-group">
                      <label>Page No.</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-hashtag"></i></span><input type="text" id="notary_page_no" class="form-control"></div>
                    </div>
                    <div class="col-md-3 po-field-group">
                      <label>Book No.</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-hashtag"></i></span><input type="text" id="notary_book_no" class="form-control"></div>
                    </div>
                    <div class="col-md-3 po-field-group">
                      <label>Series of</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar"></i></span><input type="text" id="notary_series" class="form-control"></div>
                    </div>
                  </div>
                </div>

                <div class="po-card po-card-compact">
                  <div class="po-card-title"><i class="fas fa-list-check"></i> Additional Details</div>
                  <div class="po-field-hint mb-2">
                    Filled in over the weeks the paperwork moves, so each one saves on its own the moment you change it
                    &mdash; independently of this form's Save button, and still editable once the set is locked.
                  </div>
                  <div class="row g-2 tight-field-row">
                    <div class="col-md-6 po-field-group">
                      <label>Date Received Notice of Award</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-inbox"></i></span><input type="date" id="date_received_noa" class="form-control cb-quick" data-quick="date_received_noa"></div>
                      <div class="po-field-hint">Starts the ten-calendar-day performance security clock.</div>
                    </div>
                    <div class="col-md-6 po-field-group">
                      <label>Date Performance Bond</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-shield-halved"></i></span><input type="date" id="date_performance_bond" class="form-control cb-quick" data-quick="date_performance_bond"></div>
                    </div>
                    <div class="col-md-6 po-field-group">
                      <label>Date of Contract</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-file-signature"></i></span><input type="date" id="contract_date" class="form-control cb-quick" data-quick="contract_date"></div>
                      <div class="po-field-hint">Blank prints as the ruled line the parties fill in by hand.</div>
                    </div>
                    <div class="col-md-6 po-field-group">
                      <label>Date Notice to Proceed</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-flag-checkered"></i></span><input type="date" id="ntp_date" class="form-control cb-quick" data-quick="ntp_date"></div>
                    </div>
                    <div class="col-md-6 po-field-group">
                      <label>Document Tracking No.</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-barcode"></i></span><input type="text" id="barcode_number" class="form-control cb-quick" data-quick="barcode_number" placeholder="From the routing slip"></div>
                    </div>
                    <div class="col-md-6 po-field-group">
                      <label>Remarks</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-comment-dots"></i></span><input type="text" id="remarks" class="form-control cb-quick" data-quick="remarks" list="dlRemarks" autocomplete="off" placeholder="e.g. Forwarded to CGSO"></div>
                      <div class="po-field-hint">Pick a common wording or type your own. Manage the list in Master Data &rarr; Remarks.</div>
                    </div>
                  </div>
                  <div class="po-field-hint mt-1" id="cbQuickStatus">&nbsp;</div>
                </div>

              </div>
            </div>

            <!-- Right column — the item grid. -->
            <div class="col-lg-7">
              <div class="po-card h-100 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                    <div class="po-card-title mb-0"><i class="fas fa-boxes-stacked"></i> Item Details</div>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill cb-edit-only" onclick="addItemRow({}, null, true)"><i class="fas fa-plus me-1"></i> Add Item</button>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3 cb-edit-only">
                    <input type="file" id="itemImportInput" accept=".xlsx,.csv" class="d-none">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="document.getElementById('itemImportInput').click()"><i class="fas fa-file-import me-1"></i> Import Items</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="toggleItemGuide()" title="What counts as a valid row, and what does an import file need?"><i class="fas fa-circle-question"></i></button>
                </div>

                <div id="cbItemGuide" class="import-guide d-none mb-3">
                  <div class="fw-semibold mb-1"><i class="fas fa-circle-info me-1"></i>Rows, and the import file format</div>
                  The first row of an import file must be column headers. <strong>Description</strong> is the only one
                  you have to include &mdash; a file with just that single column works fine, which is what a scope of
                  work pasted out of a bid document looks like. Add any of
                  <code>QTY, Unit, Unit Cost, Amount</code> as well, in any order.
                  <div class="mt-2 mb-1"><strong>Keep specifications on one row, not one row each.</strong></div>
                  Description is a multi-line field: press Enter inside it and the whole specification block 
                  lives on the priced row it belongs to. One item is then one row in the database instead of forty,
                  and it prints the same either way. In a CSV, wrap the cell in quotes and put real line breaks inside it.
                  <div class="mt-2 mb-1"><strong>Every row must be one of two shapes:</strong></div>
                  <table class="imp-eg">
                    <thead><tr>
                      <th>QTY</th><th>Unit</th><th>Description</th><th>Unit Cost</th><th>Amount</th><th></th>
                    </tr></thead>
                    <tbody>
                      <tr>
                        <td>1</td><td>Unit</td><td>WHEEL LOADER<br>Brand/ Model: Caterpillar 950GC<br>Engine: Turbocharged Diesel<br>&hellip;</td><td>10,650,000.00</td><td>10,650,000.00</td>
                        <td class="note"><span class="tag ok">PRICED</span> it has an Amount &mdash; specs on the same row</td>
                      </tr>
                      <tr class="eg-title">
                        <td class="blank"></td><td class="blank"></td>
                        <td>SCOPE OF WORK:<br>Mobilization<br>Site clearing</td><td class="blank"></td><td class="blank"></td>
                        <td class="note"><span class="tag ok">DESCRIPTION</span> a heading with no price &mdash; every other column left blank</td>
                      </tr>
                      <tr>
                        <td class="blank"></td><td>lot</td><td>Mobilization and accommodation</td>
                        <td class="blank"></td><td>250,000.00</td>
                        <td class="note"><span class="tag ok">PRICED</span> lump sum &mdash; an Amount alone is fine</td>
                      </tr>
                      <tr class="eg-bad">
                        <td>5</td><td>pc</td><td>Brake cleaner</td><td class="blank"></td><td class="blank"></td>
                        <td class="note"><span class="tag no">REJECTED</span> priced like an item but no Amount</td>
                      </tr>
                    </tbody>
                  </table>
                  On the sample contract &ldquo;WHEEL LOADER&rdquo; is the one priced row and the thirty-odd
                  specification lines beneath it are description-only &mdash; they print as text across the grid with the
                  numeric columns empty. <strong>Amount</strong> is worked out from QTY &times; Unit Cost when you leave
                  it out; enter it directly for a lump sum with no quantity behind it.
                  Accepted file types: .xlsx, .csv.
                  <a href="competitive_bidding_item_import_template.csv" download>Download a template</a> &mdash; it
                  already contains the priced line and specification rows shown above.
                </div>

                <div id="cbItemsEmpty" class="po-items-empty">
                    <div class="po-items-empty-icon"><i class="fas fa-bag-shopping"></i></div>
                    <div class="po-items-empty-title">No items added yet.</div>
                    <div class="po-items-empty-sub">Click "Add Item" to get started.</div>
                </div>

                <div id="cbItemsWrap" class="table-responsive items-scroll-wrap d-none">
                  <table class="app-table item-grid table table-bordered mb-0" id="cbItemsTable">
                    <thead>
                      <tr>
                        <!-- QTY was 8% and Amount 13%, which on a real bid gave
                             a 30px box for a 52px number: "6.000" read as "6.0"
                             and "32200.000" as "32200.00(". The three points come
                             off Description, which wraps and can afford them. -->
                        <th style="width:4%">#</th>
                        <th style="width:10%">QTY</th>
                        <th style="width:10%">Unit</th>
                        <th style="width:40%">Description</th>
                        <th style="width:13%">Unit Cost</th>
                        <th style="width:15%">Amount</th>
                        <th style="width:8%">Action</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>

                <button type="button" class="po-add-row-link cb-edit-only" onclick="addItemRow({}, null, true)"><i class="fas fa-plus me-1"></i> Add another row</button>

                <div class="po-items-footer-bar">
                    <div class="po-items-count">Total Items: <strong id="cbRowCount">0</strong></div>
                    <div><span class="text-muted small me-1">Grand Total:</span><span id="cbSubtotal">&#8369;0.00</span></div>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer d-flex align-items-center">
        <div class="me-auto">Status: <span id="cbModalStatusBadge" class="badge-status st-draft">Draft</span></div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancel</button>
        <button type="button" class="btn btn-primary-app" id="cbSaveButton" onclick="saveBid()">
          <i class="fas fa-floppy-disk me-1"></i> Save Bid Document
        </button>
      </div>
    </div>
  </div>
</div>


<!-- Contract Agreement print preview. Same two-part shape as the Purchase
     Order's: an off-screen source element that html2canvas rasterises, and an
     iframe showing the real PDF that comes out of it. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="cbPrintModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header po-modal-header-v2">
        <div class="po-header-icon"><i class="fas fa-print"></i></div>
        <div class="po-header-text">
            <h5 class="modal-title">Contract Agreement</h5>
            <small id="cbPrintSubtitle">&nbsp;</small>
        </div>
        <button type="button" class="po-header-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body cp-preview-body">
        <div id="cbDocTabs" class="d-flex gap-2 justify-content-center mb-3 flex-wrap"></div>
        <div id="cbPrintSource" class="cp-offscreen-source"></div>
        <div id="cbPdfLoading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="small mt-2">Generating PDF preview&hellip;</div>
        </div>
        <iframe id="cbPdfFrame" class="pdf-preview-frame d-none" title="Contract Agreement PDF preview"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-secondary" id="cbPdfOpenTab" disabled><i class="fas fa-up-right-from-square me-1"></i> Open in Tab</button>
        <button type="button" class="btn btn-outline-primary" id="cbPdfDownload" disabled><i class="fas fa-download me-1"></i> Download PDF</button>
        <button type="button" class="btn btn-primary-app" id="cbPdfPrint" disabled><i class="fas fa-print me-1"></i> Print</button>
      </div>
    </div>
  </div>
</div>
<datalist id="dlSignatories"></datalist>
<datalist id="dlRemarks"></datalist>

<?php /* Shared Document Tracking trail popup — see dts_trail.php. Included
         at the END of <body>, outside .top-navbar, for the same stacking
         reason as profile_modal.php. */ ?>
<?php include 'dts_trail.php'; ?>

<?php include 'profile_modal.php'; ?>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/jquery.dataTables.min.js"></script>
<script src="vendor/js/dataTables.bootstrap5.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script src="vendor/js/html2canvas.min.js"></script>
<script src="vendor/js/jspdf.umd.min.js"></script>
<script src="<?= asset('js/print-pdf.js') ?>"></script>
<script>
const apiUrl = 'api/competitive_bidding.php';
let cbTable = null;
let firstLoadDone = false;

// ── Small shared helpers ────────────────────────────────────────────────
function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function showToast(msg, type = 'success') {
    const id = 'toast-' + Date.now();
    const bg = type === 'success' ? 'bg-success' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-danger');
    const html = `<div id="${id}" class="toast align-items-center text-white ${bg} border-0" role="alert">
        <div class="d-flex"><div class="toast-body">${escapeHtml(msg)}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
    document.getElementById('toastContainer').insertAdjacentHTML('beforeend', html);
    const el = document.getElementById(id);
    new bootstrap.Toast(el, { delay: 4000 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

function openModal(id) {
    const el = document.getElementById(id);
    (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
}

// Money in, number out. Strips thousands separators and any currency mark
// first, so a pasted "10,650,000.00" does not silently become 10.
function parseMoney(value) {
    if (value === null || value === undefined) return 0;
    const clean = String(value).replace(/[^0-9.\-]/g, '');
    return clean === '' ? 0 : (parseFloat(clean) || 0);
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// What the bidding saved: the approved budget the request was raised for, less
// what the winning bidder was actually awarded.
//
// Returns null — not 0 — unless BOTH figures are on file. The distinction is
// the whole point: most of the live records carry no PR amount yet, and
// treating a missing budget as zero would report every one of them as having
// saved the entire contract price. "Not known" has to stay visibly different
// from "nothing saved".
function savingsOf(prAmount, contractAmount) {
    const pr = parseMoney(prAmount), award = parseMoney(contractAmount);
    if (pr <= 0 || award <= 0) return null;
    return pr - award;
}

// The percentage needs the budget it came off, which is why this takes the PR
// amount as well — ₱40,000 saved reads very differently against ₱50,000 than
// against ₱50,000,000.
function renderSavingsCell(value, prAmount) {
    if (value === null || value === undefined) {
        return '<span class="cb-save none" title="No PR amount on file, so there is nothing to compare the award against">'
             + '<span class="cb-save-val">—</span></span>';
    }
    const pr = parseMoney(prAmount);
    const pct = pr ? (Math.abs(value) / pr * 100).toFixed(1) + '%' : '';
    const over = value < 0;
    return `<span class="cb-save ${over ? 'over' : ''}">
        <span class="cb-save-val">${over ? '+' : ''}${formatMoney(Math.abs(value))}</span>
        <span class="cb-save-pct">${over ? 'over budget' : 'saved'}${pct ? ' · ' + pct : ''}</span></span>`;
}

// The stage ladder, mirroring CB_STAGES in api/competitive_bidding.php. The
// server decides which stage a row is in — this is only how each one is
// labelled and coloured, kept in one place so the badge, the chips and the
// filter cannot drift apart.
const CB_STAGES = [
    ['draft',   'Draft'],
    ['awarded', 'Awarded'],
    ['bond',    'Submission of Performance Bond'],
    ['signing', 'For Contract Signing'],
    ['signed',  'Signed'],
    ['ntp',     'Notice to Proceed'],
    ['cgso',    'Forwarded to CGSO'],
];
const CB_STAGE_LABEL = Object.fromEntries(CB_STAGES);

// Shortened for the chip row only. The badge in the table keeps the full
// wording, because that is the column somebody reads to answer "where is it".
const CB_STAGE_SHORT = {
    draft: 'Draft', awarded: 'Awarded', bond: 'For Bond', signing: 'For Signing',
    signed: 'Signed', ntp: 'NTP', cgso: 'To CGSO',
};

function stageBadge(bid) {
    const key = bid.stage || 'draft';
    return '<span class="badge-status st-' + key + '">'
         + escapeHtml(bid.stage_label || CB_STAGE_LABEL[key] || key) + '</span>';
}

// ── List ────────────────────────────────────────────────────────────────
function initTable() {
    cbTable = $('#cbTable').DataTable({
        columns: [
            // render() only dresses up the 'display' pass, so filtering, sorting
            // and export all still see the bare value.
            { data: 'bid_number', render: (d, type) =>
                type === 'display' && d && d !== '—' ? `<span class="bid-num-pill">${d}</span>` : d },
            { data: 'contract_number', render: (d, type) => {
                if (type !== 'display' || !d) return d;
                return d === '—' ? '<span class="cb-empty">—</span>' : `<span class="contract-num-pill">${d}</span>`;
            } },
            { data: 'contract_name', render: (d, type) =>
                type === 'display' && d && d !== '—' ? `<span class="cb-wrap-cell">${d}</span>` : d },
            { data: 'contract_location', render: (d, type) =>
                type === 'display' && d && d !== '—' ? `<span class="cb-wrap-cell">${d}</span>` : d },
            { data: 'supplier_name', render: (d, type) =>
                type === 'display' && d && d !== '—' ? `<span class="cb-wrap-cell">${d}</span>` : d },
            // Sorts on the number, displays the formatted string — hence the
            // split by `type` rather than formatting in formatRow().
            { data: 'pr_amount_raw', render: (d, type) =>
                type === 'display'
                    ? (d ? `<span class="cb-amount muted">${formatMoney(d)}</span>` : '<span class="cb-empty">—</span>')
                    : (d || 0) },
            { data: 'contract_amount_raw', render: (d, type) =>
                type === 'display'
                    ? (d ? `<span class="cb-amount">${formatMoney(d)}</span>` : '<span class="cb-empty">—</span>')
                    : (d || 0) },
            // Sorts on the peso figure so the biggest saving comes to the top,
            // which is the only sort order anyone wants on this column.
            { data: 'savings_raw', render: (d, type, row) =>
                type === 'display' ? renderSavingsCell(d, row.pr_amount_raw) : (d || 0) },
            { data: 'status' },
            { data: 'actions', orderable: false, searchable: false },
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        createdRow: (row) => { $(row).find('td:eq(8), td:eq(9)').addClass('text-center'); },
    });
}

function formatRow(bid) {
    return {
        id: bid.id,
        bid_number: escapeHtml(bid.bid_number) || '—',
        contract_number: escapeHtml(bid.contract_number) || '—',
        contract_name: escapeHtml(bid.contract_name) || '—',
        contract_location: escapeHtml(bid.contract_location) || '—',
        supplier_name: escapeHtml((bid.supplier_name || '').trim()) || '—',
        pr_amount_raw: parseMoney(bid.pr_amount),
        contract_amount_raw: parseMoney(bid.contract_amount),
        // null, not 0, when either figure is missing. A saving of zero and a
        // saving nobody can compute are different answers, and the cell says
        // so rather than showing ₱0.00 for both.
        savings_raw: savingsOf(bid.pr_amount, bid.contract_amount),
        locked: !!bid.locked,
        status: stageBadge(bid),
        actions: buildActions(bid),
    };
}

function buildActions(bid) {
    const number = escapeHtml(bid.bid_number);
    const locked = !!bid.locked;
    // A locked set is issued paperwork: Edit and Delete are disabled outright
    // rather than left to fail at the server, so the state is visible before
    // anyone types into a form they cannot save.
    const disabled = locked ? 'disabled' : '';
    const lockTitle = locked ? 'Unlock — allow edits again' : 'Lock — the set has been issued';
    return `<div class="table-actions justify-content-center">
        <button class="btn btn-sm btn-outline-info" title="View" onclick="viewBid('${number}')"><i class="fas fa-eye"></i></button>
        <button class="btn btn-sm btn-outline-secondary" title="Print" onclick="openBidPrint('${number}', 'contract')"><i class="fas fa-print"></i></button>
        <button class="btn btn-sm btn-outline-secondary" title="Track in the Document Tracking System" onclick="trackBid('${number}')"><i class="fas fa-route"></i></button>
        <button class="btn btn-sm ${locked ? 'btn-warning' : 'btn-outline-warning'}" title="${lockTitle}" onclick="toggleBidLock('${number}', ${locked ? 'false' : 'true'})"><i class="fas fa-${locked ? 'lock' : 'lock-open'}"></i></button>
        <button class="btn btn-sm btn-outline-primary" title="Edit" onclick="editBid('${number}')" ${disabled}><i class="fas fa-pen-to-square"></i></button>
        <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteBid('${number}')" ${disabled}><i class="fas fa-trash"></i></button>
    </div>`;
}

// One chip per stage. Stages with nothing in them are still drawn, because a
// zero is information — "no set is waiting on a performance bond" is worth
// seeing, and a chip row that changes shape as the work moves is hard to aim at.
function renderStageChips(counts) {
    const active = document.getElementById('filterStatus').value;
    document.getElementById('cbStageChips').innerHTML = CB_STAGES.map(([key]) =>
        `<button type="button" class="po-chip po-chip-${key} ${active === key ? 'chip-on' : ''}"
                 data-status="${key}" title="${escapeHtml(CB_STAGE_LABEL[key])}"
                 onclick="setStatusFilter('${key}')">
            ${escapeHtml(CB_STAGE_SHORT[key])}
            <span class="po-chip-num">${counts[key] === undefined ? '—' : counts[key]}</span>
        </button>`).join('');
}

function renderCounts(counts) {
    if (!counts) return;
    const fmt = n => Number(n || 0).toLocaleString('en-PH');
    document.getElementById('countTotal').textContent = fmt(counts.total);
    renderStageChips(counts);
    const active = document.getElementById('filterStatus').value;
    document.querySelectorAll('.po-chip').forEach(chip => {
        chip.classList.toggle('is-active', chip.dataset.status === active);
    });
}

// Clicking a count chip is just another way to set the Status dropdown — one
// code path drives the filter, so the two controls can't drift apart.
function setStatusFilter(status) {
    document.getElementById('filterStatus').value = status;
    loadBids();
}

function resetFilters() {
    document.getElementById('searchText').value = '';
    document.getElementById('filterStatus').value = '';
    initDateFilters();
    loadBids();
}

function initDateFilters() {
    // +1 day, not today: a document created later today would otherwise fall
    // outside a range ending at midnight this morning.
    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    const monthStart = new Date(); monthStart.setDate(1);
    document.getElementById('filterTo').value   = tomorrow.toISOString().slice(0, 10);
    document.getElementById('filterFrom').value = monthStart.toISOString().slice(0, 10);
}

async function loadBids() {
    const search = encodeURIComponent(document.getElementById('searchText').value.trim());
    const from   = document.getElementById('filterFrom').value;
    const to     = document.getElementById('filterTo').value;
    const status = document.getElementById('filterStatus').value;
    try {
        const res  = await fetch(`${apiUrl}?search=${search}&from=${from}&to=${to}&status=${status}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load bid documents.', 'error');
        renderCounts(data.counts);
        if (!firstLoadDone) {
            document.getElementById('cbListLoading').classList.add('d-none');
            document.getElementById('cbTable').classList.remove('d-none');
            // The table was initialised while hidden, so DataTables could not
            // measure real column widths yet — recompute now that it is visible.
            cbTable.columns.adjust();
            firstLoadDone = true;
        }
        cbTable.clear().rows.add((data.bids || []).map(formatRow)).draw();
    } catch (err) {
        if (!firstLoadDone) {
            document.getElementById('cbListLoading').innerHTML =
                '<div class="text-danger small"><i class="fas fa-triangle-exclamation me-1"></i>Unable to load bid documents. <a href="#" onclick="loadBids();return false;">Retry</a></div>';
        }
        showToast('Unable to load bid documents.', 'error');
        console.error(err);
    }
}

// ── Item grid ───────────────────────────────────────────────────────────
// Same two-shape rule as the purchase order grid: a row is either a priced
// item (it has an Amount) or a description-only line (everything but the
// description blank). Amount — not QTY or Unit Cost — is the test, because a
// lump-sum line legitimately has a total with no quantity behind it.

const CB_FIELDS = [
    'bid_number', 'contract_number', 'contract_name', 'contract_location',
    'supplier_name', 'supplier_address', 'supplier_address2', 'supplier_org_place',
    'representative_name', 'representative_title',
    'pr_amount', 'contract_amount', 'cash_bond_amount', 'surety_bond_amount',
    'place_of_delivery', 'delivery_term',
    'date_pr', 'bid_date', 'noa_date',
    'signatory_name', 'signatory_title',
    'witness_entity_name', 'witness_entity_title', 'witness_supplier_name',
    'notary_name', 'notary_place', 'notary_doc_no', 'notary_page_no', 'notary_book_no', 'notary_series',
];

// Saved one at a time through their own PATCH, not by this form's Save — see
// CB_QUICK_FIELDS in api/competitive_bidding.php for why they are separate.
const CB_QUICK_FIELD_IDS = [
    'date_received_noa', 'date_performance_bond', 'contract_date', 'ntp_date',
    'barcode_number', 'remarks',
];

function itemHasValue(v) { return v !== null && v !== undefined && String(v).trim() !== '' && Number(v) !== 0; }

// Inferred only when the row does not say what it is — an existing row saved
// before the flag existed, or one pasted in from elsewhere.
//
// A description is required for the guess, which is what keeps a brand-new
// empty row from being read as description-only: every column of {} is blank,
// so without this test the starter row came up dashed out and un-typeable.
function looksLikeDescriptionRow(item) {
    return String(item.items_description || '').trim() !== ''
        && !itemHasValue(item.quantity) && !itemHasValue(item.unit_cost)
        && !itemHasValue(item.total_cost) && !String(item.unit || '').trim();
}

function setRowType(row, isDescriptionOnly) {
    row.classList.toggle('item-row-desc', !!isDescriptionOnly);
    row.dataset.descOnly = isDescriptionOnly ? '1' : '0';
    const toggle = row.querySelector('.type-toggle');
    if (toggle) toggle.classList.toggle('active', !!isDescriptionOnly);
    if (isDescriptionOnly) {
        // Clear the numeric side so a row switched to description-only cannot
        // keep a stale cost that the server would then reject.
        ['unit', 'quantity', 'unit_cost', 'total_cost'].forEach(f => {
            const input = row.querySelector(`[data-field="${f}"]`);
            if (input) input.value = '';
        });
    }
    refreshItemsSummary();
}

function toggleRowType(row) {
    setRowType(row, row.dataset.descOnly !== '1');
}

// A stored amount, as a person would write it. The item columns are
// decimal(11,3), so a quantity of six arrives as "6.000" and a cost of ₱102 as
// "102.000" — three zeros of nothing, in the narrowest columns on the form.
// Number() drops them and keeps every real decimal, so 102.5 stays 102.5.
//
// Safe to re-save: these are <input type="number">, so the field posts the same
// number either way, and the column stores it back at its own scale.
function itemNum(v) {
    if (v === null || v === undefined || v === '') return '';
    const n = Number(v);
    return isFinite(n) ? String(n) : String(v);
}

function addItemRow(item = {}, afterRow = null, focus = false, isDescriptionOnly = null) {
    const tbody = document.querySelector('#cbItemsTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td class="item-grid-idx"></td>
        <td><input type="number" min="0" step="any" class="form-control form-control-sm" data-field="quantity" value="${itemHasValue(item.quantity) ? itemNum(item.quantity) : ''}"><span class="item-row-dash">&mdash;</span></td>
        <td><input type="text" class="form-control form-control-sm" data-field="unit" value="${escapeHtml(item.unit)}"><span class="item-row-dash">&mdash;</span></td>
        <td><textarea class="form-control form-control-sm item-desc-input" data-field="items_description" rows="1">${escapeHtml(item.items_description)}</textarea></td>
        <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" data-field="unit_cost" value="${itemHasValue(item.unit_cost) ? itemNum(item.unit_cost) : ''}"><span class="item-row-dash">&mdash;</span></td>
        <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" data-field="total_cost" value="${itemHasValue(item.total_cost) ? itemNum(item.total_cost) : ''}"><span class="item-row-dash">&mdash;</span></td>
        <td class="text-nowrap">
            <div class="d-flex gap-1">
                <button type="button" class="item-grid-action-btn add" title="Insert row below" onclick="addItemRow({}, this.closest('tr'), true)"><i class="fas fa-plus"></i></button>
                <button type="button" class="item-grid-action-btn type-toggle" title="Turn into a description-only line" onclick="toggleRowType(this.closest('tr'))"><i class="fas fa-align-left"></i></button>
                <button type="button" class="item-grid-action-btn remove" title="Remove row" onclick="this.closest('tr').remove(); refreshItemsSummary();"><i class="fas fa-trash"></i></button>
            </div>
        </td>`;

    // Amount follows QTY x Unit Cost, but stays typeable: a lump-sum line has
    // an amount and no quantity, so this fills the field rather than locking
    // it, and only when there is actually something to multiply.
    const qtyInput  = row.querySelector('[data-field="quantity"]');
    const costInput = row.querySelector('[data-field="unit_cost"]');
    const totalInput = row.querySelector('[data-field="total_cost"]');
    const recalc = () => {
        const qty  = parseFloat(qtyInput.value) || 0;
        const cost = parseFloat(costInput.value) || 0;
        if (qty && cost) totalInput.value = (qty * cost).toFixed(2);
        refreshItemsSummary();
    };
    qtyInput.addEventListener('input', recalc);
    costInput.addEventListener('input', recalc);
    totalInput.addEventListener('input', refreshItemsSummary);

    // Description grows with its content instead of clipping a long spec line.
    // Resets to 'auto' first so it shrinks back down when text is deleted.
    const descInput = row.querySelector('.item-desc-input');
    const autoGrow = () => { descInput.style.height = 'auto'; descInput.style.height = descInput.scrollHeight + 'px'; };
    descInput.addEventListener('input', autoGrow);

    if (afterRow && afterRow.parentNode === tbody) afterRow.insertAdjacentElement('afterend', row);
    else tbody.appendChild(row);

    // refreshItemsSummary() runs before autoGrow(), not just after: it is what
    // un-hides #cbItemsWrap, and scrollHeight is unreliable inside a
    // display:none ancestor. A programmatically-filled row never gets a
    // keystroke to self-correct, so it would stay clipped to one line forever.
    setRowType(row, isDescriptionOnly === null ? looksLikeDescriptionRow(item) : isDescriptionOnly);
    refreshItemsSummary();
    autoGrow();
    if (focus) (row.classList.contains('item-row-desc') ? descInput : row.querySelector('input'))?.focus();
    return row;
}

function refreshItemsSummary() {
    const rows = [...document.querySelectorAll('#cbItemsTable tbody tr')];
    document.getElementById('cbItemsWrap').classList.toggle('d-none', rows.length === 0);
    document.getElementById('cbItemsEmpty').classList.toggle('d-none', rows.length > 0);
    let total = 0;
    rows.forEach((row, i) => {
        row.querySelector('.item-grid-idx').textContent = i + 1;
        total += parseMoney(row.querySelector('[data-field="total_cost"]').value);
    });
    document.getElementById('cbSubtotal').textContent = '₱' + formatMoney(total);
    document.getElementById('cbRowCount').textContent = rows.length;
    return total;
}

function collectItems() {
    return [...document.querySelectorAll('#cbItemsTable tbody tr')].map(row => {
        const value = f => (row.querySelector(`[data-field="${f}"]`)?.value ?? '').trim();
        return {
            quantity: value('quantity'),
            unit: value('unit'),
            items_description: value('items_description'),
            unit_cost: value('unit_cost'),
            total_cost: value('total_cost'),
            is_title: row.dataset.descOnly === '1',
        };
    }).filter(item => item.items_description !== '');
}

// Mirrors the server's cbInvalidItemRows() so the same rejection is explained
// here, before a round trip. The server still enforces it — the browser is not
// a place to enforce anything.
function invalidItemRows() {
    const bad = { item: [], desc: [] };
    collectItems().forEach((item, i) => {
        const num = k => parseMoney(item[k]);
        const blank = !item.unit && !num('quantity') && !num('unit_cost') && !num('total_cost');
        if (item.is_title) { if (!blank) bad.desc.push(i + 1); }
        else if (!num('total_cost')) bad.item.push(i + 1);
    });
    return bad;
}

// Row numbers for a rejection message, worded exactly as the server words them
// (see cbRejectInvalidItems) so the same problem never gets two descriptions.
// Capped at six: pasting a bad spreadsheet should give a readable sentence,
// not three hundred row numbers.
function rowList(rows) {
    const shown = rows.slice(0, 6);
    return (rows.length === 1 ? 'Row ' : 'Rows ') + '#' + shown.join(', #')
         + (rows.length > 6 ? ` and ${rows.length - 6} more` : '');
}

// Sends the file to api/competitive_bidding_item_parse.php and appends what
// comes back. The parse is server-side because .xlsx is a zip of XML, and
// because the same shape rules then live in one place rather than being
// reimplemented in the browser and drifting.
async function importItemsIntoGrid(file) {
    const formData = new FormData();
    formData.append('file', file);
    try {
        const res  = await fetch('api/competitive_bidding_item_parse.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to import items.', 'error');
        // A single still-blank starter row is replaced rather than kept
        // alongside the import — otherwise every imported file arrives with an
        // empty row on top that then fails validation on save.
        const tbody = document.querySelector('#cbItemsTable tbody');
        const rows  = [...tbody.querySelectorAll('tr')];
        if (rows.length === 1) {
            const blank = [...rows[0].querySelectorAll('input, textarea')].every(i => i.value.trim() === '');
            if (blank) rows[0].remove();
        }
        // is_title comes from the parser, so a heading that happens to read
        // like an item does not flip type on the way in.
        data.items.forEach(item => addItemRow(item, null, false, !!item.is_title));
        refreshItemsSummary();
        showToast(data.message || 'Items imported.');
    } catch (err) {
        showToast('Unable to import items.', 'error');
        console.error(err);
    }
}

function toggleItemGuide() {



    document.getElementById('cbItemGuide').classList.toggle('d-none');
}

// ── Amount helpers ──────────────────────────────────────────────────────
function useItemsTotal() {
    document.getElementById('contract_amount').value = formatMoney(refreshItemsSummary());
}

// Performance security rates under the 2016 IRR of RA 9184, §39.2. Offered
// rather than enforced: which security a supplier posts, and at what rate, is
// the BAC's call — this form's job is to make the expected figure obvious and
// to say so plainly when the typed one differs.
const CB_BOND_RATES = { cash_bond_amount: 0.05, surety_bond_amount: 0.30 };

function computeBonds() {
    const amount = parseMoney(document.getElementById('contract_amount').value);
    if (!amount) return showToast('Enter the contract amount first.', 'warning');
    Object.keys(CB_BOND_RATES).forEach(id => {
        document.getElementById(id).value = formatMoney(amount * CB_BOND_RATES[id]);
    });
    refreshBondHints();
}

// What the figure actually typed comes to as a percentage of the contract, so
// a bond entered at the wrong rate — or one left behind when the contract
// amount changed — shows up instead of quietly going out on the award.
function refreshBondHints() {
    const amount = parseMoney(document.getElementById('contract_amount').value);
    [['cash_bond_amount', 'cashBondPct'], ['surety_bond_amount', 'suretyBondPct']].forEach(pair => {
        const hint = document.getElementById(pair[1]);
        if (!hint) return;
        const value = parseMoney(document.getElementById(pair[0]).value);
        if (!amount || !value) { hint.innerHTML = '&nbsp;'; hint.className = 'po-field-hint'; return; }
        const pct = (value / amount) * 100;
        const expected = CB_BOND_RATES[pair[0]] * 100;
        // A hair's tolerance before flagging: a bond rounded to the nearest peso
        // lands fractionally either side of the exact rate, and calling that
        // wrong would train people to ignore the hint.
        const off = Math.abs(pct - expected) > 0.005;
        hint.className = 'po-field-hint' + (off ? ' cb-pct-off' : '');
        hint.textContent = '= ' + pct.toFixed(2) + '% of the contract amount'
            + (off ? ' (expected ' + expected + '%)' : '');
    });
}

// The saving, stated in words under the two figures it comes from.
//
// Three states, not two. "Over the approved budget" is a real and legitimate
// outcome — a re-bid at a higher ABC, a contract awarded above the original
// request — and showing it as a negative saving would bury it. It gets its own
// amber treatment so it is visible without being called an error.
function refreshSavingsBand() {
    const band = document.getElementById('cbSavings');
    if (!band) return;
    const pr    = parseMoney(document.getElementById('pr_amount').value);
    const award = parseMoney(document.getElementById('contract_amount').value);

    if (pr <= 0 || award <= 0) {
        band.className = 'cb-savings-band idle';
        band.innerHTML = '<span class="lab">Savings</span><span class="val">&mdash;</span>'
                       + '<span class="sub">Fill in both the PR amount and the contract amount.</span>';
        return;
    }
    const diff = pr - award;
    const pct  = (Math.abs(diff) / pr * 100).toFixed(2) + '%';
    if (diff < 0) {
        band.className = 'cb-savings-band over';
        band.innerHTML = '<span class="lab">Over budget</span>'
                       + '<span class="val">₱' + formatMoney(-diff) + '</span>'
                       + '<span class="sub">' + pct + ' above the PR amount &mdash; awarded ₱'
                       + formatMoney(award) + ' against a budget of ₱' + formatMoney(pr) + '.</span>';
        return;
    }
    band.className = 'cb-savings-band';
    band.innerHTML = '<span class="lab">Savings</span>'
                   + '<span class="val">₱' + formatMoney(diff) + '</span>'
                   + '<span class="sub">' + pct + ' below the PR amount &mdash; awarded ₱'
                   + formatMoney(award) + ' against a budget of ₱' + formatMoney(pr) + '.</span>';
}

// ── Form ────────────────────────────────────────────────────────────────
function autoGrowField(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

// Re-measures every auto-growing field in the modal.
//
// Needed because editBid() fills the form BEFORE the modal is shown, and a
// scrollHeight read inside a display:none element comes back near-zero — so
// every field was left with a wrong inline height baked in, and nothing ever
// corrected it, because a programmatically-filled field never receives the
// keystroke that would. Filling first is deliberate (the modal should not
// animate in half-populated), so the measurement is simply redone afterwards,
// on Bootstrap's shown event — the first moment these elements have a real
// layout to measure.
function regrowModalFields() {
    document.querySelectorAll('#cbModal .cb-autogrow, #cbItemsTable .item-desc-input')
        .forEach(autoGrowField);
}

function clearForm() {
    document.getElementById('cbId').value = '';
    CB_FIELDS.concat(CB_QUICK_FIELD_IDS).forEach(f => { const el = document.getElementById(f); if (el) el.value = ''; });
    document.querySelector('#cbItemsTable tbody').innerHTML = '';
    refreshItemsSummary();
    document.querySelectorAll('.cb-autogrow').forEach(autoGrowField);
    refreshBondHints();
    refreshSavingsBand();
}

function fillForm(bid) {
    document.getElementById('cbId').value = bid.id || '';
    CB_FIELDS.concat(CB_QUICK_FIELD_IDS).forEach(f => {
        const el = document.getElementById(f);
        if (!el) return;
        let value = bid[f] ?? '';
        // A DATE column comes back as YYYY-MM-DD already; anything longer is
        // trimmed so <input type="date"> accepts it.
        if (el.type === 'date') value = String(value).slice(0, 10);
        if (['pr_amount', 'contract_amount', 'cash_bond_amount', 'surety_bond_amount'].includes(f)) {
            value = value === '' || value === null ? '' : formatMoney(value);
        }
        el.value = value;
    });
    document.querySelector('#cbItemsTable tbody').innerHTML = '';
    (bid.items || []).forEach(item => addItemRow(item));
    refreshItemsSummary();
    // Values were set programmatically, so no 'input' event fired — every
    // auto-grow field needs an explicit resize.
    document.querySelectorAll('.cb-autogrow').forEach(autoGrowField);
    refreshBondHints();
    refreshSavingsBand();
}

// Title, subtitle and status badge move together — the header and the footer
// describing different documents would be worse than either being wrong alone.
// A tracking date IS the status here, so saving one can move the document to a
// new stage. Without this the badge in the open modal — and the row behind it —
// would keep showing the stage the set was in before the date was entered.
async function refreshStageAfterQuickSave() {
    if (!cbQuickBidNumber) return;
    try {
        const res = await fetch(`${apiUrl}?bid_number=${encodeURIComponent(cbQuickBidNumber)}`);
        const data = await res.json();
        if (!data.success || !data.bid) return;
        const badge = document.getElementById('cbModalStatusBadge');
        badge.textContent = data.bid.stage_label || 'Draft';
        badge.className = 'badge-status st-' + (data.bid.stage || 'draft');
        loadBids();
    } catch (err) { console.error(err); }
}

function setModalChrome(title, subtitle, statusText, statusClass) {
    document.getElementById('cbModalTitle').textContent = title;
    // Clamped to two lines in CSS. A consolidated bid's contract name runs to
    // several hundred characters and filled the whole header with it, pushing
    // the form down the screen; the full text stays available on hover.
    const sub = document.getElementById('cbModalSubtitle');
    sub.textContent = subtitle;
    sub.title = subtitle;
    const badge = document.getElementById('cbModalStatusBadge');
    badge.textContent = statusText;
    badge.className = 'badge-status ' + statusClass;
}

function openNewBid() {
    clearForm();
    setFormReadOnly(false);
    // Nothing to save against until the document exists.
    cbQuickBidNumber = null;
    document.querySelectorAll('.cb-quick').forEach(el => { el.disabled = true; });
    document.getElementById('cbQuickStatus').textContent = 'Available once the document has been saved.';
    setModalChrome('Create Bid Document',
        'Fill in the details below to create a new Bid Document.', 'Draft', 'st-draft');
    openModal('cbModal');
    // One empty row to start, so the grid is not a dead end.
    addItemRow({}, null, false);
}

async function editBid(bidNumber, readOnly = false) {
    try {
        const res  = await fetch(`${apiUrl}?bid_number=${encodeURIComponent(bidNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load the bid document.', 'error');
        clearForm();
        fillForm(data.bid);
        // A locked document opens read-only whatever was clicked: the row's
        // Edit button is already disabled, but a deep link or a stale list
        // would otherwise walk straight into a form that cannot be saved.
        const locked = !!data.bid.locked;
        const viewing = readOnly || locked;
        setModalChrome(viewing ? 'Bid Document' : 'Edit Bid Document',
            'Bid No. ' + bidNumber + (data.bid.contract_name ? ' — ' + data.bid.contract_name : '')
                + (locked ? '  •  Locked' : ''),
            data.bid.stage_label || 'Draft', 'st-' + (data.bid.stage || 'draft'));
        setFormReadOnly(viewing);
        // Quick fields stay live even on a locked or view-only document.
        cbQuickBidNumber = bidNumber;
        document.querySelectorAll('.cb-quick').forEach(el => { el.disabled = false; });
        openModal('cbModal');
    } catch (err) {
        showToast('Unable to load the bid document.', 'error');
        console.error(err);
    }
}

async function saveBid() {
    const id = document.getElementById('cbId').value;
    const payload = { id: id || undefined, items: collectItems() };
    CB_FIELDS.forEach(f => { payload[f] = (document.getElementById(f)?.value ?? '').trim(); });

    if (!payload.bid_number)    return showToast('Bid Number is required.', 'warning');
    if (!payload.contract_name) return showToast('Name of the Contract is required.', 'warning');
    if (payload.items.length === 0) return showToast('Add at least one item.', 'warning');

    const bad = invalidItemRows();
    if (bad.item.length) {
        const has = bad.item.length === 1 ? 'has' : 'have';
        return showToast(`${rowList(bad.item)} ${has} no Amount. Fill in QTY and Unit Cost, enter an Amount directly, or leave the row as a description-only line.`, 'warning');
    }
    if (bad.desc.length) {
        const phrase = bad.desc.length === 1
            ? 'is a description-only line but still carries'
            : 'are description-only lines but still carry';
        return showToast(`${rowList(bad.desc)} ${phrase} an item number, unit or cost.`, 'warning');
    }

    const button = document.getElementById('cbSaveButton');
    button.disabled = true;
    try {
        const res = await fetch(apiUrl, {
            method: id ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to save.', 'error');
        showToast(data.message || 'Saved.');
        bootstrap.Modal.getInstance(document.getElementById('cbModal')).hide();
        loadBids();
    } catch (err) {
        showToast('Unable to save the bid document.', 'error');
        console.error(err);
    } finally {
        button.disabled = false;
    }
}

async function deleteBid(bidNumber) {
    if (!confirm(`Delete bid document ${bidNumber}? This also removes its items.`)) return;
    try {
        const res  = await fetch(`${apiUrl}?bid_number=${encodeURIComponent(bidNumber)}`, { method: 'DELETE' });
        const data = await res.json();
        showToast(data.message || (data.success ? 'Deleted.' : 'Unable to delete.'), data.success ? 'success' : 'error');
        if (data.success) loadBids();
    } catch (err) {
        showToast('Unable to delete the bid document.', 'error');
        console.error(err);
    }
}


// ── Signatories ─────────────────────────────────────────────────────────
// Off ref_signatory, the same master data the Purchase Order screen uses, so
// the Mayor is spelled one way across the whole application instead of being
// retyped onto every contract. Picking a name fills its title, which is what
// makes the pair impossible to mismatch.
let signatoryTitleByName = {};

async function loadSignatories() {
    try {
        const res = await fetch('api/master_data.php');
        const data = await res.json();
        if (!data.success) return;
        const rows = (data.categories && data.categories.signatories) || [];
        signatoryTitleByName = {};
        rows.forEach(r => { signatoryTitleByName[r.name] = r.extra || ''; });
        document.getElementById('dlSignatories').innerHTML =
            rows.map(r => `<option value="${escapeHtml(r.name)}">`).join('');
    } catch (err) {
        // A missing picklist is not worth blocking the screen for — the fields
        // stay free text, which is what they were before.
        console.error(err);
    }
}

// Only fills a blank title, never overwrites one. The title on an executed
// contract is what that person held when they signed it, and master data
// changing later must not rewrite it.
function fillSignatoryTitle(nameFieldId, titleFieldId) {
    const title = signatoryTitleByName[document.getElementById(nameFieldId).value.trim()];
    const titleField = document.getElementById(titleFieldId);
    if (title && !titleField.value.trim()) titleField.value = title;
}

// ── Quick fields ────────────────────────────────────────────────────────
// Each saves itself on change. Deliberately not part of the form's Save: these
// get filled in over weeks as the paperwork moves, and a save of unrelated
// header edits must not reset one. They stay writable on a locked document —
// recording that it reached CGSO is tracking, not a change of substance.

// Saving needs the bid number, and a document being created does not have one
// yet. Held from the last load rather than read off the form, so renaming the
// bid in the form cannot make a quick save land on a different record.
let cbQuickBidNumber = null;

async function saveQuickField(input) {
    const field = input.dataset.quick;
    if (!field || !cbQuickBidNumber) return;
    const status = document.getElementById('cbQuickStatus');
    try {
        const res = await fetch(apiUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bid_number: cbQuickBidNumber, quick_field: field, value: input.value }),
        });
        const data = await res.json();
        if (!data.success) {
            status.textContent = data.message || 'Could not save that field.';
            status.className = 'po-field-hint mt-1 cb-pct-off';
            return;
        }
        status.textContent = 'Saved ' + new Date().toLocaleTimeString('en-PH');
        status.className = 'po-field-hint mt-1';
        // A tracking date or a remark can move the set to a new stage, so the
        // badge in this modal and the row behind it are both re-read. That call
        // reloads the list too, which shows the tracking number and the lock.
        refreshStageAfterQuickSave();
    } catch (err) {
        status.textContent = 'Could not save that field.';
        status.className = 'po-field-hint mt-1 cb-pct-off';
        console.error(err);
    }
}

// Common Remarks wordings, off ref_remarks. The field stays free text — a
// genuinely one-off remark must still be typeable — this only offers the
// common ones so the common ones stay spelled identically and can be counted.
async function loadRemarksList() {
    try {
        const res = await fetch('api/master_data.php');
        const data = await res.json();
        if (!data.success) return;
        const rows = (data.categories && data.categories.remarks) || [];
        document.getElementById('dlRemarks').innerHTML =
            rows.map(r => `<option value="${escapeHtml(r.name)}">`).join('');
    } catch (err) {
        console.error(err);
    }
}

// ── View / lock / tracking ──────────────────────────────────────────────



// View reuses the edit modal with every control switched off, rather than
// being a second rendering of the same 31 fields that could drift out of step
// with it. Reverted by openNewBid()/editBid(), which both call setFormReadOnly
// (false) on the way in.
function setFormReadOnly(readOnly) {
    document.querySelectorAll('#cbForm input, #cbForm textarea, #cbForm select')
        .forEach(el => { el.disabled = readOnly; });
    document.querySelectorAll('#cbItemsTable button, #cbForm .item-grid-action-btn')
        .forEach(el => { el.disabled = readOnly; });
    document.getElementById('cbSaveButton').classList.toggle('d-none', readOnly);
    document.querySelectorAll('.cb-edit-only').forEach(el => el.classList.toggle('d-none', readOnly));
    // The import guide is toggled open by one of the .cb-edit-only buttons and
    // stays open between modal opens, so a view following an edit would show
    // instructions for filling rows it cannot fill.
    if (readOnly) document.getElementById('cbItemGuide').classList.add('d-none');
    // Drops the Action column entirely while viewing — its three buttons are
    // already disabled, and a column of dead controls is worse than no column.
    // The width goes back to Description.
    document.getElementById('cbModal').classList.toggle('cb-viewing', readOnly);
}

async function viewBid(bidNumber) {
    await editBid(bidNumber, true);
}

async function toggleBidLock(bidNumber, lock) {
    const verb = lock ? 'Lock' : 'Unlock';
    if (!confirm(`${verb} bid document ${bidNumber}?\n\n` + (lock
        ? 'It can no longer be edited or deleted until it is unlocked.'
        : 'It becomes editable again.'))) return;
    try {
        const res = await fetch(apiUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bid_number: bidNumber, lock_action: lock ? 'lock' : 'unlock' }),
        });
        const data = await res.json();
        showToast(data.message || (data.success ? 'Done.' : 'Unable to change the lock.'),
            data.success ? 'success' : 'error');
        if (data.success) loadBids();
    } catch (err) {
        showToast('Unable to change the lock.', 'error');
        console.error(err);
    }
}

// The DTS number is transcribed off a printed routing slip, so it is prompted
// for rather than being a field on the main form — it is usually written on
// after the set has been issued and locked, when the form is closed to edits.
async function trackBid(bidNumber) {
    try {
        const res = await fetch(`${apiUrl}?bid_number=${encodeURIComponent(bidNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load the bid document.', 'error');
        const current = (data.bid.barcode_number || '').trim();
        if (current) return dtsTrackBarcode(current);

        const entered = prompt(
            `Bid document ${bidNumber} has no Document Tracking number yet.\n\n` +
            'Enter the DTS number printed on its routing slip:', '');
        if (entered === null) return;
        const barcode = entered.trim();
        if (!barcode) return;

        const save = await fetch(apiUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bid_number: bidNumber, quick_field: 'barcode_number', value: barcode }),
        });
        const saved = await save.json();
        if (!saved.success) return showToast(saved.message || 'Unable to save the tracking number.', 'error');
        loadBids();
        dtsTrackBarcode(barcode);
    } catch (err) {
        showToast('Unable to open the tracking trail.', 'error');
        console.error(err);
    }
}

// ── Contract Agreement printout ─────────────────────────────────────────

// A recreation of the BAC's printed contract, from the scanned original.

function numberToWords(num) {
    const ones  = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
    const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens  = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const scales = ['', 'Thousand', 'Million', 'Billion'];
    function chunk(n) {
        let str = '';
        if (n >= 100) { str += ones[Math.floor(n / 100)] + ' Hundred '; n %= 100; }
        if (n >= 20) { str += tens[Math.floor(n / 10)] + ' '; n %= 10; }
        else if (n >= 10) { str += teens[n - 10] + ' '; n = 0; }
        if (n > 0) str += ones[n] + ' ';
        return str.trim();
    }
    let intPart = Math.floor(num);
    if (intPart === 0) return 'Zero';
    const parts = [];
    let scaleIndex = 0;
    while (intPart > 0) {
        const c = intPart % 1000;
        if (c > 0) parts.unshift(chunk(c) + (scales[scaleIndex] ? ' ' + scales[scaleIndex] : ''));
        intPart = Math.floor(intPart / 1000);
        scaleIndex++;
    }
    return parts.join(' ').trim();
}

// The contract writes the price out in full capitals followed by the figure —
// "TEN MILLION SIX HUNDRED FIFTY THOUSAND PESOS ONLY (P 10,650,000.00)".
function amountInWords(amount) {
    return numberToWords(Math.floor(amount)).toUpperCase() + ' PESOS ONLY';
}

// A blank prints as the ruled line the parties fill in by hand, which is what
// the original does for every date left open at signing.
function printedOr(value, blankWidth) {
    const v = String(value == null ? '' : value).trim();
    return v ? escapeHtml(v) : `<span class="cp-blank" style="min-width:${blankWidth || 60}px">&nbsp;</span>`;
}

function longDate(value) {
    if (!value) return '';
    const d = new Date(String(value).replace(' ', 'T'));
    return isNaN(d) ? String(value) : d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

// Item numbering is derived here, not stored. It is purely positional — priced
// rows count 1, 2, 3… and description-only lines carry no number — so deriving
// it at print time is the only way it cannot fall out of step with row order.
function buildContractItemRows(items) {
    let n = 0;
    return items.map(item => {
        const amount = parseMoney(item.total_cost);
        const priced = amount > 0;
        // A stored "0.000" is a non-empty string and therefore truthy, so the
        // obvious `value ? … : ''` prints a zero on every heading row. Compare
        // numerically instead.
        const qty  = parseMoney(item.quantity);
        const cost = parseMoney(item.unit_cost);
        return `<tr class="${priced ? 'cp-priced' : ''}">
            <td class="center">${priced ? ++n : ''}</td>
            <td class="center">${qty ? formatQty(qty) : ''}</td>
            <td class="center">${escapeHtml(item.unit) || ''}</td>
            <td class="desc">${escapeHtml(cleanDescription(item.items_description))}</td>
            <td class="num">${cost ? formatMoney(cost) : ''}</td>
            <td class="num">${priced ? formatMoney(amount) : ''}</td>
        </tr>`;
    }).join('');
}

// Quantities are whole numbers far more often than not, and "1.000 Unit" reads
// like a rounding artefact on a contract. Trailing zeros are dropped, real
// fractions kept.
function formatQty(n) {
    return Number(n).toLocaleString('en-PH', { maximumFractionDigits: 3 });
}

// Drops trailing spaces on each line and blank lines at the end. Leading
// spaces are left alone — they are the indentation the buyer typed, and
// pre-wrap renders them.
function cleanDescription(v) {
    return String(v == null ? '' : v).replace(/[ \t]+$/gm, '').replace(/\s+$/, '');
}

function buildContractHtml(bid) {
    const amount = parseMoney(bid.contract_amount);
    const itemsTotal = (bid.items || []).reduce((n, i) => n + parseMoney(i.total_cost), 0);
    // The figure the contract is executed for is the stored contract amount,
    // falling back to the items only when nobody set one — an executed price
    // is not something the item grid gets to restate.
    const price = amount || itemsTotal;
    const priceText = `<strong>${amountInWords(price)} (P ${formatMoney(price)})</strong>`;
    const name = escapeHtml(bid.contract_name || '');
    const nameLoc = name + (bid.contract_location ? ', ' + escapeHtml(bid.contract_location) : '');

    return `
    <div class="cp-page">
      <div class="cp-frame"><div class="cp-frame-inner">

        <table class="cp-letterhead">
          <tr>
            <td class="cp-seal"><img src="image/citylogo.png" alt=""></td>
            <td class="cp-lh-text">
              <div class="cp-lh-rep">Republic of the Philippines</div>
              <div class="cp-lh-rep">Province of Bukidnon</div>
              <div class="cp-lh-city">City of Malaybalay</div>
              <div>New City Hall, Barangay Casisang. 8700</div>
              <div>Tel (088) 813-4761-2</div>
            </td>
            <td class="cp-seal"></td>
          </tr>
        </table>
        <hr class="cp-rule">

        <div class="cp-contract-no">Contract No.: <span class="cp-val">${printedOr(bid.contract_number, 120)}</span></div>

        <div class="cp-title-box">
          <div class="cp-title-kicker">CONTRACT FOR</div>
          <div class="cp-title-name">${nameLoc}</div>
        </div>

        <div class="cp-h">KNOW ALL MEN BY THESE PRESENTS:</div>

        <p class="cp-p">This CONTRACT executed on the ${printedOr(contractDay(bid.contract_date), 50)} day of
          ${printedOr(contractMonth(bid.contract_date), 90)} ${escapeHtml(contractYear(bid.contract_date))} by and between:</p>

        <p class="cp-p"><strong>CITY GOVERNMENT OF MALAYBALAY</strong>, a government agency of the Republic of the
          Philippines, hereinafter called <strong>&ldquo;the Entity&rdquo;</strong>;</p>

        <div class="cp-center">-and-</div>

        <p class="cp-p"><strong>${escapeHtml(bid.supplier_name || '')}</strong> a company duly organized and existing
          under the laws of <strong>${printedOr(bid.supplier_org_place, 90)}</strong>, with principal office at
          <strong>${escapeHtml(bid.supplier_address || '')}</strong>, hereinafter called
          <strong>&ldquo;the Supplier&rdquo;</strong>.</p>

        <p class="cp-p"><strong>WHEREAS,</strong> the Entity invited Bids for certain goods,
          <strong>${name}</strong>${bid.contract_location ? ', <strong>' + escapeHtml(bid.contract_location) + '</strong>' : ''};</p>

        <p class="cp-p"><strong>WHEREAS</strong>, the Supplier submitted a responsive bid and was awarded the contract
          for the procurement in the total amount of ${priceText}, hereinafter referred to as the
          <strong>&ldquo;Contract Price.&rdquo;</strong></p>

        <p class="cp-p"><strong>NOW, THEREFORE,</strong> for and in consideration of the foregoing premises, the parties
          hereby agree as follows:</p>

        <div class="cp-list">
          <div><span class="cp-marker">1.</span>Unless otherwise stated, terms and expressions used in this Contract
            shall have the same meanings as those assigned to them in the Conditions of Contract, which form an integral
            part of this Contract.</div>
          <div><span class="cp-marker">2.</span>The following documents as required by the Implementing Rules and
            Regulations of Republic Act No. 12009 shall form part and be read and construed as integral parts of this
            Contract, <em>viz.</em>:
            <div class="cp-sublist">
              <div><strong><em>a.&nbsp;&nbsp;Philippine Bidding Documents (PBD);</em></strong></div>
              <div style="padding-left:22px"><strong><em>&bull; For Procurement of Goods</em></strong></div>
              <div style="padding-left:22px">i.&nbsp;&nbsp;&nbsp;&nbsp;Invitation to Bid;</div>
              <div style="padding-left:22px">ii.&nbsp;&nbsp;&nbsp;Instruction to Bidders;</div>
              <div style="padding-left:22px">iii.&nbsp;&nbsp;Bid Form, including all the documents/statements contained in the Bidder&rsquo;s bidding envelopes, as annexes, and all other documents submitted (e.g., Bidder&rsquo;s response to request for clarifications on the bid), including corrections to the bid, if any, resulting from the Procuring Entity&rsquo;s bid evaluation;</div>
              <div style="padding-left:22px">iv.&nbsp;&nbsp;&nbsp;Bid Data Sheet;</div>
              <div style="padding-left:22px">v.&nbsp;&nbsp;&nbsp;&nbsp;Schedule of Requirements;</div>
              <div style="padding-left:22px">vi.&nbsp;&nbsp;&nbsp;Technical Specifications;</div>
              <div style="padding-left:22px">vii.&nbsp;&nbsp;General and Special Conditions of Contract;</div>
              <div style="padding-left:22px">viii.&nbsp;Supplemental Bid Bulletins, if any; and</div>
              <div style="padding-left:22px">ix.&nbsp;&nbsp;&nbsp;Other contract documents that may be required by existing laws and/or the Entity.</div>
              <div><strong><em>b.&nbsp;&nbsp;Winning bidder&rsquo;s bid, including the Eligibility Requirements, Technical and Financial Proposals, and all other documents or statements submitted;</em></strong></div>
              <div><strong><em>c.&nbsp;&nbsp;Performance Security;</em></strong></div>
              <div><strong><em>d.&nbsp;&nbsp;Notice of Award of Contract; and the Bidder&rsquo;s Conforme thereto; and</em></strong></div>
              <div><strong><em>e.&nbsp;&nbsp;Other contract documents that may be required by existing laws and/or the Procuring Entity concerned in the PBD, such as but not limited to the Notice to Proceed and Warranty Security.</em></strong></div>
            </div>
          </div>
          <div><span class="cp-marker">3.</span>In consideration of the Contract Price of ${priceText}, or such other
            sums as may be determined in accordance with the terms of the Contract, the Supplier agrees to deliver and
            perform the items and related services for the <strong>${nameLoc}</strong> described herein in accordance
            with the terms and conditions specified in the Contract and its annexed documents.</div>
        </div>

        <table class="cp-items">
          <thead>
            <tr>
              <th style="width:6%">Item No.</th>
              <th style="width:7%">QTY</th>
              <th style="width:9%">Unit</th>
              <th style="width:44%">Description</th>
              <th style="width:17%">Unit Cost</th>
              <th style="width:17%">Amount</th>
            </tr>
          </thead>
          <tbody>
            ${buildContractItemRows(bid.items || [])}
            <tr><td colspan="4" class="cp-nothing">***NOTHING AS FOLLOWS***</td><td></td><td></td></tr>
            <tr class="cp-grand">
              <td colspan="4" class="cp-grand-label">${amountInWords(price)}</td>
              <td class="center">GRAND TOTAL</td>
              <td class="num">${formatMoney(price)}</td>
            </tr>
            <tr class="cp-delivery">
              <td colspan="2" class="center">Place of Delivery:</td>
              <td colspan="2" class="center">${escapeHtml(bid.place_of_delivery || '')}</td>
              <td class="center">Delivery Term:</td>
              <td class="center">${escapeHtml(bid.delivery_term || '')}</td>
            </tr>
          </tbody>
        </table>

        <p class="cp-penalty">In case of failure to make the full delivery within the time specified above, a penalty of
          one-tenth (1/10) of one percent for every day of delay shall be imposed.</p>

        <div class="cp-list">
          <div><span class="cp-marker">4.</span>The <strong>CITY GOVERNMENT OF MALAYBALAY</strong> agrees to pay the
            above-mentioned sum to the Supplier in accordance with the schedule and manner provided in the Bidding
            Documents and its annexes.</div>
          <div><span class="cp-marker">5.</span>Any dispute, difference, or claim arising out of or relating to this
            Contract, including its existence, validity, interpretation, breach, or termination thereof, may be
            submitted to arbitration or other form of alternative dispute resolution in accordance with the applicable
            law, such as Republic Act No. 9285 (Alternative Dispute Resolution Act of 2004) or Executive Order No 1008,
            series 1985 (Construction Industry Arbitration Law).</div>
        </div>

        <p class="cp-p" style="margin-top:18px"><strong>IN WITNESS WHEREOF,</strong> the parties hereto have caused this
          Contract to be executed in accordance with the laws of the Republic of the Philippines on the day and year
          first above written.</p>

        <table class="cp-sign">
          <tr>
            <td>
              <div class="cp-sign-role">Head of the Procuring Entity or Duly Authorized Representative</div>
              <div class="cp-sign-name">${escapeHtml(bid.signatory_name || '')}</div>
              <div class="cp-sign-title">${escapeHtml(bid.signatory_title || '')}</div>
              <div class="cp-sign-date">Date:<span class="cp-blank" style="min-width:130px">&nbsp;</span></div>
            </td>
            <td>
              <div class="cp-sign-role">Duly authorized to sign the Contract for and behalf of
                <strong>${escapeHtml(bid.supplier_name || '')}</strong></div>
              <div class="cp-sign-name">${escapeHtml(bid.representative_name || '')}</div>
              <div class="cp-sign-title">${escapeHtml(bid.representative_title || '')}</div>
              <div class="cp-sign-date">Date:<span class="cp-blank" style="min-width:130px">&nbsp;</span></div>
            </td>
          </tr>
        </table>

        <div class="cp-center" style="font-weight:700;font-style:italic;margin-top:6px">Signed in the presence of:</div>

        <table class="cp-sign">
          <tr>
            <td style="padding-top:34px">
              <div class="cp-sign-name">${escapeHtml(bid.witness_entity_name || '')}</div>
              <div class="cp-sign-title">${escapeHtml(bid.witness_entity_title || '')}</div>
              <div class="cp-sign-title">Witness &ndash; Procuring Entity</div>
            </td>
            <td style="padding-top:34px">
              <div class="cp-sign-name">${escapeHtml(bid.witness_supplier_name || '')}</div>
              <div class="cp-sign-title">Name and Signature</div>
              <div class="cp-sign-title">Witness&ndash; Supplier</div>
            </td>
          </tr>
        </table>

        <div class="cp-h" style="text-align:left;margin-top:26px">ACKNOWLEDGMENT</div>

        <p class="cp-p">BEFORE ME, A Notary Public for and in the ${printedOr(bid.notary_place, 190)}, City/Province of
          <span class="cp-blank" style="min-width:150px">&nbsp;</span>, this
          ${printedOr(contractDay(bid.contract_date), 44)} day of
          ${printedOr(contractMonth(bid.contract_date), 110)}, ${escapeHtml(contractYear(bid.contract_date))},
          personally appeared the above-named persons who have satisfactorily proven to me their identity, through their
          identifying documents written below their names and signatures, that they are the same persons who executed
          and voluntarily signed the foregoing instrument, which they acknowledged before me as their free and voluntary
          act and deed.</p>

        <p class="cp-p">WITNESS MY HAND AND SEAL this <span class="cp-blank" style="min-width:44px">&nbsp;</span> day of
          <span class="cp-blank" style="min-width:130px">&nbsp;</span>, <em>${escapeHtml(contractYear(bid.contract_date))}</em>.</p>

        <div class="cp-notary-grid">
          <div>NAME OF NOTARY PUBLIC&nbsp;&nbsp;${printedOr(bid.notary_name, 200)}</div>
          <div>Notarial Commission No.&nbsp;&nbsp;<span class="cp-blank" style="min-width:150px">&nbsp;</span></div>
          <div>Notary Public for&nbsp;&nbsp;<span class="cp-blank" style="min-width:110px">&nbsp;</span>
               until&nbsp;&nbsp;<span class="cp-blank" style="min-width:110px">&nbsp;</span></div>
          <div>Roll of Attorneys No.&nbsp;&nbsp;<span class="cp-blank" style="min-width:140px">&nbsp;</span></div>
          <div>PTR No.&nbsp;&nbsp;<span class="cp-blank" style="min-width:150px">&nbsp;</span></div>
          <div>IBP No.&nbsp;&nbsp;<span class="cp-blank" style="min-width:150px">&nbsp;</span></div>
        </div>

        <div class="cp-notary-grid" style="margin-top:20px">
          <div>Doc. No.&nbsp;&nbsp;${printedOr(bid.notary_doc_no, 70)}</div>
          <div>Page No.&nbsp;&nbsp;${printedOr(bid.notary_page_no, 70)}</div>
          <div>Book No.&nbsp;&nbsp;${printedOr(bid.notary_book_no, 70)}</div>
          <div>Series of.&nbsp;&nbsp;${printedOr(bid.notary_series, 70)}</div>
        </div>

      </div></div>
    </div>`;
}

// The contract's opening line reads "executed on the ___ day of ___ 2026", so
// the date is split rather than formatted whole. An unset contract date leaves
// the day and month as ruled lines and falls back to the current year, which
// is what the blank forms are pre-printed with.
function contractDay(value) {
    if (!value) return '';
    const d = new Date(String(value).replace(' ', 'T'));
    return isNaN(d) ? '' : String(d.getDate());
}
function contractMonth(value) {
    if (!value) return '';
    const d = new Date(String(value).replace(' ', 'T'));
    return isNaN(d) ? '' : d.toLocaleDateString('en-US', { month: 'long' });
}
function contractYear(value) {
    if (!value) return String(new Date().getFullYear());
    const d = new Date(String(value).replace(' ', 'T'));
    return isNaN(d) ? String(new Date().getFullYear()) : String(d.getFullYear());
}


// ── Notice of Award / Notice to Proceed ─────────────────────────────────
// Both sit on the BAC's letterhead and share their opening block, so that much
// is built once. `sfNumber` is the standard-form identity printed above the
// rule — the Notice of Award carries SF-GOOD-49, the Notice to Proceed none.
function buildBacLetterhead(sfNumber) {
    return `
        <table class="cl-letterhead">
          <tr>
            <td class="cl-seal"><img src="image/citylogo.png" alt=""></td>
            <td class="cl-lh-text">
              <div class="cl-lh-rep">Republic of the Philippines</div>
              <div class="cl-lh-rep">Province of Bukidnon</div>
              <div class="cl-lh-city">City of Malaybalay</div>
              <div class="cl-lh-bac">Bids and Awards Committee</div>
              <div>New City Hall, Barangay Casisang</div>
              <div>Malaybalay City, Bukidnon</div>
              <div>Tel (088) 813-4761</div>
              <div class="cl-lh-mail">bacmalaybalay@gmail.com</div>
            </td>
            <td class="cl-seal"><img src="image/City%20BAC.png" alt=""></td>
          </tr>
        </table>
        ${sfNumber ? `<div class="cl-sf">Standard Form Number: ${escapeHtml(sfNumber)}<br>Revised on: May 24, 2004</div>` : ''}
        <hr class="cl-rule">`;
}

// The three-line reference block both letters open with.
function buildBacReferenceBlock(bid) {
    return `
        <table class="cl-ref">
          <tr><td class="cl-ref-label">Contract Reference Number:</td><td class="cl-ref-value">${escapeHtml(bid.bid_number || '')}</td></tr>
          <tr><td class="cl-ref-label">Name of the Contract:</td><td class="cl-ref-value">${escapeHtml(bid.contract_name || '')}</td></tr>
          <tr><td class="cl-ref-label">Location of the Contract:</td><td class="cl-ref-value">${escapeHtml(bid.contract_location || '')}</td></tr>
        </table>`;
}

// Who the letter is addressed to. Identical on both documents.
function buildBidderAddressBlock(bid) {
    return `
        <div class="cl-addr">
          <div class="cl-addr-name">${escapeHtml(bid.representative_name || '')}</div>
          <div>${escapeHtml(bid.representative_title || '')}</div>
          <div class="cl-addr-company">${escapeHtml(bid.supplier_name || '')}</div>
          <div>${escapeHtml(bid.supplier_address || '')}</div>
          ${bid.supplier_address2 ? `<div>${escapeHtml(bid.supplier_address2)}</div>` : ''}
        </div>`;
}

function buildNoticeOfAwardHtml(bid) {
    const price = parseMoney(bid.contract_amount)
        || (bid.items || []).reduce((n, i) => n + parseMoney(i.total_cost), 0);
    const nameLoc = escapeHtml(bid.contract_name || '')
        + (bid.contract_location ? ', ' + escapeHtml(bid.contract_location) : '');

    return `
    <div class="cl-page">
      ${buildBacLetterhead('SF-GOOD-49')}
      ${buildBacReferenceBlock(bid)}

      <div class="cl-title">NOTICE OF AWARD</div>
      <div class="cl-date">${bid.noa_date ? escapeHtml(longDate(bid.noa_date)) : '&nbsp;'}</div>

      ${buildBidderAddressBlock(bid)}

      <p class="cl-p">Dear Sir:</p>

      <p class="cl-p">We are informing you that your Bid dated <strong>${bid.bid_date ? escapeHtml(longDate(bid.bid_date)) : '__________'}</strong>
        for execution of <strong>BID NO. ${escapeHtml(bid.bid_number || '')} ${nameLoc}</strong> with a total contract
        price of <strong>${amountInWords(price)} (P ${formatMoney(price)})</strong> is hereby accepted.</p>

      <p class="cl-p">You are hereby required to provide within ten (10) calendar days the performance security in the
        form and the amount stipulated below:</p>

      <p class="cl-p">Failure to provide the performance security shall constitute sufficient ground for cancellation of
        the award and forfeiture of the bid security.</p>

      <table class="cl-bond">
        <tr>
          <td><strong>Cash Bond, cashier's /manager's check, Bank Draft/guarantee: or Irrevocable Letter of Credit; or</strong></td>
          <td class="cl-bond-amt">${bid.cash_bond_amount ? 'P ' + formatMoney(parseMoney(bid.cash_bond_amount)) : '&nbsp;'}</td>
        </tr>
        <tr>
          <td><strong>Surety Bond:</strong></td>
          <td class="cl-bond-amt">${bid.surety_bond_amount ? 'P ' + formatMoney(parseMoney(bid.surety_bond_amount)) : '&nbsp;'}</td>
        </tr>
      </table>

      <div class="cl-note">*** Note: Expiry should be &ldquo;Until Issuance of final acceptance&rdquo;</div>

      <p class="cl-p">Very truly yours,</p>
      <div class="cl-sign-block">
        <div class="cl-sign-name">${escapeHtml(bid.signatory_name || '')}</div>
        <div>${escapeHtml(bid.signatory_title || '')}</div>
      </div>

      <div style="margin-top:26px">Conformed:</div>
      <div class="cl-sign-block">
        <div class="cl-sign-name">${escapeHtml(bid.representative_name || '')}</div>
        <div>${escapeHtml(bid.supplier_name || '')}</div>
        <div style="margin-top:6px">Date:<span class="cl-blank" style="min-width:150px">&nbsp;</span></div>
      </div>
    </div>`;
}

function buildNoticeToProceedHtml(bid) {
    const nameLoc = escapeHtml(bid.contract_name || '')
        + (bid.contract_location ? ', ' + escapeHtml(bid.contract_location) : '');

    return `
    <div class="cl-page">
      ${buildBacLetterhead('')}
      ${buildBacReferenceBlock(bid)}

      <div class="cl-title">NOTICE TO PROCEED</div>
      <div class="cl-date">${bid.ntp_date ? escapeHtml(longDate(bid.ntp_date)) : '&nbsp;'}</div>

      ${buildBidderAddressBlock(bid)}

      <p class="cl-p">Dear Sir:</p>

      <p class="cl-p">The attached Contract Agreement having been approved, notice is hereby given to
        <strong>${escapeHtml(bid.supplier_name || '')}</strong> that work may commence on the
        <strong>${nameLoc}.</strong></p>

      <p class="cl-p">Upon receipt of this notice, you are responsible for performing the services under the terms and
        conditions of the Agreement and in accordance with the Implementation and Payment Schedule:</p>

      <p class="cl-p">Please acknowledge receipt and acceptance of this notice by signing both copies in the space
        provided below, keep one copy and return the other to the <strong><em>City of Malaybalay.</em></strong></p>

      <p class="cl-p" style="margin-top:34px">Very truly yours,</p>
      <div class="cl-sign-block">
        <div class="cl-sign-name">${escapeHtml(bid.signatory_name || '')}</div>
        <div>${escapeHtml(bid.signatory_title || '')}</div>
      </div>

      <div class="cl-ack">
        <div>I acknowledge receipt of this Notice on <span class="cl-blank" style="min-width:250px">&nbsp;</span></div>
        <div style="text-align:center" class="cl-sub">(Date of receipt)</div>
        <div>Name of the Representative of the Bidder
          <span style="border-bottom:1px solid #000;padding:0 12px;font-weight:700">${escapeHtml(bid.representative_name || '') || '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'}</span></div>
        <div>Authorized Signature: <span class="cl-blank" style="min-width:250px">&nbsp;</span></div>
      </div>
    </div>`;
}

// The three documents, in the order the BAC issues them. Each says how to
// build it, what to call the file, and what furniture the printed original
// carries — the contract has a running header and the marginal initialling
// strip, the two letters have neither.
const CB_DOCUMENTS = {
    noa: {
        label: 'Notice of Award',
        build: buildNoticeOfAwardHtml,
        selector: '.cl-page',
        file: 'Notice_of_Award',
        singlePage: true,
        breakSelector: '.cl-p, .cl-bond, .cl-sign-block, .cl-ack > div',
        gridSelector: '.cl-bond',
        headerBandPt: 0,
        furniture: 'plain',
    },
    contract: {
        label: 'Contract Agreement',
        build: buildContractHtml,
        selector: '.cp-page',
        file: 'Contract',
        breakSelector: 'tr, .cp-p, .cp-list > div, .cp-sublist > div, .cp-title-box, .cp-sign, .cp-notary-grid > div',
        gridSelector: '.cp-items',
        headerBandPt: 22,
        furniture: 'contract',
        // The longest of the three prints by far (20+ pages once a bid covers
        // this many items) — the shared single-canvas pipeline has to shrink
        // a document that long below full resolution to stay under the
        // browser's canvas size cap, which is what made this one specifically
        // look soft. Rasterising it page-by-page instead keeps every page at
        // full scale regardless of how many there are.
        perPage: true,
    },
    ntp: {
        label: 'Notice to Proceed',
        build: buildNoticeToProceedHtml,
        selector: '.cl-page',
        file: 'Notice_to_Proceed',
        singlePage: true,
        breakSelector: '.cl-p, .cl-sign-block, .cl-ack > div',
        gridSelector: '',
        headerBandPt: 0,
        furniture: 'plain',
    },
};

// The two letters print with nothing but the date/page line at the foot. A
// page number is still worth printing: a one-page letter says "Page 1 of 1",
// which is how a reader knows nothing is missing from the envelope.
function drawPlainFurniture(pdf, page, pageCount, geo, stamp) {
    const baseline = geo.pageH - geo.margin - 4;
    pdf.setTextColor(0, 0, 0);
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(7.5);
    pdf.text(`Date Printed: ${stamp.date}   ${stamp.time}`, geo.margin, baseline);
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(9);
    pdf.text(`Page ${page} of ${pageCount}`, geo.pageW - geo.margin, baseline, { align: 'right' });
}
// ── Preview / PDF ───────────────────────────────────────────────────────
let cbPdfBlobUrl = null;
let cbPdfFileName = 'contract.pdf';

// Asks the viewer to open with its sidebar collapsed. A hint, not a guarantee.
const CB_PDF_VIEW_PARAMS = '#navpanes=0&toolbar=1&view=FitH';

// The bid currently in the preview, held so switching document does not refetch.
let cbPreviewBid = null;
let cbPreviewDoc = 'contract';

async function openBidPrint(bidNumber, docType) {
    try {
        const res = await fetch(`${apiUrl}?bid_number=${encodeURIComponent(bidNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load the bid document.', 'error');
        cbPreviewBid = data.bid;
        cbPreviewDoc = CB_DOCUMENTS[docType] ? docType : 'contract';
        renderDocTabs();
        document.getElementById('cbPrintSubtitle').textContent =
            'Bid No. ' + bidNumber + (data.bid.contract_name ? ' — ' + data.bid.contract_name : '');
        const modalEl = document.getElementById('cbPrintModal');
        openModal('cbPrintModal');
        // Wait for the fade-in to finish before rasterising: Bootstrap animates
        // .modal-dialog with a transform while showing, and html2canvas reading
        // layout mid-transition can capture an inconsistent box.
        modalEl.addEventListener('shown.bs.modal', renderCurrentDoc, { once: true });
    } catch (err) {
        showToast('Unable to load the bid document.', 'error');
        console.error(err);
    }
}

// One switcher rather than three buttons on every list row: all three documents
// describe the same record, and whoever issues them works through the set.
function renderDocTabs() {
    document.getElementById('cbDocTabs').innerHTML = Object.keys(CB_DOCUMENTS).map(key =>
        `<button type="button" class="btn btn-sm rounded-pill ${key === cbPreviewDoc ? 'btn-primary-app' : 'btn-outline-secondary'}"
                 onclick="switchDoc('${key}')">${escapeHtml(CB_DOCUMENTS[key].label)}</button>`).join('');
}

function switchDoc(key) {
    if (!CB_DOCUMENTS[key] || key === cbPreviewDoc) return;
    cbPreviewDoc = key;
    renderDocTabs();
    renderCurrentDoc();
}

function renderCurrentDoc() {
    if (!cbPreviewBid) return;
    const doc = CB_DOCUMENTS[cbPreviewDoc];
    document.getElementById('cbPrintSource').innerHTML = doc.build(cbPreviewBid);
    generateBidPdf(cbPreviewBid, doc);
}

// Space reserved at the foot of every sheet for the date/page line. The top
// band varies by document — only the contract prints a running header — so it
// lives on each entry in CB_DOCUMENTS instead.
const CB_FOOTER_BAND_PT = 20;

async function generateBidPdf(bid, doc) {
    const loading = document.getElementById('cbPdfLoading');
    const frame = document.getElementById('cbPdfFrame');
    const buttons = ['cbPdfDownload', 'cbPdfPrint', 'cbPdfOpenTab'].map(id => document.getElementById(id));

    frame.classList.add('d-none');
    buttons.forEach(b => { if (b) b.disabled = true; });
    loading.classList.remove('d-none');
    loading.innerHTML = '<div class="spinner-border text-primary" role="status"></div><div class="small mt-2">Generating PDF preview…</div>';

    try {
        const source = document.querySelector('#cbPrintSource ' + doc.selector);
        if (!source) throw new Error('Print content did not render — try closing and reopening the preview.');

        const stamp = PrintPdf.printedStamp();
        const renderFn = doc.perPage ? PrintPdf.renderPerPageToPdf : PrintPdf.renderToPagedPdf;
        const pdf = await renderFn(source, {
            // A break candidate at every table row and every paragraph, so a cut
            // never lands mid-sentence or mid-item.
            breakSelector: doc.breakSelector,
            gridSelector: doc.gridSelector,
            headerBandPt: doc.headerBandPt,
            footerBandPt: CB_FOOTER_BAND_PT,
            singlePage: !!doc.singlePage,
            restoreTo: document.getElementById('cbPrintSource'),
            onPage: (pdf, page, pageCount, geo) => doc.furniture === 'contract'
                ? drawContractFurniture(pdf, page, pageCount, geo, bid, stamp)
                : drawPlainFurniture(pdf, page, pageCount, geo, stamp),
        });

        const blob = pdf.output('blob');
        if (cbPdfBlobUrl) URL.revokeObjectURL(cbPdfBlobUrl);
        cbPdfBlobUrl = URL.createObjectURL(blob);
        cbPdfFileName = `${doc.file}_${bid.bid_number}.pdf`;

        frame.src = cbPdfBlobUrl + CB_PDF_VIEW_PARAMS;
        loading.classList.add('d-none');
        frame.classList.remove('d-none');
        buttons.forEach(b => { if (b) b.disabled = false; });
    } catch (err) {
        // Surfaced directly rather than as a generic message, so a failure is
        // diagnosable instead of a dead end.
        loading.innerHTML = '<div class="text-danger small px-3"><i class="fas fa-triangle-exclamation me-1"></i>Unable to generate the PDF: '
            + escapeHtml(err.message || String(err)) + '</div>';
        console.error(err);
    }
}

// Everything the original prints OUTSIDE its border: the bid number and page
// count across the top, the date printed and page line at the foot, and the
// rotated strip down the left margin where each party initials every sheet.
//
// Drawn here rather than in the HTML because the form is one tall raster sliced
// into pages — anything inside it can only ever appear once.
function drawContractFurniture(pdf, page, pageCount, geo, bid, stamp) {
    pdf.setTextColor(0, 0, 0);

    // The pre-bordered-stock double rule, closed on every page — around the
    // content box only, not the running header/footer bands above and below
    // it, matching where the form's own raster is placed.
    const frameX = geo.margin;
    const frameY = geo.margin + geo.headerBandPt;
    const frameW = geo.contentW;
    const frameH = geo.pageH - geo.margin * 2 - geo.headerBandPt - geo.footerBandPt;
    pdf.setDrawColor(31, 63, 143);
    pdf.setLineWidth(1.4);
    pdf.rect(frameX, frameY, frameW, frameH);
    pdf.setLineWidth(0.75);
    pdf.rect(frameX + 3, frameY + 3, frameW - 6, frameH - 6);
    pdf.setDrawColor(0, 0, 0);

    // Running header, on the baseline of the reserved top band.
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(8.5);
    pdf.text('Bid No. ' + (bid.bid_number || ''), geo.margin, geo.margin + 10);
    pdf.setTextColor(190, 30, 45);            // the original prints this in red
    pdf.text('Page ' + page + ' of ' + pageCount, geo.pageW - geo.margin, geo.margin + 10, { align: 'right' });
    pdf.setTextColor(0, 0, 0);

    // Foot of the sheet.
    const baseline = geo.pageH - geo.margin - 4;
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(7.5);
    pdf.text(`Date Printed: ${stamp.date}   ${stamp.time}`, geo.margin, baseline);
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(9);
    pdf.text(`Page ${page} of ${pageCount}`, geo.pageW - geo.margin, baseline, { align: 'right' });

    // Marginal initialling strip. Rotated 90°, reading bottom-to-top, spaced
    // down the sheet the way the original spaces them. Names come from the
    // record, so a contract with no witness simply prints fewer strips rather
    // than a label with nobody's name against it.
    const strip = [
        [bid.witness_supplier_name, 'Supplier Witness'],
        [bid.representative_name, bid.representative_title],
        [bid.witness_entity_name, (bid.witness_entity_title || '') + ' Witness'],
        [bid.signatory_name, bid.signatory_title],
    ].filter(pair => String(pair[0] || '').trim() !== '');

    if (!strip.length) return;
    const usable = geo.pageH - geo.margin * 2 - 40;
    const step = usable / strip.length;
    strip.forEach((pair, i) => {
        const y = geo.margin + 30 + step * i + step / 2;
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(7.5);
        pdf.text(String(pair[0]).toUpperCase(), geo.margin - 4, y, { angle: 90 });
        if (String(pair[1] || '').trim()) {
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(6);
            pdf.text(String(pair[1]), geo.margin + 4, y, { angle: 90 });
        }
    });
}

function downloadContractPdf() {
    if (!cbPdfBlobUrl) return;
    const a = document.createElement('a');
    a.href = cbPdfBlobUrl;
    a.download = cbPdfFileName;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function printContractPdf() {
    const frame = document.getElementById('cbPdfFrame');
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch (err) {
        // Some built-in PDF viewers don't expose a scriptable print() — fall
        // back to a new tab, where the viewer's own toolbar works normally.
        window.open(cbPdfBlobUrl, '_blank');
    }
}
window.addEventListener('DOMContentLoaded', () => {
    initTable();
    initDateFilters();
    // Competitive Insights links through as competitive_bidding.php?search=…
    // (a supplier name, a contract number). Applied to the filter box rather
    // than to the request alone, so the visitor can see what is being filtered
    // on and clear it.
    const preset = new URLSearchParams(window.location.search).get('search');
    if (preset) document.getElementById('searchText').value = preset;
    loadBids();
    document.getElementById('btnAddBid').addEventListener('click', openNewBid);
    ['contract_amount', 'cash_bond_amount', 'surety_bond_amount'].forEach(id =>
        document.getElementById(id).addEventListener('input', refreshBondHints));
    ['pr_amount', 'contract_amount'].forEach(id =>
        document.getElementById(id).addEventListener('input', refreshSavingsBand));
    loadSignatories();
    loadRemarksList();
    document.querySelectorAll('.cb-quick').forEach(el =>
        el.addEventListener('change', () => saveQuickField(el)));
    document.getElementById('cbModal').addEventListener('shown.bs.modal', regrowModalFields);
    document.getElementById('cbPdfDownload').addEventListener('click', downloadContractPdf);
    document.getElementById('cbPdfPrint').addEventListener('click', printContractPdf);
    document.getElementById('cbPdfOpenTab').addEventListener('click', () => { if (cbPdfBlobUrl) window.open(cbPdfBlobUrl, '_blank'); });
    // The description column is a percentage of a table-layout:fixed grid, so
    // resizing the window changes how its text wraps and therefore how tall it
    // needs to be. The textarea is overflow:hidden, so without re-measuring, a
    // narrower window would quietly clip the tail of a specification block with
    // no scrollbar to hint that anything was missing. Debounced, because resize
    // fires continuously while a window is being dragged.
    let regrowTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(regrowTimer);
        regrowTimer = setTimeout(regrowModalFields, 150);
    });
    document.getElementById('itemImportInput').addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) importItemsIntoGrid(file);
        // Cleared so re-picking the same file fires 'change' again.
        e.target.value = '';
    });
    document.querySelectorAll('.cb-autogrow').forEach(el => {
        el.addEventListener('input', () => autoGrowField(el));
        autoGrowField(el);
    });
    // Notification links arrive as competitive_bidding.php?bid_number=… — open
    // that document straight away rather than leaving the visitor to find it.
    const deepLink = new URLSearchParams(window.location.search).get('bid_number');
    if (deepLink) editBid(deepLink);
});
</script>
<?php include 'assistant.php'; ?>
</body>
</html>
