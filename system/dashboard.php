<?php
require_once 'config.php';
requireAuth();
$currentUser = getCurrentUser();
$activePage  = 'dashboard';

// First name only, title-cased, for the greeting — the source is often
// all-caps and/or surname-first, and neither is how you say hello.
$dFirst = trim((string)($currentUser['full_name'] ?? ''));
if (str_contains($dFirst, ',')) {
    $dFirst = trim(substr($dFirst, strpos($dFirst, ',') + 1));
}
$dFirst = (string)strtok($dFirst, ' ');
$dFirst = $dFirst !== '' ? mb_convert_case($dFirst, MB_CASE_TITLE, 'UTF-8') : '';
$dHour  = (int)date('G');
$dGreetingWord = $dHour < 12 ? 'Morning' : ($dHour < 18 ? 'Afternoon' : 'Evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link href="vendor/argon/css/argon-dashboard.min.css" rel="stylesheet">
<link href="vendor/argon/css/nucleo-icons.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
<style>
/* Recent Transactions status pills — statuses come from several different
   document types (PR/PO/RR/DR/Issuance), so these are grouped by meaning
   rather than matching one table's enum. */
.badge-status.approved, .badge-status.received, .badge-status.delivered,
.badge-status.completed, .badge-status.converted { background: #dcfce7; color: #166534; }
.badge-status.pending, .badge-status.draft, .badge-status.sent, .badge-status.open,
.badge-status.partially_received, .badge-status.partially_delivered { background: #fef3c7; color: #92400e; }
.badge-status.rejected, .badge-status.cancelled { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body>
<?php include 'topnav.php'; ?>
<div class="page-wrapper">
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row align-items-center">
                <div class="col">
                    <div class="page-pretitle">Home</div>
                    <h2 class="page-title">Dashboard</h2>
                    <div class="text-muted small">Snapshot of procurement, inventory, sales, and recent activity.</div>
                </div>

            </div>
        </div>
    </div>

    <div class="page-body">
    <div class="container-xl">
        <div class="dash-hero">
            <div class="dash-hero-inner">
                <div class="dash-hero-tag">Smarter Logistics. Stronger Connections.</div>
                <h1 class="dash-hero-title">EXECOM Logistics System</h1>
                <p class="dash-hero-sub">Good <?= e($dGreetingWord) ?><?= $dFirst !== '' ? ', ' . e($dFirst) : '' ?>! Efficient procurement, inventory and delivery for your IT and hardware needs — here's what's happening today, <?= date('l, F j, Y') ?>.</p>
                <div class="dash-hero-chips">
                    <span class="dash-hero-chip"><i class="fas fa-file-signature"></i> Procurement</span>
                    <span class="dash-hero-chip"><i class="fas fa-warehouse"></i> Inventory</span>
                    <span class="dash-hero-chip"><i class="fas fa-truck-fast"></i> Delivery</span>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="row g-3 mb-3" id="mkKpiCards">
                    <div class="col-6 col-md-3">
                        <div class="mk-kpi">
                            <div class="mk-kpi-icon" style="background:linear-gradient(135deg,#2563eb,#3b82f6)"><i class="fas fa-boxes-stacked"></i></div>
                            <div class="mk-kpi-value" id="mkInventory">—</div>
                            <div class="mk-kpi-label">Total Inventory</div>
                            <div class="mk-kpi-caption">Items in stock</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="inventory.php" class="text-decoration-none">
                        <div class="mk-kpi">
                            <div class="mk-kpi-icon" style="background:linear-gradient(135deg,#d97706,#f59e0b)"><i class="fas fa-triangle-exclamation"></i></div>
                            <div class="mk-kpi-value" id="mkLowStock">—</div>
                            <div class="mk-kpi-label">Low Stock</div>
                            <div class="mk-kpi-caption">below reorder level</div>
                        </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="order_tracking.php" class="text-decoration-none">
                        <div class="mk-kpi">
                            <div class="mk-kpi-icon" style="background:linear-gradient(135deg,#7c3aed,#8b5cf6)"><i class="fas fa-file-invoice-dollar"></i></div>
                            <div class="mk-kpi-value" id="mkOpenPo">—</div>
                            <div class="mk-kpi-label">Open PO</div>
                            <div class="mk-kpi-caption">ongoing orders</div>
                        </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="delivery_reports.php" class="text-decoration-none">
                        <div class="mk-kpi">
                            <div class="mk-kpi-icon" style="background:linear-gradient(135deg,#16a34a,#22c55e)"><i class="fas fa-truck-ramp-box"></i></div>
                            <div class="mk-kpi-value" id="mkDeliveries">—</div>
                            <div class="mk-kpi-label">Today's Deliveries</div>
                            <div class="mk-kpi-caption">ready for dispatch</div>
                        </div>
                        </a>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-lg-7">
                        <div class="panel h-100">
                            <div class="panel-header">
                                <div class="panel-title">Inventory Overview</div>
                                <div class="chart-tabs" id="invTabs">
                                    <button type="button" class="chart-tab active" data-tab="total" onclick="switchInvTab('total')">Total Stock</button>
                                    <button type="button" class="chart-tab" data-tab="category" onclick="switchInvTab('category')">By Category</button>
                                    <button type="button" class="chart-tab" data-tab="branch" onclick="switchInvTab('branch')">By Branch</button>
                                </div>
                            </div>
                            <div class="panel-body">
                                <div class="chart-box" style="height:230px;"><canvas id="invOverviewChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="panel h-100">
                            <div class="panel-header"><div class="panel-title">Stock by Category</div></div>
                            <div class="panel-body">
                                <div class="chart-box" style="height:130px;" id="catDonutBox"><canvas id="catDonutChart"></canvas></div>
                                <div id="catDonutLegend" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-lg-8">
                        <div class="panel h-100">
                            <div class="panel-header">
                                <div class="panel-title">Recent Transactions</div>
                                <a href="order_tracking.php" class="small text-decoration-none">View all <i class="fas fa-arrow-right"></i></a>
                            </div>
                            <div class="panel-body p-0" id="recentTransactionsBody"><div class="text-muted small p-3">Loading…</div></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="panel h-100">
                            <div class="panel-header"><div class="panel-title">Top Suppliers</div></div>
                            <div class="panel-body" id="topSuppliersBody"><div class="text-muted small">Loading…</div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="panel mb-3">
                    <div class="panel-header"><div class="panel-title">Quick Actions</div></div>
                    <div class="panel-body">
                        <?php if ($canPoInternal): ?><a href="po_internal.php" class="quick-action"><span class="qa-icon purple"><i class="fas fa-file-invoice-dollar"></i></span><div>Create Purchase Order</div></a><?php endif; ?>
                        <?php if ($canReceiving): ?><a href="receiving_reports.php" class="quick-action"><span class="qa-icon green"><i class="fas fa-truck-ramp-box"></i></span><div>Record Receiving Report</div></a><?php endif; ?>
                        <?php if ($canIssuance): ?><a href="issuances.php" class="quick-action"><span class="qa-icon red"><i class="fas fa-cash-register"></i></span><div>Issue Goods (POS)</div></a><?php endif; ?>
                        <?php if ($canInventory): ?><a href="inventory.php" class="quick-action"><span class="qa-icon amber"><i class="fas fa-boxes-stacked"></i></span><div>Check Inventory</div></a><?php endif; ?>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-header"><div class="panel-title">Recent Activity</div></div>
                    <div class="panel-body" id="recentActivity">
                        <div class="text-muted small">Loading…</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-label">Business Performance — This Month</div>
        <div class="row g-3 mb-3" id="kpiCards">
            <div class="col-6 col-lg-3">
                <div class="stat-card blue">
                    <div class="stat-icon blue"><i class="fas fa-sack-dollar"></i></div>
                    <div>
                        <div class="stat-value" id="kpiRevenue">—</div>
                        <div class="stat-label">Revenue</div>
                        <div id="kpiRevenueTrend"></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card green">
                    <div class="stat-icon green"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <div class="stat-value" id="kpiProfit">—</div>
                        <div class="stat-label">Gross Profit</div>
                        <div id="kpiProfitTrend"></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card purple">
                    <div class="stat-icon purple"><i class="fas fa-percent"></i></div>
                    <div>
                        <div class="stat-value" id="kpiMargin">—</div>
                        <div class="stat-label">Profit Margin</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <a href="#needsAttention" class="text-decoration-none">
                <div class="stat-card amber">
                    <div class="stat-icon amber"><i class="fas fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="stat-value" id="kpiAttention">—</div>
                        <div class="stat-label">Needs Attention</div>
                    </div>
                </div>
                </a>
            </div>
        </div>

        <div class="section-label">Operations Snapshot</div>
        <div class="row g-3 mb-3" id="statCards">
            <div class="col-6 col-lg-3">
                <div class="stat-card blue">
                    <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
                    <div>
                        <div class="stat-value" id="statIssuances">—</div>
                        <div class="stat-label">Issuances</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card purple">
                    <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div>
                        <div class="stat-value" id="statPo">—</div>
                        <div class="stat-label">Purchase Orders</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card green">
                    <div class="stat-icon"><i class="fas fa-truck-ramp-box"></i></div>
                    <div>
                        <div class="stat-value" id="statReceived">—</div>
                        <div class="stat-label">Received Items</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card amber">
                    <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
                    <div>
                        <div class="stat-value" id="statInventory">—</div>
                        <div class="stat-label">Inventory Items</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-7">
                <div class="panel h-100">
                    <div class="panel-header"><div class="panel-title">Revenue &amp; Cost — Last 6 Months</div></div>
                    <div class="panel-body">
                        <div class="chart-box" style="height:260px;"><canvas id="salesChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="panel h-100">
                    <div class="panel-header"><div class="panel-title">Top 5 Products</div></div>
                    <div class="panel-body">
                        <div class="chart-box" style="height:150px;" id="topChartBox"><canvas id="topChart"></canvas></div>
                        <div id="topProductsList" class="mt-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><div class="panel-title">Branch Performance — This Month</div></div>
                    <div class="panel-body" id="branchLeaderboard">
                        <div class="text-muted small">Loading…</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><div class="panel-title">Client Order Pipeline</div></div>
                    <div class="panel-body">
                        <div class="pipeline-row" id="orderPipeline"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3" id="needsAttention">
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><div class="panel-title">Needs Attention</div></div>
                    <div class="panel-body" id="attentionList">
                        <div class="text-muted small">Loading…</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-header"><div class="panel-title">Low Stock Alerts</div></div>
                    <div class="panel-body p-0" id="lowStockBody">
                        <div class="text-muted small p-3">Loading…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/argon/js/popper.min.js"></script>
<script src="vendor/argon/js/bootstrap.min.js"></script>
<script src="vendor/js/chart.umd.min.js"></script>
<script>
function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function fmtMoney(n) {
    return '₱' + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function timeAgo(iso) {
    const diff = (Date.now() - new Date(String(iso).replace(' ', 'T'))) / 1000;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}
// pct is a percent-change number, or null when there's no prior-period
// baseline to compare against (e.g. zero sales last month).
function trendBadge(pct) {
    if (pct === null || pct === undefined) {
        return '<span class="kpi-trend flat"><i class="fas fa-circle-plus"></i> New</span>';
    }
    if (pct > 0) return `<span class="kpi-trend up"><i class="fas fa-arrow-up"></i> ${pct}% vs last month</span>`;
    if (pct < 0) return `<span class="kpi-trend down"><i class="fas fa-arrow-down"></i> ${Math.abs(pct)}% vs last month</span>`;
    return '<span class="kpi-trend flat"><i class="fas fa-minus"></i> No change</span>';
}
// Heuristic color for the activity timeline dot, from the action verb —
// audit_logs.action has no severity/category column of its own.
function activityColor(action) {
    const a = (action || '').toLowerCase();
    if (a.includes('delete') || a.includes('cancel') || a.includes('reject')) return 'red';
    if (a.includes('approve') || a.includes('complete') || a.includes('receiv') || a.includes('activat')) return 'green';
    if (a.includes('create') || a.includes('add')) return 'blue';
    if (a.includes('transfer') || a.includes('ship') || a.includes('issu')) return 'amber';
    if (a.includes('update') || a.includes('edit')) return 'purple';
    return 'gray';
}

let salesChartInstance = null;
let topChartInstance = null;
let invOverviewChartInstance = null;
let catDonutChartInstance = null;
let dashData = null;

const statusLabels = {
    draft: 'Draft', pending: 'Pending', approved: 'Approved', rejected: 'Rejected', converted: 'Converted',
    open: 'Open', cancelled: 'Cancelled', quoted: 'Quoted', sent: 'Sent',
    partially_received: 'Partial', received: 'Received', partially_delivered: 'Partial',
    delivered: 'Delivered', completed: 'Completed', issued: 'Issued',
};

async function loadDashboard() {
    try {
        const params = new URLSearchParams(location.search);
        const branchId = params.get('branch_id');
        const url = 'api/dashboard.php' + (branchId ? ('?branch_id=' + encodeURIComponent(branchId)) : '');
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) return;
        dashData = data;

        const s = data.stats;
        document.getElementById('statIssuances').textContent = Number(s.issuances).toLocaleString();
        document.getElementById('statPo').textContent        = Number(s.purchase_orders).toLocaleString();
        document.getElementById('statReceived').textContent  = Number(s.received_items).toLocaleString();
        document.getElementById('statInventory').textContent = Number(s.inventory_items).toLocaleString();

        document.getElementById('mkInventory').textContent  = Number(s.inventory_items).toLocaleString();
        document.getElementById('mkLowStock').textContent   = Number(data.needs_attention.low_stock).toLocaleString();
        document.getElementById('mkOpenPo').textContent     = Number(data.open_po_count).toLocaleString();
        document.getElementById('mkDeliveries').textContent = Number(data.today_deliveries).toLocaleString();

        const f = data.financials;
        document.getElementById('kpiRevenue').textContent = fmtMoney(f.revenue);
        document.getElementById('kpiProfit').textContent  = fmtMoney(f.profit);
        document.getElementById('kpiMargin').textContent  = f.margin_pct + '%';
        document.getElementById('kpiRevenueTrend').innerHTML = trendBadge(f.revenue_change);
        document.getElementById('kpiProfitTrend').innerHTML  = trendBadge(f.profit_change);

        const na = data.needs_attention;
        const naTotal = na.stock_pending + na.orders_open + na.low_stock;
        document.getElementById('kpiAttention').textContent = naTotal.toLocaleString();
        renderAttentionList(na);
        renderLowStock(data.low_stock_items);
        renderOrderPipeline(data.order_pipeline);
        renderBranchLeaderboard(data.branch_performance);

        renderSalesChart(data.monthly_sales);
        renderTopProducts(data.top_products);
        renderInvOverviewChart('total');
        renderCatDonut(data.stock_by_category);
        renderRecentTransactions(data.recent_transactions);
        renderTopSuppliers(data.top_suppliers);

        const feed = document.getElementById('recentActivity');
        if (!data.recent_activity.length) {
            feed.innerHTML = '<div class="text-muted small">No activity yet.</div>';
        } else {
            feed.innerHTML = '<div class="activity-timeline">' + data.recent_activity.map(r => `
                <div class="at-item">
                    <div class="at-dot ${activityColor(r.action)}"></div>
                    <div>
                        <div class="at-title">${escapeHtml(r.action)} <span class="text-muted fw-normal">— ${escapeHtml(r.module || '')}</span></div>
                        <div class="at-meta">${escapeHtml(r.username || 'system')} · ${timeAgo(r.created_at)}</div>
                    </div>
                </div>`).join('') + '</div>';
        }
    } catch (err) {
        console.error(err);
    }
}

function renderSalesChart(series) {
    const ctx = document.getElementById('salesChart');
    if (!ctx) return;
    const labels  = series.map(r => r.label);
    const revenue = series.map(r => r.revenue);
    const cost    = series.map(r => r.cost);
    if (salesChartInstance) salesChartInstance.destroy();
    salesChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Revenue', data: revenue, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.1)', fill: true, tension: .35, pointRadius: 3 },
                { label: 'Cost', data: cost, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,.08)', fill: true, tension: .35, pointRadius: 3 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => fmtMoney(v) }, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } },
            },
        },
    });
}

