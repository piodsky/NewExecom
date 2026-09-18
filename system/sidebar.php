<?php
// sidebar.php — included by every protected page
// Requires: $currentUser, $activePage
$navUser   = $currentUser ?? ['full_name' => 'User', 'role' => 'branch_user', 'username' => '', 'branch_name' => null];

$moduleAccess    = getEffectiveModuleAccess($navUser['id'] ?? null, $navUser['role']);
$canPr           = $moduleAccess['purchase_requisition'] ?? true;
$canInternalRfq  = $moduleAccess['internal_rfq'] ?? true;
$canPoInternal   = $moduleAccess['po_internal'] ?? true;
$canReceiving    = $moduleAccess['receiving_reports'] ?? true;
$canInventory    = $moduleAccess['inventory'] ?? true;
$canStockTransfer = $moduleAccess['stock_transfer'] ?? true;
$canIssuance      = $moduleAccess['issuance'] ?? true;
$canOutgoingRfq   = $moduleAccess['outgoing_rfq'] ?? true;
$canOutgoingQuotations = $moduleAccess['outgoing_quotations'] ?? true;
$canPoOutgoing    = $moduleAccess['po_outgoing'] ?? true;
$canDeliveryReports = $moduleAccess['delivery_reports'] ?? true;
$canSuppliers    = $moduleAccess['suppliers'] ?? true;
$canClients      = $moduleAccess['clients'] ?? true;
$canItems        = $moduleAccess['items'] ?? true;
$canBranches     = $navUser['role'] === 'super_admin';
$isSuperAdmin    = $navUser['role'] === 'super_admin';
$canReports      = $moduleAccess['reports'] ?? true;
$canCostProfitReports = $moduleAccess['cost_profit_reports'] ?? true;
$canSystemGuide  = $moduleAccess['system_guide'] ?? true;
// System section: settings (user management) is gated by ACCESS_MODULES;
// Access Levels and Audit Log are admin-tier only, same rank rule everywhere
// else in this app uses.
$canSettings     = $moduleAccess['settings'] ?? true;
$isAdminTier     = in_array($navUser['role'], ['super_admin', 'branch_admin'], true);
$canAccessLevels = $navUser['role'] === 'super_admin';
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<nav class="sidebar" id="sidebar">
    <!-- Brand -->
    <a href="dashboard.php" class="sidebar-brand text-decoration-none">
        <div class="brand-icon"><img src="image/execom_icon.png" alt="EXECOM"></div>
        <div>
            <div class="brand-name"><?= e(APP_NAME) ?></div>
            <div class="brand-sub">Logistics &amp; Procurement</div>
        </div>
    </a>

    <!-- Navigation -->
    <div class="sidebar-nav">
        <div class="sidebar-section-title">Main</div>

        <a href="dashboard.php" class="sidebar-link <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-th-large"></i></span> Dashboard
        </a>

        <?php if ($canSystemGuide): ?>
        <a href="system_guide.php" class="sidebar-link <?= ($activePage === 'system_guide') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-diagram-project"></i></span> System Guide
        </a>
        <?php endif; ?>

        <?php if ($canBranches || $canSuppliers || $canClients || $canItems): ?>
        <div class="sidebar-section-title mt-2">Master Data</div>

        <?php if ($canBranches): ?>
        <a href="branches.php" class="sidebar-link <?= ($activePage === 'branches') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-store"></i></span> Branches
        </a>
        <?php endif; ?>

        <?php if ($canSuppliers): ?>
        <a href="suppliers.php" class="sidebar-link <?= ($activePage === 'suppliers') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-truck-field"></i></span> Suppliers
        </a>
        <?php endif; ?>

        <?php if ($canClients): ?>
        <a href="clients.php" class="sidebar-link <?= ($activePage === 'clients') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-building-user"></i></span> Clients
        </a>
        <?php endif; ?>

        <?php if ($canItems): ?>
        <a href="items.php" class="sidebar-link <?= ($activePage === 'items') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-boxes-stacked"></i></span> Item Catalog
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($canPr || $canInternalRfq || $canPoInternal || $canReceiving || $canInventory): ?>
        <div class="sidebar-section-title mt-2">Internal Procurement</div>

        <?php if ($canPr): ?>
        <a href="purchase_requisitions.php" class="sidebar-link <?= ($activePage === 'purchase_requisitions') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-file-signature"></i></span> Purchase Requisition
        </a>
        <?php endif; ?>

        <?php if ($canInternalRfq): ?>
        <a href="internal_rfq.php" class="sidebar-link <?= ($activePage === 'internal_rfq') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-file-invoice"></i></span> RFQ (Internal)
        </a>
        <?php endif; ?>

        <?php if ($canPoInternal): ?>
        <a href="po_internal.php" class="sidebar-link <?= ($activePage === 'po_internal') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-file-invoice-dollar"></i></span> Purchase Order (Internal)
        </a>
        <?php endif; ?>

        <?php if ($canReceiving): ?>
        <a href="receiving_reports.php" class="sidebar-link <?= ($activePage === 'receiving_reports') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-truck-ramp-box"></i></span> Receiving Reports
        </a>
        <?php endif; ?>

        <?php if ($canInventory): ?>
        <a href="inventory.php" class="sidebar-link <?= ($activePage === 'inventory') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-warehouse"></i></span> Inventory
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($canStockTransfer || $canIssuance): ?>
        <div class="sidebar-section-title mt-2">Branch Operations</div>

        <?php if ($canStockTransfer): ?>
        <a href="stock_transfers.php" class="sidebar-link <?= ($activePage === 'stock_transfers') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-dolly"></i></span> Stock Transfer
        </a>
        <?php endif; ?>

        <?php if ($canIssuance): ?>
        <a href="issuances.php" class="sidebar-link <?= ($activePage === 'issuances') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-cash-register"></i></span> Issuance (POS)
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($canOutgoingRfq || $canOutgoingQuotations || $canPoOutgoing || $canDeliveryReports): ?>
        <div class="sidebar-section-title mt-2">Outgoing Sales</div>

        <?php if ($canOutgoingRfq): ?>
        <a href="outgoing_rfq.php" class="sidebar-link <?= ($activePage === 'outgoing_rfq') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-inbox"></i></span> RFQ (Outgoing)
        </a>
        <?php endif; ?>

        <?php if ($canOutgoingQuotations): ?>
        <a href="outgoing_quotations.php" class="sidebar-link <?= ($activePage === 'outgoing_quotations') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-file-invoice-dollar"></i></span> Quotations (Outgoing)
        </a>
        <?php endif; ?>

        <?php if ($canPoOutgoing): ?>
        <a href="po_outgoing.php" class="sidebar-link <?= ($activePage === 'po_outgoing') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-file-contract"></i></span> Purchase Order (Outgoing)
        </a>
        <?php endif; ?>

        <?php if ($canDeliveryReports): ?>
        <a href="delivery_reports.php" class="sidebar-link <?= ($activePage === 'delivery_reports') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-truck"></i></span> Delivery Reports
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($canReports || $canCostProfitReports): ?>
        <div class="sidebar-section-title mt-2">Reports</div>

        <?php if ($canCostProfitReports): ?>
        <a href="cost_profit_reports.php" class="sidebar-link <?= ($activePage === 'cost_profit_reports') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-money-bill-trend-up"></i></span> Cost/Profit Reports
        </a>
        <?php endif; ?>

        <?php if ($canReports): ?>
        <a href="reports.php" class="sidebar-link <?= ($activePage === 'reports') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($canSettings || $canAccessLevels || $isAdminTier): ?>
        <div class="sidebar-section-title mt-2">System</div>

        <?php if ($canSettings): ?>
        <a href="settings.php" class="sidebar-link <?= ($activePage === 'settings') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-users-gear"></i></span> Users
        </a>
        <?php endif; ?>

        <?php if ($canAccessLevels): ?>
        <a href="access_levels.php" class="sidebar-link <?= ($activePage === 'access_levels') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-user-shield"></i></span> Access Levels
        </a>
        <?php endif; ?>

        <?php if ($isAdminTier): ?>
        <a href="audit_logs.php" class="sidebar-link <?= ($activePage === 'audit_logs') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-clock-rotate-left"></i></span> Activity Log
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Branch indicator, pinned at the foot of the sidebar. super_admin gets a
         real switcher (jumps the Dashboard to any branch's numbers); a
         branch-scoped account just sees its own branch named, since it has
         nowhere else to go. -->
    <div class="sidebar-branch">
        <div class="sidebar-branch-label">Branch</div>
        <?php if ($isSuperAdmin): ?>
        <div class="sidebar-branch-pill">
            <i class="fas fa-location-dot pin"></i>
            <select id="sidebarBranchSelect" onchange="sidebarBranchChange(this.value)">
                <option value="">All Branches</option>
            </select>
            <i class="fas fa-chevron-down chev"></i>
        </div>
        <?php else: ?>
        <div class="sidebar-branch-pill">
            <i class="fas fa-location-dot pin"></i>
            <span class="sb-name"><?= e($navUser['branch_name'] ?? 'Unassigned') ?></span>
        </div>
        <?php endif; ?>
    </div>
</nav>

<?php if ($isSuperAdmin): ?>
<script>
// Populates the sidebar's branch switcher and keeps it in sync with
// ?branch_id= on the Dashboard. Picking a branch elsewhere in the app just
// jumps to that branch's dashboard, since only the Dashboard's stats are
// branch-filterable today.
(function () {
    const params = new URLSearchParams(location.search);
    const current = params.get('branch_id') || '';
    fetch('api/branches.php').then(r => r.json()).then(data => {
        if (!data.success) return;
        const sel = document.getElementById('sidebarBranchSelect');
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
function sidebarBranchChange(branchId) {
    const base = 'dashboard.php';
    window.location.href = branchId ? `${base}?branch_id=${encodeURIComponent(branchId)}` : base;
}
</script>
<?php endif; ?>

<!-- Connection watchdog. Loaded here because sidebar.php is included by
     exactly the pages behind requireAuth() and by none of the public ones,
     so every protected screen gets it and login.php does not. -->
<script src="js/connection.js"></script>
<script>
function confirmLogout() {
    if (confirm('Sign out of <?= e(APP_NAME) ?>?')) window.location.href = 'logout.php';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
}
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
</script>
