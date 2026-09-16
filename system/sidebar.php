<?php
// sidebar.php — included by every protected page
// Requires: $currentUser, $activePage
$navUser   = $currentUser ?? ['full_name' => 'User', 'role' => 'branch_user', 'username' => '', 'branch_name' => null];
$initials  = strtoupper(substr($navUser['full_name'], 0, 1));
$navAvatar = avatarUrl($navUser);

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
$canReports      = $moduleAccess['reports'] ?? true;
$canCostProfitReports = $moduleAccess['cost_profit_reports'] ?? true;
// System section: settings (user management) is gated by ACCESS_MODULES;
// Access Levels and Audit Log are admin-tier only, same rank rule everywhere
// else in this app uses.
$canSettings     = $moduleAccess['settings'] ?? true;
$isAdminTier     = in_array($navUser['role'], ['super_admin', 'branch_admin'], true);
$canAccessLevels = $navUser['role'] === 'super_admin';

// Greeting for the sidebar illustration. Server time here so the page arrives
// with the right words already in it and nothing flickers; the small script at
// the foot of this file then keeps it honest, because a browser tab left open
// from the morning into the afternoon would otherwise still be saying "Good
// morning" at four o'clock — which is most tabs in an office.
$hour = (int)date('G');
$sbGreeting = $hour < 12 ? 'Good morning!' : ($hour < 18 ? 'Good afternoon!' : 'Good evening!');

// Who the courier greets. First name only, title-cased — the source is
// all-caps and often surname-first ("PIODOS JR, TEOFILO ARANAIDO"), and
// neither of those is how you say hello to somebody.
$sbFirst = trim((string)($navUser['full_name'] ?? ''));
if (str_contains($sbFirst, ',')) {
    $sbFirst = trim(substr($sbFirst, strpos($sbFirst, ',') + 1));
}
$sbFirst = (string)strtok($sbFirst, ' ');
$sbFirst = $sbFirst !== '' ? mb_convert_case($sbFirst, MB_CASE_TITLE, 'UTF-8') : '';
// The bubble is a fixed width, sized for the longest greeting. A first name
// past ten characters would run out of it, and a speech bubble with the name
// spilling over the edge looks worse than one that just says hello — so past
// that length, and when there is no name at all, it greets without one.
$sbHail = ($sbFirst !== '' && mb_strlen($sbFirst) <= 10) ? 'Hi ' . $sbFirst . ',' : 'Hello there,';

