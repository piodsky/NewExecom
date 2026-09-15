<?php
require_once 'config.php';
requireAuth();
requireModule('purchase_orders');
$currentUser = getCurrentUser();
$activePage  = 'purchase_orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Orders | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="vendor/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
.table-actions { display: flex; gap: .35rem; flex-wrap: wrap; }
.table-actions .btn { border-radius: 7px; padding: .35rem .55rem; font-size: .78rem; }
.modal-xl { max-width: 1200px; }
/* Create/Edit PO modal specifically — wider than the shared .modal-xl above
   (View/Print Preview modals stay as-is) so the item grid has room to
   breathe with many rows, closer to how the main PO List table stretches. */
.modal-po-wide { max-width: min(1600px, 96vw); }

/* ── Branded modal header — this page's modals only (Settings etc. keep
   their existing plain header) ── */
.po-modal-header {
    background: linear-gradient(135deg, var(--navy), #1e40af);
    color: #fff;
    border-bottom: none;
    padding: 1.1rem 1.5rem;
}
.po-modal-header .modal-title { color: #fff; font-weight: 700; letter-spacing: .01em; }
.po-modal-header .modal-title i { opacity: .9; }
.po-modal-header .btn-close { filter: invert(1); opacity: .85; }
.po-modal-header .btn-close:hover { opacity: 1; }

/* ── Redesigned header for Create/Edit + View: icon badge, title+subtitle,
   custom close button — used instead of the plain .po-modal-header above. ── */
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
.po-header-icon.rounded-circle { border-radius: 50%; }
.po-header-text { flex: 1 1 auto; min-width: 0; }
.po-header-text .modal-title { color: #fff; font-weight: 700; font-size: 1.15rem; margin: 0; }
.po-header-text small { display: block; color: rgba(255,255,255,.72); font-size: .8rem; margin-top: .1rem; }
.po-header-close {
    width: 38px; height: 38px; flex: 0 0 auto; border-radius: 12px;
    border: 1px solid rgba(255,255,255,.3); background: rgba(255,255,255,.12); color: #fff;
    display: flex; align-items: center; justify-content: center; transition: background .15s;
}
.po-header-close.rounded-circle { border-radius: 50%; }
.po-header-close:hover { background: rgba(255,255,255,.25); color: #fff; }

/* ── Card sections inside the Create/Edit + View modals ──
   Colors here are pinned to fixed values rather than the global
   --text/--muted/--border vars, so the cards stay readable on their white
   modal background no matter how those vars are retuned later. ── */
#poModal .modal-content, #poSavedModal .modal-content { color: #1e293b; }
.po-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.25rem 1.4rem; margin-bottom: 1.1rem; }
.po-card-title { display: flex; align-items: center; gap: .55rem; font-weight: 700; color: var(--navy); font-size: 1rem; margin-bottom: 1.1rem; }
.po-card-title i { color: var(--primary); font-size: 1.05rem; }

/* ── "One glance" — the Purchase Order Information card compressed enough
   to see every field without scrolling (3-across field rows + smaller
   controls), instead of the taller 2-across layout the rest of the app uses. ── */
.po-card-compact { padding: .95rem 1.15rem; }
.po-card-compact .po-card-title { margin-bottom: .65rem; font-size: .92rem; }
.po-card-compact .po-field-group { margin-bottom: .4rem; }
.po-card-compact .po-field-group > label { font-size: .72rem; margin-bottom: .15rem; }
.po-card-compact .po-input-wrap > .po-input-icon { width: 22px; height: 22px; font-size: .68rem; }
.po-card-compact .po-input-wrap > .form-control,
.po-card-compact .po-input-wrap > .form-select { padding: .3rem .5rem .3rem 32px; min-height: 32px; font-size: .8rem; }
.po-card-compact .po-input-wrap > textarea.form-control { padding-top: .35rem; }
.po-card-compact .po-input-wrap > textarea.form-control ~ .po-input-icon { top: 14px; }

/* ── Icon-prefixed form fields ── */
.po-field-group { margin-bottom: 1rem; }
/* Tighter field spacing — the row gutter (g-3) and each field's own
   margin-bottom were both adding vertical space, compounding into
   oversized gaps between fields. Applied to every field grid in the
   Create/Edit + Obligation Request modals (row g-2 + this class). */
.tight-field-row .po-field-group { margin-bottom: .6rem; }
.po-field-group > label { font-size: .8rem; font-weight: 600; color: var(--muted); margin-bottom: .3rem; display: block; }
/* Small note under a field — used to say a value was suggested, not saved. */
.po-field-hint { font-size: .72rem; color: var(--muted); margin-top: .25rem; line-height: 1.35; }
.po-field-hint:empty { display: none; }
/* Amber, not red: typing a supplier that is not yet on file is allowed and
   sometimes correct. This is a "look twice", not an error. */
.po-field-hint-warn { color: #92400e; }
.po-input-wrap { position: relative; }
.po-input-wrap > .po-input-icon {
    position: absolute; left: 5px; top: 50%; transform: translateY(-50%);
    width: 28px; height: 28px; border-radius: 8px; background: rgba(37,99,235,.1); color: var(--primary);
    display: flex; align-items: center; justify-content: center; font-size: .78rem; pointer-events: none;
}
.po-input-wrap > .form-control, .po-input-wrap > .form-select { padding-left: 40px; }
.po-input-wrap > textarea.form-control { padding-top: .55rem; }
.po-input-wrap > textarea.form-control ~ .po-input-icon { top: 19px; transform: none; }

/* ── Additional Details "quick fields" ── these are set independently of
   the main Save button (see openQuickUpdate()/saveQuickUpdate()) so that
   saving header/item edits can never silently drag along a change to one
   of these operational tracking fields (or vice versa) — each one is its
   own deliberate action, same as the legacy Generate_* dialogs it replaces. */
.po-quick-value {
    padding-left: 40px; padding-right: 40px; min-height: 32px;
    display: flex; align-items: center; background: #f8fafc; color: #1e293b;
    border: 1px solid var(--border); border-radius: 8px; font-size: .85rem;
    white-space: pre-wrap; word-break: break-word;
}
.po-card-compact .po-quick-value { min-height: 32px; font-size: .8rem; padding: .3rem 40px .3rem 32px; }
.po-quick-edit-btn {
    position: absolute; right: 4px; top: 50%; transform: translateY(-50%);
    width: 24px; height: 24px; padding: 0; border-radius: 6px;
    display: flex; align-items: center; justify-content: center; font-size: .68rem;
    border: none; background: rgba(37,99,235,.12); color: var(--primary); cursor: pointer;
}
.po-quick-edit-btn:hover { background: rgba(37,99,235,.22); }

/* ── View modal info rows ── */
.po-info-grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 1.1rem 1.5rem; }
.po-info-item { display: flex; align-items: flex-start; gap: .7rem; min-width: 0; }
.po-info-icon {
    width: 38px; height: 38px; border-radius: 50%; flex: 0 0 auto;
    background: rgba(37,99,235,.1); color: var(--primary);
    display: flex; align-items: center; justify-content: center; font-size: .95rem;
}
.po-info-icon.green { background: rgba(22,163,74,.12); color: #16a34a; }
.po-info-icon.amber { background: rgba(217,119,6,.12); color: #d97706; }
.po-info-body { min-width: 0; }
.po-info-label { font-size: .74rem; color: #64748b; text-transform: uppercase; letter-spacing: .03em; margin-bottom: .15rem; }
.po-info-value { font-size: .95rem; font-weight: 700; color: #1e293b; word-break: break-word; }
.po-info-divider { border-top: 1px solid #e2e8f0; margin: 1.1rem 0; }

/* ── Summary cards (Total Items / Total Amount) at the bottom of the View modal ── */
.po-summary-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.po-summary-card {
    display: flex; align-items: center; gap: .8rem;
    background: rgba(37,99,235,.05); border: 1px solid #e2e8f0; border-radius: 12px; padding: .9rem 1.1rem;
}
.po-summary-card .po-info-icon { background: rgba(37,99,235,.14); }
.po-summary-card .po-info-value { font-size: 1.3rem; color: var(--navy); }

/* ── Items table header — dark blue, matches the branded modal header,
   layered on top of .app-table/.item table so other tables are unaffected. ── */
.po-items-table thead th {
    background: linear-gradient(135deg, var(--navy), #1e40af) !important;
    color: #fff !important; border-color: transparent !important;
    text-transform: uppercase; font-size: .72rem; letter-spacing: .03em;
}
.po-item-action-btn {
    width: 28px; height: 28px; border-radius: 7px; border: 1px solid;
    display: inline-flex; align-items: center; justify-content: center; font-size: .75rem;
    background: transparent; transition: background .15s;
}
.po-item-action-btn.add { border-color: #16a34a; color: #16a34a; }
.po-item-action-btn.add:hover { background: rgba(22,163,74,.1); }
.po-item-action-btn.remove { border-color: #dc2626; color: #dc2626; }
.po-item-action-btn.remove:hover { background: rgba(220,38,38,.1); }
.po-add-row-link {
    display: block; text-align: center; padding: .6rem; font-size: .85rem; font-weight: 600;
    color: var(--primary); border: 1px dashed var(--border); border-radius: 8px; margin-top: .6rem;
    background: none; width: 100%; cursor: pointer;
}
.po-add-row-link:hover { background: rgba(37,99,235,.05); }

/* ── PO Saved confirmation modal ── */
.po-saved-icon {
    width: 56px; height: 56px; border-radius: 50%; margin: 0 auto .9rem;
    background: rgba(22,163,74,.12); color: #16a34a;
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
}
.po-saved-number {
    font-size: 1.15rem; font-weight: 700; color: var(--navy);
    background: rgba(37,99,235,.06); border: 1px solid rgba(37,99,235,.18);
    border-radius: 8px; padding: .5rem .75rem; margin-top: .25rem;
    word-break: break-all;
}
.po-fund-option {
    display: block; width: 100%; text-align: center; background: #fff;
    border: 2px solid var(--border); border-radius: 10px; padding: 1.1rem .75rem;
    font-weight: 600; font-size: 1.02rem; transition: background .15s, border-color .15s;
}
.po-fund-option small { display: block; font-weight: 400; opacity: .7; margin-top: .2rem; }
.po-fund-option.po-fund-gf  { color: #15803d; border-color: rgba(21,128,61,.35); }
.po-fund-option.po-fund-tf  { color: #1d4ed8; border-color: rgba(29,78,216,.35); }
.po-fund-option.po-fund-sef { color: #c2410c; border-color: rgba(194,65,12,.35); }
.po-fund-option.po-fund-gf:hover  { background: rgba(21,128,61,.08); }
.po-fund-option.po-fund-tf:hover  { background: rgba(29,78,216,.08); }
.po-fund-option.po-fund-sef:hover { background: rgba(194,65,12,.08); }

/* ── Status badges ── */
.badge-status.pending { background: linear-gradient(135deg,#d97706,#f59e0b); color: #fff; }
.badge-status.complete { background: linear-gradient(135deg,#16a34a,#22c55e); color: #fff; }
.badge-status.locked { background: linear-gradient(135deg,#475569,#64748b); color: #fff; }
/* Manual Status override (see PO_QUICK_FIELDS.status / statusBadgeHtml()) —
   replaces the pending/complete badge above when set. */
.badge-status.st-onhold { background: linear-gradient(135deg,#7c3aed,#a78bfa); color: #fff; }
.badge-status.st-terminated { background: linear-gradient(135deg,#b91c1c,#ef4444); color: #fff; }
.badge-status.st-cancelled { background: linear-gradient(135deg,#475569,#64748b); color: #fff; }
/* Additional Details "Status" field itself, colored the same as its badge. */
.po-quick-value.is-status-onhold { color: #7c3aed; font-weight: 600; }
.po-quick-value.is-status-terminated { color: #b91c1c; font-weight: 600; }
.po-quick-value.is-status-cancelled { color: #475569; font-weight: 600; }
/* Remarks flagged red specifically when Status is Terminated — see
   applyRemarksStatusColor(). */
.po-quick-value.is-terminated { color: #b91c1c; font-weight: 600; }

/* ── Count chips in the list header ──
   Both a readout and a control: each shows how many POs in the current
   date/search scope sit in that state, and clicking one applies it as the
   Status filter. Tinted to match the .badge-status colours the Status column
   already uses, so the same state reads the same way in both places. */
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
.po-chip-pending  { border-color: #fcd34d; color: #b45309; }
.po-chip-pending  .po-chip-num { color: #b45309; }
.po-chip-approved { border-color: #86efac; color: #15803d; }
.po-chip-approved .po-chip-num { color: #15803d; }
/* The chip matching the active filter reads as pressed, so the table's
   contents and the highlighted chip always agree. */
.po-chip.is-active { background: linear-gradient(135deg,#1e3a8a,#2563eb); border-color: transparent; color: #fff; }
.po-chip.is-active .po-chip-num { color: #fff; }
.po-chip-pending.is-active  { background: linear-gradient(135deg,#d97706,#f59e0b); }
.po-chip-approved.is-active { background: linear-gradient(135deg,#16a34a,#22c55e); }
/* Not a status like the other three — a data-quality flag, tinted to match the
   .po-missing-pill in the Supplier column so the chip and the cells it counts
   read as the same thing. */
.po-chip-nosupplier { border-color: #fcd34d; color: #b45309; background: #fffbeb; }
.po-chip-nosupplier .po-chip-num { color: #b45309; }
.po-chip-nosupplier:hover { background: #fef3c7; }
.po-chip-nosupplier.is-active { background: linear-gradient(135deg,#b45309,#f59e0b); color: #fff; }

/* ── Item grid — uses .app-table (same as the main PO list) for the header/
   row look, just with a couple of item-grid-specific tweaks layered on ── */
#poItemsTable td { vertical-align: middle; }
/* Compact rows — a PO with ~200 items needs to fit many rows on screen at
   once, not scroll forever. Cell padding + control padding/height are all
   trimmed down together so a row stays visually balanced, just shorter. */
#poItemsTable td { padding: .3rem .4rem; }
#poItemsTable input.form-control-sm {
    border-radius: 6px; padding: .25rem .45rem; min-height: 28px; font-size: .8rem;
}
#poItemsTable input[data-field="total_cost"] { font-weight: 600; }
/* Grows with its content (see autoGrow() in addItemRow()) instead of
   clipping a long item spec — min-height keeps it flush with the other
   single-line inputs in the row when the text is short. */
#poItemsTable textarea.item-desc-input {
    border-radius: 6px; resize: none; overflow: hidden; min-height: 28px;
    padding: .25rem .45rem; font-size: .8rem; line-height: 1.3; display: block;
}
#poItemsTable .btn-group-sm .btn { padding: .2rem .4rem; }
/* Scrolls internally with a sticky header once the grid grows past a
   comfortable height, instead of pushing Save off the bottom of a modal
   that's already scrolling as a whole (modal-dialog-scrollable). max-height
   is set in px by JS (sizePoModalColumns()), same reasoning as
   .po-left-col-scroll below — a hardcoded 46vh guess cut a tall row (e.g. a
   multi-line item description) off mid-line right at the boundary, with no
   scroll affordance visible, instead of either showing it in full or making
   the overflow obviously scrollable. min-height:0 overrides the flex-item
   default of min-height:auto (which sizes to content) — without it, a flex
   child ignores max-height/overflow and just grows to fit a long items
   table anyway, the classic flexbox "my overflow:auto child won't actually
   scroll" bug. Only matters here because .po-items-table's parent .po-card
   is d-flex flex-column. */
.items-scroll-wrap { overflow-y: auto; min-height: 0; }

/* Left column's own scroll region — max-height is set in px by JS
   (sizePoModalColumns(), called whenever #poModal opens/resizes) to match
   the modal-body's real available height at that moment. A CSS vh guess
   was tried first and rejected: it either clipped a short/empty form that
   would've fit fine, or failed to cap a long one, since a vh percentage
   doesn't track the modal's actual rendered height (header/footer size,
   dialog margins) the way a direct measurement does. */
.po-left-col-scroll { overflow-y: auto; padding-right: .3rem; }
/* Sticky only — no background/color set here, so it keeps exactly whatever
   .app-table thead th (css/base.css) already defines, same as the main PO
   list's header. */
#poItemsTable thead th { position: sticky; top: 0; z-index: 2; }
#poSubtotal { font-size: 1.4rem; font-weight: 700; color: var(--navy); }
/* Auto-growing text fields (Purpose, Department, Supplier Name, Mode of
   Procurement) — starts as a single line, grows taller as typed/pasted
   text wraps (see growField() in the script below), instead of scrolling
   or clipping long values. No manual resize handle since height is automatic. */
.po-autogrow { resize: none; overflow: hidden; }

/* ── View mode — #poModal reused as a read-only view (see
   setPoModalReadOnly() in the script below) instead of a separate template,
   so View always looks exactly like Add/Edit. .po-edit-only elements (Add
   Item, Import Items, Auto Number Lots, Add another row, Save) disappear;
   the items grid's Action column disappears too (nothing left to act on);
   every disabled field gets a flat, clearly-inert look instead of the
   default greyed-out browser disabled style, which read as "broken" more
   than "read-only" against this app's own form styling. ── */
.po-view-mode .po-edit-only { display: none !important; }
.po-view-mode #poItemsTable th:last-child,
.po-view-mode #poItemsTable td:last-child { display: none; }
.po-view-mode .po-input-wrap > .form-control:disabled,
.po-view-mode .po-input-wrap > .form-select:disabled,
#poItemsTable input:disabled, #poItemsTable textarea:disabled {
    background: #f8fafc; color: #1e293b; opacity: 1; cursor: default;
}

/* ── Create/Edit PO — two-column layout (fields left, items right) ── */
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
.po-items-footer-bar {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem;
    background: rgba(37,99,235,.05); border: 1px solid #e2e8f0; border-radius: 10px;
    padding: .7rem 1.1rem; margin-top: .75rem;
}
.po-items-footer-bar .po-items-count { color: #475569; font-size: .9rem; }
.po-items-footer-bar .po-items-count strong { color: #1e293b; }
.po-item-idx { text-align: center; color: #94a3b8; font-size: .8rem; width: 3%; }

/* ── Excel/CSV import dropzone ── */
.import-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    color: #64748b;
    cursor: pointer;
    transition: background .2s, border-color .2s;
}
.import-dropzone:hover { border-color: var(--primary); }
.import-dropzone.dragover {
    background: rgba(37,99,235,.05);
    border-color: #2563eb;
    color: #1d4ed8;
}
.import-errors { max-height: 260px; overflow-y: auto; border-radius: 10px; }

.import-guide {
    background: rgba(37,99,235,.06); border: 1px solid rgba(37,99,235,.2);
    border-radius: 8px; padding: .65rem .9rem; font-size: .82rem; color: #1e293b;
}
.import-guide code { font-size: .8rem; }

/* ── Print Preview — a faithful recreation of the office's actual printed,
   COA-audited PO form (BAC letterhead, bordered frame, signature block),
   replacing the old Crystal Reports layout the legacy VB.NET app rendered.
   Verified directly against the Crystal Report .rpt source (field names,
   parameter placeholders) rather than guessed from a low-res screenshot. ── */
.pp-preview-body { background: #e2e8f0; padding: 24px; min-height: 60vh; }
/* Clipped to zero visible height rather than shoved off-screen with a large
   negative offset — html2canvas's bounding-box math can misfire on elements
   positioned way outside the viewport in some browsers, producing a 0x0
   canvas (surfaces as jsPDF's "Invalid argument passed to jsPDF.scale").
   .print-page itself keeps its own natural offsetHeight regardless of the
   parent's overflow:hidden, so html2canvas still measures it correctly. */
.pp-offscreen-source { position: absolute; top: 0; left: 0; height: 0; overflow: hidden; }
.pdf-preview-frame { width: 100%; height: 70vh; border: none; background: #fff; }
.print-page {
    font-family: 'Times New Roman', Times, serif; font-size: 12px; color: #000;
    background: #fff; width: 816px; max-width: 100%; margin: 0 auto;
    box-shadow: 0 4px 18px rgba(0,0,0,.18);
}
/* Slimmer margin/padding than before — 2026-09-14, "don't make big margin of
   the left and right" — so the form uses more of the paper's actual
   printable width. Shared by the PO printout and the Obligation Request,
   which is why the full-page min-height/footer-push below lives on a
   separate modifier class rather than here: the Obligation Request's
   .print-page is deliberately narrower (650px, see buildObligationPreviewHtml)
   and was never meant to fill a whole sheet the way the PO form is. */
.pp-frame { border: 1.5px solid #000; margin: 8px; padding: 10px 14px 8px; }
/* Full-page treatment, PO printout only (see buildPoPreviewHtml) — a min-
   height pegged to roughly a full sheet at 816px width, with the signature
   row pushed to the bottom via margin-top:auto below, so a short PO (few
   items) still reads as a whole page instead of stopping halfway down one.
   Same idea as .iar-frame/.dv-frame/.inv-frame in the other print modules. */
.pp-frame-full { display: flex; flex-direction: column; min-height: 1010px; }
.pp-frame-full > table.pp-sign-row:last-child { margin-top: auto; }
/* The barcode is overlaid, not a table cell, so the letterhead keeps the exact
   [seal][text][seal] geometry it had before the barcode existed — a symmetric
   row is what centres the address block on the page. Giving the barcode its own
   <td> stole ~200px from the right and visibly shoved the text off-centre. */
.pp-letterhead-wrap { position: relative; }
.pp-letterhead { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
.pp-letterhead td { vertical-align: middle; text-align: center; }
.pp-seal { width: 90px; }
.pp-seal img { width: 82px; height: 82px; object-fit: contain; }
/* Code 128 of the PO number, floated in the letterhead left of the BAC seal.
   The img's width is set inline from poBarcodeDisplayWidth() — exactly half the
   PNG's natural width, so each bar falls on a whole pixel. A fractional ratio
   blurs the bar edges and scanners stop reading it. It is not hardcoded here
   because the symbol width depends on the PO number's length.
   Centred vertically with a negative margin rather than a transform: the print
   is rasterised by html2canvas, and plain box offsets rasterise identically
   everywhere while transforms do not. -25px is half the block's 49px height
   (38px bars + 1px gap + ~10px caption). */
.pp-barcode-float {
    position: absolute; right: 96px; top: 50%; margin-top: -25px;
    text-align: center;
}
.pp-barcode { height: 38px; display: block; margin: 0 auto; image-rendering: crisp-edges; }
.pp-barcode-caption {
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 8.5px; letter-spacing: .06em; margin-top: 1px; color: #000;
}
.pp-letterhead-text { font-size: 10.5px; line-height: 1.3; color: #1e3a8a; }
.pp-letterhead-text strong { color: #000; }
.pp-letterhead-text a { color: #1d4ed8; text-decoration: none; }
.pp-rule { border: none; border-top: 3px solid #000; margin: 4px 0 8px; }
/* Lighter blue than the letterhead's #1e3a8a, as requested. Kept at #3b82f6
   rather than anything paler: the title is the one line on the form that has to
   survive a photocopy, and lighter than this starts dropping out to grey. */
.pp-title-center {
    text-align: center; font-size: 22px; font-weight: 700; letter-spacing: .04em;
    margin: 6px 0 8px; color: #3b82f6;
}
.pp-underline { border-bottom: 1px solid #000; padding: 0 4px; display: inline-block; min-width: 120px; }
.pp-bold { font-weight: 700; }
.pp-header-grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; border: 1px solid #000; }
.pp-header-grid td { border: 1px solid #000; padding: 3px 6px; vertical-align: middle; }
.pp-header-grid .pp-hg-label { font-weight: 700; width: 15%; white-space: nowrap; }
.pp-header-grid .pp-hg-value { width: 35%; }
/* Supplier phone number, printed under the address rather than in a row of its
   own — only 45 of 811 suppliers have one on file, so a dedicated row would be
   empty on most purchase orders. */
.pp-supplier-contact { margin-top: 2px; }
.pp-gentlemen { margin: 10px 0; }
.pp-indent { display: inline-block; padding-left: 24px; }
.pp-note { margin: 4px 0; }
.pp-po-number { font-weight: 700; }
.pp-block3 { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.pp-block3 td { vertical-align: top; padding: 1px 4px; }
.pp-block3 .col1 { width: 55%; }
.pp-block3 .col3 { width: 45%; white-space: nowrap; }
.pp-block3 div { margin-bottom: 3px; }
.pp-underline-sm { min-width: 32px; }
.pp-items { width: 100%; border-collapse: collapse; margin: 6px 0; }
.pp-items th, .pp-items td { border: 1px solid #000; padding: 3px 6px; font-size: 12px; }
/* Top, not middle: once a description runs to 40 lines the default
   vertical centring floats No./QTY/Units/costs into the middle of the
   cell, far from the item they belong to. The legacy form lines them up
   with the first line of the description. */
.pp-items td { vertical-align: top; }
.pp-items th { text-align: center; }
.pp-items td.num { text-align: right; }
.pp-items td.center { text-align: center; }
/* Item descriptions are written as a multi-line block — a job title, then a
   SCOPE OF WORK list, indented with spaces. 51,000+ existing item rows are
   already stored that way. Default HTML collapses every run of whitespace,
   which turned all of it into one unreadable paragraph on the printed form;
   pre-wrap keeps the line breaks and the indentation, while still wrapping
   any single line that is too wide for the column.
   A wrapped line hangs back to the left edge rather than under its own
   indent, which matches how the legacy printout behaves. */
.pp-items td.desc { white-space: pre-wrap; }
/* One line of an item description. Block-level purely so the PDF pager can
   break between lines — visually it must be indistinguishable from the
   pre-wrap text it replaced, so no margin, no padding, inherited line-height. */
.pp-items td.desc .pp-dl { white-space: pre-wrap; margin: 0; padding: 0; }
/* Item text size, stepped up one or two pixels from the 11px default.
   Applied to the whole grid rather than to the description cell alone: the
   No./QTY/Unit/cost cells are top-aligned so they line up with the FIRST LINE
   of the description, and growing only the description would push that first
   line down and break the alignment the form depends on. */
.print-page.pp-text-1 .pp-items th, .print-page.pp-text-1 .pp-items td { font-size: 13px; }
.print-page.pp-text-2 .pp-items th, .print-page.pp-text-2 .pp-items td { font-size: 14px; }

/* Paper / text-size controls above the PDF preview. Deliberately here and not
   back in the print options dialog: both settings are judged by their effect on
   the page count, which is only visible once the PDF exists. */
.pp-toolbar {
    display: flex; flex-wrap: wrap; align-items: center; gap: .75rem;
    background: #fff; border: 1px solid #cbd5e1; border-radius: 10px;
    padding: .5rem .75rem; margin-bottom: .75rem;
}
.pp-tool { display: flex; align-items: center; gap: .4rem; }
.pp-tool label {
    margin: 0; font-size: .72rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .03em; color: #64748b; white-space: nowrap;
}
.pp-tool select { width: auto; min-width: 12rem; font-size: .82rem; }
.pp-layout-status {
    margin-left: auto; font-size: .8rem; color: #334155;
    display: flex; align-items: center; gap: .35rem;
}
.pp-layout-status .pp-sheets { font-weight: 700; color: var(--navy, #1e3a8a); }
/* Amber, not red: an automatic change of paper or a shrink-to-fit is a normal
   outcome the operator simply needs to know about before they hit Print. */
.pp-layout-status .pp-layout-note { color: #b45309; }


/* Print-type chooser: the two options differ only in where the No. column
   gets its numbers, which is impossible to guess from the labels alone. */
.print-type-help { font-size: .78rem; color: var(--muted); margin: .1rem 0 .5rem 1.9rem; line-height: 1.35; }
.print-hint { font-size: .8rem; border-radius: 10px; padding: .55rem .75rem; display: flex; gap: .5rem; align-items: flex-start; }
.print-hint i { margin-top: .12rem; }
.print-hint-sub { display: block; margin-top: .2rem; opacity: .75; }
.print-hint.ok   { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }
.print-hint.warn { background: #fffbeb; border: 1px solid #fde68a; color: #b45309; }

/* A heading row — "LOT 1", "SCOPE OF WORK:" — is a label, not a priced item.
   Its unit/quantity/cost boxes are always empty, so the grid hides them rather
   than showing five blank inputs to skip past. The inputs stay in the DOM
   (emptied), so saving, Auto Number Lots and the print builder go on reading
   the row exactly as before — this is presentation only. */
.po-item-title > td { background: #f1f5f9; }
.po-item-title input { display: none; }
.po-item-title .item-desc-input {
    font-weight: 700; color: var(--navy); background: #f1f5f9;
    border-color: transparent; box-shadow: none;
}
.po-title-dash { display: none; color: #cbd5e1; text-align: center; font-size: .8rem; }
.po-item-title .po-title-dash { display: block; }
.po-item-action-btn.active { background: #e0edff; color: #1d4ed8; }
/* A row the save refused, so the message and the offending row are not in
   two different places. Cleared on the next save attempt. */
.po-item-invalid > td { background: #fff1f2; }
/* Example rows put into the grid by the guide. Marked hard enough that
   nobody mistakes one for their own data, and the save refuses while any
   are still present — the point of the demo is to teach the rules, not to
   become the first junk rows in a new purchase order.
   The DEMO label is a ::after rather than markup because refreshItemsSummary()
   rewrites that cell's textContent on every add/remove. */
.po-item-demo > td { background: #fffbeb; }
.po-item-demo > td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
.po-item-demo > td.po-item-idx::after {
    content: 'DEMO'; display: block; margin-top: .15rem;
    font-size: .58rem; font-weight: 700; letter-spacing: .04em; color: #b45309;
}
.po-item-invalid .item-desc-input { border-color: #fca5a5; }

/* Worked example inside the Import Items guide. Showing the file rather than
   describing it is the difference between people getting it right first time
   and guessing — the rule (all-blank = heading, all-filled = item) is easy to
   see and awkward to put into a sentence. */
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
.pp-nothing-follows { text-align: center; font-style: italic; }
/* The initial the receiving clerk writes beside "nothing follows" — the legacy
   Crystal report's ?initial parameter. It sits inside the same cell, directly
   after the text, so the two centre as one line; upright and bold against the
   italic so it reads as a signature rather than part of the sentence. */
.pp-initial { margin-left: 18px; font-style: normal; font-weight: 700; letter-spacing: .06em; white-space: nowrap; }
.pp-lot-title { background: #e5e7eb; font-weight: 700; padding: 4px 7px; border: 1px solid #000; border-bottom: none; margin-top: 8px; }
.pp-summary-box { border: 1px solid #000; border-top: none; margin-bottom: 0; }
.pp-totals { width: 100%; border-collapse: collapse; }
.pp-totals td { padding: 4px 6px; vertical-align: middle; }
.pp-total-label { text-align: right; font-weight: 700; width: 55px; white-space: nowrap; }
.pp-total-value { text-align: right; font-weight: 700; border-left: 1px solid #000; width: 100px; }
.pp-purpose-row { width: 100%; border-collapse: collapse; border-top: 1px solid #000; }
.pp-purpose-row td { padding: 5px 6px; vertical-align: top; }
.pp-purpose-label { font-weight: 700; font-style: italic; white-space: nowrap; width: 80px; }
.pp-terms { font-size: 9.5px; margin: 6px 0 22px; line-height: 1.4; }
/* Verification QR — sits at the right of the Conforme band, above the
   approving signatory. The symbol carries a URL plus an encrypted token, so
   it runs ~41 modules at EC level L; at 132px on an 816px-wide (8.5in) page
   that puts each module near 0.8mm, comfortably above the ~0.4mm floor a
   phone camera needs. Shrinking .pp-qr much below 120px makes scans fail. */
.pp-conforme-band { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
.pp-conforme-cell { font-weight: 700; vertical-align: bottom; }
.pp-qr-cell { width: 152px; text-align: center; vertical-align: bottom; }
.pp-qr { width: 132px; height: 132px; display: block; margin: 0 auto; }
.pp-qr-caption { font-size: 8px; line-height: 1.3; margin-top: 2px; }
.pp-qr-scan { font-size: 7.5px; color: #333; letter-spacing: .01em; }
.pp-sign-row { width: 100%; border-collapse: collapse; }
.pp-sign-row td { width: 50%; vertical-align: bottom; padding-top: 36px; }
.pp-sign-left { text-align: center; }
.pp-sign-right { text-align: center; }
.pp-sign-line { border-top: 1px solid #000; width: 75%; margin: 0 auto; }
.pp-sign-caption { font-style: italic; margin-top: 2px; font-size: 10.5px; }
.pp-sign-name { font-weight: 700; }
.pp-sign-title { margin-top: 2px; }

/* ── Obligation Request print — copied field-for-field from a real printed
   sample the user supplied (the actual ORS/Obligation Request form used by
   this LGU), not approximated. Scoped with an orq- prefix since its grid
   shape (thin borders throughout, checkboxes, two-column certification/
   signature blocks) is genuinely different from the PO report's layout
   above, not a variant of it. ── */
.orq-letterhead-wrap { position: relative; }
.orq-letterhead { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
/* Overlaid for the same reason as .pp-barcode-float — see the note there. This
   form is narrower (650px) and has no right-hand seal, so the barcode sits hard
   in the top-right corner and rides slightly above the address block's
   baseline to stay clear of its longest line. */
.orq-barcode-float { position: absolute; right: 0; top: -2px; text-align: center; }
.orq-seal { width: 90px; text-align: center; vertical-align: middle; }
.orq-seal img { width: 76px; height: 76px; object-fit: contain; }
.orq-letterhead-text { text-align: center; font-size: 10.5px; line-height: 1.4; vertical-align: middle; }
.orq-letterhead-text .blue { color: #1d4ed8; }
.orq-letterhead-text .blue.bold { font-weight: 700; }
.orq-rule { border: none; border-top: 1px solid #000; margin: 2px 0 6px; }

.orq-grid { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 0; }
.orq-grid td { border: 1px solid #000; padding: 3px 7px; vertical-align: middle; font-size: 12px; }
/* 20px on this form's 650px page is the same visual weight as the Purchase
   Order's 22px title on its 816px page. */
.orq-title-cell { font-size: 20px; font-weight: 700; color: #1d4ed8; text-decoration: underline; text-align: center; }
.orq-label { font-weight: 600; white-space: nowrap; width: 16%; }
.orq-value { width: 34%; }

.orq-particular-header { text-align: center; font-weight: 700; font-size: 11px; }
.orq-particular-body { text-align: left; vertical-align: top; font-size: 12px; }
.orq-amount-cell { text-align: right; vertical-align: top; font-weight: 700; white-space: nowrap; }
.orq-pesos-label { font-weight: 600; }
.orq-words { color: #c2410c; font-style: italic; }
.orq-total-label { font-weight: 700; text-align: right; white-space: nowrap; }
.orq-total-value { font-weight: 700; text-align: right; white-space: nowrap; }

.orq-cert-grid { width: 100%; border-collapse: collapse; }
.orq-cert-grid td { border: 1px solid #000; border-top: none; padding: 6px 8px; vertical-align: top; font-size: 11px; width: 50%; }
.orq-cert-letter { display: inline-block; border: 1px solid #000; width: 15px; height: 15px; text-align: center; line-height: 14px; font-weight: 700; margin-right: 4px; }
.orq-checkbox-row { display: flex; align-items: flex-start; gap: 5px; margin-top: 4px; }
.orq-checkbox { width: 10px; height: 10px; border: 1px solid #000; flex: 0 0 auto; margin-top: 2px; }

.orq-sign-grid { width: 100%; border-collapse: collapse; }
.orq-sign-grid td { border: 1px solid #000; border-top: none; padding: 4px 8px; font-size: 11px; }
.orq-sign-grid .lbl { font-weight: 600; width: 15%; white-space: nowrap; }
.orq-sign-grid .val { width: 35%; }

@media print {
    body * { visibility: hidden; }
    #printPreviewArea, #printPreviewArea * { visibility: visible; }
    #printPreviewArea { position: absolute; left: 0; top: 0; width: 100%; }
    .print-page { box-shadow: none; width: 100%; margin: 0; }
}

/* ── Purpose autosuggest — textareas can't use <datalist>, so this is a
   small custom dropdown positioned under the field instead. ── */
.purpose-suggestions {
    position: absolute; left: 0; right: 0; top: 100%; z-index: 20;
    max-height: 340px; overflow-y: auto; margin-top: 2px;
    background: #fff; border: 1px solid var(--border);
    border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12);
}
.purpose-suggestions .ps-item {
    padding: .5rem .75rem; cursor: pointer; font-size: .85rem;
    display: flex; justify-content: space-between; gap: .5rem;
}
.purpose-suggestions .ps-item:hover,
.purpose-suggestions .ps-item.active { background: var(--primary); color: #fff; }
.purpose-suggestions .ps-item .ps-uses { opacity: .65; font-size: .75rem; white-space: nowrap; }

/* ── PO / PR number pills in the list ──
   Orange = PO Number, blue = PR Number, so the two similar-looking codes are
   tellable apart at a glance. These are <span>s rather than a background on
   the <td> because .table-hover paints an inset box-shadow across the whole
   cell on hover, which would sit on top of any td background-color. */
.po-num-pill, .pr-num-pill {
    display: inline-block; padding: .18rem .55rem; border-radius: 6px;
    font-size: .8rem; font-weight: 700; letter-spacing: .01em;
    white-space: nowrap; border: 1px solid transparent;
}
.po-num-pill { color: #c2410c; background: #fff2e6; border-color: #fed7aa; }
.pr-num-pill { color: #1d4ed8; background: #e8f0ff; border-color: #bfdbfe; }
.pr-num-empty { color: var(--muted); }
.pr-num-group { display: inline-flex; flex-wrap: wrap; gap: .25rem; }
/* Tints are opaque so the pills read cleanly against the white table cell. */
/* Rows can now be several lines tall — top-aligning the whole row keeps the
   short columns lined up with the first line of the purpose, not floating
   in the middle of it. */
#poTable tbody td { vertical-align: top; }
/* A PO with no supplier is a gap in the record, not just an empty cell — it
   gets a warning pill (same shape/tint as the Pending count chip) instead of
   the quiet — used for the other optional columns. */
.po-missing-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .55rem; border-radius: 20px;
    font-size: .72rem; font-weight: 600; white-space: nowrap;
    border: 1px solid #fcd34d; background: #fffbeb; color: #b45309;
}
/* Purpose is free text and often a full sentence — shown in full, wrapping
   onto as many lines as it needs. Capped in width only, so it wraps instead
   of pushing the rest of the columns off to the right. */
.po-purpose-cell {
    display: block; max-width: 320px; min-width: 160px;
    white-space: normal; word-break: break-word;
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
                <div class="page-title">Purchase Orders</div>
                <div class="page-subtitle">Modern purchase order management with modal workflows and flexible import.</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="d-flex gap-2 no-print">
                <button class="btn btn-primary-app btn-sm" id="btnAddPO"><i class="fas fa-plus me-1"></i> New PO</button>
            </div>
            <?php include 'topbar.php'; ?>
        </div>
    </div>
    <div class="content-area">
        <div class="row g-3 mb-3 no-print">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div>
                        <div class="stat-value" id="statPosToday">—</div>
                        <div class="stat-label">POs Today</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon teal"><i class="fas fa-calendar-days"></i></div>
                    <div>
                        <div class="stat-value" id="statPosMonth">—</div>
                        <div class="stat-label">POs This Month</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-peso-sign"></i></div>
                    <div>
                        <div class="stat-value" id="statAmountMonth" style="font-size:1.15rem;">—</div>
                        <div class="stat-label">Amount This Month</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon amber"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="stat-value" id="statPendingApproval">—</div>
                        <div class="stat-label">Pending Approval</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel mb-3 no-print">
            <div class="panel-body">
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" id="searchText" class="form-control" placeholder="PO/PR number, barcode, department, supplier, or date">
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
                        <!-- Same two states the table's Status column shows: a PO
                             counts as Approved once Date_Approved_PO is set.
                             "No supplier" is a data-quality cut rather than a
                             state — it overlaps both, which is why it lives
                             here as a filter and not in the Status column. -->
                        <select id="filterStatus" class="form-select" onchange="loadPurchaseOrders()">
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="no_supplier">No supplier</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3 d-flex gap-2">
                        <button class="btn btn-primary-app w-100" onclick="loadPurchaseOrders()"><i class="fas fa-search me-1"></i> Filter</button>
                        <button class="btn btn-outline-secondary w-100" onclick="resetFilters()"><i class="fas fa-eraser me-1"></i> Clear</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="panel-title"><i class="fas fa-list me-2 text-primary"></i>Purchase Order List</span>
                <!-- Counts for the current date/search scope. Clicking one sets
                     the Status filter, so the numbers double as the control. -->
                <div class="po-count-chips no-print">
                    <button type="button" class="po-chip" data-status="" onclick="setStatusFilter('')">
                        Total <span class="po-chip-num" id="countTotal">—</span>
                    </button>
                    <button type="button" class="po-chip po-chip-pending" data-status="pending" onclick="setStatusFilter('pending')">
                        Pending <span class="po-chip-num" id="countPending">—</span>
                    </button>
                    <button type="button" class="po-chip po-chip-approved" data-status="approved" onclick="setStatusFilter('approved')">
                        Approved <span class="po-chip-num" id="countApproved">—</span>
                    </button>
                    <button type="button" class="po-chip po-chip-nosupplier" data-status="no_supplier" onclick="setStatusFilter('no_supplier')">
                        <i class="fas fa-triangle-exclamation"></i>No supplier <span class="po-chip-num" id="countNoSupplier">—</span>
                    </button>
                </div>
            </div>
            <div class="panel-body">
                <div id="poListLoading" class="text-center text-muted py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="small mt-2">Loading purchase orders…</div>
                </div>
                <table id="poTable" class="app-table table table-hover table-bordered d-none" style="width:100%">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>PR Number</th>
                            <th>Supplier</th>
                            <th>Department</th>
                            <th>Category</th>
                            <th>Purpose</th>
                            <th>Date Created</th>
                            <th>Approved</th>
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

<!-- Purchase Order Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="poModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-po-wide modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header po-modal-header-v2">
        <div class="po-header-icon"><i class="fas fa-cart-shopping"></i></div>
        <div class="po-header-text">
            <h5 class="modal-title" id="poModalTitle">Create Purchase Order</h5>
            <small id="poModalSubtitle">Fill in the details below to create a new Purchase Order.</small>
        </div>
        <button type="button" class="po-header-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body">
        <form id="poForm">
          <input type="hidden" id="poId" name="po_id">

          <!-- Every free-text field in this modal is now an auto-growing
               <textarea> (see .po-autogrow / growField() below) rather than
               a single-line <input> — <datalist> doesn't work on a
               <textarea>, so every field that used to have a native
               datalist dropdown (Department, Division, Supplier Name,
               PR Received Name, Mode of Procurement, Place of Delivery,
               Working Days, Payment Term, Purpose) now uses the same custom
               growable-textarea suggestion dropdown instead — see
               bindGrowSuggestField() below. -->

          <div class="row g-4">
            <!-- Left column — form fields. Wrapped in its own scroll region
                 (same idea as .items-scroll-wrap on the right) so a record
                 with a lot of filled-in/wrapped fields scrolls internally
                 instead of growing the whole modal — see the flex-chain
                 comment above .po-left-col-scroll in the <style> block. -->
            <div class="col-lg-5">
              <div class="po-left-col-scroll">
              <div class="po-card po-card-compact">
                <div class="po-card-title"><i class="fas fa-file-invoice"></i> Purchase Order Information</div>
                <div class="row g-2 tight-field-row">
                  <div class="col-md-4 po-field-group"><label>PO Number</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-file-invoice"></i></span><input type="text" id="poNumber" class="form-control" readonly placeholder="Auto generated"></div></div>
                  <div class="col-md-4 po-field-group"><label>PR Number</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-clipboard-list"></i></span><textarea id="prNumber" name="pr_number" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div></div>
                  <div class="col-md-4 po-field-group"><label>Date Purchase Request</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar-plus"></i></span><input type="date" id="datePR" name="date_pr" class="form-control"></div><div class="po-field-hint">When the request was raised. Paired with Date PO it is what makes a lead-time report possible.</div></div>
                  <div class="col-md-4 po-field-group position-relative">
                      <label>Category</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-tags"></i></span><textarea id="category" name="category" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div>
                      <div id="categorySuggestions" class="purpose-suggestions d-none"></div>
                  </div>
                  <div class="col-md-4 po-field-group position-relative">
                      <label>Department</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-building"></i></span><textarea id="department" name="department" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div>
                      <div id="departmentSuggestions" class="purpose-suggestions d-none"></div>
                  </div>
                  <div class="col-md-4 po-field-group position-relative">
                      <label>Division</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-building"></i></span><textarea id="division" name="division" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div>
                      <div id="divisionSuggestions" class="purpose-suggestions d-none"></div>
                  </div>
                  <div class="col-md-4 po-field-group"><label>Section</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-table-cells"></i></span><textarea id="section" name="section" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div></div>
                  <div class="col-md-6 po-field-group position-relative">
                      <label>Supplier Name</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-users"></i></span><textarea id="supplierName" name="supplier_name" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div>
                      <div id="supplierNameSuggestions" class="purpose-suggestions d-none"></div>
                  </div>
                  <div class="col-md-6 po-field-group"><label>Supplier Address</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-location-dot"></i></span><textarea id="supplierAddress" name="supplier_address" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div></div>
                  <div class="col-md-4 po-field-group"><label>PO Date</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar-days"></i></span><input type="datetime-local" id="datePO" name="date_po" class="form-control"></div></div>
                  <div class="col-md-4 po-field-group position-relative">
                      <label>PR Received Name</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span><textarea id="prReceivedName" name="pr_received_name" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div>
                      <div id="prReceivedNameSuggestions" class="purpose-suggestions d-none"></div>
                  </div>
                  <div class="col-md-4 po-field-group"><label>Reference No.</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-hashtag"></i></span><textarea id="referenceNo" name="reference_no" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div></div>
                  <div class="col-md-4 po-field-group"><label>BAC Resolution No.</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-shield-halved"></i></span><textarea id="bacResolutionNo" name="bac_resolution_no" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div></div>
                  <div class="col-md-4 po-field-group position-relative">
                      <label>Mode of Procurement</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-basket-shopping"></i></span><textarea id="modeOfProcurement" name="mode_of_procurement" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div>
                      <div id="modeOfProcurementSuggestions" class="purpose-suggestions d-none"></div>
                  </div>
                  <div class="col-md-4 po-field-group position-relative">
                      <label>Place of Delivery</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-truck"></i></span><textarea id="placeOfDelivery" name="place_of_delivery" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div>
                      <div id="placeOfDeliverySuggestions" class="purpose-suggestions d-none"></div>
                  </div>
                  <div class="col-md-6 po-field-group position-relative">
                      <label>Working Days</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-clock"></i></span><textarea id="workingDays" name="po_working_days" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div>
                      <div id="workingDaysSuggestions" class="purpose-suggestions d-none"></div>
                  </div>
                  <div class="col-md-6 po-field-group position-relative">
                      <label>Payment Term</label>
                      <div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar-check"></i></span><textarea id="paymentTerm" name="po_payment_term" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea></div>
                      <div id="paymentTermSuggestions" class="purpose-suggestions d-none"></div>
                  </div>
                  <!-- End User isn't shown here anymore (redundant with the
                       Obligation Request modal's own End User field) but the
                       column is still written per-item on save, so its value
                       is preserved via this hidden field rather than blanked
                       out on every edit — see fillForm(). -->
                  <input type="hidden" id="endUser" name="end_user">
                  <div class="col-12 po-field-group position-relative">
                      <label>Purpose</label>
                      <div class="po-input-wrap">
                          <span class="po-input-icon"><i class="fas fa-bullseye"></i></span>
                          <textarea id="purpose" name="purpose" class="form-control po-autogrow" rows="1" autocomplete="off"></textarea>
                      </div>
                      <div id="purposeSuggestions" class="purpose-suggestions d-none"></div>
                  </div>
                </div>
              </div>
              </div>
              <!-- Additional Details lives OUTSIDE .po-left-col-scroll (unlike
                   Purchase Order Information above it) so it's never pushed
                   below the fold and clipped by that scroll region's capped
                   height — see sizePoModalColumns(), which now sizes the
                   scroll region to (modal-body height − this card's own
                   height) specifically so this card always renders in full.
                   These fields only apply to a PO that already exists — a
                   brand-new PO doesn't have a supplier-received date, COA
                   date, approval date, barcode, or remarks yet, so this
                   section stays hidden while creating and only appears once
                   you're editing a saved PO. -->
              <div class="po-card po-card-compact" id="poAdditionalDetailsSection">
                <div class="po-card-title"><i class="fas fa-list-check"></i> Additional Details <span class="text-muted small fw-normal ms-1">(each field saves on its own, via its pencil button)</span></div>
                <div class="row g-2 tight-field-row">
                    <div class="col-md-6 po-field-group"><label>Status</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-flag"></i></span><div class="po-quick-value" id="statusValue">&mdash;</div><button type="button" class="po-quick-edit-btn" onclick="openQuickUpdate('status')" title="Update"><i class="fas fa-pen"></i></button></div></div>
                    <div class="col-md-6 po-field-group"><label>Date Received Supplier</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-truck-ramp-box"></i></span><div class="po-quick-value" id="dateReceivedSupplierValue">&mdash;</div><button type="button" class="po-quick-edit-btn" onclick="openQuickUpdate('date_received_supplier')" title="Update"><i class="fas fa-pen"></i></button></div></div>
                    <div class="col-md-6 po-field-group"><label>COA Date Received</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-calendar-check"></i></span><div class="po-quick-value" id="dateCOAReceivedValue">&mdash;</div><button type="button" class="po-quick-edit-btn" onclick="openQuickUpdate('date_coa_received')" title="Update"><i class="fas fa-pen"></i></button></div></div>
                    <div class="col-md-6 po-field-group"><label>Approved PO Date</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-circle-check"></i></span><div class="po-quick-value" id="dateApprovedPOValue">&mdash;</div><button type="button" class="po-quick-edit-btn" onclick="openQuickUpdate('date_approved_po')" title="Update"><i class="fas fa-pen"></i></button></div></div>
                    <div class="col-md-6 po-field-group"><label>Barcode Number</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-barcode"></i></span><div class="po-quick-value" id="barcodeNumberValue">&mdash;</div><button type="button" class="po-quick-edit-btn" onclick="openQuickUpdate('barcode_number')" title="Update"><i class="fas fa-pen"></i></button></div></div>
                    <div class="col-12 po-field-group"><label>Remarks</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-file-pen"></i></span><div class="po-quick-value" id="remarksValue">&mdash;</div><button type="button" class="po-quick-edit-btn" onclick="openQuickUpdate('remarks')" title="Update"><i class="fas fa-pen"></i></button></div></div>
                </div>
              </div>


              <!-- Shared single-field editor for the Additional Details "quick
                   fields" above — one modal, its contents rebuilt per field by
                   openQuickUpdate() rather than five near-identical modals. -->
              <div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="poQuickUpdateModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title fw-bold" id="poQuickUpdateTitle">Update</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" id="poQuickUpdateField">
                      <div id="poQuickUpdateInputWrap"></div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="button" class="btn btn-primary-app" onclick="saveQuickUpdate()"><i class="fas fa-floppy-disk me-1"></i> Save</button>
                    </div>
                  </div>
                </div>
              </div>

            </div>

            <!-- Right column — items -->
            <div class="col-lg-7">
              <div class="po-card h-100 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                    <div class="po-card-title mb-0"><i class="fas fa-boxes-stacked"></i> Item Details</div>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill po-edit-only" onclick="addItemRow({}, null, true)"><i class="fas fa-plus me-1"></i> Add Item</button>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3 po-edit-only">
                    <input type="file" id="itemImportInput" accept=".xlsx,.csv" class="d-none">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="document.getElementById('itemImportInput').click()"><i class="fas fa-file-import me-1"></i> Import Items</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="toggleImportGuide()" title="What columns does the import file need?"><i class="fas fa-circle-question"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" onclick="autoNumberLots()" title="Fill Number Series automatically: each row with no Unit/Total Cost starts a new lot, and every item row below it gets that lot's number."><i class="fas fa-list-ol me-1"></i> Auto Number Lots</button>
                </div>
                <div id="itemImportGuide" class="import-guide d-none mb-3">
                    <div class="fw-semibold mb-1"><i class="fas fa-circle-info me-1"></i>Import Items file format</div>
                    The first row must be column headers. <strong>Description</strong> is the only one
                    you have to include — a file with just that single column works fine. Add any of
                    <code>Unit, Qty, Unit Cost, Total Cost, Number Series</code> as well, in any order.
                    <div class="mt-2 mb-1"><strong>Every row must be one of two shapes:</strong></div>
                    <table class="imp-eg">
                      <thead><tr>
                        <th>Unit</th><th>Description</th><th>Qty</th><th>Unit Cost</th><th>Total Cost</th><th></th>
                      </tr></thead>
                      <tbody>
                        <tr class="eg-title">
                          <td class="blank"></td><td>LOT 1</td><td class="blank"></td><td class="blank"></td><td class="blank"></td>
                          <td class="note"><span class="tag ok">TITLE</span> every other column left blank</td>
                        </tr>
                        <tr>
                          <td>pc</td><td>Electric motor 5HP 220V</td><td>1</td><td>19,500.00</td><td>19,500.00</td>
                          <td class="note"><span class="tag ok">ITEM</span> priced &mdash; it has a Total Cost</td>
                        </tr>
                        <tr>
                          <td>liters</td><td>Diesel</td><td class="blank"></td><td class="blank"></td><td>99,975.00</td>
                          <td class="note"><span class="tag ok">ITEM</span> lump sum &mdash; Total Cost only is fine</td>
                        </tr>
                        <tr class="eg-bad">
                          <td>pc</td><td>Brake cleaner</td><td>5</td><td class="blank"></td><td class="blank"></td>
                          <td class="note"><span class="tag no">REJECTED</span> priced like an item but no Total Cost</td>
                        </tr>
                      </tbody>
                    </table>
                    A row is either a <strong>title</strong> (every column but Description left blank) or a <strong>priced item</strong> (it has a Total Cost) &mdash; nothing in between. A title row prints as a heading with empty Qty/Unit Cost/Total columns, exactly like
                    "LOT 1" or "SCOPE OF WORK:" on the form. <strong>Total Cost</strong> is worked out for you
                    from Qty × Unit Cost when you leave it out. Afterwards, use
                    <a href="#" onclick="autoNumberLots();return false;">Auto-Number Lots</a> to fill Number Series.
                    Accepted file types: .xlsx, .csv.
                    <a href="purchase_order_item_import_template.csv" download>Download a template</a> — it already
                    contains the two lots shown above.
                    <div class="d-flex flex-wrap gap-2 mt-2">
                      <button type="button" class="btn btn-sm btn-primary-app rounded-pill" onclick="showExampleRows()"><i class="fas fa-wand-magic-sparkles me-1"></i> Show me an example</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill d-none" id="btnClearExample" onclick="clearExampleRows()"><i class="fas fa-eraser me-1"></i> Clear example rows</button>
                    </div>
                </div>

                <div id="poItemsEmptyState" class="po-items-empty">
                    <div class="po-items-empty-icon"><i class="fas fa-bag-shopping"></i></div>
                    <div class="po-items-empty-title">No items added yet.</div>
                    <div class="po-items-empty-sub">Click "Add Item" to get started.</div>
                </div>
                <div id="poItemsTableWrap" class="table-responsive items-scroll-wrap d-none">
                  <table class="app-table po-items-table table table-bordered mb-0" id="poItemsTable">
                    <thead>
                      <tr>
                        <th style="width:4%">#</th>
                        <th style="width:8%">Unit</th>
                        <th style="width:31%">Description</th>
                        <th style="width:8%">Qty</th>
                        <th style="width:11%">Unit Cost</th>
                        <th style="width:11%">Total Cost</th>
                        <th style="width:11%">Number Series</th>
                        <th style="width:8%">Action</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
                <button type="button" class="po-add-row-link po-edit-only" onclick="addItemRow({}, null, true)"><i class="fas fa-plus me-1"></i> Add another row</button>

                <div class="po-items-footer-bar">
                    <div class="po-items-count">Total Items: <strong id="poItemsCount">0</strong></div>
                    <div><span class="text-muted small me-1">Grand Total:</span><span id="poSubtotal">₱0.00</span></div>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer d-flex align-items-center">
        <div class="me-auto">Status: <span id="poModalStatusBadge" class="badge-status pending">Pending</span></div>
        <!-- Hidden until the PO exists (see setAdditionalDetailsVisible()) — same
             rule as Additional Details, since there is nothing to print before
             the record has a PO Number. Opens the same #poPrintModal / print
             pipeline as the list row's own Print button (openPrintModal(),
             buildActions()), just reachable without leaving the modal first. -->
        <button type="button" class="btn btn-outline-secondary d-none" id="poModalPrintButton" onclick="openPrintModal(document.getElementById('poNumber').value)"><i class="fas fa-print me-1"></i> Print Purchase Order</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> <span id="poModalCancelLabel">Cancel</span></button>
        <button type="button" class="btn btn-primary-app po-edit-only" id="savePOButton" onclick="savePurchaseOrder()"><i class="fas fa-floppy-disk me-1"></i> Save Purchase Order</button>
      </div>
    </div>
  </div>
</div>

<!-- PO Saved confirmation — shown centered over the page right after a
     successful create/update, since the PO Modal has already closed by then
     and a corner toast is easy to miss for something as important as the
     PO Number that was just generated. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="poSavedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content text-center">
      <div class="modal-body py-4">
        <div class="po-saved-icon"><i class="fas fa-check"></i></div>
        <h5 class="fw-bold mb-1" id="poSavedTitle">Purchase Order Saved</h5>
        <div class="text-muted small mb-2" id="poSavedSubtitle"></div>
        <div class="po-saved-number" id="poSavedNumber"></div>
      </div>
      <div class="modal-footer justify-content-center border-top-0 pt-0">
        <button type="button" class="btn btn-primary-app px-4" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Fund source choice — shown before a brand-new Purchase Order's form
     opens. Mirrors the legacy PO_Choices.vb screen (General Fund / Trust
     Fund / Special Education Fund); the pick determines the PO Number's
     prefix (PO-sys… / TF-sys… / SEF-sys…) via generatePoNumber() in
     api/purchase_orders.php. Only relevant on create — an existing PO's
     number (and prefix) never changes on edit. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="poFundModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">New Purchase Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Select the fund source for this Purchase Order.</p>
        <div class="row g-3">
          <div class="col-md-4">
            <button type="button" class="po-fund-option po-fund-gf" onclick="chooseFundType('PO')">General Fund<small>(GF)</small></button>
          </div>
          <div class="col-md-4">
            <button type="button" class="po-fund-option po-fund-tf" onclick="chooseFundType('TF')">Trust Fund<small>(TF)</small></button>
          </div>
          <div class="col-md-4">
            <button type="button" class="po-fund-option po-fund-sef" onclick="chooseFundType('SEF')">Special Education Fund<small>(SEF)</small></button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Print Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="poPrintModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header po-modal-header">
        <h5 class="modal-title"><i class="fas fa-print me-2"></i>Print Purchase Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="printType" id="printTypeStandard" value="standard" checked>
                <label class="form-check-label" for="printTypeStandard">Standard Purchase Order</label>
            </div>
            <div class="print-type-help">Numbers the <strong>No.</strong> column 1, 2, 3 down the page &mdash; every row counted, LOT rows included.</div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="printType" id="printTypeLot" value="lot">
                <label class="form-check-label" for="printTypeLot">Purchase Order by LOT</label>
            </div>
            <div class="print-type-help">Takes the <strong>No.</strong> from each item&rsquo;s <strong>Number Series</strong>, so &ldquo;LOT 1&rdquo; / &ldquo;LOT 2&rdquo; divider rows stay blank instead of using up a number.</div>
            <div id="printTypeHint" class="print-hint d-none"></div>
        </div>
        <!-- Only the two officers who actually approve a Purchase Order. It was
             a free-text box over the whole signatory list, which also meant the
             default landed on whoever sorted first by name — the City Treasurer
             — so every dialog opened proposing the wrong person to sign.
             The title is derived from the pick rather than typed, because the
             two can only ever disagree by mistake. -->
        <div class="mb-3">
            <label class="form-label">Signatory Name</label>
            <select id="printSignatoryName" class="form-select"></select>
            <div id="printSignatoryHint" class="form-text d-none"></div>
        </div>
        <datalist id="dlSignatories"></datalist>
        <div class="mb-3">
            <label class="form-label">Signatory Title</label>
            <input type="text" id="printSignatoryTitle" class="form-control" value="City Mayor" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">Date</label>
            <div class="input-group">
                <input type="date" id="printDate" class="form-control">
                <button type="button" class="btn btn-outline-primary" id="btnClearPrintDate" title="Print with no date"><i class="fas fa-xmark me-1"></i>Clear</button>
            </div>
            <div class="form-text">Printed beside <strong>PO NO.</strong> Suggested from this PO&rsquo;s own date &mdash; click <strong>Clear</strong> for an order with no single meaningful date, like meals/catering.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Initial <span class="text-danger">*</span></label>
            <input type="text" id="printInitial" class="form-control" list="dlPoUserInitials" autocomplete="off" maxlength="12" required>
            <datalist id="dlPoUserInitials"></datalist>
            <div class="form-text">Printed beside <em>***nothing follows***</em>. Suggested from this PO&rsquo;s PR Received By name &mdash; type over it if someone else initials the form.</div>
        </div>
        <?php if (appUrlIsLocalOnly()): ?>
        <!-- The QR links back to whatever host this page was loaded from. On
             localhost that address means nothing to a phone, so the printout
             would carry a dead link. Warn rather than block — previewing from
             localhost is perfectly normal, it is only printing that suffers. -->
        <div class="alert alert-warning py-2 px-3 mb-0 small">
            <i class="fas fa-triangle-exclamation me-1"></i>
            You are browsing via <strong><?= e(parse_url(appBaseUrl(), PHP_URL_HOST)) ?></strong>, so the
            verification QR on this printout will only open on this computer.
            For copies that staff can scan with a phone, open this system through
            its network address (e.g. <code>http://192.168.10.x/…</code>) before printing,
            or set <code>APP_URL</code> in <code>.env</code>.
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <!-- Same preview/PDF pipeline as Open Print Preview, just without the
             "Date Printed / Page N of M" footer stamp on the generated PDF —
             see currentPreviewIncludeFooter / addCanvasToPdfPaged()'s stamp arg. -->
        <button type="button" class="btn btn-outline-secondary" id="printButtonNoFooter">Open Print Preview (No Footer)</button>
        <button type="button" class="btn btn-primary-app" id="printButton">Open Print Preview</button>
      </div>
    </div>
  </div>
</div>

<!-- Obligation Request Modal — replaces the legacy Obligation_Print.vb dialog.
     Same fields, same order, same two print variants (Payment / Reimbursement)
     as the WinForms form; "Payment Print" maps to Obligation_Request_Report.rpt,
     "Reimbursement Print" to Obligation Request Report Reimbursement.rpt (the
     only difference in that report is it also takes a total_cost parameter).
     Saving either stamps Barcode Number onto the PO first, exactly like
     btnSave_Click/btnSave2_Click did. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="poObligationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header po-modal-header-v2">
        <div class="po-header-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="po-header-text">
            <h5 class="modal-title">Obligation Request</h5>
            <small>Please fill up the details for the Obligation Request.</small>
        </div>
        <button type="button" class="po-header-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="obligationPoNumber">
        <div class="po-card">
            <div class="row g-2 tight-field-row">
                <div class="col-12 po-field-group"><label>Barcode Number</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-barcode"></i></span><input type="text" id="obBarcodeNumber" class="form-control"></div><div class="po-field-hint" id="obBarcodeHint"></div></div>
                <div class="col-12 po-field-group"><label>Supplier Name</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-users"></i></span><input type="text" id="obSupplierName" class="form-control"></div></div>
                <div class="col-12 po-field-group"><label>Supplier Address</label><div class="po-input-wrap"><span class="po-input-icon" style="top:19px;transform:none;"><i class="fas fa-location-dot"></i></span><textarea id="obSupplierAddress" class="form-control" rows="2"></textarea></div></div>
                <div class="col-md-6 po-field-group"><label>Department</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-building"></i></span><input type="text" id="obDepartment" class="form-control"></div></div>
                <div class="col-md-6 po-field-group"><label>Category</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-tags"></i></span><input type="text" id="obCategory" class="form-control"></div></div>
                <div class="col-md-6 po-field-group"><label>City Budget</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span><input type="text" id="obCityBudget" class="form-control" list="dlSignatories" autocomplete="off"></div></div>
                <!-- A textarea, not an input: the real form carries two lines under
                     this signature (the officer's title, then the authority line) and
                     both need to stay editable. Line breaks are kept on the printout. -->
                <div class="col-md-6 po-field-group"><label>City Budget Position</label><div class="po-input-wrap"><span class="po-input-icon" style="top:19px;transform:none;"><i class="fas fa-id-badge"></i></span><textarea id="obCityBudgetPosition" class="form-control" rows="4"></textarea></div></div>
                <div class="col-md-6 po-field-group"><label>End User</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-user"></i></span><input type="text" id="obEndUser" class="form-control" list="dlSignatories" autocomplete="off"></div></div>
                <div class="col-md-6 po-field-group"><label>End User Position</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-id-badge"></i></span><input type="text" id="obEndUserPosition" class="form-control"></div></div>
                <div class="col-12 po-field-group"><label class="text-danger">Total Amount</label><div class="po-input-wrap"><span class="po-input-icon"><i class="fas fa-peso-sign"></i></span><input type="text" id="obTotalAmount" class="form-control" readonly></div></div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-outline-danger" id="obReimbursementButton"><i class="fas fa-receipt me-1"></i> Reimbursement Print</button>
        <button type="button" class="btn btn-dark" id="obPaymentButton"><i class="fas fa-money-check-dollar me-1"></i> Payment Print</button>
      </div>
    </div>
  </div>
</div>

<!-- Obligation Request Print Preview — same PDF pipeline as the PO print
     preview (html2canvas + jsPDF), just a much shorter single "slip"
     instead of the full itemized PO. The visual layout below is NOT
     verified against a real printed sample or the Crystal Reports
     Designer (unlike the Purchase Order print, which was) — it's built
     directly from the .rpt's known parameter contract (see
     tools/rpt_reports/Obligation Request Report*.md) as a plain, honest
     rendering of those fields. Swap this out once a real reference is
     available. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="obPrintPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header po-modal-header no-print">
        <h5 class="modal-title"><i class="fas fa-print me-2"></i>Obligation Request Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pp-preview-body">
        <div id="obPrintPreviewArea" class="pp-offscreen-source"></div>
        <div id="obPdfPreviewLoading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="small mt-2">Generating PDF preview…</div>
        </div>
        <iframe id="obPdfPreviewFrame" class="pdf-preview-frame d-none" title="Obligation Request PDF preview"></iframe>
      </div>
      <div class="modal-footer no-print">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-secondary" id="obPdfOpenTabButton" disabled><i class="fas fa-up-right-from-square me-1"></i> Open in Tab</button>
        <button type="button" class="btn btn-outline-primary" id="obPdfDownloadButton" disabled><i class="fas fa-download me-1"></i> Download PDF</button>
        <button type="button" class="btn btn-primary-app" id="obPdfPrintButton" disabled><i class="fas fa-print me-1"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<!-- Print Preview Modal — renders the actual printable Purchase Order
     in-page instead of opening purchase_order_print.php in a new tab.
     window.print() + the @media print rule below isolate #printPreviewArea
     so only the "paper" prints, not the rest of the app chrome. -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="poPrintPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header po-modal-header no-print">
        <h5 class="modal-title"><i class="fas fa-print me-2"></i>Print Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pp-preview-body">
        <!-- Off-screen but still laid out (not display:none — html2canvas
             needs a real render box to rasterize) — this is the source
             html2canvas snapshots into the actual PDF below, not shown itself. -->
        <div id="printPreviewArea" class="pp-offscreen-source"></div>
        <div id="pdfPreviewLoading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="small mt-2">Generating PDF preview…</div>
        </div>
        <div class="pp-toolbar no-print">
            <div class="pp-tool">
                <label for="pdfPaperSize"><i class="fas fa-file-lines me-1"></i>Paper</label>
                <select id="pdfPaperSize" class="form-select form-select-sm">
                    <option value="auto">Auto &mdash; smallest sheet that fits</option>
                    <option value="a4">A4 &middot; 8.27 &times; 11.69 in</option>
                    <option value="long">Long / Folio &middot; 8.5 &times; 13 in</option>
                    <option value="legal">Legal &middot; 8.5 &times; 14 in</option>
                    <option value="letter">Letter &middot; 8.5 &times; 11 in</option>
                </select>
            </div>
            <div class="pp-tool">
                <label for="pdfTextSize"><i class="fas fa-text-height me-1"></i>Item text</label>
                <select id="pdfTextSize" class="form-select form-select-sm">
                    <option value="0">Normal</option>
                    <option value="1">Larger (+1)</option>
                    <option value="2">Largest (+2)</option>
                </select>
            </div>
            <div class="pp-layout-status" id="pdfLayoutStatus"></div>
        </div>
        <iframe id="pdfPreviewFrame" class="pdf-preview-frame d-none" title="Purchase Order PDF preview"></iframe>

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

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/jquery.dataTables.min.js"></script>
<script src="vendor/js/dataTables.bootstrap5.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script src="vendor/js/html2canvas.min.js"></script>
<script src="vendor/js/jspdf.umd.min.js"></script>
<script>
const apiUrl = 'api/purchase_orders.php';
const actorRole = '<?= e($currentUser['role']) ?>';
const actorRoleId = <?= (int)($currentUser['role_id'] ?? 0) ?>;
// Lock/Unlock, Delete and Edit are per-role permissions configurable from
// Access Levels (tbl_po_permissions — see poActionPermission() in
// config.php), not a hardcoded role list. These three only hide/show the
// buttons — api/purchase_orders.php checks the same function server-side on
// every request, so a hand-made call gets nowhere regardless of what the
// button shows.
const canLock   = <?= canLockPurchaseOrder((int)($currentUser['role_id'] ?? 0)) ? 'true' : 'false' ?>;
const canDelete = <?= canDeletePurchaseOrder((int)($currentUser['role_id'] ?? 0)) ? 'true' : 'false' ?>;
const canEditPo = <?= canEditPurchaseOrder((int)($currentUser['role_id'] ?? 0)) ? 'true' : 'false' ?>;
let selectedPoNumber = null;
let selectedPrintPo = null;
let poTable;

// Real PO/supplier/item data is full of apostrophes, quotes, ampersands
// ("3J'S WATER REFILLING STATION", 24" pipe, etc.) — every place that
// interpolates it into an HTML string via innerHTML/template literals needs
// this, or a stray `"` breaks the attribute it's sitting in and a stray
// `<`/`>`/`&` gets rendered/executed as markup instead of shown as text.
function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function showToast(msg, type='success') {
    const id = 'toast-' + Date.now();
    const bg = type === 'success' ? 'bg-success' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-danger');
    const el = document.createElement('div');
    el.id = id;
    el.className = `toast align-items-center text-white ${bg} border-0`;
    el.setAttribute('role','alert');
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    document.getElementById('toastContainer').appendChild(el);
    const toast = new bootstrap.Toast(el, {delay: 4000});
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

function formatDateTime(value) {
    if (!value) return '';
    const date = new Date(value.replace(' ', 'T'));
    if (isNaN(date)) return value;
    return date.toISOString().slice(0,16);
}

function resetFilters() {
    document.getElementById('searchText').value = '';
    document.getElementById('filterStatus').value = '';
    // +1 day, not today — a PO created later today (after this page loaded)
    // still needs to fall inside the default "To" bound instead of getting
    // filtered out until someone manually bumps the date forward.
    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    const monthStart = new Date(); monthStart.setDate(1);
    document.getElementById('filterFrom').value = monthStart.toISOString().slice(0,10);
    document.getElementById('filterTo').value = tomorrow.toISOString().slice(0,10);
    loadPurchaseOrders();
}

function buildActions(row) {
    const locked = !!row.locked;
    const lockBtn = canLock
        ? (locked
            ? `<button class="btn btn-sm btn-outline-warning" onclick="setLock('${row.po_number}','unlock')" title="Unlock"><i class="fas fa-lock-open"></i></button>`
            : `<button class="btn btn-sm btn-outline-secondary" onclick="setLock('${row.po_number}','lock')" title="Lock"><i class="fas fa-lock"></i></button>`)
        : '';
    return `
      <div class="table-actions">
        <button class="btn btn-sm btn-outline-info" onclick="viewPurchaseOrder('${row.po_number}')" title="View"><i class="fas fa-eye"></i></button>
        ${canEditPo ? `<button class="btn btn-sm btn-outline-primary" onclick="editPurchaseOrder('${row.po_number}')" title="${locked ? 'Locked' : 'Edit'}" ${locked ? 'disabled' : ''}><i class="fas fa-edit"></i></button>` : ''}
        ${canDelete ? `<button class="btn btn-sm btn-outline-danger" onclick="deletePurchaseOrder('${row.po_number}')" title="${locked ? 'Locked' : 'Delete'}" ${locked ? 'disabled' : ''}><i class="fas fa-trash"></i></button>` : ''}
        <button class="btn btn-sm btn-outline-secondary" onclick="openPrintModal('${row.po_number}')" title="Purchase Order"><i class="fas fa-print"></i></button>
        <button class="btn btn-sm btn-outline-secondary" onclick="openObligationModal('${row.po_number}')" title="Obligation Request"><i class="fas fa-file-invoice-dollar"></i></button>
        <button class="btn btn-sm btn-outline-dark" onclick="dtsTrackPo('${row.po_number}')" title="Track in DTS"><i class="fas fa-route"></i></button>
        ${lockBtn}
      </div>
    `;
}

async function setLock(poNumber, action) {
    if (!confirm(`${action === 'lock' ? 'Lock' : 'Unlock'} PO ${poNumber}?`)) return;
    try {
        const res = await fetch(apiUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ po_number: poNumber, lock_action: action })
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to update lock status.', 'error');
        showToast(data.message || 'Done.');
        loadPurchaseOrders();
    } catch (err) {
        showToast('Unable to update lock status.', 'error');
        console.error(err);
    }
}

// Recomputes the grand total, renumbers the "#" column, and swaps between
// the empty-state placeholder and the actual grid depending on row count —
// called after every add/remove/import so the two always stay in sync.
//
// Also silently re-runs Auto Number Lots (a no-op on a PO with no lot-label
// rows) so Number Series stays contiguous through every add/remove, instead
// of only being right the moment someone last clicked the button by hand.
function refreshItemsSummary() {
    autoNumberLots(true);
    let total = 0;
    const rows = document.querySelectorAll('#poItemsTable tbody tr');
    rows.forEach((row, i) => {
        const cost = parseFloat(row.querySelector('[data-field="total_cost"]').value) || 0;
        total += cost;
        const idxCell = row.querySelector('.po-item-idx');
        if (idxCell) idxCell.textContent = i + 1;
    });
    document.getElementById('poSubtotal').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('poItemsCount').textContent = rows.length;
    document.getElementById('poItemsEmptyState').classList.toggle('d-none', rows.length > 0);
    document.getElementById('poItemsTableWrap').classList.toggle('d-none', rows.length === 0);
    sizePoModalColumns();
}

// afterRow (optional): a specific <tr> to insert the new row right after,
// instead of always appending to the bottom — lets you drop a forgotten
// "LOT 6" label row exactly where it belongs without deleting and retyping
// everything below it. focus (optional): true when the user explicitly
// clicked Add Row/Insert, so the new row's first field is ready to type
// into; left off for bulk population (editing an existing PO, importing).
// True when an item row carries nothing but a description — the shape of every
// "LOT 1" / "SCOPE OF WORK:" label already stored on 51,000+ existing rows.
//
// Used ONLY when loading saved data. While someone is typing, the row type is
// whatever they chose explicitly, so a half-finished item cannot flicker into a
// heading the moment its description is entered and its price is not.
function looksLikeTitleRow(item) {
    return String(item.items_description || '').trim() !== ''
        && String(item.unit || '').trim() === ''
        && !itemHasValue(item.quantity)
        && !itemHasValue(item.unit_cost)
        && !itemHasValue(item.total_cost);
}

function setRowType(row, isTitle) {
    row.classList.toggle('po-item-title', isTitle);
    if (isTitle) {
        // Clear what the row can no longer show, so a value cannot survive
        // invisibly and then reappear on the printed form.
        ['unit', 'quantity', 'unit_cost', 'total_cost', 'number_series'].forEach(f => {
            const el = row.querySelector(`[data-field="${f}"]`);
            if (el) el.value = '';
        });
    }
    const btn = row.querySelector('.title-toggle');
    if (btn) {
        btn.title = isTitle ? 'Turn back into an item row' : 'Turn into a LOT / title row';
        btn.innerHTML = `<i class="fas fa-${isTitle ? 'rotate-left' : 'heading'}"></i>`;
        btn.classList.toggle('active', isTitle);
    }
    refreshItemsSummary();
}

function toggleRowType(row) {
    setRowType(row, !row.classList.contains('po-item-title'));
}

// isTitle: true/false to force the row type, or null (the default) to work it
// out from the data — which is what loading a saved PO wants.
function addItemRow(item = {}, afterRow = null, focus = false, isTitle = null) {
    const tbody = document.querySelector('#poItemsTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td class="po-item-idx"></td>
        <td><input type="text" class="form-control form-control-sm" data-field="unit" value="${escapeHtml(item.unit)}"><span class="po-title-dash">&mdash;</span></td>
        <td><textarea class="form-control form-control-sm item-desc-input" data-field="items_description" rows="1">${escapeHtml(item.items_description)}</textarea></td>
        <td><input type="number" min="0" step="1" class="form-control form-control-sm" data-field="quantity" value="${itemHasValue(item.quantity) ? item.quantity : ''}"><span class="po-title-dash">&mdash;</span></td>
        <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" data-field="unit_cost" value="${itemHasValue(item.unit_cost) ? item.unit_cost : ''}"><span class="po-title-dash">&mdash;</span></td>
        <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" data-field="total_cost" value="${itemHasValue(item.total_cost) ? item.total_cost : ''}" readonly><span class="po-title-dash">&mdash;</span></td>
        <td><input type="text" class="form-control form-control-sm" data-field="number_series" value="${escapeHtml(item.number_series)}"><span class="po-title-dash">&mdash;</span></td>
        <td class="text-nowrap">
            <div class="d-flex gap-1">
                <button type="button" class="po-item-action-btn add" title="Insert row below" onclick="addItemRow({}, this.closest('tr'), true)"><i class="fas fa-plus"></i></button>
                <button type="button" class="po-item-action-btn title-toggle" title="Turn into a LOT / title row" onclick="toggleRowType(this.closest('tr'))"><i class="fas fa-heading"></i></button>
                <button type="button" class="po-item-action-btn remove" title="Remove row" onclick="this.closest('tr').remove(); refreshItemsSummary();"><i class="fas fa-trash"></i></button>
            </div>
        </td>
    `;
    row.querySelectorAll('input').forEach(input => {
        if (input.dataset.field === 'quantity' || input.dataset.field === 'unit_cost') {
            input.addEventListener('input', () => {
                const qty = parseFloat(row.querySelector('[data-field="quantity"]').value) || 0;
                const unit = parseFloat(row.querySelector('[data-field="unit_cost"]').value) || 0;
                row.querySelector('[data-field="total_cost"]').value = (qty * unit).toFixed(2);
                refreshItemsSummary();
            });
        }
    });
    // Description grows with its content instead of clipping/scrolling a
    // long item spec — resets to 'auto' first so it can shrink back down
    // too if the text is edited shorter, not just grow one-way.
    const descInput = row.querySelector('.item-desc-input');
    const autoGrow = () => { descInput.style.height = 'auto'; descInput.style.height = descInput.scrollHeight + 'px'; };
    descInput.addEventListener('input', autoGrow);
    if (afterRow && afterRow.parentNode === tbody) {
        afterRow.insertAdjacentElement('afterend', row);
    } else {
        tbody.appendChild(row);
    }
    // refreshItemsSummary() must run before autoGrow() below, not just
    // after — it's what removes #poItemsTableWrap's d-none class (still set
    // whenever this is the first row). scrollHeight is unreliable not only
    // on a detached element, but on any element inside a display:none
    // ancestor too — being attached to tbody isn't enough on its own if the
    // grid wrapping it is still hidden at measurement time. That's exactly
    // the case for a programmatically-filled, disabled View-mode row (never
    // gets a keystroke to self-correct afterward), so it stayed clipped to
    // one line forever there.
    setRowType(row, isTitle === null ? looksLikeTitleRow(item) : isTitle);
    refreshItemsSummary();
    autoGrow();
    // A heading row has no visible inputs, so Unit is not there to focus.
    if (focus) {
        (row.classList.contains('po-item-title') ? descInput : row.querySelector('input'))?.focus();
    }
    return row;
}

// Real POs use rows with no Unit Cost/Total Cost purely as group labels
// (e.g. "LOT 1", "LOT 2") — everything below one of those, until the next
// label row, belongs to that lot. Number Series is a running item count
// across the *whole* grid (1, 2, 3, …), not the lot number — it keeps
// counting up through every lot rather than resetting at each label row.
//
// silent (used by refreshItemsSummary() after every add/remove/import): skip
// both toasts and just renumber quietly, so a plain PO with no lot-label
// rows is left untouched instead of getting a warning toast on every edit.
// Without this, deleting a mid-series row left every row after it holding
// its old, now-gapped number until someone remembered to click this button
// by hand — see memory of the PO-sys2026826298 print that shipped as
// 1,2,3,4,5,9,10,12 because two items were deleted after numbering and
// nobody re-ran it.
function autoNumberLots(silent) {
    let lot = 0;
    let itemNumber = 0;
    document.querySelectorAll('#poItemsTable tbody tr').forEach(row => {
        const unitCost = row.querySelector('[data-field="unit_cost"]').value.trim();
        const totalCost = row.querySelector('[data-field="total_cost"]').value.trim();
        const numberSeriesInput = row.querySelector('[data-field="number_series"]');
        // An explicitly-marked heading row counts even if a stray cost was
        // left on it; otherwise fall back to the old shape test so POs saved
        // before heading rows existed still number correctly.
        const isLabelRow = row.classList.contains('po-item-title') || (!unitCost && !totalCost);
        if (isLabelRow) {
            lot++;
            numberSeriesInput.value = '';
        } else if (lot > 0) {
            itemNumber++;
            numberSeriesInput.value = String(itemNumber);
        }
    });
    if (silent) return;
    if (lot === 0) {
        showToast('No lot-label rows found (a row with Unit Cost and Total Cost both blank, like "LOT 1") — nothing to number.', 'warning');
    } else {
        showToast(`Numbered ${itemNumber} item(s) across ${lot} lot(s).`);
    }
}

// Drops a worked example straight into the grid: a heading row, a normal item,
// and the lump-sum shape people get wrong most often.
//
// Deliberately the real grid rather than a picture or a recording — pressing H
// on a row and watching it become a heading, or trying to save one with no
// Total Cost and reading the error, teaches the two shapes far better than
// watching someone else do it. It also cannot go out of date, which a video of
// this modal would within about a week.
const EXAMPLE_ROWS = [
    { items_description: 'LOT 1', isTitle: true },
    { items_description: 'Electric motor 5HP 220V @ 60Hz', unit: 'pc', quantity: '1',
      unit_cost: '19500', total_cost: '19500.00', number_series: '1' },
    { items_description: 'Diesel', unit: 'liters', total_cost: '99975.00', number_series: '2' },
];

function showExampleRows() {
    clearExampleRows();
    let first = null;
    EXAMPLE_ROWS.forEach(eg => {
        const row = addItemRow(eg, null, false, !!eg.isTitle);
        row.classList.add('po-item-demo');
        if (!first) first = row;
    });
    document.getElementById('btnClearExample').classList.remove('d-none');
    refreshItemsSummary();
    first?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    showToast('Example rows added. Press the H button on a row to see it become a '
            + 'LOT / title line — then clear them before saving.', 'warning');
}

function clearExampleRows() {
    document.querySelectorAll('#poItemsTable tbody tr.po-item-demo').forEach(r => r.remove());
    document.getElementById('btnClearExample').classList.add('d-none');
    refreshItemsSummary();
}

function toggleImportGuide() {
    document.getElementById('itemImportGuide').classList.toggle('d-none');
}

async function importItemsIntoGrid(file) {
    const formData = new FormData();
    formData.append('file', file);
    try {
        const res = await fetch('api/purchase_order_item_parse.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to import items.', 'error');
        // A single still-blank starter row gets replaced rather than kept alongside the import.
        const tbody = document.querySelector('#poItemsTable tbody');
        const rows = [...tbody.querySelectorAll('tr')];
        if (rows.length === 1) {
            const onlyRow = rows[0];
            const blank = [...onlyRow.querySelectorAll('input')].every(i => i.value.trim() === '');
            if (blank) onlyRow.remove();
        }
        data.items.forEach(item => addItemRow(item));
        refreshItemsSummary();
        showToast(data.message || 'Items imported.');
    } catch (err) {
        showToast('Unable to import items.', 'error');
        console.error(err);
    }
}

// Supplier name -> address, filled in from api/master_data.php so picking a
// known supplier can autofill its address instead of retyping it every time.
let supplierAddressByName = {};
// Supplier contact numbers from ref_supplier, keyed the way supplier names are
// compared everywhere else (case, punctuation and spacing removed) — a PO
// stores its supplier as free text, so an exact-string lookup would miss most
// of them.
let supplierContactByKey = {};
// Signatory name -> title, same idea for the Print modal's signatory fields.
let signatoryTitleByName = {};
// Initials on file for the people who receive PRs, keyed the same way supplier
// and department names are compared (case, punctuation and spacing removed) so
// a PO's free-text PR Received By name still matches its reference row.
let poUserInitialsByKey = {};
// The first entry in Signatories, used as the default End User on the
// Obligation Request — where the legacy Obligation_Print.vb pre-filled one.
//
// It is deliberately NOT the Purchase Order's signatory any more. "First
// entry" means first by name, and Master Data currently sorts BELLA STEPHANIE
// B. SAAVEDRA (City Treasurer) above JAY WARREN R. PABILLARAN (City Mayor) —
// so every Print dialog opened proposing the Treasurer to sign a PO. That
// picker now uses approvingSignatories below, matched on title.
let defaultSignatory = null;
// The Purchase Order's approving officers: the City Mayor first, the City Vice
// Mayor second. Matched on title, so a new mayor is a Master Data edit.
let approvingSignatories = [];
// True when Master Data has neither title and the picker had to fall back to
// listing everyone — surfaced in the dialog rather than left to be discovered.
let approvingFallback = false;
// Whoever signs the "Existence of available appropriation" certification on
// the Obligation Request. Held separately because that form and the Purchase
// Order are signed by two different people.
let cityBudgetSignatory = null;
// Fallback initials for the box beside "***nothing follows***": one letter per
// word, so "IMELDA B. DAJAO" gives "IBD". Used only when nobody has recorded
// real initials against that name in Master Data -> PR Received By, and only
// ever as a suggestion — the field on the print dialog is free text.
function deriveInitials(name) {
    return String(name == null ? '' : name)
        .split(/\s+/)
        .map(word => word.replace(/[^A-Za-z]/g, '').charAt(0))
        .join('')
        .toUpperCase();
}
// The two lines the certification block printed before it became editable.
// The authority line is not a job title — it is what the signature is given
// under — so it is never read from the signatory record, only used as the
// starting text alongside whatever title that record carries.
const CITY_BUDGET_TITLE_DEFAULT = 'City Budget Officer';
const CITY_BUDGET_AUTHORITY_LINE = 'Head, Budget Unit / Authorized Representative';
// Free-text fields that can't use a <datalist> on a <textarea> — each backs
// its own custom dropdown via bindGrowSuggestField() below. Purpose has no
// reference table of its own (it's mined from actual PO history, with usage
// counts — see api/master_data.php); Department/Supplier Name/Mode of
// Procurement reuse the same ref_* master data the old <datalist>s did.
let purposeSuggestionsList = [];
let categorySuggestionsList = [];
let departmentSuggestionsList = [];
let supplierNameSuggestionsList = [];
let modeOfProcurementSuggestionsList = [];
let divisionSuggestionsList = [];
let prReceivedNameSuggestionsList = [];
let placeOfDeliverySuggestionsList = [];
let workingDaysSuggestionsList = [];
let paymentTermSuggestionsList = [];
// Every auto-growing <textarea> field in the Create/Edit PO modal — used to
// resize/reset them together (see fillForm(), newPurchaseOrder()).
const PO_AUTOGROW_FIELD_IDS = [
    'prNumber', 'category', 'department', 'division', 'section',
    'supplierName', 'supplierAddress', 'prReceivedName', 'referenceNo',
    'bacResolutionNo', 'modeOfProcurement', 'placeOfDelivery',
    'workingDays', 'paymentTerm', 'purpose',
];

// Additional Details "quick fields" — each one is read-only in the main
// form and saved independently through its own pencil button + small modal
// (openQuickUpdate()/saveQuickUpdate()), via the PATCH handlers in
// api/purchase_orders.php. Keeps these operational tracking fields from
// ever being changed as an accidental side effect of editing/saving the
// PO's header or items.
const PO_QUICK_FIELDS = {
    // Manual override on top of the automatic Pending/Approved badge — see
    // setModalStatusBadge() and formatRow(). Blank clears the override.
    status:                  { label: 'Status', type: 'select', display: 'statusValue',
                                options: [
                                    { value: '', label: '— Clear (Normal) —' },
                                    { value: 'On Hold', label: 'On Hold' },
                                    { value: 'Terminated', label: 'Terminated' },
                                    { value: 'Cancelled', label: 'Cancelled' },
                                ] },
    date_received_supplier: { label: 'Date Received Supplier', type: 'datetime-local', display: 'dateReceivedSupplierValue' },
    date_coa_received:      { label: 'COA Date Received',      type: 'date',           display: 'dateCOAReceivedValue' },
    date_approved_po:       { label: 'Approved PO Date',       type: 'date',           display: 'dateApprovedPOValue' },
    barcode_number:         { label: 'Barcode Number',         type: 'text',           display: 'barcodeNumberValue' },
    // 'text', not 'textarea': a <datalist> cannot attach to a textarea, and a
    // remark is a short status line ('Forwarded to CGSO'), not a paragraph.
    remarks:                { label: 'Remarks',                type: 'text',           display: 'remarksValue' },
};
let poQuickFieldValues = {};

function renderQuickFieldDisplay(field) {
    const cfg = PO_QUICK_FIELDS[field];
    const el = document.getElementById(cfg.display);
    if (!el) return;
    const raw = poQuickFieldValues[field] || '';
    let text = raw;
    if (raw && cfg.type === 'datetime-local') {
        text = new Date(raw.replace(' ', 'T')).toLocaleString('en-PH');
    } else if (raw && cfg.type === 'date') {
        text = new Date(raw + 'T00:00:00').toLocaleDateString('en-PH');
    }
    el.textContent = text || '—';
    if (field === 'status') {
        el.classList.remove('is-status-terminated', 'is-status-cancelled', 'is-status-onhold');
        if (raw === 'Terminated') el.classList.add('is-status-terminated');
        else if (raw === 'Cancelled') el.classList.add('is-status-cancelled');
        else if (raw === 'On Hold') el.classList.add('is-status-onhold');
        applyRemarksStatusColor(raw);
    }
}

// Flags Remarks red specifically when Status is Terminated, so an exception
// PO reads as urgent at a glance without having to open the Status field too.
function applyRemarksStatusColor(status) {
    const remarksEl = document.getElementById('remarksValue');
    if (remarksEl) remarksEl.classList.toggle('is-terminated', status === 'Terminated');
}

function openQuickUpdate(field) {
    const poNumber = document.getElementById('poNumber').value;
    if (!poNumber) return; // section is hidden until a PO exists — see setAdditionalDetailsVisible()
    const cfg = PO_QUICK_FIELDS[field];
    const raw = poQuickFieldValues[field] || '';
    document.getElementById('poQuickUpdateField').value = field;
    document.getElementById('poQuickUpdateTitle').textContent = cfg.label;
    const wrap = document.getElementById('poQuickUpdateInputWrap');
    if (cfg.type === 'textarea') {
        wrap.innerHTML = `<textarea class="form-control" id="poQuickUpdateInput" rows="3">${escapeHtml(raw)}</textarea>`;
    } else if (cfg.type === 'datetime-local') {
        wrap.innerHTML = `<input type="datetime-local" class="form-control" id="poQuickUpdateInput" value="${escapeHtml(formatDateTime(raw))}">`;
    } else if (cfg.type === 'date') {
        wrap.innerHTML = `<input type="date" class="form-control" id="poQuickUpdateInput" value="${escapeHtml(raw)}">`;
    } else if (cfg.type === 'select') {
        wrap.innerHTML = `<select class="form-select" id="poQuickUpdateInput">${
            cfg.options.map(o => `<option value="${escapeHtml(o.value)}" ${o.value === raw ? 'selected' : ''}>${escapeHtml(o.label)}</option>`).join('')
        }</select>`;
    } else {
        wrap.innerHTML = `<input type="text" class="form-control" id="poQuickUpdateInput" value="${escapeHtml(raw)}">`;
    }
    // Remarks gets the shared ref_remarks picklist, the same list
    // Competitive Bidding offers. Still free text: a one-off remark must
    // remain typeable, this only keeps the common wordings identical so they
    // can be filtered and counted instead of existing as five spellings.
    if (field === 'remarks') {
        const input = document.getElementById('poQuickUpdateInput');
        input.setAttribute('list', 'dlRemarks');
        input.setAttribute('autocomplete', 'off');
    }
    openModal('poQuickUpdateModal');
}

async function saveQuickUpdate() {
    const field = document.getElementById('poQuickUpdateField').value;
    const poNumber = document.getElementById('poNumber').value;
    let value = document.getElementById('poQuickUpdateInput').value;
    if (PO_QUICK_FIELDS[field].type === 'datetime-local' && value) value = value.replace('T', ' ');
    try {
        const res = await fetch(apiUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ po_number: poNumber, [field]: value })
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to save.', 'error');
        poQuickFieldValues[field] = value;
        renderQuickFieldDisplay(field);
        if (field === 'date_approved_po' || field === 'status') {
            setModalStatusBadge({ date_approved_po: poQuickFieldValues.date_approved_po, status: poQuickFieldValues.status });
        }
        bootstrap.Modal.getInstance(document.getElementById('poQuickUpdateModal')).hide();
        showToast(`${PO_QUICK_FIELDS[field].label} updated.`);
        loadPurchaseOrders();
    } catch (err) {
        showToast('Unable to save.', 'error');
        console.error(err);
    }
}

async function loadAutosuggest() {
    try {
        const res = await fetch('api/master_data.php');
        const data = await res.json();
        if (!data.success) return;
        const cats = data.categories;
        const fill = (listId, rows) => {
            document.getElementById(listId).innerHTML =
                rows.map(r => `<option value="${escapeHtml(r.name)}">`).join('');
        };
        fill('dlSignatories', cats.signatories || []);
        fill('dlRemarks', cats.remarks || []);
        // Everyone who receives PRs, offered as ready-made initials — the ones
        // recorded against their name where they exist, derived where they do not.
        poUserInitialsByKey = {};
        (cats.po_users || []).forEach(u => {
            const stored = (u.extra || '').trim();
            if (stored) poUserInitialsByKey[refKey(u.name)] = stored;
        });
        const initials = [...new Set((cats.po_users || [])
            .map(u => (u.extra || '').trim() || deriveInitials(u.name))
            .filter(Boolean))].sort();
        document.getElementById('dlPoUserInitials').innerHTML =
            initials.map(v => `<option value="${escapeHtml(v)}">`).join('');
        supplierAddressByName = {};
        (cats.suppliers || []).forEach(s => { supplierAddressByName[s.name] = s.extra || ''; });
        supplierContactByKey = {};
        (cats.suppliers || []).forEach(s => {
            const contact = (s.extra2 || '').trim();
            if (contact) supplierContactByKey[refKey(s.name)] = contact;
        });
        signatoryTitleByName = {};
        (cats.signatories || []).forEach(s => { signatoryTitleByName[s.name] = s.extra || ''; });
        // Who may approve a Purchase Order: the Mayor, then the Vice Mayor.
        // Matched on the title so replacing either officer is a Master Data
        // edit, not a code change — the same rule cityBudgetSignatory uses.
        //
        // If Master Data holds neither title we fall back to the whole list
        // rather than leaving the picker empty: a printout nobody can sign is
        // worse than a picker that offers too much, and the hint says why.
        const sigs = cats.signatories || [];
        const isVice  = s => /vice/i.test(s.extra || '');
        const isMayor = s => /mayor/i.test(s.extra || '');
        approvingSignatories = [
            ...sigs.filter(s => isMayor(s) && !isVice(s)),
            ...sigs.filter(s => isMayor(s) && isVice(s)),
        ];
        approvingFallback = approvingSignatories.length === 0;
        if (approvingFallback) approvingSignatories = sigs.slice();
        renderSignatoryOptions();
        // The Obligation Request's End User still defaults from the full list.
        defaultSignatory = (cats.signatories && cats.signatories[0]) || null;
        // Matched on the title rather than pinned to a name, so replacing the
        // budget officer is a Master Data edit and not a code change.
        cityBudgetSignatory = (cats.signatories || []).find(
            sig => /budget/i.test(sig.extra || '')) || null;
        purposeSuggestionsList = cats.purposes || [];
        categorySuggestionsList = cats.categories || [];
        departmentSuggestionsList = cats.departments || [];
        supplierNameSuggestionsList = cats.suppliers || [];
        modeOfProcurementSuggestionsList = cats.modes || [];
        divisionSuggestionsList = cats.divisions || [];
        prReceivedNameSuggestionsList = cats.po_users || [];
        placeOfDeliverySuggestionsList = cats.place_of_delivery || [];
        workingDaysSuggestionsList = cats.working_days || [];
        paymentTermSuggestionsList = cats.payment_term || [];
    } catch (err) {
        console.error(err);
    }
}

// Grows a textarea to fit its content instead of scrolling/clipping —
// shared by every .po-autogrow field (every free-text field in the
// Create/Edit PO modal, plus each item's Description in the items grid).
function growField(field) {
    field.style.height = 'auto';
    field.style.height = field.scrollHeight + 'px';
    sizePoModalColumns();
}

// Caps .po-left-col-scroll and #poItemsTableWrap at #poModal's actual
// available modal-body space (measured, not guessed) so a long/heavily-
// filled record (mainly View, but also a long Create/Edit session) scrolls
// its own field list / item grid internally instead of growing the whole
// modal. A flat CSS vh guess was tried for both and rejected — it either
// clipped short/empty content that would've fit fine, or cut a tall row
// (e.g. a multi-line item description) off mid-line right at the boundary,
// since a vh percentage doesn't track the modal's actual rendered height
// (header/footer size, dialog margins, whether Additional Details is
// showing) the way a direct measurement does. Both are capped at the same
// full modal-body height — generous rather than exact, since each is
// already independently bounded by its own flex-parent's real allocated
// space (.po-card.h-100 / row stretch), same mechanism already relied on
// elsewhere. Called on modal open/resize and after every field resize
// (growField()) so it never falls behind as content changes.
function sizePoModalColumns() {
    const modal = document.getElementById('poModal');
    if (!modal || !modal.classList.contains('show')) return;
    const body = modal.querySelector('.modal-body');
    if (!body) return;
    const maxH = body.clientHeight;
    const leftScroll = document.querySelector('.po-left-col-scroll');
    const itemsWrap = document.getElementById('poItemsTableWrap');
    const additionalDetails = document.getElementById('poAdditionalDetailsSection');
    // Additional Details sits below .po-left-col-scroll, not inside it,
    // specifically so it always renders in full — see the comment on that
    // card in the markup. Its own height (0 while hidden, e.g. during
    // Create) is subtracted from the scroll region's budget so the two
    // together still fit the modal without Additional Details ever being
    // the thing that gets clipped/scrolled out of view.
    const reserved = (additionalDetails && !additionalDetails.classList.contains('d-none')) ? additionalDetails.offsetHeight + 16 : 0;
    if (leftScroll) leftScroll.style.maxHeight = Math.max(maxH - reserved, 120) + 'px';
    if (itemsWrap) itemsWrap.style.maxHeight = maxH + 'px';
}
window.addEventListener('resize', sizePoModalColumns);

// Plain auto-grow, no suggestion dropdown — for free-text fields that never
// had a picklist behind them (PR Number, Category, Section, Supplier
// Address, Reference No., BAC Resolution No., Barcode Number, Remarks).
function bindGrowField(fieldId) {
    const field = document.getElementById(fieldId);
    field.addEventListener('input', () => growField(field));
}

function renderFieldSuggestions(fieldId, boxId, list, query) {
    const box = document.getElementById(boxId);
    const q = query.trim().toLowerCase();
    // Capped at 50 (not truly "all") purely so focusing an empty field with
    // an 800+ row picklist like Suppliers doesn't dump the whole table into
    // the DOM at once — typing a few characters narrows well below that.
    const matches = (q ? list.filter(p => p.name.toLowerCase().includes(q)) : list).slice(0, 50);
    if (!matches.length) { box.classList.add('d-none'); return; }
    box.innerHTML = '';
    matches.forEach(p => {
        const item = document.createElement('div');
        item.className = 'ps-item';
        item.innerHTML = `<span></span>` + (p.uses !== undefined ? `<span class="ps-uses">${p.uses}×</span>` : '');
        item.querySelector('span').textContent = p.name;
        item.addEventListener('mousedown', (e) => {
            e.preventDefault();
            const field = document.getElementById(fieldId);
            field.value = p.name;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            box.classList.add('d-none');
        });
        box.appendChild(item);
    });
    box.classList.remove('d-none');
}

// Wires up growField() + the suggestion dropdown together for one field —
// used for every .po-autogrow field that also needs a picklist (Purpose,
// Department, Supplier Name, Mode of Procurement).
// Same comparison the server uses in refCanonicalKey(): case, punctuation and
// spacing removed, so "5 J CONSUMER  GOODS" and "5J CONSUMER GOODS" match.
function refKey(v) {
    return String(v == null ? '' : v).toUpperCase().replace(/[^A-Z0-9]/g, '');
}

// Flags a value that is not in the master list. Advisory only — a genuinely new
// supplier still has to be typeable — but it is what stops a typo becoming the
// 13th spelling of a supplier that is already on file. The server canonicalises
// independently, so this notice never decides what actually gets stored.
function markUnknownRefValue(fieldId, getList) {
    const field = document.getElementById(fieldId);
    let note = document.getElementById(fieldId + 'RefNote');
    const typed = field.value.trim();
    const list = getList() || [];
    const known = typed === '' || list.some(p => refKey(p.name) === refKey(typed));

    if (known) { if (note) note.remove(); return; }
    if (!note) {
        note = document.createElement('div');
        note.id = fieldId + 'RefNote';
        note.className = 'po-field-hint po-field-hint-warn';
        field.closest('.po-field-group').appendChild(note);
    }
    note.textContent = 'Not in the master list — pick from the suggestions if it already exists, '
                     + 'or add it under Master Data.';
}

// The Department suffix currently appended onto Purpose by syncPurposeDepartment()
// below, e.g. "City Admin" once Purpose reads "For the use of supplier - City Admin" —
// tracked separately from either field's raw value because Purpose legitimately
// contains " - " on its own (a real clause, not just this suffix), so the only safe
// way to find and replace *our own* append later is to remember exactly what we
// added last time. Reset alongside the fields themselves in newPurchaseOrder() and
// fillForm(), so a fresh/loaded record never has a stale suffix to strip.
let poLastAppendedDept = null;

// Keeps Purpose ending in "<what the user typed> - <Department>" without the app
// having to touch the PO Purpose picklist itself: Purpose stays free text, this
// only maintains one suffix on top of it. Runs on blur of either field (fill order
// isn't fixed — Department first is the common case, but Purpose can be edited
// after Department is already set) so the two stay in sync regardless of which one
// changes. Left blank when either side is empty — "" or " - City Admin" alone would
// only look like a mistake, not a feature.
function syncPurposeDepartment() {
    const deptEl = document.getElementById('department');
    const purposeEl = document.getElementById('purpose');
    const dept = deptEl.value.trim();
    let purpose = purposeEl.value;
    const oldSuffix = poLastAppendedDept ? ' - ' + poLastAppendedDept : '';
    if (oldSuffix && purpose.endsWith(oldSuffix)) {
        purpose = purpose.slice(0, -oldSuffix.length);
    }
    purpose = purpose.replace(/\s+$/, '');
    if (dept && purpose) purpose = purpose + ' - ' + dept;
    if (purpose !== purposeEl.value) {
        purposeEl.value = purpose;
        growField(purposeEl);
    }
    poLastAppendedDept = dept || null;
}

function bindGrowSuggestField(fieldId, boxId, getList, checkAgainstMaster) {
    const field = document.getElementById(fieldId);
    const box = document.getElementById(boxId);
    const sync = () => { if (checkAgainstMaster) markUnknownRefValue(fieldId, getList); };
    field.addEventListener('input', () => {
        growField(field);
        renderFieldSuggestions(fieldId, boxId, getList(), field.value);
        sync();
    });
    field.addEventListener('focus', () => renderFieldSuggestions(fieldId, boxId, getList(), field.value));
    field.addEventListener('blur', () => { box.classList.add('d-none'); sync(); });
}

function openModal(modalId) {
    const modalEl = document.getElementById(modalId);
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    return modal;
}

function showPoSavedModal(poNumber, isNew) {
    document.getElementById('poSavedTitle').textContent = isNew ? 'Purchase Order Created' : 'Purchase Order Updated';
    document.getElementById('poSavedSubtitle').textContent = isNew ? 'Your new PO Number is:' : 'PO Number:';
    document.getElementById('poSavedNumber').textContent = poNumber || '—';
    openModal('poSavedModal');
}

async function loadStats() {
    try {
        const res = await fetch(`${apiUrl}?stats=1`);
        const data = await res.json();
        if (!data.success) return;
        const s = data.stats;
        document.getElementById('statPosToday').textContent = s.pos_today;
        document.getElementById('statPosMonth').textContent = s.pos_this_month;
        document.getElementById('statAmountMonth').textContent = '₱' + Number(s.amount_this_month).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('statPendingApproval').textContent = s.pending_approval;
    } catch (err) {
        console.error(err);
    }
}

let firstLoadDone = false;

// Clicking a count chip is just another way to set the Status dropdown —
// one code path drives the filter, so the two controls can't drift apart.
function setStatusFilter(status) {
    document.getElementById('filterStatus').value = status;
    loadPurchaseOrders();
}

function renderCounts(counts) {
    if (!counts) return;
    const fmt = n => Number(n || 0).toLocaleString('en-PH');
    document.getElementById('countTotal').textContent    = fmt(counts.total);
    document.getElementById('countPending').textContent  = fmt(counts.pending);
    document.getElementById('countApproved').textContent = fmt(counts.approved);
    // Overlaps pending/approved by design — see the counts note in the API.
    document.getElementById('countNoSupplier').textContent = fmt(counts.no_supplier);

    const active = document.getElementById('filterStatus').value;
    document.querySelectorAll('.po-chip').forEach(chip => {
        chip.classList.toggle('is-active', chip.dataset.status === active);
    });
}

async function loadPurchaseOrders() {
    const search = encodeURIComponent(document.getElementById('searchText').value.trim());
    const from   = document.getElementById('filterFrom').value;
    const to     = document.getElementById('filterTo').value;
    const status = document.getElementById('filterStatus').value;
    try {
        const res = await fetch(`${apiUrl}?search=${search}&from=${from}&to=${to}&status=${status}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load purchase orders.', 'error');
        renderCounts(data.counts);

        // The DataTables controls (search/length/pagination) only make sense
        // once there's a table to control — so the table stays swapped out
        // for a plain loading placeholder until the first real load lands.
        // (poTable itself is created exactly once, synchronously, at page
        // setup — see DOMContentLoaded — so there's no re-init race here.)
        if (!firstLoadDone) {
            document.getElementById('poListLoading').classList.add('d-none');
            document.getElementById('poTable').classList.remove('d-none');
            // poTable was initialized while its container was display:none
            // (DOMContentLoaded, before this first load resolves), so DataTables
            // couldn't measure real column widths yet — recompute now that it's visible.
            poTable.columns.adjust();
            firstLoadDone = true;
        }
        poTable.clear().rows.add(data.orders.map(formatRow)).draw();
    } catch (err) {
        if (!firstLoadDone) {
            document.getElementById('poListLoading').innerHTML =
                '<div class="text-danger small"><i class="fas fa-triangle-exclamation me-1"></i>Unable to load purchase orders. <a href="#" onclick="loadPurchaseOrders();return false;">Retry</a></div>';
        }
        showToast('Unable to load purchase orders.', 'error');
        console.error(err);
    }
}

function formatRow(row) {
    return {
        po_number: escapeHtml(row.po_number),
        pr_number: escapeHtml(row.pr_number) || '—',
        // Trimmed first, so a supplier that is only whitespace counts as missing
        // and gets the warning pill rather than rendering as a blank cell.
        supplier_name: escapeHtml((row.supplier_name || '').trim()) || '—',
        department: escapeHtml(row.department) || '—',
        category: escapeHtml(row.category) || '—',
        purpose: escapeHtml(row.purpose) || '—',
        date_created: row.date_created || '',
        approved: statusBadgeHtml(row),
        actions: buildActions(row),
    };
}

function fillForm(order) {
    document.getElementById('poId').value = order.id || '';
    document.getElementById('poNumber').value = order.po_number || '';
    document.getElementById('prNumber').value = order.pr_number || '';
    // A DATE column arrives as YYYY-MM-DD; anything longer is trimmed so
    // <input type="date"> accepts it.
    document.getElementById('datePR').value = (order.date_pr || '').slice(0, 10);
    document.getElementById('category').value = order.category || '';
    document.getElementById('department').value = order.department || '';
    // A record saved with this feature already ends Purpose in " - <Department>";
    // remembering that here is what stops the next blur from doubling it up. A
    // record saved before this feature existed has no such suffix, so nothing
    // strips on the first edit — it just gains the suffix once Purpose or
    // Department is touched, same as any other record.
    poLastAppendedDept = order.department || null;
    document.getElementById('division').value = order.division || '';
    document.getElementById('section').value = order.section || '';
    document.getElementById('supplierName').value = order.supplier_name || '';
    document.getElementById('supplierAddress').value = order.supplier_address || '';
    document.getElementById('datePO').value = formatDateTime(order.date_po || '');
    document.getElementById('prReceivedName').value = order.pr_received_name || '';
    document.getElementById('referenceNo').value = order.reference_no || '';
    document.getElementById('bacResolutionNo').value = order.bac_resolution_no || '';
    document.getElementById('modeOfProcurement').value = order.mode_of_procurement || '';
    document.getElementById('placeOfDelivery').value = order.place_of_delivery || '';
    document.getElementById('workingDays').value = order.po_working_days || '';
    document.getElementById('paymentTerm').value = order.po_payment_term || '';
    document.getElementById('purpose').value = order.purpose || '';
    document.getElementById('endUser').value = order.end_user || '';
    // The five "quick field" values (see PO_QUICK_FIELDS) are display-only
    // here — each saves independently via openQuickUpdate()/saveQuickUpdate(),
    // not bundled into this form's own Save button.
    poQuickFieldValues = {
        status: order.status || '',
        date_received_supplier: order.date_received_supplier || '',
        date_coa_received: order.date_coa_received ? order.date_coa_received.slice(0,10) : '',
        date_approved_po: order.date_approved_po ? order.date_approved_po.slice(0,10) : '',
        barcode_number: order.barcode_number || '',
        remarks: order.remarks || '',
    };
    Object.keys(PO_QUICK_FIELDS).forEach(renderQuickFieldDisplay);
    // Values were just set programmatically (no keystroke to trigger the
    // 'input' listener), so every auto-grow field needs an explicit resize —
    // otherwise a long value stays clipped to one row until the user types
    // into it.
    PO_AUTOGROW_FIELD_IDS.forEach(id => growField(document.getElementById(id)));
    const tbody = document.querySelector('#poItemsTable tbody');
    tbody.innerHTML = '';
    (order.items || []).forEach(item => addItemRow(item));
    refreshItemsSummary();
    setModalStatusBadge(order);
}

// Same Pending/Approved vocabulary as the list view's formatRow() —
// reflects whether the PO has been through Approved PO Date yet.
// Status field values map to the .badge-status.st-* classes defined above.
const PO_STATUS_OVERRIDE_CLASS = { 'On Hold': 'st-onhold', 'Terminated': 'st-terminated', 'Cancelled': 'st-cancelled' };

function statusBadgeHtml(order) {
    const override = order && order.status;
    if (override && PO_STATUS_OVERRIDE_CLASS[override]) {
        return `<span class="badge-status ${PO_STATUS_OVERRIDE_CLASS[override]}">${escapeHtml(override)}</span>`;
    }
    const approved = !!(order && order.date_approved_po);
    return approved ? '<span class="badge-status complete">Approved</span>' : '<span class="badge-status pending">Pending</span>';
}

function setModalStatusBadge(order) {
    const badge = document.getElementById('poModalStatusBadge');
    const override = order && order.status;
    const approved = !!(order && order.date_approved_po);
    badge.className = 'badge-status';
    if (override && PO_STATUS_OVERRIDE_CLASS[override]) {
        badge.textContent = override;
        badge.classList.add(PO_STATUS_OVERRIDE_CLASS[override]);
    } else {
        badge.textContent = approved ? 'Approved' : 'Pending';
        badge.classList.add(approved ? 'complete' : 'pending');
    }
}

// A brand-new PO has no supplier-received date, COA date, approval date,
// barcode, or remarks yet — those only make sense once the PO exists, so
// that section is hidden while creating and shown while editing.
function setAdditionalDetailsVisible(visible) {
    document.getElementById('poAdditionalDetailsSection').classList.toggle('d-none', !visible);
    document.getElementById('poModalPrintButton').classList.toggle('d-none', !visible);
}

// Set by chooseFundType() when the fund-source modal is confirmed; sent
// along on create so api/purchase_orders.php's generatePoNumber() can pick
// the right PO-sys/TF-sys/SEF-sys prefix. Defaults to General Fund (PO).
let poFundType = 'PO';

function chooseFundType(fundType) {
    poFundType = fundType;
    bootstrap.Modal.getInstance(document.getElementById('poFundModal')).hide();
    newPurchaseOrder();
}

async function newPurchaseOrder() {
    document.getElementById('poModalTitle').textContent = 'Create Purchase Order';
    document.getElementById('poModalSubtitle').textContent = 'Fill in the details below to create a new Purchase Order.';
    document.getElementById('poForm').reset();
    document.getElementById('poNumber').value = '';
    document.getElementById('poId').value = '';
    poLastAppendedDept = null;
    // form.reset() clears the text but not a previously-grown textarea's
    // inline height (that's a style property, not a form value) — collapse
    // each back to one row so a reopened modal doesn't start out tall.
    PO_AUTOGROW_FIELD_IDS.forEach(id => growField(document.getElementById(id)));
    poQuickFieldValues = {};
    Object.keys(PO_QUICK_FIELDS).forEach(renderQuickFieldDisplay);
    setAdditionalDetailsVisible(false);
    const tbody = document.querySelector('#poItemsTable tbody'); tbody.innerHTML = '';
    refreshItemsSummary();
    setModalStatusBadge(null);
    setPoModalReadOnly(false);
    openModal('poModal');
}

async function editPurchaseOrder(poNumber) {
    try {
        const res = await fetch(`${apiUrl}?po_number=${encodeURIComponent(poNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load purchase order.', 'error');
        document.getElementById('poModalTitle').textContent = 'Edit Purchase Order';
        document.getElementById('poModalSubtitle').textContent = 'Update the details below and save your changes.';
        setAdditionalDetailsVisible(true);
        fillForm(data.order);
        setPoModalReadOnly(false);
        openModal('poModal');
    } catch (err) {
        showToast('Unable to load purchase order.', 'error');
        console.error(err);
    }
}

// View reuses the exact same #poModal as Create/Edit (same cards, same
// field layout, same items grid) rather than a separate read-only template
// — guarantees View always looks like Add/Edit because it IS the same
// markup, just switched into a disabled, buttons-hidden mode instead of a
// second copy of every field that could quietly drift out of sync with it.
async function viewPurchaseOrder(poNumber) {
    try {
        const res = await fetch(`${apiUrl}?po_number=${encodeURIComponent(poNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load purchase order.', 'error');
        document.getElementById('poModalTitle').textContent = 'View Purchase Order';
        document.getElementById('poModalSubtitle').textContent = 'Read-only — details for this Purchase Order.';
        setAdditionalDetailsVisible(true);
        fillForm(data.order);
        setPoModalReadOnly(true);
        openModal('poModal');
    } catch (err) {
        showToast('Unable to load purchase order.', 'error');
        console.error(err);
    }
}

// Toggles #poModal between an editable Create/Edit form and a read-only
// View — disables every field (so nothing's clickable/typeable, but values
// still read fine via .value for anything that re-fills the form later)
// and hides every edit-only control (.po-edit-only: Add Item, Import Items,
// Auto Number Lots, per-row add/remove, Add another row, Save) via the
// .po-view-mode class on the modal itself.
function setPoModalReadOnly(readOnly) {
    document.getElementById('poModal').classList.toggle('po-view-mode', readOnly);
    document.querySelectorAll('#poForm input, #poForm textarea, #poForm select').forEach(el => { el.disabled = readOnly; });
    document.getElementById('poModalCancelLabel').textContent = readOnly ? 'Close' : 'Cancel';
}

// Swaps a button into a disabled "working…" state and back. Stashes the
// original markup on the element itself so the caller doesn't have to know
// what the label/icon was.
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

// Guard against a second submit while the first is still in flight. A PO can
// carry hundreds of item rows (the largest on file has 980) and each one costs
// a couple of round trips to the live server, so a big save legitimately takes
// several seconds. Without this, an impatient second click POSTs again and
// creates a DUPLICATE purchase order under a brand-new PO Number — the save is
// not idempotent, so this has to be blocked at the source, not just visually.
let poSaveInFlight = false;

// Checks every row against the type it has actually been given, rather than
// guessing that type from whether the cells happen to be empty.
//
//   title row — declared by the H button (or recognised when an existing PO is
//               loaded). Carries a description and nothing else.
//   item row  — everything else. Must end up with a Total Cost, because that is
//               the figure the grand total and the printed form are built from.
//
// Inferring "blank means heading" was the earlier version and it was wrong: an
// ordinary row someone typed a description into and then left alone looks
// exactly like a heading, so it saved silently as one. Reading the declared
// type is the whole reason the row type is explicit.
//
// Quantity and Unit Cost are deliberately NOT required on an item row. 1,450
// live rows are lump sums recorded as unit + total only ("Diesel / liters /
// 99,975.00"), which is a real way this office buys.
function invalidItemRows() {
    const bad = [];
    document.querySelectorAll('#poItemsTable tbody tr').forEach((row, i) => {
        const v = k => row.querySelector(`[data-field="${k}"]`).value.trim();
        const num = k => parseFloat(v(k)) || 0;
        if (v('items_description') === '') return; // dropped on save anyway
        if (row.classList.contains('po-item-title')) {
            // Should be unreachable from the UI — setRowType() empties these —
            // but a heading that smuggled a cost through would land in the
            // grand total looking like nothing.
            if (v('unit') !== '' || num('quantity') || num('unit_cost') || num('total_cost')) {
                bad.push({ row, no: i + 1, why: 'title' });
            }
        } else if (num('total_cost') === 0) {
            bad.push({ row, no: i + 1, why: 'item' });
        }
    });
    return bad;
}

async function savePurchaseOrder() {
    if (poSaveInFlight) return;
    // Belt-and-suspenders: normally already applied by the blur handler on
    // Purpose/Department, but Save can be reached without either ever
    // blurring (e.g. the button is clicked while a different field still has
    // focus).
    syncPurposeDepartment();

    const demo = document.querySelectorAll('#poItemsTable tbody tr.po-item-demo');
    if (demo.length) {
        demo[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
        return showToast(`This purchase order still has ${demo.length} example row`
            + `${demo.length === 1 ? '' : 's'} in it. Use "Clear example rows" in the `
            + `import guide before saving.`, 'error');
    }

    document.querySelectorAll('.po-item-invalid').forEach(r => r.classList.remove('po-item-invalid'));
    const bad = invalidItemRows();
    if (bad.length) {
        bad.forEach(b => b.row.classList.add('po-item-invalid'));
        bad[0].row.scrollIntoView({ block: 'center', behavior: 'smooth' });
        const list = ws => ws.slice(0, 6).map(b => '#' + b.no).join(', ')
                        + (ws.length > 6 ? ` and ${ws.length - 6} more` : '');
        const items  = bad.filter(b => b.why === 'item');
        const titles = bad.filter(b => b.why === 'title');
        const parts = [];
        if (items.length) {
            parts.push(`Row ${list(items)} has no Total Cost. Fill in Qty and Unit Cost, enter a `
                     + `Total Cost directly, or press the H button on that row to make it a `
                     + `LOT / title line.`);
        }
        if (titles.length) {
            parts.push(`Row ${list(titles)} is a LOT / title row but still carries a unit or a cost.`);
        }
        return showToast(parts.join(' '), 'error');
    }

    const items = [];
    document.querySelectorAll('#poItemsTable tbody tr').forEach(row => {
        const getVal = key => row.querySelector(`[data-field="${key}"]`).value.trim();
        const item = {
            unit: getVal('unit'),
            items_description: getVal('items_description'),
            quantity: getVal('quantity'),
            unit_cost: getVal('unit_cost'),
            total_cost: getVal('total_cost'),
            number_series: getVal('number_series'),
            // Which of the two shapes the user chose for this row. The server
            // cannot work this out from the values — that is the whole bug.
            is_title: row.classList.contains('po-item-title'),
        };
        if (item.items_description || item.quantity || item.unit_cost) {
            items.push(item);
        }
    });
    if (!items.length) return showToast('Please add at least one item.', 'warning');
    const payload = {
        po_number: document.getElementById('poNumber').value || null,
        pr_number: document.getElementById('prNumber').value.trim(),
        date_pr: document.getElementById('datePR').value,
        category: document.getElementById('category').value.trim(),
        department: document.getElementById('department').value.trim(),
        division: document.getElementById('division').value.trim(),
        section: document.getElementById('section').value.trim(),
        supplier_name: document.getElementById('supplierName').value.trim(),
        supplier_address: document.getElementById('supplierAddress').value.trim(),
        date_po: document.getElementById('datePO').value ? document.getElementById('datePO').value.replace('T', ' ') : null,
        pr_received_name: document.getElementById('prReceivedName').value.trim(),
        reference_no: document.getElementById('referenceNo').value.trim(),
        bac_resolution_no: document.getElementById('bacResolutionNo').value.trim(),
        mode_of_procurement: document.getElementById('modeOfProcurement').value.trim(),
        place_of_delivery: document.getElementById('placeOfDelivery').value.trim(),
        po_working_days: document.getElementById('workingDays').value.trim(),
        po_payment_term: document.getElementById('paymentTerm').value.trim(),
        purpose: document.getElementById('purpose').value.trim(),
        end_user: document.getElementById('endUser').value.trim(),
        fund_type: poFundType,
        items,
        // Barcode Number, Date Received Supplier, COA Date Received, Approved
        // PO Date, and Remarks are NOT part of this payload — those five save
        // independently through their own pencil button (see PO_QUICK_FIELDS,
        // openQuickUpdate()/saveQuickUpdate()), never bundled into this Save.
    };
    const poId = document.getElementById('poId').value;
    const method = poId ? 'PUT' : 'POST';
    const url = apiUrl + (poId ? `?po_number=${encodeURIComponent(document.getElementById('poNumber').value)}` : '');

    poSaveInFlight = true;
    const saveBtn = document.getElementById('savePOButton');
    const cancelBtn = document.querySelector('#poModal .modal-footer .btn-secondary');
    // Saving a large PO rewrites every item row inside one transaction —
    // dismissing the modal mid-flight would hide a save that is still running,
    // so Cancel is held shut until it finishes too.
    setButtonBusy(saveBtn, true, items.length > 40 ? `Saving ${items.length} items…` : 'Saving…');
    if (cancelBtn) cancelBtn.disabled = true;

    try {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to save purchase order.', 'error');
        bootstrap.Modal.getInstance(document.getElementById('poModal')).hide();
        // The PO Number is only known once the backend generates it on
        // create (see generatePoNumber() in api/purchase_orders.php) — on
        // update it's unchanged, so the form's own (already-populated,
        // readonly) field still has it. A centered modal here rather than
        // just a toast, since the PO Modal has already closed and this is
        // the one piece of information (especially on create) the user
        // actually needs to walk away with.
        showPoSavedModal(data.po_number || document.getElementById('poNumber').value, !poId);
        loadPurchaseOrders();
    } catch (err) {
        showToast('Unable to save purchase order.', 'error');
        console.error(err);
    } finally {
        // finally, not just the success path: a validation failure returns
        // early out of the try above, and that must still hand the button back.
        poSaveInFlight = false;
        setButtonBusy(saveBtn, false);
        if (cancelBtn) cancelBtn.disabled = false;
    }
}

async function deletePurchaseOrder(poNumber) {
    if (!confirm('Delete PO ' + poNumber + '?')) return;
    try {
        const res = await fetch(`${apiUrl}?po_number=${encodeURIComponent(poNumber)}`, { method: 'DELETE' });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to delete purchase order.', 'error');
        showToast(data.message || 'Purchase order deleted.');
        loadPurchaseOrders();
    } catch (err) {
        showToast('Unable to delete purchase order.', 'error');
        console.error(err);
    }
}

// Fills the Signatory Name picker and keeps the read-only title in step.
function renderSignatoryOptions() {
    const sel = document.getElementById('printSignatoryName');
    if (!sel) return;
    sel.innerHTML = approvingSignatories.map(s =>
        `<option value="${escapeHtml(s.name)}">${escapeHtml(s.name)}</option>`).join('');
    const hint = document.getElementById('printSignatoryHint');
    hint.classList.toggle('d-none', !approvingFallback);
    hint.textContent = approvingFallback
        ? 'No signatory in Master Data is titled City Mayor or City Vice Mayor, so every signatory is '
        + 'listed here. Set the titles in Master Data → Signatories to narrow this to the two who approve.'
        : '';
    syncSignatoryTitle();
}

// The title always follows the name — it is never typed, so the two cannot
// disagree on a signed document.
function syncSignatoryTitle() {
    const name = document.getElementById('printSignatoryName').value;
    const match = approvingSignatories.find(s => s.name === name);
    document.getElementById('printSignatoryTitle').value =
        (match && match.extra) ? match.extra : 'City Mayor';
}

function openPrintModal(poNumber) {
    selectedPrintPo = poNumber;
    // Back to the Mayor for every print run. Unlike the old free-text box there
    // is nothing here a person can have half-typed, so resetting cannot discard
    // anyone's work — and a Vice Mayor chosen for one PO must not silently
    // carry over onto the next one.
    const nameField = document.getElementById('printSignatoryName');
    if (nameField.options.length) nameField.selectedIndex = 0;
    syncSignatoryTitle();
    openModal('poPrintModal');
    // Back to the default before each PO, so the previous print's choice
    // does not carry over and quietly become this one's.
    document.getElementById('printTypeStandard').checked = true;
    printTypeTouched = false;
    const initialField = document.getElementById('printInitial');
    initialField.value = '';
    initialField.classList.remove('is-invalid');
    // Reset too — refilled from this PO's own Date_PO once checkPrintTypeForPo's
    // fetch lands, same pattern as the initials field just above.
    document.getElementById('printDate').value = '';
    checkPrintTypeForPo(poNumber);
}

// Set once the user clicks either radio themselves, after which the automatic
// pick stops touching it — including when a slow response lands late.
let printTypeTouched = false;
let printTypeToken = 0;

document.querySelectorAll('input[name="printType"]').forEach(r =>
    r.addEventListener('change', () => { printTypeTouched = true; }));

// Chooses the form from what is actually on the purchase order, and says why.
//
// The two forms only differ in the No. column, and the wrong one is silent:
// By LOT with no Number Series prints an empty No. column and nobody notices
// until it is on paper. Measured across the 18,280 live purchase orders,
// 20.6% are LOT jobs (divider rows AND a Number Series) and 73.1% are plainly
// Standard, so this decides ~94% of prints correctly with nothing to click.
//
// The remaining 6.3% have divider rows but no Number Series — neither form is
// right for those, so they stay on Standard (which at least numbers every row)
// and get a warning pointing at Auto Number Lots.
//
// The radios stay visible and stay editable: this preselects, it does not
// decide.
async function checkPrintTypeForPo(poNumber) {
    const box = document.getElementById('printTypeHint');
    const token = ++printTypeToken;
    box.className = 'print-hint d-none';
    box.innerHTML = '';
    try {
        const res  = await fetch(`${apiUrl}?po_number=${encodeURIComponent(poNumber)}`);
        const data = await res.json();
        if (!data.success || token !== printTypeToken) return;
        const items = (data.order && data.order.items) || [];

        // Same fetch already needed for the print-type decision, so the
        // initial costs no extra request. Only fills an empty box — anything
        // typed before the response lands is left alone.
        const initialField = document.getElementById('printInitial');
        if (!initialField.value.trim()) {
            const prName = (data.order && data.order.pr_received_name) || '';
            // What is on file wins; the derived form is only the fallback for
            // someone with no initials recorded yet.
            initialField.value = poUserInitialsByKey[refKey(prName)] || deriveInitials(prName);
        }
        const dateField = document.getElementById('printDate');
        if (dateField && !dateField.value) {
            dateField.value = toDateInputValue(data.order && data.order.date_po);
        }
        // A divider row is a description with no money against it — the same
        // shape Auto Number Lots looks for.
        const dividers = items.filter(i => String(i.items_description || '').trim() !== ''
            && !itemHasValue(i.unit_cost) && !itemHasValue(i.total_cost)).length;
        const withSeries = items.filter(i => String(i.number_series || '').trim() !== '').length;

        let cls, icon, msg, pick;
        if (dividers && withSeries) {
            pick = 'lot'; cls = 'ok'; icon = 'fa-layer-group';
            msg = `<strong>${dividers}</strong> LOT / divider row${dividers === 1 ? '' : 's'} and `
                + `<strong>${withSeries}</strong> row${withSeries === 1 ? '' : 's'} with a Number Series, `
                + `so this prints <strong>by LOT</strong>.`;
        } else if (dividers && !withSeries) {
            pick = 'standard'; cls = 'warn'; icon = 'fa-triangle-exclamation';
            msg = `This purchase order has <strong>${dividers}</strong> row${dividers === 1 ? '' : 's'} with no cost, `
                + `but no Number Series is filled in &mdash; printing <strong>by LOT</strong> would leave the No. column `
                + `completely blank. Kept on <strong>Standard</strong>; run <strong>Auto Number Lots</strong> in the `
                + `item grid if the LOT form is what you want.`;
        } else {
            pick = 'standard'; cls = 'ok'; icon = 'fa-list-ol';
            msg = 'Every row here is a priced item, so this prints <strong>Standard</strong>.';
        }

        // Never overrides a choice the user has already made by hand.
        if (!printTypeTouched) {
            document.getElementById(pick === 'lot' ? 'printTypeLot' : 'printTypeStandard').checked = true;
            msg += ' <span class="print-hint-sub">Chosen from the items on this PO &mdash; change it above if you need to.</span>';
        }
        box.className = 'print-hint ' + cls;
        box.innerHTML = `<i class="fas ${icon}"></i><div>${msg}</div>`;
    } catch (err) {
        // The hint is a convenience; failing to load it must not block printing.
        console.error(err);
    }
}

function fmtMoney2(v) {
    return Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Quantity for the printed form. Unlike money, a quantity has no fixed number
// of decimal places worth showing: the column is decimal(11,3), so MySQL hands
// back "4.000" for four boxes, and printing that literally puts ".000" on
// 128,631 of the 160,813 live item rows for no reason.
//
// parseFloat drops only the trailing zeros the storage format adds — 4.000
// prints as 4, while the 453 rows that hold a genuine fraction keep it:
// 30.500 -> 30.5, 66.670 -> 66.67, 0.500 -> 0.5.
//
// No thousands separator on purpose. Money gets one, quantities never had one
// on this form, and adding it here would change the look of every printout
// beyond the one thing being fixed.
function fmtQty(v) {
    const num = Number(v);
    if (v === null || v === undefined || String(v).trim() === '' || !isFinite(num)) return '';
    return String(parseFloat(num.toFixed(3)));
}

function fmtPrintDate(v) {
    if (!v) return '';
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d)) return v;
    return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
}

// Datetime/date string -> the yyyy-mm-dd an <input type="date"> needs, read
// back with local date parts (not toISOString(), which is UTC and can land
// on the wrong calendar day depending on the browser's timezone).
function toDateInputValue(v) {
    if (!v) return '';
    const d = new Date(String(v).replace(' ', 'T'));
    if (isNaN(d)) return '';
    const pad = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

// Standard English number-to-words, used for the "Amount in Words" line —
// no library needed for whole-peso amounts up to billions.
function numberToWords(num) {
    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
    const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const scales = ['', 'Thousand', 'Million', 'Billion'];

    function chunkToWords(n) {
        let str = '';
        if (n >= 100) { str += ones[Math.floor(n / 100)] + ' Hundred '; n %= 100; }
        if (n >= 20) { str += tens[Math.floor(n / 10)] + ' '; n %= 10; }
        else if (n >= 10) { str += teens[n - 10] + ' '; n = 0; }
        if (n > 0) { str += ones[n] + ' '; }
        return str.trim();
    }

    let intPart = Math.floor(num);
    if (intPart === 0) return 'Zero';
    const parts = [];
    let scaleIndex = 0;
    while (intPart > 0) {
        const chunk = intPart % 1000;
        if (chunk > 0) parts.unshift(chunkToWords(chunk) + (scales[scaleIndex] ? ' ' + scales[scaleIndex] : ''));
        intPart = Math.floor(intPart / 1000);
        scaleIndex++;
    }
    return parts.join(' ').trim();
}

function amountInWords(amount) {
    const pesos = Math.floor(amount);
    const centavos = Math.round((amount - pesos) * 100);
    return `${numberToWords(pesos)} Pesos And ${String(centavos).padStart(2, '0')}/100 Only`;
}

// useNumberSeries: number the "No." column from each item's own Number Series
// instead of counting rows. A by-LOT purchase order carries divider rows
// ("LOT 1", "LOT 2") that are labels, not items — they have no unit, qty or
// cost, and no Number Series. Counting rows gives those dividers a number and
// pushes every real item's number out of step with the Number Series column
// the office actually works from; reading the series instead leaves dividers
// blank and keeps item numbering continuous across lots.
// A zero here means "this row is a label, not a priced item" — scope-of-work
// lines under a LOT carry 0 quantity and 0 cost, and the printed form leaves
// those cells empty rather than papering the page with 0.00.
//
// The obvious `value ? ... : ''` does NOT work: the database hands these back
// as strings, and "0.000" is a non-empty string, so it is truthy. Every one
// of those checks silently passed and printed the zero.
// Tidies a multi-line description for print: drops trailing spaces on each
// line and any blank lines at the end. Leading spaces are left alone — they
// are the indentation the buyer typed, and pre-wrap renders them.
function cleanDescription(v) {
    return String(v == null ? '' : v).replace(/[ 	]+$/gm, '').replace(/\s+$/, '');
}

// Each source line becomes its own block so the page slicer has a break
// candidate every line instead of only at the row boundary. Rendered
// identically: the lines keep pre-wrap, so the buyer's indentation and
// wrapping survive, and an empty line keeps its height via &nbsp;.
function descriptionLines(v) {
    return cleanDescription(v).split('\n')
        .map(line => `<div class="pp-dl">${escapeHtml(line) || '&nbsp;'}</div>`)
        .join('');
}

function itemHasValue(v) {
    return v !== null && v !== undefined && String(v).trim() !== '' && parseFloat(v) !== 0;
}

function buildPrintItemRows(items, startNo, useNumberSeries) {
    return items.map((item, i) => `
        <tr>
            <td class="center">${useNumberSeries ? escapeHtml((item.number_series || '').toString().trim()) : (startNo + i)}</td>
            <td class="center">${itemHasValue(item.quantity) ? escapeHtml(fmtQty(item.quantity)) : ''}</td>
            <td class="center">${escapeHtml(item.unit)}</td>
            <td class="desc">${descriptionLines(item.items_description)}</td>
            <td class="num">${itemHasValue(item.unit_cost) ? fmtMoney2(item.unit_cost) : ''}</td>
            <td class="num">${itemHasValue(item.total_cost) ? fmtMoney2(item.total_cost) : ''}</td>
        </tr>
    `).join('');
}

// Recreates the office's actual printed PO form (BAC letterhead + signature
// block), built client-side from the same order/items data the View modal
// already uses — no server round-trip needed beyond the existing GET.
function buildPrintPreviewHtml(order, type, signatoryName, signatoryTitle, initial, dateStr) {
    const items = order.items || [];
    const grandTotal = items.reduce((sum, i) => sum + (parseFloat(i.total_cost) || 0), 0);

    // Both variants print the SAME single continuous table — the by-LOT form
    // differs only in where the "No." column gets its numbers. The lot
    // structure is already in the data as divider rows the user typed
    // ("LOT 1", "LOT 2") and the Number Series against each item, so there is
    // nothing to regroup: rebuilding it as one table per lot produced a
    // heading and a repeated header row the real form does not have.
    const itemsHtml = `<table class="pp-items"><thead><tr><th>No.</th><th>QTY</th><th>Units</th><th>Item Description</th><th>Unit Cost</th><th>Total</th></tr></thead>
        <tbody>${buildPrintItemRows(items, 1, type === 'lot')}
        <tr><td colspan="6" class="pp-nothing-follows">***nothing follows***${(initial || '').trim() ? `<span class="pp-initial">${escapeHtml(initial.trim())}</span>` : ''}</td></tr>
        </tbody></table>`;

    const workingDays = (order.po_working_days || '').toString();
    const paymentTerm = (order.po_payment_term || '').toString();
    const workingDaysSuffix = /^\d+$/.test(workingDays.trim()) ? ' Working Days' : '';
    // Payment Term prints EXACTLY what Master Data holds — no unit is appended
    // here. It used to gain a hardcoded " Calendar Days" whenever the value was
    // a bare number, which made the ref_payment_term list unable to express
    // anything else: an entry meaning five WORKING days still printed as
    // calendar days. The unit belongs in the list entry ("30 Calendar Days",
    // "5 Working Days"), where the office can edit it without a code change.
    // Delivery Term above still appends, deliberately — it was not part of this
    // change and its list is a different one.
    // The "Date" beside PO No. is left to whatever the preparer typed into the
    // print dialog's Date field (empty by default for a document with no one
    // meaningful date, like a meals/catering order) — not silently pulled from
    // Date_PO or from today. The Crystal source filled it from ?Date_Printed,
    // a print-time parameter, which meant a reprint two months later showed a
    // different date on an otherwise identical document; anchoring it to
    // Date_PO instead once had the opposite problem, forcing a date onto POs
    // where none applies. When this copy was printed is still on the form —
    // in the footer, where it belongs.
    // Supplier phone number, looked up in the master list rather than stored on
    // the purchase order (there is no column for it there).
    //
    // Printed only when it looks like a phone number. Of the 45 numbers on file,
    // 23 are one to three digits — truncated entries like "857" — and putting
    // those on a purchase order as a supplier's contact is worse than leaving
    // the line off. Seven digits is the shortest real local landline.
    const supplierContact = supplierContactByKey[refKey(order.supplier_name)] || '';
    const supplierContactHtml = supplierContact.replace(/\D/g, '').length >= 7
        ? `<div class="pp-supplier-contact">Tel. No.: ${escapeHtml(supplierContact)}</div>`
        : '';

    // Verification QR. Scanning it yields the PO number, its current lifecycle
    // status, and whether this copy is the original or a later revision — the
    // same facts printed underneath, so the paper stays self-checking even if
    // the reader has no scanner. Omitted entirely if the server couldn't
    // build it, rather than printing a broken image on an audited document.
    // Code 128 of the PO number, sitting in the letterhead just left of the
    // BAC seal. Complements the QR at the bottom: this one scans straight into
    // a "PO number" field on any warehouse/COA scanner, where the QR carries a
    // whole verification URL. Omitted rather than shown broken if the server
    // couldn't build it.
    const barcodeHtml = order.barcode_png ? `
            <div class="pp-barcode-float">
                <img class="pp-barcode" src="${order.barcode_png}" style="width:${order.barcode_width || 176}px" alt="Barcode of ${escapeHtml(order.po_number)}">
                <div class="pp-barcode-caption">${escapeHtml(order.barcode_text || order.po_number)}</div>
            </div>` : '';

    const v = order.verification || {};
    const qrHtml = order.qr_png ? `
                <td class="pp-qr-cell">
                    <img class="pp-qr" src="${order.qr_png}" alt="Purchase Order verification QR code">
                    <!-- The lifecycle status ("Pending Approval" etc.) used to
                         print here and was removed: it is only true at the
                         moment of printing, so an approved PO would keep
                         carrying "Pending Approval" on paper forever. The live
                         status is on the verifier page the QR opens, which is
                         always current. ORIGINAL/EDITED stays because that is a
                         property of this printed copy, not of the record. -->
                    <div class="pp-qr-caption">
                        <strong>${v.original ? 'ORIGINAL' : 'EDITED'}</strong><br>
                        ${escapeHtml(v.code || '')}
                        <div class="pp-qr-scan">Scan to verify</div>
                    </div>
                </td>` : '';

    return `
    <div class="print-page">
      <div class="pp-frame pp-frame-full">
        <div class="pp-letterhead-wrap">
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
        ${barcodeHtml}
        </div>
        <hr class="pp-rule">

        <div class="pp-title-center">PURCHASE ORDER</div>

        <table class="pp-header-grid">
            <tr>
                <td class="pp-hg-label">SUPPLIER</td>
                <td class="pp-hg-value">${escapeHtml(order.supplier_name)}</td>
                <td class="pp-hg-label">PO NO.</td>
                <td class="pp-hg-value pp-po-number">${escapeHtml(order.po_number)}</td>
            </tr>
            <tr>
                <td class="pp-hg-label">ADDRESS</td>
                <td class="pp-hg-value">${escapeHtml(order.supplier_address)}${supplierContactHtml}</td>
                <td class="pp-hg-label">DATE</td>
                <td class="pp-hg-value">${(dateStr || '').trim() ? escapeHtml(dateStr.trim()) : '&nbsp;'}</td>
            </tr>
            <tr>
                <td class="pp-hg-label">MODE OF<br>PROCUREMENT</td>
                <td class="pp-hg-value">${escapeHtml(order.mode_of_procurement)}</td>
                <td class="pp-hg-label">PR NO.</td>
                <td class="pp-hg-value">${escapeHtml(order.pr_number)}</td>
            </tr>
            <tr>
                <td class="pp-hg-label">REF #</td>
                <td class="pp-hg-value">${escapeHtml(order.reference_no)}</td>
                <td class="pp-hg-label">BAC REF#</td>
                <td class="pp-hg-value">${escapeHtml(order.bac_resolution_no)}</td>
            </tr>
        </table>

        <div class="pp-gentlemen">
            Gentlemen<br>
            <span class="pp-indent">Please furnish this office the following articles subject to the terms and conditions contained herein :</span>
        </div>

        <table class="pp-block3">
            <tr>
                <td class="col1">Place of Delivery : <span class="pp-underline">${escapeHtml(order.place_of_delivery)}</span></td>
                <td class="col3"><div>Delivery Term <span class="pp-underline pp-underline-sm">${escapeHtml(workingDays)}</span>${workingDaysSuffix}</div></td>
            </tr>
            <tr>
                <td class="col1">Date of Delivery: <span class="pp-underline">&nbsp;</span></td>
                <td class="col3"><div>Payment Term <span class="pp-underline pp-underline-sm">${escapeHtml(paymentTerm)}</span></div></td>
            </tr>
        </table>

        ${itemsHtml}

        ${order.note && order.note.trim() !== '' ? `<div class="pp-note"><strong>Note:</strong> ${escapeHtml(order.note)}</div>` : ''}

        <div class="pp-summary-box">
            <table class="pp-totals">
                <tr>
                    <td>Amount in Words : <span class="pp-underline" style="min-width:420px;">${amountInWords(grandTotal)}</span></td>
                    <td class="pp-total-label">TOTAL:</td>
                    <td class="pp-total-value">${fmtMoney2(grandTotal)}</td>
                </tr>
            </table>
            <table class="pp-purpose-row">
                <tr>
                    <td class="pp-purpose-label"><em>PURPOSE :</em></td>
                    <td>${escapeHtml(order.purpose)}</td>
                </tr>
            </table>
        </div>

        <div class="pp-terms">
            In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one (1) percent for every day of delay shall be imposed.<br>
            I have read and agree with the terms and condition stipulated at the back of the Purchase Order.
        </div>

        <table class="pp-conforme-band">
            <tr>
                <td class="pp-conforme-cell">Conforme:</td>
                ${qrHtml}
            </tr>
        </table>
        <table class="pp-sign-row">
            <tr>
                <td class="pp-sign-left">
                    <div class="pp-sign-line"></div>
                    ${order.requested_by && order.requested_by.trim() !== '' ? `<div class="pp-sign-name">${escapeHtml(order.requested_by)}</div>` : ''}
                    <div class="pp-sign-caption">(Signature over printed name )</div>
                    <div class="pp-sign-line" style="margin-top:18px;"></div>
                    <div class="pp-sign-caption">(Date)</div>
                </td>
                <td class="pp-sign-right">
                    <div class="pp-sign-line"></div>
                    <div class="pp-sign-name">${escapeHtml(signatoryName) || '&nbsp;'}</div>
                    <div class="pp-sign-title">${escapeHtml(signatoryTitle)}</div>
                </td>
            </tr>
        </table>
      </div>
    </div>`;
}

async function openPrintPreview(includeFooter = true) {
    const type = document.querySelector('input[name="printType"]:checked').value;
    if (!selectedPrintPo) return;
    currentPreviewIncludeFooter = includeFooter;
    const signatoryName = document.getElementById('printSignatoryName').value.trim();
    const signatoryTitle = document.getElementById('printSignatoryTitle').value.trim();

    // Required: the initials say who prepared the copy, and a form that goes
    // out without them cannot be traced back to anyone. Checked here rather
    // than with the browser's own validation because this input is not inside
    // a <form> — nothing would enforce the `required` attribute on its own.
    const initialField = document.getElementById('printInitial');
    if (!initialField.value.trim()) {
        showToast('Enter the initials before printing — they identify who prepared this copy.', 'error');
        initialField.classList.add('is-invalid');
        initialField.focus();
        return;
    }
    initialField.classList.remove('is-invalid');
    try {
        const res = await fetch(`${apiUrl}?po_number=${encodeURIComponent(selectedPrintPo)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load purchase order.', 'error');
        const initial = document.getElementById('printInitial').value.trim();
        const dateStr = fmtPrintDate(document.getElementById('printDate').value);
        document.getElementById('printPreviewArea').innerHTML =
            buildPrintPreviewHtml(data.order, type, signatoryName, signatoryTitle, initial, dateStr);
        bootstrap.Modal.getInstance(document.getElementById('poPrintModal')).hide();
        const previewModalEl = document.getElementById('poPrintPreviewModal');
        openModal('poPrintPreviewModal');
        // Wait for the modal's fade-in transition to actually finish before
        // rasterizing — Bootstrap animates .modal-dialog with a CSS
        // transform while showing, and html2canvas reading layout mid-
        // transition can capture an inconsistent (sometimes zero-size) box.
        previewModalEl.addEventListener('shown.bs.modal', () => generatePdfPreview(data.order.po_number), { once: true });
    } catch (err) {
        showToast('Unable to load purchase order.', 'error');
        console.error(err);
    }
}

let currentPdfBlobUrl = null;
let currentPdfFileName = 'purchase_order.pdf';
// The PO whose preview is on screen, so changing paper or text size can
// re-render without going back through the options dialog.
let currentPreviewPo = null;
// Whether the current preview includes the "Date Printed / Page N of M"
// footer stamp — set by which print button was pressed (see openPrintPreview()),
// remembered alongside currentPreviewPo so a paper/text-size change re-renders
// with the same choice instead of silently bringing the footer back.
let currentPreviewIncludeFooter = true;

// Paper and item-text size are a property of the printer and the operator's
// eyesight, not of any one purchase order, so they are remembered rather than
// re-chosen on every print. localStorage can throw outright (private windows,
// site data blocked), so every access is guarded and simply falls back to the
// defaults — a preference that fails to persist must not stop a form printing.
function readPrintPref(key, fallback) {
    try { const v = localStorage.getItem(key); return v === null ? fallback : v; } catch (e) { return fallback; }
}
function writePrintPref(key, value) {
    try { localStorage.setItem(key, value); } catch (e) { /* not worth surfacing */ }
}


// PDF open parameters appended to the blob URL. navpanes=0 asks the viewer to
// start with its thumbnail/bookmark sidebar collapsed, so the preview opens
// showing the page itself rather than a page-strip taking a third of the
// width. These are a hint, not a guarantee — they are honoured by Adobe
// Reader and Edge, while Chrome's built-in viewer treats its sidebar as a
// user preference it remembers, and may ignore them. Appending them is
// harmless where unsupported: the fragment is simply dropped.
const PDF_VIEW_PARAMS = '#navpanes=0&toolbar=1&view=FitH';

// ── Row-aware page slicing ──────────────────────────────────────────────
// The printable page is rasterised into one tall canvas, then cut into
// Letter-sized pages. Cutting at a fixed page-height interval slices straight
// through whichever table row straddles the boundary, so an item ends up with
// its top half on one page and its bottom half on the next. These two helpers
// cut at row boundaries instead.

// Y offsets (in canvas pixels) where a cut is safe: the top edge of every row
// and of each major block in the form.
function collectSafePageBreaks(source, canvas) {
    const pxScale = canvas.height / source.offsetHeight;
    const srcTop = source.getBoundingClientRect().top;
    const offsets = [];
    source.querySelectorAll('tr, .pp-dl, .pp-lot-title, .pp-summary-box, .pp-terms, .pp-conforme-band, .pp-sign-row')
        .forEach(el => {
            const top = el.getBoundingClientRect().top - srcTop;
            if (top > 0) offsets.push(Math.round(top * pxScale));
        });
    offsets.push(canvas.height);
    return [...new Set(offsets)].sort((a, b) => a - b);
}

// Where each bordered grid sits on the canvas, so a page cut landing inside
// one can be closed off. The item table's column rules are drawn by the cells
// themselves; slicing the raster between two rows therefore leaves the
// verticals running to the edge of the page with nothing capping them, and the
// next page starting the same way. The legacy Crystal printout closed both
// edges with a rule, and so does this — see drawGridEdge().
function collectGridSpans(source, canvas) {
    // Per-axis scales rather than one: html2canvas rasterises uniformly, but
    // the two ratios can round apart by a pixel on a tall page, and a rule
    // that stops a pixel short of the column it is closing is visible.
    const xScale = canvas.width  / source.offsetWidth;
    const yScale = canvas.height / source.offsetHeight;
    const srcRect = source.getBoundingClientRect();
    return [...source.querySelectorAll('.pp-items')].map(el => {
        const r = el.getBoundingClientRect();
        return {
            top:    Math.round((r.top    - srcRect.top)  * yScale),
            bottom: Math.round((r.bottom - srcRect.top)  * yScale),
            left:   Math.round((r.left   - srcRect.left) * xScale),
            right:  Math.round((r.right  - srcRect.left) * xScale),
        };
    });
}

// Caps a sliced grid with a horizontal rule at the top or bottom of the page,
// matching the 1px cell borders (scaled to canvas pixels, so it lines up with
// the verticals it is closing rather than looking hairline next to them).
function drawGridEdge(ctx, spans, cutY, sliceH, edge, pxScale) {
    const lineW = Math.max(1, Math.round(pxScale));
    // Strictly inside: a cut exactly on the grid's own top or bottom edge is
    // already closed by that border, and would only be doubled here.
    spans.filter(s => cutY > s.top && cutY < s.bottom).forEach(s => {
        ctx.fillStyle = '#000000';
        ctx.fillRect(s.left, edge === 'top' ? 0 : sliceH - lineW, s.right - s.left, lineW);
    });
}

// Draws the canvas into the PDF one page at a time, ending each page on the
// last safe break that still fits.
// Safe printable inset, in points. 18pt = 0.25in (6.35mm), comfortably
// outside the ~4-6mm non-printable band virtually every office printer has.
const PDF_PAGE_MARGIN_PT = 18;

// The paper sizes this form can be printed on, in points (1pt = 1/72in).
//
// A4 is first because it is what the office actually stocks, and getting this
// wrong is not cosmetic: the output was 'letter' (612pt / 8.5in wide) for a
// while and printed with a visibly wider left margin than right. The draw is
// symmetric — the image goes in at x=18pt with width pageW-36 — so an unequal
// margin on paper can only mean the sheet is narrower than the PDF page: the
// driver lays the page down from the left and the surplus falls off the right.
// A4 is 16.7pt narrower than Letter, which turned an 18pt right margin into
// about 1pt while the left stayed 18pt, and is the same overflow that lost the
// right border entirely before the margin existed.
//
// Long / Folio is 8.5 x 13in and Legal 8.5 x 14in. Both are stocked here and
// both are taller than A4, which is the whole reason they are offered: a purchase
// order that spills three lines onto a second sheet on A4 fits on one sheet of
// Long. Letter is included for completeness but is SHORTER than A4, so Auto
// never reaches for it.
const PDF_PAPER_SIZES = {
    a4:     { label: 'A4',           size: [595.28, 841.89] },
    long:   { label: 'Long / Folio', size: [612, 936] },
    legal:  { label: 'Legal',        size: [612, 1008] },
    letter: { label: 'Letter',       size: [612, 792] },
};

// What Auto tries, in order. Smallest sheet first: Auto's job is to reach for a
// bigger sheet only when a smaller one would cost an extra page, never to
// quietly move the job onto paper that may not be in the tray.
const PDF_AUTO_PAPER_ORDER = ['a4', 'long', 'legal'];
const PDF_DEFAULT_PAPER = 'a4';

// How far Auto may shrink a form to keep it on one sheet, once every paper size
// has been tried at full size. 0.9 takes the 11px body text to a hair under
// 10px, which still photocopies and still reads. Past that the saving is not
// worth the legibility, and a second sheet is the better answer — so it stops
// and paginates, and the status line says the form is on two pages.
const PDF_FIT_MIN_SCALE = 0.9;

// Fixed paper for the Obligation Request slip, which is a single short form and
// has no pagination to optimise.
const PDF_PAGE_FORMAT = PDF_PAPER_SIZES[PDF_DEFAULT_PAPER].size;


// Height reserved at the foot of every sheet for the page footer, in points.
// Taken out of the content area, so the form is scaled to fit above it rather
// than being overprinted by it.
const PDF_FOOTER_BAND_PT = 20;

// When this copy was printed, formatted the way the Crystal report did it
// (mm/dd/yyyy and a lowercase 12-hour clock with no space: "9:19:50am").
function printedStamp() {
    const now = new Date();
    return {
        date: now.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' }),
        time: now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' })
                 .toLowerCase().replace(/ /g, ''),
    };
}

// The page footer, drawn on every sheet — "Date Printed" left, "Page N of M"
// right, matching the legacy report's Page Footer section. Written as PDF text
// rather than as part of the form: the form is one tall raster sliced into
// pages, so anything inside it can only ever appear once, on whichever page it
// happens to fall on. Only here is the page count even known.
function drawPageFooter(pdf, pageNum, pageCount, stamp) {
    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();
    // Sits inside the same printable inset as the form, on the baseline of the
    // reserved band so descenders stay clear of the paper edge.
    const baseline = pageH - PDF_PAGE_MARGIN_PT - 4;
    pdf.setTextColor(0, 0, 0);
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(7.5);
    pdf.text(`Date Printed: ${stamp.date}   ${stamp.time}`, PDF_PAGE_MARGIN_PT, baseline);
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(10);
    pdf.text(`Page ${pageNum} of ${pageCount}`, pageW - PDF_PAGE_MARGIN_PT, baseline, { align: 'right' });
}

// Where each page ends, in canvas pixels. Split out from the drawing so the
// rule about short last pages has somewhere to live.
//
// Greedy fill, snapped back to the last safe break that fits — then one
// correction: a final page holding nothing but the signature block reads as a
// mistake on a printed PO, so if the tail comes out very short the previous
// page is cut earlier to give it company. Both pages stay legal cuts; only the
// balance between them changes.
function computePageCuts(totalPx, maxSlicePx, safeBreaks) {
    const MIN_SLICE_PX = 60;                       // never emit a sliver of a page
    const MIN_TAIL_PX  = Math.round(maxSlicePx * 0.25);
    const cuts = [];
    let y = 0;
    while (y < totalPx) {
        let end = Math.min(y + maxSlicePx, totalPx);
        if (end < totalPx) {
            // Last safe boundary that fits on this page. If nothing qualifies —
            // a single block taller than a whole page — fall back to the hard
            // cut rather than looping forever.
            const fit = safeBreaks.filter(b => b > y + MIN_SLICE_PX && b <= end).pop();
            if (fit) end = fit;
        }
        cuts.push(end);
        y = end;
    }
    if (cuts.length >= 2) {
        const tailStart = cuts[cuts.length - 2];
        if (totalPx - tailStart < MIN_TAIL_PX) {
            const pageStart = cuts.length >= 3 ? cuts[cuts.length - 3] : 0;
            const earlier = safeBreaks.filter(b =>
                b > pageStart + MIN_SLICE_PX && b <= totalPx - MIN_TAIL_PX).pop();
            if (earlier) cuts[cuts.length - 2] = earlier;
        }
    }
    return cuts;
}

// The drawable box on a given sheet, in points. Derived rather than tabulated
// so every size automatically gets the same printable inset and footer band.
function paperGeometry(sizePt) {
    const [pageW, pageH] = sizePt;
    return {
        pageW, pageH,
        contentW: pageW - PDF_PAGE_MARGIN_PT * 2,
        contentH: pageH - PDF_PAGE_MARGIN_PT * 2 - PDF_FOOTER_BAND_PT,
    };
}

// How many sheets this canvas needs on a given paper, and where they cut.
// Pure arithmetic on the numbers computePageCuts already works from, so Auto can
// price every paper size without building (or drawing) a single PDF.
function planPaper(key, canvas, safeBreaks) {
    const g = paperGeometry(PDF_PAPER_SIZES[key].size);
    // canvas.width always maps to the full content width, whatever the sheet —
    // the form is drawn edge to edge of the printable box — so a wider sheet
    // means fewer canvas pixels per point and a taller one means more of them
    // fit. Both effects are already in this one number.
    const maxSlicePx = Math.floor(g.contentH * (canvas.width / g.contentW));
    return { key, g, maxSlicePx, cuts: computePageCuts(canvas.height, maxSlicePx, safeBreaks), fitScale: 1, note: '' };
}

// Picks the sheet to print on.
//
// An explicit choice is honoured exactly, including one that costs extra pages:
// if the operator says Legal it is because Legal is in the tray.
//
// Auto costs the form on every paper size and takes the one that needs the
// fewest sheets, ties going to the smallest paper — so it only ever reaches for
// bigger stock when doing so actually saves a sheet, and a form that fits on A4
// stays on A4. That covers both the one-page case and a long purchase order
// that runs to three A4 sheets but two of Long.
//
// If nothing holds it on one sheet at full size, a small shrink is tried — the
// same thing a person does by hand when a few lines hang over the bottom. That
// pass goes LARGEST sheet first, because the largest sheet is by definition the
// one closest to fitting and so needs the least shrinking: squeezing a form to
// 92% on Long when Legal would have taken it at 99% is the worse print, and the
// paper has to change either way at that point.
function choosePaperLayout(canvas, safeBreaks, requested) {
    if (requested && requested !== 'auto' && PDF_PAPER_SIZES[requested]) {
        return planPaper(requested, canvas, safeBreaks);
    }

    let best = null;
    for (const key of PDF_AUTO_PAPER_ORDER) {
        const plan = planPaper(key, canvas, safeBreaks);
        // Strictly fewer, so an equal page count leaves the earlier (smaller)
        // paper in place rather than upgrading for nothing.
        if (!best || plan.cuts.length < best.cuts.length) best = plan;
    }
    if (best.cuts.length === 1) {
        if (best.key !== PDF_DEFAULT_PAPER) best.note = 'switched from A4 to keep it on one sheet';
        return best;
    }

    for (const key of [...PDF_AUTO_PAPER_ORDER].reverse()) {
        const plan = planPaper(key, canvas, safeBreaks);
        const needed = plan.maxSlicePx / canvas.height;
        if (needed >= PDF_FIT_MIN_SCALE) {
            plan.cuts = [canvas.height];
            plan.fitScale = needed;
            // Floored, not rounded: a form squeezed to 99.6% must not report
            // itself as printed at 100%.
            const parts = key === PDF_DEFAULT_PAPER ? [] : ['switched from A4'];
            parts.push('scaled to ' + Math.floor(needed * 100) + '%');
            plan.note = parts.join(', ') + ' to keep it on one sheet';
            return plan;
        }
    }

    // Genuinely more than one sheet however it is cut. `best` already holds the
    // paper that needs the fewest of them.
    if (best.key !== PDF_DEFAULT_PAPER) best.note = 'switched from A4 to save a sheet';
    return best;
}

function addCanvasToPdfPaged(pdf, canvas, layout, gridSpans = [], pxScale = 1, stamp = null) {
    // Printers cannot mark the outermost few millimetres of a sheet. Drawing
    // the canvas edge-to-edge left the form frame only ~3.7mm in, inside that
    // non-printable band, so the top and right borders vanished on real
    // hardware even though they were present in the on-screen preview.
    // Insetting by PDF_PAGE_MARGIN_PT keeps the whole frame printable.
    const contentW = layout.g.contentW;
    const pxPerPt = canvas.width / contentW;    // canvas px per PDF point

    // Cuts were computed by choosePaperLayout, not here, because the paper size
    // is chosen from the page count and the footer on page 1 has to state the
    // total — both need the pagination decided before a single page is drawn.
    const cuts = layout.cuts;
    const fitScale = layout.fitScale || 1;
    let y = 0;
    let firstPage = true;
    for (const [i, end] of cuts.entries()) {
        const sliceH = end - y;
        const slice = document.createElement('canvas');
        slice.width = canvas.width;
        slice.height = sliceH;
        const ctx = slice.getContext('2d');
        ctx.fillStyle = '#ffffff';                 // JPEG has no alpha; unfilled area would go black
        ctx.fillRect(0, 0, slice.width, slice.height);
        ctx.drawImage(canvas, 0, y, canvas.width, sliceH, 0, 0, canvas.width, sliceH);
        // Cap the item grid wherever this page cuts through it: a rule at the
        // bottom where the page leaves off, and one at the top of the page
        // that carries on, so the column rules never run off an open edge.
        drawGridEdge(ctx, gridSpans, y,   sliceH, 'top',    pxScale);
        drawGridEdge(ctx, gridSpans, end, sliceH, 'bottom', pxScale);

        if (!firstPage) pdf.addPage();
        // Scaled about the top-left of the printable box rather than centred:
        // when a form is shrunk to save a sheet, the letterhead staying exactly
        // where the reader expects it matters more than the block being
        // optically centred. fitScale is 1 unless Auto had to shrink.
        pdf.addImage(slice.toDataURL('image/jpeg', 0.95), 'JPEG', PDF_PAGE_MARGIN_PT, PDF_PAGE_MARGIN_PT,
            contentW * fitScale, (sliceH / pxPerPt) * fitScale);

        if (stamp) drawPageFooter(pdf, i + 1, cuts.length, stamp);
        firstPage = false;
        y = end;
    }
}

// Says which sheet the PDF came out on and how many of them there are, plus
// what Auto did if it did anything. Stated rather than left to be noticed: the
// operator is about to feed paper into a printer, and "this is on Long" or
// "this was scaled to 94%" is exactly the kind of thing that must not be a
// surprise discovered after the fact.
function showLayoutStatus(layout) {
    const el = document.getElementById('pdfLayoutStatus');
    if (!el) return;
    if (!layout) { el.innerHTML = ''; return; }
    const sheets = layout.cuts.length;
    el.innerHTML = '<span>' + escapeHtml(PDF_PAPER_SIZES[layout.key].label) + '</span>'
        + '<span class="pp-sheets">' + sheets + (sheets === 1 ? ' page' : ' pages') + '</span>'
        + (layout.note ? '<span class="pp-layout-note">' + escapeHtml(layout.note) + '</span>' : '');
}

// The selects are disabled while a PDF is being built, because each change
// re-rasterises the whole form and two overlapping renders would race to set
// the same iframe.
function setPreviewToolbarBusy(busy) {
    ['pdfPaperSize', 'pdfTextSize'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = busy;
    });
}

// Snapshots the already-built #printPreviewArea (via html2canvas) and lays the
// image into a real PDF (via jsPDF), on whichever sheet choosePaperLayout picks
// and across as many pages as the content needs — so what shows up here is an
// actual PDF file (viewable/printable/downloadable through the browser's own
// PDF viewer), not just an HTML page pretending to be one.
async function generatePdfPreview(poNumber) {
    currentPreviewPo = poNumber;

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
    setPreviewToolbarBusy(true);
    showLayoutStatus(null);

    try {
        const source = document.querySelector('#printPreviewArea .print-page');
        if (!source) throw new Error('Print content did not render — try closing and reopening the print dialog.');

        // Item text size, applied to the live source before it is rasterised —
        // the PDF is a photograph of this element, so the text has to be the
        // right size here or it is only ever an enlargement of an 11px image.
        const textStep = document.getElementById('pdfTextSize').value;
        source.classList.remove('pp-text-1', 'pp-text-2');
        if (textStep !== '0') source.classList.add('pp-text-' + textStep);

        // A PO with a lot of items makes this element very tall; rasterizing
        // that at a fixed scale:2 can exceed the browser's max canvas
        // dimensions (commonly ~16384px/side) and fail outright. Scale down
        // proportionally for tall content instead of always using scale:2 —
        // trades a bit of sharpness on long POs for not breaking at all.
        const MAX_CANVAS_PX = 14000;
        const scale = Math.min(2, MAX_CANVAS_PX / Math.max(source.offsetHeight, 1));

        const canvas = await html2canvas(source, {
            scale, backgroundColor: '#ffffff', useCORS: true, logging: false, imageTimeout: 15000
        });
        if (!canvas.width || !canvas.height) {
            throw new Error('Rendered page came out empty (0x0) — the print content may not have laid out correctly.');
        }

        // Probe that the canvas is exportable before slicing it, so a tainted
        // or oversized canvas fails here with a clear message rather than
        // part-way through page assembly.
        try {
            canvas.toDataURL('image/jpeg', 0.95);
        } catch (e) {
            throw new Error('Could not export the rendered page as an image (' + e.message + ').');
        }

        const safeBreaks = collectSafePageBreaks(source, canvas);
        const layout = choosePaperLayout(canvas, safeBreaks, document.getElementById('pdfPaperSize').value);
        const pdf = new jspdf.jsPDF({ unit: 'pt', format: PDF_PAPER_SIZES[layout.key].size });
        addCanvasToPdfPaged(pdf, canvas, layout, collectGridSpans(source, canvas),
            canvas.height / source.offsetHeight, currentPreviewIncludeFooter ? printedStamp() : null);
        showLayoutStatus(layout);

        const blob = pdf.output('blob');
        if (currentPdfBlobUrl) URL.revokeObjectURL(currentPdfBlobUrl);
        currentPdfBlobUrl = URL.createObjectURL(blob);
        currentPdfFileName = `PO_${poNumber}.pdf`;

        frame.src = currentPdfBlobUrl + PDF_VIEW_PARAMS;
        loading.classList.add('d-none');
        frame.classList.remove('d-none');
        downloadBtn.disabled = false;
        printBtn.disabled = false;
        if (openTabBtn) openTabBtn.disabled = false;
    } catch (err) {
        // Surfaced directly (not a generic message) so a failure is
        // actually diagnosable instead of a dead end.
        loading.innerHTML = '<div class="text-danger small px-3"><i class="fas fa-triangle-exclamation me-1"></i>Unable to generate PDF preview: '
            + escapeHtml(err.message || String(err)) + '</div>';
        console.error(err);
    } finally {
        // In finally, not at the end of the try: a failed render must still
        // give the selects back, since changing the paper or the text size is
        // one of the few things that might get it to render at all.
        setPreviewToolbarBusy(false);
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
        // Some browsers' built-in PDF viewer doesn't expose a scriptable
        // print() — fall back to opening it in a new tab, where the
        // viewer's own toolbar (Ctrl+P / print icon) works normally.
        window.open(currentPdfBlobUrl, '_blank');
    }
}

// ═══════════════ Obligation Request ═══════════════
// Replaces Obligation_Print.vb. Same fields/defaults as that dialog: City
// Budget defaults to the literal text "CITY BUDGET" (never dynamically set
// in the legacy app either — staff just overwrite it by hand), End User
// defaults to whatever's in the Signatories master data list, Total Amount
// is SUM(Total_Cost) across the PO's items (mirrors the legacy aggregate
// query), and it's read-only here since it's derived, not typed.
let obligationOrder = null;

async function openObligationModal(poNumber) {
    try {
        const res = await fetch(`${apiUrl}?po_number=${encodeURIComponent(poNumber)}`);
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to load purchase order.', 'error');
        obligationOrder = data.order;
        const total = (obligationOrder.items || []).reduce((sum, i) => sum + (parseFloat(i.total_cost) || 0), 0);

        document.getElementById('obligationPoNumber').value = obligationOrder.po_number;
        // Suggest the scannable form of the PO number (hyphen stripped, the same
        // string the Code 128 encodes) when this PO has never had a barcode
        // number set. It is only a suggestion: a value already stored always
        // wins, and the user can type over either one. Nothing reaches the
        // database until they press Print.
        const storedBarcode = (obligationOrder.barcode_number || '').trim();
        document.getElementById('obBarcodeNumber').value =
            storedBarcode || obligationOrder.barcode_text || '';
        document.getElementById('obBarcodeHint').textContent = storedBarcode
            ? ''
            : 'Suggested from the PO number. Edit it if a different number is needed.';
        document.getElementById('obSupplierName').value = obligationOrder.supplier_name || '';
        document.getElementById('obSupplierAddress').value = obligationOrder.supplier_address || '';
        document.getElementById('obDepartment').value = obligationOrder.department || '';
        document.getElementById('obCategory').value = obligationOrder.category || '';
        // Falls back to the old placeholder text when no budget officer is on
        // file, so the form still prints something in that box.
        document.getElementById('obCityBudget').value =
            cityBudgetSignatory ? cityBudgetSignatory.name : 'CITY BUDGET';
        // Starts from the signatory's own title where one is on file, so the
        // printed block comes out exactly as it did before unless it is edited.
        const cbTitle = (cityBudgetSignatory && (cityBudgetSignatory.extra || '').trim())
            || CITY_BUDGET_TITLE_DEFAULT;
        document.getElementById('obCityBudgetPosition').value =
            cbTitle + '\n' + CITY_BUDGET_AUTHORITY_LINE;
        document.getElementById('obEndUser').value = defaultSignatory ? defaultSignatory.name : '';
        document.getElementById('obEndUserPosition').value = defaultSignatory ? (defaultSignatory.extra || '') : '';
        document.getElementById('obTotalAmount').value = fmtMoney2(total);

        openModal('poObligationModal');
    } catch (err) {
        showToast('Unable to load purchase order.', 'error');
        console.error(err);
    }
}

// Copied field-for-field from a real printed Obligation Request sample —
// not the earlier plain approximation. Barcode Number is intentionally not
// shown here: it was never one of the report's SetParameterValue calls in
// the legacy VB source, and it doesn't appear anywhere on the real sample
// either (it's still saved to the PO record, just not printed). "City
// Budget" is a person's name here (confirmed by the sample) — it's whoever
// signs the "Existence of available appropriation" certification, printed
// as the right-hand signatory alongside "City Budget Officer / Head, Budget
// Unit / Authorized Representative", which is fixed boilerplate text in the
// real form, not a value anyone types.
function buildObligationPreviewHtml(v, reportType) {
    const actionWord = reportType === 'reimbursement' ? 'Reimbursement' : 'Payment';
    // Same Code 128 block as the Purchase Order letterhead. This form has no
    // right-hand seal, so the barcode simply takes the top-right corner.
    const barcodeHtml = v.barcodePng ? `
            <div class="orq-barcode-float">
                <img class="pp-barcode" src="${v.barcodePng}" style="width:${v.barcodeWidth || 176}px" alt="Barcode of ${escapeHtml(v.poNumber || '')}">
                <div class="pp-barcode-caption">${escapeHtml(v.barcodeText || v.poNumber || '')}</div>
            </div>` : '';
    return `
    <div class="print-page" style="width:650px;">
      <div class="pp-frame">
        <div class="orq-letterhead-wrap">
        <table class="orq-letterhead">
            <tr>
                <td class="orq-seal"><img src="image/citylogo.png" alt=""></td>
                <td class="orq-letterhead-text">
                    <span class="blue">Province of Bukidnon</span><br>
                    <span class="blue bold">City of Malaybalay</span><br>
                    New City Hall, Barangay Casisang<br>
                    Malaybalay City, Bukidnon<br>
                    Tel&nbsp; (088) 813-4761-2
                </td>
            </tr>
        </table>
        ${barcodeHtml}
        </div>
        <hr class="orq-rule">

        <table class="orq-grid">
            <tr>
                <td class="orq-title-cell" colspan="2">OBLIGATION REQUEST</td>
                <td class="orq-label">No.</td>
                <td class="orq-value">&nbsp;</td>
            </tr>
            <tr>
                <td class="orq-label">Payee:</td>
                <td class="orq-value">${escapeHtml(v.supplierName) || '&nbsp;'}</td>
                <td class="orq-label">Date:</td>
                <td class="orq-value">&nbsp;</td>
            </tr>
            <tr>
                <td class="orq-label">Address:</td>
                <td class="orq-value">${escapeHtml(v.supplierAddress) || '&nbsp;'}</td>
                <td class="orq-label">Responsibility Center:</td>
                <td class="orq-value">&nbsp;</td>
            </tr>
            <tr>
                <td class="orq-label">Office:</td>
                <td class="orq-value">${escapeHtml(v.department) || '&nbsp;'}</td>
                <td class="orq-label">F/P.P.A:</td>
                <td class="orq-value">&nbsp;</td>
            </tr>
        </table>

        <table class="orq-grid">
            <tr>
                <td class="orq-particular-header" style="width:12%;">Responsible<br>Center</td>
                <td class="orq-particular-header" style="width:48%;">PARTICULAR</td>
                <td class="orq-particular-header" style="width:10%;">F.P.P</td>
                <td class="orq-particular-header" style="width:14%;">Account<br>Code</td>
                <td class="orq-particular-header" style="width:16%;">Amount</td>
            </tr>
            <tr>
                <td class="orq-particular-body" style="height:150px;">&nbsp;</td>
                <td class="orq-particular-body">To ${actionWord} of<br>${escapeHtml(v.category) || '&nbsp;'}<br><br>as per supporting papers, hereto<br>attached in the amount of . . . .</td>
                <td class="orq-particular-body">&nbsp;</td>
                <td class="orq-particular-body">&nbsp;</td>
                <td class="orq-amount-cell">PHP&nbsp;${fmtMoney2(v.totalAmount)}</td>
            </tr>
            <tr>
                <td colspan="3" class="orq-pesos-label">PESOS: <span class="orq-words">${amountInWords(v.totalAmount)}</span></td>
                <td class="orq-total-label">Total:</td>
                <td class="orq-total-value">&#8369;&nbsp; ${fmtMoney2(v.totalAmount)}</td>
            </tr>
        </table>

        <table class="orq-cert-grid">
            <tr>
                <td>
                    <span class="orq-cert-letter">A</span>
                    <div class="orq-checkbox-row"><span class="orq-checkbox"></span> Charges to appropriation/ allotment necessary lawful and under my direct supervision.</div>
                    <div class="orq-checkbox-row"><span class="orq-checkbox"></span> Supporting Document valid, proper and legal</div>
                </td>
                <td>
                    <span class="orq-cert-letter">B</span>
                    Existence of available appropriation
                </td>
            </tr>
        </table>

        <table class="orq-sign-grid">
            <tr>
                <td class="lbl">Signature:</td><td class="val">&nbsp;</td>
                <td class="lbl">Signature:</td><td class="val">&nbsp;</td>
            </tr>
            <tr>
                <td class="lbl">Printed Name:</td><td class="val"><strong>${escapeHtml(v.endUser) || '&nbsp;'}</strong></td>
                <td class="lbl">Printed Name:</td><td class="val"><strong>${escapeHtml(v.cityBudget) || '&nbsp;'}</strong></td>
            </tr>
            <tr>
                <td class="lbl">Position:</td><td class="val">${escapeHtml(v.endUserPosition) || '&nbsp;'}</td>
                <td class="lbl">Position:</td><td class="val">${escapeHtml(v.cityBudgetPosition).replace(/\r?\n/g, '<br>') || '&nbsp;'}</td>
            </tr>
            <tr>
                <td class="lbl">Date:</td><td class="val">&nbsp;</td>
                <td class="lbl">Date:</td><td class="val">&nbsp;</td>
            </tr>
        </table>
      </div>
    </div>`;
}

// Saves Barcode Number onto the PO (matches btnSave_Click/btnSave2_Click
// doing this before rendering either report), then opens the preview.
async function saveObligationBarcodeAndPrint(reportType) {
    const poNumber = document.getElementById('obligationPoNumber').value;
    const barcode = document.getElementById('obBarcodeNumber').value.trim();
    const values = {
        barcode,
        // Carried through from the order the modal was opened with, so the
        // Obligation Request letterhead can show the same Code 128 of the PO
        // number that the Purchase Order printout does.
        poNumber,
        barcodePng: (obligationOrder && obligationOrder.barcode_png) || '',
        barcodeText: (obligationOrder && obligationOrder.barcode_text) || '',
        barcodeWidth: (obligationOrder && obligationOrder.barcode_width) || 0,
        supplierName: document.getElementById('obSupplierName').value.trim(),
        supplierAddress: document.getElementById('obSupplierAddress').value.trim(),
        department: document.getElementById('obDepartment').value.trim(),
        category: document.getElementById('obCategory').value.trim(),
        cityBudget: document.getElementById('obCityBudget').value.trim(),
        cityBudgetPosition: document.getElementById('obCityBudgetPosition').value.trim(),
        endUser: document.getElementById('obEndUser').value.trim(),
        endUserPosition: document.getElementById('obEndUserPosition').value.trim(),
        totalAmount: parseFloat((document.getElementById('obTotalAmount').value || '0').replace(/,/g, '')) || 0,
    };
    try {
        const res = await fetch(apiUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ po_number: poNumber, barcode_number: barcode })
        });
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Unable to save barcode number.', 'error');

        document.getElementById('obPrintPreviewArea').innerHTML = buildObligationPreviewHtml(values, reportType);
        bootstrap.Modal.getInstance(document.getElementById('poObligationModal')).hide();
        const previewModalEl = document.getElementById('obPrintPreviewModal');
        openModal('obPrintPreviewModal');
        previewModalEl.addEventListener('shown.bs.modal', () => generateObligationPdfPreview(poNumber), { once: true });
    } catch (err) {
        showToast('Unable to save barcode number.', 'error');
        console.error(err);
    }
}

let obligationPdfBlobUrl = null;
let obligationPdfFileName = 'obligation_request.pdf';

async function generateObligationPdfPreview(poNumber) {
    const loading = document.getElementById('obPdfPreviewLoading');
    const frame = document.getElementById('obPdfPreviewFrame');
    const downloadBtn = document.getElementById('obPdfDownloadButton');
    const printBtn = document.getElementById('obPdfPrintButton');
    const openTabBtn = document.getElementById('obPdfOpenTabButton');

    frame.classList.add('d-none');
    downloadBtn.disabled = true;
    printBtn.disabled = true;
    if (openTabBtn) openTabBtn.disabled = true;
    loading.classList.remove('d-none');
    loading.innerHTML = '<div class="spinner-border text-primary" role="status"></div><div class="small mt-2">Generating PDF preview…</div>';

    try {
        const source = document.querySelector('#obPrintPreviewArea .print-page');
        if (!source) throw new Error('Print content did not render — try closing and reopening the dialog.');

        const canvas = await html2canvas(source, { scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false, imageTimeout: 15000 });
        if (!canvas.width || !canvas.height) {
            throw new Error('Rendered page came out empty (0x0) — the print content may not have laid out correctly.');
        }

        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const pdf = new jspdf.jsPDF({ unit: 'pt', format: PDF_PAGE_FORMAT });
        // Same printable inset as the paged Purchase Order output, so this
        // form is not clipped at the paper edge either.
        const pageWidth = pdf.internal.pageSize.getWidth();
        const imgWidth  = pageWidth - PDF_PAGE_MARGIN_PT * 2;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        pdf.addImage(imgData, 'JPEG', PDF_PAGE_MARGIN_PT, PDF_PAGE_MARGIN_PT, imgWidth, imgHeight);

        const blob = pdf.output('blob');
        if (obligationPdfBlobUrl) URL.revokeObjectURL(obligationPdfBlobUrl);
        obligationPdfBlobUrl = URL.createObjectURL(blob);
        obligationPdfFileName = `Obligation_${poNumber}.pdf`;

        frame.src = obligationPdfBlobUrl + PDF_VIEW_PARAMS;
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

function downloadObligationPdfPreview() {
    if (!obligationPdfBlobUrl) return;
    const a = document.createElement('a');
    a.href = obligationPdfBlobUrl;
    a.download = obligationPdfFileName;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function printObligationPdfPreview() {
    const frame = document.getElementById('obPdfPreviewFrame');
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch (err) {
        window.open(obligationPdfBlobUrl, '_blank');
    }
}

function initTable() {
    poTable = $('#poTable').DataTable({
        columns: [
            // render() only dresses up the 'display' pass — filtering, sorting
            // and any export still see the bare number, so the pill markup
            // never leaks into a search term or a sort key.
            { data: 'po_number', render: (d, type) =>
                type === 'display' && d ? `<span class="po-num-pill">${d}</span>` : d },
            { data: 'pr_number', render: (d, type) => {
                if (type !== 'display' || !d) return d;
                if (d === '—') return '<span class="pr-num-empty">—</span>';
                // A PO can cite several PRs ("20251936, 20251919, …") — one pill
                // each keeps them readable instead of one 48-char nowrap blob.
                // Split on comma/newline only: the value is already HTML-escaped,
                // so splitting on ';' too would tear entities like &amp; in half.
                const parts = d.split(/[,\n\r]+/).map(s => s.trim()).filter(Boolean);
                if (!parts.length) return '<span class="pr-num-empty">—</span>';
                return `<span class="pr-num-group">${
                    parts.map(p => `<span class="pr-num-pill">${p}</span>`).join('')
                }</span>`;
            } },
            // A blank supplier is worth flagging, not just leaving as a dash —
            // the PO can't be printed or obligated without one. Display-only:
            // sorting and searching still see the bare value.
            { data: 'supplier_name', render: (d, type) => {
                if (type !== 'display' || !d) return d;
                if (d !== '—') return d;
                return '<span class="po-missing-pill"><i class="fas fa-triangle-exclamation"></i>No supplier</span>';
            } },
            { data: 'department' },
            { data: 'category' },
            // Purpose is long free text — shown in full and left to wrap onto as
            // many lines as it needs (see .po-purpose-cell). The wrapper span is
            // display-only; sorting and searching still see the bare string.
            { data: 'purpose', render: (d, type) => {
                if (type !== 'display' || !d) return d;
                if (d === '—') return '<span class="pr-num-empty">—</span>';
                return `<span class="po-purpose-cell">${d}</span>`;
            } },
            { data: 'date_created', render: (d, type) => {
                if (type !== 'display') return d || '';
                if (!d) return '—';
                const dt = new Date(String(d).replace(' ', 'T'));
                return isNaN(dt) ? d : dt.toLocaleString('en-PH');
            } },
            { data: 'approved' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        order: [[6, 'desc']],
        pageLength: 15,
        createdRow: (row, data) => {
            $(row).find('td:eq(7)').addClass('text-center');
        }
    });
}

function initDateFilters() {
    // +1 day, not today — see the same note in resetFilters(). "From"
    // defaults to the 1st of the current month, not a rolling 30-day window.
    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    const monthStart = new Date(); monthStart.setDate(1);
    document.getElementById('filterTo').value   = tomorrow.toISOString().slice(0,10);
    document.getElementById('filterFrom').value = monthStart.toISOString().slice(0,10);
}

window.addEventListener('DOMContentLoaded', () => {
    initTable();
    initDateFilters();
    loadStats();
    loadPurchaseOrders();
    loadAutosuggest();
    // Notification links point here as purchase_orders.php?po_number=...&view=1
    // (see notifyPoUsers() calls in api/purchase_orders.php) — open that PO's
    // View modal directly instead of leaving the visitor to search for it.
    const deepLink   = new URLSearchParams(window.location.search);
    const deepLinkPo = deepLink.get('po_number');
    if (deepLinkPo) viewPurchaseOrder(deepLinkPo);
    // PO Insights links in with ?search=/?from=/?to=/?status=. Applied after
    // initDateFilters() has set its defaults so the link wins over them, and
    // only reloaded when the link actually carried a filter.
    let deepFiltered = false;
    [['search', 'searchText'], ['from', 'filterFrom'], ['to', 'filterTo'], ['status', 'filterStatus']]
        .forEach(([param, fieldId]) => {
            const value = deepLink.get(param);
            if (value === null) return;
            document.getElementById(fieldId).value = value;
            deepFiltered = true;
        });
    if (deepFiltered) loadPurchaseOrders();
    document.getElementById('btnAddPO').addEventListener('click', () => openModal('poFundModal'));
    document.getElementById('printButton').addEventListener('click', () => openPrintPreview(true));
    document.getElementById('printButtonNoFooter').addEventListener('click', () => openPrintPreview(false));
    // Restore the remembered paper / text size before anything can render, so
    // the first preview of the session already honours them.
    const paperSel = document.getElementById('pdfPaperSize');
    const textSel  = document.getElementById('pdfTextSize');
    paperSel.value = readPrintPref('po_print_paper', 'auto');
    textSel.value  = readPrintPref('po_print_text', '0');
    // A select silently keeps its old value when handed one that is not in its
    // list, which is what a stale preference from an older build looks like.
    if (!paperSel.value) paperSel.value = 'auto';
    if (!textSel.value) textSel.value = '0';
    paperSel.addEventListener('change', () => {
        writePrintPref('po_print_paper', paperSel.value);
        if (currentPreviewPo) generatePdfPreview(currentPreviewPo);
    });
    textSel.addEventListener('change', () => {
        writePrintPref('po_print_text', textSel.value);
        if (currentPreviewPo) generatePdfPreview(currentPreviewPo);
    });

    document.getElementById('pdfDownloadButton').addEventListener('click', downloadPdfPreview);

    document.getElementById('pdfPrintButton').addEventListener('click', printPdfPreview);
    document.getElementById('pdfOpenTabButton').addEventListener('click', () => { if (currentPdfBlobUrl) window.open(currentPdfBlobUrl, '_blank'); });
    document.getElementById('itemImportInput').addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) importItemsIntoGrid(file);
        e.target.value = '';
    });
    // Picking a known supplier (from the datalist) autofills its address —
    // only overwrites if the address field is still blank, so it never
    // clobbers an address someone already typed/edited by hand.
    document.getElementById('supplierName').addEventListener('input', (e) => {
        const address = supplierAddressByName[e.target.value];
        const addressField = document.getElementById('supplierAddress');
        if (address && !addressField.value.trim()) { addressField.value = address; growField(addressField); }
    });
    // Choosing a name is choosing who signs; the title follows from it and is
    // read-only, so there is nothing to overwrite and nothing to reconcile.
    document.getElementById('printSignatoryName').addEventListener('change', syncSignatoryTitle);
    // Clear the required-field warning the moment they start typing, rather
    // than leaving it red until the next press of Preview.
    document.getElementById('printInitial').addEventListener('input', (e) =>
        e.target.classList.remove('is-invalid'));
    // Native date inputs don't offer a one-click way to blank themselves —
    // clicking the little browser-drawn "x" only shows once a value is
    // already there, and on some browsers not even then. This is the
    // reliable way to print the DATE cell empty.
    document.getElementById('btnClearPrintDate').addEventListener('click', () => {
        document.getElementById('printDate').value = '';
    });
    // Same known-name -> known-title autofill, for the Obligation Request
    // modal's End User / End User Position pair.
    document.getElementById('obEndUser').addEventListener('input', (e) => {
        const title = signatoryTitleByName[e.target.value];
        if (title) document.getElementById('obEndUserPosition').value = title;
    });
    document.getElementById('obReimbursementButton').addEventListener('click', () => saveObligationBarcodeAndPrint('reimbursement'));
    document.getElementById('obPaymentButton').addEventListener('click', () => saveObligationBarcodeAndPrint('payment'));
    document.getElementById('obPdfDownloadButton').addEventListener('click', downloadObligationPdfPreview);
    document.getElementById('obPdfPrintButton').addEventListener('click', printObligationPdfPreview);
    document.getElementById('obPdfOpenTabButton').addEventListener('click', () => { if (obligationPdfBlobUrl) window.open(obligationPdfBlobUrl, '_blank'); });
    bindGrowSuggestField('purpose', 'purposeSuggestions', () => purposeSuggestionsList);
    bindGrowSuggestField('category', 'categorySuggestions', () => categorySuggestionsList);
    bindGrowSuggestField('department', 'departmentSuggestions', () => departmentSuggestionsList, true);
    // Keeps Purpose reading "<purpose text> - <Department>" — see
    // syncPurposeDepartment(). Wired on top of bindGrowSuggestField's own blur
    // handler above, not instead of it; blur fires whichever field the user
    // leaves, in whatever order they filled them in.
    document.getElementById('purpose').addEventListener('blur', syncPurposeDepartment);
    document.getElementById('department').addEventListener('blur', syncPurposeDepartment);
    bindGrowSuggestField('supplierName', 'supplierNameSuggestions', () => supplierNameSuggestionsList, true);
    bindGrowSuggestField('modeOfProcurement', 'modeOfProcurementSuggestions', () => modeOfProcurementSuggestionsList);
    bindGrowSuggestField('division', 'divisionSuggestions', () => divisionSuggestionsList, true);
    bindGrowSuggestField('prReceivedName', 'prReceivedNameSuggestions', () => prReceivedNameSuggestionsList);
    bindGrowSuggestField('placeOfDelivery', 'placeOfDeliverySuggestions', () => placeOfDeliverySuggestionsList);
    bindGrowSuggestField('workingDays', 'workingDaysSuggestions', () => workingDaysSuggestionsList);
    bindGrowSuggestField('paymentTerm', 'paymentTermSuggestions', () => paymentTermSuggestionsList);
    ['prNumber', 'section', 'supplierAddress', 'referenceNo', 'bacResolutionNo']
        .forEach(bindGrowField);
    // fillForm() populates every field and item row *before* openModal()
    // shows the modal — meaning the whole modal is still display:none at
    // that point (Bootstrap's default until '.show' is added), so every
    // scrollHeight-based auto-grow measurement taken during that fill is
    // unreliable, same underlying reason a detached or d-none-wrapped
    // element measures wrong (see addItemRow()'s own comment on this).
    // Re-running every auto-grow calculation here, once the modal is
    // actually visible, is what makes it correct regardless of what
    // happened during the (possibly-hidden) population phase.
    document.getElementById('poModal').addEventListener('shown.bs.modal', () => {
        sizePoModalColumns();
        PO_AUTOGROW_FIELD_IDS.forEach(id => growField(document.getElementById(id)));
        document.querySelectorAll('#poItemsTable .item-desc-input').forEach(growField);
    });
});
</script>
<?php include 'profile_modal.php'; ?>
<datalist id="dlRemarks"></datalist>

<?php /* Shared Document Tracking trail popup — see dts_trail.php. */ ?>
<?php include 'dts_trail.php'; ?>
<?php include 'assistant.php'; ?>
</body>
</html>
