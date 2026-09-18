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

// ── Monthly Purchase Order Summary (year to date, both PO tables combined) ──
$monthlyPo = array_fill(1, 12, 0);
foreach (['po_internal', 'po_outgoing'] as $table) {
    $sql = "SELECT MONTH(created_at) AS m, COUNT(*) AS cnt FROM {$table}
            WHERE is_deleted = 0 AND YEAR(created_at) = YEAR(CURDATE())";
    $params = [];
    if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
    $sql .= ' GROUP BY MONTH(created_at)';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $monthlyPo[(int)$row['m']] += (int)$row['cnt'];
    }
}
$currentMonth = (int)date('n');
$monthNames   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthlyPoSeries = [];
for ($m = 1; $m <= $currentMonth; $m++) {
    $monthlyPoSeries[] = ['label' => $monthNames[$m - 1], 'count' => $monthlyPo[$m]];
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
    'monthly_po'      => $monthlyPoSeries,
    'top_products'    => $topProducts,
    'recent_activity' => $recentActivity,
]);
