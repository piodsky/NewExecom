<?php
// topnav.php — the horizontal top-nav shell, included by every page.
// Requires: $currentUser, $activePage. Same per-module access gating as
// before — same ACCESS_MODULES keys.
//
// Phase 7: framework swapped from Tabler to Argon Dashboard (recolored navy
// locally, see argon-dashboard-master/ + system/vendor/argon/). Nav groups
// are now data-driven ($navGroups below) so the same list powers both the
// Level-1 dropdown (works from any page) and the new Level-2 context strip
// (the current group's own pages, shown as a slim bar under the main
// header — hidden on Dashboard, same as the rest of the app had no
// per-group sub-bar before this phase).
$navUser = $currentUser ?? ['full_name' => 'User', 'role' => 'branch_user', 'username' => '', 'branch_name' => null];

$moduleAccess    = getEffectiveModuleAccess($navUser['id'] ?? null, $navUser['role']);
$canPoInternal   = $moduleAccess['po_internal'] ?? true;
$canReceiving    = $moduleAccess['receiving_reports'] ?? true;
$canPayables     = $moduleAccess['payables'] ?? true;
$canInventory    = $moduleAccess['inventory'] ?? true;
$canWarehouse    = $moduleAccess['warehouse'] ?? true;
$canStockTransfer = $moduleAccess['stock_transfer'] ?? true;
$canIssuance      = $moduleAccess['issuance'] ?? true;
$canOutgoingRfq   = $moduleAccess['outgoing_rfq'] ?? true;
$canOutgoingQuotations = $moduleAccess['outgoing_quotations'] ?? true;
$canPoOutgoing    = $moduleAccess['po_outgoing'] ?? true;
$canDeliveryReports = $moduleAccess['delivery_reports'] ?? true;
$canBilling      = $moduleAccess['billing'] ?? true;
$canCollections  = $moduleAccess['collections'] ?? true;
$canJobOrders    = $moduleAccess['job_orders'] ?? true;
$canSuppliers    = $moduleAccess['suppliers'] ?? true;
$canClients      = $moduleAccess['clients'] ?? true;
$canItems        = $moduleAccess['items'] ?? true;
$canSalesmen     = $moduleAccess['salesmen'] ?? true;
$canBranches     = $navUser['role'] === 'super_admin';
$isSuperAdmin    = $navUser['role'] === 'super_admin';
$canReports      = $moduleAccess['reports'] ?? true;
$canCostProfitReports = $moduleAccess['cost_profit_reports'] ?? true;
$canOrderTracking = $moduleAccess['order_tracking'] ?? true;
$canSystemGuide  = $moduleAccess['system_guide'] ?? true;
$canSettings     = $moduleAccess['settings'] ?? true;
$isAdminTier     = in_array($navUser['role'], ['super_admin', 'branch_admin'], true);
$canAccessLevels = $navUser['role'] === 'super_admin';