// Inventory Overview panel — one shared canvas, three tabs. "total" is a
// line trend (units on hand over the last 6 months); "category"/"branch"
// are bar breakdowns of the current snapshot.
function switchInvTab(tab) {
    document.querySelectorAll('#invTabs .chart-tab').forEach(btn => btn.classList.toggle('active', btn.dataset.tab === tab));
    renderInvOverviewChart(tab);
}
function renderInvOverviewChart(tab) {
    const ctx = document.getElementById('invOverviewChart');
    if (!ctx || !dashData) return;
    if (invOverviewChartInstance) invOverviewChartInstance.destroy();

    if (tab === 'total') {
        const series = dashData.stock_trend;
        invOverviewChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: series.map(r => r.label),
                datasets: [{ label: 'Total Stock', data: series.map(r => r.total), borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.1)', fill: true, tension: .35, pointRadius: 3 }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } },
            },
        });
        return;
    }

    const rows = tab === 'category' ? dashData.stock_by_category : dashData.stock_by_branch;
    const colors = ['#2563eb', '#7c3aed', '#16a34a', '#d97706', '#0891b2', '#dc2626', '#65a30d', '#db2777'];
    invOverviewChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: rows.map(r => r.name),
            datasets: [{ label: 'On Hand', data: rows.map(r => r.qty), backgroundColor: rows.map((_, i) => colors[i % colors.length]), borderRadius: 6 }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } },
        },
    });
}

