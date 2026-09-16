<?php
require_once 'config.php';
requireAuth();
$currentUser = getCurrentUser();
$activePage  = 'dashboard';
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
                <div class="page-breadcrumb">Home <i class="fas fa-chevron-right"></i> Dashboard</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php include 'topbar.php'; ?>
        </div>
    </div>

    <div class="content-area">
        <div class="row g-3 mb-3" id="statCards">
            <div class="col-6 col-lg-3">
                <div class="stat-card blue">
                    <div class="stat-icon"><i class="fas fa-users-gear"></i></div>
                    <div class="stat-value" id="statActiveUsers">—</div>
                    <div class="stat-label">Active Users<?= $currentUser['branch_id'] ? ' (this branch)' : '' ?></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card teal">
                    <div class="stat-icon"><i class="fas fa-store"></i></div>
                    <div class="stat-value" id="statBranches">—</div>
                    <div class="stat-label">Branches</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card amber">
                    <div class="stat-icon"><i class="fas fa-truck-field"></i></div>
                    <div class="stat-value" id="statSuppliers">—</div>
                    <div class="stat-label">Active Suppliers</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card purple">
                    <div class="stat-icon"><i class="fas fa-building-user"></i></div>
                    <div class="stat-value" id="statClients">—</div>
                    <div class="stat-label">Active Clients</div>
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
<script>
async function loadDashboard() {
    try {
        const res  = await fetch('api/dashboard.php');
        const data = await res.json();
        if (!data.success) return;

        const s = data.stats;
        document.getElementById('statActiveUsers').textContent = s.active_users.toLocaleString();
        document.getElementById('statBranches').textContent    = s.branches.toLocaleString();
        document.getElementById('statSuppliers').textContent   = s.suppliers.toLocaleString();
        document.getElementById('statClients').textContent     = s.clients.toLocaleString();

        const feed = document.getElementById('recentActivity');
        if (!data.recent_activity.length) {
            feed.innerHTML = '<div class="text-muted small">No activity yet.</div>';
            return;
        }
        feed.innerHTML = data.recent_activity.map(r => `
            <div class="txn-item">
                <div class="txn-icon"><i class="fas fa-clock-rotate-left"></i></div>
                <div>
                    <div class="txn-title">${escapeHtml(r.action)} <span class="text-muted">— ${escapeHtml(r.module || '')}</span></div>
                    <div class="txn-meta">${escapeHtml(r.username || 'system')} · ${new Date(r.created_at).toLocaleString()}</div>
                </div>
            </div>`).join('');
    } catch (err) {
        console.error(err);
    }
}
function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
loadDashboard();
</script>
<?php include 'profile_modal.php'; ?>
</body>
</html>