// Each entry: [page, icon, label, visible?]. One list per group drives both
// the Level-1 dropdown and the Level-2 strip — no more keeping two copies
// of the same link set in sync by hand.
$navGroups = [
    'master_data' => [
        'label' => 'Master Data', 'icon' => 'fa-database',
        'links' => [
            ['branches.php', 'fa-store', 'Branches', $canBranches],
            ['suppliers.php', 'fa-truck-field', 'Suppliers', $canSuppliers],
            ['clients.php', 'fa-building-user', 'Clients', $canClients],
            ['items.php', 'fa-boxes-stacked', 'Item Catalog', $canItems],
            ['salesmen.php', 'fa-user-tie', 'Salesman List', $canSalesmen],
        ],
    ],
    'procurement' => [
        'label' => 'Procurement', 'icon' => 'fa-cart-shopping',
        'links' => [
            ['po_internal.php', 'fa-file-invoice-dollar', 'Purchase Order (Internal)', $canPoInternal],
            ['receiving_reports.php', 'fa-truck-ramp-box', 'Receiving Reports', $canReceiving],
            ['payables.php', 'fa-money-check-dollar', 'Payables (AP)', $canPayables],
            ['inventory.php', 'fa-warehouse', 'Inventory', $canInventory],
            ['warehouse.php', 'fa-map-location-dot', 'Warehouse Map', $canWarehouse],
        ],
    ],
    'branch_ops' => [
        'label' => 'Branch Ops', 'icon' => 'fa-dolly',
        'links' => [
            ['stock_transfers.php', 'fa-dolly', 'Stock Transfer', $canStockTransfer],
            ['issuances.php', 'fa-cash-register', 'Issuance (POS)', $canIssuance],
        ],
    ],
    'outgoing_sales' => [
        'label' => 'Outgoing Sales', 'icon' => 'fa-truck',
        'links' => [
            ['outgoing_rfq.php', 'fa-inbox', 'RFQ (Outgoing)', $canOutgoingRfq],
            ['outgoing_quotations.php', 'fa-file-invoice-dollar', 'Quotations (Outgoing)', $canOutgoingQuotations],
            ['po_outgoing.php', 'fa-file-contract', 'Purchase Order (Outgoing)', $canPoOutgoing],
            ['delivery_reports.php', 'fa-truck', 'Delivery Reports', $canDeliveryReports],
        ],
    ],
    'service_billing' => [
        'label' => 'Service & Billing', 'icon' => 'fa-screwdriver-wrench',
        'links' => [
            ['job_orders.php', 'fa-screwdriver-wrench', 'Job Orders', $canJobOrders],
            ['billing.php', 'fa-file-invoice-dollar', 'Billing', $canBilling],
            ['collections.php', 'fa-hand-holding-dollar', 'Collections', $canCollections],
        ],
    ],
    'reports' => [
        'label' => 'Reports', 'icon' => 'fa-chart-bar',
        'links' => [
            ['order_tracking.php', 'fa-route', 'Order Tracking', $canOrderTracking],
            ['cost_profit_reports.php', 'fa-money-bill-trend-up', 'Cost/Profit Reports', $canCostProfitReports],
            ['reports.php', 'fa-chart-bar', 'Reports', $canReports],
        ],
    ],
    'system' => [
        'label' => 'System', 'icon' => 'fa-gear',
        'links' => [
            ['settings.php', 'fa-users-gear', 'Users', $canSettings],
            ['access_levels.php', 'fa-user-shield', 'Access Levels', $canAccessLevels],
            ['audit_logs.php', 'fa-clock-rotate-left', 'Activity Log', $isAdminTier],
            ['system_guide.php', 'fa-diagram-project', 'System Guide', $canSystemGuide],
        ],
    ],
];

// Which group (if any) owns the current page — drives the Level-2 strip.
$activeGroupKey = null;
foreach ($navGroups as $key => $group) {
    foreach ($group['links'] as [$page, , , $visible]) {
        if ($visible && $activePage === basename($page, '.php')) { $activeGroupKey = $key; break 2; }
    }
}

// navbar-search-link is a pure marker class (no CSS attached) so topbar.php's
// "quick nav search" can find these links too.
function navLink($href, $icon, $label, $active) {
    $cls = 'dropdown-item navbar-search-link' . ($active ? ' active' : '');
    echo '<a href="' . e($href) . '" class="' . $cls . '"><i class="fas ' . e($icon) . ' me-2"></i>' . e($label) . '</a>';
}
?>
<!-- Utility row: branch picker (left) + search/bell/avatar (right) — its own
     dedicated row above the main nav row, so the primary nav links never
     have to fight the action icons for space. -->