function renderCatDonut(categories) {
    const box  = document.getElementById('catDonutBox');
    const legend = document.getElementById('catDonutLegend');
    if (!categories.length) {
        box.style.display = 'none';
        legend.innerHTML = '<div class="text-muted small text-center py-3">No stock recorded yet.</div>';
        return;
    }
    box.style.display = '';
    const colors = ['#2563eb', '#7c3aed', '#16a34a', '#d97706', '#0891b2', '#dc2626', '#65a30d', '#db2777'];
    const total = categories.reduce((sum, c) => sum + Number(c.qty), 0) || 1;
    const ctx = document.getElementById('catDonutChart');
    if (catDonutChartInstance) catDonutChartInstance.destroy();
    catDonutChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: { labels: categories.map(c => c.name), datasets: [{ data: categories.map(c => c.qty), backgroundColor: colors, borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } },
    });
    legend.innerHTML = categories.map((c, i) => `
        <div class="donut-legend-row">
            <span class="donut-legend-dot" style="background:${colors[i % colors.length]}"></span>
            <span class="donut-legend-name">${escapeHtml(c.name)}</span>
            <span class="donut-legend-pct">${Math.round(c.qty / total * 100)}%</span>
        </div>`).join('');
}

function renderRecentTransactions(rows) {
    const box = document.getElementById('recentTransactionsBody');
    if (!rows.length) {
        box.innerHTML = '<div class="text-muted small p-3">No transactions recorded yet.</div>';
        return;
    }
    box.innerHTML = `<div class="table-responsive"><table class="app-table table mb-0">
        <thead><tr><th>Date</th><th>Document No.</th><th>Type</th><th>Description</th><th>Status</th></tr></thead>
        <tbody>${rows.map(r => `
            <tr>
                <td class="text-muted text-nowrap">${new Date(String(r.created_at).replace(' ', 'T')).toLocaleDateString()}</td>
                <td class="text-nowrap"><span class="doc-number ${r.doc_class}">${escapeHtml(r.doc_number)}</span></td>
                <td class="text-nowrap">${escapeHtml(r.doc_type)}</td>
                <td>${escapeHtml(r.description || '—')}</td>
                <td class="text-nowrap"><span class="badge-status ${r.status}">${statusLabels[r.status] || escapeHtml(r.status)}</span></td>
            </tr>`).join('')}</tbody>
    </table></div>`;
}

