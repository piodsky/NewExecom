<?php
require_once 'config.php';
requireModule('issuance');
$currentUser  = getCurrentUser();
$isSuperAdmin = $currentUser['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Issuance | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<?php include __DIR__ . '/report_tools.php'; ?>
<style>
html, body { height: 100%; overflow: hidden; }
body { background: var(--body-bg); display: flex; flex-direction: column; }

/* ── Focused POS chrome — deliberately without the app's own sidebar/topbar,
   the same way a print page breaks from the standard shell for a single
   task done well. ── */
.pos-topbar {
    flex: 0 0 auto; background: #fff; border-bottom: 1px solid var(--border);
    padding: .75rem 1.25rem; display: flex; align-items: center; justify-content: space-between;
}
.pos-back { color: var(--muted); text-decoration: none; font-size: .85rem; font-weight: 600; display: flex; align-items: center; gap: .5rem; }
.pos-back:hover { color: var(--text); }
.pos-topbar-right { display: flex; align-items: center; gap: .9rem; }
.pos-topbar-right select { border: 1.5px solid #d1d5db; border-radius: 8px; padding: .35rem .7rem; font-size: .83rem; font-weight: 600; }
.pos-user { font-size: .82rem; color: var(--muted); }
.pos-user strong { color: var(--text); }

.pos-wrap { flex: 1 1 auto; min-height: 0; display: flex; }

/* ── Left: catalog ── */
.pos-catalog { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; padding: 1rem 1.25rem; }
.pos-search { position: relative; margin-bottom: .85rem; flex: 0 0 auto; }
.pos-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--muted); }
.pos-search input {
    width: 100%; height: 46px; border: 1.5px solid var(--border); border-radius: 10px;
    padding: 0 1rem 0 44px; font-size: .92rem; background: #fff; outline: none;
}
.pos-search input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
.pos-scan-feedback {
    flex: 0 0 auto; margin: -.55rem 0 .85rem; padding: .4rem .7rem; border-radius: 8px;
    font-size: .78rem; font-weight: 600;
}
.pos-scan-feedback.ok  { background: #dcfce7; color: #15803d; }
.pos-scan-feedback.err { background: #fee2e2; color: #b91c1c; }
.pos-tabs { display: flex; gap: .6rem; margin-bottom: .9rem; flex: 0 0 auto; }
.pos-tab {
    flex: 1; border: 1.5px solid var(--border); background: #fff; border-radius: 10px;
    padding: .65rem 1rem; font-size: .85rem; font-weight: 700; color: var(--muted);
    display: flex; align-items: center; justify-content: center; gap: .5rem; cursor: pointer;
}
.pos-tab.active { background: #eff6ff; border-color: var(--primary); color: var(--primary); }
.pos-po-picker { margin-bottom: .9rem; flex: 0 0 auto; }
.pos-po-picker select { width: 100%; border: 1.5px solid var(--border); border-radius: 10px; padding: .6rem .85rem; font-size: .85rem; }

.pos-grid-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding-bottom: 1rem; }
.pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .9rem; }
.pos-card {
    background: #fff; border: 1px solid var(--border); border-radius: 12px;
    padding: 1rem; position: relative; box-shadow: var(--card-shadow);
}
.pos-card.out { opacity: .48; }
.pos-card-info {
    position: absolute; top: .6rem; right: .6rem; width: 22px; height: 22px; border-radius: 50%;
    background: var(--body-bg); color: var(--muted); border: none; font-size: .68rem;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.pos-card-icon {
    width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 1.05rem; margin-bottom: .7rem;
}
.pos-card-name { font-size: .82rem; font-weight: 800; text-transform: uppercase; letter-spacing: .01em; line-height: 1.25; min-height: 2rem; }
.pos-card-unit { font-size: .72rem; color: var(--muted); margin-top: .1rem; }
.pos-card-stock { font-size: .78rem; font-weight: 700; margin-top: .35rem; margin-bottom: .7rem; }
.pos-card-stock.ok { color: var(--success); }
.pos-card-stock.low { color: var(--warning); }
.pos-card-stock.none { color: var(--muted); font-weight: 600; }
.pos-add-btn {
    width: 100%; background: var(--navy); color: #fff; border: none; border-radius: 8px;
    padding: .5rem; font-size: .8rem; font-weight: 700; cursor: pointer;
}
.pos-add-btn:hover { opacity: .9; }
.pos-add-btn:disabled { background: #cbd5e1; cursor: not-allowed; }
.pos-empty-catalog { text-align: center; color: var(--muted); padding: 3rem 1rem; }

/* Info popover — one reusable floating panel rather than re-binding a
   Bootstrap popover on every card each time the grid re-renders. */
.pos-popover {
    position: fixed; z-index: 500; width: 240px; background: #fff; border: 1px solid var(--border);
    border-radius: 10px; box-shadow: 0 12px 32px rgba(0,0,0,.15); padding: .85rem; font-size: .8rem;
}
.pos-popover h6 { font-size: .82rem; font-weight: 800; margin-bottom: .4rem; }
.pos-popover .pp-row { display: flex; justify-content: space-between; padding: .2rem 0; color: var(--muted); }
.pos-popover .pp-row span:last-child { color: var(--text); font-weight: 600; }
.pos-popover .pp-serials { margin-top: .5rem; max-height: 100px; overflow-y: auto; font-size: .72rem; color: var(--muted); }

/* ── Right: cart / slip ── */
.pos-cart { flex: 0 0 400px; max-width: 92vw; background: #fff; border-left: 1px solid var(--border); display: flex; flex-direction: column; }
.pos-cart-header { flex: 0 0 auto; padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: .6rem; font-weight: 800; font-size: 1rem; }
.pos-cart-header i { color: var(--primary); }
.pos-cart-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 1.1rem 1.25rem; }
.pos-cart-body .form-label { font-size: .78rem; }
.pos-cart-empty { text-align: center; color: var(--muted); padding: 3rem 1rem; font-size: .85rem; }
.pos-cart-empty i { font-size: 2.4rem; display: block; margin-bottom: .7rem; opacity: .35; }
.pos-cart-row { display: flex; gap: .7rem; align-items: flex-start; padding: .7rem 0; border-bottom: 1px solid #f1f5f9; }
.pos-cart-row .pcr-name { font-size: .83rem; font-weight: 700; }
.pos-cart-row .pcr-sub { font-size: .7rem; color: var(--muted); }
.pos-cart-row .pcr-controls { display: flex; align-items: center; gap: .4rem; margin-top: .4rem; }
.pos-cart-row .pcr-controls input[type=number] {
    width: 64px; padding: .25rem .4rem; font-size: .78rem;
    border: 1px solid var(--border); border-radius: 6px;
    -moz-appearance: textfield;
}
.pos-cart-row .pcr-controls input[type=number]::-webkit-outer-spin-button,
.pos-cart-row .pcr-controls input[type=number]::-webkit-inner-spin-button {
    -webkit-appearance: none; margin: 0;
}
.pos-cart-row .pcr-price-wrap { display: flex; align-items: center; gap: .35rem; }
.pos-cart-row .pcr-price-wrap input {
    width: 88px; padding: .25rem .4rem; font-size: .78rem;
    border: 1px solid var(--border); border-radius: 6px;
    -moz-appearance: textfield; /* Firefox: no spinner eating the box's width */
}
/* Chrome/Safari/Edge: the built-in up/down spinner otherwise claims ~20px of
   this already-narrow field, which is what was clipping the typed value. */
.pos-cart-row .pcr-price-wrap input::-webkit-outer-spin-button,
.pos-cart-row .pcr-price-wrap input::-webkit-inner-spin-button {
    -webkit-appearance: none; margin: 0;
}
.pos-cart-row .pcr-remove { margin-left: auto; background: none; border: none; color: var(--danger); font-size: .85rem; cursor: pointer; flex-shrink: 0; }
.pos-cart-row .pcr-total { font-size: .82rem; font-weight: 700; margin-left: auto; white-space: nowrap; }

.pos-cart-footer { flex: 0 0 auto; padding: 1rem 1.25rem 1.25rem; border-top: 1px solid var(--border); }
.pos-total-row { display: flex; justify-content: space-between; font-size: .85rem; color: var(--muted); margin-bottom: .3rem; }
.pos-total-row.grand { font-size: 1.15rem; font-weight: 800; color: var(--text); margin: .5rem 0 1rem; }
#posSubmitBtn { width: 100%; padding: .8rem; font-size: .92rem; }
#posSubmitBtn:disabled { background: #94a3b8; opacity: 1; transform: none; cursor: not-allowed; }

@media (max-width: 860px) {
    .pos-wrap { flex-direction: column; overflow-y: auto; }
    .pos-cart { flex: 0 0 auto; border-left: none; border-top: 1px solid var(--border); }
}
</style>
</head>
<body>

<div class="pos-topbar">
    <a href="issuances.php" class="pos-back"><i class="fas fa-arrow-left"></i> Back to Issuance</a>
    <div class="pos-topbar-right">
        <?php if ($isSuperAdmin): ?>
        <select id="posBranchSelect" onchange="onBranchChanged()">
            <option value="">Select branch…</option>
        </select>
        <?php else: ?>
        <span class="pos-user">Branch: <strong><?= e($currentUser['branch_name'] ?? '') ?></strong></span>
        <?php endif; ?>
        <span class="pos-user"><i class="fas fa-user"></i> <strong><?= e($currentUser['full_name']) ?></strong></span>
    </div>
</div>

<div class="pos-wrap">
    <div class="pos-catalog">
        <div class="pos-search">
            <i class="fas fa-barcode"></i>
            <input type="text" id="posSearch" placeholder="Search, or scan a barcode…" autocomplete="off"
                   oninput="renderGrid()" onkeydown="handleSearchKeydown(event)">
        </div>
        <div class="pos-scan-feedback d-none" id="posScanFeedback"></div>
        <div class="pos-tabs">
            <button type="button" class="pos-tab active" id="posTabBrowse" onclick="setMode('browse')"><i class="fas fa-boxes-stacked"></i> Browse Stock</button>
            <button type="button" class="pos-tab" id="posTabPo" onclick="setMode('po')"><i class="fas fa-file-invoice"></i> By PO</button>
        </div>
        <div class="pos-po-picker d-none" id="posPoPicker">
            <select id="posPoSelect" onchange="onPoPicked()"></select>
        </div>
        <div class="pos-grid-scroll">
            <div class="pos-grid" id="posGrid"></div>
        </div>
    </div>

    <div class="pos-cart">
        <div class="pos-cart-header"><i class="fas fa-cart-shopping"></i> New Issuance</div>
        <div class="pos-cart-body">
            <label class="form-label">Client / Recipient</label>
            <select class="form-select mb-3" id="posClient" onchange="onClientChanged()"><option value="">Walk-in / Retail</option></select>

            <label class="form-label">Salesman <span class="text-muted">(optional)</span></label>
            <select class="form-select mb-3" id="posSalesman"><option value="">—</option></select>

            <div id="posWalkinFields" class="mb-3">
                <label class="form-label">Buyer Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm mb-2" id="posWalkinName" placeholder="Full name" maxlength="150">
                <label class="form-label">Address <span class="text-muted">(optional)</span></label>
                <input type="text" class="form-control form-control-sm mb-2" id="posWalkinAddress" placeholder="Address" maxlength="255">
                <label class="form-label">Contact Number <span class="text-muted">(optional)</span></label>
                <input type="text" class="form-control form-control-sm" id="posWalkinContact" placeholder="Contact number" maxlength="50">
            </div>

            <div id="posCartList"><div class="pos-cart-empty"><i class="fas fa-basket-shopping"></i>Cart is empty<br>Click items on the left to add them</div></div>
        </div>
        <div class="pos-cart-footer">
            <div class="pos-total-row"><span>Items in cart</span><span id="posItemCount">0</span></div>
            <div class="pos-total-row"><span>Subtotal</span><span id="posSubtotal">₱0.00</span></div>
            <div class="pos-total-row">
                <span>Discount <span class="text-muted">(optional)</span></span>
                <span class="pcr-price-wrap">₱<input type="number" min="0" step="0.01" id="posDiscount" value="" placeholder="0.00" oninput="renderCart()"></span>
            </div>
            <div class="pos-total-row grand"><span>Total</span><span id="posGrandTotal">₱0.00</span></div>
            <button type="button" class="btn btn-primary-app" id="posSubmitBtn" onclick="submitIssuance()" disabled>
                <i class="fas fa-print me-1"></i>Issue &amp; Print
            </button>
        </div>
    </div>
</div>

<div class="pos-popover d-none" id="posPopover"></div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
const myBranchId   = <?= $currentUser['branch_id'] !== null ? (int)$currentUser['branch_id'] : 'null' ?>;

function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const peso = n => '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Category -> icon/color, purely cosmetic. Falls back to a generic box for
// anything not seeded yet (e.g. a future "Others" catalog entry).
const CATEGORY_STYLE = {
    'Monitor':                          { icon: 'fa-desktop',    bg: '#dbeafe', fg: '#1d4ed8' },
    'Laptop / All-in-One':              { icon: 'fa-laptop',      bg: '#ede9fe', fg: '#6d28d9' },
    'Processor / Motherboard':          { icon: 'fa-microchip',   bg: '#e2e8f0', fg: '#334155' },
    'SSD / HDD / External Storage':     { icon: 'fa-hard-drive',  bg: '#cffafe', fg: '#0e7490' },
    'RAM':                              { icon: 'fa-memory',      bg: '#dcfce7', fg: '#15803d' },
    'Printer':                          { icon: 'fa-print',       bg: '#ffedd5', fg: '#c2410c' },
    'CCTV':                             { icon: 'fa-video',       bg: '#fee2e2', fg: '#b91c1c' },
    'Projector / Scanner / Webcam':     { icon: 'fa-camera',      bg: '#fce7f3', fg: '#be185d' },
};
function categoryStyle(name) { return CATEGORY_STYLE[name] || { icon: 'fa-box', bg: '#f1f5f9', fg: '#475569' }; }

let mode = 'browse';           // 'browse' | 'po'
let catalog = { units: [], stock: [] };
let poItemFilter = null;       // array of item_ids, or null
let poItemRemaining = null;    // item_id => remaining ordered quantity when a PO is picked
let poClientPreset = null;     // { id, name } when a PO is picked
let cart = [];                 // { key, kind:'unit'|'stock', item_id, inventory_unit_id?, serial_number?, item_name, unit, unit_price, quantity, max }
let clientsCache = [];

function setMode(m) {
    mode = m;
    document.getElementById('posTabBrowse').classList.toggle('active', m === 'browse');
    document.getElementById('posTabPo').classList.toggle('active', m === 'po');
    document.getElementById('posPoPicker').classList.toggle('d-none', m !== 'po');
    if (m === 'browse') {
        poItemFilter = null;
        poItemRemaining = null;
        poClientPreset = null;
        document.getElementById('posClient').disabled = false;
        document.getElementById('posPoSelect').value = '';
        onClientChanged();
    }
    renderGrid();
}

// Buyer-detail fields only make sense for a walk-in sale — once a client on
// record (or a PO's preset client) is picked, their info is already on file.
// A client with a default salesman on file prefills that here too (still
// overridable — this is just a convenience, not a lock).
function onClientChanged() {
    const clientId = document.getElementById('posClient').value;
    const hasClient = !!clientId;
    document.getElementById('posWalkinFields').classList.toggle('d-none', hasClient);
    const client = clientsCache.find(c => String(c.id) === String(clientId));
    if (client && client.default_salesman_id) {
        document.getElementById('posSalesman').value = client.default_salesman_id;
    }
}

async function loadClients() {
    const res  = await fetch('api/clients.php?type=all');
    const data = await res.json();
    clientsCache = (data.success ? data.clients : []).filter(c => c.is_active == 1);
    // Previously-registered walk-in buyers show up here too (tagged, so
    // they're not mistaken for a formal/government client) — pick one
    // instead of retyping their details every visit.
    document.getElementById('posClient').innerHTML = '<option value="">Walk-in / Retail (new)</option>' +
        clientsCache.map(c => `<option value="${c.id}">${escapeHtml(c.name)}${c.client_type === 'walkin' ? ' (Walk-in)' : ''}</option>`).join('');
}

async function loadSalesmen() {
    const res  = await fetch('api/salesmen.php');
    const data = await res.json();
    const salesmen = (data.success ? data.salesmen : []).filter(s => s.is_active == 1);
    document.getElementById('posSalesman').innerHTML = '<option value="">—</option>' +
        salesmen.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
}

async function loadBranches() {
    const res  = await fetch('api/stock_transfers.php?branches=1');
    const data = await res.json();
    const branches = data.success ? data.branches : [];
    document.getElementById('posBranchSelect').innerHTML = '<option value="">Select branch…</option>' +
        branches.map(b => `<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
}

async function loadPoOptions() {
    const res  = await fetch('api/po_outgoing.php?open_for_issuance=1');
    const data = await res.json();
    const pos = data.success ? data.pos : [];
    document.getElementById('posPoSelect').innerHTML = '<option value="">Select a Purchase Order…</option>' +
        pos.map(p => `<option value="${p.po_number}">${escapeHtml(p.po_number)} — ${escapeHtml(p.client_name)}</option>`).join('');
}

async function onPoPicked() {
    const poNumber = document.getElementById('posPoSelect').value;
    if (!poNumber) { poItemFilter = null; poItemRemaining = null; poClientPreset = null; document.getElementById('posClient').disabled = false; onClientChanged(); renderGrid(); return; }
    const res  = await fetch('api/po_outgoing.php?id=' + encodeURIComponent(poNumber));
    const data = await res.json();
    if (!data.success) return alert(data.message);
    poItemFilter = data.po.items.filter(i => i.item_id).map(i => Number(i.item_id));
    poItemRemaining = {};
    data.po.items.filter(i => i.item_id).forEach(i => {
        poItemRemaining[Number(i.item_id)] = Math.max(0, Number(i.quantity || 0) - Number(i.issued_qty || 0));
    });

    await loadClients();
    const sel = document.getElementById('posClient');
    for (const opt of sel.options) { if (opt.textContent === data.po.client_name) { sel.value = opt.value; break; } }
    sel.disabled = true;
    onClientChanged();
    renderGrid();
}

function currentBranchId() { return isSuperAdmin ? (document.getElementById('posBranchSelect').value || null) : myBranchId; }

async function onBranchChanged() {
    const branchId = currentBranchId();
    cart = []; document.getElementById('posDiscount').value = ''; renderCart();
    if (!branchId) { catalog = { units: [], stock: [] }; renderGrid(); return; }
    await fetchCatalog(branchId);
}

async function fetchCatalog(branchId) {
    document.getElementById('posGrid').innerHTML = '<div class="pos-empty-catalog"><i class="fas fa-spinner fa-spin"></i></div>';
    const res  = await fetch('api/issuances.php?catalog_for=' + branchId);
    const data = await res.json();
    if (!data.success) { alert(data.message); return; }
    catalog = data;
    renderGrid();
    document.getElementById('posSearch').focus();
}

// One card per catalog item: serialized units are grouped (count = how many
// serials are still in stock and not already sitting in the cart); bulk
// items show remaining quantity_on_hand minus whatever the cart has taken.
function buildCards() {
    const cartUnitIds = new Set(cart.filter(c => c.kind === 'unit').map(c => c.inventory_unit_id));
    const cartBulkQty = {};
    const cartItemQty = {};
    cart.filter(c => c.kind === 'stock').forEach(c => { cartBulkQty[c.item_id] = (cartBulkQty[c.item_id] || 0) + c.quantity; });
    cart.forEach(c => { cartItemQty[c.item_id] = (cartItemQty[c.item_id] || 0) + c.quantity; });

    const byItem = {};
    catalog.units.forEach(u => {
        if (cartUnitIds.has(u.id)) return;
        const key = 'u' + u.item_id;
        if (!byItem[key]) byItem[key] = { item_id: u.item_id, item_name: u.item_name, sku: u.sku, unit: u.unit || 'unit', category_name: u.category_name, default_price: u.default_price, kind: 'unit', units: [] };
        byItem[key].units.push(u);
    });
    catalog.stock.forEach(s => {
        const remaining = Number(s.quantity_on_hand) - (cartBulkQty[s.item_id] || 0);
        byItem['s' + s.item_id] = { item_id: s.item_id, item_name: s.item_name, sku: s.sku, unit: s.unit, category_name: s.category_name, default_price: s.default_price, kind: 'stock', qty: remaining };
    });

    return Object.values(byItem).map(c => {
        if (!poItemRemaining) return c;
        const poLeft = Math.max(0, Number(poItemRemaining[Number(c.item_id)] || 0) - Number(cartItemQty[c.item_id] || 0));
        if (c.kind === 'unit') c.units = c.units.slice(0, Math.floor(poLeft));
        if (c.kind === 'stock') c.qty = Math.min(Number(c.qty || 0), poLeft);
        return c;
    }).sort((a, b) => a.item_name.localeCompare(b.item_name));
}

function renderGrid() {
    const grid = document.getElementById('posGrid');
    const branchId = currentBranchId();
    if (!branchId) { grid.innerHTML = '<div class="pos-empty-catalog">Select a branch to begin.</div>'; return; }
    if (mode === 'po' && !poItemFilter) { grid.innerHTML = '<div class="pos-empty-catalog">Select a Purchase Order above to load its items.</div>'; return; }

    const q = (document.getElementById('posSearch').value || '').trim().toLowerCase();
    let cards = buildCards();
    if (poItemFilter) cards = cards.filter(c => poItemFilter.includes(Number(c.item_id)));
    if (q) cards = cards.filter(c => c.item_name.toLowerCase().includes(q));

    if (!cards.length) {
        grid.innerHTML = `<div class="pos-empty-catalog">${poItemFilter ? 'None of this PO\'s items are in stock at this branch.' : 'No sellable stock at this branch.'}</div>`;
        return;
    }

    grid.innerHTML = cards.map((c, i) => {
        const style = categoryStyle(c.category_name);
        const available = c.kind === 'unit' ? c.units.length : c.qty;
        const isOut = available <= 0;
        const stockClass = isOut ? 'none' : (available <= 3 ? 'low' : 'ok');
        const stockLabel = isOut ? 'Out of stock' : `${available} ${escapeHtml(c.unit)}`;
        return `
        <div class="pos-card ${isOut ? 'out' : ''}">
            <button type="button" class="pos-card-info" onclick="showInfo(event, ${i})"><i class="fas fa-info"></i></button>
            <div class="pos-card-icon" style="background:${style.bg};color:${style.fg}"><i class="fas ${style.icon}"></i></div>
            <div class="pos-card-name">${escapeHtml(c.item_name)}</div>
            <div class="pos-card-unit">${escapeHtml(c.unit)}</div>
            <div class="pos-card-stock ${stockClass}">${stockLabel}</div>
            <button type="button" class="pos-add-btn" ${isOut ? 'disabled' : ''} onclick="cardAdd(${i})"><i class="fas fa-plus"></i> Add</button>
        </div>`;
    }).join('');

    window._posCards = cards; // scratch reference for showInfo()/cardAdd() by index
}

function cardAdd(i) {
    const c = window._posCards[i];
    if (!c) return;
    if (c.kind === 'unit') {
        const unit = c.units[0];
        if (!unit) return;
        cart.push({
            key: 'u' + unit.id, kind: 'unit', item_id: c.item_id, inventory_unit_id: unit.id,
            serial_number: unit.serial_number, item_name: c.item_name, unit: c.unit,
            unit_price: Number(unit.default_price || c.default_price || 0), quantity: 1, max: 1,
        });
    } else {
        const existing = cart.find(l => l.kind === 'stock' && l.item_id === c.item_id);
        if (existing) {
            if (existing.quantity + 1 > (existing.max)) return;
            existing.quantity += 1;
        } else {
            cart.push({
                key: 's' + c.item_id, kind: 'stock', item_id: c.item_id, item_name: c.item_name, unit: c.unit,
                unit_price: Number(c.default_price || 0), quantity: 1, max: Number(c.qty),
            });
        }
    }
    renderCart();
    renderGrid();
}

// ── Barcode scanning ──
// A USB/Bluetooth barcode scanner behaves like a keyboard: it types the
// encoded value into whatever's focused, then sends Enter. So the same
// search box doubles as the scan target — sku (bulk items) and
// serial_number (serialized units) are what's actually printed on EXECOM's
// physical labels, and matching against them needs no separate barcode
// column. Enter is the trigger rather than a debounce timer, so a human
// typing a slow manual search never gets treated as a scan.
function handleSearchKeydown(evt) {
    if (evt.key !== 'Enter') return;
    evt.preventDefault();
    const code = evt.target.value.trim();
    if (!code) return;
    tryBarcodeAdd(code);
}

function scanFeedback(kind, msg) {
    const el = document.getElementById('posScanFeedback');
    el.className = 'pos-scan-feedback ' + kind;
    el.textContent = msg;
    clearTimeout(window._scanFeedbackTimer);
    window._scanFeedbackTimer = setTimeout(() => el.classList.add('d-none'), 2200);
}

function tryBarcodeAdd(code) {
    const branchId = currentBranchId();
    if (!branchId) { scanFeedback('err', 'Select a branch first.'); return; }

    const needle = code.toLowerCase();
    let cards = buildCards();
    if (poItemFilter) cards = cards.filter(c => poItemFilter.includes(Number(c.item_id)));

    for (const c of cards) {
        if (c.kind === 'unit') {
            const unit = c.units.find(u => (u.serial_number || '').toLowerCase() === needle);
            if (unit) {
                cart.push({
                    key: 'u' + unit.id, kind: 'unit', item_id: c.item_id, inventory_unit_id: unit.id,
                    serial_number: unit.serial_number, item_name: c.item_name, unit: c.unit,
                    unit_price: Number(unit.default_price || c.default_price || 0), quantity: 1, max: 1,
                });
                scanFeedback('ok', `Added: ${c.item_name} (SN: ${unit.serial_number})`);
                finishScan();
                return;
            }
        } else if ((c.sku || '').toLowerCase() === needle) {
            const existing = cart.find(l => l.kind === 'stock' && l.item_id === c.item_id);
            if (existing) {
                if (existing.quantity + 1 > existing.max) { scanFeedback('err', `No more ${c.item_name} in stock.`); finishScan(); return; }
                existing.quantity += 1;
            } else {
                if (c.qty <= 0) { scanFeedback('err', `${c.item_name} is out of stock.`); finishScan(); return; }
                cart.push({ key: 's' + c.item_id, kind: 'stock', item_id: c.item_id, item_name: c.item_name, unit: c.unit, unit_price: Number(c.default_price || 0), quantity: 1, max: Number(c.qty) });
            }
            scanFeedback('ok', `Added: ${c.item_name}`);
            finishScan();
            return;
        }
    }
    scanFeedback('err', `No item found for "${code}".`);
    finishScan();
}

function finishScan() {
    const input = document.getElementById('posSearch');
    input.value = '';
    renderCart();
    renderGrid();
    input.focus();
}

function showInfo(evt, i) {
    evt.stopPropagation();
    const c = window._posCards[i];
    const pop = document.getElementById('posPopover');
    if (!c) return;
    let html = `<h6>${escapeHtml(c.item_name)}</h6>
        <div class="pp-row"><span>Category</span><span>${escapeHtml(c.category_name || '—')}</span></div>
        <div class="pp-row"><span>Unit</span><span>${escapeHtml(c.unit)}</span></div>
        <div class="pp-row"><span>List price</span><span>${peso(c.default_price)}</span></div>`;
    if (c.kind === 'unit') {
        html += `<div class="pp-row"><span>Available</span><span>${c.units.length}</span></div>`;
        if (c.units.length) {
            html += '<div class="pp-serials">SN: ' + c.units.map(u => escapeHtml(u.serial_number || '—')).join(', ') + '</div>';
        }
    } else {
        html += `<div class="pp-row"><span>On hand</span><span>${c.qty}</span></div>`;
    }
    pop.innerHTML = html;
    const r = evt.currentTarget.getBoundingClientRect();
    pop.style.top  = Math.min(r.bottom + 6, window.innerHeight - 220) + 'px';
    pop.style.left = Math.min(r.left - 190, window.innerWidth - 260) + 'px';
    pop.classList.remove('d-none');
}
document.addEventListener('click', (e) => {
    const pop = document.getElementById('posPopover');
    if (!pop.classList.contains('d-none') && !pop.contains(e.target) && !e.target.closest('.pos-card-info')) {
        pop.classList.add('d-none');
    }
});

function removeFromCart(key) {
    cart = cart.filter(l => l.key !== key);
    renderCart();
    renderGrid();
}

function changeCartQty(key, val) {
    const line = cart.find(l => l.key === key);
    if (!line) return;
    let qty = Math.max(1, Math.min(line.max, Math.floor(Number(val) || 1)));
    line.quantity = qty;
    renderCart();
    renderGrid();
}

function changeCartPrice(key, val) {
    const line = cart.find(l => l.key === key);
    if (!line) return;
    line.unit_price = Math.max(0, Number(val) || 0);
    renderCart();
}

function renderCart() {
    const list = document.getElementById('posCartList');
    if (!cart.length) {
        list.innerHTML = '<div class="pos-cart-empty"><i class="fas fa-basket-shopping"></i>Cart is empty<br>Click items on the left to add them</div>';
    } else {
        list.innerHTML = cart.map(l => `
            <div class="pos-cart-row">
                <div style="flex:1 1 auto;min-width:0;">
                    <div class="pcr-name">${escapeHtml(l.item_name)}</div>
                    <div class="pcr-sub">${l.kind === 'unit' ? 'SN: ' + escapeHtml(l.serial_number || '—') : escapeHtml(l.unit) + ' · max ' + l.max}</div>
                    <div class="pcr-controls">
                        ${l.kind === 'stock'
                            ? `<input type="number" min="1" max="${l.max}" value="${l.quantity}" onchange="changeCartQty('${l.key}', this.value)">`
                            : `<span class="text-muted small">Qty: 1</span>`}
                        <div class="pcr-price-wrap">₱<input type="number" min="0" step="0.01" value="${l.unit_price}" onchange="changeCartPrice('${l.key}', this.value)"></div>
                    </div>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <button type="button" class="pcr-remove" onclick="removeFromCart('${l.key}')"><i class="fas fa-trash"></i></button>
                    <div class="pcr-total mt-2">${peso(l.unit_price * l.quantity)}</div>
                </div>
            </div>`).join('');
    }

    const count = cart.reduce((n, l) => n + l.quantity, 0);
    const subtotal = cart.reduce((n, l) => n + l.unit_price * l.quantity, 0);
    const discount = currentDiscount(subtotal);
    document.getElementById('posItemCount').textContent = count;
    document.getElementById('posSubtotal').textContent = peso(subtotal);
    document.getElementById('posGrandTotal').textContent = peso(subtotal - discount);
    document.getElementById('posSubmitBtn').disabled = cart.length === 0 || !currentBranchId();
}

// Reads the optional discount field and clamps it to [0, subtotal] — also
// writes the clamped value back so what's displayed is what gets charged.
function currentDiscount(subtotal) {
    const input = document.getElementById('posDiscount');
    let discount = Math.round(Math.max(0, Number(input.value) || 0) * 100) / 100;
    if (discount > subtotal) {
        discount = subtotal;
        input.value = discount ? discount.toFixed(2) : '';
    }
    return discount;
}

async function submitIssuance() {
    const branchId = currentBranchId();
    if (!branchId) return alert('Select a branch first.');
    if (!cart.length) return alert('Cart is empty.');
    if (mode === 'po' && poItemRemaining) {
        const totals = {};
        cart.forEach(l => { totals[l.item_id] = (totals[l.item_id] || 0) + Number(l.quantity || 0); });
        const over = Object.entries(totals).find(([itemId, total]) => total > Number(poItemRemaining[Number(itemId)] || 0) + 0.0001);
        if (over) return alert('Cannot issue more than the remaining ordered quantity for this PO item.');
    }

    const clientId = document.getElementById('posClient').value || null;
    const walkinName = document.getElementById('posWalkinName').value.trim();
    if (!clientId && !walkinName) return alert('Buyer name is required for a walk-in sale.');

    const subtotal = cart.reduce((n, l) => n + l.unit_price * l.quantity, 0);
    const payload = {
        client_id: clientId,
        salesman_id: document.getElementById('posSalesman').value || null,
        po_number: mode === 'po' ? (document.getElementById('posPoSelect').value || null) : null,
        items: cart.map(l => l.kind === 'unit'
            ? { inventory_unit_id: l.inventory_unit_id, unit_price: l.unit_price }
            : { item_id: l.item_id, quantity: l.quantity, unit_price: l.unit_price }),
        discount_amount: currentDiscount(subtotal),
    };
    if (!clientId) {
        payload.walkin_name    = walkinName;
        payload.walkin_address = document.getElementById('posWalkinAddress').value.trim();
        payload.walkin_contact = document.getElementById('posWalkinContact').value.trim();
    }
    if (isSuperAdmin) payload.branch_id = branchId;

    const btn = document.getElementById('posSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Posting…';

    try {
        const res  = await fetch('api/issuances.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json();
        if (!data.success) {
            alert(data.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-print me-1"></i>Issue &amp; Print';
            return;
        }
        // The sale is posted. Clear it before opening the preview so closing
        // the modal cannot submit the same cart again.
        cart = [];
        document.getElementById('posDiscount').value = '';
        renderCart();
        btn.innerHTML = '<i class="fas fa-print me-1"></i>Issue &amp; Print';
        ReportPreview.openDocument('print_issuance.php?id=' + encodeURIComponent(data.issuance_number));
        await Promise.allSettled([fetchCatalog(branchId), loadPoOptions().then(onPoPicked)]);
    } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-print me-1"></i>Issue &amp; Print';
    }
}

(async function init() {
    await loadClients();
    await loadSalesmen();
    await loadPoOptions();
    renderCart();
    if (isSuperAdmin) {
        await loadBranches();
        renderGrid();
    } else {
        await fetchCatalog(myBranchId);
    }
})();
</script>
</body>
</html>
