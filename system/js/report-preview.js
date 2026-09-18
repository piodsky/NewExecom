(function () {
    'use strict';
    const embedded = window.parent !== window && new URLSearchParams(location.search).get('embed') === '1';
    if (embedded) document.documentElement.classList.add('report-embedded');

    function showDialog(dialog, onClose) {
        // Bootstrap's document-level focus trap would otherwise pull focus out
        // of the PDF dialog when it is opened over a transaction's details.
        const previousFocus = document.activeElement;
        const detailModal = document.querySelector('.modal.show');
        const focusTrap = detailModal && window.bootstrap?.Modal.getInstance(detailModal)?._focustrap;
        focusTrap?.deactivate();
        let finished = false;
        function finish() {
            if (finished) return;
            finished = true;
            onClose();
            if (detailModal?.classList.contains('show')) focusTrap?.activate();
            if (previousFocus?.isConnected) previousFocus.focus();
        }
        function close() { dialog.close(); finish(); }
        dialog.addEventListener('close', finish, { once: true });
        dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
        document.body.append(dialog);
        dialog.showModal();
        return close;
    }

    function openDocument(href) {
        const url = new URL(href, location.href);
        const allowedPages = ['print_document.php', 'print_po_internal.php', 'print_quotation.php', 'print_issuance.php'];
        if (url.origin !== location.origin || !allowedPages.includes(url.pathname.split('/').pop())) {
            throw new Error('Invalid report address.');
        }
        const existing = document.querySelector('.report-document-dialog');
        if (existing) { existing.focus(); return; }
        url.searchParams.set('embed', '1');
        const dialog = element('dialog', undefined, 'report-dialog report-document-dialog');
        dialog.setAttribute('aria-label', 'PDF preview');
        dialog.innerHTML = `<div class="report-toolbar"><h2>PDF Preview</h2>
            <button type="button" class="btn btn-light btn-sm" data-close>Close</button></div>
            <div class="report-body"><div class="report-message report-document-loading" role="status">Loading document…</div>
            <iframe class="report-frame" title="Document PDF preview"></iframe></div>`;
        const frame = dialog.querySelector('iframe');
        const message = dialog.querySelector('.report-message');
        frame.onload = () => {
            if (!dialog.open) return;
            message.hidden = true;
            frame.hidden = false;
            const title = frame.contentDocument?.title;
            if (title) dialog.querySelector('h2').textContent = title;
        };
        const closeFromDocument = event => {
            if (event.origin === location.origin && event.source === frame.contentWindow && event.data?.type === 'report-preview-close') closeDialog();
        };
        window.addEventListener('message', closeFromDocument);
        const closeDialog = showDialog(dialog, () => {
            window.removeEventListener('message', closeFromDocument);
            frame.src = 'about:blank';
            dialog.remove();
        });
        dialog.querySelector('[data-close]').onclick = closeDialog;
        frame.src = url.href;
    }

    function element(tag, text, className) {
        const node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        if (className) node.className = className;
        return node;
    }

    function createSheet(title) {
        const sheet = element('article', undefined, 'report-sheet');
        const letterhead = document.getElementById('reportLetterhead');
        if (letterhead) sheet.append(letterhead.content.cloneNode(true));
        sheet.append(element('h1', title));
        return sheet;
    }

    function table(headers, rows) {
        const output = element('table');
        const head = output.createTHead().insertRow();
        headers.forEach(label => head.append(element('th', label)));
        if (headers[0] === '#') head.cells[0].style.width = '34px';
        const body = output.createTBody();
        rows.forEach(values => {
            const row = body.insertRow();
            values.forEach(value => row.append(element('td', value ?? '—')));
        });
        if (!rows.length) {
            const cell = body.insertRow().insertCell();
            cell.colSpan = headers.length;
            cell.textContent = 'No records.';
        }
        return output;
    }

    async function preview(sheet, title) {
        const dialog = element('dialog', undefined, 'report-dialog');
        dialog.setAttribute('aria-label', title + ' — Print Preview');
        dialog.innerHTML = `<div class="report-toolbar"><h2></h2>
            <label>Paper <select class="form-select form-select-sm" aria-label="Paper size">
            <option value="auto">Auto</option><option value="a4">A4</option><option value="long">Long / Folio</option>
            <option value="legal">Legal</option><option value="letter">Letter</option></select></label>
            <button type="button" class="btn btn-light btn-sm" data-close>Close</button></div>
            <div class="report-body"><div class="report-source-host"></div><div class="report-message" role="status"></div>
            <iframe class="report-frame" title="Report PDF preview" hidden></iframe></div>
            <div class="report-actions"><span class="report-layout" role="status"></span>
            <button type="button" class="btn btn-outline-primary btn-sm" data-download disabled>Download PDF</button>
            <button type="button" class="btn btn-primary-app btn-sm" data-print disabled>Print</button></div>`;
        dialog.querySelector('h2').textContent = title;
        const source = sheet.cloneNode(true);
        dialog.querySelector('.report-source-host').append(source);
        const frame = dialog.querySelector('iframe');
        const message = dialog.querySelector('.report-message');
        const paper = dialog.querySelector('select');
        const buttons = [...dialog.querySelectorAll('[data-download], [data-print]')];
        let blobUrl = null;
        let closed = false;
        let rendering = false;
        const closeDialog = showDialog(dialog, dispose);

        function dispose() {
            closed = true;
            if (blobUrl) URL.revokeObjectURL(blobUrl);
            // Keep the source alive until an in-flight canvas capture finishes.
            if (!rendering) dialog.remove();
            if (embedded) window.parent.postMessage({ type: 'report-preview-close' }, location.origin);
        }
        dialog.querySelector('[data-close]').onclick = closeDialog;
        dialog.querySelector('[data-download]').onclick = () => {
            const link = element('a');
            link.href = blobUrl;
            link.download = title.replace(/[^a-z0-9_-]+/gi, '_') + '.pdf';
            document.body.append(link);
            link.click();
            link.remove();
        };
        dialog.querySelector('[data-print]').onclick = () => {
            try { frame.contentWindow.focus(); frame.contentWindow.print(); }
            catch (_) { dialog.querySelector('.report-layout').textContent = 'Use the print icon in the PDF preview toolbar.'; }
        };

        async function generate() {
            rendering = true;
            paper.disabled = true;
            buttons.forEach(button => { button.disabled = true; });
            frame.hidden = true;
            message.hidden = false;
            message.textContent = 'Generating PDF preview…';
            dialog.querySelector('.report-layout').textContent = '';
            try {
                if (!window.PrintPdf) throw new Error('Report tools could not load. Refresh the page and try again.');
                await document.fonts.ready;
                await Promise.all([...source.querySelectorAll('img')].map(img => img.decode().catch(() => {})));
                const result = await PrintPdf.renderToPdf(source, {
                    paper: paper.value, breakSelector: 'tr, h2, .report-signatures', gridSelector: 'table',
                });
                if (closed) return;
                const oldUrl = blobUrl;
                blobUrl = URL.createObjectURL(result.blob);
                frame.src = blobUrl + '#navpanes=0&toolbar=1&view=FitH';
                if (oldUrl) URL.revokeObjectURL(oldUrl);
                const layout = PrintPdf.describeLayout(result.layout);
                dialog.querySelector('.report-layout').textContent = `${layout.sheetsLabel} · ${layout.paper}${layout.note ? ' · ' + layout.note : ''}`;
                message.hidden = true;
                frame.hidden = false;
                buttons.forEach(button => { button.disabled = false; });
            } catch (error) {
                message.textContent = 'Unable to generate PDF: ' + error.message;
            } finally {
                rendering = false;
                paper.disabled = false;
                if (closed) dialog.remove();
            }
        }
        paper.onchange = generate;
        await generate();
    }

    function visible(node) { return node.getClientRects().length > 0; }

    function cellText(cell) {
        const clone = cell.cloneNode(true);
        clone.querySelectorAll('button, script, style, .d-none, [hidden]').forEach(node => node.remove());
        clone.querySelectorAll('input[type="checkbox"]').forEach(input => input.replaceWith(document.createTextNode(input.checked ? 'Allowed' : 'Denied')));
        clone.querySelectorAll('br').forEach(br => br.replaceWith(document.createTextNode('\n')));
        return clone.textContent.trim().replace(/[ \t]+/g, ' ');
    }

    function moduleReport() {
        const title = document.querySelector('.page-title')?.textContent.trim() || 'Report';
        const reportTitle = /\breports?\b/i.test(title) ? title : title + ' Report';
        const sheet = createSheet(reportTitle);
        const area = document.querySelector('.content-area');
        if (!area) return;
        sheet.append(element('p', 'Generated: ' + new Date().toLocaleString()));
        sheet.append(element('p', 'Records currently loaded in the visible tables, with their displayed search and sort order.'));
        const branch = document.querySelector('.sidebar-branch .sb-name');
        if (branch) sheet.append(element('p', 'Branch: ' + branch.textContent.trim()));
        (window.moduleReportMeta || []).forEach(([label, value]) => sheet.append(element('p', `${label}: ${value}`)));

        area.querySelectorAll('.stat-card').forEach(card => {
            if (visible(card)) sheet.append(element('p', `${card.querySelector('.stat-label')?.textContent.trim() || ''}: ${card.querySelector('.stat-value')?.textContent.trim() || ''}`));
        });
        let tableCount = 0;
        area.querySelectorAll('table').forEach(source => {
            if (!visible(source) || source.closest('.modal') || !source.tHead) return;
            const headers = [...source.tHead.rows[0].cells];
            const indices = headers.map((th, i) => ({ i, text: th.textContent.trim() }))
                .filter(col => col.text && !/^actions?$/i.test(col.text)).map(col => col.i);
            if (!indices.length) return;
            let rows = [...(source.tBodies[0]?.rows || [])];
            if (window.jQuery?.fn.dataTable?.isDataTable(source)) {
                rows = jQuery(source).DataTable().rows({ search: 'applied', order: 'applied' }).nodes().toArray();
            }
            if (rows.some(row => /loading/i.test(row.textContent) && row.cells.length === 1)) {
                throw new Error('Wait for the module to finish loading, then open the report again.');
            }
            const values = rows.filter(row => row.cells.length === headers.length)
                .map(row => indices.map(i => cellText(row.cells[i])));
            const panel = source.closest('.panel');
            const caption = panel?.querySelector('.panel-title')?.textContent.trim() || title;
            sheet.append(element('h2', `${caption} (${values.length} records)`));
            panel?.querySelectorAll('.panel-body > p').forEach(note => sheet.append(element('p', note.textContent.trim())));
            sheet.append(table(indices.map(i => headers[i].textContent.trim()), values));
            tableCount++;
        });
        if (!tableCount) throw new Error('There are no visible report tables on this page.');
        if (document.getElementById('cprTxBody')) sheet.append(element('p', 'Transaction detail shows up to 500 lines. Summary totals include all matching transactions.'));
        if (document.getElementById('reportBody')) sheet.append(element('p', 'Activity detail shows up to 500 records.'));
        const auditCount = document.getElementById('alCount');
        if (auditCount) sheet.append(element('p', auditCount.textContent.trim()));
        const auditCap = document.getElementById('alCapped');
        if (auditCap && visible(auditCap)) sheet.append(element('p', auditCap.textContent.trim()));
        return preview(sheet, reportTitle);
    }

    window.ReportPreview = { element, createSheet, table, preview, moduleReport, openDocument };
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-report-modal]');
        if (!link) return;
        event.preventDefault();
        openDocument(link.href);
    });
    document.getElementById('moduleReportButton')?.addEventListener('click', async () => {
        try { await moduleReport(); } catch (error) { alert(error.message); }
    });
    document.querySelectorAll('[data-report-document]').forEach(button => {
        button.addEventListener('click', () => {
            const content = document.getElementById(button.dataset.reportDocument);
            if (!content) return;
            const sheet = element('article', undefined, 'report-sheet');
            sheet.append(content.cloneNode(true));
            preview(sheet, button.dataset.reportTitle || document.title);
        });
    });
    if (embedded) document.querySelector('[data-report-document]')?.click();
})();
