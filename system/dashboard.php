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
<link href="vendor/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="vendor/css/all.min.css">
<link rel="stylesheet" href="css/base.css">
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
                <div class="small text-muted">Snapshot of procurement, inventory, sales, and recent activity.</div>
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Dashboard</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="dash-header">
            <div>
                <h1 class="dash-greeting">Good <?= e($dGreetingWord) ?><?= $dFirst !== '' ? ', ' . e($dFirst) : '' ?>!</h1>
                <div class="dash-subtitle">Here's what's happening with your logistics today.</div>
            </div>
            <div class="dash-date-pill"><i class="fas fa-calendar-days"></i> <?= date('l, F j, Y') ?></div>
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
                    <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                    <div>
                        <div class="stat-value" id="statPr">—</div>
                        <div class="stat-label">Purchase Requisitions</div>
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

        <div class="panel">
            <div class="panel-header"><div class="panel-title">Recent Activity</div></div>
            <div class="panel-body" id="recentActivity">
                <div class="text-muted small">Loading…</div>
            </div>
        </div>
    </div>
</div>
</div>

<script src="vendor/js/jquery.min.js"></script>
<script src="vendor/js/bootstrap.bundle.min.js"></script>
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

async function loadDashboard() {
    try {
        const params = new URLSearchParams(location.search);
        const branchId = params.get('branch_id');
        const url = 'api/dashboard.php' + (branchId ? ('?branch_id=' + encodeURIComponent(branchId)) : '');
        const res  = await fetch(url);
        const data = await res.json();
        if (!data.success) return;

        const s = data.stats;
        document.getElementById('statPr').textContent        = Number(s.purchase_requisitions).toLocaleString();
        document.getElementById('statPo').textContent        = Number(s.purchase_orders).toLocaleString();
        document.getElementById('statReceived').textContent  = Number(s.received_items).toLocaleString();
        document.getElementById('statInventory').textContent = Number(s.inventory_items).toLocaleString();

        const f = data.financials;
        document.getElementById('kpiRevenue').textContent = fmtMoney(f.revenue);
        document.getElementById('kpiProfit').textContent  = fmtMoney(f.profit);
        document.getElementById('kpiMargin').textContent  = f.margin_pct + '%';
        document.getElementById('kpiRevenueTrend').innerHTML = trendBadge(f.revenue_change);
        document.getElementById('kpiProfitTrend').innerHTML  = trendBadge(f.profit_change);

        const na = data.needs_attention;
        const naTotal = na.pr_pending + na.stock_pending + na.orders_open + na.low_stock;
        document.getElementById('kpiAttention').textContent = naTotal.toLocaleString();
        renderAttentionList(na);
        renderLowStock(data.low_stock_items);
        renderOrderPipeline(data.order_pipeline);
        renderBranchLeaderboard(data.branch_performance);

        renderSalesChart(data.monthly_sales);
        renderTopProducts(data.top_products);

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
        { key: 'pr_pending',    icon: 'fa-file-signature',      color: 'blue',   label: 'Purchase Requisitions Pending', href: 'purchase_requisitions.php' },
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
