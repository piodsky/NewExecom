(async function () {
    'use strict';
    const { element, createSheet, table, preview } = window.ReportPreview;
    const config = document.body.dataset;
    const status = document.getElementById('documentStatus');
    const button = document.getElementById('documentPreviewButton');
    const quantity = value => Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = value => value === null || value === undefined ? '—' : quantity(value);
    const progress = (done, total) => `${quantity(done)} / ${quantity(total)}`;
    const date = value => value ? AppDateTime.format(value) : '—';
    const label = value => String(value ?? '—').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    const definitions = {
        purchase_requisition: {
            fields: [['department', 'Department'], ['purpose', 'Purpose'], ['needed_date', 'Needed', date], ['requested_by_name', 'Requested By'], ['approved_by_name', 'Approved By'], ['approved_at', 'Approved', date], ['remarks', 'Remarks']],
            signatures: [['Requested By', 'requested_by_name'], ['Approved By', 'approved_by_name']],
        },
        internal_rfq: {
            fields: [['pr_number', 'PR Number'], ['sent_date', 'Sent', date], ['created_by_name', 'Prepared By']],
            signatures: [['Prepared By', 'created_by_name'], ['Reviewed By', null]],
        },
        outgoing_rfq: {
            fields: [['client_name', 'Client'], ['client_address', 'Address'], ['received_date', 'Received', date], ['due_date', 'Due', date], ['created_by_name', 'Recorded By']],
            signatures: [['Recorded By', 'created_by_name'], ['Reviewed By', null]],
        },
        po_outgoing: {
            fields: [['client_name', 'Client'], ['client_address', 'Address'], ['outgoing_quotation_number', 'Quotation Number'], ['end_user', 'End User'], ['place_of_delivery', 'Place of Delivery'], ['delivery_term', 'Delivery Terms'], ['payment_term', 'Payment Terms'], ['created_by_name', 'Prepared By']],
            signatures: [['Prepared By', 'created_by_name'], ['Received By', null]],
        },
        receiving_reports: {
            fields: [['po_number', 'PO Number'], ['supplier_name', 'Supplier'], ['supplier_address', 'Supplier Address'], ['date_received', 'Received', date], ['received_by_name', 'Received By'], ['condition_notes', 'Condition / Notes']],
            signatures: [['Received By', 'received_by_name'], ['Checked By', null]],
        },
        delivery_reports: {
            fields: [['po_number', 'PO Number'], ['client_name', 'Client'], ['client_address', 'Client Address'], ['place_of_delivery', 'Place of Delivery'], ['date_delivered', 'Delivered', date], ['forwarder', 'Forwarder'], ['delivered_by_name', 'Delivered By'], ['received_by', 'Received By'], ['date_accepted', 'Accepted', date]],
            signatures: [['Delivered By', 'delivered_by_name'], ['Received By', 'received_by']],
        },
        stock_transfer: {
            fields: [['from_branch_name', 'From Branch'], ['to_branch_name', 'To Branch'], ['date_created', 'Transfer Date', date], ['date_received', 'Received', date], ['requested_by_name', 'Requested By'], ['approved_by_name', 'Approved By']],
            signatures: [['Requested By', 'requested_by_name'], ['Approved By', 'approved_by_name'], ['Received By', null]],
        },
    };
    try {
        const response = await fetch(config.api + '?' + new URLSearchParams({ id: config.recordId }), { cache: 'no-store' });
        if (!response.ok || response.redirected) throw new Error('Unable to load this document. Return to the module and sign in again if needed.');
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Document not found.');
        const record = data[config.key];
        if (!record || !Array.isArray(record.items)) throw new Error('Document details are unavailable.');
        const definition = definitions[config.module];
        const sheet = createSheet(config.title);
        const meta = element('div', undefined, 'report-meta');
        const fields = [[null, 'Document Number', () => config.recordId], ['branch_name', 'Branch'], ['created_at', 'Created', date], ['status', 'Status', label], ...definition.fields];
        fields.forEach(([field, name, format]) => {
            if (field && (record[field] === undefined || record[field] === null || record[field] === '')) return;
            const line = element('div');
            line.append(element('strong', name + ': '), document.createTextNode(format ? format(record[field]) : String(record[field])));
            meta.append(line);
        });
        sheet.append(meta);
        let headers = ['#', 'Description', 'Quantity', 'Unit'];
        let rows = record.items.map((item, i) => [i + 1, item.description || item.item_name || '—', quantity(item.quantity), item.unit || '—']);
        if (config.module === 'po_outgoing') {
            headers.push('Issued', 'Delivered to Client', 'Unit Price (PHP)', 'Amount (PHP)');
            rows = rows.map((row, i) => {
                const item = record.items[i];
                return [...row, progress(item.issued_qty, item.quantity), progress(item.delivered_qty, item.quantity), money(item.unit_price), money(item.total_price)];
            });
        } else if (config.module === 'receiving_reports') {
            headers = ['#', 'Item', 'Serial Number', 'Quantity', 'Unit', 'Unit Cost (PHP)', 'Amount (PHP)'];
            rows = record.items.map((item, i) => [i + 1, item.item_name, item.serial_number || '—', quantity(item.quantity), item.unit || '—', money(item.unit_cost), money(Number(item.quantity) * Number(item.unit_cost))]);
        } else if (config.module === 'stock_transfer') {
            headers = ['#', 'Item', 'Serial Number', 'Quantity'];
            rows = record.items.map((item, i) => [i + 1, item.description_snapshot, item.serial_snapshot || '—', quantity(item.quantity ?? (item.inventory_unit_id ? 1 : 0))]);
        }
        sheet.append(table(headers, rows));
        if (config.module === 'po_outgoing') sheet.append(element('p', 'TOTAL (PHP): ' + money(record.total_amount), 'report-total'));
        if (config.module === 'receiving_reports') {
            const total = record.items.reduce((sum, item) => sum + Number(item.quantity) * Number(item.unit_cost), 0);
            sheet.append(element('p', 'TOTAL RECEIVED COST (PHP): ' + money(total), 'report-total'));
        }
        if (config.module === 'internal_rfq') {
            const quotes = record.items.flatMap(item => (item.quotes || []).map(quote => [
                item.description || item.item_name || '—', quote.supplier_name, money(quote.quoted_price),
                quote.lead_time_days ?? '—', Number(quote.is_selected) ? 'Yes' : 'No',
            ]));
            if (quotes.length) {
                sheet.append(element('h2', 'Supplier Quotation Comparison'));
                sheet.append(table(['Item', 'Supplier', 'Quoted Unit Price (PHP)', 'Lead Time (Days)', 'Selected'], quotes));
            }
        }
        const signatures = element('div', undefined, 'report-signatures');
        definition.signatures.forEach(([name, field]) => {
            const block = element('div');
            block.append(element('div', record[field] || 'Printed Name & Signature'), element('strong', name));
            signatures.append(block);
        });
        sheet.append(signatures);
        document.body.append(sheet);
        status.textContent = '';
        button.disabled = false;
        button.onclick = () => preview(sheet, config.title + ' ' + config.recordId);
        await preview(sheet, config.title + ' ' + config.recordId);
    } catch (error) {
        status.textContent = error.message;
        status.classList.add('text-danger');
    }
})();
