<?php
// Stock Transfer — Internal, branch-to-branch. A request only touches
// inventory once it's actually shipped (out of the source branch) and again
// once received (into the destination branch); a pending or cancelled
// request never has to be rolled back against a live count because it never
// moved anything in the first place. Any user can request a transfer for
// their own branch (mirrors PR); only admin-tier can ship or receive one,
// scoped to whichever side of the transfer they administer.
require_once '../config.php';
requireModule('stock_transfer');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadTransfer(PDO $db, string $transferNumber): ?array {
    $stmt = $db->prepare(
        'SELECT st.*, fb.name AS from_branch_name, tb.name AS to_branch_name,
                ru.full_name AS requested_by_name, au.full_name AS approved_by_name
         FROM stock_transfers st
         JOIN branches fb ON fb.id = st.from_branch_id
         JOIN branches tb ON tb.id = st.to_branch_id
         JOIN users ru ON ru.id = st.requested_by
         LEFT JOIN users au ON au.id = st.approved_by
         WHERE st.transfer_number = ? AND st.is_deleted = 0'
    );
    $stmt->execute([$transferNumber]);
    $t = $stmt->fetch();
    if (!$t) return null;

    $itemStmt = $db->prepare(
        'SELECT id, inventory_unit_id, item_id, quantity, description_snapshot, serial_snapshot
         FROM stock_transfer_items WHERE transfer_number = ? AND is_deleted = 0 ORDER BY id'
    );
    $itemStmt->execute([$transferNumber]);
    $t['items'] = $itemStmt->fetchAll();
    return $t;
}

// True if $scope (null = super_admin) may see/act on a transfer touching
// these two branches.
function transferInScope(?int $scope, int $fromBranch, int $toBranch): bool {
    return $scope === null || $scope === $fromBranch || $scope === $toBranch;
}

