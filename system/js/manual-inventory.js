(function () {
    'use strict';
    const modalElement = document.getElementById('manualInventoryModal');
    if (!modalElement) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const field = name => document.getElementById('manualInv' + name);
    const form = document.getElementById('manualInventoryForm');
    let items = [];
    let rows = [];
    let editing = null;
    let saving = false;
    let loaded = false;
    let draftDirty = false;
    let requestId = '';
    const selectedItem = () => items.find(item => String(item.id) === field('Item').value);
    const newRequestId = () => [...crypto.getRandomValues(new Uint8Array(16))].map(byte => byte.toString(16).padStart(2, '0')).join('');

    function showError(message) {
        field('Error').textContent = message;
        field('Error').classList.toggle('d-none', !message);
        if (message) field('Error').scrollIntoView({ block: 'nearest' });
    }
    function renderOptions() {
        const current = field('Item').value;
        const search = field('ItemSearch').value.trim().toLocaleLowerCase();
        const matches = items.filter(item => String(item.id) === current || `${item.name} ${item.sku || ''}`.toLocaleLowerCase().includes(search));
        field('Item').innerHTML = '<option value="">Select an item…</option>' + matches.map(item =>
            `<option value="${Number(item.id)}">${escapeHtml(item.name)}${item.sku ? ' (' + escapeHtml(item.sku) + ')' : ''} — ${Number(item.is_serialized) ? 'Serialized' : 'Bulk'}</option>`
        ).join('');
        field('Item').value = current;
    }
    function pickItem(focus = true) {
        const item = selectedItem();
        const serialized = Number(item?.is_serialized) === 1;
        field('Quantity').value = '1';
        field('Quantity').readOnly = serialized;
        field('Quantity').max = serialized ? '1' : '999999999999.99';
        field('Unit').textContent = item ? '(' + item.unit + ')' : '';
        field('Cost').value = item?.default_cost ?? '0';
        field('ItemHint').textContent = !item ? 'Choose an active item from the Item Catalog.' : serialized
            ? 'Scan one serial per unit. Keep scanning to add more of this item.'
            : 'Enter the quantity received. You can mix bulk items and serialized units in one batch.';
        for (const name of ['Serial', 'Location']) {
            field(name + 'Wrap').classList.toggle('d-none', !serialized);
            field(name).disabled = !serialized;
            field(name).value = '';
        }
        field('Serial').required = serialized;
        draftDirty = false;
        if (focus && item) {
            field(serialized ? 'Serial' : 'Quantity').focus();
            if (!serialized) field('Quantity').select();
        }
    }
    function resetEntry() {
        editing = null;
        field('EntryTitle').textContent = 'Add an item to the batch';
        field('AddLine').textContent = 'Add to Batch';
        field('ResetLine').textContent = 'Clear entry';
        field('ItemSearch').value = '';
        field('Item').value = '';
        renderOptions();
        pickItem(false);
        renderRows();
    }
    function renderRows() {
        field('Rows').innerHTML = rows.length ? rows.map((row, index) => `<tr${editing === index ? ' class="table-warning"' : ''}>
            <td>${index + 1}</td><td>${escapeHtml(row.name)}</td>
            <td>${escapeHtml(row.serial_number || 'Bulk stock')}</td>
            <td>${escapeHtml(row.quantity)} ${escapeHtml(row.unit)}</td><td>${peso(row.unit_cost)}</td>
            <td>${escapeHtml(row.location || '—')}</td>
            <td class="text-nowrap"><button type="button" class="btn btn-light btn-sm" data-edit="${index}" aria-label="Edit row ${index + 1}">Edit</button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove="${index}" aria-label="Remove row ${index + 1}">Remove</button></td></tr>`).join('')
            : '<tr><td colspan="7" class="text-muted small">Scan a serial or add a bulk quantity to begin.</td></tr>';
        const serialized = rows.filter(row => row.serialized).length;
        const total = rows.reduce((sum, row) => sum + Number(row.quantity) * Number(row.unit_cost), 0);
        field('Count').textContent = `${rows.length} / 500 rows`;
        field('Summary').textContent = rows.length ? `${serialized} serial units · ${rows.length - serialized} bulk rows · ${peso(total)}` : 'No items queued.';
        field('Save').disabled = saving || !rows.length || editing !== null;
    }
    function addRow() {
        if (saving) return;
        showError('');
        const item = selectedItem();
        if (!item) { field('Item').reportValidity(); return; }
        const serialized = Number(item.is_serialized) === 1;
        for (const name of ['Item', 'Quantity', 'Cost', ...(serialized ? ['Serial', 'Location'] : [])]) {
            if (!field(name).reportValidity()) return;
        }
        const quantity = serialized ? '1' : field('Quantity').value;
        const cost = field('Cost').value;
        const serial = serialized ? field('Serial').value.trim() : '';
        if (!/^\d{1,12}(?:\.\d{1,2})?$/.test(quantity) || Number(quantity) <= 0 || !/^\d{1,13}(?:\.\d{1,2})?$/.test(cost)) {
            showError('Enter a positive quantity and a non-negative cost, with at most two decimal places.'); return;
        }
        if (serialized && !serial) { showError('Scan or enter a serial number.'); field('Serial').focus(); return; }
        const duplicate = serialized && rows.findIndex((row, index) => index !== editing && row.item_id === String(item.id) && row.serial_number.toLocaleLowerCase() === serial.toLocaleLowerCase());
        if (serialized && duplicate !== -1) {
            showError(`Serial ${serial} is already in row ${duplicate + 1} for this item.`);
            field('Serial').focus(); field('Serial').select(); return;
        }
        if (editing === null && rows.length >= 500) { showError('This batch has 500 rows. Save it before adding more.'); return; }
        const row = { item_id: String(item.id), name: item.name, unit: item.unit, serialized, quantity, unit_cost: cost, serial_number: serial, location: serialized ? field('Location').value.trim() : '' };
        const wasEditing = editing !== null;
        if (wasEditing) rows[editing] = row; else rows.push(row);
        editing = null;
        field('EntryTitle').textContent = 'Add an item to the batch';
        field('AddLine').textContent = 'Add to Batch';
        field('ResetLine').textContent = 'Clear entry';
        field('Serial').value = '';
        draftDirty = false;
        renderRows();
        field('Feedback').textContent = `${wasEditing ? 'Updated' : 'Added'}: ${row.name}${serial ? ' — ' + serial : ' — ' + quantity + ' ' + row.unit}. ${rows.length} rows ready to save.`;
        field(serialized ? 'Serial' : 'Quantity').focus();
        if (!serialized) field('Quantity').select();
    }
    function editRow(index) {
        const row = rows[index];
        if (!row || saving) return;
        editing = index;
        field('ItemSearch').value = '';
        renderOptions();
        field('Item').value = row.item_id;
        pickItem(false);
        field('Quantity').value = row.quantity;
        field('Cost').value = row.unit_cost;
        field('Serial').value = row.serial_number;
        field('Location').value = row.location;
        field('EntryTitle').textContent = 'Edit row ' + (index + 1);
        field('AddLine').textContent = 'Update Row';
        field('ResetLine').textContent = 'Cancel edit';
        showError('');
        renderRows();
        field(row.serialized ? 'Serial' : 'Quantity').focus();
    }

    window.openManualInventory = async function () {
        if (saving) return;
        if (loaded) { modal.show(); return; }
        const button = document.getElementById('manualAddBtn');
        button.disabled = true;
        document.getElementById('invNotice').classList.add('d-none');
        try {
            const data = await inventoryRequest('api/inventory.php?action=entry_options');
            if (!data.branches.length) throw new Error('No active branch is available for your account.');
            if (!data.items.length) throw new Error('Add an active item in the Item Catalog before entering inventory.');
            items = data.items;
            rows = [];
            form.reset();
            field('Date').value = AppDateTime.nowInput();
            field('Branch').innerHTML = '<option value="">Select a branch…</option>' + data.branches.map(branch => `<option value="${Number(branch.id)}">${escapeHtml(branch.name)}</option>`).join('');
            field('Branch').disabled = data.branches.length === 1;
            if (data.branches.length === 1) field('Branch').value = data.branches[0].id;
            requestId = newRequestId();
            resetEntry();
            showError('');
            field('Feedback').textContent = '';
            loaded = true;
            modal.show();
        } catch (error) { showInventoryNotice(error.message); }
        finally { button.disabled = false; }
    };
    modalElement.addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    field('Item').addEventListener('change', () => pickItem());
    field('ItemSearch').addEventListener('input', renderOptions);
    field('ItemSearch').addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        const search = field('ItemSearch').value.trim().toLocaleLowerCase();
        const match = items.find(item => (item.sku || '').toLocaleLowerCase() === search && search);
        if (match) { field('ItemSearch').value = ''; renderOptions(); field('Item').value = match.id; pickItem(); }
        else field('Item').focus();
    });
    field('Composer').addEventListener('input', event => {
        if (['manualInvSerial', 'manualInvQuantity', 'manualInvCost', 'manualInvLocation'].includes(event.target.id)) draftDirty = true;
    });
    form.addEventListener('keydown', event => {
        // Scanner Enter adds a row. It must never submit the whole arrival.
        if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
            event.preventDefault();
            if (['manualInvSerial', 'manualInvQuantity'].includes(event.target.id)) addRow();
        }
    });
    field('AddLine').addEventListener('click', addRow);
    field('ResetLine').addEventListener('click', () => { resetEntry(); showError(''); });
    field('Rows').addEventListener('click', event => {
        if (saving) return;
        const edit = event.target.closest('[data-edit]');
        const remove = event.target.closest('[data-remove]');
        if (edit) editRow(Number(edit.dataset.edit));
        if (remove) {
            const index = Number(remove.dataset.remove);
            rows.splice(index, 1);
            if (editing === index) resetEntry();
            else if (editing !== null && editing > index) { editing--; field('EntryTitle').textContent = 'Edit row ' + (editing + 1); }
            renderRows(); showError('');
            field('Feedback').textContent = `Removed row ${index + 1}. ${rows.length} rows ready to save.`;
        }
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        showError('');
        if (!rows.length) { showError('Add at least one item to the batch.'); return; }
        if (editing !== null || draftDirty || field('Serial').value.trim()) { showError('Add or update the current entry first, or use Clear entry to discard it.'); return; }
        for (const name of ['Branch', 'Date', 'Remarks']) if (!field(name).reportValidity()) return;
        if (!field('Remarks').value.trim()) { showError('Enter a reason or reference for this arrival.'); field('Remarks').focus(); return; }
        const payload = {
            action: 'manual_add_batch', csrf_token: form.dataset.csrfToken, request_id: requestId,
            branch_id: field('Branch').value, date_received: field('Date').value, remarks: field('Remarks').value.trim(),
            items: rows.map(({ item_id, quantity, unit_cost, serial_number, location }) => ({ item_id, quantity, unit_cost, serial_number, location })),
        };
        saving = true;
        const controls = [...modalElement.querySelectorAll('input, select, textarea, button')];
        const states = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        field('Save').textContent = 'Saving batch…';
        let result;
        try {
            result = await inventoryRequest('api/inventory.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        } catch (error) {
            showError(error.message);
            const row = error.message.match(/^Row (\d+):/);
            if (row) field('Rows').children[Number(row[1]) - 1]?.classList.add('table-danger');
        } finally {
            saving = false;
            controls.forEach((control, index) => { control.disabled = states[index]; });
            field('Save').textContent = 'Save All to Inventory';
        }
        if (!result) return;
        rows = [];
        loaded = false;
        resetEntry();
        modal.hide();
        document.getElementById('invSearch').value = '';
        document.getElementById('invStatus').value = '';
        showInvTab(result.serialized_count ? 'units' : 'stock');
        showInventoryNotice(result.message, 'success');
        try { await loadInventory(); }
        catch (_) { showInventoryNotice('Inventory was added, but the list could not refresh. Reload the page to see it.', 'warning'); }
    });
})();