function renderTopSuppliers(rows) {
    const box = document.getElementById('topSuppliersBody');
    if (!rows.length) {
        box.innerHTML = '<div class="text-muted small">No supplier orders recorded yet.</div>';
        return;
    }
    box.innerHTML = rows.map(r => `
        <div class="supplier-row">
            <div class="supplier-badge">${escapeHtml(r.name.slice(0, 2).toUpperCase())}</div>
            <div class="supplier-name">${escapeHtml(r.name)}</div>
            <div class="supplier-count">${Number(r.order_count).toLocaleString()} order${r.order_count == 1 ? '' : 's'}</div>
        </div>`).join('');
}

function renderBranchLeaderboard(branches) {
    const box = document.getElementById('branchLeaderboard');
    if (!branches.length) {
        box.innerHTML = '<div class="text-muted small">No branch data yet.</div>';
        return;
    }
    const max = Math.max(1, ...branches.map(b => Number(b.revenue)));
    box.innerHTML = branches.map((b, i) => `
        <div class="lb-row">
            <div class="lb-rank">${i + 1}</div>
            <div class="lb-body">
                <div class="lb-name"><span>${escapeHtml(b.branch_name)}</span><span class="lb-value">${fmtMoney(b.revenue)}</span></div>
                <div class="lb-bar-track"><div class="lb-bar-fill" style="width:${(Number(b.revenue) / max * 100).toFixed(1)}%"></div></div>
            </div>
        </div>`).join('');
}

