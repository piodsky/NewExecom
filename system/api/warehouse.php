<?php
// Warehouse Map — a structured floor plan of bins (warehouse_locations) laid
// over Inventory. Receiving/Manual Add/Quick Scan already put stock INTO
// inventory_units/inventory_stock; this is the put-away/pick layer on top —
// where each unit or pooled bulk line physically sits, and a visual grid of
// that. A unit only counts as "occupying" a bin while status = 'in_stock';
// once Issuance or Stock Transfer moves it out, it drops off the map on its
// own (no need to clear location_id from those flows — this page's queries
// simply stop counting it).
require_once '../config.php';
requireModule('warehouse');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function resolveBranchId(?int $scope, $requested): ?int {
    if ($scope !== null) return $scope;
    $requested = $requested !== null && $requested !== '' ? (int)$requested : null;
    return $requested;
}

if ($method === 'GET') {
    $branchId = resolveBranchId($scope, $_GET['branch_id'] ?? null);

    $response = ['need_branch' => $branchId === null];
    if ($scope === null) {
        $response['branches'] = $db->query('SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name')->fetchAll();
    }
    if ($branchId === null) {
        jsonResponse(true, '', $response + ['locations' => [], 'unplaced_units' => [], 'unplaced_stock' => []]);
    }
    requireBranchAccess($branchId);

    $stmt = $db->prepare(
        'SELECT id, code, label, grid_row, grid_col, capacity, is_active
         FROM warehouse_locations WHERE branch_id = ? ORDER BY grid_row, grid_col'
    );
    $stmt->execute([$branchId]);
    $locations = $stmt->fetchAll();

    $stmt = $db->prepare(
        "SELECT iu.location_id, iu.id, iu.serial_number, it.name AS item_name, it.sku
         FROM inventory_units iu JOIN items it ON it.id = iu.item_id
         WHERE iu.branch_id = ? AND iu.is_deleted = 0 AND iu.status = 'in_stock' AND iu.location_id IS NOT NULL"
    );
    $stmt->execute([$branchId]);
    $placedUnits = $stmt->fetchAll();

    $stmt = $db->prepare(
        'SELECT s.location_id, s.item_id, it.name AS item_name, it.sku, s.quantity_on_hand, it.unit
         FROM inventory_stock s JOIN items it ON it.id = s.item_id
         WHERE s.branch_id = ? AND s.location_id IS NOT NULL AND s.quantity_on_hand > 0'
    );
    $stmt->execute([$branchId]);
    $placedStock = $stmt->fetchAll();

    $unitsByLocation = [];
    foreach ($placedUnits as $u) { $unitsByLocation[$u['location_id']][] = $u; }
    $stockByLocation = [];
    foreach ($placedStock as $s) { $stockByLocation[$s['location_id']][] = $s; }

    // unit_count is what `capacity` actually limits (serialized units only —
    // see the column comment in schema.sql); stock_qty is shown alongside it
    // for context but never counts against capacity, since one bulk SKU's
    // pooled quantity isn't "one item taking up one slot" the way a unit is.
    foreach ($locations as &$loc) {
        $loc['units'] = $unitsByLocation[$loc['id']] ?? [];
        $loc['stock'] = $stockByLocation[$loc['id']] ?? [];
        $loc['unit_count'] = count($loc['units']);
        $loc['stock_qty']  = (int)array_sum(array_column($loc['stock'], 'quantity_on_hand'));
    }
    unset($loc);

    $stmt = $db->prepare(
        "SELECT iu.id, iu.serial_number, it.name AS item_name, it.sku, iu.date_received
         FROM inventory_units iu JOIN items it ON it.id = iu.item_id
         WHERE iu.branch_id = ? AND iu.is_deleted = 0 AND iu.status = 'in_stock' AND iu.location_id IS NULL
         ORDER BY iu.date_received DESC, iu.id DESC LIMIT 200"
    );
    $stmt->execute([$branchId]);
    $unplacedUnits = $stmt->fetchAll();

    $stmt = $db->prepare(
        'SELECT s.item_id, it.name AS item_name, it.sku, s.quantity_on_hand, it.unit
         FROM inventory_stock s JOIN items it ON it.id = s.item_id
         WHERE s.branch_id = ? AND s.quantity_on_hand > 0 AND s.location_id IS NULL
         ORDER BY it.name'
    );
    $stmt->execute([$branchId]);
    $unplacedStock = $stmt->fetchAll();

    jsonResponse(true, '', $response + [
        'branch_id'       => $branchId,
        'locations'       => $locations,
        'unplaced_units'  => $unplacedUnits,
        'unplaced_stock'  => $unplacedStock,
    ]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? '';

    if ($action === 'create_location' || $action === 'update_location') {
        if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage warehouse locations.');
        $branchId = resolveBranchId($scope, $data['branch_id'] ?? null);
        if (!$branchId) jsonResponse(false, 'Branch is required.');
        requireBranchAccess($branchId);

        $code = trim($data['code'] ?? '');
        $label = trim($data['label'] ?? '');
        $gridRow = max(1, (int)($data['grid_row'] ?? 1));
        $gridCol = max(1, (int)($data['grid_col'] ?? 1));
        $capacity = $data['capacity'] !== '' && $data['capacity'] !== null ? max(0, (int)$data['capacity']) : null;
        if ($code === '') jsonResponse(false, 'Bin code is required.');

        if ($action === 'create_location') {
            $exists = $db->prepare('SELECT COUNT(*) FROM warehouse_locations WHERE branch_id = ? AND code = ?');
            $exists->execute([$branchId, $code]);
            if ($exists->fetchColumn() > 0) jsonResponse(false, 'That bin code already exists for this branch.');
            $db->prepare(
                'INSERT INTO warehouse_locations (branch_id, code, label, grid_row, grid_col, capacity) VALUES (?,?,?,?,?,?)'
            )->execute([$branchId, $code, $label ?: null, $gridRow, $gridCol, $capacity]);
            logAudit('CREATE_WAREHOUSE_LOCATION', 'warehouse', "Created bin \"{$code}\"");
            jsonResponse(true, 'Bin added.');
        }

        $locId = (int)($data['id'] ?? 0);
        if (!$locId) jsonResponse(false, 'Invalid bin.');
        $stmt = $db->prepare('SELECT branch_id FROM warehouse_locations WHERE id = ?');
        $stmt->execute([$locId]);
        $existingBranch = $stmt->fetchColumn();
        if ($existingBranch === false) jsonResponse(false, 'Bin not found.');
        requireBranchAccess((int)$existingBranch);

        $exists = $db->prepare('SELECT COUNT(*) FROM warehouse_locations WHERE branch_id = ? AND code = ? AND id != ?');
        $exists->execute([$existingBranch, $code, $locId]);
        if ($exists->fetchColumn() > 0) jsonResponse(false, 'That bin code already exists for this branch.');

        $db->prepare('UPDATE warehouse_locations SET code=?, label=?, grid_row=?, grid_col=?, capacity=? WHERE id=?')
           ->execute([$code, $label ?: null, $gridRow, $gridCol, $capacity, $locId]);
        logAudit('UPDATE_WAREHOUSE_LOCATION', 'warehouse', "Updated bin \"{$code}\"");
        jsonResponse(true, 'Bin updated.');
    }

    if ($action === 'toggle_location_active') {
        if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage warehouse locations.');
        $locId = (int)($data['id'] ?? 0);
        if (!$locId) jsonResponse(false, 'Invalid bin.');
        $stmt = $db->prepare('SELECT branch_id, code, is_active FROM warehouse_locations WHERE id = ?');
        $stmt->execute([$locId]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, 'Bin not found.');
        requireBranchAccess((int)$row['branch_id']);
        $newState = (int)$row['is_active'] ? 0 : 1;
        $db->prepare('UPDATE warehouse_locations SET is_active = ? WHERE id = ?')->execute([$newState, $locId]);
        logAudit($newState ? 'ACTIVATE_WAREHOUSE_LOCATION' : 'DEACTIVATE_WAREHOUSE_LOCATION', 'warehouse', $row['code']);
        jsonResponse(true, $newState ? 'Bin activated.' : 'Bin deactivated.');
    }

    if ($action === 'place_unit') {
        $unitId = (int)($data['unit_id'] ?? 0);
        $locationId = !empty($data['location_id']) ? (int)$data['location_id'] : null;
        if (!$unitId) jsonResponse(false, 'Invalid item.');

        $stmt = $db->prepare(
            "SELECT iu.branch_id, iu.status, it.name AS item_name
             FROM inventory_units iu JOIN items it ON it.id = iu.item_id
             WHERE iu.id = ? AND iu.is_deleted = 0"
        );
        $stmt->execute([$unitId]);
        $unit = $stmt->fetch();
        if (!$unit) jsonResponse(false, 'Item not found.');
        requireBranchAccess((int)$unit['branch_id']);
        if ($unit['status'] !== 'in_stock') jsonResponse(false, 'Only in-stock items can be placed on the map.');

        if ($locationId) {
            $stmt = $db->prepare('SELECT branch_id, code, capacity FROM warehouse_locations WHERE id = ? AND is_active = 1');
            $stmt->execute([$locationId]);
            $loc = $stmt->fetch();
            if (!$loc) jsonResponse(false, 'Bin not found.');
            if ((int)$loc['branch_id'] !== (int)$unit['branch_id']) jsonResponse(false, 'That bin belongs to a different branch.');
            if ($loc['capacity'] !== null) {
                $countStmt = $db->prepare("SELECT COUNT(*) FROM inventory_units WHERE location_id = ? AND status = 'in_stock' AND is_deleted = 0 AND id != ?");
                $countStmt->execute([$locationId, $unitId]);
                if ((int)$countStmt->fetchColumn() >= (int)$loc['capacity']) jsonResponse(false, "Bin \"{$loc['code']}\" is at capacity.");
            }
        }

        $db->prepare('UPDATE inventory_units SET location_id = ? WHERE id = ?')->execute([$locationId, $unitId]);
        logAudit($locationId ? 'PLACE_INVENTORY_UNIT' : 'UNPLACE_INVENTORY_UNIT', 'warehouse', $unit['item_name']);
        jsonResponse(true, $locationId ? 'Item placed.' : 'Item removed from the map.');
    }

    if ($action === 'place_stock') {
        $itemId = (int)($data['item_id'] ?? 0);
        $branchId = resolveBranchId($scope, $data['branch_id'] ?? null);
        $locationId = !empty($data['location_id']) ? (int)$data['location_id'] : null;
        if (!$itemId || !$branchId) jsonResponse(false, 'Invalid item.');
        requireBranchAccess($branchId);

        $stmt = $db->prepare('SELECT s.id, it.name AS item_name FROM inventory_stock s JOIN items it ON it.id = s.item_id WHERE s.item_id = ? AND s.branch_id = ?');
        $stmt->execute([$itemId, $branchId]);
        $stock = $stmt->fetch();
        if (!$stock) jsonResponse(false, 'Stock line not found.');

        if ($locationId) {
            $stmt = $db->prepare('SELECT branch_id FROM warehouse_locations WHERE id = ? AND is_active = 1');
            $stmt->execute([$locationId]);
            $locBranch = $stmt->fetchColumn();
            if ($locBranch === false) jsonResponse(false, 'Bin not found.');
            if ((int)$locBranch !== $branchId) jsonResponse(false, 'That bin belongs to a different branch.');
        }

        $db->prepare('UPDATE inventory_stock SET location_id = ? WHERE id = ?')->execute([$locationId, $stock['id']]);
        logAudit($locationId ? 'PLACE_INVENTORY_STOCK' : 'UNPLACE_INVENTORY_STOCK', 'warehouse', $stock['item_name']);
        jsonResponse(true, $locationId ? 'Stock placed.' : 'Stock removed from the map.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
