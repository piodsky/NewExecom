<?php
// Dashboard stat cards + charts + recent activity feed. Branch-scoped: a
// branch_admin/branch_user sees only their own branch's numbers; super_admin
// sees the whole company by default, or one branch via ?branch_id= (see
// getUserBranchScope() in config.php — a branch-scoped account's own scope
// always wins over anything passed in the query string).
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$user  = getCurrentUser();
$scope = getUserBranchScope($user);
if ($scope === null && isset($_GET['branch_id']) && ctype_digit((string)$_GET['branch_id'])) {
    $scope = (int)$_GET['branch_id'];
}
$db = getDB();

// ── KPI cards ──────────────────────────────────────────────────────────
$prCountSql = 'SELECT COUNT(*) FROM pr_requests WHERE is_deleted = 0';
$prParams   = [];
if ($scope !== null) { $prCountSql .= ' AND branch_id = ?'; $prParams[] = $scope; }
$stmt = $db->prepare($prCountSql);
$stmt->execute($prParams);
$prCount = (int)$stmt->fetchColumn();

$poInternalSql = 'SELECT COUNT(*) FROM po_internal WHERE is_deleted = 0';
$poOutgoingSql = 'SELECT COUNT(*) FROM po_outgoing WHERE is_deleted = 0';
$poParams = [];
if ($scope !== null) {
    $poInternalSql .= ' AND branch_id = ?';
    $poOutgoingSql .= ' AND branch_id = ?';
    $poParams[] = $scope;
}
$stmt = $db->prepare($poInternalSql); $stmt->execute($poParams); $poInternalCount = (int)$stmt->fetchColumn();
$stmt = $db->prepare($poOutgoingSql); $stmt->execute($poParams); $poOutgoingCount = (int)$stmt->fetchColumn();
$poCount = $poInternalCount + $poOutgoingCount;

$receivedSql = 'SELECT COALESCE(SUM(rri.quantity), 0)
                FROM receiving_report_items rri
                JOIN receiving_reports rr ON rr.rr_number = rri.rr_number
                WHERE rri.is_deleted = 0 AND rr.is_deleted = 0';
$receivedParams = [];
if ($scope !== null) { $receivedSql .= ' AND rr.branch_id = ?'; $receivedParams[] = $scope; }
$stmt = $db->prepare($receivedSql);
$stmt->execute($receivedParams);
$receivedItems = (float)$stmt->fetchColumn();

$unitsSql = "SELECT COUNT(*) FROM inventory_units WHERE is_deleted = 0 AND status = 'in_stock'";
$stockSql = 'SELECT COALESCE(SUM(quantity_on_hand), 0) FROM inventory_stock';
$unitsParams = []; $stockParams = [];
if ($scope !== null) {
    $unitsSql .= ' AND branch_id = ?'; $unitsParams[] = $scope;
    $stockSql .= ' WHERE branch_id = ?'; $stockParams[] = $scope;
}
$stmt = $db->prepare($unitsSql); $stmt->execute($unitsParams); $unitCount = (int)$stmt->fetchColumn();
$stmt = $db->prepare($stockSql); $stmt->execute($stockParams); $bulkQty = (float)$stmt->fetchColumn();
$inventoryItems = $unitCount + $bulkQty;

// ── Financial totals for an arbitrary date range — same revenue/cost match
// used by Cost/Profit Reports (api/cost_profit_reports.php): revenue is what
// Issuance actually sold a line for, cost is the serialized unit's own cost
// or, for a bulk item, items.default_cost as a standard-cost estimate. ──
function dashboardFinancials(PDO $db, string $from, string $to, ?int $scope): array {
    $where  = 'ii.is_deleted = 0 AND iss.is_deleted = 0 AND iss.date_created BETWEEN ? AND ?';
    $params = [$from, $to];
    if ($scope !== null) { $where .= ' AND iss.branch_id = ?'; $params[] = $scope; }
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(ii.total_price),0) AS revenue,
                COALESCE(SUM(COALESCE(iu.unit_cost, it.default_cost, 0) * ii.quantity),0) AS cost
         FROM issuance_items ii
         JOIN issuances iss ON iss.issuance_number = ii.issuance_number
         LEFT JOIN inventory_units iu ON iu.id = ii.inventory_unit_id
         LEFT JOIN items it ON it.id = COALESCE(ii.item_id, iu.item_id)
         WHERE $where"
    );
    $stmt->execute($params);
    $row     = $stmt->fetch();
    $revenue = (float)$row['revenue'];
    $cost    = (float)$row['cost'];
    $profit  = $revenue - $cost;
    return [
        'revenue'    => $revenue,
        'cost'       => $cost,
        'profit'     => $profit,
        'margin_pct' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0.0,
    ];
}
function dashboardPctChange(float $cur, float $prev): ?float {
    if (abs($prev) < 0.005) { return $cur > 0 ? null : 0.0; } // null = "new" (no prior baseline)
    return round(($cur - $prev) / abs($prev) * 100, 1);
}