function renderOrderPipeline(stages) {
    const pillClass = { open: 'pp-open', partially_delivered: 'pp-partial', delivered: 'pp-delivered', completed: 'pp-completed', cancelled: 'pp-cancelled' };
    const box = document.getElementById('orderPipeline');
    box.innerHTML = stages.map(st => `
        <div class="pipeline-pill ${pillClass[st.status] || ''}">
            <div class="pp-count">${Number(st.count).toLocaleString()}</div>
            <div class="pp-label">${escapeHtml(st.label)}</div>
        </div>`).join('');
}

function renderAttentionList(na) {
    const items = [
        { key: 'stock_pending', icon: 'fa-truck-arrow-right',   color: 'purple', label: 'Stock Transfers In Progress',   href: 'stock_transfers.php' },
        { key: 'orders_open',   icon: 'fa-file-invoice-dollar', color: 'teal',   label: 'Client Orders In Progress',     href: 'po_outgoing.php' },
        { key: 'low_stock',     icon: 'fa-triangle-exclamation', color: 'amber', label: 'Items Below Reorder Level',     href: 'inventory.php' },
    ];
    const box = document.getElementById('attentionList');
    const withCounts = items.filter(it => Number(na[it.key]) > 0);
    if (!withCounts.length) {
        box.innerHTML = '<div class="text-muted small">All clear — nothing waiting on action right now.</div>';
        return;
    }
    box.innerHTML = withCounts.map(it => `
        <a href="${it.href}" class="quick-action">
            <div class="qa-icon ${it.color}"><i class="fas ${it.icon}"></i></div>
            <div>${escapeHtml(it.label)}</div>
            <div class="qa-count">${Number(na[it.key]).toLocaleString()}</div>
        </a>`).join('');
}

