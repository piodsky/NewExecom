// Global quick inventory search — press F3 (or click the floating button)
// from any page to look up what's currently available, the same "open a
// sheet, hit Find, type a few letters" reflex staff already use on the
// INVENTORY 2026 - MC.xlsx spreadsheet. Reuses api/inventory.php as-is
// (?q=&status=in_stock) — no new endpoint, no schema change.
(function () {
    const style = document.createElement('style');
    style.textContent = `
        #qisTrigger {
            position: fixed; right: 20px; bottom: 20px; z-index: 2000;
            background: linear-gradient(135deg, var(--primary, #2563eb), #1d4ed8);
            color: #fff; border: none; border-radius: 999px;
            padding: .7rem 1.1rem; font-size: .82rem; font-weight: 600;
            box-shadow: 0 4px 14px rgba(37,99,235,.35); cursor: pointer;
            display: flex; align-items: center; gap: .5rem;
        }
        #qisTrigger:hover { opacity: .92; }
        #qisOverlay {
            position: fixed; inset: 0; z-index: 2100; display: none;
            background: rgba(15,23,42,.55); align-items: flex-start; justify-content: center;
            padding: 8vh 1rem 1rem;
        }
        #qisOverlay.show { display: flex; }
        #qisBox {
            background: #fff; border-radius: 12px; width: 100%; max-width: 620px;
            box-shadow: 0 20px 60px rgba(0,0,0,.35); overflow: hidden;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        #qisInputRow { display: flex; align-items: center; gap: .6rem; padding: .9rem 1.1rem; border-bottom: 1px solid #e2e8f0; }
        #qisInputRow i { color: #64748b; }
        #qisInput { flex: 1 1 auto; border: none; outline: none; font-size: 1rem; }
        #qisHint { font-size: .7rem; color: #94a3b8; border: 1px solid #e2e8f0; border-radius: 5px; padding: .1rem .4rem; }
        #qisResults { max-height: 55vh; overflow-y: auto; }
        .qis-group-label { padding: .5rem 1.1rem .3rem; font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
        .qis-row { display: flex; justify-content: space-between; align-items: center; padding: .6rem 1.1rem; border-top: 1px solid #f1f5f9; }
        .qis-row:hover { background: #f8fafc; }
        .qis-item-name { font-weight: 600; color: #1e293b; font-size: .88rem; }
        .qis-item-sub { font-size: .75rem; color: #64748b; }
        .qis-count { font-size: .8rem; font-weight: 700; color: #166534; background: #dcfce7; border-radius: 999px; padding: .15rem .6rem; }
        .qis-empty { padding: 1.5rem; text-align: center; color: #94a3b8; font-size: .85rem; }
        .qis-serials { padding: 0 1.1rem .6rem; font-size: .72rem; color: #64748b; font-family: Consolas, monospace; }
    `;
    document.head.appendChild(style);

    const trigger = document.createElement('button');
    trigger.id = 'qisTrigger';
    trigger.type = 'button';
    trigger.innerHTML = '<i class="fas fa-magnifying-glass"></i> Quick Search <span style="opacity:.7">(F3)</span>';
    document.body.appendChild(trigger);

    const overlay = document.createElement('div');
    overlay.id = 'qisOverlay';
    overlay.innerHTML = `
        <div id="qisBox">
            <div id="qisInputRow">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="qisInput" placeholder="Search available stock — item, brand, model, serial…">
                <span id="qisHint">Esc to close</span>
            </div>
            <div id="qisResults"><div class="qis-empty">Start typing to search what's currently in stock.</div></div>
        </div>`;
    document.body.appendChild(overlay);

    const input = overlay.querySelector('#qisInput');
    const results = overlay.querySelector('#qisResults');

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function openOverlay() {
        overlay.classList.add('show');
        input.value = '';
        results.innerHTML = '<div class="qis-empty">Start typing to search what\'s currently in stock.</div>';
        setTimeout(() => input.focus(), 0);
    }
    function closeOverlay() {
        overlay.classList.remove('show');
    }

    trigger.addEventListener('click', openOverlay);
    overlay.addEventListener('mousedown', (e) => { if (e.target === overlay) closeOverlay(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F3') {
            e.preventDefault();
            overlay.classList.contains('show') ? closeOverlay() : openOverlay();
        } else if (e.key === 'Escape' && overlay.classList.contains('show')) {
            closeOverlay();
        }
    });

    let debounceTimer = null;
    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const term = input.value.trim();
        if (term.length < 2) {
            results.innerHTML = '<div class="qis-empty">Keep typing… (2+ characters)</div>';
            return;
        }
        debounceTimer = setTimeout(() => runSearch(term), 200);
    });

    async function runSearch(term) {
        results.innerHTML = '<div class="qis-empty">Searching…</div>';
        let data;
        try {
            const res = await fetch('api/inventory.php?q=' + encodeURIComponent(term) + '&status=in_stock');
            data = await res.json();
        } catch (err) {
            results.innerHTML = '<div class="qis-empty">Search failed — try again.</div>';
            return;
        }
        if (!data.success) {
            results.innerHTML = `<div class="qis-empty">${escapeHtml(data.message || 'Search failed.')}</div>`;
            return;
        }

        const units = data.units || [];
        const stock = (data.stock || []).filter(s => Number(s.quantity_on_hand) > 0);

        if (!units.length && !stock.length) {
            results.innerHTML = '<div class="qis-empty">Nothing available matches that search.</div>';
            return;
        }

        const groups = new Map();
        units.forEach(u => {
            const key = u.item_name + '|' + u.branch_name;
            if (!groups.has(key)) groups.set(key, { item_name: u.item_name, branch_name: u.branch_name, sku: u.sku, serials: [] });
            groups.get(key).serials.push(u.serial_number);
        });

        let html = '';
        if (groups.size) {
            html += '<div class="qis-group-label">Serialized — available now</div>';
            for (const g of groups.values()) {
                html += `
                    <div class="qis-row">
                        <div>
                            <div class="qis-item-name">${escapeHtml(g.item_name)}</div>
                            <div class="qis-item-sub">${escapeHtml(g.branch_name)}${g.sku ? ' &middot; ' + escapeHtml(g.sku) : ''}</div>
                        </div>
                        <span class="qis-count">${g.serials.length} unit${g.serials.length === 1 ? '' : 's'}</span>
                    </div>
                    <div class="qis-serials">${g.serials.map(escapeHtml).join(', ')}</div>`;
            }
        }
        if (stock.length) {
            html += '<div class="qis-group-label">Bulk stock — available now</div>';
            html += stock.map(s => `
                <div class="qis-row">
                    <div>
                        <div class="qis-item-name">${escapeHtml(s.item_name)}</div>
                        <div class="qis-item-sub">${escapeHtml(s.branch_name)}${s.sku ? ' &middot; ' + escapeHtml(s.sku) : ''}</div>
                    </div>
                    <span class="qis-count">${Number(s.quantity_on_hand)} ${escapeHtml(s.unit || '')}</span>
                </div>`).join('');
        }
        results.innerHTML = html;
    }
})();