// This-month-to-date vs the same number of days into last month, so an
// early-month glance isn't comparing a partial month against a full one.
$curFrom = new DateTime(date('Y-m-01 00:00:00'));
$curTo   = new DateTime();
$prevFrom = (clone $curFrom)->modify('-1 month');
$prevLast = (clone $curFrom)->modify('-1 day')->setTime(23, 59, 59);
$prevTo   = (clone $prevFrom)->modify('+' . $curFrom->diff($curTo)->days . ' days')->setTime(23, 59, 59);
if ($prevTo > $prevLast) { $prevTo = $prevLast; }

$financials = dashboardFinancials($db, $curFrom->format('Y-m-d H:i:s'), $curTo->format('Y-m-d H:i:s'), $scope);
$financialsPrev = dashboardFinancials($db, $prevFrom->format('Y-m-d H:i:s'), $prevTo->format('Y-m-d H:i:s'), $scope);
$financials['revenue_change'] = dashboardPctChange($financials['revenue'], $financialsPrev['revenue']);
$financials['profit_change']  = dashboardPctChange($financials['profit'], $financialsPrev['profit']);

// ── Revenue vs Cost — last 6 months (current month included, partial) ──
$monthlySales = [];
for ($i = 5; $i >= 0; $i--) {
    $mFrom = (clone $curFrom)->modify("-{$i} months");
    $mTo   = $i === 0 ? $curTo : (clone $mFrom)->modify('+1 month')->modify('-1 day')->setTime(23, 59, 59);
    $f = dashboardFinancials($db, $mFrom->format('Y-m-d H:i:s'), $mTo->format('Y-m-d H:i:s'), $scope);
    $monthlySales[] = ['label' => $mFrom->format('M'), 'revenue' => $f['revenue'], 'cost' => $f['cost']];
}

// ── Branch performance — revenue this month, every active branch (even at
// zero) so an underperforming branch is visible, not just missing. Narrows
// to one row for a branch-scoped user, same as Cost/Profit Reports. ──
$bpSql = "SELECT b.id AS branch_id, b.name AS branch_name,
                 COALESCE(SUM(ii.total_price), 0) AS revenue
          FROM branches b
          LEFT JOIN issuances iss ON iss.branch_id = b.id AND iss.is_deleted = 0
                 AND iss.date_created BETWEEN ? AND ?
          LEFT JOIN issuance_items ii ON ii.issuance_number = iss.issuance_number AND ii.is_deleted = 0
          WHERE b.is_active = 1";
$bpParams = [$curFrom->format('Y-m-d H:i:s'), $curTo->format('Y-m-d H:i:s')];
if ($scope !== null) { $bpSql .= ' AND b.id = ?'; $bpParams[] = $scope; }
$bpSql .= ' GROUP BY b.id, b.name ORDER BY revenue DESC';
$stmt = $db->prepare($bpSql);
$stmt->execute($bpParams);
$branchPerformance = array_map(
    fn($r) => ['branch_id' => (int)$r['branch_id'], 'branch_name' => $r['branch_name'], 'revenue' => (float)$r['revenue']],
    $stmt->fetchAll()
);

// ── Needs Attention — counts of things waiting on a decision, so management
// doesn't have to open every module to find them. ──
function dashboardCount(PDO $db, string $sql, array $params): int {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}
$prPendingSql = "SELECT COUNT(*) FROM pr_requests WHERE is_deleted = 0 AND status = 'pending'";
$prPendingParams = [];
if ($scope !== null) { $prPendingSql .= ' AND branch_id = ?'; $prPendingParams[] = $scope; }