function renderLowStock(items) {
    const box = document.getElementById('lowStockBody');
    if (!items.length) {
        box.innerHTML = '<div class="text-muted small p-3">No items below their reorder level.</div>';
        return;
    }
    box.innerHTML = `<table class="app-table">
        <thead><tr><th>Item</th><th>Branch</th><th class="text-end">On Hand</th><th class="text-end">Reorder At</th></tr></thead>
        <tbody>${items.map(r => `
            <tr>
                <td>${escapeHtml(r.item_name)}</td>
                <td>${escapeHtml(r.branch_name)}</td>
                <td class="text-end text-danger fw-bold">${Number(r.quantity_on_hand).toLocaleString()}</td>
                <td class="text-end text-muted">${Number(r.reorder_level).toLocaleString()}</td>
            </tr>`).join('')}</tbody>
    </table>`;
}

function renderTopProducts(products) {
    const box = document.getElementById('topChartBox');
    const list = document.getElementById('topProductsList');
    if (!products.length) {
        box.style.display = 'none';
        list.innerHTML = '<div class="text-muted small py-3 text-center">No sales recorded yet.</div>';
        return;
    }
    box.style.display = '';
    const colors = ['#2563eb', '#7c3aed', '#16a34a', '#d97706', '#dc2626'];
    const ctx = document.getElementById('topChart');
    if (topChartInstance) topChartInstance.destroy();
    topChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: products.map(p => p.item_name),
            datasets: [{ data: products.map(p => Number(p.revenue)), backgroundColor: colors, borderWidth: 0 }],
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } },
    });

    list.innerHTML = products.map((p, i) => `
        <div class="tp-row">
            <div class="tp-rank" style="background:${colors[i]}22;color:${colors[i]}">${i + 1}</div>
            <div>
                <div class="tp-name">${escapeHtml(p.item_name)}</div>
                <div class="tp-meta">${Number(p.qty_sold).toLocaleString()} sold</div>
            </div>
            <div class="tp-amount">
                <div class="tp-rev">${fmtMoney(p.revenue)}</div>
            </div>
        </div>`).join('');
}

loadDashboard();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
