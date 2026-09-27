<?php
// Receiving Report — Internal: goods arriving from EXECOM's own supplier
// into a branch. Posting one writes the receiving lines AND the resulting
// inventory rows (serialized
// units or bulk stock) in one transaction, then rolls the parent PO's status
// forward. Admin-tier only.
require_once '../config.php';
requireModule('receiving_reports');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadRr(PDO $db, string $rrNumber): ?array {
    $stmt = $db->prepare(
        'SELECT r.*, b.name AS branch_name, u.full_name AS received_by_name,
                p.supplier_name, p.supplier_address
         FROM receiving_reports r JOIN branches b ON b.id = r.branch_id JOIN users u ON u.id = r.received_by
         LEFT JOIN po_internal p ON p.po_number = r.po_number
         WHERE r.rr_number = ? AND r.is_deleted = 0'
    );
    $stmt->execute([$rrNumber]);
    $rr = $stmt->fetch();
    if (!$rr) return null;

    $itemStmt = $db->prepare(
        'SELECT rri.id, rri.item_id, i.name AS item_name, i.unit, i.purchase_unit, i.purchase_conversion_factor,
                rri.quantity, rri.unit_cost, rri.serial_number
         FROM receiving_report_items rri LEFT JOIN items i ON i.id = rri.item_id
         WHERE rri.rr_number = ? AND rri.is_deleted = 0 ORDER BY rri.id'
    );
    $itemStmt->execute([$rrNumber]);
    $rr['items'] = $itemStmt->fetchAll();
    return $rr;
}

