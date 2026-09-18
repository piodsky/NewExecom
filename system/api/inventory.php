<?php
// Inventory — branch-scoped serialized units and bulk stock. Administrators
// can also enter existing stock directly, without a Purchase Order or RR.
require_once '../config.php';
requireModule('inventory');
header('Content-Type: application/json');

$user  = getCurrentUser();
$scope = getUserBranchScope($user);
$db    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

$adminGetActions = ['entry_options', 'unassigned_list'];
if ($method === 'POST' || ($method === 'GET' && in_array($_GET['action'] ?? '', $adminGetActions, true))) {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can manually add inventory.');
    if ($user['role'] !== 'super_admin' && $scope === null) {
        jsonResponse(false, 'Your account must be assigned to a branch before adding inventory.');
    }
}

if ($method === 'GET' && ($_GET['action'] ?? '') === 'entry_options') {
    $items = $db->query(
        'SELECT id, name, sku, unit, is_serialized, default_cost FROM items WHERE is_active = 1 ORDER BY name'
    )->fetchAll();
    $branchStmt = $db->prepare(
        'SELECT id, name FROM branches WHERE is_active = 1' . ($scope !== null ? ' AND id = ?' : '') . ' ORDER BY name'
    );
    $branchStmt->execute($scope !== null ? [$scope] : []);
    jsonResponse(true, '', ['items' => $items, 'branches' => $branchStmt->fetchAll()]);
}

if ($method === 'GET' && ($_GET['action'] ?? '') === 'unassigned_list') {
    $sql = "SELECT uu.id, uu.branch_id, b.name AS branch_name, uu.serial_number, uu.date_received,
                   uu.remarks, u.full_name AS scanned_by_name
            FROM inventory_unassigned_units uu
            JOIN branches b ON b.id = uu.branch_id
            LEFT JOIN users u ON u.id = uu.scanned_by
            WHERE uu.is_deleted = 0";
    $params = [];
    if ($scope !== null) { $sql .= ' AND uu.branch_id = ?'; $params[] = $scope; }
    $q = trim($_GET['q'] ?? '');
    if ($q !== '') { $sql .= ' AND uu.serial_number LIKE ?'; $params[] = "%{$q}%"; }
    $sql .= ' ORDER BY uu.created_at DESC, uu.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['unassigned' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = getInput();
    $action = is_array($data) ? ($data['action'] ?? '') : '';
    if (!in_array($action, ['manual_add', 'manual_add_batch', 'quick_scan_batch', 'segregate'], true)) jsonResponse(false, 'Invalid action.');
    $token = $data['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['inventory_csrf_token']) || !hash_equals($_SESSION['inventory_csrf_token'], $token)) {
        jsonResponse(false, 'Your form has expired. Refresh the page and try again.');
    }
    try {
        if (in_array($action, ['manual_add', 'manual_add_batch'], true)) {
            require_once __DIR__ . '/../lib/manual_inventory.php';
            $result = saveManualInventory($db, $user, $scope, $data);
            $message = $action === 'manual_add_batch' ? $result['row_count'] . ' inventory rows added successfully.' : 'Inventory added successfully.';
        } elseif ($action === 'quick_scan_batch') {
            require_once __DIR__ . '/../lib/quick_scan_inventory.php';
            $result = saveQuickScanBatch($db, $user, $scope, $data);
            $message = $result['row_count'] . ' serial' . ($result['row_count'] === 1 ? '' : 's') . ' scanned. Segregate them to a master item when ready.';
        } else {
            require_once __DIR__ . '/../lib/quick_scan_inventory.php';
            $result = segregateUnassignedUnits($db, $user, $scope, $data);
            $message = $result['row_count'] . ' unit' . ($result['row_count'] === 1 ? '' : 's') . ' assigned to ' . $result['item_name'] . '.';
        }
    } catch (DomainException | InvalidArgumentException $e) {
        jsonResponse(false, $e->getMessage());
    } catch (Throwable $e) {
        error_log('Inventory action failed: ' . $e->getMessage());
        jsonResponse(false, 'Failed to complete the request. Please try again.');
    }
    jsonResponse(true, $message, $result);
}
if ($method !== 'GET') jsonResponse(false, 'Method not allowed.');

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