$stPendingSql = "SELECT COUNT(*) FROM stock_transfers WHERE is_deleted = 0 AND status IN ('pending','in_transit')";
$stPendingParams = [];
if ($scope !== null) { $stPendingSql .= ' AND (from_branch_id = ? OR to_branch_id = ?)'; $stPendingParams[] = $scope; $stPendingParams[] = $scope; }

$ordersOpenSql = "SELECT COUNT(*) FROM po_outgoing WHERE is_deleted = 0 AND status IN ('open','partially_delivered')";
$ordersOpenParams = [];
if ($scope !== null) { $ordersOpenSql .= ' AND branch_id = ?'; $ordersOpenParams[] = $scope; }

$lowStockSql = 'SELECT COUNT(*) FROM inventory_stock WHERE reorder_level > 0 AND quantity_on_hand <= reorder_level';
$lowStockParams = [];
if ($scope !== null) { $lowStockSql .= ' AND branch_id = ?'; $lowStockParams[] = $scope; }

$needsAttention = [
    'pr_pending'    => dashboardCount($db, $prPendingSql, $prPendingParams),
    'stock_pending' => dashboardCount($db, $stPendingSql, $stPendingParams),
    'orders_open'   => dashboardCount($db, $ordersOpenSql, $ordersOpenParams),
    'low_stock'     => dashboardCount($db, $lowStockSql, $lowStockParams),
];

// ── Today's Deliveries + combined Open PO count (internal + client-facing) ──
$todaySql = "SELECT COUNT(*) FROM delivery_reports WHERE is_deleted = 0 AND DATE(date_delivered) = CURDATE()";
$todayParams = [];
if ($scope !== null) { $todaySql .= ' AND branch_id = ?'; $todayParams[] = $scope; }
$todayDeliveries = dashboardCount($db, $todaySql, $todayParams);

$openPoInternalSql = "SELECT COUNT(*) FROM po_internal WHERE is_deleted = 0 AND status IN ('draft','sent','partially_received')";
$openPoInternalParams = [];
if ($scope !== null) { $openPoInternalSql .= ' AND branch_id = ?'; $openPoInternalParams[] = $scope; }
$openPoCount = dashboardCount($db, $openPoInternalSql, $openPoInternalParams) + $needsAttention['orders_open'];

// ── Stock on hand grouped by category / by branch (bulk qty + serialized
// in-stock units combined, same definition as the KPI card above). ──
function dashboardStockByCategory(PDO $db, ?int $scope): array {
    $totals = [];
    $stockSql = "SELECT c.id AS gid, COALESCE(c.name,'Uncategorized') AS gname, COALESCE(SUM(s.quantity_on_hand),0) AS qty
                 FROM inventory_stock s
                 JOIN items i ON i.id = s.item_id
                 LEFT JOIN item_categories c ON c.id = i.category_id
                 WHERE 1=1";
    $params = [];
    if ($scope !== null) { $stockSql .= ' AND s.branch_id = ?'; $params[] = $scope; }
    $stockSql .= ' GROUP BY c.id, c.name';
    $stmt = $db->prepare($stockSql); $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $key = $r['gid'] ?? 0;
        $totals[$key] = ['name' => $r['gname'], 'qty' => (float)$r['qty']];
    }
    $unitSql = "SELECT c.id AS gid, COALESCE(c.name,'Uncategorized') AS gname, COUNT(*) AS qty
                FROM inventory_units u
                JOIN items i ON i.id = u.item_id
                LEFT JOIN item_categories c ON c.id = i.category_id
                WHERE u.is_deleted = 0 AND u.status = 'in_stock'";
    $uparams = [];
    if ($scope !== null) { $unitSql .= ' AND u.branch_id = ?'; $uparams[] = $scope; }
    $unitSql .= ' GROUP BY c.id, c.name';
    $stmt = $db->prepare($unitSql); $stmt->execute($uparams);
    foreach ($stmt->fetchAll() as $r) {
        $key = $r['gid'] ?? 0;
        if (!isset($totals[$key])) { $totals[$key] = ['name' => $r['gname'], 'qty' => 0]; }
        $totals[$key]['qty'] += (float)$r['qty'];
    }
    $out = array_values(array_filter($totals, fn($r) => $r['qty'] > 0));
    usort($out, fn($a, $b) => $b['qty'] <=> $a['qty']);
    return $out;
}
function dashboardStockByBranch(PDO $db, ?int $scope): array {
    $totals = [];
    $stockSql = "SELECT b.id AS gid, b.name AS gname, COALESCE(SUM(s.quantity_on_hand),0) AS qty
                 FROM branches b
                 LEFT JOIN inventory_stock s ON s.branch_id = b.id
                 WHERE b.is_active = 1";
    $params = [];
    if ($scope !== null) { $stockSql .= ' AND b.id = ?'; $params[] = $scope; }
    $stockSql .= ' GROUP BY b.id, b.name';
    $stmt = $db->prepare($stockSql); $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) { $totals[$r['gid']] = ['name' => $r['gname'], 'qty' => (float)$r['qty']]; }
    $unitSql = "SELECT branch_id AS gid, COUNT(*) AS qty FROM inventory_units WHERE is_deleted = 0 AND status = 'in_stock'";
    $uparams = [];
    if ($scope !== null) { $unitSql .= ' AND branch_id = ?'; $uparams[] = $scope; }
    $unitSql .= ' GROUP BY branch_id';
    $stmt = $db->prepare($unitSql); $stmt->execute($uparams);
    foreach ($stmt->fetchAll() as $r) {
        if (isset($totals[$r['gid']])) { $totals[$r['gid']]['qty'] += (float)$r['qty']; }
    }
    $out = array_values($totals);
    usort($out, fn($a, $b) => $b['qty'] <=> $a['qty']);
    return $out;
}
$stockByCategory = dashboardStockByCategory($db, $scope);
$stockByBranch   = dashboardStockByBranch($db, $scope);

