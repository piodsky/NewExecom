<?php
require_once 'config.php';
requireAuth();
requireModule('inspection');
$currentUser = getCurrentUser();
$activePage  = 'inspection';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inspection &amp; Acceptance | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="vendor/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?= asset('css/base.css') ?>">
<link rel="stylesheet" href="<?= asset('css/iar-print.css') ?>">
<link rel="stylesheet" href="<?= asset('css/dv-print.css') ?>">
<style>
/* Deliberately reuses the Purchase Order module's card, field and chip
   vocabulary so the two screens read as one system. Only what this page adds
   beyond that vocabulary is defined here. */
.table-actions { display: flex; gap: .35rem; flex-wrap: wrap; }
.po-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem 1.4rem; margin-bottom: 1.1rem; }
.po-card-compact { padding: .95rem 1.15rem; }
.po-card-title { display: flex; align-items: center; gap: .55rem; font-weight: 700; color: var(--navy); font-size: 1rem; margin-bottom: 1.1rem; }
.po-card-compact .po-card-title { margin-bottom: .65rem; font-size: .92rem; }
.po-card-title i { color: var(--primary); font-size: 1.05rem; }

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

/* Remarks suggestions — inspRemarks is a <textarea> (remarks can genuinely
   run long, unlike Progress Billing's one-line Remarks input which uses a
   plain native <datalist> instead), and a <textarea> cannot take a `list`
   attribute at all, so this is a small self-built dropdown instead, same
   idea as purchase_orders.php's own Purpose/Supplier suggestion boxes but a
   local copy rather than a shared one — the two pages don't share a script. */
