(function () {
    'use strict';
    const scanModalEl = document.getElementById('quickScanModal');
    const segModalEl = document.getElementById('segregateModal');
    if (!scanModalEl || !segModalEl) return;
    const scanModal = bootstrap.Modal.getOrCreateInstance(scanModalEl);
    const segModal = bootstrap.Modal.getOrCreateInstance(segModalEl);
    const scanForm = document.getElementById('quickScanForm');
    const segForm = document.getElementById('segregateForm');
    const sf = name => document.getElementById('quickScan' + name);
    const gf = name => document.getElementById('segregate' + name);
    const newRequestId = () => [...crypto.getRandomValues(new Uint8Array(16))].map(byte => byte.toString(16).padStart(2, '0')).join('');

    let branches = [];
    let items = [];
    let serials = [];
    let scanSaving = false;
    let scanLoaded = false;
    let unassigned = [];
    let selected = new Set();
    let segSaving = false;

    // ── Unassigned list (Quick Scan tab) ──
    window.loadUnassigned = async function () {
        const data = await inventoryRequest('api/inventory.php?action=unassigned_list');
        unassigned = data.unassigned;
        selected = new Set([...selected].filter(id => unassigned.some(row => row.id === id)));
        renderUnassigned();
    };
    function renderUnassigned() {
        document.getElementById('unassignedCount').textContent = unassigned.length;
        document.getElementById('unassignedBody').innerHTML = unassigned.length ? unassigned.map(row => `
            <tr>
                <td><input type="checkbox" class="unassignedRow" value="${row.id}" ${selected.has(row.id) ? 'checked' : ''}></td>
                <td>${escapeHtml(row.serial_number)}</td>
                <td>${escapeHtml(row.branch_name)}</td>
                <td>${AppDateTime.format(row.date_received)}</td>
                <td>${escapeHtml(row.scanned_by_name || '—')}</td>
                <td>${escapeHtml(row.remarks || '—')}</td>
            </tr>`).join('') : '<tr><td colspan="6" class="text-muted small">No scanned serials waiting to be segregated.</td></tr>';
        document.getElementById('unassignedSelectAll').checked = unassigned.length > 0 && selected.size === unassigned.length;
        document.getElementById('segregateBtn').disabled = selected.size === 0;
    }
    document.getElementById('unassignedBody').addEventListener('change', event => {
        if (!event.target.classList.contains('unassignedRow')) return;
        const id = Number(event.target.value);
        if (event.target.checked) selected.add(id); else selected.delete(id);
        renderUnassigned();
    });
    document.getElementById('unassignedSelectAll').addEventListener('change', event => {
        selected = new Set(event.target.checked ? unassigned.map(row => row.id) : []);
        renderUnassigned();
    });

    // ── Quick Scan modal ──
    async function ensureOptionsLoaded() {
        if (scanLoaded) return;
        const data = await inventoryRequest('api/inventory.php?action=entry_options');
        if (!data.branches.length) throw new Error('No active branch is available for your account.');
        branches = data.branches;
        items = data.items;
        scanLoaded = true;
    }
    function renderScanRows() {
        sf('Rows').innerHTML = serials.length ? serials.map((serial, index) => `<tr>
            <td>${index + 1}</td><td>${escapeHtml(serial)}</td>
            <td><button type="button" class="btn btn-outline-danger btn-sm" data-remove="${index}" aria-label="Remove serial ${index + 1}">Remove</button></td></tr>`).join('')
            : '<tr><td colspan="3" class="text-muted small">Scan a serial to begin.</td></tr>';
        sf('Count').textContent = `${serials.length} / 500`;
        sf('Summary').textContent = serials.length ? `${serials.length} serial(s) scanned.` : 'No serials scanned.';
        sf('Save').disabled = scanSaving || !serials.length;
    }
    function showScanError(message) {
        sf('Error').textContent = message;
        sf('Error').classList.toggle('d-none', !message);
    }
    window.openQuickScan = async function () {
        if (scanSaving) return;
        const button = document.getElementById('quickScanBtn');
        button.disabled = true;
        try {
            await ensureOptionsLoaded();
            serials = [];
            scanForm.reset();
            sf('Date').value = AppDateTime.nowInput();
            sf('Branch').innerHTML = '<option value="">Select a branch…</option>' + branches.map(branch => `<option value="${Number(branch.id)}">${escapeHtml(branch.name)}</option>`).join('');
            sf('Branch').disabled = branches.length === 1;
            if (branches.length === 1) sf('Branch').value = branches[0].id;
            showScanError('');
            renderScanRows();
            scanModal.show();
            setTimeout(() => sf('Branch').value ? sf('Serial').focus() : sf('Branch').focus(), 200);
        } catch (error) { showInventoryNotice(error.message); }
        finally { button.disabled = false; }
    };
    scanModalEl.addEventListener('hide.bs.modal', event => { if (scanSaving) event.preventDefault(); });
    sf('Serial').addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        showScanError('');
        const serial = sf('Serial').value.trim();
        if (!serial) return;
        if (serial.length > 100) { showScanError('Serial number must be 100 characters or fewer.'); return; }
        const duplicate = serials.some(existing => existing.toLocaleLowerCase() === serial.toLocaleLowerCase());
        if (duplicate) { showScanError(`Serial ${serial} is already in this batch.`); sf('Serial').select(); return; }
        if (serials.length >= 500) { showScanError('This batch has 500 serials. Save it before scanning more.'); return; }
        serials.push(serial);
        sf('Serial').value = '';
        renderScanRows();
    });
    sf('Rows').addEventListener('click', event => {
        if (scanSaving) return;
        const remove = event.target.closest('[data-remove]');
        if (!remove) return;
        serials.splice(Number(remove.dataset.remove), 1);
        renderScanRows();
    });
    scanForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (scanSaving) return;
        showScanError('');
        if (!serials.length) { showScanError('Scan at least one serial.'); return; }
        for (const name of ['Branch', 'Date']) if (!sf(name).reportValidity()) return;
        const payload = {
            action: 'quick_scan_batch', csrf_token: scanForm.dataset.csrfToken, request_id: newRequestId(),
            branch_id: sf('Branch').value, date_received: sf('Date').value, remarks: sf('Remarks').value.trim(), serials,
        };
        scanSaving = true;
        const controls = [...scanModalEl.querySelectorAll('input, select, textarea, button')];
        const states = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        sf('Save').textContent = 'Saving…';
        let result;
        try {
            result = await inventoryRequest('api/inventory.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        } catch (error) {
            showScanError(error.message);
        } finally {
            scanSaving = false;
            controls.forEach((control, index) => { control.disabled = states[index]; });
            sf('Save').textContent = 'Save Scanned Serials';
        }
        if (!result) return;
        serials = [];
        scanModal.hide();
        showInvTab('unassigned');
        showInventoryNotice(result.message, 'success');
        try { await window.loadUnassigned(); }
        catch (_) { showInventoryNotice('Serials were scanned, but the list could not refresh. Reload the page to see it.', 'warning'); }
    });

    // ── Segregate modal ──
    function renderSegItemOptions() {
        const current = gf('Item').value;
        const search = gf('ItemSearch').value.trim().toLocaleLowerCase();
        const serialized = items.filter(item => Number(item.is_serialized) === 1);
        const matches = serialized.filter(item => String(item.id) === current || `${item.name} ${item.sku || ''}`.toLocaleLowerCase().includes(search));
        gf('Item').innerHTML = '<option value="">Select an item…</option>' + matches.map(item =>
            `<option value="${Number(item.id)}" data-cost="${item.default_cost ?? 0}">${escapeHtml(item.name)}${item.sku ? ' (' + escapeHtml(item.sku) + ')' : ''}</option>`
        ).join('');
        gf('Item').value = current;
    }
    window.openSegregateModal = async function () {
        if (segSaving || !selected.size) return;
        const button = document.getElementById('segregateBtn');
        button.disabled = true;
        try {
            await ensureOptionsLoaded();
            if (!items.some(item => Number(item.is_serialized) === 1)) throw new Error('Add an active serialized item in the Item Catalog before segregating scanned serials.');
            segForm.reset();
            document.getElementById('segregateError').classList.add('d-none');
            const rows = unassigned.filter(row => selected.has(row.id));
            gf('SerialList').textContent = rows.map(row => row.serial_number).join(', ');
            gf('ItemSearch').value = '';
            renderSegItemOptions();
            gf('Cost').value = '0';
            gf('Location').value = '';
            gf('Remarks').value = '';
            segModal.show();
        } catch (error) { showInventoryNotice(error.message); }
        finally { button.disabled = false; }
    };
    segModalEl.addEventListener('hide.bs.modal', event => { if (segSaving) event.preventDefault(); });
    gf('ItemSearch').addEventListener('input', renderSegItemOptions);
    gf('Item').addEventListener('change', () => {
        const option = gf('Item').selectedOptions[0];
        if (option && option.dataset.cost !== undefined) gf('Cost').value = option.dataset.cost;
    });
    segForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (segSaving) return;
        const errorEl = document.getElementById('segregateError');
        errorEl.classList.add('d-none');
        for (const name of ['Item', 'Cost']) if (!gf(name).reportValidity()) return;
        const ids = [...selected];
        if (!ids.length) { errorEl.textContent = 'Select at least one scanned serial.'; errorEl.classList.remove('d-none'); return; }
        const payload = {
            action: 'segregate', csrf_token: segForm.dataset.csrfToken, request_id: newRequestId(),
            unassigned_ids: ids, item_id: gf('Item').value, unit_cost: gf('Cost').value,
            location: gf('Location').value.trim(), remarks: gf('Remarks').value.trim(),
        };
        segSaving = true;
        const controls = [...segModalEl.querySelectorAll('input, select, textarea, button')];
        const states = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        document.getElementById('segregateSave').textContent = 'Assigning…';
        let result;
        try {
            result = await inventoryRequest('api/inventory.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        } catch (error) {
            errorEl.textContent = error.message;
            errorEl.classList.remove('d-none');
        } finally {
            segSaving = false;
            controls.forEach((control, index) => { control.disabled = states[index]; });
            document.getElementById('segregateSave').textContent = 'Assign to Item';
        }
        if (!result) return;
        selected = new Set();
        segModal.hide();
        showInventoryNotice(result.message, 'success');
        try { await Promise.all([loadInventory(), window.loadUnassigned()]); }
        catch (_) { showInventoryNotice('Serials were assigned, but the lists could not refresh. Reload the page to see it.', 'warning'); }
    });
})();
