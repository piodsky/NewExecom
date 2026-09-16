<?php
// Issuance — Internal, POS-style. The other end of inventory from Receiving
// Report: this is where stock leaves the system, sold or dispensed to a
// walk-in customer (client_id null) or a client on record. Any user with
// module access can post one (front-line staff process sales); voiding one
// back out is admin-tier only, matching the correction pattern used by
// PO/RFQ cancel elsewhere.
require_once '../config.php';
requireModule('issuance');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadIssuance(PDO $db, string $issuanceNumber): ?array {
    $stmt = $db->prepare(
        'SELECT iss.*, b.name AS branch_name, c.name AS client_name, u.full_name AS issued_by_name
         FROM issuances iss
         JOIN branches b ON b.id = iss.branch_id
         LEFT JOIN clients c ON c.id = iss.client_id
         JOIN users u ON u.id = iss.issued_by
         WHERE iss.issuance_number = ? AND iss.is_deleted = 0'
    );
    $stmt->execute([$issuanceNumber]);
    $iss = $stmt->fetch();
    if (!$iss) return null;

    $itemStmt = $db->prepare(
        "SELECT ii.id, ii.inventory_unit_id, ii.item_id, ii.quantity, ii.unit_price, ii.total_price,
                COALESCE(i1.name, i2.name) AS item_name, iu.serial_number
         FROM issuance_items ii
         LEFT JOIN items i1 ON i1.id = ii.item_id
         LEFT JOIN inventory_units iu ON iu.id = ii.inventory_unit_id
         LEFT JOIN items i2 ON i2.id = iu.item_id
         WHERE ii.issuance_number = ? AND ii.is_deleted = 0 ORDER BY ii.id"
    );
    $itemStmt->execute([$issuanceNumber]);
    $iss['items'] = $itemStmt->fetchAll();
    return $iss;
}

