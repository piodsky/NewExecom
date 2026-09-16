<?php
// Cost/Profit Reports — Internal and Outgoing. No tables of its own: this is
// read-only aggregation over what Issuance already recorded. Issuance is the
// single point where stock actually leaves a branch (Phase 3), for both a
// walk-in/retail sale and one fulfilling a specific Outgoing PO (Phase 4) —
// so issuance_items.total_price is the one place "what we sold it for" lives
// for both sale types, and it's what this report treats as revenue.
//
// Cost is matched per line: a serialized unit carries its own exact
// unit_cost from the day it was received, but a bulk item's inventory_stock
// is a pooled running quantity with no per-unit cost history once multiple
// receipts merge into it — so bulk cost falls back to items.default_cost as
// a standard-cost estimate. This is a known simplification, not a bug: exact
// bulk costing would need weighted-average lot tracking, which is out of
// scope for this phase.
require_once '../config.php';
requireModule('cost_profit_reports');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();

$from = trim($_GET['from'] ?? '') ?: date('Y-m-01');
$to   = trim($_GET['to'] ?? '') ?: date('Y-m-d');

$branchId = null;
if ($scope !== null) {
    $branchId = $scope;
} elseif (!empty($_GET['branch_id'])) {
    $branchId = (int)$_GET['branch_id'];
}

$saleType = trim($_GET['sale_type'] ?? ''); // '', 'internal', 'outgoing'

$where  = 'ii.is_deleted = 0 AND iss.is_deleted = 0 AND DATE(iss.date_created) BETWEEN ? AND ?';
$params = [$from, $to];
if ($branchId) { $where .= ' AND iss.branch_id = ?'; $params[] = $branchId; }
if ($saleType === 'internal') { $where .= ' AND iss.po_number IS NULL'; }
if ($saleType === 'outgoing') { $where .= ' AND iss.po_number IS NOT NULL'; }

$fromClause = "issuance_items ii
    JOIN issuances iss ON iss.issuance_number = ii.issuance_number
    LEFT JOIN inventory_units iu ON iu.id = ii.inventory_unit_id
    LEFT JOIN items it ON it.id = COALESCE(ii.item_id, iu.item_id)
    LEFT JOIN item_categories cat ON cat.id = it.category_id
    LEFT JOIN clients c ON c.id = iss.client_id
    LEFT JOIN branches b ON b.id = iss.branch_id";

$revenueExpr = 'ii.total_price';
$costExpr    = 'COALESCE(iu.unit_cost, it.default_cost, 0) * ii.quantity';

// Totals
$stmt = $db->prepare(
    "SELECT COUNT(*) AS line_count, COALESCE(SUM($revenueExpr),0) AS revenue, COALESCE(SUM($costExpr),0) AS cost
     FROM $fromClause WHERE $where"
);
$stmt->execute($params);
$totals = $stmt->fetch();
$totals['revenue'] = (float)$totals['revenue'];
$totals['cost']    = (float)$totals['cost'];
$totals['margin']  = $totals['revenue'] - $totals['cost'];
$totals['margin_pct'] = $totals['revenue'] > 0 ? round($totals['margin'] / $totals['revenue'] * 100, 1) : 0;

// By branch — a company-wide comparison, meaningful for super_admin; for a
// branch-scoped user this naturally collapses to their own single branch.
$stmt = $db->prepare(
    "SELECT b.id AS branch_id, b.name AS branch_name,
            COALESCE(SUM($revenueExpr),0) AS revenue, COALESCE(SUM($costExpr),0) AS cost
     FROM $fromClause WHERE $where GROUP BY b.id, b.name ORDER BY revenue DESC"
);
$stmt->execute($params);
$byBranch = array_map(function ($r) {
    $r['revenue'] = (float)$r['revenue']; $r['cost'] = (float)$r['cost']; $r['margin'] = $r['revenue'] - $r['cost'];
    return $r;
}, $stmt->fetchAll());

// By item category
$stmt = $db->prepare(
    "SELECT cat.id AS category_id, COALESCE(cat.name, 'Uncategorized') AS category_name,
            COALESCE(SUM($revenueExpr),0) AS revenue, COALESCE(SUM($costExpr),0) AS cost
     FROM $fromClause WHERE $where GROUP BY cat.id, cat.name ORDER BY revenue DESC"
);
$stmt->execute($params);
$byCategory = array_map(function ($r) {
    $r['revenue'] = (float)$r['revenue']; $r['cost'] = (float)$r['cost']; $r['margin'] = $r['revenue'] - $r['cost'];
    return $r;
}, $stmt->fetchAll());

// By client (NULL client_id = walk-in/retail sale)
$stmt = $db->prepare(
    "SELECT c.id AS client_id, COALESCE(c.name, 'Walk-in / Retail') AS client_name,
            COALESCE(SUM($revenueExpr),0) AS revenue, COALESCE(SUM($costExpr),0) AS cost
     FROM $fromClause WHERE $where GROUP BY c.id, c.name ORDER BY revenue DESC"
);
$stmt->execute($params);
$byClient = array_map(function ($r) {
    $r['revenue'] = (float)$r['revenue']; $r['cost'] = (float)$r['cost']; $r['margin'] = $r['revenue'] - $r['cost'];
    return $r;
}, $stmt->fetchAll());

// By sale type — Internal (walk-in/retail) vs Outgoing (fulfilling a client PO)
$stmt = $db->prepare(
    "SELECT (iss.po_number IS NOT NULL) AS is_outgoing,
            COALESCE(SUM($revenueExpr),0) AS revenue, COALESCE(SUM($costExpr),0) AS cost
     FROM $fromClause WHERE $where GROUP BY is_outgoing"
);
$stmt->execute($params);
$bySaleType = array_map(function ($r) {
    $r['revenue'] = (float)$r['revenue']; $r['cost'] = (float)$r['cost']; $r['margin'] = $r['revenue'] - $r['cost'];
    $r['label'] = $r['is_outgoing'] ? 'Outgoing (client orders)' : 'Internal (walk-in / retail)';
    return $r;
}, $stmt->fetchAll());

// Line-level detail for drill-down / CSV export — capped so this stays a
// report, not an unpaginated dump.
$stmt = $db->prepare(
    "SELECT iss.issuance_number, iss.date_created, b.name AS branch_name,
            COALESCE(c.name, 'Walk-in / Retail') AS client_name, iss.po_number,
            COALESCE(it.name, 'Unknown item') AS item_name, ii.quantity,
            $revenueExpr AS revenue, $costExpr AS cost
     FROM $fromClause WHERE $where ORDER BY iss.date_created DESC LIMIT 500"
);
$stmt->execute($params);
$transactions = array_map(function ($r) {
    $r['revenue'] = (float)$r['revenue']; $r['cost'] = (float)$r['cost']; $r['margin'] = $r['revenue'] - $r['cost'];
    return $r;
}, $stmt->fetchAll());

jsonResponse(true, '', [
    'totals'       => $totals,
    'by_branch'    => $byBranch,
    'by_category'  => $byCategory,
    'by_client'    => $byClient,
    'by_sale_type' => $bySaleType,
    'transactions' => $transactions,
]);