.insp-remarks-suggest {
    position: absolute; left: 0; right: 0; top: 100%; z-index: 20;
    max-height: 220px; overflow-y: auto; margin-top: 2px;
    background: #fff; border: 1px solid var(--border);
    border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,.12);
}
.insp-remarks-suggest .rs-item { padding: .45rem .7rem; cursor: pointer; font-size: .82rem; }
.insp-remarks-suggest .rs-item:hover { background: var(--primary); color: #fff; }
/* Names are the one thing on this form that has no natural length limit, and a
   field cut off mid-letter reads as data loss. An unfocused input shows an
   ellipsis instead; focused, the browser scrolls the text as normal, and the
   title attribute (set by syncNameTitles) has the whole thing on hover. */
#inspectModal input[type="text"] { text-overflow: ellipsis; }


/* Status chips over the table — same control as the PO list. */
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
.po-chip-pending   { border-color: #fcd34d; color: #b45309; }
.po-chip-inspected { border-color: #86efac; color: #15803d; }

.num-pill {
    display: inline-block; padding: .1rem .45rem; border-radius: 6px;
    font-size: .74rem; font-weight: 700; border: 1px solid transparent; white-space: nowrap;
}
.po-num-pill { color: #c2410c; background: #fff2e6; border-color: #fed7aa; }
.pr-num-pill { color: #1d4ed8; background: #e8f0ff; border-color: #bfdbfe; }

.status-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 700; padding: .18rem .55rem; border-radius: 20px; }
/* Purpose is free text and often a full sentence — shown in full, wrapping
   onto as many lines as it needs. Capped in width only, so it wraps instead of
   pushing the rest of the columns off to the right. Same treatment, and the
   same numbers, as the Purchase Order list. */
.insp-purpose-cell {
    display: block; max-width: 320px; min-width: 160px;
    white-space: normal; word-break: break-word;
}
/* Remarks are free text and can run long. Narrower than Purpose and capped at
   three lines, so one wordy note cannot make its row four times taller than
   every other row in the table; the full text is on the row's own dialog. */
.insp-remarks-cell {
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;
    overflow: hidden; max-width: 220px; min-width: 120px;
    white-space: normal; word-break: break-word;
}
/* Once one cell in a row can run to several lines, the default vertical
   centring floats the short cells into the middle of a tall row, away from the
   purpose they belong to. */
#inspectionTable tbody td { vertical-align: top; }

.status-inspected { background: #dcfce7; color: #15803d; }
.status-pending   { background: #fef3c7; color: #b45309; }

/* ── Progress billing ───────────────────────────────────────────────────────
   A second flow living beside the single-inspection one, so it gets its own
   colour — indigo — and never reuses the green/amber the Inspected and
   Awaiting states own. */
.status-billing { background: #e0e7ff; color: #4338ca; }
/* Finished and over-billed read at a glance, without having to parse a number.
   Deliberately the same green the Inspected badge uses — in this app green
   already means "nothing further is owed here". */
.status-billing.done { background: #dcfce7; color: #15803d; }
.status-billing.over { background: #fee2e2; color: #b91c1c; }
.status-billing.idle { background: #f1f5f9; color: #64748b; }
.po-chip-billing { border-color: #a5b4fc; color: #4338ca; }
/* Two badges can share the Status cell. Stacked, because side by side they
   push the column wide enough to squeeze Purpose. */
.status-stack { display: inline-flex; flex-direction: column; align-items: flex-start; gap: .25rem; }
/* Two lines BY DESIGN, not by wrapping: the badge is exactly as wide as the
   words "Progress Billing", so adding the state below it cannot widen the
   Status column and push Actions off the table. */
.status-badge.status-billing { align-items: flex-start; line-height: 1.25; }
.status-badge.status-billing i { margin-top: .12rem; }
.pb-badge-txt { display: block; white-space: nowrap; }
.pb-badge-txt b { display: block; font-weight: 800; }
/* The row already carries up to four buttons; a fifth must not stack them
   vertically the way the Competitive Bidding list did. */
#inspectionTable .table-actions { flex-wrap: nowrap; }

.pb-modal-header { background: linear-gradient(135deg, #4338ca 0%, #6366f1 100%); }
/* The completion pill, beside the dialog title. Its whole job is to answer
   "how far along is this?" without reading the numbers underneath. */
.pb-title-row { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.pb-status-pill {
    font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    padding: .18rem .6rem; border-radius: 20px; white-space: nowrap;
    background: rgba(255,255,255,.22); color: #fff; border: 1px solid rgba(255,255,255,.35);
}
.pb-status-pill:empty { display: none; }
/* Done, over, and not started each get their own read — the same three states
   the bar underneath uses, so the two can never disagree. */
.pb-status-pill.done  { background: #16a34a; border-color: #16a34a; }
.pb-status-pill.over  { background: #dc2626; border-color: #dc2626; }
.pb-status-pill.idle  { background: rgba(255,255,255,.12); color: rgba(255,255,255,.75); }
.pb-bar.done span { background: linear-gradient(90deg, #15803d, #22c55e); }

.pb-flow-note {
    display: flex; gap: .55rem; align-items: flex-start;
    background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 10px;
    padding: .6rem .8rem; margin-bottom: .9rem;
    font-size: .8rem; line-height: 1.45; color: #3730a3;
}
.pb-flow-note i.fa-circle-info { margin-top: .15rem; }

/* Contract / billed / remaining, and the bar underneath. */
.pb-hero { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .75rem; }
.pb-hero .cell { background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: .6rem .75rem; }
.pb-hero .k { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); font-weight: 700; }
.pb-hero .v { font-size: 1.05rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.pb-hero .v.billed    { color: #4338ca; }
.pb-hero .v.remaining { color: #15803d; }
.pb-hero .v.over      { color: #b91c1c; }
.pb-bar { height: 10px; border-radius: 6px; background: #e2e8f0; overflow: hidden; margin-top: .6rem; }
.pb-bar span { display: block; height: 100%; background: linear-gradient(90deg, #4338ca, #6366f1); }
.pb-bar.over span { background: linear-gradient(90deg, #b91c1c, #ef4444); }

/* Per-item progress. One column per billing recorded, so a contract with six
   tranches gets six columns — it scrolls sideways inside its own box rather
   than widening the dialog. */
.pb-progress-scroll { overflow-x: auto; }
.pb-progress { width: 100%; font-size: .8rem; border-collapse: collapse; }
.pb-progress th, .pb-progress td { border: 1px solid var(--border); padding: .35rem .5rem; vertical-align: top; }
.pb-progress thead th { background: #eef2ff; color: #3730a3; font-size: .68rem; text-transform: uppercase;
                        letter-spacing: .04em; white-space: nowrap; }
.pb-progress td.num, .pb-progress th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.pb-progress .pb-desc { min-width: 220px; white-space: pre-wrap; }
.pb-progress tr.done td { background: #f0fdf4; }
.pb-progress tr.over td { background: #fef2f2; }
.pb-progress .pb-billed-cell { color: #4338ca; font-weight: 600; }
.pb-progress tfoot td { background: #f8fafc; font-weight: 700; }
.pb-unmatched { font-size: .68rem; color: #b45309; font-weight: 700; }
/* A caption row on the purchase order — "Lot 1", or a line spelling out what
   the priced item above it contains. Rendered as the heading it is, not as an
   item with a permanently empty quantity. */
.pb-progress tr.caption td, #pbEditItems tr.caption td {
    background: #f1f5f9; font-weight: 700; color: #334155; white-space: pre-wrap; }
.pb-lot { font-size: .68rem; color: var(--muted); font-weight: 600; }

/* One recorded billing. */
.pb-card { border: 1px solid var(--border); border-left: 4px solid #6366f1; border-radius: 10px;
           padding: .7rem .9rem; margin-bottom: .6rem; background: #fff; }
.pb-card-head { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
.pb-card-no { font-weight: 700; color: #3730a3; }
.pb-card-meta { font-size: .76rem; color: var(--muted); }
.pb-card-total { margin-left: auto; font-weight: 700; font-variant-numeric: tabular-nums; }
.pb-card-lines { margin-top: .5rem; font-size: .78rem; border-top: 1px dashed var(--border); padding-top: .45rem; }
.pb-card-lines div { display: flex; gap: .5rem; padding: .1rem 0; }
.pb-card-lines .q { font-variant-numeric: tabular-nums; color: #4338ca; font-weight: 600; min-width: 74px; }
.pb-card-lines .d { flex: 1; white-space: pre-wrap; }
.pb-card-lines .t { font-variant-numeric: tabular-nums; }
.pb-card-revised { font-size: .72rem; color: #b45309; font-weight: 600; margin-top: .15rem; }

/* The editor's item grid. */
#pbEditItems { width: 100%; font-size: .8rem; border-collapse: collapse; }
#pbEditItems th, #pbEditItems td { border: 1px solid var(--border); padding: .3rem .4rem; vertical-align: top; }
#pbEditItems thead th { background: #eef2ff; color: #3730a3; font-size: .68rem; text-transform: uppercase;
                        letter-spacing: .04em; white-space: nowrap; }
#pbEditItems td.num, #pbEditItems th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
#pbEditItems td.num, #pbEditItems th.num { width: 1%; }
#pbEditItems .pb-desc { width: auto; }
#pbEditItems input { width: 96px; max-width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: .18rem .35rem;
                     font-size: .8rem; text-align: right; font-variant-numeric: tabular-nums; }
#pbEditItems input.over { border-color: #ef4444; background: #fef2f2; }
#pbEditItems .pb-desc { min-width: 240px; white-space: pre-wrap; }
#pbEditItems tr.zero td { color: var(--muted); }
/* Description/Unit/Unit Cost — read-only text before, editable inputs now
   (requested 2026-09-09: a typo or a unit label carried over wrong from the
   PO shouldn't require reopening the PO itself to fix). Description is a
   <textarea>, not an <input>, same reasoning as the main Inspection modal's
   item grid (autoGrow()) — a spec is not a short label and should wrap
   rather than scroll sideways inside a fixed-width box. */
#pbEditItems .pb-desc-input {
    width: 100%; min-width: 220px; border: 1px solid transparent; background: transparent;
    padding: .18rem .3rem; border-radius: 6px; font-size: .8rem; text-align: left;
    resize: none; overflow: hidden; line-height: 1.35; display: block; min-height: 22px;
    font-family: inherit;
}
#pbEditItems .pb-unit-input { text-align: left; width: 64px; }
#pbEditItems .pb-desc-input:focus,
#pbEditItems .pb-unit-input:focus,
#pbEditItems .pb-cost-input:focus { border-color: var(--primary); background: #fff; outline: none; }
#pbEditItems .pb-qty-cell { display: flex; align-items: center; gap: .3rem; justify-content: flex-end; }
#pbEditItems .pb-qty-cell input.pb-qty { width: 76px; }
/* "Full" — one click to bill everything still outstanding on the line,
   instead of computing and typing the remaining figure by hand. For deliveries
   accepted as complete despite the measured quantity landing a little under
   the order (food and other weighed goods especially). */
.pb-fill-btn {
    border: 1px solid var(--primary); background: #eef4fe; color: var(--primary);
    border-radius: 6px; font-size: .68rem; font-weight: 600; padding: .2rem .4rem;
    white-space: nowrap; cursor: pointer; line-height: 1;
}
.pb-fill-btn:hover { background: var(--primary); color: #fff; }
.pb-remaining-hint { font-size: .68rem; color: var(--muted); }
.pb-empty { text-align: center; color: var(--muted); padding: 1.4rem; border: 1px dashed var(--border); border-radius: 10px; }

/* The inspect dialog specifically, matching .modal-po-wide and .modal-cb-wide
   on the other two screens. modal-xl's 1140px left the description column about
   260px wide once the five fixed columns had taken their share, which wrapped
   "Examination Table, Stainless Steel" onto three lines and a real
   specification onto twenty. */
.modal-insp-wide { max-width: min(1600px, 96vw); }

/* Header, icon badge and close button ported from .po-modal-header-v2 on the
   Purchase Order screen so the three big modals in this app open the same way.
   A plain white bar with a dark icon was the odd one out. */
.insp-modal-header {
    background: linear-gradient(135deg, var(--navy), #1e40af);
    color: #fff; border-bottom: none;
    padding: 1.15rem 1.4rem;
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

/* Each card's icon gets a tinted chip rather than sitting bare on white, and
   each section a colour of its own — the reference material, what the delivery
   came with, who signed for it. Four cards that look identical are four cards
   the eye has to read to tell apart. */
.po-card-title i {
    width: 26px; height: 26px; border-radius: 8px; flex: 0 0 auto;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .8rem; background: #e8f0ff; color: var(--primary);
}
.po-card.sec-order   .po-card-title i { background: #e8f0ff; color: #1d4ed8; }
.po-card.sec-receipt .po-card-title i { background: #fff2e6; color: #c2410c; }
.po-card.sec-people  .po-card-title i { background: #eef7ee; color: #15803d; }
.po-card.sec-accept  .po-card-title i { background: #f3ecff; color: #7c3aed; }
/* A hairline of the same colour along the top edge, so the grouping still
   reads once the card is scrolled and its title is off screen. */
.po-card.sec-order   { border-top: 3px solid #bfdbfe; }
.po-card.sec-receipt { border-top: 3px solid #fed7aa; }
.po-card.sec-people  { border-top: 3px solid #bbf7d0; }
.po-card.sec-accept  { border-top: 3px solid #ddd6fe; }


/* Inspection item grid. Quantity is editable because a delivery is routinely
   short — that is the whole point of an inspection — and the line total is
   recomputed from quantity x unit cost, never typed. */

.insp-items-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
.insp-items-table th {
    background: linear-gradient(180deg, #eaf1fd, #dfe9fa);
    font-size: .7rem; text-transform: uppercase; letter-spacing: .04em;
    color: var(--navy); font-weight: 700;
    padding: .5rem .55rem; text-align: left; white-space: nowrap;
    border-bottom: 1px solid #c7d8f5;
    position: sticky; top: 0; z-index: 1;
}
/* Zebra and hover, faint. With descriptions running to twenty lines the eye
   loses which quantity belongs to which item, and a tint that survives a
   glance is enough to hold the row together. */
.insp-items-table tbody tr:nth-child(even) { background: #fafcff; }
.insp-items-table tbody tr:hover { background: #eef4fe; }
.insp-items-table tbody td:first-child {
    color: var(--primary); font-weight: 700; font-size: .78rem; padding-top: .55rem;
}
.insp-items-table td.num:last-child input { color: var(--navy); font-weight: 600; }
/* Top, not middle: once a description runs to twenty lines the default
   vertical centring floats the row number, unit, quantity and costs into the
   middle of the cell, far from the item they belong to. Same reason the
   printed PO form top-aligns its grid. A single-line row looks identical
   either way, since the inputs set their own height. */
.insp-items-table td { padding: .25rem .35rem; border-bottom: 1px solid #eef2f7; vertical-align: top; }
.insp-items-table input {
    width: 100%; border: 1px solid transparent; background: transparent;
    padding: .3rem .4rem; border-radius: 6px; font-size: .84rem;
}
.insp-items-table textarea.item-desc {
    width: 100%; border: 1px solid transparent; background: transparent;
    padding: .3rem .4rem; border-radius: 6px; font-size: .84rem;
    resize: none; overflow: hidden; line-height: 1.35; display: block; min-height: 28px;
    font-family: inherit;
}
.insp-items-table input:focus,
.insp-items-table textarea.item-desc:focus { border-color: var(--primary); background: #fff; outline: none; }
.insp-items-table textarea.item-desc[readonly] { color: var(--muted); }
.insp-items-table input[readonly] { color: var(--muted); }
.insp-items-table td.num input { text-align: right; font-variant-numeric: tabular-nums; }
/* A heading row — "LOT 3" and the like — same rule and same look as the PO's
   own item grid (.po-item-title in purchase_orders.php): Unit/Quantity/Unit
   Cost/Total are always blank on a title row, so they are hidden instead of
   showing four empty boxes next to a delivered quantity of 0, which read as
   "LOT 3 was ordered and zero of it delivered" rather than what it actually
   is — a section label with nothing to deliver against. Detected the same
   way the PO grid detects it: blank unit and no quantity/cost/total. */
.ins-item-title > td { background: #f1f5f9; }
.ins-item-title input { display: none; }
.ins-item-title textarea.item-desc {
    font-weight: 700; color: var(--navy); background: #f1f5f9;
    border-color: transparent; box-shadow: none;
}
.ins-title-dash { display: none; color: #cbd5e1; text-align: center; font-size: .8rem; }
.ins-item-title .ins-title-dash { display: block; }
/* Both columns scroll inside themselves rather than growing the dialog. The
   max-heights are set in px by sizeInspModalColumns() from the modal body's
   real height, for the same reason the PO modal does it that way: a vh guess
   either clips a short form that would have fitted, or fails to cap a long one,
   because a vh percentage doesn't track the dialog's actual rendered height.
   min-height:0 is what lets a flex child actually scroll instead of stretching
   to fit its content. */
.insp-left-col-scroll { overflow-y: auto; min-height: 0; padding-right: .3rem; }
.insp-items-scroll { overflow-y: auto; min-height: 0; border: 1px solid #cddcf7; border-radius: 10px; }

/* A tinted canvas behind the cards, the same idea as the page's own --body-bg.
   White cards on a white modal body have nothing to stand away from, which is
   most of what made this dialog read as flat. */
#inspectModal .modal-body   { background: #f5f8fd; }
#inspectModal .modal-footer { background: #f5f8fd; border-top: 1px solid #dbe3ee; }
#inspectModal .po-card      { box-shadow: 0 1px 3px rgba(30,58,138,.06); }

.insp-total-row {
    display: flex; justify-content: flex-end; gap: 2rem; font-size: .88rem;
    margin-top: .6rem; padding: .55rem .9rem;
    background: linear-gradient(135deg, #eef4fe, #e6edfb);
    border: 1px solid #cddcf7; border-radius: 10px;
}

.insp-total-row .lbl { color: var(--muted); font-weight: 600; }
.insp-total-row .val { font-weight: 700; color: var(--navy); font-variant-numeric: tabular-nums; }

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
/* PO and PR numbers wear the same orange and blue pills they wear in the list
   behind this modal, so the two identifiers stay recognisable at a glance
   instead of becoming two more lines of grey text. */
.po-summary-strip .cell .v .num-pill { font-size: .8rem; }

.po-summary-strip .cell .k { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }
.po-summary-strip .cell .v { font-size: .87rem; font-weight: 600; color: var(--text); word-break: break-word; }
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
                <div class="page-title">Inspection &amp; Acceptance</div>
                <div class="page-subtitle">Record delivery inspection against a purchase order and print the acceptance report.</div>
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
                    <div class="stat-icon blue"><i class="fas fa-clipboard-check"></i></div>
                    <div>
                        <div class="stat-value" id="statToday">—</div>
                        <div class="stat-label">Inspected Today</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon teal"><i class="fas fa-calendar-days"></i></div>
                    <div>
                        <div class="stat-value" id="statMonth">—</div>
                        <div class="stat-label">Inspected This Month</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-peso-sign"></i></div>
                    <div>
                        <div class="stat-value" id="statAmount" style="font-size:1.15rem;">—</div>
                        <div class="stat-label">Accepted Value This Month</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon amber"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="stat-value" id="statAwaiting">—</div>
                        <div class="stat-label">Awaiting Inspection</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="po-card no-print">
            <div class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-3 po-field-group mb-0">
                    <label>Search</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="text" id="filterSearch" class="form-control" placeholder="PO/PR number, supplier, invoice"></div>
                </div>
                <div class="col-sm-6 col-md-2 po-field-group mb-0">
                    <label>From</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar"></i></span>
                    <input type="date" id="filterFrom" class="form-control"></div>
                </div>
                <div class="col-sm-6 col-md-2 po-field-group mb-0">
                    <label>To</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar"></i></span>
                    <input type="date" id="filterTo" class="form-control"></div>
                </div>
                <div class="col-sm-6 col-md-2 po-field-group mb-0">
                    <label>Status</label>
                    <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-filter"></i></span>
                    <select id="filterStatus" class="form-select">
                        <option value="">All</option>
                        <option value="pending">Awaiting inspection</option>
                        <option value="inspected">Inspected</option>
                        <option value="progress_billing">Progress billing</option>
                    </select></div>
                </div>
                <div class="col-sm-6 col-md-3 d-flex gap-2">
                    <button class="btn btn-primary-app flex-fill" id="btnFilter"><i class="fas fa-magnifying-glass me-1"></i> Filter</button>
                    <button class="btn btn-outline-secondary flex-fill" id="btnClear"><i class="fas fa-eraser me-1"></i> Clear</button>
                </div>
            </div>
        </div>

        <div class="po-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="po-card-title mb-0"><i class="fas fa-list-check"></i> Deliveries</div>
                <div class="d-flex gap-2 flex-wrap no-print" id="countChips">
                    <button type="button" class="po-chip active" data-status=""><span class="po-chip-num" id="countTotal">0</span> Total</button>
                    <button type="button" class="po-chip po-chip-pending" data-status="pending"><span class="po-chip-num" id="countPending">0</span> Awaiting</button>
                    <button type="button" class="po-chip po-chip-inspected" data-status="inspected"><span class="po-chip-num" id="countInspected">0</span> Inspected</button>
                    <button type="button" class="po-chip po-chip-billing" data-status="progress_billing"><span class="po-chip-num" id="countBilling">0</span> Progress Billing</button>
                </div>
            </div>
            <div class="table-responsive">
                <!-- .app-table is the shared table skin in css/base.css: uppercase,
                     letter-spaced, non-wrapping headers on a tinted band. Same
                     classes the Purchase Order list uses, so both tables match. -->
                <table class="app-table table table-hover table-bordered" id="inspectionTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>PO Number</th><th>PR Number</th><th>Supplier</th>
                            <th>Department</th><th>Purpose</th><th>PO Date</th><th>Invoice No.</th>
                            <th>Remarks</th>
                            <th>Status</th><th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inspectionBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<!-- ── Inspection modal ──────────────────────────────────────────────────
     One modal for record / view / revise, distinguished by inspMode, exactly
     as settings.php uses one modal for add and edit. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="inspectModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-insp-wide modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header insp-modal-header">
        <div class="insp-header-icon"><i class="fas fa-clipboard-check"></i></div>
        <div class="insp-header-text">
            <h5 class="modal-title" id="inspModalTitle">Inspect Delivery</h5>
            <small id="inspModalSubtitle">Check what arrived against the purchase order, then record who inspected and accepted it.</small>
        </div>
        <button type="button" class="insp-header-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="inspPoNumber">

        <div class="readonly-banner d-none" id="inspReadonlyBanner">
          <i class="fas fa-lock"></i>
          <span>This delivery is already inspected. Choose <strong>Revise</strong> to change it — the current version is kept as history.</span>
        </div>

        <!-- Two columns, same split as the Purchase Order modal: the form on
             the left, the item grid on the right where there is room for it.
             Each side scrolls inside itself so a long delivery never pushes
             Save off the bottom of the dialog. -->
        <div class="row g-4">
        <!-- 5/7 at every size. This was briefly 4/8 on wide screens, to give the
             item descriptions as much room as possible — but a twelfth of the
             dialog is about 128px, and taking it off this column left the name
             fields around 170px of text each, which cuts "TEOFILO ARANAIDO
             PIODOS JR" short even two-across. Handing it back makes them ~250px,
             which fits a full name outright, and still leaves the description
             more than twice the width it had before the dialog was widened.
             Names have no natural length limit and descriptions wrap; the
             column that cannot wrap is the one that gets the twelfth. -->
        <div class="col-lg-5">
        <div class="insp-left-col-scroll">

        <div class="po-card po-card-compact sec-order">
          <div class="po-card-title"><i class="fas fa-file-invoice"></i> Purchase Order</div>
          <div class="po-summary-strip">
            <div class="cell"><div class="k">PO Number</div><div class="v" id="sumPo">—</div></div>
            <div class="cell"><div class="k">PR Number</div><div class="v" id="sumPr">—</div></div>
            <div class="cell"><div class="k">Supplier</div><div class="v" id="sumSupplier">—</div></div>
            <div class="cell"><div class="k">Department</div><div class="v" id="sumDept">—</div></div>
            <div class="cell"><div class="k">Purpose</div><div class="v" id="sumPurpose">—</div></div>
          </div>
        </div>

        <div class="po-card po-card-compact sec-receipt">
          <div class="po-card-title"><i class="fas fa-receipt"></i> Receipt</div>
          <div class="row g-2 tight-field-row">
            <div class="col-md-4 po-field-group">
              <label>Invoice / Receipt No. <span class="text-danger">*</span></label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-hashtag"></i></span>
              <input type="text" id="inspInvoice" class="form-control"></div>
            </div>
            <div class="col-md-4 po-field-group">
              <label>Barcode Number</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-barcode"></i></span>
              <input type="text" id="inspBarcode" class="form-control"></div>
            </div>
            <div class="col-md-4 po-field-group">
              <label>Date BAC Received</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar-check"></i></span>
              <input type="datetime-local" id="inspBacDate" class="form-control"></div>
            </div>
            <!-- Date of Receipt: when the goods actually arrived. Suggested
                 from the PO's Delivery Term, never forced — the suggestion is
                 what the term promised, not what happened. -->
            <div class="col-md-6 po-field-group">
              <label>Date of Receipt</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-truck-ramp-box"></i></span>
              <input type="date" id="inspReceiptDate" class="form-control"></div>
              <div class="po-field-hint" id="inspReceiptHint"></div>
            </div>
            <div class="col-md-6 po-field-group d-flex align-items-end">
              <button type="button" class="btn btn-sm btn-outline-secondary w-100 d-none"
                      id="inspReceiptUseSuggestion"></button>
            </div>
            <div class="col-12 po-field-group">
              <label>Remarks</label>
              <div class="po-input-wrap"><span class="po-input-icon" style="top:19px;transform:none;"><i class="fas fa-comment-dots"></i></span>
              <textarea id="inspRemarks" class="form-control" rows="2" autocomplete="off"
                        oninput="renderRemarksSuggestions('inspRemarks', 'inspRemarksSuggestions', this.value)"
                        onfocus="renderRemarksSuggestions('inspRemarks', 'inspRemarksSuggestions', this.value)"
                        onblur="document.getElementById('inspRemarksSuggestions').classList.add('d-none')"></textarea>
              <div id="inspRemarksSuggestions" class="insp-remarks-suggest d-none"></div></div>
            </div>
          </div>
        </div>

        <div class="po-card po-card-compact sec-people">
          <div class="po-card-title"><i class="fas fa-user-check"></i> Inspection Officers <span class="text-danger">*</span></div>
          <div class="row g-2 tight-field-row">
            <div class="col-md-6 po-field-group">
              <label>Inspector 1</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
              <input type="text" id="inspBy1" class="form-control" list="dlInspectors1" autocomplete="off"></div>
              <div class="po-field-hint" id="inspBy1Hint"></div>
            </div>
            <div class="col-md-6 po-field-group">
              <label>Inspector 2</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
              <input type="text" id="inspBy2" class="form-control" list="dlInspectors2" autocomplete="off"></div>
              <div class="po-field-hint" id="inspBy2Hint"></div>
            </div>
            <div class="col-md-6 po-field-group">
              <label>Inspector 3</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
              <input type="text" id="inspBy3" class="form-control" list="dlInspectors3" autocomplete="off"></div>
              <div class="po-field-hint" id="inspBy3Hint"></div>
            </div>
            <div class="col-md-6 po-field-group">
              <label>Inspector 4</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
              <input type="text" id="inspBy4" class="form-control" list="dlInspectors4" autocomplete="off"></div>
              <div class="po-field-hint" id="inspBy4Hint"></div>
            </div>
          </div>
          <datalist id="dlInspectors1"></datalist>
          <datalist id="dlInspectors2"></datalist>
          <datalist id="dlInspectors3"></datalist>
          <datalist id="dlInspectors4"></datalist>
          <datalist id="dlRemarks"></datalist>
        </div>

        <div class="po-card po-card-compact mb-0 sec-accept">
          <div class="po-card-title"><i class="fas fa-people-carry-box"></i> Received &amp; Accepted</div>
          <div class="row g-2 tight-field-row">
            <div class="col-md-6 po-field-group"><label>End User 1</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user-tag"></i></span>
              <input type="text" id="inspEnd1" class="form-control" list="dlEndUsers" autocomplete="off" oninput="employeeSuggest(this, 'dlEndUsers')"></div></div>
            <div class="col-md-6 po-field-group"><label>End User 2</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user-tag"></i></span>
              <input type="text" id="inspEnd2" class="form-control" list="dlEndUsers" autocomplete="off" oninput="employeeSuggest(this, 'dlEndUsers')"></div></div>
            <div class="col-md-6 po-field-group"><label>End User 3</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user-tag"></i></span>
              <input type="text" id="inspEnd3" class="form-control" list="dlEndUsers" autocomplete="off" oninput="employeeSuggest(this, 'dlEndUsers')"></div></div>
            <div class="col-md-6 po-field-group"><label>End User 4</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user-tag"></i></span>
              <input type="text" id="inspEnd4" class="form-control" list="dlEndUsers" autocomplete="off" oninput="employeeSuggest(this, 'dlEndUsers')"></div></div>
            <div class="col-md-6 po-field-group"><label>End User 5</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user-tag"></i></span>
              <input type="text" id="inspEnd5" class="form-control" list="dlEndUsers" autocomplete="off" oninput="employeeSuggest(this, 'dlEndUsers')"></div></div>
            <div class="col-md-6 po-field-group"><label>Accepted By</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-signature"></i></span>
              <input type="text" id="inspAccepted" class="form-control" list="dlAcceptedBy" autocomplete="off"></div></div>
          </div>
          <datalist id="dlOffices"></datalist>
          <datalist id="dlAcceptedBy"></datalist>
          <datalist id="dlEndUsers"></datalist>
        </div>

        </div><!-- /.insp-left-col-scroll -->
        </div><!-- /.col-lg-5 -->

        <!-- Right column — the item grid, where the width actually is. -->
        <div class="col-lg-7">
        <div class="po-card po-card-compact h-100 d-flex flex-column mb-0 sec-order">
          <div class="po-card-title"><i class="fas fa-boxes-stacked"></i> Items Delivered</div>
          <div class="insp-items-scroll items-scroll-wrap">
            <table class="insp-items-table">
              <thead>
                <tr>
                  <th style="width:30px">#</th><th>Description</th><th style="width:66px">Unit</th>
                  <th style="width:82px">Quantity</th><th style="width:92px">Unit Cost</th>
                  <th style="width:104px">Total</th>
                </tr>
              </thead>
              <tbody id="inspItemsBody"></tbody>
            </table>
          </div>
          <div class="insp-total-row">
            <div><span class="lbl">Items</span> <span class="val" id="inspItemCount">0</span></div>
            <div><span class="lbl">Total</span> <span class="val" id="inspSubTotal">0.00</span></div>
          </div>
          <div class="po-field-hint">Quantity and unit cost are editable — short deliveries are recorded here. The line total is calculated, not typed.</div>
        </div>
        </div><!-- /.col-lg-7 -->
        </div><!-- /.row -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-secondary d-none" id="btnPrintInspection"><i class="fas fa-print me-1"></i> Print Report</button>
        <button type="button" class="btn btn-outline-danger d-none" id="btnUndoInspection"><i class="fas fa-rotate-left me-1"></i> Undo Last Save</button>
        <button type="button" class="btn btn-outline-primary d-none" id="btnReviseInspection"><i class="fas fa-pen me-1"></i> Revise</button>
        <button type="button" class="btn btn-primary-app" id="btnSaveInspection"><i class="fas fa-save me-1"></i> <span id="inspSaveLabel">Save Inspection</span></button>
      </div>
    </div>
  </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

<?php include 'profile_modal.php'; ?>
<!-- ── Progress billing ─────────────────────────────────────────────────────
     Its own dialog, deliberately: a progress-billing PO is delivered and paid
     in tranches, and putting that in front of every ordinary inspection would
     change a flow that works. The Inspect modal above is untouched.

     One dialog with two panes rather than two stacked modals — Bootstrap
     backdrops do not nest cleanly, and the editor needs the history's numbers
     on screen the moment it closes. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="pbModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-insp-wide modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header insp-modal-header pb-modal-header">
        <div class="insp-header-icon"><i class="fas fa-layer-group"></i></div>
        <div class="insp-header-text">
            <h5 class="modal-title pb-title-row"><span id="pbTitle">Progress Billing</span><span class="pb-status-pill" id="pbStatusPill"></span></h5>
            <small id="pbSubtitle">Deliveries and payments recorded in tranches against one purchase order.</small>
        </div>
        <button type="button" class="insp-header-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="pbPoNumber">

        <!-- ── Pane 1: history ─────────────────────────────────────────── -->
        <div id="pbHistoryPane">
          <!-- The flow, stated where it is acted on. This dialog is the whole
               inspection route for a progress-billing PO; the single Inspect
               button on the worklist is for orders delivered in one go. -->
          <div class="pb-flow-note">
            <i class="fas fa-circle-info"></i>
            <span>Each billing is a delivery in its own right — it records the invoice,
            the inspection officers, the end users and the quantities, and prints its own
            Inspection &amp; Acceptance Report. On a progress-billing purchase order this
            replaces the single <i class="fas fa-clipboard-check"></i> Inspect; using both
            counts the same money twice.</span>
          </div>
          <div class="po-card po-card-compact">
            <div class="po-card-title"><i class="fas fa-file-invoice"></i> Purchase Order</div>
            <div class="po-summary-strip">
              <div class="cell"><div class="k">PO Number</div><div class="v" id="pbSumPo">—</div></div>
              <div class="cell"><div class="k">PR Number</div><div class="v" id="pbSumPr">—</div></div>
              <div class="cell"><div class="k">Supplier</div><div class="v" id="pbSumSupplier">—</div></div>
              <div class="cell"><div class="k">Payment Term</div><div class="v" id="pbSumTerm">—</div></div>
            </div>
          </div>

          <div class="po-card po-card-compact">
            <div class="po-card-title"><i class="fas fa-chart-line"></i> Billed to date</div>
            <div class="pb-hero">
              <div class="cell"><div class="k">Purchase Order</div><div class="v" id="pbTotOrdered">—</div></div>
              <div class="cell"><div class="k">Billed</div><div class="v billed" id="pbTotBilled">—</div></div>
              <div class="cell"><div class="k">Remaining</div><div class="v remaining" id="pbTotRemaining">—</div></div>
              <div class="cell"><div class="k">Billings</div><div class="v" id="pbTotCount">—</div></div>
            </div>
            <div class="pb-bar" id="pbBar"><span style="width:0%"></span></div>
            <div class="pb-remaining-hint mt-1" id="pbBarHint"></div>
          </div>

          <div class="po-card po-card-compact">
            <div class="po-card-title"><i class="fas fa-list-ol"></i> Quantity per billing</div>
            <div class="pb-progress-scroll" id="pbProgressWrap"></div>
          </div>

          <div class="po-card po-card-compact">
            <div class="po-card-title"><i class="fas fa-clock-rotate-left"></i> History</div>
            <div id="pbHistory"></div>
          </div>
        </div>

        <!-- ── Pane 2: record / revise one billing ─────────────────────── -->
        <div id="pbEditorPane" class="d-none">
          <div class="readonly-banner" id="pbEditorBanner">
            <i class="fas fa-circle-info"></i>
            <span id="pbEditorBannerText"></span>
          </div>

          <div class="po-card po-card-compact">
            <div class="po-card-title"><i class="fas fa-calendar-week"></i> This billing</div>
            <div class="row g-2 tight-field-row">
              <div class="col-md-3 po-field-group">
                <label>Billing No. <span class="text-danger">*</span></label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-hashtag"></i></span>
                <select id="pbEditNo" class="form-select"></select></div>
              </div>
              <div class="col-md-3 po-field-group">
                <label>Period From</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar"></i></span>
                <input type="date" id="pbEditFrom" class="form-control"></div>
              </div>
              <div class="col-md-3 po-field-group">
                <label>Period To</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar"></i></span>
                <input type="date" id="pbEditTo" class="form-control"></div>
              </div>
              <div class="col-md-3 po-field-group">
                <label>Date Delivered</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-truck"></i></span>
                <input type="date" id="pbEditDelivery" class="form-control"></div>
              </div>
            </div>
          </div>

          <div class="po-card po-card-compact">
            <div class="po-card-title"><i class="fas fa-receipt"></i> Receipt</div>
            <div class="row g-2 tight-field-row">
              <div class="col-md-4 po-field-group">
                <label>Invoice / Receipt No. <span class="text-danger">*</span></label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-hashtag"></i></span>
                <input type="text" id="pbEditInvoice" class="form-control"></div>
              </div>
              <div class="col-md-4 po-field-group">
                <label>Barcode Number</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-barcode"></i></span>
                <input type="text" id="pbEditBarcode" class="form-control"></div>
              </div>
              <div class="col-md-4 po-field-group">
                <label>Date BAC Received</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar-check"></i></span>
                <input type="datetime-local" id="pbEditBac" class="form-control"></div>
              </div>
            </div>
          </div>

          <div class="po-card po-card-compact">
            <div class="po-card-title"><i class="fas fa-user-check"></i> Inspection Officers</div>
            <div class="row g-2 tight-field-row">
              <div class="col-md-3 po-field-group"><label>Inspector 1</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="pbEditBy1" class="form-control" list="dlPbInspectors1" autocomplete="off"></div></div>
              <div class="col-md-3 po-field-group"><label>Inspector 2</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="pbEditBy2" class="form-control" list="dlPbInspectors2" autocomplete="off"></div></div>
              <div class="col-md-3 po-field-group"><label>Inspector 3</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="pbEditBy3" class="form-control" list="dlPbInspectors3" autocomplete="off"></div></div>
              <div class="col-md-3 po-field-group"><label>Inspector 4</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="pbEditBy4" class="form-control" list="dlPbInspectors4" autocomplete="off"></div></div>
            </div>
            <datalist id="dlPbInspectors1"></datalist>
            <datalist id="dlPbInspectors2"></datalist>
            <datalist id="dlPbInspectors3"></datalist>
            <datalist id="dlPbInspectors4"></datalist>
          </div>

          <div class="po-card po-card-compact">
            <div class="po-card-title"><i class="fas fa-people-carry-box"></i> End Users &amp; Acceptance</div>
            <div class="row g-2 tight-field-row">
              <div class="col-md-4 po-field-group"><label>End User 1</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="pbEditEnd1" class="form-control" list="dlPbEndUsers" autocomplete="off" oninput="employeeSuggest(this, 'dlPbEndUsers')"></div></div>
              <div class="col-md-4 po-field-group"><label>End User 2</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="pbEditEnd2" class="form-control" list="dlPbEndUsers" autocomplete="off" oninput="employeeSuggest(this, 'dlPbEndUsers')"></div></div>
              <div class="col-md-4 po-field-group"><label>End User 3</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="pbEditEnd3" class="form-control" list="dlPbEndUsers" autocomplete="off" oninput="employeeSuggest(this, 'dlPbEndUsers')"></div></div>
              <div class="col-md-4 po-field-group"><label>End User 4</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="pbEditEnd4" class="form-control" list="dlPbEndUsers" autocomplete="off" oninput="employeeSuggest(this, 'dlPbEndUsers')"></div></div>
              <div class="col-md-4 po-field-group"><label>End User 5</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span>
                <input type="text" id="pbEditEnd5" class="form-control" list="dlPbEndUsers" autocomplete="off" oninput="employeeSuggest(this, 'dlPbEndUsers')"></div></div>
              <div class="col-md-4 po-field-group"><label>Accepted by</label>
                <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-signature"></i></span>
                <input type="text" id="pbEditAccepted" class="form-control" list="dlPbOffices" autocomplete="off"></div></div>
            </div>
            <datalist id="dlPbOffices"></datalist>
            <datalist id="dlPbEndUsers"></datalist>
            <div class="po-field-group mt-2"><label>Remarks</label>
              <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-comment-dots"></i></span>
              <input type="text" id="pbEditRemarks" class="form-control" list="dlRemarks" autocomplete="off"></div></div>
          </div>

          <div class="po-card po-card-compact">
            <div class="po-card-title"><i class="fas fa-boxes-stacked"></i> Quantities billed</div>
            <!-- Every purchase order line is listed, whether or not this tranche
                 covers it. Leaving a quantity at zero is how an item is left out
                 of this billing; nothing is deleted. -->
            <div class="pb-progress-scroll">
              <table id="pbEditItems">
                <thead><tr>
                  <th class="pb-desc">Item</th><th>Unit</th>
                  <th class="num">Ordered</th><th class="num">Remaining</th>
                  <th class="num">Qty this billing</th><th class="num">Unit Cost</th><th class="num">Total</th>
                </tr></thead>
                <tbody id="pbEditItemsBody"></tbody>
                <tfoot><tr>
                  <td colspan="6" class="num"><strong>Total this billing</strong></td>
                  <td class="num"><strong id="pbEditTotal">0.00</strong></td>
                </tr></tfoot>
              </table>
            </div>
            <div class="pb-remaining-hint mt-1" id="pbEditHint"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <span class="me-auto pb-remaining-hint" id="pbFooterNote"></span>
        <button type="button" class="btn btn-outline-secondary d-none" id="pbBtnBack">
          <i class="fas fa-arrow-left me-1"></i> Back to history</button>
        <button type="button" class="btn btn-outline-secondary" id="pbBtnClose" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary-app" id="pbBtnNew">
          <i class="fas fa-plus me-1"></i> Record billing</button>
        <button type="button" class="btn btn-primary-app d-none" id="pbBtnSave">
          <i class="fas fa-floppy-disk me-1"></i> Save billing</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Print: options ───────────────────────────────────────────────────────
     One field only. Everything else the report prints is already on the
     inspection record, and re-asking for it here would be a second place for
     it to be wrong. The initials are the exception: they are the parameter the
     legacy form asked for at print time, and they identify who ran the copy. -->
<div class="modal fade" id="iarPrintModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-print me-2"></i>Print Inspection &amp; Acceptance Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="po-summary-strip mb-3">
            <div class="cell"><div class="k">PO Number</div><div class="v"><span class="num-pill po-num-pill" id="iarPrintPo"></span></div></div>
            <div class="cell"><div class="k">Supplier</div><div class="v" id="iarPrintSupplier">—</div></div>
        </div>
        <div class="po-field-group">
            <label>Initials <span class="text-danger">*</span></label>
            <input type="text" id="iarInitial" class="form-control" maxlength="12" autocomplete="off" placeholder="e.g. JMR" required>
            <div class="po-field-hint">Printed small beside &ldquo;***nothing follows***&rdquo;, exactly where the
                legacy report put it. Remembered for next time.</div>
        </div>
        <div class="po-field-group">
            <label>Blank End User / Warehouse lines</label>
            <input type="number" id="iarBlankEndUsers" class="form-control" min="0" max="5" step="1" value="0" style="max-width:110px">
            <div class="po-field-hint">For when the end user isn&rsquo;t known yet and will sign by hand at the
                counter &mdash; adds this many blank signature blocks (label and ruled lines, no name) without
                typing anything into End User 1&ndash;5. Capped at 5 blocks total on the printed row.</div>
        </div>
        <div id="iarPrintWarn" class="po-field-hint po-field-hint-warn mt-2"></div>
        <!-- Lines with nothing delivered are left off the printout. Saying so
             here, before the preview, so a shorter report than expected is an
             answered question rather than a suspected bug. -->
        <div id="iarOmitNote" class="po-field-hint mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary-app" id="btnIarPreview"><i class="fas fa-file-pdf me-1"></i> Preview</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Print: PDF preview ───────────────────────────────────────────────────
     A real PDF in an iframe, not an HTML page pretending to be one, so what is
     on screen is exactly what the printer receives. -->
<div class="modal fade" id="iarPreviewModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-file-pdf me-2"></i>Inspection &amp; Acceptance Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="pp-toolbar">
            <div class="pp-tool">
                <label for="iarPaperSize">Paper</label>
                <select id="iarPaperSize" class="form-select form-select-sm">
                    <option value="auto">Auto — fewest pages</option>
                    <option value="a4">A4</option>
                    <option value="long">Long / Folio</option>
                    <option value="legal">Legal</option>
                    <option value="letter">Letter</option>
                </select>
            </div>
            <div class="pp-tool">
                <label for="iarTextSize">Item text</label>
                <select id="iarTextSize" class="form-select form-select-sm">
                    <option value="0">Normal</option>
                    <option value="1">Larger (+1)</option>
                    <option value="2">Largest (+2)</option>
                </select>
            </div>
            <div class="pp-layout-status" id="iarLayoutStatus"></div>
        </div>
        <div id="iarPreviewLoading" class="text-center py-5"></div>
        <iframe id="iarPreviewFrame" class="pdf-preview-frame d-none" title="Report preview"></iframe>
        <!-- The sheet is built here, off-screen, and photographed from here. -->
        <div class="iar-offscreen"><div id="iarSheetArea"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-secondary" id="iarOpenTab" disabled><i class="fas fa-up-right-from-square me-1"></i> Open in Tab</button>
        <button type="button" class="btn btn-outline-primary" id="iarDownload" disabled><i class="fas fa-download me-1"></i> Download</button>
        <button type="button" class="btn btn-primary-app" id="iarPrint" disabled><i class="fas fa-print me-1"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Disbursement Voucher: options ────────────────────────────────────────
     The nine values Voucher_Print.vb asked for, in the order that form asked
     for them. Six arrive filled in — the legacy dialog pre-filled five of them
     the same way and left Office/Unit/Project blank even though the PO has
     always carried it. The amount is not a field: it is the sum of the accepted
     lines, and a voucher whose figure can be typed over is a voucher that can
     disagree with the report stapled behind it. -->
<div class="modal fade" id="dvModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-money-check-dollar me-2"></i>Disbursement Voucher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="po-summary-strip mb-3">
            <div class="cell"><div class="k">PO Number</div><div class="v"><span class="num-pill po-num-pill" id="dvPo"></span></div></div>
            <div class="cell"><div class="k">Covers</div><div class="v" id="dvScope">—</div></div>
            <div class="cell"><div class="k">Amount</div><div class="v" id="dvAmount">—</div></div>
        </div>

        <div class="row g-2">
            <div class="col-md-7">
                <div class="po-field-group"><label>Payee</label>
                    <input type="text" id="dvPayee" class="form-control"></div>
            </div>
            <div class="col-md-5">
                <div class="po-field-group"><label>TIN</label>
                    <input type="text" id="dvTin" class="form-control" autocomplete="off" placeholder="Written on by hand if left blank">
                </div>
            </div>
            <div class="col-md-7">
                <div class="po-field-group"><label>Address</label>
                    <input type="text" id="dvAddress" class="form-control"></div>
            </div>
            <div class="col-md-5">
                <div class="po-field-group"><label>Office / Unit / Project</label>
                    <input type="text" id="dvDepartment" class="form-control"></div>
            </div>
        </div>

        <hr class="my-3">

        <div class="po-field-group">
            <label>Category</label>
            <input type="text" id="dvCategory" class="form-control" list="dvCategoryList" autocomplete="off"
                   placeholder="e.g. OFFICE SUPPLIES">
            <datalist id="dvCategoryList"></datalist>
            <div class="po-field-hint">Prints as &ldquo;To payment of &hellip;&rdquo;. Suggestions come from Master Data &rarr; Categories;
                you can still type anything.</div>
        </div>
        <div class="po-field-group">
            <label>Explanation</label>
            <textarea id="dvExplanation" class="form-control" rows="3"
                      placeholder="e.g. FOR USE IN THE OPERATION OF CITY ENRO"></textarea>
            <div class="po-field-hint" id="dvRememberHint"></div>
        </div>

        <hr class="my-3">

        <div class="row g-2">
            <div class="col-md-4">
                <div class="po-field-group"><label>City Accountant</label>
                    <input type="text" id="dvAccountant" class="form-control" list="dvSignatoryList" autocomplete="off"></div>
            </div>
            <div class="col-md-4">
                <div class="po-field-group"><label>City Treasurer</label>
                    <input type="text" id="dvTreasurer" class="form-control" list="dvSignatoryList" autocomplete="off"></div>
            </div>
            <div class="col-md-4">
                <div class="po-field-group"><label>City Mayor</label>
                    <input type="text" id="dvMayor" class="form-control" list="dvSignatoryList" autocomplete="off"></div>
            </div>
            <datalist id="dvSignatoryList"></datalist>
        </div>
        <div id="dvSignWarn" class="po-field-hint po-field-hint-warn mt-1"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-outline-danger" id="btnDvReimbursement"><i class="fas fa-receipt me-1"></i> Reimbursement Print</button>
        <button type="button" class="btn btn-primary-app" id="btnDvPreview"><i class="fas fa-file-pdf me-1"></i> Preview</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Disbursement Voucher: PDF preview ────────────────────────────────────
     Same pipeline, same paper control and the same three buttons as the
     Inspection &amp; Acceptance preview above it. -->
<div class="modal fade" id="dvPreviewModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-file-pdf me-2"></i>Disbursement Voucher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="pp-toolbar">
            <div class="pp-tool">
                <label for="dvPaperSize">Paper</label>
                <select id="dvPaperSize" class="form-select form-select-sm">
                    <option value="auto">Auto — fewest pages</option>
                    <option value="a4">A4</option>
                    <option value="long">Long / Folio</option>
                    <option value="legal">Legal</option>
                    <option value="letter">Letter</option>
                </select>
            </div>
            <div class="pp-tool">
                <label for="dvTextSize">Text</label>
                <select id="dvTextSize" class="form-select form-select-sm">
                    <option value="0">Normal</option>
                    <option value="1">Larger (+1)</option>
                    <option value="2">Largest (+2)</option>
                </select>
            </div>
            <div class="pp-layout-status" id="dvLayoutStatus"></div>
        </div>
        <div id="dvPreviewLoading" class="text-center py-5"></div>
        <iframe id="dvPreviewFrame" class="pdf-preview-frame d-none" title="Disbursement Voucher preview"></iframe>
        <div class="iar-offscreen"><div id="dvSheetArea"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-secondary" id="dvOpenTab" disabled><i class="fas fa-up-right-from-square me-1"></i> Open in Tab</button>
        <button type="button" class="btn btn-outline-primary" id="dvDownload" disabled><i class="fas fa-download me-1"></i> Download</button>
        <button type="button" class="btn btn-primary-app" id="dvPrint" disabled><i class="fas fa-print me-1"></i> Print</button>
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
<script src="<?= asset('js/iar-print.js') ?>"></script>
<!-- After iar-print.js, not before: the voucher reuses its amount-in-words
     formula rather than carrying a second copy that could drift from it. -->
<script src="<?= asset('js/dv-print.js') ?>"></script>
<script>
const apiUrl = 'api/inspection.php';
let inspTable = null;
let inspMode = 'create';          // create | view | revise
let inspectorList = [];
let officeList = [];

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
function money(v) {
    const n = parseFloat(v) || 0;
    return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtDate(v) {
    if (!v || String(v).startsWith('0000')) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    return isNaN(d) ? '—' : d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}
// Identical to the Purchase Order page's toast so both screens behave the same.
function showToast(msg, type = 'success') {
    const bg = type === 'success' ? 'bg-success' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-danger');
    const el = document.createElement('div');
    el.className = `toast align-items-center text-white ${bg} border-0`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(msg)}</div>`
                 + `<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    document.getElementById('toastContainer').appendChild(el);
    new bootstrap.Toast(el, { delay: 4000 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

// ── Date of Receipt, suggested from the Delivery Term ──────────────────────
//
// The term is free text on the purchase order and the live data shows why that
// matters: "10 Working Days" is the common case, but "As Needed Working Days",
// "immediately Working Days", "Weekly", "per schedule" and even "Dec. 23, 2022
// Working Days" are all really on file. Anything that does not start with a
// number gets no suggestion at all — a made-up date on a receipt is worse than
// an empty box, because the empty box gets asked about.
//
// "Working Days" steps over Saturdays and Sundays; a term that says "Calendar
// Days" adds plain calendar days. Public holidays are NOT modelled: the office
// keeps no holiday table, and guessing them would make the suggestion wrong in
// a way nobody could see.
function receiptSuggestion(po) {
    const term = String((po && po.delivery_term) || '').trim();
    const m = term.match(/^\s*(\d+)/);
    if (!m) return null;

    const days = parseInt(m[1], 10);
    const base = po.date_po || po.date_created;
    const t = Date.parse(String(base || '').replace(' ', 'T'));
    if (isNaN(t)) return null;

    const calendar = /calendar/i.test(term);
    const d = new Date(t);
    if (calendar) {
        d.setDate(d.getDate() + days);
    } else {
        let left = days;
        while (left > 0) {
            d.setDate(d.getDate() + 1);
            const wd = d.getDay();
            if (wd !== 0 && wd !== 6) left--;   // 0 Sun, 6 Sat
        }
    }
    return {
        iso: d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0')
           + '-' + String(d.getDate()).padStart(2, '0'),
        days, term, calendar,
    };
}

let receiptSuggested = null;
// The record the dialog is currently showing, kept so pressing Revise can
// re-run the fill: view mode deliberately leaves the box empty, and switching
// to revise has to be able to offer the suggestion after all.
let receiptContext = null;

function applyReceiptSuggestion(po, existing) {
    receiptContext = { po: po, existing: existing };
    const field = document.getElementById('inspReceiptDate');
    const hint  = document.getElementById('inspReceiptHint');
    const btn   = document.getElementById('inspReceiptUseSuggestion');
    receiptSuggested = receiptSuggestion(po);

    // A saved date always wins. The suggestion only ever fills a blank field,
    // because overwriting a recorded receipt date with a computed one would be
    // a silent edit to a fact somebody entered on purpose.
    //
    // And it only fills it while the record is being WRITTEN. This dialog in
    // view mode says "What was recorded when this delivery was inspected", so
    // putting a computed date in that box would be the dialog telling a lie:
    // every one of the ~16,700 inspections saved before this column existed
    // holds no receipt date, and each would appear to have one.
    const saved = existing && existing.delivery_date
        ? String(existing.delivery_date).slice(0, 10) : '';
    field.value = saved || (inspMode === 'view' ? '' : (receiptSuggested ? receiptSuggested.iso : ''));

    const term = String((po && po.delivery_term) || '').trim();
    if (inspMode === 'view') {
        hint.textContent = saved ? '' : 'No receipt date was recorded on this inspection.';
        btn.classList.add('d-none');
        return;
    }
    if (receiptSuggested) {
        hint.textContent = 'Suggested from the Delivery Term (' + term + '): '
            + receiptSuggested.days + ' '
            + (receiptSuggested.calendar ? 'calendar' : 'working') + ' days after the PO date. '
            + 'Change it to the date the goods actually arrived.';
        btn.textContent = 'Use suggested ' + fmtDate(receiptSuggested.iso);
        btn.classList.remove('d-none');
    } else {
        hint.textContent = term
            ? 'The Delivery Term (' + term + ') is not a number of days, so there is nothing to '
              + 'compute from — enter the receipt date yourself.'
            : 'This purchase order has no Delivery Term, so there is nothing to suggest from.';
        btn.classList.add('d-none');
    }
}

// ── List ───────────────────────────────────────────────────────────────────
async function loadList() {
    const params = new URLSearchParams({
        search: document.getElementById('filterSearch').value.trim(),
        from:   document.getElementById('filterFrom').value,
        to:     document.getElementById('filterTo').value,
        status: document.getElementById('filterStatus').value,
    });
    try {
        const res = await fetch(`${apiUrl}?${params}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load deliveries.', 'danger');
        renderCounts(data.counts);
        renderRows(data.orders || []);
    } catch (err) {
        showToast('Unable to load deliveries.', 'danger');
        console.error(err);
    }
}

function renderCounts(c) {
    if (!c) return;
    document.getElementById('countTotal').textContent     = c.total || 0;
    document.getElementById('countPending').textContent   = c.pending || 0;
    document.getElementById('countInspected').textContent = c.inspected || 0;
    document.getElementById('countBilling').textContent   = c.progress_billing || 0;
}

function renderRows(rows) {
    const body = document.getElementById('inspectionBody');
    if (inspTable) { inspTable.destroy(); inspTable = null; }
    body.innerHTML = rows.map(r => {
        // Awaiting means nobody has accounted for this delivery yet. A purchase
        // order with billings on file has been accounted for, so it drops the
        // Awaiting badge and lets the billing badge speak — "Awaiting" next to
        // "Progress Billing Completed" said two opposite things at once.
        // r.awaiting comes from the API, which is also what the chips and the
        // filter count, so the three cannot drift apart.
        const baseBadge = r.inspected
            ? '<span class="status-badge status-inspected"><i class="fas fa-check"></i> Inspected</span>'
            : r.awaiting
                ? '<span class="status-badge status-pending"><i class="fas fa-hourglass-half"></i> Awaiting</span>'
                : '';
        // A progress-billing PO can still carry BOTH: it may also be inspected
        // the ordinary way. The count is how many tranches are on file, not how
        // many are left.
        const billingBadge = r.progress_billing ? pbRowBadge(r) : '';
        const badge = `<span class="status-stack">${baseBadge}${billingBadge}</span>`;
        // Undo sits beside View, on inspected rows only — the wrong-PO mistake
        // is spotted from the list, so it is fixable from the list.
        // On a progress-billing PO the tranches ARE the deliveries — each one
        // records its own invoice, officers and quantities and prints its own
        // report — so Billings is the button that should look like the action
        // and the single Inspect is the unusual choice. Styling only: both
        // still work, because a PO can legitimately need both.
        const inspectIsPrimary = !r.progress_billing;
        const act = r.inspected
            ? `<button class="btn btn-sm btn-outline-primary" onclick="openInspection('${escapeHtml(r.po_number)}','view')" title="View"><i class="fas fa-eye"></i></button>`
              + `<button class="btn btn-sm btn-outline-secondary" onclick="openIarPrint('${escapeHtml(r.po_number)}')" title="Print the Inspection &amp; Acceptance Report"><i class="fas fa-print"></i></button>`
              // The voucher is the paper that follows acceptance, so it sits
              // next to the report it is stapled to. Inspected rows only: the
              // amount it certifies is the sum of the ACCEPTED lines, which do
              // not exist until somebody has inspected.
              + `<button class="btn btn-sm btn-outline-secondary" onclick="openVoucher('${escapeHtml(r.po_number)}')" title="Disbursement Voucher"><i class="fas fa-money-check-dollar"></i></button>`
              + `<button class="btn btn-sm btn-outline-danger" onclick="undoInspection('${escapeHtml(r.po_number)}')" title="Undo last save"><i class="fas fa-rotate-left"></i></button>`
            : `<button class="btn btn-sm ${inspectIsPrimary ? 'btn-primary-app' : 'btn-outline-secondary'}"`
              + ` onclick="openInspection('${escapeHtml(r.po_number)}','create')"`
              + ` title="${inspectIsPrimary ? 'Inspect'
                  : 'Record one single inspection for the whole purchase order — normally not what a progress-billing PO wants'}">`
              + `<i class="fas fa-clipboard-check"></i></button>`;
        // Shown only on progress-billing purchase orders, so the ordinary
        // worklist looks and behaves exactly as it did.
        const billingAct = r.progress_billing
            ? `<button class="btn btn-sm ${r.inspected ? 'btn-outline-primary' : 'btn-primary-app'}"`
              + ` onclick="openBillings('${escapeHtml(r.po_number)}')"`
              + ` title="Progress billing — record and print each delivery tranche">`
              + `<i class="fas fa-layer-group"></i></button>`
            : '';
        return `<tr>
            <td><span class="num-pill po-num-pill">${escapeHtml(r.po_number)}</span></td>
            <td>${r.pr_number ? `<span class="num-pill pr-num-pill">${escapeHtml(r.pr_number)}</span>` : '<span class="text-muted">—</span>'}</td>
            <td>${escapeHtml(r.supplier_name) || '<span class="text-muted">—</span>'}</td>
            <td>${escapeHtml(r.department) || '<span class="text-muted">—</span>'}</td>
            <td>${r.purpose ? `<span class="insp-purpose-cell">${escapeHtml(r.purpose)}</span>` : '<span class="text-muted">—</span>'}</td>
            <td>${fmtDate(r.date_created)}</td>
            <td>${escapeHtml(r.invoice_number) || '<span class="text-muted">—</span>'}</td>
            <td>${r.remarks ? `<span class="insp-remarks-cell" title="${escapeHtml(r.remarks)}">${escapeHtml(r.remarks)}</span>` : '<span class="text-muted">—</span>'}</td>
            <td>${badge}</td>
            <td class="no-print"><div class="table-actions">${act}${billingAct}</div></td>
        </tr>`;
    }).join('');

    inspTable = $('#inspectionTable').DataTable({
        pageLength: 10, lengthMenu: [10, 25, 50, 100], order: [],
        language: { search: '', searchPlaceholder: 'Search table…' },
    });
}

// The worklist badge for a progress-billing purchase order: how far through it
// the billings have got, at a glance.
//
// Reads r.billing_status, which api/inspection.php gets from
// progressBillingStatus() in config.php — the SAME call behind the dialog's
// pill. Nothing here re-decides what "complete" means; a badge and a dialog
// giving different answers about one purchase order is the failure this avoids.
function pbRowBadge(r) {
    const n = r.billing_count || 0;
    const tallies = n + (n === 1 ? ' billing' : ' billings');
    let text, cls = '';
    switch (r.billing_status) {
        case 'complete':   text = 'Completed';                       cls = ' done'; break;
        case 'over':       text = pbPct(r.billed_percent) + '%';     cls = ' over'; break;
        case 'partial':    text = pbPct(r.billed_percent) + '%';                    break;
        // Billed, but the purchase order carries no amount to measure against.
        case 'unmeasured': text = tallies;                                          break;
        default:           text = 'Not started';                     cls = ' idle'; break;
    }
    // The count stays visible on the tooltip rather than in the badge: the
    // percentage is what was asked for, and "Progress Billing · 2 · 68.7%" is
    // two numbers competing for the same glance.
    return `<span class="status-badge status-billing${cls}" title="${escapeHtml(r.payment_term || '')} — ${escapeHtml(tallies)}">`
         + `<i class="fas fa-layer-group"></i>`
         + `<span class="pb-badge-txt">Progress Billing<b>${escapeHtml(text)}</b></span></span>`;
}

function setStatusFilter(status) {
    document.getElementById('filterStatus').value = status;
    document.querySelectorAll('#countChips .po-chip').forEach(c =>
        c.classList.toggle('active', (c.dataset.status || '') === status));
    loadList();
}

// ── Modal ──────────────────────────────────────────────────────────────────
async function openInspection(poNumber, mode) {
    try {
        const res = await fetch(`${apiUrl}?po_number=${encodeURIComponent(poNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load.', 'danger');

        // A progress-billing PO is delivered in tranches, and each tranche is
        // already a full inspection of itself. Recording ONE inspection for the
        // whole order as well counts the same money twice in anything that adds
        // both up. Said once, here, rather than left to be discovered in a
        // report — and it is a steer, not a block: the operator can go ahead.
        //
        // Only on 'create'. Opening, printing or revising an inspection that
        // already exists is a decision that was made long ago.
        if (mode === 'create' && data.po && data.po.is_progress_billing) {
            const proceed = confirm(
                poNumber + ' is on Progress Billing.\n\n'
              + 'Deliveries on this purchase order are normally recorded one tranche at a '
              + 'time under Billings, which already captures the invoice, the officers and '
              + 'the quantities, and prints its own Inspection & Acceptance Report.\n\n'
              + 'Recording a single inspection for the whole order as well would count the '
              + 'same money twice.\n\n'
              + 'OK  —  open Billings instead\n'
              + 'Cancel  —  record one inspection for the whole order anyway');
            if (proceed) return openBillings(poNumber);
        }

        inspMode = mode;
        const po = data.po, insp = data.inspection;

        document.getElementById('inspPoNumber').value = po.po_number;
        // innerHTML, not textContent, so the two identifiers keep the orange
        // and blue pills they wear in the list behind this modal. Both values
        // are escaped first — they are supplier-facing free text on the PO.
        document.getElementById('sumPo').innerHTML = po.po_number
            ? `<span class="num-pill po-num-pill">${escapeHtml(po.po_number)}</span>` : '—';
        document.getElementById('sumPr').innerHTML = po.pr_number
            ? `<span class="num-pill pr-num-pill">${escapeHtml(po.pr_number)}</span>` : '—';
        document.getElementById('sumSupplier').textContent = po.supplier_name || '—';
        document.getElementById('sumDept').textContent     = po.department || '—';
        document.getElementById('sumPurpose').textContent  = po.purpose || '—';

        document.getElementById('inspInvoice').value  = insp ? (insp.invoice_number || '') : '';
        document.getElementById('inspBarcode').value  = insp ? (insp.barcode_number || '') : '';
        document.getElementById('inspRemarks').value  = insp ? (insp.remarks || '') : '';
        document.getElementById('inspBacDate').value  = toLocalInput(insp ? insp.date_bac_received : null);
        applyReceiptSuggestion(po, insp);

        // NOT defaulted to the signed-in user any more — requested 2026-09-09.
        // Whoever runs the inspection is very often not the end user (an
        // Inspection User checking a delivery for a different department, for
        // instance), so pre-filling their own name here was wrong more often
        // than it was right, and easy to miss clearing before saving.
        document.getElementById('inspEnd1').value = insp ? (insp.end_user1 || '') : '';
        ['2','3','4','5'].forEach(n => {
            document.getElementById('inspEnd' + n).value = insp ? (insp['end_user' + n] || '') : '';
        });
        ['1','2','3','4'].forEach(n => {
            document.getElementById('inspBy' + n).value = insp ? (insp['inspected_by_' + n] || '') : '';
        });
        document.getElementById('inspAccepted').value = insp ? (insp.accepted_by || '') : 'DIOSCORA R. NIERE, CPA';

        renderItems(data.items || []);
        syncInspectors();   // filter the pickers and flag any duplicate already on file
        syncNameTitles();
        applyMode();
        openModal('inspectModal');
    } catch (err) {
        showToast('Unable to load the delivery.', 'danger');
        console.error(err);
    }
}

function toLocalInput(v) {
    const d = v && !String(v).startsWith('0000') ? new Date(String(v).replace(' ', 'T')) : new Date();
    if (isNaN(d)) return '';
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function applyMode() {
    const readOnly = inspMode === 'view';
    document.getElementById('inspModalTitle').textContent =
        inspMode === 'create' ? 'Inspect Delivery' : inspMode === 'view' ? 'Inspection Record' : 'Revise Inspection';
    document.getElementById('inspModalSubtitle').textContent =
        inspMode === 'create' ? 'Check what arrived against the purchase order, then record who inspected and accepted it.'
      : inspMode === 'view'   ? 'What was recorded when this delivery was inspected.'
      :                         'Saving keeps the current version on file and files this as a new one.';
    document.getElementById('inspSaveLabel').textContent =
        inspMode === 'revise' ? 'Save Revision' : 'Save Inspection';
    document.getElementById('inspReadonlyBanner').classList.toggle('d-none', !readOnly);
    document.getElementById('btnReviseInspection').classList.toggle('d-none', !readOnly);
    // Only on a record that exists, and only while viewing it — there is
    // nothing to undo on a form that has not been saved yet.
    document.getElementById('btnUndoInspection').classList.toggle('d-none', !readOnly || inspMode === 'create');
    document.getElementById('btnPrintInspection').classList.toggle('d-none', !readOnly || inspMode === 'create');
    document.getElementById('btnSaveInspection').classList.toggle('d-none', readOnly);

    document.querySelectorAll('#inspectModal input, #inspectModal textarea').forEach(el => {
        if (el.type === 'hidden') return;
        if (el.dataset.calculated === '1') { el.readOnly = true; return; }
        el.readOnly = readOnly;
    });
    // readOnly stops typing but not a script setting .value, so the suggestion
    // button has to go as well while the record is only being viewed.
    document.getElementById('inspReceiptUseSuggestion').classList.toggle(
        'd-none', readOnly || !receiptSuggested);
    document.getElementById('inspReceiptHint').classList.toggle('d-none', readOnly);
}

// Same test the PO item grid uses (looksLikeTitleRow() in purchase_orders.php):
// a description with nothing else — no unit, no quantity, no cost, no total.
// That shape is how a LOT/section heading has always been stored (no schema
// change, no is_title column here either), so a PO's "LOT 3" heading arrives
// with exactly this shape and now renders as one here too.
function looksLikeTitleRow(it) {
    return String(it.items_description || '').trim() !== ''
        && String(it.unit || '').trim() === ''
        && !(parseFloat(it.quantity) || 0)
        && !(parseFloat(it.unit_cost) || 0)
        && !(parseFloat(it.total_cost) || 0);
}

function renderItems(items) {
    const body = document.getElementById('inspItemsBody');
    body.innerHTML = items.map((it, i) => {
        const isTitle = looksLikeTitleRow(it);
        return `
        <tr class="${isTitle ? 'ins-item-title' : ''}">
          <td class="text-muted">${i + 1}</td>
          <td><textarea rows="1" class="item-desc" data-f="items_description" oninput="autoGrow(this)">${escapeHtml(it.items_description)}</textarea></td>
          <td><input type="text" value="${escapeHtml(it.unit)}" data-f="unit"><span class="ins-title-dash">&mdash;</span></td>
          <td class="num"><input type="number" step="0.001" min="0" value="${parseFloat(it.quantity) || 0}" data-f="quantity" oninput="recalcRow(this)"><span class="ins-title-dash">&mdash;</span></td>
          <td class="num"><input type="number" step="0.01" min="0" value="${parseFloat(it.unit_cost) || 0}" data-f="unit_cost" oninput="recalcRow(this)"><span class="ins-title-dash">&mdash;</span></td>
          <td class="num"><input type="text" value="${money(it.total_cost)}" data-f="total_cost" data-calculated="1" readonly><span class="ins-title-dash">&mdash;</span></td>
        </tr>`;
    }).join('');
    body.querySelectorAll('tr').forEach((tr, i) => { tr.dataset.itemId = items[i].item_id || ''; });
    body.querySelectorAll('textarea.item-desc').forEach(autoGrow);
    recalcTotals();
}

// Item descriptions are long procurement specs, not short labels, so the field
// grows to fit rather than truncating mid-word — same treatment as the PO
// modal's item grid.
function autoGrow(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

function recalcRow(el) {
    const tr = el.closest('tr');
    const q = parseFloat(tr.querySelector('[data-f="quantity"]').value) || 0;
    const c = parseFloat(tr.querySelector('[data-f="unit_cost"]').value) || 0;
    tr.querySelector('[data-f="total_cost"]').value = money(q * c);
    recalcTotals();
}

function recalcTotals() {
    const rows = document.querySelectorAll('#inspItemsBody tr');
    let total = 0;
    rows.forEach(tr => {
        const q = parseFloat(tr.querySelector('[data-f="quantity"]').value) || 0;
        const c = parseFloat(tr.querySelector('[data-f="unit_cost"]').value) || 0;
        total += q * c;
    });
    document.getElementById('inspItemCount').textContent = rows.length;
    document.getElementById('inspSubTotal').textContent = money(total);
}

function collectItems() {
    return Array.from(document.querySelectorAll('#inspItemsBody tr')).map(tr => ({
        item_id:           tr.dataset.itemId || '',
        items_description: tr.querySelector('[data-f="items_description"]').value.trim(),
        unit:              tr.querySelector('[data-f="unit"]').value.trim(),
        quantity:          parseFloat(tr.querySelector('[data-f="quantity"]').value) || 0,
        unit_cost:         parseFloat(tr.querySelector('[data-f="unit_cost"]').value) || 0,
    }));
}

async function saveInspection() {
    const btn = document.getElementById('btnSaveInspection');
    const payload = {
        po_number:        document.getElementById('inspPoNumber').value,
        invoice_number:   document.getElementById('inspInvoice').value.trim(),
        barcode_number:   document.getElementById('inspBarcode').value.trim(),
        date_bac_received:document.getElementById('inspBacDate').value,
        delivery_date:    document.getElementById('inspReceiptDate').value,
        remarks:          document.getElementById('inspRemarks').value.trim(),
        accepted_by:      document.getElementById('inspAccepted').value.trim(),
        items:            collectItems(),
    };
    ['1','2','3','4'].forEach(n => payload['inspected_by_' + n] = document.getElementById('inspBy' + n).value.trim());
    ['1','2','3','4','5'].forEach(n => payload['end_user' + n] = document.getElementById('inspEnd' + n).value.trim());

    btn.disabled = true;
    try {
        const res = await fetch(apiUrl, {
            method: inspMode === 'revise' ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Could not save.', 'danger');
        showToast(data.message || 'Saved.', 'success');
        bootstrap.Modal.getInstance(document.getElementById('inspectModal')).hide();
        loadList();
        loadStats();
    } catch (err) {
        showToast('Could not save the inspection.', 'danger');
        console.error(err);
    } finally {
        btn.disabled = false;
    }
}

// ── Undo ───────────────────────────────────────────────────────────────────
// Takes back the last save. Because every save in this module appends a new
// version rather than overwriting the old one, that means one of two things,
// and which one depends entirely on whether this PO has been inspected once or
// several times:
//
//   • inspected once  -> the inspection comes off the purchase order entirely
//                        and it returns to the Awaiting list. This is the
//                        "wrong PO inspected" case.
//   • revised before  -> the previous version is put back, exactly as it was.
//
// The confirmation says which, by name and date, before anything happens —
// pressing one button and getting either outcome without being told would be
// worse than having no undo at all. The server decides again independently;
// this lookup is for the wording, not for the decision.
async function undoInspection(poNumber) {
    let previous = null;
    try {
        const res = await fetch(`${apiUrl}?po_number=${encodeURIComponent(poNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load.', 'danger');
        if (!data.exists) return showToast('This purchase order has no inspection to undo.', 'warning');
        previous = data.previous;
    } catch (err) {
        showToast('Unable to check the inspection history.', 'danger');
        console.error(err);
        return;
    }

    const message = previous
        ? `Undo the last save on PO ${poNumber}?\n\n`
          + `The inspection goes back to the version recorded on `
          + `${fmtDate(previous.date_inspected)}`
          + (previous.invoice_number ? ` (invoice ${previous.invoice_number})` : '')
          + `.\n\nAnything printed from the current version will be out of date.`
        : `Undo the inspection on PO ${poNumber}?\n\n`
          + `There is no earlier version to go back to, so the inspection is removed `
          + `and this purchase order returns to the Awaiting list.\n\n`
          + `Nothing is erased — the record stays on file and can be re-inspected.`;
    if (!confirm(message)) return;

    try {
        const res = await fetch(apiUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'undo', po_number: poNumber }),
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Could not undo.', 'danger');
        showToast(data.message || 'Undone.', 'success');
        // Closed rather than refreshed: after a removal there is no record left
        // to show, and after a restore the form on screen is the version that
        // was just taken back.
        const modal = bootstrap.Modal.getInstance(document.getElementById('inspectModal'));
        if (modal) modal.hide();
        loadList();
        loadStats();
    } catch (err) {
        showToast('Could not undo the inspection.', 'danger');
        console.error(err);
    }
}

// ── Printing the Inspection & Acceptance Report ────────────────────────────
//
// The form itself is built by js/iar-print.js out of css/iar-print.css, both a
// reconstruction of the Crystal report in
// vb/Inspection and Acceptance/Crystal Reports/. Everything below is only the
// plumbing: fetch the record, build the sheet, hand it to the shared
// raster/slice pipeline in js/print-pdf.js, and put the resulting PDF on
// screen — the same pipeline the Purchase Order print uses.

let iarPreviewData = null;      // the record currently on screen
let iarBlobUrl = null;
let iarFileName = 'inspection_report.pdf';

// Paper is a property of the printer and the operator, not of any one
// delivery, so it is remembered. localStorage can throw outright (private
// windows, site data blocked), so every access is guarded — a preference that
// fails to persist must never stop a form printing.
function readIarPref(key, fallback) {
    try { const v = localStorage.getItem(key); return v === null ? fallback : v; } catch (e) { return fallback; }
}
function writeIarPref(key, value) {
    try { localStorage.setItem(key, value); } catch (e) { /* not worth surfacing */ }
}

// The office each inspector signs for.
//
// ref_signaturies first, because that is the table Inspect_Print.vb's
// combo_office() queries and so is the legacy source of truth. Then a
// fallback the legacy report did not have: ref_signaturies_inspector, the
// table the inspector was PICKED from.
//
// The fallback is there because without it this form prints blank offices for
// people whose office the system knows perfectly well. On the sample record,
// all four inspectors are absent from ref_signaturies and all four carry a
// department in ref_signaturies_inspector — so the legacy report printed four
// blank lines under four signatures while the answer sat one table away. That
// is a defect worth not reproducing.
//
// Order matters, and this order is deliberate: where both tables have a row,
// the legacy one still wins, so nothing that printed correctly before changes.
// How many lines the printout will leave off, and why. Uses the builder's own
// rule rather than a second copy of it, so the note and the paper can never
// disagree about which rows are dropped.
function setIarOmitNote(items) {
    const all = items || [];
    const kept = all.filter(IarPrint.wasDelivered).length;
    const dropped = all.length - kept;
    document.getElementById('iarOmitNote').textContent = dropped
        ? dropped + (dropped === 1 ? ' line has' : ' lines have')
          + ' no quantity and no cost, so ' + (dropped === 1 ? 'it' : 'they')
          + ' will be left off the printed report. '
          + kept + (kept === 1 ? ' line' : ' lines') + ' will print.'
        : '';
}

function inspectorOffice(name) {
    if (!name) return '';
    const signatory = officeList.find(p => refKey(p.name) === refKey(name));
    if (signatory && signatory.department) return signatory.department;
    const picked = inspectorList.find(p => refKey(p.name) === refKey(name));
    return picked ? (picked.department || '') : '';
}

async function openIarPrint(poNumber) {
    if (!poNumber) return;
    try {
        const res = await fetch(apiUrl + '?po_number=' + encodeURIComponent(poNumber));
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load the inspection.', 'danger');
        // An un-inspected PO has no report to print — the form's whole lower
        // half IS the inspection. Said plainly rather than printing a mostly
        // empty sheet.
        if (!data.inspection) {
            return showToast('This delivery has not been inspected yet, so there is no report to print.', 'warning');
        }
        iarPreviewData = data;
        document.getElementById('iarPrintPo').textContent = poNumber;
        document.getElementById('iarPrintSupplier').textContent = (data.po && data.po.supplier_name) || '—';
        document.getElementById('iarInitial').value = readIarPref('iar.initial', '');
    document.getElementById('iarInitial').classList.remove('is-invalid');
    // Not remembered like the initials — how many blank slots a delivery
    // needs is specific to that print, not to whoever is running the copy.
    document.getElementById('iarBlankEndUsers').value = 0;

        // Only where NEITHER table can supply an office, since inspectorOffice()
        // now falls back to the inspector list. Naming the right table matters:
        // the earlier wording sent people to Master Data to fix a row that was
        // already there.
        const unknown = ['inspected_by_1', 'inspected_by_2', 'inspected_by_3', 'inspected_by_4']
            .map(k => data.inspection[k])
            .filter(n => n && n !== '-' && !inspectorOffice(n));
        document.getElementById('iarPrintWarn').textContent = unknown.length
            ? unknown.join(', ') + (unknown.length === 1 ? ' has' : ' have')
              + ' no office on file in either signatory list, so the line under '
              + (unknown.length === 1 ? 'that signature' : 'those signatures')
              + ' will print blank. Add the office in Master Data to fix it.'
            : '';
        setIarOmitNote(data.items);

        bootstrap.Modal.getOrCreateInstance(document.getElementById('iarPrintModal')).show();
    } catch (err) {
        showToast('Unable to load the inspection.', 'danger');
        console.error(err);
    }
}

// One progress billing, printed on the Inspection & Acceptance form.
//
// Deliberately routed through the SAME print and preview modals as the ordinary
// report: paper size, initials, the office warning and the PDF pipeline are all
// identical, and a second copy of that dialog would be a second thing to keep
// in step. The only difference reaching the builder is opts.billing.
async function openIarPrintBilling(billingNo) {
    if (!pbData) return;
    const b = (pbData.billings || []).find(x => x.billing_no === billingNo);
    if (!b) return showToast('That billing is no longer on file.', 'danger');

    // The billing's header carries its own snapshot of supplier, department and
    // purpose — taken from the PO when it was recorded — so the form prints what
    // was true on the day it was billed, not what the PO says now.
    iarPreviewData = {
        po: pbData.po,
        inspection: b,
        items: b.items || [],
        billing: { no: b.billing_no, dateFrom: b.date_from, dateTo: b.date_to },
    };
    document.getElementById('iarPrintPo').textContent =
        pbData.po.po_number + ' · Progress Billing - ' + b.billing_no;
    document.getElementById('iarPrintSupplier').textContent = pbData.po.supplier_name || '—';
    document.getElementById('iarInitial').value = readIarPref('iar.initial', '');
    document.getElementById('iarInitial').classList.remove('is-invalid');
    // Not remembered like the initials — how many blank slots a delivery
    // needs is specific to that print, not to whoever is running the copy.
    document.getElementById('iarBlankEndUsers').value = 0;

    const unknown = ['inspected_by_1', 'inspected_by_2', 'inspected_by_3', 'inspected_by_4']
        .map(k => b[k]).filter(n => n && n !== '-' && !inspectorOffice(n));
    document.getElementById('iarPrintWarn').textContent = unknown.length
        ? unknown.join(', ') + (unknown.length === 1 ? ' has' : ' have')
          + ' no office on file in either signatory list, so the line under '
          + (unknown.length === 1 ? 'that signature' : 'those signatures')
          + ' will print blank. Add the office in Master Data to fix it.'
        : '';
    setIarOmitNote(b.items);

    bootstrap.Modal.getOrCreateInstance(document.getElementById('iarPrintModal')).show();
}

function openIarPreview() {
    if (!iarPreviewData) return;

    // Required, same as the Purchase Order print: the initials say who prepared
    // the copy, and a form that leaves the office without them cannot be traced
    // back to anyone. Enforced here because the input is not inside a <form>,
    // so the `required` attribute alone would do nothing.
    const initialField = document.getElementById('iarInitial');
    if (!initialField.value.trim()) {
        showToast('Enter the initials before printing — they identify who prepared this copy.', 'danger');
        initialField.classList.add('is-invalid');
        initialField.focus();
        return;
    }
    initialField.classList.remove('is-invalid');
    writeIarPref('iar.initial', initialField.value.trim());
    document.getElementById('iarPaperSize').value = readIarPref('iar.paper', 'auto');
    document.getElementById('iarTextSize').value = readIarPref('iar.text', '0');
    bootstrap.Modal.getInstance(document.getElementById('iarPrintModal')).hide();

    const previewEl = document.getElementById('iarPreviewModal');
    bootstrap.Modal.getOrCreateInstance(previewEl).show();
    // Wait for the fade-in to finish before rasterising: Bootstrap animates
    // .modal-dialog with a CSS transform while showing, and html2canvas
    // reading layout mid-transition can capture an inconsistent box.
    previewEl.addEventListener('shown.bs.modal', renderIarPdf, { once: true });
}

async function renderIarPdf() {
    const d = iarPreviewData;
    if (!d) return;
    const loading = document.getElementById('iarPreviewLoading');
    const frame = document.getElementById('iarPreviewFrame');
    const buttons = ['iarDownload', 'iarPrint', 'iarOpenTab'].map(id => document.getElementById(id));

    frame.classList.add('d-none');
    buttons.forEach(b => { if (b) b.disabled = true; });
    document.getElementById('iarPaperSize').disabled = true;
    document.getElementById('iarTextSize').disabled = true;
    loading.classList.remove('d-none');
    loading.innerHTML = '<div class="spinner-border text-primary" role="status"></div>'
                      + '<div class="small mt-2">Generating PDF preview…</div>';
    document.getElementById('iarLayoutStatus').innerHTML = '';

    try {
        // The inspection's own fields sit alongside the PO's, which is the
        // shape buildSheet expects — one flat object, as the legacy DataSet
        // handed the report.
        const sheetData = Object.assign({}, d.po, d.inspection, { items: d.items || [] });
        document.getElementById('iarSheetArea').innerHTML = IarPrint.buildSheet(sheetData, {
            initial: document.getElementById('iarInitial').value.trim(),
            inspectorOffice: inspectorOffice,
            // Absent for an ordinary inspection, which is what selects the
            // ordinary form — see the note above buildSheet().
            billing: d.billing || null,
            blankEndUsers: parseInt(document.getElementById('iarBlankEndUsers').value, 10) || 0,
        });

        // A stale cached js/print-pdf.js is the one failure here that looks
        // like a code bug and is not. Named plainly, with the fix, because
        // "X is not a function" sends people looking for X in the source.
        if (!window.PrintPdf || typeof PrintPdf.renderToPdf !== 'function') {
            throw new Error('the print engine did not load, or your browser is holding an old copy of it. '
                          + 'Reload the page with Ctrl+F5 and try again.');
        }
        const source = document.querySelector('#iarSheetArea .iar-sheet');
        // Item text size, applied to the live source before it is rasterised —
        // same idea as purchase_orders.php's own Item text control: the PDF is
        // a photograph of this element, so the text has to be the right size
        // here or it is only ever an enlargement of an 11px image.
        const textStep = document.getElementById('iarTextSize').value;
        source.classList.remove('iar-text-1', 'iar-text-2');
        if (textStep !== '0') source.classList.add('iar-text-' + textStep);
        const result = await PrintPdf.renderToPdf(source, {
            paper: document.getElementById('iarPaperSize').value,
            // Item rows are the only safe cuts: the header and footer blocks
            // are absolutely positioned and must never be split.
            breakSelector: '.iar-items tr',
            gridSelector: '.iar-items',
        });

        const info = PrintPdf.describeLayout(result.layout);
        document.getElementById('iarLayoutStatus').innerHTML =
            '<span>' + escapeHtml(info.paper) + '</span>'
          + '<span class="pp-sheets">' + escapeHtml(info.sheetsLabel) + '</span>'
          + (info.note ? '<span class="pp-layout-note">' + escapeHtml(info.note) + '</span>' : '');

        if (iarBlobUrl) URL.revokeObjectURL(iarBlobUrl);
        iarBlobUrl = URL.createObjectURL(result.blob);
        iarFileName = (d.billing ? 'IAR_PB' + d.billing.no + '_' : 'IAR_')
                    + (d.po ? d.po.po_number : 'report') + '.pdf';
        frame.src = iarBlobUrl + '#navpanes=0&toolbar=1&view=FitH';
        loading.classList.add('d-none');
        frame.classList.remove('d-none');
        buttons.forEach(b => { if (b) b.disabled = false; });
    } catch (err) {
        // Surfaced directly rather than as a generic message, so a failure is
        // diagnosable instead of a dead end.
        loading.innerHTML = '<div class="text-danger small px-3">'
            + '<i class="fas fa-triangle-exclamation me-1"></i>Unable to generate the PDF: '
            + escapeHtml(err.message || String(err)) + '</div>';
        console.error(err);
    } finally {
        // In finally, not at the end of the try: changing the paper is one of
        // the few things that might get a failed render to succeed.
        document.getElementById('iarPaperSize').disabled = false;
        document.getElementById('iarTextSize').disabled = false;
    }
}

function downloadIarPdf() {
    if (!iarBlobUrl) return;
    const a = document.createElement('a');
    a.href = iarBlobUrl;
    a.download = iarFileName;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function printIarPdf() {
    const frame = document.getElementById('iarPreviewFrame');
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch (err) {
        // Some built-in PDF viewers do not expose a scriptable print() — fall
        // back to a new tab, where the viewer's own toolbar works normally.
        if (iarBlobUrl) window.open(iarBlobUrl, '_blank');
    }
}


// ── The Disbursement Voucher ───────────────────────────────────────────────
//
// Replaces Voucher_Print.vb. Same nine values, same single figure, and the
// same two flows that form had: the whole purchase order, or one progress
// billing. The form itself is built by js/dv-print.js out of css/dv-print.css
// and driven through the same PDF pipeline as everything else on this page.
//
// One thing the legacy did that is NOT reproduced: it wrote Category and
// Category_Explanation to tbl_inspection as a silent side effect of pressing
// Print. Here the save is explicit and is announced — see dvSave().

let dvData = null;              // { po, amount, billing }
let dvBlobUrl = null;
let dvFileName = 'disbursement_voucher.pdf';
// 'payment' (default) or 'reimbursement' — set by which footer button was
// pressed, same idea as the Obligation Request's Print / Reimbursement Print
// pair in purchase_orders.php. Only changes the explanation line's wording
// (buildSheet()'s actionWord); Payee is the same free-text field either way,
// so reimbursing an employee is just typing their name over the supplier's.
let dvReportType = 'payment';

// Whoever holds the office, by Position, out of the signatory list the page
// already loaded. Inspection_Main_Form carried these three as labels the
// operator could not change; here they are looked up but stay editable, so an
// OIC signing this week is typed over rather than being a reason not to print.
function dvOfficer(position) {
    const want = String(position).toUpperCase();
    const hit = officeList.find(p => String(p.position || '').trim().toUpperCase() === want);
    return hit ? hit.name : '';
}

function dvFillDialog(po, amount, scopeLabel, billing, canRemember) {
    dvData = { po: po, amount: amount, billing: billing || null, canRemember: canRemember !== false };

    document.getElementById('dvPo').textContent = po.po_number || '';
    document.getElementById('dvScope').textContent = scopeLabel;
    document.getElementById('dvAmount').textContent = '₱' + money(amount);

    document.getElementById('dvPayee').value = po.supplier_name || '';
    document.getElementById('dvAddress').value = po.supplier_address || '';
    // Blank on the legacy dialog, though the purchase order has always carried
    // it — the same kind of gap as the inspector offices, filled the same way.
    document.getElementById('dvDepartment').value = po.department || '';
    document.getElementById('dvTin').value = '';

    // What was classified last time wins; otherwise the purchase order's own
    // category, which is usually the right answer and is always a better
    // starting point than an empty box.
    const insp = po._inspection || {};
    document.getElementById('dvCategory').value = insp.category || po.po_category || '';
    document.getElementById('dvExplanation').value = insp.category_explanation || '';

    // Category and Explanation are stored on the inspection record, which is
    // the only place the legacy ever put them. A progress-billing purchase
    // order that was never inspected as a whole has no such record, so there is
    // nowhere to keep them — the voucher still prints in full, it just opens
    // blank next time. Said here rather than surfaced later as a failed save.
    document.getElementById('dvRememberHint').textContent = dvData.canRemember
        ? 'Both fields are saved onto the inspection when you press Preview, so the next voucher for '
          + 'this purchase order opens already filled in.'
        : 'This purchase order has no whole-order inspection record, so these two fields cannot be '
          + 'remembered — the voucher prints them, but the next one will open blank.';

    document.getElementById('dvAccountant').value = dvOfficer('CITY ACCOUNTANT');
    document.getElementById('dvTreasurer').value  = dvOfficer('CITY TREASURER');
    document.getElementById('dvMayor').value      = dvOfficer('CITY MAYOR');

    const missing = [
        ['City Accountant', 'dvAccountant'], ['City Treasurer', 'dvTreasurer'], ['City Mayor', 'dvMayor'],
    ].filter(([, id]) => !document.getElementById(id).value).map(([label]) => label);
    document.getElementById('dvSignWarn').textContent = missing.length
        ? 'No one is on file as ' + missing.join(', ') + ' in Master Data → Signatories, so '
          + (missing.length === 1 ? 'that line' : 'those lines') + ' will print blank. Type the name to fix it here.'
        : '';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('dvModal')).show();
}

// The whole purchase order — Voucher_Print.vb's "View" branch, which summed
// tbl_inspection_items_details for the PO.
async function openVoucher(poNumber) {
    if (!poNumber) return;
    try {
        const res = await fetch(apiUrl + '?po_number=' + encodeURIComponent(poNumber));
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load the inspection.', 'danger');
        if (!data.inspection) {
            return showToast('This delivery has not been inspected yet, so there is nothing to certify for payment.', 'warning');
        }
        // A progress-billing PO is billed in tranches, and a voucher for the
        // whole contract when only part of it has been delivered pays for
        // goods nobody has accepted. Said plainly; not blocked, because a
        // fully-delivered contract legitimately gets one.
        if (data.po.is_progress_billing) {
            const ok = confirm(
                'This purchase order is on progress billing.\n\n'
              + 'A voucher raised here certifies the FULL inspected amount, not one tranche. '
              + 'To pay a single billing, open Billings and use the voucher button on that card instead.\n\n'
              + 'Continue with the full amount?');
            if (!ok) return;
        }
        const amount = (data.items || []).reduce((s, it) => s + (parseFloat(it.total_cost) || 0), 0);
        const po = Object.assign({}, data.po, { _inspection: data.inspection });
        dvFillDialog(po, amount, 'Whole purchase order', null);
    } catch (err) {
        showToast('Unable to load the inspection.', 'danger');
        console.error(err);
    }
}

// One progress billing — Voucher_Print.vb's "For Progress Billing" branch,
// which summed only the rows carrying that billing number.
function openVoucherBilling(billingNo) {
    if (!pbData) return;
    const b = (pbData.billings || []).find(x => x.billing_no === billingNo);
    if (!b) return showToast('That billing is no longer on file.', 'danger');

    const amount = (b.items || []).reduce((s, it) => s + (parseFloat(it.total_cost) || 0), 0);
    // pbData.classification is null when this PO has no whole-order inspection
    // row — common on progress billing, and the one place the classification
    // can be stored. dvFillDialog says so on screen; see dvSave().
    const po = Object.assign({}, pbData.po, { _inspection: pbData.classification || {} });
    dvFillDialog(po, amount, 'Progress Billing - ' + b.billing_no,
                 { no: b.billing_no, dateFrom: b.date_from, dateTo: b.date_to },
                 pbData.classification !== null);
}

// Saves the classification, then previews. Explicit, and reported: the legacy
// wrote these two columns behind the operator's back on every print.
async function dvSave() {
    if (!dvData) return;
    // Nowhere to save it — the dialog already said so. Attempting the PATCH
    // anyway would answer "No inspection exists for this purchase order",
    // which reads like a refusal to print.
    if (!dvData.canRemember) return;
    const category = document.getElementById('dvCategory').value.trim();
    const explanation = document.getElementById('dvExplanation').value.trim();
    if (!category) {
        // Not fatal — the .rpt prints whatever it is given — but a voucher that
        // does not say what it pays for is a voucher the accountant sends back.
        showToast('Category is blank, so the voucher will read "To payment of" and nothing else.', 'warning');
    }
    try {
        const res = await fetch(apiUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                po_number: dvData.po.po_number,
                category: category,
                category_explanation: explanation,
            }),
        });
        const out = await res.json();
        if (!out.success) showToast(out.message || 'The classification could not be saved.', 'warning');
        else if (out.message && out.message !== 'Classification unchanged.') showToast(out.message, 'success');
    } catch (err) {
        // The voucher still prints. A failed save is worth saying out loud, but
        // it is not a reason to stand between somebody and their paperwork.
        showToast('The classification could not be saved, but the voucher will still print.', 'warning');
        console.error(err);
    }
}

async function openDvPreview(reportType) {
    if (!dvData) return;
    dvReportType = reportType === 'reimbursement' ? 'reimbursement' : 'payment';
    await dvSave();
    document.getElementById('dvPaperSize').value = readIarPref('dv.paper', 'auto');
    document.getElementById('dvTextSize').value = readIarPref('dv.text', '0');
    bootstrap.Modal.getInstance(document.getElementById('dvModal')).hide();

    const previewEl = document.getElementById('dvPreviewModal');
    bootstrap.Modal.getOrCreateInstance(previewEl).show();
    previewEl.addEventListener('shown.bs.modal', renderDvPdf, { once: true });
}

async function renderDvPdf() {
    if (!dvData) return;
    const loading = document.getElementById('dvPreviewLoading');
    const frame = document.getElementById('dvPreviewFrame');
    const buttons = ['dvDownload', 'dvPrint', 'dvOpenTab'].map(id => document.getElementById(id));

    frame.classList.add('d-none');
    buttons.forEach(b => { if (b) b.disabled = true; });
    document.getElementById('dvPaperSize').disabled = true;
    document.getElementById('dvTextSize').disabled = true;
    loading.classList.remove('d-none');
    loading.innerHTML = '<div class="spinner-border text-primary" role="status"></div>'
                      + '<div class="small mt-2">Generating PDF preview…</div>';
    document.getElementById('dvLayoutStatus').innerHTML = '';

    try {
        document.getElementById('dvSheetArea').innerHTML = DvPrint.buildSheet({
            poNumber:    dvData.po.po_number,
            payee:       document.getElementById('dvPayee').value.trim(),
            address:     document.getElementById('dvAddress').value.trim(),
            tin:         document.getElementById('dvTin').value.trim(),
            department:  document.getElementById('dvDepartment').value.trim(),
            category:    document.getElementById('dvCategory').value.trim(),
            explanation: document.getElementById('dvExplanation').value.trim(),
            accountant:  document.getElementById('dvAccountant').value.trim(),
            treasurer:   document.getElementById('dvTreasurer').value.trim(),
            mayor:       document.getElementById('dvMayor').value.trim(),
            amount:      dvData.amount,
            billing:     dvData.billing,
            reportType:  dvReportType,
        });

        if (!window.PrintPdf || typeof PrintPdf.renderToPdf !== 'function') {
            throw new Error('the print engine did not load, or your browser is holding an old copy of it. '
                          + 'Reload the page with Ctrl+F5 and try again.');
        }
        const source = document.querySelector('#dvSheetArea .dv-sheet');
        // Text size, applied to the live source before it is rasterised — same
        // idea as the IAR's iar-text-1/2 and the PO's pp-text-1/2.
        const dvTextStep = document.getElementById('dvTextSize').value;
        source.classList.remove('dv-text-1', 'dv-text-2');
        if (dvTextStep !== '0') source.classList.add('dv-text-' + dvTextStep);
        // The voucher fits one sheet, so in practice nothing is ever cut. If a
        // very long explanation ever pushes it over, the only acceptable cuts
        // are between the form's top-level bands — the default 'tr' would
        // happily slice a certification away from the signature under it,
        // because every one of those blocks is itself a table of rows.
        const result = await PrintPdf.renderToPdf(source, {
            paper: document.getElementById('dvPaperSize').value,
            breakSelector: '.dv-frame > table',
            gridSelector: '.dv-frame > table',
        });

        const info = PrintPdf.describeLayout(result.layout);
        document.getElementById('dvLayoutStatus').innerHTML =
            '<span>' + escapeHtml(info.paper) + '</span>'
          + '<span class="pp-sheets">' + escapeHtml(info.sheetsLabel) + '</span>'
          + (info.note ? '<span class="pp-layout-note">' + escapeHtml(info.note) + '</span>' : '');

        if (dvBlobUrl) URL.revokeObjectURL(dvBlobUrl);
        dvBlobUrl = URL.createObjectURL(result.blob);
        dvFileName = 'DV_' + (dvData.billing ? 'PB' + dvData.billing.no + '_' : '')
                   + (dvData.po.po_number || 'voucher') + '.pdf';
        frame.src = dvBlobUrl + '#navpanes=0&toolbar=1&view=FitH';
        loading.classList.add('d-none');
        frame.classList.remove('d-none');
        buttons.forEach(b => { if (b) b.disabled = false; });
    } catch (err) {
        loading.innerHTML = '<div class="text-danger small px-3">'
            + '<i class="fas fa-triangle-exclamation me-1"></i>Unable to generate the PDF: '
            + escapeHtml(err.message || String(err)) + '</div>';
        console.error(err);
    } finally {
        document.getElementById('dvPaperSize').disabled = false;
        document.getElementById('dvTextSize').disabled = false;
    }
}

function downloadDvPdf() {
    if (!dvBlobUrl) return;
    const a = document.createElement('a');
    a.href = dvBlobUrl;
    a.download = dvFileName;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function printDvPdf() {
    const frame = document.getElementById('dvPreviewFrame');
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch (err) {
        if (dvBlobUrl) window.open(dvBlobUrl, '_blank');
    }
}


// ── Progress billing ───────────────────────────────────────────────────────
// The second flow. Nothing above this line changes: a purchase order that is
// not on progress billing never reaches any of it, and one that is still
// inspects exactly the way it always did.
const pbApiUrl = 'api/inspection_progress.php';
let pbData     = null;   // the last GET payload for the open PO
let pbEditNo   = null;   // the billing being edited; null while browsing history

function pbNum(v) {
    const n = parseFloat(v);
    return isFinite(n) ? n : 0;
}
// Quantities are counts, not money — 200, not 200.00. Shown with decimals only
// when the stored value actually has them, which legacy rows occasionally do.
function pbQty(v) {
    const n = pbNum(v);
    return Number.isInteger(n) ? String(n) : n.toFixed(2);
}
function pbDateOnly(v) {
    if (!v) return '';
    const s = String(v);
    return s.startsWith('0000') ? '' : s.slice(0, 10);
}

async function openBillings(poNumber) {
    try {
        const res  = await fetch(`${pbApiUrl}?po_number=${encodeURIComponent(poNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load billings.', 'danger');
        pbData = data;
        document.getElementById('pbPoNumber').value = data.po.po_number;
        pbRenderHistory();
        pbShowHistory();
        openModal('pbModal');
    } catch (err) {
        showToast('Unable to load billings.', 'danger');
        console.error(err);
    }
}

function pbShowHistory() {
    pbEditNo = null;
    document.getElementById('pbHistoryPane').classList.remove('d-none');
    document.getElementById('pbEditorPane').classList.add('d-none');
    document.getElementById('pbBtnBack').classList.add('d-none');
    document.getElementById('pbBtnSave').classList.add('d-none');
    document.getElementById('pbBtnClose').classList.remove('d-none');
    document.getElementById('pbBtnNew').classList.remove('d-none');
    const next = pbData ? pbData.next_billing_no : 1;
    document.getElementById('pbBtnNew').innerHTML =
        `<i class="fas fa-plus me-1"></i> Record Progress Billing - ${next}`;
    document.getElementById('pbFooterNote').textContent = '';
    document.querySelector('#pbModal .modal-body').scrollTop = 0;
}

function pbRenderHistory() {
    const po = pbData.po, t = pbData.totals;
    document.getElementById('pbTitle').textContent = 'Progress Billing — ' + po.po_number;
    document.getElementById('pbSumPo').innerHTML = po.po_number
        ? `<span class="num-pill po-num-pill">${escapeHtml(po.po_number)}</span>` : '—';
    document.getElementById('pbSumPr').innerHTML = po.pr_number
        ? `<span class="num-pill pr-num-pill">${escapeHtml(po.pr_number)}</span>` : '—';
    document.getElementById('pbSumSupplier').textContent = po.supplier_name || '—';
    document.getElementById('pbSumTerm').textContent     = po.payment_term || '—';

    const over = t.remaining_amount < -0.005;
    document.getElementById('pbTotOrdered').textContent   = '₱' + money(t.ordered_amount);
    document.getElementById('pbTotBilled').textContent    = '₱' + money(t.billed_amount);
    const rem = document.getElementById('pbTotRemaining');
    rem.textContent = '₱' + money(Math.abs(t.remaining_amount));
    rem.className = 'v ' + (over ? 'over' : 'remaining');
    document.getElementById('pbTotCount').textContent = t.billing_count;

    // Both states come from the server's `status`, decided once by
    // progressBillingStatus() in config.php and shared with the worklist badge.
    const done = t.status === 'complete';
    pbSetStatusPill(t);

    const bar = document.getElementById('pbBar');
    const pct = t.billed_percent;
    bar.classList.toggle('over', over);
    bar.classList.toggle('done', done);
    // A FULL bar means completed, and nothing else. Capped at 100 so an
    // over-billed contract cannot draw wider than its own box, and held just
    // short of full until `done` actually holds — otherwise a long contract
    // whose percentage rounds to 100 with pesos still outstanding shows a full
    // bar beside a pill reading 99.9%. The figures underneath stay exact.
    bar.firstElementChild.style.width =
        (pct === null ? 0 : (done || over) ? 100 : Math.min(99.5, Math.max(0, pct))) + '%';
    document.getElementById('pbBarHint').textContent =
        pct === null ? 'The purchase order has no amount to measure against.'
        : over ? `Billed ${pct}% of the purchase order — ₱${money(-t.remaining_amount)} over.`
        : done ? `Fully billed — all ₱${money(t.ordered_amount)} of the purchase order is accounted for.`
        : `Billed ${pct}% of the purchase order — ₱${money(t.remaining_amount)} still to bill.`;

    pbRenderProgressTable();
    pbRenderCards();
}

// The four states a progress-billing purchase order can be in, worded for the
// title bar. Percentages are shown to one decimal and never rounded UP to 100:
// pbRenderHistory decides "completed" from the remaining amount, and a pill
// reading 100% beside a bar that is not full would be the more confusing of the
// two numbers to believe.
function pbSetStatusPill(t) {
    const pill = document.getElementById('pbStatusPill');
    const pct  = t.billed_percent;
    let text, cls = '';
    switch (t.status) {
        case 'complete':   text = 'Completed · 100%';                     cls = 'done'; break;
        case 'over':       text = 'Over-billed · ' + pbPct(pct) + '%';    cls = 'over'; break;
        case 'partial':    text = pbPct(pct) + '% billed';                              break;
        // Nothing to measure against — say what IS known rather than a
        // percentage of zero.
        case 'unmeasured': text = t.billing_count + (t.billing_count === 1 ? ' billing' : ' billings'); break;
        default:           text = 'Not started';                          cls = 'idle'; break;
    }
    pill.textContent = text;
    pill.className = 'pb-status-pill' + (cls ? ' ' + cls : '');
}

// One decimal, and a whole number keeps no decimal point at all — "50%", not
// "50.0%". Held below 100 unless it really is 100, so the pill can never claim
// completion the Completed test has not granted.
function pbPct(v) {
    const n = Math.round((parseFloat(v) || 0) * 10) / 10;
    const shown = (n >= 100 && n < 100.05) ? 99.9 : n;
    return Number.isInteger(shown) ? String(shown) : shown.toFixed(1);
}

// Ordered against billed, with one column per billing recorded. This is the
// "how many were in Progress Billing 1, 2, …" view.
function pbRenderProgressTable() {
    const wrap = document.getElementById('pbProgressWrap');
    const rows = pbData.progress || [];
    const nos  = (pbData.billings || []).map(b => b.billing_no);
    if (!rows.length) {
        wrap.innerHTML = '<div class="pb-empty">This purchase order has no item lines.</div>';
        return;
    }

    const head = `<thead><tr>
        <th class="pb-desc">Item</th><th>Unit</th><th class="num">Ordered</th>
        ${nos.map(n => `<th class="num">PB-${n}</th>`).join('')}
        <th class="num">Billed</th><th class="num">Remaining</th></tr></thead>`;

    const body = rows.map(r => {
        if (r.line_kind === 'caption') {
            return `<tr class="caption"><td colspan="${5 + nos.length}">${escapeHtml(r.items_description || '')}</td></tr>`;
        }
        const ordered   = pbNum(r.ordered_quantity);
        const billed    = pbNum(r.billed_quantity);
        const remaining = pbNum(r.remaining_quantity);
        // "Fully billed" only means something when a quantity was ordered. A
        // lump-sum line carries no quantity at all and must not be painted
        // green just for having a zero remainder.
        const cls = r.over_billed ? 'over' : (ordered > 0 && remaining <= 0.0001 ? 'done' : '');
        return `<tr class="${cls}">
            <td class="pb-desc">${escapeHtml(r.items_description || '')}
                ${r.unmatched ? '<div class="pb-unmatched">not on the purchase order</div>' : ''}</td>
            <td>${escapeHtml(r.unit || '')}</td>
            <td class="num">${ordered > 0 ? pbQty(ordered) : '—'}</td>
            ${nos.map(n => `<td class="num">${r.per_billing[n] ? `<span class="pb-billed-cell">${pbQty(r.per_billing[n])}</span>` : '—'}</td>`).join('')}
            <td class="num">${billed > 0 ? pbQty(billed) : '—'}</td>
            <td class="num">${ordered > 0 ? pbQty(remaining) : '—'}</td>
        </tr>`;
    }).join('');

    const foot = `<tfoot><tr>
        <td colspan="${3 + nos.length}" class="num">Amount</td>
        <td class="num">₱${money(pbData.totals.billed_amount)}</td>
        <td class="num">₱${money(pbData.totals.remaining_amount)}</td></tr></tfoot>`;

    wrap.innerHTML = `<table class="pb-progress">${head}<tbody>${body}</tbody>${foot}</table>`;
}

function pbRenderCards() {
    const host = document.getElementById('pbHistory');
    const list = pbData.billings || [];
    if (!list.length) {
        host.innerHTML = '<div class="pb-empty">Nothing billed yet. '
                       + 'Use <strong>Record Progress Billing - 1</strong> below to start.</div>';
        return;
    }
    host.innerHTML = list.map(b => {
        const from = pbDateOnly(b.date_from), to = pbDateOnly(b.date_to);
        const period = from && to ? `${fmtDate(from)} – ${fmtDate(to)}` : (from ? fmtDate(from) : '');
        const officers = ['inspected_by_1','inspected_by_2','inspected_by_3','inspected_by_4']
            .map(k => b[k]).filter(v => v && v.trim() && v.trim() !== '-');
        const lines = (b.items || []).map(i => `<div>
            <span class="q">${pbQty(i.quantity)} ${escapeHtml(i.unit || '')}</span>
            <span class="d">${escapeHtml(i.items_description || '')}</span>
            <span class="t">₱${money(i.total_cost)}</span></div>`).join('');
        return `<div class="pb-card">
            <div class="pb-card-head">
                <span class="pb-card-no">${escapeHtml(b.label || ('Progress Billing - ' + b.billing_no))}</span>
                <span class="pb-card-meta">${period ? escapeHtml(period) + ' · ' : ''}Invoice ${escapeHtml(b.invoice_number || '—')}</span>
                <span class="pb-card-total">₱${money(b.total)}</span>
                <button class="btn btn-sm btn-outline-primary" onclick="pbOpenEditor(${b.billing_no})" title="Revise"><i class="fas fa-pen"></i></button>
                <button class="btn btn-sm btn-outline-secondary" onclick="openIarPrintBilling(${b.billing_no})" title="Print this billing's Inspection &amp; Acceptance Report"><i class="fas fa-print"></i></button>
                <button class="btn btn-sm btn-outline-secondary" onclick="openVoucherBilling(${b.billing_no})" title="Disbursement Voucher for this billing only"><i class="fas fa-money-check-dollar"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="pbUndo(${b.billing_no})"
                        title="${b.previous ? 'Undo the last revision of this billing' : 'Undo — remove this billing'}"><i class="fas fa-rotate-left"></i></button>
            </div>
            <div class="pb-card-meta">${officers.length ? 'Inspected by ' + escapeHtml(officers.join(', ')) : 'No inspector recorded'}${b.accepted_by ? ' · Accepted by ' + escapeHtml(b.accepted_by) : ''}</div>
            ${b.previous ? `<div class="pb-card-revised"><i class="fas fa-clock-rotate-left"></i> Revised — undo restores the version of ${escapeHtml(fmtDate(b.previous.date_inspected))}</div>` : ''}
            ${b.remarks ? `<div class="pb-card-meta">${escapeHtml(b.remarks)}</div>` : ''}
            <div class="pb-card-lines">${lines}</div>
        </div>`;
    }).join('');
}

// ── The editor ─────────────────────────────────────────────────────────────
function pbOpenEditor(billingNo) {
    const existing = billingNo == null ? null
        : (pbData.billings || []).find(b => b.billing_no === billingNo) || null;
    pbEditNo = billingNo == null ? pbData.next_billing_no : billingNo;

    // Which numbers may be chosen. Revising is locked to its own number —
    // moving a billing from 2 to 3 is not an edit, it is two operations.
    const used = new Set((pbData.billings || []).map(b => b.billing_no));
    const sel  = document.getElementById('pbEditNo');
    if (existing) {
        sel.innerHTML = `<option value="${existing.billing_no}">Progress Billing - ${existing.billing_no}</option>`;
        sel.disabled = true;
    } else {
        let opts = '';
        for (let n = 1; n <= 20; n++) if (!used.has(n)) opts += `<option value="${n}">Progress Billing - ${n}</option>`;
        sel.innerHTML = opts;
        sel.disabled = false;
        sel.value = String(pbEditNo);
    }

    document.getElementById('pbEditorBannerText').textContent = existing
        ? `Revising Progress Billing - ${existing.billing_no}. The current version is kept as history.`
        : `Recording Progress Billing - ${pbEditNo} for ${pbData.po.po_number}. `
          + 'Enter only the quantities this tranche covers; leave the rest at zero.';

    document.getElementById('pbEditFrom').value     = existing ? pbDateOnly(existing.date_from) : '';
    document.getElementById('pbEditTo').value       = existing ? pbDateOnly(existing.date_to) : '';
    document.getElementById('pbEditDelivery').value = existing ? pbDateOnly(existing.delivery_date) : '';
    document.getElementById('pbEditInvoice').value  = existing ? (existing.invoice_number || '') : '';
    document.getElementById('pbEditBarcode').value  = existing ? (existing.barcode_number || '') : '';
    document.getElementById('pbEditBac').value      = toLocalInput(existing ? existing.date_bac_received : null);
    document.getElementById('pbEditRemarks').value  = existing ? (existing.remarks || '') : '';
    document.getElementById('pbEditAccepted').value = existing ? (existing.accepted_by || '') : 'DIOSCORA R. NIERE, CPA';
    ['1','2','3','4'].forEach(n =>
        document.getElementById('pbEditBy' + n).value = existing ? (existing['inspected_by_' + n] || '') : '');
    // NOT defaulted to the signed-in user — see the same change in the
    // Inspect modal (openInspection()) for why.
    document.getElementById('pbEditEnd1').value = existing ? (existing.end_user1 || '') : '';
    ['2','3','4','5'].forEach(n =>
        document.getElementById('pbEditEnd' + n).value = existing ? (existing['end_user' + n] || '') : '');

    pbRefreshPickers();
    pbRenderEditorItems(existing);

    document.getElementById('pbHistoryPane').classList.add('d-none');
    document.getElementById('pbEditorPane').classList.remove('d-none');
    document.getElementById('pbBtnBack').classList.remove('d-none');
    document.getElementById('pbBtnSave').classList.remove('d-none');
    document.getElementById('pbBtnClose').classList.add('d-none');
    document.getElementById('pbBtnNew').classList.add('d-none');
    document.querySelector('#pbModal .modal-body').scrollTop = 0;
}

// Its own copy of the dedupe filter rather than a call to
// refreshInspectorLists(), which reads the Inspect modal's fields — sharing it
// would let one dialog filter the other's pickers.
function pbRefreshPickers() {
    const fields = ['pbEditBy1','pbEditBy2','pbEditBy3','pbEditBy4'];
    fields.forEach((id, idx) => {
        const taken = fields.filter(o => o !== id)
            .map(o => refKey(document.getElementById(o).value.trim())).filter(k => k !== '');
        const dl = document.getElementById('dlPbInspectors' + (idx + 1));
        if (!dl) return;
        dl.innerHTML = inspectorList.filter(p => !taken.includes(refKey(p.name)))
            .map(p => `<option value="${escapeHtml(p.name)}">${escapeHtml(p.department || '')}</option>`).join('');
    });
    document.getElementById('dlPbOffices').innerHTML =
        officeList.map(p => `<option value="${escapeHtml(p.name)}">${escapeHtml(p.position || '')}</option>`).join('');
}

function pbRenderEditorItems(existing) {
    const body  = document.getElementById('pbEditItemsBody');
    const saved = {};
    if (existing) (existing.items || []).forEach(i => { saved[String(i.item_id ?? '')] = i; });

    // Remaining shown here EXCLUDES the billing being revised. Otherwise
    // reopening Progress Billing - 2 shows a remainder its own quantities have
    // already eaten, and every revision looks like an over-billing.
    const progressByItem = {};
    (pbData.progress || []).forEach(r => { progressByItem[String(r.item_id ?? '')] = r; });

    body.innerHTML = (pbData.order_items || []).map((it, idx) => {
        // A caption is part of the document, not a billable line — shown so the
        // grid still reads like the purchase order, with nothing to type in.
        if (it.line_kind === 'caption') {
            return `<tr class="caption" data-idx="${idx}"><td colspan="7">${escapeHtml(it.items_description || '')}</td></tr>`;
        }
        const key  = String(it.item_id ?? '');
        const prog = progressByItem[key];
        const mine = existing && saved[key] ? pbNum(saved[key].quantity) : 0;
        // bill_quantity / bill_unit_cost come from the server so a lump sum is
        // "1 lot at the whole amount" in both this grid and the progress table.
        const ordered   = pbNum(it.bill_quantity);
        const billedElsewhere = prog ? pbNum(prog.billed_quantity) - mine : 0;
        const remaining = ordered - billedElsewhere;
        // Description/unit/cost: what was actually saved for THIS billing when
        // revising one (a description typo fixed on billing 3 should stay fixed
        // when billing 3 is reopened, not silently revert to the PO's own text),
        // falling back to the purchase order's own values for a fresh line.
        const savedRow = existing && saved[key] ? saved[key] : null;
        const desc = savedRow && savedRow.items_description != null ? savedRow.items_description : (it.items_description || '');
        const unit = savedRow && savedRow.unit != null ? savedRow.unit : (it.unit || (it.line_kind === 'lump' ? 'lot' : ''));
        const cost = savedRow && savedRow.unit_cost != null ? pbNum(savedRow.unit_cost) : pbNum(it.bill_unit_cost);
        return `<tr data-idx="${idx}" data-item-id="${escapeHtml(key)}"
                    data-ordered="${ordered}" data-remaining="${remaining}"
                    class="${mine ? '' : 'zero'}">
            <td class="pb-desc"><textarea rows="1" class="pb-desc-input" oninput="pbAutoGrow(this)">${escapeHtml(desc)}</textarea>
                ${it.line_kind === 'lump' ? '<div class="pb-lot">lump sum — bill 1 for the whole amount</div>' : ''}</td>
            <td><input type="text" class="pb-unit-input" value="${escapeHtml(unit)}"></td>
            <td class="num">${ordered > 0 ? pbQty(ordered) : '—'}</td>
            <td class="num">${ordered > 0 ? pbQty(remaining) : '—'}</td>
            <td class="num pb-qty-cell">
                <input type="number" step="any" min="0" class="pb-qty" value="${mine || ''}" placeholder="0">
                ${ordered > 0 ? `<button type="button" class="pb-fill-btn" title="Bill the full remaining quantity — for a delivery accepted as complete despite a small natural shortfall (e.g. weighed goods)" onclick="pbFillRemaining(this)">Full</button>` : ''}
            </td>
            <td class="num"><input type="number" step="0.01" min="0" class="pb-cost-input" value="${cost}"></td>
            <td class="num pb-line-total">${money(mine * cost)}</td>
        </tr>`;
    }).join('');

    body.querySelectorAll('input.pb-qty, input.pb-cost-input').forEach(i => i.addEventListener('input', pbRecalc));
    body.querySelectorAll('textarea.pb-desc-input').forEach(pbAutoGrow);
    pbRecalc();
}

// Same idea as the Inspection modal's autoGrow() — a description is a spec,
// not a short label, so the box grows to fit it instead of truncating.
function pbAutoGrow(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

// "Full" button — sets this billing's quantity to everything still
// outstanding on the line, in one click, instead of the operator computing
// and typing it. Requested 2026-09-09 for deliveries accepted as complete
// despite the measured quantity coming in a little under the order (food and
// other weighed/measured goods especially) — the office's call to make, not
// something the form should second-guess, so it just fills the number rather
// than validating it against anything.
function pbFillRemaining(btn) {
    const tr = btn.closest('tr');
    const input = tr.querySelector('input.pb-qty');
    const remaining = pbNum(tr.dataset.remaining);
    input.value = remaining > 0 ? pbQty(remaining) : input.value;
    pbRecalc();
}

function pbRecalc() {
    let total = 0, warn = 0;
    document.querySelectorAll('#pbEditItemsBody tr').forEach(tr => {
        const input = tr.querySelector('input.pb-qty');
        if (!input) return;                         // a caption row
        const qty   = pbNum(input.value);
        // Read live off the Unit Cost box, not the row's original dataset —
        // that box is editable now, and the line total has to track whatever
        // is actually typed there, not what the PO said when the grid loaded.
        const cost  = pbNum(tr.querySelector('.pb-cost-input')?.value);
        const rem   = pbNum(tr.dataset.remaining);
        total += qty * cost;
        tr.classList.toggle('zero', qty === 0);
        tr.querySelector('.pb-line-total').textContent = money(qty * cost);
        // Flagged, never blocked. A lump-sum purchase order line carries no
        // quantity, so "more than ordered" is a judgement the operator makes,
        // not a rule the form can enforce without rejecting valid data.
        //
        // Gated on ORDERED, not on what is left: an item already billed in full
        // has a remainder of zero, and testing the remainder meant the one case
        // most worth flagging — billing an exhausted line again — was the one
        // case that passed silently.
        const ordered = pbNum(tr.dataset.ordered);
        const over = ordered > 0 && qty > rem + 0.0001;
        input.classList.toggle('over', over);
        if (over) warn++;
    });
    document.getElementById('pbEditTotal').textContent = money(total);
    document.getElementById('pbEditHint').textContent = warn
        ? `${warn} item${warn > 1 ? 's are' : ' is'} billed for more than the quantity still outstanding. `
          + 'Saving is allowed — check it is deliberate.'
        : '';
}

function pbCollectItems() {
    const out = [];
    document.querySelectorAll('#pbEditItemsBody tr').forEach(tr => {
        const input = tr.querySelector('input.pb-qty');
        if (!input) return;                         // a caption row
        const qty = pbNum(input.value);
        if (qty <= 0) return;                       // a zero line is simply not in this billing
        out.push({
            item_id:           tr.dataset.itemId || '',
            unit:              tr.querySelector('.pb-unit-input').value.trim(),
            items_description: tr.querySelector('.pb-desc-input').value.trim(),
            quantity:          qty,
            unit_cost:         pbNum(tr.querySelector('.pb-cost-input').value),
        });
    });
    return out;
}

async function pbSave() {
    const poNumber  = document.getElementById('pbPoNumber').value;
    const billingNo = parseInt(document.getElementById('pbEditNo').value, 10);
    const items     = pbCollectItems();
    if (!items.length) return showToast('Enter the quantity for at least one item.', 'danger');

    const existing = (pbData.billings || []).some(b => b.billing_no === billingNo);
    const payload = {
        po_number: poNumber,
        billing_no: billingNo,
        date_from:         document.getElementById('pbEditFrom').value,
        date_to:           document.getElementById('pbEditTo').value,
        delivery_date:     document.getElementById('pbEditDelivery').value,
        invoice_number:    document.getElementById('pbEditInvoice').value.trim(),
        barcode_number:    document.getElementById('pbEditBarcode').value.trim(),
        date_bac_received: document.getElementById('pbEditBac').value,
        remarks:           document.getElementById('pbEditRemarks').value.trim(),
        accepted_by:       document.getElementById('pbEditAccepted').value.trim(),
        items,
    };
    ['1','2','3','4'].forEach(n => payload['inspected_by_' + n] = document.getElementById('pbEditBy' + n).value.trim());
    ['1','2','3','4','5'].forEach(n => payload['end_user' + n] = document.getElementById('pbEditEnd' + n).value.trim());

    const btn = document.getElementById('pbBtnSave');
    btn.disabled = true;
    try {
        const res = await fetch(pbApiUrl, {
            method: existing ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Could not save this billing.', 'danger');
        showToast(data.message, 'success');
        await openBillings(poNumber);   // reload, which lands back on the history pane
        loadList();
    } catch (err) {
        showToast('Could not save this billing.', 'danger');
        console.error(err);
    } finally {
        btn.disabled = false;
    }
}

// Undo one billing. Two outcomes behind one button — restore the version this
// one replaced, or remove the billing altogether when there is nothing behind
// it — so the confirmation states which, exactly as undoInspection() does.
async function pbUndo(billingNo) {
    const poNumber = document.getElementById('pbPoNumber').value;
    const b = (pbData.billings || []).find(x => x.billing_no === billingNo);
    const amount = b ? '₱' + money(b.total) : '';
    const prev = b && b.previous;
    const question = prev
        ? `Undo the last revision of Progress Billing - ${billingNo} on ${poNumber}?\n\n`
          + `The current version (${amount}) is put aside and the version of `
          + `${fmtDate(prev.date_inspected)}`
          + `${prev.invoice_number ? ', invoice ' + prev.invoice_number : ''} is restored.`
        : `Remove Progress Billing - ${billingNo} (${amount}) from ${poNumber}?\n\n`
          + 'Nothing was recorded before this one, so the billing comes off the purchase '
          + 'order entirely and its number is free to use again.';
    if (!confirm(question + '\n\nThe other billings on this purchase order are not affected.')) return;
    try {
        const res = await fetch(pbApiUrl, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ po_number: poNumber, billing_no: billingNo }),
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Could not undo this billing.', 'danger');
        showToast(data.message, 'success');
        await openBillings(poNumber);
        loadList();
    } catch (err) {
        showToast('Could not undo this billing.', 'danger');
        console.error(err);
    }
}

// ── Signatory pickers ──────────────────────────────────────────────────────

// Same reasoning as the supplier fix on the PO form: an advisory notice when a
// typed name is not on the master list, because a name that exists in
// ref_signaturies_inspector but not ref_signaturies prints a blank office.
function refKey(v) { return String(v == null ? '' : v).toUpperCase().replace(/[^A-Z0-9]/g, ''); }

const INSPECTOR_FIELDS = ['inspBy1', 'inspBy2', 'inspBy3', 'inspBy4'];

// Every field on this form that holds a person's name. Widening them helps but
// cannot be a guarantee — there is no length a full name is promised to fit in
// — so each one also carries its own value as a tooltip.
const NAME_FIELDS = INSPECTOR_FIELDS.concat(
    ['inspEnd1', 'inspEnd2', 'inspEnd3', 'inspEnd4', 'inspEnd5', 'inspAccepted']);

// Mirrors each name field's value into its title, so hovering a truncated name
// shows all of it. Cleared when empty rather than left as an empty string,
// which would otherwise pop a blank tooltip on every hover.
function syncNameTitles() {
    NAME_FIELDS.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const v = el.value.trim();
        if (v) el.title = v; else el.removeAttribute('title');
    });
}


// One officer cannot sign twice. Checked here for immediate feedback and again
// on the server, which is what actually enforces it.
function inspectorNotice(id) {
    const field = document.getElementById(id);
    const note = document.getElementById(id + 'Hint');
    if (!note) return;
    const typed = field.value.trim();
    const setNote = (msg) => {
        note.textContent = msg;
        note.classList.toggle('po-field-hint-warn', msg !== '');
    };
    if (typed === '') return setNote('');

    const dupIndex = INSPECTOR_FIELDS.findIndex(other =>
        other !== id && refKey(document.getElementById(other).value.trim()) === refKey(typed));
    if (dupIndex !== -1) {
        return setNote('Already listed as Inspector ' + (dupIndex + 1) + '. Each officer can sign only once.');
    }
    if (!inspectorList.some(p => refKey(p.name) === refKey(typed))) {
        return setNote('Not on the inspector master list — the office may print blank.');
    }
    setNote('');
}

// Each field offers only officers not already chosen in the other three, so a
// duplicate is hard to pick in the first place rather than merely rejected.
function refreshInspectorLists() {
    INSPECTOR_FIELDS.forEach((id, idx) => {
        const taken = INSPECTOR_FIELDS
            .filter(other => other !== id)
            .map(other => refKey(document.getElementById(other).value.trim()))
            .filter(k => k !== '');
        const dl = document.getElementById('dlInspectors' + (idx + 1));
        if (!dl) return;
        dl.innerHTML = inspectorList
            .filter(p => !taken.includes(refKey(p.name)))
            .map(p => `<option value="${escapeHtml(p.name)}">${escapeHtml(p.department || '')}</option>`)
            .join('');
    });
}

function syncInspectors() {
    refreshInspectorLists();
    INSPECTOR_FIELDS.forEach(inspectorNotice);
}

async function loadSignatories() {
    try {
        const res = await fetch(`${apiUrl}?signatories=1`);
        const data = await res.json();
        if (!data.success) return;
        inspectorList = data.inspectors || [];
        officeList = data.offices || [];
        refreshInspectorLists();
        document.getElementById('dlOffices').innerHTML =
            officeList.map(p => `<option value="${escapeHtml(p.name)}">${escapeHtml(p.position || '')}</option>`).join('');
        // The voucher's three signatory boxes offer the same list, so an OIC
        // standing in for the accountant is picked rather than retyped.
        document.getElementById('dvSignatoryList').innerHTML =
            officeList.map(p => `<option value="${escapeHtml(p.name)}">${escapeHtml(p.position || '')}</option>`).join('');
        document.getElementById('dvCategoryList').innerHTML =
            (data.categories || []).map(c => `<option value="${escapeHtml(c)}"></option>`).join('');
    } catch (err) { console.error(err); }
}

// Accepted By's own picklist — ref_signatory (api/master_data.php's
// "signatories" category), NOT the legacy ref_signaturies table dlOffices
// above reads from. Two different tables named almost the same thing: this is
// the one Master Data's Signatories screen writes to, so a name added there
// (e.g. "DIOSCORA R. NIERE, CPA") shows up here, which the legacy table never
// would. Requires Purchase Orders module access the same way the PO screen's
// own signatory fields do; degrades to plain free text (same as before) if
// that fetch fails, rather than blocking the field.
async function loadAcceptedBySignatories() {
    try {
        const res = await fetch('api/master_data.php');
        const data = await res.json();
        if (!data.success) return;
        const rows = (data.categories && data.categories.signatories) || [];
        document.getElementById('dlAcceptedBy').innerHTML =
            rows.map(r => `<option value="${escapeHtml(r.name)}">${escapeHtml(r.extra || '')}</option>`).join('');
    } catch (err) { console.error(err); }
}

// End User autosuggest, off the live HRMIS personnel directory
// (api/inspection.php?employees=1 -> hrmis.tblpersonalinformation, ~9,400
// rows) — too many to dump into a datalist upfront the way the small
// picklists above do, so this searches on demand as the person types instead,
// the same shape Settings > User Management's own employee picker uses.
// Debounced (300ms) so every keystroke doesn't fire a request, and a stale
// response that lands after a newer one can't clobber it — a fast typist
// firing three requests in a row would otherwise risk the SECOND-to-last
// response arriving last and overwriting the datalist with outdated options.
// One shared datalist per modal (dlEndUsers here, dlPbEndUsers in Progress
// Billing) rather than one per field: only one field is ever focused at a
// time, so there is nothing to gain from five separate lists, and Enter/Tab
// still commits into whichever input triggered the fetch.
let employeeSuggestTimer = null;
let employeeSuggestSeq = 0;
function employeeSuggest(el, datalistId) {
    clearTimeout(employeeSuggestTimer);
    const q = el.value.trim();
    if (q === '') { document.getElementById(datalistId).innerHTML = ''; return; }
    const seq = ++employeeSuggestSeq;
    employeeSuggestTimer = setTimeout(async () => {
        try {
            const res = await fetch(`${apiUrl}?employees=1&q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if (!data.success || seq !== employeeSuggestSeq) return; // superseded by a newer keystroke
            document.getElementById(datalistId).innerHTML =
                (data.rows || []).map(r => `<option value="${escapeHtml(r.full_name)}">`).join('');
        } catch (err) { console.error(err); }
    }, 300);
}

// Remarks picklist — tbl_remarks_inspection (api/inspection.php?remarks=1),
// loaded once since it is a short, fixed list (unlike the employee directory
// above), not searched live. Feeds two different widgets: a plain <datalist>
// for Progress Billing's Remarks <input>, and remarksList below for the main
// dialog's Remarks <textarea>, which can't use a <datalist> at all.
let remarksList = [];
async function loadRemarksList() {
    try {
        const res = await fetch(`${apiUrl}?remarks=1`);
        const data = await res.json();
        if (!data.success) return;
        remarksList = data.rows || [];
        document.getElementById('dlRemarks').innerHTML =
            remarksList.map(v => `<option value="${escapeHtml(v)}">`).join('');
    } catch (err) { console.error(err); }
}

// mousedown + preventDefault (not click) so choosing a suggestion never
// blurs the textarea first — same reason purchase_orders.php's own
// suggestion boxes (renderFieldSuggestions()) do it this way: a blur-then-
// click would close the box before the click could land on it.
function renderRemarksSuggestions(fieldId, boxId, query) {
    const box = document.getElementById(boxId);
    const q = query.trim().toLowerCase();
    const matches = q ? remarksList.filter(v => v.toLowerCase().includes(q)) : remarksList;
    if (!matches.length) { box.classList.add('d-none'); return; }
    box.innerHTML = matches.map(v => `<div class="rs-item">${escapeHtml(v)}</div>`).join('');
    box.querySelectorAll('.rs-item').forEach((el, i) => {
        el.addEventListener('mousedown', (e) => {
            e.preventDefault();
            const field = document.getElementById(fieldId);
            field.value = matches[i];
            box.classList.add('d-none');
        });
    });
    box.classList.remove('d-none');
}

async function loadStats() {
    try {
        const res = await fetch(`${apiUrl}?stats=1`);
        const data = await res.json();
        if (!data.success) return;
        const s = data.stats || {};
        document.getElementById('statToday').textContent    = s.inspected_today ?? '—';
        document.getElementById('statMonth').textContent    = s.inspected_month ?? '—';
        document.getElementById('statAwaiting').textContent = s.awaiting ?? '—';
        document.getElementById('statAmount').textContent   = '₱' + money(s.amount_month || 0);
    } catch (err) { console.error(err); }
}

function openModal(id) {
    const el = document.getElementById(id);
    (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
}

// Caps both scroll regions to the modal body's real height, so the dialog never
// grows past the viewport and Save stays reachable. Measured rather than
// guessed in vh — see the note on .insp-left-col-scroll.
function sizeInspModalColumns() {
    const body = document.querySelector('#inspectModal .modal-body');
    if (!body) return;

    // Re-measure the description fields now the modal is actually on screen.
    // renderItems() runs while it is still hidden, where scrollHeight reflects
    // a width the field never has once visible, so every long description came
    // out clipped to one line.
    document.querySelectorAll('#inspItemsBody textarea.item-desc').forEach(autoGrow);
    const avail = body.clientHeight - 24;
    if (avail < 200) return;                       // too small to be meaningful
    const left = document.querySelector('.insp-left-col-scroll');
    if (left) left.style.maxHeight = avail + 'px';

    // The item grid shares its column with the totals row and the hint line,
    // so it gets whatever the card has left rather than the full height.
    const scroll = document.querySelector('.insp-items-scroll');
    const card = scroll ? scroll.closest('.po-card') : null;
    if (scroll && card) {
        const chrome = card.offsetHeight - scroll.offsetHeight;
        scroll.style.maxHeight = Math.max(160, avail - chrome) + 'px';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const today = new Date();
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    const iso = d => d.toISOString().slice(0, 10);
    document.getElementById('filterFrom').value = iso(first);
    document.getElementById('filterTo').value   = iso(today);

    document.getElementById('btnFilter').addEventListener('click', loadList);
    document.getElementById('btnClear').addEventListener('click', () => {
        document.getElementById('filterSearch').value = '';
        document.getElementById('filterFrom').value = iso(first);
        document.getElementById('filterTo').value = iso(today);
        setStatusFilter('');
    });
    document.getElementById('filterSearch').addEventListener('keypress', e => { if (e.key === 'Enter') loadList(); });
    document.getElementById('filterStatus').addEventListener('change', e => setStatusFilter(e.target.value));
    document.querySelectorAll('#countChips .po-chip').forEach(chip =>
        chip.addEventListener('click', () => setStatusFilter(chip.dataset.status || '')));

    document.getElementById('inspectModal').addEventListener('shown.bs.modal', sizeInspModalColumns);
    window.addEventListener('resize', sizeInspModalColumns);

    document.getElementById('btnSaveInspection').addEventListener('click', saveInspection);
    document.getElementById('btnReviseInspection').addEventListener('click', () => {
        inspMode = 'revise';
        // Re-run before applyMode: view mode leaves the receipt box empty on
        // purpose, and revising is the point at which a suggestion becomes
        // useful rather than misleading.
        if (receiptContext) applyReceiptSuggestion(receiptContext.po, receiptContext.existing);
        applyMode();
    });
    document.getElementById('btnUndoInspection').addEventListener('click', () =>
        undoInspection(document.getElementById('inspPoNumber').value));
    document.getElementById('btnPrintInspection').addEventListener('click', () =>
        openIarPrint(document.getElementById('inspPoNumber').value));
    document.getElementById('btnIarPreview').addEventListener('click', openIarPreview);
    document.getElementById('iarInitial').addEventListener('input', (e) =>
        e.target.classList.remove('is-invalid'));
    document.getElementById('inspReceiptUseSuggestion').addEventListener('click', () => {
        if (receiptSuggested) document.getElementById('inspReceiptDate').value = receiptSuggested.iso;
    });
    document.getElementById('iarPaperSize').addEventListener('change', () => {
        writeIarPref('iar.paper', document.getElementById('iarPaperSize').value);
        if (iarPreviewData) renderIarPdf();
    });
    document.getElementById('iarTextSize').addEventListener('change', () => {
        writeIarPref('iar.text', document.getElementById('iarTextSize').value);
        if (iarPreviewData) renderIarPdf();
    });
    document.getElementById('iarDownload').addEventListener('click', downloadIarPdf);
    document.getElementById('iarPrint').addEventListener('click', printIarPdf);
    document.getElementById('iarOpenTab').addEventListener('click', () => {
        if (iarBlobUrl) window.open(iarBlobUrl, '_blank');
    });

    document.getElementById('btnDvPreview').addEventListener('click', () => openDvPreview('payment'));
    document.getElementById('btnDvReimbursement').addEventListener('click', () => openDvPreview('reimbursement'));
    document.getElementById('dvPaperSize').addEventListener('change', () => {
        writeIarPref('dv.paper', document.getElementById('dvPaperSize').value);
        if (dvData) renderDvPdf();
    });
    document.getElementById('dvTextSize').addEventListener('change', () => {
        writeIarPref('dv.text', document.getElementById('dvTextSize').value);
        if (dvData) renderDvPdf();
    });
    document.getElementById('dvDownload').addEventListener('click', downloadDvPdf);
    document.getElementById('dvPrint').addEventListener('click', printDvPdf);
    document.getElementById('dvOpenTab').addEventListener('click', () => {
        if (dvBlobUrl) window.open(dvBlobUrl, '_blank');
    });

    INSPECTOR_FIELDS.forEach(id =>
        document.getElementById(id).addEventListener('input', syncInspectors));
    NAME_FIELDS.forEach(id =>
        document.getElementById(id).addEventListener('input', syncNameTitles));

    document.getElementById('pbBtnNew').addEventListener('click', () => pbOpenEditor(null));
    document.getElementById('pbBtnBack').addEventListener('click', pbShowHistory);
    document.getElementById('pbBtnSave').addEventListener('click', pbSave);
    ['pbEditBy1','pbEditBy2','pbEditBy3','pbEditBy4'].forEach(id =>
        document.getElementById(id).addEventListener('input', pbRefreshPickers));

    loadSignatories();
    loadAcceptedBySignatories();
    loadRemarksList();
    loadStats();
    loadList();

    // Deep link from a notification: inspection.php?po_number=…&view=1
    const q = new URLSearchParams(location.search);
    // …&billing=1 lands on the progress-billing dialog instead of the
    // inspection one, which is what the progress-billing notifications link to.
    if (q.get('po_number')) {
        if (q.get('billing')) openBillings(q.get('po_number'));
        else openInspection(q.get('po_number'), q.get('view') ? 'view' : 'create');
    }
});
</script>
<?php include 'assistant.php'; ?>
</body>
</html>