<div class="topnav-utility d-print-none">
    <div class="container-xl topnav-utility-inner">
        <div class="topnav-branch">
            <?php if ($isSuperAdmin): ?>
            <label class="topnav-branch-pill mb-0">
                <i class="fas fa-location-dot pin"></i>
                <select id="topnavBranchSelect" onchange="topnavBranchChange(this.value)">
                    <option value="">All Branches</option>
                </select>
                <i class="fas fa-chevron-down chev"></i>
            </label>
            <?php else: ?>
            <span class="text-muted small"><i class="fas fa-location-dot me-1"></i><?= e($navUser['branch_name'] ?? 'Unassigned') ?></span>
            <?php endif; ?>
        </div>

        <div class="topnav-actions">
            <?php include 'topbar.php'; ?>
        </div>
    </div>
</div>

<header class="navbar navbar-expand-md d-print-none topnav-light">
  <div class="container-xl">
    <a href="dashboard.php" class="navbar-brand pe-0 pe-md-3 d-flex align-items-center" style="text-decoration:none;">
        <img src="image/execom_icon.png" alt="" style="height:34px;width:34px;border-radius:8px;margin-right:.65rem;">
        <span class="d-none d-sm-block" style="line-height:1.15;">
            <span style="display:block;font-weight:800;font-size:1.02rem;color:var(--text);"><?= e(APP_NAME) ?></span>
            <span style="display:block;font-size:.66rem;color:var(--muted);font-weight:600;">Logistics System</span>
        </span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnavCollapse">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="topnavCollapse">
        <ul class="navbar-nav topnav-links">
            <li class="nav-item">
                <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
                    <span class="nav-link-icon"><i class="fas fa-th-large"></i></span>
                    <span class="nav-link-title">Dashboard</span>
                </a>
            </li>

            <?php foreach ($navGroups as $key => $group):
                $visibleLinks = array_values(array_filter($group['links'], fn($l) => $l[3]));
                if (!$visibleLinks) continue;
            ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle <?= $activeGroupKey === $key ? 'active' : '' ?>" data-bs-toggle="dropdown" href="#" role="button">
                    <span class="nav-link-icon"><i class="fas <?= e($group['icon']) ?>"></i></span>
                    <span class="nav-link-title"><?= e($group['label']) ?></span>
                </a>
                <div class="dropdown-menu">
                    <?php foreach ($visibleLinks as [$page, $icon, $label]) navLink($page, $icon, $label, $activePage === basename($page, '.php')); ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
  </div>
</header>

<?php if ($activeGroupKey !== null):
    $activeLinks = array_values(array_filter($navGroups[$activeGroupKey]['links'], fn($l) => $l[3]));
?>
<nav class="subnav d-print-none">
    <div class="container-xl subnav-inner">
        <span class="subnav-label"><i class="fas <?= e($navGroups[$activeGroupKey]['icon']) ?> me-2"></i><?= e($navGroups[$activeGroupKey]['label']) ?></span>
        <div class="subnav-links">
            <?php foreach ($activeLinks as [$page, $icon, $label]): ?>
            <a href="<?= e($page) ?>" class="subnav-link <?= $activePage === basename($page, '.php') ? 'active' : '' ?>">
                <i class="fas <?= e($icon) ?> me-1"></i><?= e($label) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
<?php endif; ?>

<?php if ($isSuperAdmin): ?>
<script>
(function () {
    const params = new URLSearchParams(location.search);
    const current = params.get('branch_id') || '';
    fetch('api/branches.php').then(r => r.json()).then(data => {
        if (!data.success) return;
        const sel = document.getElementById('topnavBranchSelect');
        if (!sel) return;
        data.branches.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.name;
            if (String(b.id) === current) opt.selected = true;
            sel.appendChild(opt);
        });
    }).catch(() => {});
})();
function topnavBranchChange(branchId) {
    window.location.href = branchId ? `dashboard.php?branch_id=${encodeURIComponent(branchId)}` : 'dashboard.php';
}
</script>
<?php endif; ?>

<script src="js/connection.js"></script>
<script src="js/quick-inventory-search.js"></script>
<script>
function confirmLogout() {
    if (confirm('Sign out of <?= e(APP_NAME) ?>?')) window.location.href = 'logout.php';
}
</script>