// ── Total Stock trend — last 6 months. Walked backward from today's actual
// total (exact at the right edge); each earlier point is today's total minus
// the net received-vs-issued movement since then. Doesn't net out inter-
// branch stock transfers, so it's an approximation further back in time. ──
$stockTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $mFrom = (clone $curFrom)->modify("-{$i} months");
    $mTo   = $i === 0 ? $curTo : (clone $mFrom)->modify('+1 month')->modify('-1 day')->setTime(23, 59, 59);

    $recvSql = "SELECT COALESCE(SUM(rri.quantity),0) FROM receiving_report_items rri
                JOIN receiving_reports rr ON rr.rr_number = rri.rr_number
                WHERE rri.is_deleted = 0 AND rr.is_deleted = 0 AND rr.date_received BETWEEN ? AND ?";
    $recvParams = [$mFrom->format('Y-m-d H:i:s'), $mTo->format('Y-m-d H:i:s')];
    if ($scope !== null) { $recvSql .= ' AND rr.branch_id = ?'; $recvParams[] = $scope; }
    $stmt = $db->prepare($recvSql); $stmt->execute($recvParams);
    $recvQty = (float)$stmt->fetchColumn();

    $issSql = "SELECT COALESCE(SUM(COALESCE(ii.quantity,1)),0) FROM issuance_items ii
               JOIN issuances iss ON iss.issuance_number = ii.issuance_number
               WHERE ii.is_deleted = 0 AND iss.is_deleted = 0 AND iss.date_created BETWEEN ? AND ?";
    $issParams = [$mFrom->format('Y-m-d H:i:s'), $mTo->format('Y-m-d H:i:s')];
    if ($scope !== null) { $issSql .= ' AND iss.branch_id = ?'; $issParams[] = $scope; }
    $stmt = $db->prepare($issSql); $stmt->execute($issParams);
    $issQty = (float)$stmt->fetchColumn();

    $stockTrend[] = ['label' => $mFrom->format('M'), 'net' => $recvQty - $issQty];
}
$runningTotal = $inventoryItems - array_sum(array_column($stockTrend, 'net'));
foreach ($stockTrend as &$row) {
    $runningTotal += $row['net'];
    $row['total'] = round($runningTotal, 2);
    unset($row['net']);
}
unset($row);