if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $rr = loadRr($db, $id);
        if (!$rr) jsonResponse(false, 'Receiving report not found.');
        if ($scope !== null && (int)$rr['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');
        jsonResponse(true, '', ['rr' => $rr]);
    }

    $sql = 'SELECT r.rr_number, r.branch_id, b.name AS branch_name, r.po_number, r.date_received,
                   u.full_name AS received_by_name, r.created_at
            FROM receiving_reports r JOIN branches b ON b.id = r.branch_id JOIN users u ON u.id = r.received_by
            WHERE r.is_deleted = 0';
    $params = [];
    if ($scope !== null) { $sql .= ' AND r.branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY r.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['receiving_reports' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can post receiving reports.');
    $data = getInput();

    $poNumber = trim($data['po_number'] ?? '');
    if ($poNumber === '') jsonResponse(false, 'A Purchase Order is required.');

    $poStmt = $db->prepare('SELECT * FROM po_internal WHERE po_number = ? AND is_deleted = 0');
    $poStmt->execute([$poNumber]);
    $po = $poStmt->fetch();
    if (!$po) jsonResponse(false, 'Purchase Order not found.');
    if ($scope !== null && (int)$po['branch_id'] !== $scope) jsonResponse(false, 'Access denied for that branch.');
    if (!in_array($po['status'], ['sent', 'partially_received'], true)) {
        jsonResponse(false, 'This Purchase Order is not open for receiving.');
    }

    $dateReceived   = inputDateTime($data['date_received'] ?? null, 'Date and time received', date('Y-m-d H:i:s'));
    $conditionNotes = trim($data['condition_notes'] ?? '');
    $lines          = is_array($data['items'] ?? null) ? $data['items'] : [];
    $lines = array_values(array_filter($lines, fn($l) => !empty($l['item_id']) && (float)($l['quantity'] ?? 0) > 0));
    if (!$lines) jsonResponse(false, 'At least one received item is required.');

    // Ordered vs. already-received quantities per item on this PO, so a
    // receiving report can never accept more than what remains outstanding.
    $orderedStmt = $db->prepare('SELECT item_id, SUM(quantity) AS qty FROM po_internal_items WHERE po_number = ? AND is_deleted = 0 AND item_id IS NOT NULL GROUP BY item_id');
    $orderedStmt->execute([$poNumber]);
    $ordered = [];
    foreach ($orderedStmt->fetchAll() as $row) { $ordered[$row['item_id']] = (float)$row['qty']; }

    $receivedStmt = $db->prepare(
        "SELECT rri.item_id, SUM(rri.quantity) AS qty FROM receiving_report_items rri
         JOIN receiving_reports rr ON rr.rr_number = rri.rr_number
         WHERE rr.po_number = ? AND rri.is_deleted = 0 AND rr.is_deleted = 0 GROUP BY rri.item_id"
    );
    $receivedStmt->execute([$poNumber]);
    $alreadyReceived = [];
    foreach ($receivedStmt->fetchAll() as $row) { $alreadyReceived[$row['item_id']] = (float)$row['qty']; }

    $lineTotals = [];
    foreach ($lines as $l) {
        $itemId = (int)$l['item_id'];
        $lineTotals[$itemId] = ($lineTotals[$itemId] ?? 0) + (float)$l['quantity'];
    }

    foreach ($lineTotals as $itemId => $receiveQty) {
        $orderedQty = $ordered[$itemId] ?? 0;
        $remaining  = $orderedQty - ($alreadyReceived[$itemId] ?? 0);
        if ($receiveQty > $remaining + 0.0001) {
            jsonResponse(false, "Cannot receive {$receiveQty}; only {$remaining} remains ordered for this item.");
        }
    }

    foreach ($lines as $l) {
        $itemId = (int)$l['item_id'];
        $orderedQty = $ordered[$itemId] ?? 0;
        $remaining  = $orderedQty - ($alreadyReceived[$itemId] ?? 0);
        if ((float)$l['quantity'] > $remaining + 0.0001) {
            jsonResponse(false, "Cannot receive more than the remaining {$remaining} ordered for this item.");
        }
    }

    $itemMetaStmt = $db->prepare('SELECT is_serialized, purchase_conversion_factor FROM items WHERE id = ?');

    $db->beginTransaction();
    try {
        $rrNumber = mintDocNumber($db, (int)$po['branch_id'], 'RR');
        $db->prepare('INSERT INTO receiving_reports (rr_number, branch_id, po_number, received_by, date_received, condition_notes) VALUES (?,?,?,?,?,?)')
           ->execute([$rrNumber, $po['branch_id'], $poNumber, $user['id'], $dateReceived, $conditionNotes ?: null]);

        $lineStmt = $db->prepare('INSERT INTO receiving_report_items (rr_number, item_id, quantity, unit_cost, serial_number) VALUES (?,?,?,?,?)');
        $unitStmt = $db->prepare(
            'INSERT INTO inventory_units (item_id, branch_id, serial_number, status, unit_cost, source_rr_number, date_received)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stockStmt = $db->prepare(
            'INSERT INTO inventory_stock (item_id, branch_id, quantity_on_hand) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + VALUES(quantity_on_hand)'
        );

        foreach ($lines as $l) {
            $itemId   = (int)$l['item_id'];
            $qty      = (float)$l['quantity'];
            $unitCost = (float)($l['unit_cost'] ?? 0);
            $serial   = trim($l['serial_number'] ?? '');

            $itemMetaStmt->execute([$itemId]);
            $itemMeta = $itemMetaStmt->fetch();
            $isSerialized = (bool)($itemMeta['is_serialized'] ?? false);
            $conversionFactor = (float)($itemMeta['purchase_conversion_factor'] ?? 1);

            if ($isSerialized) {
                if ($serial === '') { $db->rollBack(); jsonResponse(false, 'Serial number is required for this item.'); }
                if (abs($qty - 1.0) > 0.0001) { $db->rollBack(); jsonResponse(false, 'Add one line per unit for serial-tracked items.'); }
                $lineStmt->execute([$rrNumber, $itemId, 1, $unitCost, $serial]);
                $unitStmt->execute([$itemId, $po['branch_id'], $serial, 'in_stock', $unitCost, $rrNumber, $dateReceived]);
            } else {
                // receiving_report_items.quantity stays in purchase-unit terms
                // (matching po_internal_items.quantity, for the ordered-vs-
                // received reconciliation above) — only the stock credit
                // itself converts to the item's actual sell/stock unit.
                $lineStmt->execute([$rrNumber, $itemId, $qty, $unitCost, null]);
                $stockStmt->execute([$itemId, $po['branch_id'], $qty * $conversionFactor]);
            }
        }

        // Roll the PO's status forward: fully received once every line's
        // running total matches what was ordered.
        $newReceivedStmt = $db->prepare(
            "SELECT rri.item_id, SUM(rri.quantity) AS qty FROM receiving_report_items rri
             JOIN receiving_reports rr ON rr.rr_number = rri.rr_number
             WHERE rr.po_number = ? AND rri.is_deleted = 0 AND rr.is_deleted = 0 GROUP BY rri.item_id"
        );
        $newReceivedStmt->execute([$poNumber]);
        $totals = [];
        foreach ($newReceivedStmt->fetchAll() as $row) { $totals[$row['item_id']] = (float)$row['qty']; }

        $fullyReceived = true;
        foreach ($ordered as $itemId => $orderedQty) {
            if (($totals[$itemId] ?? 0) + 0.0001 < $orderedQty) { $fullyReceived = false; break; }
        }
        $db->prepare('UPDATE po_internal SET status = ? WHERE po_number = ?')
           ->execute([$fullyReceived ? 'received' : 'partially_received', $poNumber]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Receiving report post failed: ' . $e->getMessage());
        jsonResponse(false, 'Failed to post receiving report.');
    }

    logAudit('CREATE_RR', 'receiving_reports', "Posted {$rrNumber} against {$poNumber}");
    if ($fullyReceived) {
        notifyRoles(['branch_admin', 'super_admin'], 'Purchase Order fully received', "{$poNumber} from {$po['supplier_name']} is complete", 'po_internal.php');
    }
    jsonResponse(true, 'Receiving report posted.', ['rr_number' => $rrNumber]);
}

jsonResponse(false, 'Method not allowed.');