if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $iss = loadIssuance($db, $id);
        if (!$iss) jsonResponse(false, 'Issuance not found.');
        if ($scope !== null && (int)$iss['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');
        jsonResponse(true, '', ['issuance' => $iss]);
    }

    // Sellable stock at a given branch: in-stock serial units plus bulk items
    // with quantity on hand. Same shape as api/inventory.php.
    $catalogFor = trim($_GET['catalog_for'] ?? '');
    if ($catalogFor !== '') {
        $branchId = (int)$catalogFor;
        if ($scope !== null && $branchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

        $unitStmt = $db->prepare(
            "SELECT u.id, u.item_id, i.name AS item_name, i.default_price, u.serial_number, u.unit_cost
             FROM inventory_units u JOIN items i ON i.id = u.item_id
             WHERE u.branch_id = ? AND u.status = 'in_stock' AND u.is_deleted = 0 ORDER BY i.name"
        );
        $unitStmt->execute([$branchId]);

        $stockStmt = $db->prepare(
            'SELECT s.item_id, i.name AS item_name, i.unit, i.default_price, s.quantity_on_hand
             FROM inventory_stock s JOIN items i ON i.id = s.item_id
             WHERE s.branch_id = ? AND s.quantity_on_hand > 0 ORDER BY i.name'
        );
        $stockStmt->execute([$branchId]);

        jsonResponse(true, '', ['units' => $unitStmt->fetchAll(), 'stock' => $stockStmt->fetchAll()]);
    }

    $sql = 'SELECT iss.issuance_number, iss.branch_id, b.name AS branch_name, c.name AS client_name,
                   iss.total_amount, u.full_name AS issued_by_name, iss.date_created, iss.created_at
            FROM issuances iss
            JOIN branches b ON b.id = iss.branch_id
            LEFT JOIN clients c ON c.id = iss.client_id
            JOIN users u ON u.id = iss.issued_by
            WHERE iss.is_deleted = 0';
    $params = [];
    if ($scope !== null) { $sql .= ' AND iss.branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY iss.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['issuances' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $branchId = $scope !== null ? $scope : (int)($data['branch_id'] ?? 0);
        if (!$branchId) jsonResponse(false, 'Branch is required.');
        if ($scope !== null && $branchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

        $clientId = !empty($data['client_id']) ? (int)$data['client_id'] : null;
        if ($clientId) {
            $clientCheck = $db->prepare('SELECT COUNT(*) FROM clients WHERE id = ? AND is_active = 1');
            $clientCheck->execute([$clientId]);
            if (!$clientCheck->fetchColumn()) jsonResponse(false, 'Client not found.');
        }

        $lines = is_array($data['items'] ?? null) ? $data['items'] : [];
        if (!$lines) jsonResponse(false, 'At least one item is required.');

        $unitCheck  = $db->prepare("SELECT u.id, i.name, u.unit_cost FROM inventory_units u JOIN items i ON i.id = u.item_id WHERE u.id = ? AND u.branch_id = ? AND u.status = 'in_stock' AND u.is_deleted = 0");
        $stockCheck = $db->prepare('SELECT s.quantity_on_hand, i.name FROM inventory_stock s JOIN items i ON i.id = s.item_id WHERE s.item_id = ? AND s.branch_id = ?');

        $prepared = [];
        foreach ($lines as $l) {
            $unitPrice = (float)($l['unit_price'] ?? -1);
            if ($unitPrice < 0) jsonResponse(false, 'Unit price is required for every line.');

            if (!empty($l['inventory_unit_id'])) {
                $unitId = (int)$l['inventory_unit_id'];
                $unitCheck->execute([$unitId, $branchId]);
                $unit = $unitCheck->fetch();
                if (!$unit) jsonResponse(false, 'One of the selected units is no longer available at this branch.');
                $prepared[] = ['inventory_unit_id' => $unitId, 'item_id' => null, 'quantity' => 1, 'unit_price' => $unitPrice, 'total' => $unitPrice, 'label' => $unit['name']];
            } elseif (!empty($l['item_id']) && (float)($l['quantity'] ?? 0) > 0) {
                $itemId = (int)$l['item_id'];
                $qty    = (float)$l['quantity'];
                $stockCheck->execute([$itemId, $branchId]);
                $stock = $stockCheck->fetch();
                if (!$stock || (float)$stock['quantity_on_hand'] < $qty) jsonResponse(false, 'Not enough stock on hand for one of the selected items.');
                $prepared[] = ['inventory_unit_id' => null, 'item_id' => $itemId, 'quantity' => $qty, 'unit_price' => $unitPrice, 'total' => $qty * $unitPrice, 'label' => $stock['name']];
            }
        }
        if (!$prepared) jsonResponse(false, 'At least one valid item is required.');

        $db->beginTransaction();
        try {
            $issuanceNumber = mintDocNumber($db, $branchId, 'ISS');
            $total = array_sum(array_column($prepared, 'total'));

            $db->prepare(
                'INSERT INTO issuances (issuance_number, branch_id, client_id, issued_by, date_created, total_amount)
                 VALUES (?,?,?,?,NOW(),?)'
            )->execute([$issuanceNumber, $branchId, $clientId, $user['id'], $total]);

            $itemStmt = $db->prepare(
                'INSERT INTO issuance_items (issuance_number, inventory_unit_id, item_id, quantity, unit_price, total_price)
                 VALUES (?,?,?,?,?,?)'
            );
            foreach ($prepared as $p) {
                $itemStmt->execute([$issuanceNumber, $p['inventory_unit_id'], $p['item_id'], $p['quantity'], $p['unit_price'], $p['total']]);
                if ($p['inventory_unit_id']) {
                    $db->prepare("UPDATE inventory_units SET status = 'issued', date_out = CURDATE() WHERE id = ?")->execute([$p['inventory_unit_id']]);
                } else {
                    $db->prepare('UPDATE inventory_stock SET quantity_on_hand = quantity_on_hand - ? WHERE item_id = ? AND branch_id = ?')
                       ->execute([$p['quantity'], $p['item_id'], $branchId]);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Issuance create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to post issuance.');
        }

        logAudit('CREATE_ISSUANCE', 'issuance', "Posted {$issuanceNumber} for " . number_format($total, 2));
        jsonResponse(true, 'Issuance posted.', ['issuance_number' => $issuanceNumber]);
    }

    // Every action below targets an existing issuance.
    $issuanceNumber = trim($data['issuance_number'] ?? '');
    if ($issuanceNumber === '') jsonResponse(false, 'Invalid issuance.');
    $iss = loadIssuance($db, $issuanceNumber);
    if (!$iss) jsonResponse(false, 'Issuance not found.');
    if ($scope !== null && (int)$iss['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

    if ($action === 'void') {
        if (!$isAdminTier) jsonResponse(false, 'Only administrators can void an issuance.');

        $db->beginTransaction();
        try {
            foreach ($iss['items'] as $line) {
                if ($line['inventory_unit_id']) {
                    $db->prepare("UPDATE inventory_units SET status = 'in_stock', date_out = NULL WHERE id = ?")->execute([$line['inventory_unit_id']]);
                } else {
                    $db->prepare('UPDATE inventory_stock SET quantity_on_hand = quantity_on_hand + ? WHERE item_id = ? AND branch_id = ?')
                       ->execute([$line['quantity'], $line['item_id'], $iss['branch_id']]);
                }
            }
            $db->prepare('UPDATE issuances SET is_deleted = 1 WHERE issuance_number = ?')->execute([$issuanceNumber]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Issuance void failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to void issuance.');
        }

        logAudit('VOID_ISSUANCE', 'issuance', "Voided {$issuanceNumber}");
        jsonResponse(true, 'Issuance voided and stock restored.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
