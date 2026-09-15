/* ── Shared print pipeline ────────────────────────────────────────────────────
 * One tall HTML element -> html2canvas raster -> sliced at safe row boundaries
 * -> laid into a real PDF via jsPDF, with the page furniture (Date Printed,
 * Page N of M) drawn in the PDF layer rather than in the form.
 *
 * That last part is the reason any of this is structured the way it is: the
 * form is a photograph, so anything inside it can appear exactly once, on
 * whichever page it happens to land on. Only out here is the page count even
 * known.
 *
 * Lifted verbatim from the Purchase Order print in purchase_orders.php, which
 * is still carrying its own copy of these functions. The behaviour here is
 * meant to be identical; when the PO print is migrated onto this module, that
 * copy goes away. Nothing module-specific belongs in this file — the two
 * selectors that differ between forms are passed in.
 *
 * Requires vendor/js/html2canvas.min.js and vendor/js/jspdf.umd.min.js.
 * ──────────────────────────────────────────────────────────────────────────── */
(function (global) {
    'use strict';

    // Safe printable inset, in points. 18pt = 0.25in (6.35mm), comfortably
    // outside the ~4-6mm non-printable band virtually every office printer has.
    // Drawing edge-to-edge put the form's own frame inside that band, and the
    // top and right borders vanished on real hardware while still showing in
    // the on-screen preview.
    const MARGIN_PT = 18;

    // Height reserved at the foot of every sheet for the page footer, in
    // points. Taken out of the content area, so a form is scaled to fit above
    // it rather than being overprinted by it.
    const FOOTER_BAND_PT = 20;

    // The paper sizes these forms can print on, in points (1pt = 1/72in).
    //
    // A4 is first because it is what the office actually stocks, and getting
    // this wrong is not cosmetic: Letter (612pt wide) against A4 paper printed
    // with a visibly wider left margin than right, because the driver lays the
    // page down from the left and the surplus falls off the right.
    //
    // Long / Folio is 8.5 x 13in and Legal 8.5 x 14in; both are stocked here
    // and both are taller than A4, which is the point — a form that spills
    // three lines onto a second A4 sheet fits on one sheet of Long. Letter is
    // offered for completeness but is SHORTER than A4, so Auto never picks it.
    const PAPER = {
        a4:     { label: 'A4',           size: [595.28, 841.89] },
        long:   { label: 'Long / Folio', size: [612, 936] },
        legal:  { label: 'Legal',        size: [612, 1008] },
        letter: { label: 'Letter',       size: [612, 792] },
    };
    const AUTO_ORDER = ['a4', 'long', 'legal'];
    const DEFAULT_PAPER = 'a4';

    // How far Auto may shrink a form to keep it on one sheet, once every paper
    // size has been tried at full size. 0.9 takes 11px body text to a hair
    // under 10px, which still photocopies and still reads. Past that a second
    // sheet is the better answer.
    const FIT_MIN_SCALE = 0.9;

    // Browsers cap canvas dimensions (commonly ~16384px a side); a long form
    // rasterised at a flat scale:2 can blow straight past that and fail
    // outright. Scaling down proportionally for tall content trades a little
    // sharpness for not breaking at all.
    const MAX_CANVAS_PX = 14000;

    // ── Safe cuts ───────────────────────────────────────────────────────────
    // Cutting at a fixed page-height interval slices through whichever row
    // straddles the boundary, leaving an item with its top half on one page and
    // its bottom half on the next. These are the Y offsets (canvas pixels) where
    // a cut is safe: the top edge of every row and of each major block.
    function collectSafeBreaks(source, canvas, selector) {
        const pxScale = canvas.height / source.offsetHeight;
        const srcTop = source.getBoundingClientRect().top;
        const offsets = [];
        source.querySelectorAll(selector).forEach(el => {
            const top = el.getBoundingClientRect().top - srcTop;
            if (top > 0) offsets.push(Math.round(top * pxScale));
        });
        offsets.push(canvas.height);
        return [...new Set(offsets)].sort((a, b) => a - b);
    }

    // Where each bordered grid sits on the canvas, so a cut landing inside one
    // can be closed off. A grid's column rules are drawn by its own cells, so
    // slicing the raster between two rows leaves the verticals running to the
    // edge of the page with nothing capping them.
    function collectGridSpans(source, canvas, selector) {
        // Per-axis scales rather than one: html2canvas rasterises uniformly,
        // but the two ratios can round apart by a pixel on a tall page, and a
        // rule that stops a pixel short of the column it closes is visible.
        const xScale = canvas.width  / source.offsetWidth;
        const yScale = canvas.height / source.offsetHeight;
        const srcRect = source.getBoundingClientRect();
        return [...source.querySelectorAll(selector)].map(el => {
            const r = el.getBoundingClientRect();
            return {
                top:    Math.round((r.top    - srcRect.top)  * yScale),
                bottom: Math.round((r.bottom - srcRect.top)  * yScale),
                left:   Math.round((r.left   - srcRect.left) * xScale),
                right:  Math.round((r.right  - srcRect.left) * xScale),
            };
        });
    }

    // Caps a sliced grid with a horizontal rule, matching the 1px cell borders
    // scaled to canvas pixels so it lines up with the verticals it is closing.
    function drawGridEdge(ctx, spans, cutY, sliceH, edge, pxScale) {
        const lineW = Math.max(1, Math.round(pxScale));
        // Strictly inside: a cut exactly on the grid's own top or bottom edge
        // is already closed by that border and would only be doubled here.
        spans.filter(s => cutY > s.top && cutY < s.bottom).forEach(s => {
            ctx.fillStyle = '#000000';
            ctx.fillRect(s.left, edge === 'top' ? 0 : sliceH - lineW, s.right - s.left, lineW);
        });
    }

    // Where each page ends, in canvas pixels. Greedy fill snapped back to the
    // last safe break that fits, then one correction: a final page holding
    // nothing but a signature block reads as a mistake on a printed form, so a
    // very short tail pulls the previous cut earlier to give it company. Both
    // pages stay legal cuts; only the balance between them changes.
    function computePageCuts(totalPx, maxSlicePx, safeBreaks) {
        const MIN_SLICE_PX = 60;                       // never emit a sliver of a page
        const MIN_TAIL_PX  = Math.round(maxSlicePx * 0.25);
        const cuts = [];
        let y = 0;
        while (y < totalPx) {
            let end = Math.min(y + maxSlicePx, totalPx);
            if (end < totalPx) {
                // If nothing qualifies — a single block taller than a whole
                // page — fall back to the hard cut rather than looping forever.
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

    // The drawable box on a sheet, in points. Derived rather than tabulated so
    // every size automatically gets the same inset and footer band.
    function paperGeometry(sizePt) {
        const [pageW, pageH] = sizePt;
        return {
            pageW, pageH,
            contentW: pageW - MARGIN_PT * 2,
            contentH: pageH - MARGIN_PT * 2 - FOOTER_BAND_PT,
        };
    }

    // How many sheets this canvas needs on a given paper, and where they cut.
    // Pure arithmetic, so Auto can price every size without building a PDF.
    function planPaper(key, canvas, safeBreaks) {
        const g = paperGeometry(PAPER[key].size);
        // canvas.width always maps to the full content width whatever the
        // sheet — the form is drawn edge to edge of the printable box — so a
        // wider sheet means fewer canvas pixels per point and a taller one
        // means more of them fit. Both effects are in this one number.
        const maxSlicePx = Math.floor(g.contentH * (canvas.width / g.contentW));
        return {
            key, g, maxSlicePx,
            cuts: computePageCuts(canvas.height, maxSlicePx, safeBreaks),
            fitScale: 1, note: '',
        };
    }

    // Picks the sheet to print on.
    //
    // An explicit choice is honoured exactly, including one that costs extra
    // pages: if the operator says Legal it is because Legal is in the tray.
    //
    // Auto costs the form on every size and takes the fewest sheets, ties going
    // to the smallest paper — so it reaches for bigger stock only when that
    // actually saves a sheet. If nothing holds it on one sheet at full size, a
    // small shrink is tried, LARGEST sheet first: the largest sheet is by
    // definition closest to fitting and so needs the least shrinking. Squeezing
    // to 92% on Long when Legal would have taken it at 99% is the worse print,
    // and the paper changes either way at that point.
    function choosePaperLayout(canvas, safeBreaks, requested) {
        if (requested && requested !== 'auto' && PAPER[requested]) {
            return planPaper(requested, canvas, safeBreaks);
        }
        let best = null;
        for (const key of AUTO_ORDER) {
            const plan = planPaper(key, canvas, safeBreaks);
            // Strictly fewer, so an equal page count leaves the earlier
            // (smaller) paper in place rather than upgrading for nothing.
            if (!best || plan.cuts.length < best.cuts.length) best = plan;
        }
        if (best.cuts.length === 1) {
            if (best.key !== DEFAULT_PAPER) best.note = 'switched from A4 to keep it on one sheet';
            return best;
        }
        for (const key of [...AUTO_ORDER].reverse()) {
            const plan = planPaper(key, canvas, safeBreaks);
            const needed = plan.maxSlicePx / canvas.height;
            if (needed >= FIT_MIN_SCALE) {
                plan.cuts = [canvas.height];
                plan.fitScale = needed;
                // Floored, not rounded: a form squeezed to 99.6% must not
                // report itself as printed at 100%.
                const parts = key === DEFAULT_PAPER ? [] : ['switched from A4'];
                parts.push('scaled to ' + Math.floor(needed * 100) + '%');
                plan.note = parts.join(', ') + ' to keep it on one sheet';
                return plan;
            }
        }
        if (best.key !== DEFAULT_PAPER) best.note = 'switched from A4 to save a sheet';
        return best;
    }

    // When this copy was printed, formatted the way the Crystal reports did it
    // (mm/dd/yyyy and a lowercase 12-hour clock with no space: "9:19:50am").
    function printedStamp() {
        const now = new Date();
        return {
            date: now.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' }),
            time: now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' })
                     .toLowerCase().replace(/ /g, ''),
        };
    }

    // "Date Printed" left, "Page N of M" right — the legacy reports' Page
    // Footer section, drawn per sheet.
    function drawPageFooter(pdf, pageNum, pageCount, stamp) {
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();
        // Baseline inside the same printable inset as the form, so descenders
        // stay clear of the paper edge.
        const baseline = pageH - MARGIN_PT - 4;
        pdf.setTextColor(0, 0, 0);
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(7.5);
        pdf.text(`Date Printed: ${stamp.date}   ${stamp.time}`, MARGIN_PT, baseline);
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(10);
        pdf.text(`Page ${pageNum} of ${pageCount}`, pageW - MARGIN_PT, baseline, { align: 'right' });
    }

    function addCanvasToPdfPaged(pdf, canvas, layout, gridSpans, pxScale, stamp) {
        const contentW = layout.g.contentW;
        const pxPerPt = canvas.width / contentW;
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
            ctx.fillStyle = '#ffffff';   // JPEG has no alpha; unfilled area would go black
            ctx.fillRect(0, 0, slice.width, slice.height);
            ctx.drawImage(canvas, 0, y, canvas.width, sliceH, 0, 0, canvas.width, sliceH);
            drawGridEdge(ctx, gridSpans, y,   sliceH, 'top',    pxScale);
            drawGridEdge(ctx, gridSpans, end, sliceH, 'bottom', pxScale);

            if (!firstPage) pdf.addPage();
            // Scaled about the top-left of the printable box rather than
            // centred: when a form is shrunk to save a sheet, the letterhead
            // staying where the reader expects it matters more than the block
            // being optically centred.
            pdf.addImage(slice.toDataURL('image/jpeg', 0.95), 'JPEG', MARGIN_PT, MARGIN_PT,
                contentW * fitScale, (sliceH / pxPerPt) * fitScale);

            if (stamp) drawPageFooter(pdf, i + 1, cuts.length, stamp);
            firstPage = false;
            y = end;
        }
    }

    // ── Rasterising ─────────────────────────────────────────────────────────
    // Shared by both entry points below, so the canvas limits and the
    // exportability probe are enforced exactly once.
    async function rasterise(source) {
        if (!source) throw new Error('Print content did not render — try closing and reopening the print dialog.');

        const scale = Math.min(2, MAX_CANVAS_PX / Math.max(source.offsetHeight, 1));
        const canvas = await html2canvas(source, {
            scale, backgroundColor: '#ffffff', useCORS: true, logging: false, imageTimeout: 15000,
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
        return canvas;
    }

    // ── The general entry point ─────────────────────────────────────────────
    //
    // Rasterises `source` onto a fixed sheet and returns the jsPDF document,
    // letting the caller draw whatever furniture belongs OUTSIDE the form —
    // a running header, a page line, the rotated initialling strip down a
    // contract's margin. Furniture has to be drawn out here because the form
    // is one tall raster sliced into pages: anything inside it can appear
    // exactly once, on whichever page it lands on, and the page count is not
    // known until the slicing is done.
    //
    // opts:
    //   paper         key into PAPER; A4 when omitted
    //   breakSelector where a page may be cut (defaults to table rows)
    //   gridSelector  bordered grids whose sliced edge needs capping; '' for none
    //   headerBandPt  space reserved at the top of every sheet for furniture
    //   footerBandPt  space reserved at the foot; defaults to the page line's
    //   singlePage    never paginate — shrink to fit one sheet instead
    //   onPage        (pdf, page, pageCount, geo) => void, per sheet
    //
    // `geo` carries pageW/pageH/margin plus the content box, which is what a
    // furniture callback needs to place anything against a paper edge.
    async function renderToPagedPdf(source, opts) {
        opts = opts || {};
        const paperKey = (opts.paper && PAPER[opts.paper]) ? opts.paper : DEFAULT_PAPER;
        const [pageW, pageH] = PAPER[paperKey].size;
        const headerBandPt = opts.headerBandPt || 0;
        const footerBandPt = (opts.footerBandPt == null) ? FOOTER_BAND_PT : opts.footerBandPt;

        const geo = {
            pageW, pageH,
            margin: MARGIN_PT,
            headerBandPt, footerBandPt,
            contentW: pageW - MARGIN_PT * 2,
            contentH: pageH - MARGIN_PT * 2 - headerBandPt - footerBandPt,
        };

        const canvas = await rasterise(source);
        const pxPerPt = canvas.width / geo.contentW;
        const maxSlicePx = Math.floor(geo.contentH * pxPerPt);

        let cuts, fitScale = 1;
        if (opts.singlePage) {
            // One sheet, whatever it takes. A Notice of Award that runs three
            // lines long is shrunk, not paginated: the second page of a
            // one-page letter is a filing error waiting to happen.
            cuts = [canvas.height];
            fitScale = Math.min(1, maxSlicePx / canvas.height);
        } else {
            const safeBreaks = collectSafeBreaks(source, canvas, opts.breakSelector || 'tr');
            cuts = computePageCuts(canvas.height, maxSlicePx, safeBreaks);
        }

        // querySelectorAll('') throws, so an explicitly empty selector — which
        // is how a document says "no grids here" — has to short-circuit.
        const spans = opts.gridSelector ? collectGridSpans(source, canvas, opts.gridSelector) : [];
        const pxScale = canvas.height / source.offsetHeight;

        const pdf = new jspdf.jsPDF({ unit: 'pt', format: PAPER[paperKey].size });
        let y = 0;
        cuts.forEach((end, i) => {
            const sliceH = end - y;
            const slice = document.createElement('canvas');
            slice.width = canvas.width;
            slice.height = sliceH;
            const ctx = slice.getContext('2d');
            ctx.fillStyle = '#ffffff';   // JPEG has no alpha; unfilled area would go black
            ctx.fillRect(0, 0, slice.width, slice.height);
            ctx.drawImage(canvas, 0, y, canvas.width, sliceH, 0, 0, canvas.width, sliceH);
            drawGridEdge(ctx, spans, y,   sliceH, 'top',    pxScale);
            drawGridEdge(ctx, spans, end, sliceH, 'bottom', pxScale);

            if (i > 0) pdf.addPage();
            // Below the reserved header band, and scaled about the top-left of
            // the printable box rather than centred — when a form is shrunk to
            // hold one sheet, the letterhead staying where the reader expects
            // it matters more than the block being optically centred.
            pdf.addImage(slice.toDataURL('image/jpeg', 0.95), 'JPEG',
                MARGIN_PT, MARGIN_PT + headerBandPt,
                geo.contentW * fitScale, (sliceH / pxPerPt) * fitScale);

            if (typeof opts.onPage === 'function') opts.onPage(pdf, i + 1, cuts.length, geo);
            y = end;
        });
        return pdf;
    }

    // ── The per-page entry point ────────────────────────────────────────────
    //
    // renderToPagedPdf's one-giant-canvas approach breaks down once a document
    // runs long enough: the whole thing has to fit under MAX_CANVAS_PX as a
    // single raster, so a 20+ page form gets scaled down below 1:1 before it
    // ever becomes a PDF page, and prints soft no matter how sharp the source
    // HTML is. This entry point rasterises each page slice on its own instead,
    // so page count no longer trades off against sharpness — a 3-page form and
    // a 30-page one come out equally crisp.
    //
    // The safe-break scan runs on plain DOM measurements (no canvas needed for
    // that part), so page boundaries are known before anything is rasterised.
    // Each slice is then captured by temporarily moving `source` into a fixed-
    // height, overflow:hidden clipping host and shifting it up so only that
    // slice is visible — html2canvas rasterises whatever's visible in the box
    // it's given, so a bounded box is what keeps every capture small enough to
    // stay at full scale. `source` is moved back to `opts.restoreTo` (or its
    // original parent) when done, even if a capture throws.
    //
    // Same opts as renderToPagedPdf, plus:
    //   restoreTo   element to re-append `source` to afterward (defaults to
    //               source's current parent, captured before the first move)
    async function renderPerPageToPdf(source, opts) {
        opts = opts || {};
        if (!source) throw new Error('Print content did not render — try closing and reopening the print dialog.');
        const paperKey = (opts.paper && PAPER[opts.paper]) ? opts.paper : DEFAULT_PAPER;
        const [pageW, pageH] = PAPER[paperKey].size;
        const headerBandPt = opts.headerBandPt || 0;
        const footerBandPt = (opts.footerBandPt == null) ? FOOTER_BAND_PT : opts.footerBandPt;
        const geo = {
            pageW, pageH, margin: MARGIN_PT, headerBandPt, footerBandPt,
            contentW: pageW - MARGIN_PT * 2,
            contentH: pageH - MARGIN_PT * 2 - headerBandPt - footerBandPt,
        };

        const restoreTo = opts.restoreTo || source.parentNode;
        const sourceRect = source.getBoundingClientRect();
        const totalH = source.offsetHeight;
        // The scalar that maps this element's CSS pixels to PDF points — fixed
        // by its rendered width, same idea as canvas.width/contentW in the
        // shared pipeline but without needing a canvas to derive it.
        const cssPxPerPt = source.offsetWidth / geo.contentW;
        const maxSliceCss = geo.contentH * cssPxPerPt;

        const safeBreaks = [];
        source.querySelectorAll(opts.breakSelector || 'tr').forEach(el => {
            const top = el.getBoundingClientRect().top - sourceRect.top;
            if (top > 0) safeBreaks.push(Math.round(top));
        });
        safeBreaks.push(totalH);
        const cuts = computePageCuts(totalH, maxSliceCss, [...new Set(safeBreaks)].sort((a, b) => a - b));

        const clipHost = document.createElement('div');
        clipHost.style.cssText = 'position:absolute; top:0; left:-99999px; overflow:hidden; background:#fff; width:' + source.offsetWidth + 'px;';
        const shifter = document.createElement('div');
        clipHost.appendChild(shifter);
        document.body.appendChild(clipHost);

        const pdf = new jspdf.jsPDF({ unit: 'pt', format: PAPER[paperKey].size });
        try {
            let y = 0;
            for (const [i, end] of cuts.entries()) {
                const sliceH = end - y;
                clipHost.style.height = sliceH + 'px';
                shifter.style.marginTop = (-y) + 'px';
                shifter.appendChild(source);

                // Never capped by MAX_CANVAS_PX in practice — a single page
                // slice is a fraction of what a whole multi-page document
                // would be, so this stays at the full scale:2 every time.
                const scale = Math.min(2, MAX_CANVAS_PX / Math.max(sliceH, 1));
                const canvas = await html2canvas(clipHost, {
                    scale, backgroundColor: '#ffffff', useCORS: true, logging: false, imageTimeout: 15000,
                });

                if (i > 0) pdf.addPage();
                pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG',
                    MARGIN_PT, MARGIN_PT + headerBandPt, geo.contentW, sliceH / cssPxPerPt);

                if (typeof opts.onPage === 'function') opts.onPage(pdf, i + 1, cuts.length, geo);
                y = end;
            }
        } finally {
            if (restoreTo) restoreTo.appendChild(source);
            clipHost.remove();
        }
        return pdf;
    }

    // ── The auto-paper entry point ──────────────────────────────────────────
    // Rasterises `source`, PICKS the paper to suit the content, and returns a
    // finished blob plus the layout that produced it, so the caller can say
    // what it did. Use renderToPagedPdf instead when the sheet is fixed and
    // the caller wants to draw its own furniture.
    async function renderToPdf(source, opts) {
        opts = opts || {};
        const breakSelector = opts.breakSelector || 'tr';
        const gridSelector  = opts.gridSelector  || 'table';

        const canvas = await rasterise(source);
        const safeBreaks = collectSafeBreaks(source, canvas, breakSelector);
        const layout = choosePaperLayout(canvas, safeBreaks, opts.paper);
        const pdf = new jspdf.jsPDF({ unit: 'pt', format: PAPER[layout.key].size });
        addCanvasToPdfPaged(pdf, canvas, layout,
            collectGridSpans(source, canvas, gridSelector),
            canvas.height / source.offsetHeight,
            opts.stamp === false ? null : printedStamp());
        return { blob: pdf.output('blob'), layout };
    }

    // Says which sheet the PDF came out on and how many there are, plus what
    // Auto did if it did anything. Stated rather than left to be noticed: the
    // operator is about to feed paper into a printer, and "this is on Long" is
    // exactly the kind of thing that must not be discovered after the fact.
    function describeLayout(layout) {
        if (!layout) return null;
        const sheets = layout.cuts.length;
        return {
            paper: PAPER[layout.key].label,
            sheets,
            sheetsLabel: sheets + (sheets === 1 ? ' page' : ' pages'),
            note: layout.note || '',
        };
    }

    global.PrintPdf = {
        PAPER, DEFAULT_PAPER, MARGIN_PT,
        // Two entry points, and the difference matters:
        //   renderToPagedPdf  fixed sheet, caller draws its own furniture,
        //                     returns the jsPDF document
        //   renderToPdf       picks the paper to suit the content, draws the
        //                     standard page line, returns { blob, layout }
        renderToPagedPdf, renderToPdf, renderPerPageToPdf,
        describeLayout, printedStamp,
        // Exposed for callers that need to drive the steps themselves.
        rasterise, collectSafeBreaks, collectGridSpans, computePageCuts,
        choosePaperLayout, addCanvasToPdfPaged, drawGridEdge,
    };
})(window);
