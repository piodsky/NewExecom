<?php
// Inventory — Internal, hybrid model. Read-only view for this phase: rows
// are populated by posting a Receiving Report and will later be consumed by
// Stock Transfer and Issuance (Phase 3). Branch-scoped like every other
// transactional module. A branch IS a warehouse — no separate warehouse
// table (see the structure plan).
require_once '../config.php';
requireModule('inventory');
header('Content-Type: application/json');

$user  = getCurrentUser();
$scope = getUserBranchScope($user);
$db    = getDB();

$sql = "SELECT u.id, u.item_id, i.name AS item_name, i.sku, c.name AS category_name, u.branch_id,
               b.name AS branch_name, u.serial_number, u.status, u.unit_cost, u.location,
               u.date_received, u.date_out, u.remarks
        FROM inventory_units u
        JOIN items i ON i.id = u.item_id
        LEFT JOIN item_categories c ON c.id = i.category_id
        JOIN branches b ON b.id = u.branch_id
        WHERE u.is_deleted = 0";
$params = [];
if ($scope !== null) { $sql .= ' AND u.branch_id = ?'; $params[] = $scope; }

$status = trim($_GET['status'] ?? '');
if ($status !== '') { $sql .= ' AND u.status = ?'; $params[] = $status; }

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $sql .= ' AND (i.name LIKE ? OR u.serial_number LIKE ? OR i.sku LIKE ?)';
    array_push($params, "%{$q}%", "%{$q}%", "%{$q}%");
}
$sql .= ' ORDER BY u.date_received DESC, u.id DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$units = $stmt->fetchAll();

$stockSql = "SELECT s.id, s.item_id, i.name AS item_name, i.sku, c.name AS category_name, i.unit,
                    s.branch_id, b.name AS branch_name, s.quantity_on_hand, s.reorder_level
             FROM inventory_stock s
             JOIN items i ON i.id = s.item_id
             LEFT JOIN item_categories c ON c.id = i.category_id
             JOIN branches b ON b.id = s.branch_id
             WHERE 1=1";
$stockParams = [];
if ($scope !== null) { $stockSql .= ' AND s.branch_id = ?'; $stockParams[] = $scope; }
if ($q !== '') { $stockSql .= ' AND (i.name LIKE ? OR i.sku LIKE ?)'; array_push($stockParams, "%{$q}%", "%{$q}%"); }
$stockSql .= ' ORDER BY i.name';
$stockStmt = $db->prepare($stockSql);
$stockStmt->execute($stockParams);
$stock = $stockStmt->fetchAll();

jsonResponse(true, '', ['units' => $units, 'stock' => $stock]);