?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<nav class="sidebar" id="sidebar">
    <!-- Brand -->
    <a href="dashboard.php" class="sidebar-brand text-decoration-none">
        <div class="brand-icon"><img src="image/execom_icon.png" alt="EXECOM"></div>
        <div>
            <div class="brand-name"><?= e(APP_NAME) ?></div>
            <div class="brand-sub">Logistics System</div>
        </div>
    </a>

    <!-- User -->
    <div class="sidebar-user">
        <div class="su-row">
            <div class="avatar">
                <?php if ($navAvatar): ?>
                    <img src="<?= e($navAvatar) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"
                         onerror="this.replaceWith(document.createTextNode(<?= e(json_encode($initials)) ?>))">
                <?php else: ?>
                    <?= e($initials) ?>
                <?php endif; ?>
                <span class="su-dot" title="Online"></span>
            </div>
            <div class="su-info">
                <div class="u-name"><?= e($navUser['full_name']) ?></div>
                <div class="su-meta">
                    <span class="su-role"><i class="fas fa-user-shield"></i><?= e(roleLabel($navUser['role'])) ?><?= $navUser['branch_name'] ?? null ? ' · ' . e($navUser['branch_name']) : '' ?></span>
                    <span class="su-sep">|</span>
                    <span class="su-online"><span class="su-online-dot"></span>Online</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav">
        <div class="sidebar-section-title">Main</div>

        <a href="dashboard.php" class="sidebar-link <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fas fa-th-large"></i></span> Dashboard
        </a>

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

    <!-- Decorative footer illustration: a delivery being checked off, which is
         what this system is for. Drawn as inline SVG rather than shipped as an
         image because every asset here is vendored with no internet access, and
         a vector stays sharp at any sidebar width without a second @2x file.
         aria-hidden and pointer-events:none — it is decoration, and nothing in
         it is information a screen reader or a click should reach. -->
    <div class="sidebar-art" aria-hidden="true">
      <svg viewBox="0 0 240 220" role="presentation" focusable="false">
        <!-- Foliage. Opaque teal, not a translucent wash: at low alpha over
             this much blue the green disappears and the leaves read as paler
             blue smudges. -->
        <g class="art-leaves">
          <ellipse cx="118" cy="120" rx="104" ry="58" fill="#3b63c9" opacity=".4"/>
          <path d="M203 152c15-17 18-45 5-67-15 17-20 45-5 67z" fill="#14b8a6" opacity=".85"/>
          <path d="M203 152c-17-13-26-41-19-65 17 13 28 41 19 65z" fill="#0d9488" opacity=".8"/>
          <path d="M223 152c11-11 15-31 9-46-11 11-15 31-9 46z" fill="#2dd4bf" opacity=".7"/>
          <path d="M203 152c0-24 2-43 6-58" stroke="#0f766e" stroke-width="1.5" fill="none" opacity=".5"/>
          <path d="M17 152c-11-14-12-36-2-53 11 14 14 36 2 53z" fill="#0d9488" opacity=".75"/>
          <path d="M17 152c13-10 19-30 14-48-12 10-20 30-14 48z" fill="#2dd4bf" opacity=".6"/>
          <path d="M17 152c0-18-1-31-3-42" stroke="#0f766e" stroke-width="1.3" fill="none" opacity=".4"/>
          <circle cx="209" cy="30" r="4.5" fill="#5eead4" opacity=".55"/>
          <circle cx="32" cy="20" r="3" fill="#5eead4" opacity=".5"/>
          <circle cx="191" cy="10" r="2.5" fill="#a7f3d0" opacity=".55"/>
        </g>

        <ellipse cx="122" cy="150" rx="84" ry="10" fill="#0b1c4d" opacity=".28"/>

        <!-- Small parcel, stacked. Drawn first so the large one overlaps it. -->
        <g>
          <rect x="128" y="44" width="58" height="44" rx="2" fill="#e7bd8d"/>
          <rect x="128" y="38" width="58" height="8" rx="2" fill="#d0a068"/>
          <rect x="149" y="38" width="15" height="50" fill="#bf8b55" opacity=".55"/>
          <circle cx="139" cy="66" r="7" fill="#fff" opacity=".55"/>
          <path d="M135.5 63h7v6h-7z" fill="#a3703f" opacity=".85"/>
          <path d="M135.5 65.4h7" stroke="#e7bd8d" stroke-width="1.1"/>
        </g>

        <g>
          <rect x="112" y="86" width="88" height="64" rx="2" fill="#dda971"/>
          <rect x="112" y="78" width="88" height="10" rx="2" fill="#c68b52"/>
          <rect x="147" y="78" width="18" height="72" fill="#b3773f" opacity=".5"/>
          <path d="M112 114h88" stroke="#c68b52" stroke-width="1.4" opacity=".7"/>
        </g>

        <!-- Clipboard, tilted — the inspection itself. -->
        <g transform="rotate(-6 68 90)">
          <rect x="26" y="26" width="82" height="124" rx="9" fill="#e8eef7"/>
          <rect x="33" y="37" width="68" height="104" rx="5" fill="#fff"/>
          <rect x="53" y="18" width="28" height="15" rx="4" fill="#94a3b8"/>
          <rect x="60" y="14" width="14" height="8" rx="3" fill="#cbd5e1"/>
          <g>
            <rect x="41" y="54" width="15" height="15" rx="4" fill="#2563eb"/>
            <path d="M44.6 61.4l2.6 2.7 4.6-5.4" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            <rect x="61" y="57" width="32" height="4" rx="2" fill="#cbd5e1"/>
            <rect x="61" y="64" width="22" height="3.5" rx="1.75" fill="#e2e8f0"/>
          </g>
          <g>
            <rect x="41" y="80" width="15" height="15" rx="4" fill="#2563eb"/>
            <path d="M44.6 87.4l2.6 2.7 4.6-5.4" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            <rect x="61" y="83" width="32" height="4" rx="2" fill="#cbd5e1"/>
            <rect x="61" y="90" width="26" height="3.5" rx="1.75" fill="#e2e8f0"/>
          </g>
          <g>
            <rect x="41" y="106" width="15" height="15" rx="4" fill="#2563eb"/>
            <path d="M44.6 113.4l2.6 2.7 4.6-5.4" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            <rect x="61" y="109" width="28" height="4" rx="2" fill="#cbd5e1"/>
            <rect x="61" y="116" width="20" height="3.5" rx="1.75" fill="#e2e8f0"/>
          </g>
        </g>

        <!-- The lane the walker crosses: a band of ground in front of
             everything else, so they are never lost behind the scenery. -->
        <ellipse cx="120" cy="200" rx="116" ry="26" fill="#2a54b8" opacity=".45"/>

        <!-- ── The greeting, then the handover ─────────────────────────────
             A courier on the right with the delivery, an inspector on the left
             with the clipboard. They greet and shake hands, then the parcel
             crosses to the inspector, waits while it is checked, and goes back.

             The step in and out is what makes the handshake possible at all:
             an arm is 18 units long, so two people standing far enough apart
             for a parcel to visibly travel between them cannot reach each
             other. They close the gap to shake, then open it again to trade —
             which is also just what people do.

             Each figure is a static group at its spot on the ground wrapping an
             animated group that steps; the courier's mirror is a third group
             inside that, since an SVG element carries only one transform. Limbs
             follow the same rule: a static group at the joint around an
             animated group that only rotates, so CSS transform-origin — which
             defaults to an SVG element's own local origin — lands the rotation
             on the shoulder with no arithmetic. -->

        <!-- Inspector, facing right. -->
        <g transform="translate(80,206)"><g class="fig-a">
          <ellipse cx="0" cy="2" rx="12" ry="3.2" fill="#0b1c4d" opacity=".3"/>
          <g transform="translate(-3,-24)">
            <rect x="-3.5" y="0" width="7" height="20" rx="3.5" fill="#152a5e"/>
            <rect x="-5.5" y="16.5" width="11" height="5" rx="2.5" fill="#0b1830"/>
          </g>
          <!-- Outer arm, holding the clipboard down at the side. -->
          <g transform="translate(-8,-42)"><g class="arm-hold">
            <rect x="-2.8" y="0" width="5.6" height="18" rx="2.8" fill="#0d9488"/>
            <g transform="translate(0,15.5)">
              <rect x="-5.5" y="-1" width="11" height="13" rx="1.8" fill="#fff"/>
              <rect x="-2.2" y="-2.8" width="4.4" height="3" rx="1.1" fill="#94a3b8"/>
              <rect x="-3.4" y="3" width="6.8" height="1.5" rx=".75" fill="#cbd5e1"/>
              <rect x="-3.4" y="6" width="5" height="1.5" rx=".75" fill="#e2e8f0"/>
            </g>
          </g></g>
          <g class="body-a">
            <rect x="-10" y="-45" width="20" height="24" rx="7.5" fill="#f8fafc"/>
            <path d="M-10-40.5c0-2.7 1.8-4.5 4.5-4.5h11c2.7 0 4.5 1.8 4.5 4.5v16.5c0 2.7-1.8 4.5-4.5 4.5h-11c-2.7 0-4.5-1.8-4.5-4.5z" fill="#14b8a6"/>
            <path d="M-1.9-45h3.8v28h-3.8z" fill="#f8fafc" opacity=".92"/>
            <circle cx="0" cy="-53.5" r="7.4" fill="#f2c6a0"/>
            <path d="M-7.4-54.5a7.4 7.4 0 0 1 14.8 0c0-4.7-3.3-7.7-7.4-7.7s-7.4 3-7.4 7.7z" fill="#3f2d23"/>
            <rect x="-2.3" y="-47.5" width="4.6" height="3.5" fill="#f2c6a0"/>
          </g>
          <g transform="translate(3,-24)">
            <rect x="-3.5" y="0" width="7" height="20" rx="3.5" fill="#1e3a8a"/>
            <rect x="-5.5" y="16.5" width="11" height="5" rx="2.5" fill="#0f172a"/>
          </g>
          <!-- Inner arm: shakes, then takes the parcel. -->
          <g transform="translate(8,-42)"><g class="arm-a-in">
            <rect x="-2.8" y="0" width="5.6" height="18" rx="2.8" fill="#14b8a6"/>
            <circle cx="0" cy="18" r="3.4" fill="#f2c6a0"/>
          </g></g>
        </g></g>

        <!-- Courier, mirrored so the two face each other. -->
        <g transform="translate(162,206)"><g class="fig-b"><g transform="scale(-1,1)">
          <ellipse cx="0" cy="2" rx="12" ry="3.2" fill="#0b1c4d" opacity=".3"/>
          <g transform="translate(-3,-24)">
            <rect x="-3.5" y="0" width="7" height="20" rx="3.5" fill="#243b53"/>
            <rect x="-5.5" y="16.5" width="11" height="5" rx="2.5" fill="#0b1830"/>
          </g>
          <g transform="translate(-8,-42)"><g class="arm-hold-b">
            <rect x="-2.8" y="0" width="5.6" height="18" rx="2.8" fill="#c2551f"/>
          </g></g>
          <g class="body-b">
            <rect x="-10" y="-45" width="20" height="24" rx="7.5" fill="#ef8a4c"/>
            <path d="M-10-40.5c0-2.7 1.8-4.5 4.5-4.5h11c2.7 0 4.5 1.8 4.5 4.5v4H-10z" fill="#c2551f"/>
            <circle cx="0" cy="-53.5" r="7.4" fill="#e0a878"/>
            <path d="M-7.6-54a7.6 7.6 0 0 1 15.2 0z" fill="#c2410c"/>
            <path d="M-7.6-54h-4.6c-.9 0-1.4.6-1.4 1.4s.5 1.4 1.4 1.4h4.6z" fill="#9a3412"/>
            <rect x="-2.3" y="-47.5" width="4.6" height="3.5" fill="#e0a878"/>
          </g>
          <g transform="translate(3,-24)">
            <rect x="-3.5" y="0" width="7" height="20" rx="3.5" fill="#334155"/>
            <rect x="-5.5" y="16.5" width="11" height="5" rx="2.5" fill="#0f172a"/>
          </g>
          <!-- Inner arm: shakes, then hands the parcel over. Because this whole
               figure is mirrored, the SAME rotation angle as the inspector's arm
               produces the opposite screen direction — which is exactly what
               keeps the two hands clasped through the pumps. -->
          <g transform="translate(8,-42)"><g class="arm-b-in">
            <rect x="-2.8" y="0" width="5.6" height="18" rx="2.8" fill="#ef8a4c"/>
            <circle cx="0" cy="18" r="3.4" fill="#e0a878"/>
          </g></g>
        </g></g></g>

        <!-- The parcel, a sibling of both figures rather than a child of
             either — it belongs to whichever hand holds it, and only an
             independent element can be handed over. Base point is the
             courier's resting hand. -->
        <g transform="translate(143,178)"><g class="parcel">
          <rect x="-7" y="-6" width="14" height="12" rx="1.5" fill="#e7bd8d"/>
          <rect x="-7" y="-8" width="14" height="3" rx="1" fill="#d0a068"/>
          <rect x="-1.6" y="-8" width="3.2" height="14" fill="#bf8b55" opacity=".6"/>
        </g></g>

        <!-- ── The conversation ────────────────────────────────────────────
             Five lines, timed against the same 24s clock as the action, so
             what is said always matches what is being done: they greet while
             apart, talk over the delivery while shaking hands, and the parcel
             is checked and thanked for while it is actually changing hands.
             Only one bubble is ever visible — two at once reads as people
             talking over each other rather than as a conversation.

             All five share one bubble outline, sized for the longest line
             ("Good afternoon!" / "For inspection?", both 15 characters), so no
             bubble changes shape as the day goes on or as the chat moves on.
             Each is a static group at its spot wrapping an animated group,
             because a CSS transform REPLACES an element's transform attribute
             rather than adding to it — animating a bubble that was placed by
             attribute sends it to the origin of the drawing. -->

        <!-- 1. The courier arrives. The second line is the only part that
                changes with the time of day. -->
        <g transform="translate(162,122)"><g class="say-1">
          <path d="M-33-19h66a5 5 0 0 1 5 5v20a5 5 0 0 1-5 5h-25l-5 7-3-7h-33a5 5 0 0 1-5-5v-20a5 5 0 0 1 5-5z"
                fill="#fff7ed" stroke="#c2410c" stroke-width="1.5" stroke-linejoin="round"/>
          <text text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="8.5" font-weight="700" fill="#9a3412">
            <tspan x="0" y="-5"><?= e($sbHail) ?></tspan>
            <tspan x="0" y="6" id="sbGreeting"><?= e($sbGreeting) ?></tspan>
          </text>
        </g></g>

        <!-- 2. Said mid-handshake, so it sits over where the inspector has
                stepped TO, not over where they started. -->
        <g transform="translate(95,122)"><g class="say-2">
          <path d="M-33-19h66a5 5 0 0 1 5 5v20a5 5 0 0 1-5 5h-25l-5 7-3-7h-33a5 5 0 0 1-5-5v-20a5 5 0 0 1 5-5z"
                fill="#eff6ff" stroke="#1d4ed8" stroke-width="1.5" stroke-linejoin="round"/>
          <text text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="8.5" font-weight="700" fill="#1e3a8a">
            <tspan x="0" y="-5">Hello!</tspan>
            <tspan x="0" y="6">For inspection?</tspan>
          </text>
        </g></g>

        <!-- 3. Just before the parcel crosses. -->
        <g transform="translate(162,122)"><g class="say-3">
          <path d="M-33-19h66a5 5 0 0 1 5 5v20a5 5 0 0 1-5 5h-25l-5 7-3-7h-33a5 5 0 0 1-5-5v-20a5 5 0 0 1 5-5z"
                fill="#fff7ed" stroke="#c2410c" stroke-width="1.5" stroke-linejoin="round"/>
          <text text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="8.5" font-weight="700" fill="#9a3412">
            <tspan x="0" y="-5">Yes po &#8212;</tspan>
            <tspan x="0" y="6">one parcel.</tspan>
          </text>
        </g></g>

        <!-- 4. While the inspector is holding it. -->
        <g transform="translate(80,122)"><g class="say-4">
          <path d="M-33-19h66a5 5 0 0 1 5 5v20a5 5 0 0 1-5 5h-25l-5 7-3-7h-33a5 5 0 0 1-5-5v-20a5 5 0 0 1 5-5z"
                fill="#eff6ff" stroke="#1d4ed8" stroke-width="1.5" stroke-linejoin="round"/>
          <text text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="8.5" font-weight="700" fill="#1e3a8a">
            <tspan x="0" y="-5">Let me</tspan>
            <tspan x="0" y="6">check it&#8230;</tspan>
          </text>
        </g></g>

        <!-- 5. As it goes back, which closes the loop. -->
        <g transform="translate(162,128)"><g class="say-5">
          <path d="M-30-12h60a5 5 0 0 1 5 5v13a5 5 0 0 1-5 5h-22l-5 7-3-7h-30a5 5 0 0 1-5-5v-13a5 5 0 0 1 5-5z"
                fill="#fff7ed" stroke="#c2410c" stroke-width="1.5" stroke-linejoin="round"/>
          <text text-anchor="middle" font-family="Segoe UI, system-ui, sans-serif" font-size="8.5" font-weight="700" fill="#9a3412">
            <tspan x="0" y="1">Thank you po!</tspan>
          </text>
        </g></g>
      </svg>
    </div>
</nav>


<!-- Connection watchdog. Loaded here because sidebar.php is included by
     exactly the pages behind requireAuth() and by none of the public ones,
     so every protected screen gets it and login.php does not. -->
<!-- The greeting in the sidebar illustration is rendered by PHP from server
     time, so the page arrives correct and nothing flickers. This keeps it that
     way: an office tab opened at nine is still open at four, and would
     otherwise be wishing everyone a good morning all afternoon. Client time
     from here on, which is the clock the person reading it is actually on. -->
<script>
(function () {
    var el = document.getElementById('sbGreeting');
    if (!el) return;
    function greet() {
        var h = new Date().getHours();
        el.textContent = h < 12 ? 'Good morning!' : (h < 18 ? 'Good afternoon!' : 'Good evening!');
    }
    greet();
    setInterval(greet, 300000);   // five minutes; it only ever changes twice a day
})();
</script>

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