if ($method === 'GET') {
    // Destination-branch picker needs the branch list, but api/branches.php
    // is hard-gated to super_admin (it's the branch-structure CRUD screen) —
    // any user with this module can read just id/name here instead.
    if (!empty($_GET['branches'])) {
        $stmt = $db->query('SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name');
        jsonResponse(true, '', ['branches' => $stmt->fetchAll()]);
    }

    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $t = loadTransfer($db, $id);
        if (!$t) jsonResponse(false, 'Transfer not found.');
        if (!transferInScope($scope, (int)$t['from_branch_id'], (int)$t['to_branch_id'])) jsonResponse(false, 'Access denied.');
        jsonResponse(true, '', ['transfer' => $t]);
    }

    // Stock available to move out of a given branch: serial units currently
    // in_stock there, plus bulk items with quantity on hand. Same shape as
    // api/inventory.php so the create-transfer picker can reuse the pattern.
    $availableAt = trim($_GET['available_at'] ?? '');
    if ($availableAt !== '') {
        $branchId = (int)$availableAt;
        if ($scope !== null && $branchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

        $unitStmt = $db->prepare(
            "SELECT u.id, u.item_id, i.name AS item_name, i.unit, u.serial_number, u.unit_cost
             FROM inventory_units u JOIN items i ON i.id = u.item_id
             WHERE u.branch_id = ? AND u.status = 'in_stock' AND u.is_deleted = 0 ORDER BY i.name"
        );
        $unitStmt->execute([$branchId]);

        $stockStmt = $db->prepare(
            'SELECT s.item_id, i.name AS item_name, i.unit, s.quantity_on_hand
             FROM inventory_stock s JOIN items i ON i.id = s.item_id
             WHERE s.branch_id = ? AND s.quantity_on_hand > 0 ORDER BY i.name'
        );
        $stockStmt->execute([$branchId]);

        jsonResponse(true, '', ['units' => $unitStmt->fetchAll(), 'stock' => $stockStmt->fetchAll()]);
    }

    $direction = trim($_GET['direction'] ?? '');
    $sql = 'SELECT st.transfer_number, st.from_branch_id, fb.name AS from_branch_name,
                   st.to_branch_id, tb.name AS to_branch_name, st.status,
                   ru.full_name AS requested_by_name, st.date_created, st.created_at
            FROM stock_transfers st
            JOIN branches fb ON fb.id = st.from_branch_id
            JOIN branches tb ON tb.id = st.to_branch_id
            JOIN users ru ON ru.id = st.requested_by
            WHERE st.is_deleted = 0';
    $params = [];
    if ($scope !== null) {
        if ($direction === 'outgoing') { $sql .= ' AND st.from_branch_id = ?'; $params[] = $scope; }
        elseif ($direction === 'incoming') { $sql .= ' AND st.to_branch_id = ?'; $params[] = $scope; }
        else { $sql .= ' AND (st.from_branch_id = ? OR st.to_branch_id = ?)'; $params[] = $scope; $params[] = $scope; }
    }
    $sql .= ' ORDER BY st.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['transfers' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $fromBranchId = $scope !== null ? $scope : (int)($data['from_branch_id'] ?? 0);
        if (!$fromBranchId) jsonResponse(false, 'Source branch is required.');
        if ($scope !== null && $fromBranchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

        $toBranchId = (int)($data['to_branch_id'] ?? 0);
        if (!$toBranchId) jsonResponse(false, 'Destination branch is required.');
        if ($toBranchId === $fromBranchId) jsonResponse(false, 'Source and destination branch must differ.');
        $toBranchStmt = $db->prepare('SELECT COUNT(*) FROM branches WHERE id = ? AND is_active = 1');
        $toBranchStmt->execute([$toBranchId]);
        if (!$toBranchStmt->fetchColumn()) jsonResponse(false, 'Destination branch not found.');

        $lines = is_array($data['items'] ?? null) ? $data['items'] : [];
        if (!$lines) jsonResponse(false, 'At least one item is required.');

        $unitCheck  = $db->prepare("SELECT u.id, i.name, i.category_id FROM inventory_units u JOIN items i ON i.id = u.item_id WHERE u.id = ? AND u.branch_id = ? AND u.status = 'in_stock' AND u.is_deleted = 0");
        $stockCheck = $db->prepare('SELECT s.quantity_on_hand, i.name FROM inventory_stock s JOIN items i ON i.id = s.item_id WHERE s.item_id = ? AND s.branch_id = ?');

        $prepared = [];
        $seenUnitIds = [];
        $stockTotals = [];
        $stockAvailable = [];
        foreach ($lines as $l) {
            if (!empty($l['inventory_unit_id'])) {
                $unitId = (int)$l['inventory_unit_id'];
                if (isset($seenUnitIds[$unitId])) jsonResponse(false, 'A serialized unit was selected more than once.');
                $seenUnitIds[$unitId] = true;
                $unitCheck->execute([$unitId, $fromBranchId]);
                $unit = $unitCheck->fetch();
                if (!$unit) jsonResponse(false, 'One of the selected units is no longer available at the source branch.');
                $prepared[] = ['inventory_unit_id' => $unitId, 'item_id' => null, 'quantity' => null, 'description_snapshot' => $unit['name']];
            } elseif (!empty($l['item_id']) && (float)($l['quantity'] ?? 0) > 0) {
                $itemId = (int)$l['item_id'];
                $qty    = (float)$l['quantity'];
                $stockCheck->execute([$itemId, $fromBranchId]);
                $stock = $stockCheck->fetch();
                if (!$stock || (float)$stock['quantity_on_hand'] < $qty) jsonResponse(false, 'Not enough stock on hand for one of the selected items.');
                $stockTotals[$itemId] = ($stockTotals[$itemId] ?? 0) + $qty;
                $stockAvailable[$itemId] = (float)$stock['quantity_on_hand'];
                $prepared[] = ['inventory_unit_id' => null, 'item_id' => $itemId, 'quantity' => $qty, 'description_snapshot' => $stock['name']];
            }
        }
        if (!$prepared) jsonResponse(false, 'At least one valid item is required.');
        foreach ($stockTotals as $itemId => $qty) {
            if ($qty > ($stockAvailable[$itemId] ?? 0) + 0.0001) {
                jsonResponse(false, 'Not enough stock on hand for one of the selected items.');
            }
        }

        $db->beginTransaction();
        try {
            $transferNumber = mintDocNumber($db, $fromBranchId, 'ST');
            $db->prepare(
                'INSERT INTO stock_transfers (transfer_number, from_branch_id, to_branch_id, requested_by, status, date_created)
                 VALUES (?,?,?,?,?,NOW())'
            )->execute([$transferNumber, $fromBranchId, $toBranchId, $user['id'], 'pending']);

            $itemStmt = $db->prepare(
                'INSERT INTO stock_transfer_items (transfer_number, inventory_unit_id, item_id, quantity, description_snapshot, serial_snapshot)
                 VALUES (?,?,?,?,?, (SELECT serial_number FROM inventory_units WHERE id = ?))'
            );
            foreach ($prepared as $p) {
                $itemStmt->execute([$transferNumber, $p['inventory_unit_id'], $p['item_id'], $p['quantity'], $p['description_snapshot'], $p['inventory_unit_id']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Stock transfer create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to create transfer request.');
        }

        logAudit('CREATE_STOCK_TRANSFER', 'stock_transfer', "Requested {$transferNumber}");
        notifyRoles(['branch_admin', 'super_admin'], 'Stock transfer requested', "{$transferNumber} is awaiting shipment", 'stock_transfers.php');
        jsonResponse(true, 'Transfer request created.', ['transfer_number' => $transferNumber]);
    }

    // Every action below targets an existing transfer.
    $transferNumber = trim($data['transfer_number'] ?? '');
    if ($transferNumber === '') jsonResponse(false, 'Invalid transfer.');
    $t = loadTransfer($db, $transferNumber);
    if (!$t) jsonResponse(false, 'Transfer not found.');
    if (!transferInScope($scope, (int)$t['from_branch_id'], (int)$t['to_branch_id'])) jsonResponse(false, 'Access denied.');

    if ($action === 'ship') {
        if (!$isAdminTier) jsonResponse(false, 'Only administrators can ship a transfer.');
        if ($scope !== null && $scope !== (int)$t['from_branch_id']) jsonResponse(false, 'Only the source branch can ship this transfer.');
        if ($t['status'] !== 'pending') jsonResponse(false, 'Only a pending transfer can be shipped.');

        $db->beginTransaction();
        try {
            foreach ($t['items'] as $line) {
                if ($line['inventory_unit_id']) {
                    $chk = $db->prepare("SELECT status FROM inventory_units WHERE id = ? AND branch_id = ? AND is_deleted = 0");
                    $chk->execute([$line['inventory_unit_id'], $t['from_branch_id']]);
                    $st = $chk->fetchColumn();
                    if ($st !== 'in_stock') { $db->rollBack(); jsonResponse(false, "Unit for \"{$line['description_snapshot']}\" is no longer available to ship."); }
                    $db->prepare("UPDATE inventory_units SET status = 'in_transit' WHERE id = ?")->execute([$line['inventory_unit_id']]);
                } else {
                    $chk = $db->prepare('SELECT quantity_on_hand FROM inventory_stock WHERE item_id = ? AND branch_id = ?');
                    $chk->execute([$line['item_id'], $t['from_branch_id']]);
                    $onHand = (float)$chk->fetchColumn();
                    if ($onHand < (float)$line['quantity']) { $db->rollBack(); jsonResponse(false, "Not enough stock left for \"{$line['description_snapshot']}\" to ship."); }
                    $db->prepare('UPDATE inventory_stock SET quantity_on_hand = quantity_on_hand - ? WHERE item_id = ? AND branch_id = ?')
                       ->execute([$line['quantity'], $line['item_id'], $t['from_branch_id']]);
                }
            }
            $db->prepare("UPDATE stock_transfers SET status = 'in_transit', approved_by = ? WHERE transfer_number = ?")
               ->execute([$user['id'], $transferNumber]);
            $db->commit();

            foreach (array_unique(array_column(array_filter($t['items'], fn($l) => $l['item_id']), 'item_id')) as $touchedItemId) {
                checkLowStock($db, (int)$touchedItemId, (int)$t['from_branch_id']);
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Stock transfer ship failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to ship transfer.');
        }

        logAudit('SHIP_STOCK_TRANSFER', 'stock_transfer', "Shipped {$transferNumber}");
        notifyRoles(['branch_admin', 'super_admin'], 'Stock transfer in transit', "{$transferNumber} is on its way to {$t['to_branch_name']}", 'stock_transfers.php');
        jsonResponse(true, 'Transfer marked as in transit.');
    }

    if ($action === 'receive') {
        if (!$isAdminTier) jsonResponse(false, 'Only administrators can receive a transfer.');
        if ($scope !== null && $scope !== (int)$t['to_branch_id']) jsonResponse(false, 'Only the destination branch can receive this transfer.');
        if ($t['status'] !== 'in_transit') jsonResponse(false, 'Only an in-transit transfer can be received.');

        $db->beginTransaction();
        try {
            foreach ($t['items'] as $line) {
                if ($line['inventory_unit_id']) {
                    $db->prepare("UPDATE inventory_units SET branch_id = ?, status = 'in_stock' WHERE id = ?")
                       ->execute([$t['to_branch_id'], $line['inventory_unit_id']]);
                } else {
                    $db->prepare(
                        'INSERT INTO inventory_stock (item_id, branch_id, quantity_on_hand) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + VALUES(quantity_on_hand)'
                    )->execute([$line['item_id'], $t['to_branch_id'], $line['quantity']]);
                }
            }
            $db->prepare("UPDATE stock_transfers SET status = 'received', date_received = NOW() WHERE transfer_number = ?")
               ->execute([$transferNumber]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Stock transfer receive failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to receive transfer.');
        }

        logAudit('RECEIVE_STOCK_TRANSFER', 'stock_transfer', "Received {$transferNumber}");
        notifyRoles(['branch_admin', 'super_admin'], 'Stock transfer received', "{$transferNumber} has arrived at {$t['to_branch_name']}", 'stock_transfers.php');
        jsonResponse(true, 'Transfer received into branch inventory.');
    }

    if ($action === 'cancel') {
        if (!$isAdminTier) jsonResponse(false, 'Only administrators can cancel a transfer.');
        if ($scope !== null && $scope !== (int)$t['from_branch_id']) jsonResponse(false, 'Only the source branch can cancel this transfer.');
        if ($t['status'] !== 'pending') jsonResponse(false, 'Only a pending transfer can be cancelled — an in-transit transfer must be received first.');
        $db->prepare("UPDATE stock_transfers SET status = 'cancelled' WHERE transfer_number = ?")->execute([$transferNumber]);
        logAudit('CANCEL_STOCK_TRANSFER', 'stock_transfer', "Cancelled {$transferNumber}");
        jsonResponse(true, 'Transfer cancelled.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
