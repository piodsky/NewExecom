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
                    <div class="panel-header"><div class="panel-title">Purchase Order Summary (Year to Date)</div></div>
                    <div class="panel-body">
                        <div class="chart-box" style="height:260px;"><canvas id="poChart"></canvas></div>
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

let poChartInstance = null;
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

        renderPoChart(data.monthly_po);
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

function renderPoChart(series) {
    const ctx = document.getElementById('poChart');
    if (!ctx) return;
    const labels = series.map(r => r.label);
    const counts = series.map(r => r.count);
    if (poChartInstance) poChartInstance.destroy();
    poChartInstance = new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Purchase Orders', data: counts, backgroundColor: '#2563eb', borderRadius: 6, maxBarThickness: 36 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } },
        },
    });
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
