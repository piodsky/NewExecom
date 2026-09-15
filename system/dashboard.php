<?php
require_once 'config.php';
requireAuth();
$currentUser = getCurrentUser();
$activePage  = 'dashboard';
// The insight strip below carries whole-of-city spend figures, so it is shown
// to the same tier that can open PO Insights itself. The dashboard is the
// landing page for EVERY account, which is exactly why the rest of Insights
// does not live here.
$canInsights = in_array($currentUser['role'], ['admin', 'super_admin'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
/* ── Dashboard-only styles ──
   Charts are hand-rolled inline SVG rather than a charting library: nothing
   in vendor/ provides one and this repo is deliberately CDN-free, so pulling
   Chart.js would either add a build step or break the offline rule. Two
   simple charts are cheaper than either. */
.dash-link { font-size: .78rem; font-weight: 600; text-decoration: none; }

/* ── Insight strip ──
   Three figures from PO Insights, not a second copy of it: the one finding
   that asks somebody to act, plus the door to the rest. */
.ins-strip { border: 1px solid #fecaca; background: #fef2f2; border-radius: 12px;
             padding: .9rem 1.1rem; margin-bottom: 1rem; }
.ins-strip-head { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
                  justify-content: space-between; margin-bottom: .75rem; }
.ins-strip-title { font-weight: 800; color: #991b1b; font-size: .92rem;
                   display: flex; align-items: center; gap: .5rem; }
.ins-strip-link { font-size: .78rem; font-weight: 700; text-decoration: none; color: #b91c1c; }
.ins-strip-link:hover { color: #7f1d1d; }
.ins-fig { background: #fff; border: 1px solid #fecaca; border-radius: 10px;
           padding: .65rem .8rem; height: 100%; }
.ins-fig-val { font-size: 1.3rem; font-weight: 800; line-height: 1.1; color: #b91c1c; }
.ins-fig-lab { font-size: .74rem; color: #7f1d1d; margin-top: .15rem; }
.ins-fig-sub { font-size: .68rem; color: #9f6b6b; }
.stat-card .dash-link { display: inline-block; margin-top: .25rem; }

/* Compact tiles inside the Overview panel — smaller than the top .stat-card
   row so the two levels of summary read as a hierarchy, not a repeat. */
.mini-stat {
    display: flex; align-items: center; gap: .85rem;
    padding: .9rem 1rem; border: 1px solid var(--border);
    border-radius: 12px; background: #fff; height: 100%;
}
.mini-stat-icon {
    width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1rem;
}
.mini-stat-value { font-size: 1.5rem; font-weight: 700; color: #0f172a; line-height: 1.1; }
.mini-stat-label { font-size: .78rem; font-weight: 600; color: #334155; }
.mini-stat-sub   { font-size: .72rem; color: #94a3b8; }

.chart-title { font-size: .88rem; font-weight: 700; color: #0f172a; margin-bottom: .1rem; }
.chart-sub   { font-size: .72rem; color: #94a3b8; margin-bottom: .6rem; }
.chart-legend { display: flex; flex-wrap: wrap; gap: .4rem 1rem; font-size: .76rem; color: #475569; }
.chart-legend .cl-item { display: flex; align-items: center; gap: .4rem; }
.chart-legend .cl-swatch { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.chart-legend .cl-dash { width: 14px; height: 0; border-top: 2px dashed #cbd5e1; }
.chart-legend .cl-solid { width: 14px; height: 0; border-top: 2px solid #2563eb; }

/* Fixed height + preserveAspectRatio="none" means the chart stretches to fill
   whatever width it gets without growing taller on narrow screens — and since
   the geometry lives in viewBox units, no resize listener is needed. Strokes
   carry vector-effect="non-scaling-stroke" so the stretch doesn't thicken them. */
.chart-svg { width: 100%; height: 190px; display: block; overflow: visible; }
.donut-wrap { display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; }
.donut-wrap svg { flex-shrink: 0; }
.donut-center-value { font-size: 1.15rem; font-weight: 700; fill: #0f172a; }
.donut-center-label { font-size: .5rem; font-weight: 600; fill: #94a3b8; letter-spacing: .06em; }
.donut-legend { display: flex; flex-direction: column; gap: .5rem; font-size: .8rem; min-width: 170px; }
.donut-legend .dl-row { display: flex; align-items: center; gap: .5rem; }
.donut-legend .dl-swatch { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.donut-legend .dl-label { color: #475569; }
.donut-legend .dl-value { margin-left: auto; font-weight: 700; color: #0f172a; }

/* ── Quote of the day ──
   Served from system/data/quotes.json (366 entries, one per day of a leap
   year) so it works with no internet connection. Full-bleed banner across the
   top of the page: laid out as a row so the extra width goes into bigger type
   rather than a long thin line of text. */
/* No photo behind this — there isn't one of this office's own to use — so the
   dusk-over-mountains mood is built from the gradient alone: deep navy on the
   left easing into a warm amber glow on the right, the same left-to-right
   read as the reference. */
.quote-card {
    background: linear-gradient(100deg, #0b1a33 0%, #13294f 30%, #24406b 55%, #5b4632 85%, #7a5528 100%);
    border-radius: 14px; padding: 1.4rem 1.75rem; color: #fff;
    position: relative; overflow: hidden;
}
/* Faint radial glow standing in for the photo's own light source — kept
   subtle so it reads as atmosphere, not a shape. */
.quote-card::after {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(ellipse 60% 90% at 88% 30%, rgba(251,191,120,.22), transparent 70%);
}
.quote-top {
    display: flex; align-items: center; justify-content: space-between;
    gap: .75rem; flex-wrap: wrap; margin-bottom: .9rem;
    position: relative; z-index: 1;
}
.quote-eyebrow {
    font-size: .72rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase;
    color: #fdba74; padding-left: .8rem; border-left: 3px solid #f97316;
}
.quote-body { min-width: 0; position: relative; z-index: 1; }
.quote-text { font-size: 1.3rem; line-height: 1.45; font-weight: 600; font-style: italic; max-width: 62ch; }
.quote-meta { display: flex; align-items: center; gap: .75rem; margin-top: .6rem; flex-wrap: wrap; }
.quote-author { font-size: .88rem; font-weight: 600; font-style: italic; color: rgba(255,255,255,.82); }
.quote-topic {
    display: inline-block; padding: .15rem .65rem; border-radius: 20px;
    background: rgba(255,255,255,.18); font-size: .68rem; font-weight: 600; text-transform: capitalize;
}
.quote-day {
    font-size: .76rem; color: rgba(255,255,255,.75);
    white-space: nowrap;
}
@media (max-width: 767.98px) {
    .quote-text { font-size: 1.08rem; }
}

.sys-row {
    display: flex; align-items: center; gap: .75rem;
    padding: .7rem .85rem; border: 1px solid var(--border); border-radius: 10px; margin-bottom: .55rem;
}
.sys-icon {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: .85rem;
}
.sys-name   { font-size: .82rem; font-weight: 600; color: #1e293b; }
.sys-detail { font-size: .71rem; color: #94a3b8; word-break: break-all; }
.sys-state  { font-size: .75rem; font-weight: 700; }
.sys-state.up   { color: #16a34a; }
.sys-state.down { color: #dc2626; }

#recentPoTable td, #recentPoTable th { vertical-align: middle; }
.recent-po-num { color: #2563eb; font-weight: 600; text-decoration: none; white-space: nowrap; }
.recent-po-num:hover { text-decoration: underline; }
#recentPoTable td:nth-child(3), #recentPoTable td:nth-child(4) { white-space: nowrap; }
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
                <div class="page-title">Dashboard</div>
                <div class="page-subtitle">Welcome, <?= e($currentUser['full_name']) ?>!</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small d-none d-md-inline" id="currentDate"></span>
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <!-- Quote of the day — full width across the top so it's the first
             thing seen, ahead of the stat cards. -->
        <div class="quote-card mb-3" id="quoteCard">
            <div class="quote-top">
                <div class="quote-eyebrow">Quote of the Day</div>
                <div class="quote-day" id="quoteDay"></div>
            </div>
            <div class="quote-body">
                <div class="quote-text" id="quoteText">Loading…</div>
                <div class="quote-meta">
                    <span class="quote-author" id="quoteAuthor"></span>
                    <span class="quote-topic d-none" id="quoteTopic"></span>
                </div>
            </div>
        </div>

        <!-- Account row -->
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="stat-value" id="statTotalUsers">—</div>
                        <div class="stat-label">Total Users</div>
                        <a href="settings.php" class="dash-link">View all users <i class="fas fa-angle-right"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                    <div>
                        <div class="stat-value" id="statActiveUsers">—</div>
                        <div class="stat-label">Active Users</div>
                        <a href="settings.php" class="dash-link">View active users <i class="fas fa-angle-right"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-id-badge"></i></div>
                    <div>
                        <div class="stat-value" id="statYourRole" style="font-size:1.05rem;">—</div>
                        <div class="stat-label">Your Role</div>
                        <?php /* The "View role details" link pointed at the
                                 removed Access Levels screen. Roles and
                                 purchase-order access are set in HRMIS now,
                                 so there is nothing here to link to. */ ?>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon teal"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="stat-value" style="font-size:1.05rem;" id="statLastLogin">—</div>
                        <div class="stat-label">Last Login</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Overview + charts -->
            <div class="col-xl-8">
                <div class="panel mb-3">
                    <div class="panel-header">
                        <span class="panel-title"><i class="fas fa-chart-pie me-2 text-primary"></i>Overview</span>
                        <a href="purchase_orders.php" class="dash-link">Open Purchase Orders <i class="fas fa-angle-right"></i></a>
                    </div>
                    <div class="panel-body">
                        <div class="row g-3 mb-4" id="overviewTiles">
                            <div class="col-sm-6 col-lg-3">
                                <div class="mini-stat">
                                    <div class="mini-stat-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="fas fa-cart-shopping"></i></div>
                                    <div>
                                        <div class="mini-stat-value" id="ovPoMonth">—</div>
                                        <div class="mini-stat-label">Purchase Orders</div>
                                        <div class="mini-stat-sub">This Month</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="mini-stat">
                                    <div class="mini-stat-icon" style="background:#fef3c7;color:#b45309;"><i class="fas fa-clock"></i></div>
                                    <div>
                                        <div class="mini-stat-value" id="ovPending">—</div>
                                        <div class="mini-stat-label">Pending PO</div>
                                        <div class="mini-stat-sub">For Approval</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="mini-stat">
                                    <div class="mini-stat-icon" style="background:#dcfce7;color:#15803d;"><i class="fas fa-circle-check"></i></div>
                                    <div>
                                        <div class="mini-stat-value" id="ovApproved">—</div>
                                        <div class="mini-stat-label">Approved PO</div>
                                        <div class="mini-stat-sub">This Year</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <div class="mini-stat">
                                    <div class="mini-stat-icon" style="background:#ede9fe;color:#6d28d9;"><i class="fas fa-building"></i></div>
                                    <div>
                                        <div class="mini-stat-value" id="ovSuppliers">—</div>
                                        <div class="mini-stat-label">Total Suppliers</div>
                                        <div class="mini-stat-sub">Registered</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-7">
                                <div class="chart-title">Purchase Orders Trend</div>
                                <div class="chart-sub" id="trendSub">Daily count</div>
                                <div class="chart-legend mb-2">
                                    <span class="cl-item"><span class="cl-solid"></span>This Month</span>
                                    <span class="cl-item"><span class="cl-dash"></span>Last Month</span>
                                </div>
                                <div id="trendChart"><div class="text-muted small py-4">Loading…</div></div>
                            </div>
                            <div class="col-lg-5">
                                <div class="chart-title">PO Status Distribution</div>
                                <div class="chart-sub">Last 12 months</div>
                                <div id="statusChart"><div class="text-muted small py-4">Loading…</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($canInsights): ?>
                <div class="ins-strip d-none" id="insStrip">
                    <div class="ins-strip-head">
                        <div class="ins-strip-title"><i class="fas fa-triangle-exclamation"></i>
                            <span id="insStripTitle">From PO Insights</span></div>
                        <a href="po_insights.php" class="ins-strip-link">Open PO Insights <i class="fas fa-angle-right"></i></a>
                    </div>
                    <div class="row g-2" id="insStripFigures"></div>
                </div>
                <?php endif; ?>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title"><i class="fas fa-file-invoice me-2 text-primary"></i>Recent Purchase Orders</span>
                        <a href="purchase_orders.php" class="dash-link">View all <i class="fas fa-angle-right"></i></a>
                    </div>
                    <div class="panel-body p-0">
                        <div class="table-responsive">
                            <table class="app-table table" id="recentPoTable">
                                <thead>
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Supplier</th>
                                        <th>Date Created</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="5" class="text-muted small text-center py-3">Loading…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right rail -->
            <div class="col-xl-4">
                <div class="panel mb-3">
                    <div class="panel-header">
                        <span class="panel-title"><i class="fas fa-history me-2 text-secondary"></i>Recent Activity</span>
                        <a href="settings.php" class="dash-link">View all <i class="fas fa-angle-right"></i></a>
                    </div>
                    <div class="panel-body" id="recentActivity">
                        <div class="text-muted small text-center py-3">Loading…</div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title"><i class="fas fa-heart-pulse me-2 text-secondary"></i>System Summary</span>
                    </div>
                    <div class="panel-body" id="systemSummary">
                        <div class="text-muted small text-center py-3">Loading…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
<script>
function showToast(msg,type='success'){const id='t'+Date.now();const bg=type==='success'?'bg-success':'bg-danger';document.getElementById('toastContainer').insertAdjacentHTML('beforeend',`<div id="${id}" class="toast align-items-center text-white ${bg} border-0"><div class="d-flex"><div class="toast-body">${msg}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);const t=new bootstrap.Toast(document.getElementById(id),{delay:4000});t.show();document.getElementById(id).addEventListener('hidden.bs.toast',()=>document.getElementById(id)?.remove());}

const escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const peso = n => '₱' + Number(n || 0).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});

document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-PH',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

/* ── Line chart ───────────────────────────────────────────────────────────
   Plain SVG in a fixed 320x150 viewBox, stretched to the container by CSS.
   Drawing in viewBox units means the geometry never has to be recomputed on
   resize, which is why there's no resize handler anywhere on this page. */
function renderTrend(trend) {
    const box = document.getElementById('trendChart');
    const days = trend.days;
    // The current-month series stops at today. Plotting the remaining days of
    // the month would draw a flat line along zero, which reads as "no orders
    // placed" rather than "hasn't happened yet". Last month is always full.
    const cur  = trend.this_month.slice(0, Math.max(1, trend.today));
    const prev = trend.last_month.slice(0, days);
    const max  = Math.max(1, ...cur, ...prev);

    const W = 320, H = 150, padL = 24, padB = 18, padT = 6;
    // x() is indexed against the full month for both series, so the partial
    // current-month line still lines up with last month's day-for-day.
    const x = i => padL + (i / Math.max(1, days - 1)) * (W - padL - 4);
    const y = v => padT + (1 - v / max) * (H - padT - padB);
    const path = arr => arr.map((v, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(' ');
    // Area fill needs the line closed back along the baseline.
    const area = `${path(cur)} L${x(cur.length - 1).toFixed(1)},${y(0)} L${x(0).toFixed(1)},${y(0)} Z`;

    // Four gridlines with rounded value labels.
    let grid = '';
    for (let g = 0; g <= 3; g++) {
        const v  = Math.round((max / 3) * g);
        const yy = y(v).toFixed(1);
        grid += `<line x1="${padL}" y1="${yy}" x2="${W - 4}" y2="${yy}" stroke="#eef2f7" stroke-width="1"/>`
             +  `<text x="${padL - 5}" y="${(+yy + 3).toFixed(1)}" text-anchor="end" font-size="7" fill="#94a3b8">${v}</text>`;
    }
    // Date ticks every 7 days.
    let ticks = '';
    for (let d = 0; d < days; d += 7) {
        ticks += `<text x="${x(d).toFixed(1)}" y="${H - 4}" text-anchor="middle" font-size="7" fill="#94a3b8">${d + 1}</text>`;
    }

    box.innerHTML = `
        <svg class="chart-svg" viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" role="img"
             aria-label="Daily purchase order count, this month versus last month">
            <defs>
                <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#2563eb" stop-opacity=".22"/>
                    <stop offset="100%" stop-color="#2563eb" stop-opacity="0"/>
                </linearGradient>
            </defs>
            ${grid}
            <path d="${area}" fill="url(#trendFill)"/>
            <path d="${path(prev)}" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="4 3"
                  stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
            <path d="${path(cur)}" fill="none" stroke="#2563eb" stroke-width="2"
                  stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
            ${ticks}
        </svg>`;

    const curTotal  = cur.reduce((a, b) => a + b, 0);
    const prevTotal = prev.reduce((a, b) => a + b, 0);
    const diff = curTotal - prevTotal;
    const sign = diff > 0 ? '+' : '';
    document.getElementById('trendSub').textContent =
        `${curTotal} this month vs ${prevTotal} last month (${sign}${diff})`;
}

/* ── Donut ────────────────────────────────────────────────────────────────
   Built from stroke-dasharray arcs on concentric circles rather than arc
   path maths — same visual result, far less to get wrong. */
function renderStatus(status) {
    const box   = document.getElementById('statusChart');
    const total = status.reduce((a, s) => a + s.value, 0);
    if (!total) {
        box.innerHTML = '<div class="text-muted small py-4">No purchase orders in the last 12 months.</div>';
        return;
    }

    const R = 42, C = 2 * Math.PI * R;
    let offset = 0;
    const arcs = status.filter(s => s.value > 0).map(s => {
        const len = (s.value / total) * C;
        const seg = `<circle cx="60" cy="60" r="${R}" fill="none" stroke="${s.color}" stroke-width="16"
                        stroke-dasharray="${len.toFixed(2)} ${(C - len).toFixed(2)}"
                        stroke-dashoffset="${(-offset).toFixed(2)}"
                        transform="rotate(-90 60 60)"><title>${escapeHtml(s.label)}: ${s.value}</title></circle>`;
        offset += len;
        return seg;
    }).join('');

    box.innerHTML = `
        <div class="donut-wrap">
            <svg width="120" height="120" viewBox="0 0 120 120" role="img" aria-label="Purchase order status distribution">
                ${arcs}
                <text x="60" y="58" text-anchor="middle" class="donut-center-value">${total.toLocaleString()}</text>
                <text x="60" y="70" text-anchor="middle" class="donut-center-label">TOTAL</text>
            </svg>
            <div class="donut-legend">
                ${status.map(s => `
                    <div class="dl-row">
                        <span class="dl-swatch" style="background:${s.color}"></span>
                        <span class="dl-label">${escapeHtml(s.label)}</span>
                        <span class="dl-value">${s.value.toLocaleString()}</span>
                    </div>`).join('')}
            </div>
        </div>`;
}

/* Status shown per PO in the Recent table — derived from the same date
   columns the donut uses, so the two can never disagree. */
function poStatus(r) {
    if (r.date_coa)      return ['COA received', '#ede9fe', '#6d28d9'];
    if (r.date_received) return ['With supplier', '#dcfce7', '#15803d'];
    if (r.date_approved) return ['Approved',      '#dbeafe', '#1d4ed8'];
    return ['Pending', '#fef3c7', '#b45309'];
}

function renderRecentPo(rows) {
    const body = document.querySelector('#recentPoTable tbody');
    if (!rows || !rows.length) {
        body.innerHTML = '<tr><td colspan="5" class="text-muted small text-center py-3">No purchase orders yet.</td></tr>';
        return;
    }
    body.innerHTML = rows.map(r => {
        const [label, bg, fg] = poStatus(r);
        const when = r.date_created
            ? new Date(r.date_created.replace(' ', 'T')).toLocaleDateString('en-PH', {month:'short', day:'numeric', year:'numeric'})
            : '—';
        return `
            <tr>
                <td><a class="recent-po-num" href="purchase_orders.php?po_number=${encodeURIComponent(r.po_number)}&view=1">${escapeHtml(r.po_number)}</a></td>
                <td>${escapeHtml(r.supplier_name) || '—'}</td>
                <td>${when}</td>
                <td class="text-end">${peso(r.amount)}</td>
                <td><span class="badge-status" style="background:${bg};color:${fg}">${label}</span></td>
            </tr>`;
    }).join('');
}

function renderSystem(sys) {
    // "Local Database" is gone — the app no longer has one; everything lives
    // on the HRMIS server. Filtered rather than assumed present so a missing
    // key can't render an undefined tile.
    const rows = [
        ['PO Database',    'fa-server',        sys.po_db],
        ['HRMIS Database', 'fa-network-wired', sys.hrmis_db],
        ['Runtime',        'fa-code',          sys.php],
    ].filter(([, , s]) => s);
    document.getElementById('systemSummary').innerHTML = rows.map(([name, icon, s]) => `
        <div class="sys-row">
            <div class="sys-icon" style="background:${s.ok ? '#dcfce7' : '#fee2e2'};color:${s.ok ? '#15803d' : '#b91c1c'}">
                <i class="fas ${icon}"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="sys-name">${name}</div>
                <div class="sys-detail">${escapeHtml(s.detail)}${s.ms != null ? ` · ${s.ms} ms` : ''}</div>
            </div>
            <div class="sys-state ${s.ok ? 'up' : 'down'}">${s.ok ? 'Online' : 'Down'}</div>
        </div>`).join('');
}

function renderQuote(q) {
    if (!q) { document.getElementById('quoteCard').classList.add('d-none'); return; }
    document.getElementById('quoteText').textContent   = '“' + q.text + '”';
    document.getElementById('quoteAuthor').textContent = '— ' + q.author;
    document.getElementById('quoteDay').textContent    = `Day ${q.day} of ${q.total}`;
    const topic = document.getElementById('quoteTopic');
    topic.textContent = q.topic;
    topic.classList.remove('d-none');
}

async function loadDashboard() {
    try {
        const res  = await fetch('api/dashboard.php');
        const data = await res.json();
        if (!data.success) return showToast(data.message || 'Failed to load dashboard.', 'error');

        document.getElementById('statTotalUsers').textContent  = data.total_users;
        document.getElementById('statActiveUsers').textContent = data.active_users;
        document.getElementById('statYourRole').textContent    = data.your_role;
        document.getElementById('statLastLogin').textContent   = <?= json_encode($currentUser['last_login'] ?? null) ?>
            ? new Date(<?= json_encode($currentUser['last_login']) ?>.replace(' ','T')).toLocaleDateString('en-PH',{month:'short',day:'numeric'})
            : 'First login';

        renderQuote(data.quote);
        renderSystem(data.system);

        if (data.po) {
            document.getElementById('ovPoMonth').textContent   = data.po.this_month.toLocaleString();
            document.getElementById('ovPending').textContent   = data.po.pending_approval.toLocaleString();
            document.getElementById('ovApproved').textContent  = data.po.approved_ytd.toLocaleString();
            document.getElementById('ovSuppliers').textContent = data.po.suppliers.toLocaleString();
            renderTrend(data.po.trend);
            renderStatus(data.po.status);
            renderRecentPo(data.po.recent);
        } else {
            // The PO database is a separate host — say so plainly instead of
            // leaving em-dashes and spinners on screen forever.
            const msg = data.po_error || 'Purchase Order data unavailable.';
            ['ovPoMonth','ovPending','ovApproved','ovSuppliers'].forEach(id => document.getElementById(id).textContent = '—');
            document.getElementById('trendChart').innerHTML  = `<div class="text-danger small py-4">${escapeHtml(msg)}</div>`;
            document.getElementById('statusChart').innerHTML = '';
            document.querySelector('#recentPoTable tbody').innerHTML =
                `<tr><td colspan="5" class="text-danger small text-center py-3">${escapeHtml(msg)}</td></tr>`;
        }

        const box = document.getElementById('recentActivity');
        if (!data.recent.length) {
            box.innerHTML = '<div class="text-muted small text-center py-3">No activity yet.</div>';
            return;
        }
        box.innerHTML = data.recent.map(r => `
            <div class="txn-item">
                <div class="txn-icon" style="background:${r.action==='LOGIN'?'#dcfce7':'#fee2e2'};color:${r.action==='LOGIN'?'#15803d':'#991b1b'};">
                    <i class="fas fa-${r.action==='LOGIN'?'right-to-bracket':'right-from-bracket'}"></i>
                </div>
                <div>
                    <p class="txn-title">${escapeHtml(r.username) || 'Unknown'} ${r.action === 'LOGIN' ? 'signed in' : 'signed out'}</p>
                    <div class="txn-meta">${new Date(r.created_at.replace(' ','T')).toLocaleString('en-PH')}</div>
                </div>
            </div>
        `).join('');
    } catch (err) {
        showToast('Failed to load dashboard.', 'error');
        console.error(err);
    }
}

loadDashboard();

<?php if ($canInsights): ?>
// Deliberately a second request rather than more work inside api/dashboard.php:
// this one reads every live PO and takes about half a second, and the dashboard
// should not wait on it. The strip stays hidden until it has something to say.
async function loadInsightStrip() {
    try {
        const res  = await fetch('api/po_insights.php?summary=1');
        const data = await res.json();
        if (!data.success) return;

        const a = data.approval, o = data.orphans;
        const peso = n => '₱' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const short = n => Math.abs(n) >= 1e9 ? '₱' + (n / 1e9).toFixed(2) + 'B'
                         : Math.abs(n) >= 1e6 ? '₱' + (n / 1e6).toFixed(1) + 'M'
                         : '₱' + Math.round(n / 1e3) + 'K';
        const nf  = n => Number(n || 0).toLocaleString('en-US');
        const pct = (x, y) => y ? (x / y * 100).toFixed(1) + '%' : '—';
        const fig = (v, l, s) => `<div class="col-sm-6"><div class="ins-fig">
            <div class="ins-fig-val">${v}</div><div class="ins-fig-lab">${l}</div>
            <div class="ins-fig-sub">${s}</div></div></div>`;

        document.getElementById('insStripTitle').textContent =
            `${nf(a.unapproved_pos)} POs carry no approval date — ${peso(a.unapproved_val)}`;
        document.getElementById('insStripFigures').innerHTML =
            fig(pct(a.big_unapproved, a.big_pos), 'of POs over ₱1M have no approval date',
                `${nf(a.big_unapproved)} of ${nf(a.big_pos)}`) +
            fig(pct(a.bid_big_unapproved, a.bid_big_pos), 'of bidding POs over ₱1M',
                `against ${pct(a.small_unapproved, a.small_pos)} of POs under ₱1M`) +
            fig(short(a.big_unapproved_val), 'unapproved value, over ₱1M',
                'the money the approval date is missing on') +
            fig(nf(o.groups), 'item groups belong to no live PO',
                `worth ${short(o.value)}, left behind by deletions`);
        document.getElementById('insStrip').classList.remove('d-none');
    } catch (err) {
        // A failed insight scan must never break the dashboard: leave the strip
        // hidden and say nothing.
        console.error(err);
    }
}
loadInsightStrip();
<?php endif; ?>
</script>
<?php include 'profile_modal.php'; ?>
<?php include 'assistant.php'; ?>
</body>
</html>
