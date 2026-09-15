<?php
require_once 'config.php';
// Same gate as Reports: this screen only reads, but what it reads is every
// PO's value, so it belongs behind the same door.
requireReportsAdmin();
$currentUser = getCurrentUser();
$activePage  = 'po_insights';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PO Insights | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
/* Charts here are plain divs, not a charting library: the shapes needed are
   bars and nothing else, and this keeps the page to one HTTP request. */
.ins-bars { display: flex; align-items: flex-end; gap: 4px; height: 150px; }
/* The months panel has no table under it, so its chart takes the space instead. */
.ins-bars.tall { height: 320px; }
.ins-bar-col { flex: 1 1 0; display: flex; flex-direction: column; justify-content: flex-end;
               align-items: center; height: 100%; min-width: 0; }
.ins-bar { width: 100%; border-radius: 4px 4px 0 0; background: var(--primary); min-height: 2px;
           transition: opacity .15s; }
.ins-bar:hover { opacity: .75; }
.ins-bar.alt { background: #93c5fd; }
.ins-bar-label { font-size: .64rem; color: var(--muted); margin-top: .35rem; white-space: nowrap;
                 overflow: hidden; text-overflow: ellipsis; max-width: 100%; }

/* Ranked lists (suppliers, offices): the bar is the row's background, so the
   name stays readable at any share and nothing needs a second column. */
.ins-rank { display: block; padding: .5rem .6rem; border-radius: 8px; text-decoration: none;
            color: inherit; position: relative; overflow: hidden; margin-bottom: .3rem; }
.ins-rank:hover { background: #f1f5f9; color: inherit; }
.ins-rank-fill { position: absolute; inset: 0 auto 0 0; background: rgba(37,99,235,.10);
                 border-radius: 8px; pointer-events: none; }
.ins-rank-row { position: relative; display: flex; align-items: baseline; gap: .5rem; }
.ins-rank-name { font-size: .82rem; font-weight: 600; color: var(--text); flex: 1;
                 white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ins-rank-val { font-size: .82rem; font-weight: 700; color: var(--navy); font-variant-numeric: tabular-nums; }
.ins-rank-sub { position: relative; font-size: .7rem; color: var(--muted); }

.ins-table { width: 100%; font-size: .82rem; margin: 0; }
.ins-table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em;
                color: var(--muted); font-weight: 700; padding: .35rem .5rem; border-bottom: 1px solid var(--border); }
.ins-table td { padding: .42rem .5rem; border-bottom: 1px solid #f1f5f9; }
.ins-table tr:last-child td { border-bottom: 0; }
.ins-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

/* The approval gap gets its own colour because it is the one finding on this
   page that asks somebody to do something. */
.ins-flag { border: 1px solid #fecaca; background: #fef2f2; border-radius: 12px; padding: 1.1rem 1.25rem; }
.ins-flag-title { font-weight: 800; color: #991b1b; display: flex; align-items: center; gap: .5rem; }
.ins-gauge { background: #fff; border: 1px solid #fecaca; border-radius: 10px; padding: .7rem .85rem; height: 100%; }
.ins-gauge-val { font-size: 1.5rem; font-weight: 800; line-height: 1; color: #b91c1c; }
.ins-gauge-lab { font-size: .74rem; color: #7f1d1d; margin-top: .25rem; }

.ins-q { display: flex; gap: .7rem; padding: .55rem 0; border-bottom: 1px solid #f1f5f9; }
.ins-q:last-child { border-bottom: 0; }
.ins-q-count { flex: 0 0 74px; text-align: right; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--navy); }
.ins-q-label { font-size: .84rem; font-weight: 600; color: var(--text); }
.ins-q-note { font-size: .74rem; color: var(--muted); }
.ins-q.ok .ins-q-count { color: #16a34a; }

.ins-period .btn { font-size: .78rem; }
.ins-scope { font-size: .72rem; color: var(--muted); font-weight: 600; }
.ins-hint { font-size: .74rem; color: var(--muted); }

/* .panel-header is a two-child flex with space-between, so the scope text and
   the eye are grouped into one right-hand child rather than added as a third —
   otherwise the scope would drift to the middle of the header. */
.panel-tools { display: flex; align-items: center; gap: .55rem; }
.ins-eye {
    border: 1px solid var(--border); background: #fff; color: var(--muted);
    border-radius: 8px; width: 30px; height: 30px; padding: 0; font-size: .8rem;
    display: inline-flex; align-items: center; justify-content: center;
    cursor: pointer; transition: color .15s, border-color .15s, background .15s;
}
.ins-eye:hover { color: var(--primary); border-color: var(--primary); }
.ins-eye:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
/* Hidden is a state worth seeing from across the room — otherwise an empty
   panel reads as "no suppliers this period", which is a different fact. */
.ins-eye.is-off { color: var(--primary); background: #eff6ff; border-color: #bfdbfe; }
.ins-masked {
    display: flex; align-items: center; gap: .5rem;
    font-size: .78rem; color: var(--muted);
    border: 1px dashed var(--border); border-radius: 10px;
    padding: 1.15rem 1rem; justify-content: center; text-align: center;
}
@media print { .sidebar, .top-navbar, .ins-period, .ins-noprint { display: none !important; } }
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
                <div class="page-title">PO Insights</div>
                <div class="page-subtitle">What the purchase order data says — read-only.</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="ins-hint" id="insFooting">Reading the live purchase order data…</div>
            <div class="btn-group ins-period" role="group">
                <button class="btn btn-outline-secondary btn-sm active" data-period="all">All time</button>
                <button class="btn btn-outline-secondary btn-sm" data-period="ytd">This year</button>
                <button class="btn btn-outline-secondary btn-sm" data-period="12m">Last 12 months</button>
            </div>
        </div>

        <div class="row g-3 mb-3" id="insTiles"></div>

        <div class="row g-3 mb-3">
            <div class="col-lg-5">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Value by year</h6>
                        <span class="ins-scope">all time</span></div>
                    <div class="panel-body"><div class="ins-bars" id="insYears"></div>
                        <div class="mt-3" id="insYearTable"></div></div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Recent months</h6>
                        <span class="ins-scope">POs raised per month</span></div>
                    <div class="panel-body"><div class="ins-bars tall" id="insMonths"></div>
                        <div class="ins-hint mt-3" id="insMonthNote"></div></div>
                </div>
            </div>
        </div>

        <div class="ins-flag mb-3" id="insApproval"></div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="panel h-100">
                    <!-- The one panel on this page that names outside parties
                         and what they were paid. The eye hides it on demand, so
                         this screen can be shown to a room, or shared, without
                         the supplier league table being on it. -->
                    <div class="panel-header"><h6 class="panel-title">Top suppliers by value</h6>
                        <div class="panel-tools">
                            <span class="ins-scope" id="insSupplierScope"></span>
                            <button type="button" class="ins-eye ins-noprint" id="insSuppliersEye"
                                    aria-controls="insSuppliers" aria-expanded="true"
                                    title="Hide supplier names and amounts">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                                <span class="visually-hidden">Hide supplier names and amounts</span>
                            </button>
                        </div></div>
                    <div class="panel-body"><div id="insSuppliers"></div>
                        <div class="ins-masked d-none" id="insSuppliersMask">
                            <i class="fas fa-eye-slash" aria-hidden="true"></i>
                            <span>Supplier names and amounts are hidden. Use the eye to show them again.</span>
                        </div></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Top offices by value</h6>
                        <span class="ins-scope" id="insOfficeScope"></span></div>
                    <div class="panel-body"><div id="insOffices"></div></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Where the money sits</h6>
                        <span class="ins-scope" id="insBandScope"></span></div>
                    <div class="panel-body" id="insBands"></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Paper trail coverage</h6>
                        <span class="ins-scope">all time</span></div>
                    <div class="panel-body" id="insCoverage"></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-7">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Same item, very different price</h6>
                        <span class="ins-scope" id="insPriceScope"></span></div>
                    <div class="panel-body" id="insPrices"></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="panel h-100">
                    <div class="panel-header"><h6 class="panel-title">Worth a look</h6>
                        <span class="ins-scope">all time</span></div>
                    <div class="panel-body" id="insQuality"></div>
                </div>
            </div>
        </div>

        <div class="panel mb-3">
            <div class="panel-header"><h6 class="panel-title">Free text vs the reference lists</h6>
                <a href="master_data.php" class="ins-hint text-decoration-none ins-noprint">Open Master Data <i class="fas fa-arrow-right ms-1"></i></a></div>
            <div class="panel-body" id="insSprawl"></div>
        </div>
    </div>
</div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
<?php include 'profile_modal.php'; ?>
<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
let insPeriod = 'all';

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
const nf = n => Number(n || 0).toLocaleString('en-US');
const peso = n => '₱' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
// Short form for tiles and bar labels: a billion pesos does not fit in a tile.
function pesoShort(n) {
    const v = Number(n || 0);
    if (Math.abs(v) >= 1e9) return '₱' + (v / 1e9).toFixed(2) + 'B';
    if (Math.abs(v) >= 1e6) return '₱' + (v / 1e6).toFixed(1) + 'M';
    if (Math.abs(v) >= 1e3) return '₱' + Math.round(v / 1e3) + 'K';
    return '₱' + v.toFixed(0);
}
const pct = (a, b) => b ? (a / b * 100).toFixed(1) + '%' : '—';
// Drill-through: the PO list reads these off the query string.
const poLink = (search, status) =>
    'purchase_orders.php?search=' + encodeURIComponent(search) + (status ? '&status=' + status : '');

function tile(icon, colour, value, label, sub) {
    return `<div class="col-sm-6 col-xl-3"><div class="stat-card">
        <div class="stat-icon ${colour}"><i class="fas ${icon}"></i></div>
        <div style="min-width:0">
            <div class="stat-value">${value}</div>
            <div class="stat-label">${label}</div>
            ${sub ? `<div class="ins-hint">${sub}</div>` : ''}
        </div></div></div>`;
}

function bars(el, series, opts) {
    const max = Math.max(...series.map(s => s.v), 1);
    el.innerHTML = series.map(s => `
        <div class="ins-bar-col" title="${escapeHtml(s.title)}">
            <div class="ins-bar ${opts && opts.alt ? 'alt' : ''}" style="height:${Math.max(2, s.v / max * 100)}%"></div>
            <div class="ins-bar-label">${escapeHtml(s.label)}</div>
        </div>`).join('');
}

function renderTiles(d) {
    const p = d.process, a = d.approval;
    document.getElementById('insTiles').innerHTML =
        tile('fa-file-invoice-dollar', 'blue', nf(d.scale.pos), 'Live purchase orders',
             nf(d.scale.history) + ' superseded rows behind them') +
        tile('fa-peso-sign', 'green', pesoShort(d.scale.value), 'Total value',
             nf(d.scale.lines) + ' item lines') +
        tile('fa-hourglass-half', 'amber', nf(a.unapproved_pos), 'No approval date',
             pesoShort(a.unapproved_val) + ' of value') +
        tile('fa-stopwatch', 'teal', (p.appr_avg == null ? '—' : p.appr_avg + ' d'),
             'PO → approved, average', p.window + ', ' + nf(p.appr_n) + ' POs measured');
}

function renderTrend(d) {
    bars(document.getElementById('insYears'), d.years.filter(y => y.y >= 2022).map(y => ({
        v: y.val, label: y.y, title: `${y.y}: ${nf(y.pos)} POs, ${peso(y.val)}`,
    })));
    document.getElementById('insYearTable').innerHTML = `<table class="ins-table">
        <thead><tr><th>Year</th><th class="ins-num">POs</th><th class="ins-num">Value</th></tr></thead>
        <tbody>${d.years.filter(y => y.y >= 2022).map(y => `<tr>
            <td>${y.y}</td><td class="ins-num">${nf(y.pos)}</td>
            <td class="ins-num">${peso(y.val)}</td></tr>`).join('')}</tbody></table>`;

    bars(document.getElementById('insMonths'), d.months.map(m => ({
        v: m.pos, label: m.m.slice(2), title: `${m.m}: ${nf(m.pos)} POs, ${peso(m.val)}`,
    })), { alt: true });
    // A month far below its neighbours is usually an operational gap rather
    // than missing data, and it is worth naming so nobody reads it as a bug.
    const busiest = d.months.reduce((a, b) => b.pos > a.pos ? b : a, d.months[0] || { pos: 0 });
    const quietest = d.months.reduce((a, b) => b.pos < a.pos ? b : a, d.months[0] || { pos: 0 });
    document.getElementById('insMonthNote').textContent = d.months.length
        ? `Busiest ${busiest.m} (${nf(busiest.pos)} POs) · quietest ${quietest.m} (${nf(quietest.pos)}).`
        : '';
}

function renderApproval(d) {
    const a = d.approval;
    const gauge = (v, l) => `<div class="col-6 col-lg-3"><div class="ins-gauge">
        <div class="ins-gauge-val">${v}</div><div class="ins-gauge-lab">${l}</div></div></div>`;
    document.getElementById('insApproval').innerHTML = `
        <div class="ins-flag-title mb-1"><i class="fas fa-triangle-exclamation"></i>
            ${nf(a.unapproved_pos)} POs carry no approval date — ${peso(a.unapproved_val)}</div>
        <p class="mb-3" style="font-size:.85rem;color:#7f1d1d;">
            It is not spread evenly: the bigger the PO, the less likely the date was ever entered.
            Anything that reports on <code>Date_Approved_PO</code> under-counts exactly the
            procurement that matters most.</p>
        <div class="row g-2 mb-3">
            ${gauge(pct(a.small_unapproved, a.small_pos), 'of POs under ₱1M')}
            ${gauge(pct(a.big_unapproved, a.big_pos), 'of POs over ₱1M')}
            ${gauge(pct(a.bid_big_unapproved, a.bid_big_pos), 'of bidding POs over ₱1M')}
            ${gauge(pesoShort(a.big_unapproved_val), 'unapproved, over ₱1M')}
        </div>
        <table class="ins-table">
            <thead><tr><th>Biggest unapproved</th><th>PO date</th><th>Supplier</th><th class="ins-num">Value</th></tr></thead>
            <tbody>${a.biggest.map(b => `<tr>
                <td><a href="purchase_orders.php?po_number=${encodeURIComponent(b.po)}&view=1">${escapeHtml(b.po)}</a></td>
                <td>${escapeHtml(b.day || '—')}</td>
                <td>${escapeHtml(b.sup)}</td>
                <td class="ins-num">${peso(b.val)}</td></tr>`).join('')}</tbody>
        </table>`;
}

function renderRanks(elId, list, total, noun) {
    const top = list[0] ? list[0].val : 1;
    document.getElementById(elId).innerHTML = list.map((r, i) => `
        <a class="ins-rank" href="${poLink(r.name)}" title="Show these in the PO list">
            <div class="ins-rank-fill" style="width:${Math.max(2, r.val / top * 100)}%"></div>
            <div class="ins-rank-row">
                <span class="ins-rank-name">${i + 1}. ${escapeHtml(r.name)}</span>
                <span class="ins-rank-val">${pesoShort(r.val)}</span>
            </div>
            <div class="ins-rank-sub">${nf(r.pos)} POs · ${pct(r.val, total)} of value${
                r.spellings > 1 ? ` · <span class="text-warning-emphasis">${r.spellings} spellings</span>` : ''}</div>
        </a>`).join('') || `<div class="ins-hint">No ${noun} in this period.</div>`;
}

function renderBands(d) {
    const total = d.period.val;
    document.getElementById('insBands').innerHTML = `<table class="ins-table">
        <thead><tr><th>PO size</th><th class="ins-num">POs</th><th class="ins-num">Value</th>
            <th class="ins-num">Share</th><th class="ins-num">No approval</th></tr></thead>
        <tbody>${d.bands.map(b => `<tr>
            <td>${escapeHtml(b.label)}</td>
            <td class="ins-num">${nf(b.pos)}</td>
            <td class="ins-num">${pesoShort(b.val)}</td>
            <td class="ins-num">${pct(b.val, total)}</td>
            <td class="ins-num">${b.pos ? pct(b.unapproved, b.pos) : '—'}</td></tr>`).join('')}</tbody></table>`;
}

function renderCoverage(d) {
    const p = d.process;
    document.getElementById('insCoverage').innerHTML = `
        <table class="ins-table mb-3">
            <thead><tr><th>Year</th><th class="ins-num">POs</th>
                <th class="ins-num">Inspected</th><th class="ins-num">Has barcode (DTS)</th></tr></thead>
            <tbody>${d.coverage.map(c => `<tr>
                <td>${c.y}</td><td class="ins-num">${nf(c.pos)}</td>
                <td class="ins-num">${pct(c.inspected, c.pos)}</td>
                <td class="ins-num">${pct(c.barcode, c.pos)}</td></tr>`).join('')}</tbody>
        </table>
        <div class="ins-hint">
            Printed ${pct(p.printed, p.total)} · obligation printed ${pct(p.obligated, p.total)} ·
            COA date filled ${pct(p.coa, p.total)} all time, but only ${nf(p.coa_recent)} of
            ${nf(p.recent_total)} POs in ${escapeHtml(p.window)}. Without a barcode a PO cannot be
            found in Document Tracking at all.
        </div>`;
}

function renderPrices(d) {
    document.getElementById('insPriceScope').textContent = d.prices_window + ', 15+ purchases, 3× or wider';
    document.getElementById('insPrices').innerHTML = d.prices.length ? `<table class="ins-table">
        <thead><tr><th>Item</th><th>Unit</th><th class="ins-num">Buys</th>
            <th class="ins-num">Lowest</th><th class="ins-num">Highest</th><th class="ins-num">Spread</th></tr></thead>
        <tbody>${d.prices.map(p => `<tr>
            <td>${escapeHtml(p.item)}</td>
            <td>${escapeHtml(p.unit || '')}</td>
            <td class="ins-num">${nf(p.buys)}</td>
            <td class="ins-num">${peso(p.lo)}</td>
            <td class="ins-num">${peso(p.hi)}</td>
            <td class="ins-num">${(p.hi / p.lo).toFixed(0)}×</td></tr>`).join('')}</tbody></table>
        <div class="ins-hint mt-2">Some of this is real — a brake pad for a motorcycle is not a
            brake pad for a dump truck. The rest is one description covering several different things,
            which is also how the item catalogue reached ${nf(d.scale.catalog)} rows.</div>`
        : '<div class="ins-hint">Nothing this volatile in the window.</div>';
}

function renderQuality(d) {
    document.getElementById('insQuality').innerHTML = d.quality.map(q => `
        <div class="ins-q ${q.count ? '' : 'ok'}">
            <div class="ins-q-count">${nf(q.count)}</div>
            <div style="min-width:0">
                <div class="ins-q-label">${escapeHtml(q.label)}</div>
                <div class="ins-q-note">${escapeHtml(q.note)}</div>
            </div>
        </div>`).join('');
}

function renderSprawl(d) {
    document.getElementById('insSprawl').innerHTML = `<table class="ins-table">
        <thead><tr><th>Field</th><th class="ins-num">Spellings in use</th>
            <th class="ins-num">Distinct after normalising</th>
            <th class="ins-num">Rows in reference list</th></tr></thead>
        <tbody>${d.sprawl.map(s => `<tr>
            <td>${escapeHtml(s.label)}</td>
            <td class="ins-num">${nf(s.spellings)}</td>
            <td class="ins-num">${nf(s.canonical)}
                ${s.collapsible ? `<span class="ins-hint">(${nf(s.collapsible)} differ only by case or punctuation)</span>` : ''}</td>
            <td class="ins-num">${nf(s.ref_rows)}</td></tr>`).join('')}</tbody></table>
        <div class="ins-hint mt-2">Every PO stores these as free text, so the same office or supplier
            can be spelled many ways. The merge tool in Master Data collapses them.</div>`;
}

async function loadInsights() {
    document.getElementById('insFooting').textContent = 'Reading the live purchase order data…';
    try {
        const res  = await fetch('api/po_insights.php?period=' + encodeURIComponent(insPeriod));
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Unable to load insights.');

        renderTiles(data);
        renderTrend(data);
        renderApproval(data);
        renderRanks('insSuppliers', data.suppliers, data.period.val, 'suppliers');
        renderRanks('insOffices', data.offices, data.period.val, 'offices');
        renderBands(data);
        renderCoverage(data);
        renderPrices(data);
        renderQuality(data);
        renderSprawl(data);

        ['insSupplierScope', 'insOfficeScope', 'insBandScope'].forEach(id =>
            document.getElementById(id).textContent = data.period.label.toLowerCase());
        document.getElementById('insFooting').textContent =
            `${nf(data.period.pos)} POs worth ${peso(data.period.val)} in scope (${data.period.label.toLowerCase()}) `
            + `· ${nf(data.scale.suppliers)} suppliers, ${nf(data.scale.offices)} offices `
            + `· read straight from the live data, nothing cached.`;
    } catch (err) {
        document.getElementById('insFooting').textContent = 'Unable to load insights.';
        console.error(err);
    }
}

// ── Hiding the supplier league table ───────────────────────────────────────
// Whether it is showing is a property of the person and the room, not of the
// data, so it is remembered per browser. localStorage can throw outright
// (private windows, site data blocked), so every access is guarded — a
// preference that fails to persist must never stop the panel working.
const INS_SUPPLIERS_HIDDEN_KEY = 'ins.suppliers.hidden';

function insSuppliersHiddenPref() {
    try { return localStorage.getItem(INS_SUPPLIERS_HIDDEN_KEY) === '1'; } catch (e) { return false; }
}

function applySuppliersVisibility(hidden) {
    // Toggling the wrapper, never the innerHTML: changing the period re-renders
    // the list underneath, and hidden must stay hidden across that.
    document.getElementById('insSuppliers').classList.toggle('d-none', hidden);
    document.getElementById('insSuppliersMask').classList.toggle('d-none', !hidden);

    const btn = document.getElementById('insSuppliersEye');
    btn.classList.toggle('is-off', hidden);
    btn.setAttribute('aria-expanded', String(!hidden));
    const label = hidden ? 'Show supplier names and amounts' : 'Hide supplier names and amounts';
    btn.title = label;
    btn.querySelector('.visually-hidden').textContent = label;
    btn.querySelector('i').className = hidden ? 'fas fa-eye-slash' : 'fas fa-eye';
}

document.addEventListener('DOMContentLoaded', () => {
    applySuppliersVisibility(insSuppliersHiddenPref());
    document.getElementById('insSuppliersEye').addEventListener('click', () => {
        const hidden = !document.getElementById('insSuppliers').classList.contains('d-none');
        applySuppliersVisibility(hidden);
        try { localStorage.setItem(INS_SUPPLIERS_HIDDEN_KEY, hidden ? '1' : '0'); } catch (e) { /* not worth surfacing */ }
    });

    document.querySelectorAll('.ins-period .btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.ins-period .btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            insPeriod = btn.dataset.period;
            loadInsights();
        });
    });
    loadInsights();
});
</script>
<?php include 'assistant.php'; ?>
</body>
</html>