// ── Recent Transactions — latest handful across every document type, for
// the landing-page feed (separate from the audit-log-driven Recent Activity). ──
function dashboardRecentTransactions(PDO $db, ?int $scope): array {
    $rows = [];
    $fetch = function (string $sql, array $params) use ($db) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    };

    $sql = "SELECT pr_number AS doc_number, purpose AS description, status, created_at FROM pr_requests WHERE is_deleted = 0";
    $params = []; if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY created_at DESC LIMIT 5';
    foreach ($fetch($sql, $params) as $r) {
        $rows[] = ['doc_number' => $r['doc_number'], 'doc_type' => 'PR', 'doc_class' => 'doc-pr',
                   'description' => $r['description'] ?: 'Purchase Requisition', 'status' => $r['status'], 'created_at' => $r['created_at']];
    }

    $sql = "SELECT po_number AS doc_number, supplier_name AS description, status, created_at FROM po_internal WHERE is_deleted = 0";
    $params = []; if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY created_at DESC LIMIT 5';
    foreach ($fetch($sql, $params) as $r) {
        $rows[] = ['doc_number' => $r['doc_number'], 'doc_type' => 'PO (Supplier)', 'doc_class' => 'doc-poi',
                   'description' => $r['description'] ?: 'Supplier order', 'status' => $r['status'], 'created_at' => $r['created_at']];
    }

    $sql = "SELECT po_number AS doc_number, client_name AS description, status, created_at FROM po_outgoing WHERE is_deleted = 0";
    $params = []; if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY created_at DESC LIMIT 5';
    foreach ($fetch($sql, $params) as $r) {
        $rows[] = ['doc_number' => $r['doc_number'], 'doc_type' => 'PO (Client)', 'doc_class' => 'doc-poo',
                   'description' => $r['description'] ?: 'Client order', 'status' => $r['status'], 'created_at' => $r['created_at']];
    }

    $sql = "SELECT rr_number AS doc_number, po_number, created_at FROM receiving_reports WHERE is_deleted = 0";
    $params = []; if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY created_at DESC LIMIT 5';
    foreach ($fetch($sql, $params) as $r) {
        $rows[] = ['doc_number' => $r['doc_number'], 'doc_type' => 'Receiving', 'doc_class' => 'doc-rr',
                   'description' => $r['po_number'] ? ('For ' . $r['po_number']) : 'Goods received', 'status' => 'received', 'created_at' => $r['created_at']];
    }

    $sql = "SELECT dr_number AS doc_number, po_number, created_at FROM delivery_reports WHERE is_deleted = 0";
    $params = []; if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY created_at DESC LIMIT 5';
    foreach ($fetch($sql, $params) as $r) {
        $rows[] = ['doc_number' => $r['doc_number'], 'doc_type' => 'Delivery', 'doc_class' => 'doc-dr',
                   'description' => $r['po_number'] ? ('For ' . $r['po_number']) : 'Client delivery', 'status' => 'delivered', 'created_at' => $r['created_at']];
    }

    $sql = "SELECT issuance_number AS doc_number, walkin_name, po_number, created_at FROM issuances WHERE is_deleted = 0";
    $params = []; if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY created_at DESC LIMIT 5';
    foreach ($fetch($sql, $params) as $r) {
        $rows[] = ['doc_number' => $r['doc_number'], 'doc_type' => 'Issuance', 'doc_class' => 'doc-iss',
                   'description' => $r['walkin_name'] ?: ($r['po_number'] ? ('For ' . $r['po_number']) : 'Walk-in sale'),
                   'status' => 'completed', 'created_at' => $r['created_at']];
    }

    usort($rows, fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));
    return array_slice($rows, 0, 8);
}
$recentTransactions = dashboardRecentTransactions($db, $scope);

// ── Top Suppliers — ranked by how many internal POs have gone to them ──
$supSql = "SELECT s.id, s.name, COUNT(*) AS order_count
           FROM po_internal p JOIN suppliers s ON s.id = p.supplier_id
           WHERE p.is_deleted = 0";
$supParams = [];
if ($scope !== null) { $supSql .= ' AND p.branch_id = ?'; $supParams[] = $scope; }
$supSql .= ' GROUP BY s.id, s.name ORDER BY order_count DESC LIMIT 5';
$stmt = $db->prepare($supSql); $stmt->execute($supParams);
$topSuppliers = $stmt->fetchAll();

// ── Low stock alerts — the actual items behind needs_attention.low_stock ──
$lowStockItemsSql = "SELECT it.name AS item_name, b.name AS branch_name,
                             s.quantity_on_hand, s.reorder_level
                      FROM inventory_stock s
                      JOIN items it ON it.id = s.item_id
                      JOIN branches b ON b.id = s.branch_id
                      WHERE s.reorder_level > 0 AND s.quantity_on_hand <= s.reorder_level";
$lowStockItemsParams = [];
if ($scope !== null) { $lowStockItemsSql .= ' AND s.branch_id = ?'; $lowStockItemsParams[] = $scope; }
$lowStockItemsSql .= ' ORDER BY (s.quantity_on_hand - s.reorder_level) ASC LIMIT 6';
$stmt = $db->prepare($lowStockItemsSql);
$stmt->execute($lowStockItemsParams);
$lowStockItems = $stmt->fetchAll();

// ── Client order pipeline — current backlog snapshot of po_outgoing, not
// date-filtered (it's "what's in flight right now", not a monthly count) ──
$pipelineSql = 'SELECT status, COUNT(*) AS cnt FROM po_outgoing WHERE is_deleted = 0';
$pipelineParams = [];
if ($scope !== null) { $pipelineSql .= ' AND branch_id = ?'; $pipelineParams[] = $scope; }
$pipelineSql .= ' GROUP BY status';
$stmt = $db->prepare($pipelineSql);
$stmt->execute($pipelineParams);
$pipelineCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$pipelineLabels = [
    'open'                => 'Open',
    'partially_delivered' => 'Partial',
    'delivered'           => 'Delivered',
    'completed'           => 'Completed',
    'cancelled'           => 'Cancelled',
];
$orderPipeline = [];
foreach ($pipelineLabels as $key => $label) {
    $orderPipeline[] = ['status' => $key, 'label' => $label, 'count' => (int)($pipelineCounts[$key] ?? 0)];
}

// ── Top 5 Products (by what was actually issued/sold) ──
$topSql = "SELECT it.id AS item_id, it.name AS item_name,
                  SUM(COALESCE(ii.quantity, 1)) AS qty_sold,
                  SUM(ii.total_price) AS revenue
           FROM issuance_items ii
           JOIN issuances i ON i.issuance_number = ii.issuance_number
           LEFT JOIN inventory_units iu ON iu.id = ii.inventory_unit_id
           JOIN items it ON it.id = COALESCE(iu.item_id, ii.item_id)
           WHERE ii.is_deleted = 0 AND i.is_deleted = 0";
$topParams = [];
if ($scope !== null) { $topSql .= ' AND i.branch_id = ?'; $topParams[] = $scope; }
$topSql .= ' GROUP BY it.id, it.name ORDER BY revenue DESC LIMIT 5';
$stmt = $db->prepare($topSql);
$stmt->execute($topParams);
$topProducts = $stmt->fetchAll();

// ── Recent activity — same audit_logs table the Activity Log page filters,
// just the newest handful for a landing-page glance. ──
$activitySql = 'SELECT username, action, module, details, created_at FROM audit_logs WHERE 1=1';
$activityParams = [];
if ($scope !== null) {
    // audit_logs has no branch_id of its own (it predates branch scoping);
    // narrowing by the acting user's branch keeps this feed relevant without
    // a schema change.
    $activitySql .= ' AND user_id IN (SELECT id FROM users WHERE branch_id = ?)';
    $activityParams[] = $scope;
}
$activitySql .= ' ORDER BY created_at DESC LIMIT 8';
$stmt = $db->prepare($activitySql);
$stmt->execute($activityParams);
$recentActivity = $stmt->fetchAll();

jsonResponse(true, '', [
    'stats' => [
        'purchase_requisitions' => $prCount,
        'purchase_orders'       => $poCount,
        'received_items'        => $receivedItems,
        'inventory_items'       => $inventoryItems,
    ],
    'financials'         => $financials,
    'monthly_sales'      => $monthlySales,
    'branch_performance' => $branchPerformance,
    'needs_attention'    => $needsAttention,
    'low_stock_items'    => $lowStockItems,
    'order_pipeline'     => $orderPipeline,
    'top_products'       => $topProducts,
    'recent_activity'    => $recentActivity,
    'today_deliveries'   => $todayDeliveries,
    'open_po_count'      => $openPoCount,
    'stock_by_category'  => $stockByCategory,
    'stock_by_branch'    => $stockByBranch,
    'stock_trend'        => $stockTrend,
    'recent_transactions' => $recentTransactions,
    'top_suppliers'      => $topSuppliers,
]);
