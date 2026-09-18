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
        'SELECT iss.*, b.name AS branch_name,
                COALESCE(c.name, iss.walkin_name) AS client_name,
                COALESCE(c.address, iss.walkin_address) AS client_address,
                COALESCE(c.client_type, IF(iss.walkin_name IS NOT NULL, \'walkin\', NULL)) AS client_type,
                u.full_name AS issued_by_name
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

// Spreads an optional whole-sale discount across each prepared line's total
// (and recomputed unit_price), in integer cents, so the last line absorbs
// any rounding remainder and SUM(line totals) still lands exactly on
// subtotal - discount. Keeps issuance_items.total_price as the true "what we
// sold it for" (see api/cost_profit_reports.php) even with a discount applied.
function applyDiscountToLines(array $prepared, float $discount): array {
    if ($discount <= 0) return $prepared;
    $totalCents = 0;
    foreach ($prepared as $p) $totalCents += (int)round($p['total'] * 100);
    if ($totalCents <= 0) return $prepared;
    $discountCents = min((int)round($discount * 100), $totalCents);
    $remaining = $discountCents;
    $count = count($prepared);
    $i = 0;
    foreach ($prepared as &$p) {
        $i++;
        $lineCents = (int)round($p['total'] * 100);
        $share = $i === $count ? $remaining : (int)floor($discountCents * $lineCents / $totalCents);
        $remaining -= $share;
        $newLineCents = $lineCents - $share;
        $p['total'] = $newLineCents / 100;
        $p['unit_price'] = $p['quantity'] > 0 ? round($p['total'] / $p['quantity'], 2) : $p['total'];
    }
    unset($p);
    return $prepared;
}

// Rolls a linked Outgoing PO's status forward or back based on how much of
// it has actually left the branch so far. Never touches a PO that's already
// completed or cancelled — completed is Delivery Report's call to make, and
// a cancelled PO shouldn't come back to life just because a void ran.
function recomputePoOutgoingStatus(PDO $db, string $poNumber): void {
    $poStmt = $db->prepare('SELECT status FROM po_outgoing WHERE po_number = ? AND is_deleted = 0');
    $poStmt->execute([$poNumber]);
    $status = $poStmt->fetchColumn();
    if ($status === false || in_array($status, ['completed', 'cancelled'], true)) return;

    $orderedStmt = $db->prepare('SELECT item_id, SUM(quantity) AS qty FROM po_outgoing_items WHERE po_number = ? AND is_deleted = 0 AND item_id IS NOT NULL GROUP BY item_id');
    $orderedStmt->execute([$poNumber]);
    $ordered = [];
    foreach ($orderedStmt->fetchAll() as $row) { $ordered[$row['item_id']] = (float)$row['qty']; }
    if (!$ordered) return;

    $issuedStmt = $db->prepare(
        "SELECT COALESCE(ii.item_id, u.item_id) AS item_id, SUM(ii.quantity) AS qty
         FROM issuance_items ii
         JOIN issuances iss ON iss.issuance_number = ii.issuance_number
         LEFT JOIN inventory_units u ON u.id = ii.inventory_unit_id
         WHERE iss.po_number = ? AND ii.is_deleted = 0 AND iss.is_deleted = 0
         GROUP BY COALESCE(ii.item_id, u.item_id)"
    );
    $issuedStmt->execute([$poNumber]);
    $issued = [];
    foreach ($issuedStmt->fetchAll() as $row) { $issued[$row['item_id']] = (float)$row['qty']; }

    $anyIssued = false; $fullyIssued = true;
    foreach ($ordered as $itemId => $qty) {
        $got = $issued[$itemId] ?? 0;
        if ($got > 0) $anyIssued = true;
        if ($got + 0.0001 < $qty) $fullyIssued = false;
    }

    $newStatus = $fullyIssued ? 'delivered' : ($anyIssued ? 'partially_delivered' : 'open');
    $db->prepare('UPDATE po_outgoing SET status = ? WHERE po_number = ?')->execute([$newStatus, $poNumber]);
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

        // sku/serial_number double as this branch's barcode values — a
        // scanned code is matched against them client-side on the POS screen
        // rather than requiring a separate barcode column.
        $unitStmt = $db->prepare(
            "SELECT u.id, u.item_id, i.name AS item_name, i.sku, i.unit, i.default_price, ic.name AS category_name,
                    u.serial_number, u.unit_cost
             FROM inventory_units u JOIN items i ON i.id = u.item_id
             LEFT JOIN item_categories ic ON ic.id = i.category_id
             WHERE u.branch_id = ? AND u.status = 'in_stock' AND u.is_deleted = 0 ORDER BY i.name"
        );
        $unitStmt->execute([$branchId]);

        $stockStmt = $db->prepare(
            'SELECT s.item_id, i.name AS item_name, i.sku, i.unit, i.default_price, ic.name AS category_name,
                    s.quantity_on_hand
             FROM inventory_stock s JOIN items i ON i.id = s.item_id
             LEFT JOIN item_categories ic ON ic.id = i.category_id
             WHERE s.branch_id = ? AND s.quantity_on_hand > 0 ORDER BY i.name'
        );
        $stockStmt->execute([$branchId]);

        jsonResponse(true, '', ['units' => $unitStmt->fetchAll(), 'stock' => $stockStmt->fetchAll()]);
    }

    $sql = 'SELECT iss.issuance_number, iss.branch_id, b.name AS branch_name,
                   COALESCE(c.name, iss.walkin_name) AS client_name,
                   COALESCE(c.client_type, IF(iss.walkin_name IS NOT NULL, \'walkin\', NULL)) AS client_type,
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

        // Fulfilling a specific Outgoing PO (a government/client order) rather
        // than a walk-in sale — restricts lines to that PO's own catalog
        // items and, once posted, rolls the PO's status forward.
        $poNumber = trim($data['po_number'] ?? '') ?: null;
        $poItemIds = null;
        $poRemaining = null;
        if ($poNumber) {
            $poStmt = $db->prepare('SELECT * FROM po_outgoing WHERE po_number = ? AND is_deleted = 0');
            $poStmt->execute([$poNumber]);
            $poRow = $poStmt->fetch();
            if (!$poRow) jsonResponse(false, 'Purchase Order not found.');
            if ($scope !== null && (int)$poRow['branch_id'] !== $scope) jsonResponse(false, 'Access denied for that branch.');
            if ((int)$poRow['branch_id'] !== $branchId) jsonResponse(false, 'That PO belongs to a different branch.');
            if (!in_array($poRow['status'], ['open', 'partially_delivered'], true)) jsonResponse(false, 'This Purchase Order is not open for issuance.');

            $poItemStmt = $db->prepare('SELECT DISTINCT item_id FROM po_outgoing_items WHERE po_number = ? AND is_deleted = 0 AND item_id IS NOT NULL');
            $poItemStmt->execute([$poNumber]);
            $poItemIds = array_map('intval', $poItemStmt->fetchAll(PDO::FETCH_COLUMN));

            $orderedStmt = $db->prepare('SELECT item_id, SUM(quantity) AS qty FROM po_outgoing_items WHERE po_number = ? AND is_deleted = 0 AND item_id IS NOT NULL GROUP BY item_id');
            $orderedStmt->execute([$poNumber]);
            $ordered = [];
            foreach ($orderedStmt->fetchAll() as $row) { $ordered[(int)$row['item_id']] = (float)$row['qty']; }

            $issuedStmt = $db->prepare(
                "SELECT COALESCE(ii.item_id, u.item_id) AS item_id, SUM(ii.quantity) AS qty
                 FROM issuance_items ii
                 JOIN issuances iss ON iss.issuance_number = ii.issuance_number
                 LEFT JOIN inventory_units u ON u.id = ii.inventory_unit_id
                 WHERE iss.po_number = ? AND ii.is_deleted = 0 AND iss.is_deleted = 0
                 GROUP BY COALESCE(ii.item_id, u.item_id)"
            );
            $issuedStmt->execute([$poNumber]);
            $issued = [];
            foreach ($issuedStmt->fetchAll() as $row) { $issued[(int)$row['item_id']] = (float)$row['qty']; }

            $poRemaining = [];
            foreach ($ordered as $itemId => $orderedQty) {
                $poRemaining[$itemId] = max(0, $orderedQty - ($issued[$itemId] ?? 0));
            }
        }

        $clientId = !empty($data['client_id']) ? (int)$data['client_id'] : ($poNumber ? $poRow['client_id'] : null);
        if ($clientId) {
            $clientCheck = $db->prepare('SELECT COUNT(*) FROM clients WHERE id = ? AND is_active = 1');
            $clientCheck->execute([$clientId]);
            if (!$clientCheck->fetchColumn()) jsonResponse(false, 'Client not found.');
        }

        // No client on record — this is a walk-in sale. Name is the only
        // thing the printed slip actually needs; address and contact number
        // are nice-to-have and stay optional. The name/address/contact are
        // also written onto the issuance itself as a print-safe snapshot,
        // same pattern as the client_name/client_address snapshot on the
        // outgoing documents.
        $walkinName = $walkinAddress = $walkinContact = null;
        if (!$clientId) {
            $walkinName = trim($data['walkin_name'] ?? '');
            if ($walkinName === '') jsonResponse(false, 'Buyer name is required for a walk-in sale.');
            $walkinAddress = trim($data['walkin_address'] ?? '') ?: null;
            $walkinContact = trim($data['walkin_contact'] ?? '') ?: null;
        }

        $lines = is_array($data['items'] ?? null) ? $data['items'] : [];
        if (!$lines) jsonResponse(false, 'At least one item is required.');

        $unitCheck  = $db->prepare("SELECT u.id, u.item_id, i.name, u.unit_cost FROM inventory_units u JOIN items i ON i.id = u.item_id WHERE u.id = ? AND u.branch_id = ? AND u.status = 'in_stock' AND u.is_deleted = 0");
        $stockCheck = $db->prepare('SELECT s.quantity_on_hand, i.name FROM inventory_stock s JOIN items i ON i.id = s.item_id WHERE s.item_id = ? AND s.branch_id = ?');

        $prepared = [];
        $seenUnitIds = [];
        $stockTotals = [];
        $stockAvailable = [];
        $poLineTotals = [];
        foreach ($lines as $l) {
            $unitPrice = (float)($l['unit_price'] ?? -1);
            if ($unitPrice < 0) jsonResponse(false, 'Unit price is required for every line.');

            if (!empty($l['inventory_unit_id'])) {
                $unitId = (int)$l['inventory_unit_id'];
                if (isset($seenUnitIds[$unitId])) jsonResponse(false, 'A serialized unit was selected more than once.');
                $seenUnitIds[$unitId] = true;
                $unitCheck->execute([$unitId, $branchId]);
                $unit = $unitCheck->fetch();
                if (!$unit) jsonResponse(false, 'One of the selected units is no longer available at this branch.');
                if ($poItemIds !== null && !in_array((int)$unit['item_id'], $poItemIds, true)) jsonResponse(false, 'That unit is not one of the items ordered on this PO.');
                if ($poRemaining !== null) $poLineTotals[(int)$unit['item_id']] = ($poLineTotals[(int)$unit['item_id']] ?? 0) + 1;
                $prepared[] = ['inventory_unit_id' => $unitId, 'item_id' => null, 'quantity' => 1, 'unit_price' => $unitPrice, 'total' => $unitPrice, 'label' => $unit['name']];
            } elseif (!empty($l['item_id']) && (float)($l['quantity'] ?? 0) > 0) {
                $itemId = (int)$l['item_id'];
                $qty    = (float)$l['quantity'];
                if ($poItemIds !== null && !in_array($itemId, $poItemIds, true)) jsonResponse(false, 'That item is not one of the items ordered on this PO.');
                $stockCheck->execute([$itemId, $branchId]);
                $stock = $stockCheck->fetch();
                if (!$stock || (float)$stock['quantity_on_hand'] < $qty) jsonResponse(false, 'Not enough stock on hand for one of the selected items.');
                $stockTotals[$itemId] = ($stockTotals[$itemId] ?? 0) + $qty;
                $stockAvailable[$itemId] = (float)$stock['quantity_on_hand'];
                if ($poRemaining !== null) $poLineTotals[$itemId] = ($poLineTotals[$itemId] ?? 0) + $qty;
                $prepared[] = ['inventory_unit_id' => null, 'item_id' => $itemId, 'quantity' => $qty, 'unit_price' => $unitPrice, 'total' => $qty * $unitPrice, 'label' => $stock['name']];
            }
        }
        if (!$prepared) jsonResponse(false, 'At least one valid item is required.');
        foreach ($stockTotals as $itemId => $qty) {
            if ($qty > ($stockAvailable[$itemId] ?? 0) + 0.0001) {
                jsonResponse(false, 'Not enough stock on hand for one of the selected items.');
            }
        }
        foreach ($poLineTotals as $itemId => $qty) {
            $remaining = $poRemaining[$itemId] ?? 0;
            if ($qty > $remaining + 0.0001) {
                jsonResponse(false, "Cannot issue {$qty}; only {$remaining} remains ordered for this PO item.");
            }
        }

        // Optional whole-sale discount (e.g. senior/PWD or a loyal-customer
        // deduction) — not per line, applied once at checkout.
        $discountRaw = trim((string)($data['discount_amount'] ?? '0'));
        if ($discountRaw === '') $discountRaw = '0';
        if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $discountRaw)) {
            jsonResponse(false, 'Discount must be zero or greater, with at most two decimal places.');
        }
        $discount = (float)$discountRaw;
        $subtotal = array_sum(array_column($prepared, 'total'));
        if ($discount > $subtotal + 0.0001) jsonResponse(false, 'Discount cannot exceed the subtotal.');
        $prepared = applyDiscountToLines($prepared, $discount);

        $db->beginTransaction();
        try {
            $issuanceNumber = mintDocNumber($db, $branchId, 'ISS');
            $total = array_sum(array_column($prepared, 'total'));

            // Match the walk-in buyer to one already on file (by name), or
            // register them — so next time this same buyer shows up in the
            // POS client dropdown and in Client Management, instead of being
            // typed in from scratch every visit.
            if (!$clientId && $walkinName !== '') {
                $matchStmt = $db->prepare("SELECT id FROM clients WHERE client_type = 'walkin' AND name = ? LIMIT 1");
                $matchStmt->execute([$walkinName]);
                $clientId = $matchStmt->fetchColumn() ?: null;
                if (!$clientId) {
                    $db->prepare(
                        "INSERT INTO clients (name, address, contact_number, client_type) VALUES (?,?,?,'walkin')"
                    )->execute([$walkinName, $walkinAddress, $walkinContact]);
                    $clientId = (int)$db->lastInsertId();
                }
            }

            $db->prepare(
                'INSERT INTO issuances (issuance_number, branch_id, client_id, walkin_name, walkin_address, walkin_contact, po_number, issued_by, date_created, total_amount, discount_amount)
                 VALUES (?,?,?,?,?,?,?,?,NOW(),?,?)'
            )->execute([$issuanceNumber, $branchId, $clientId, $walkinName, $walkinAddress, $walkinContact, $poNumber, $user['id'], $total, $discount]);

            $itemStmt = $db->prepare(
                'INSERT INTO issuance_items (issuance_number, inventory_unit_id, item_id, quantity, unit_price, total_price)
                 VALUES (?,?,?,?,?,?)'
            );
            foreach ($prepared as $p) {
                $itemStmt->execute([$issuanceNumber, $p['inventory_unit_id'], $p['item_id'], $p['quantity'], $p['unit_price'], $p['total']]);
                if ($p['inventory_unit_id']) {
                    $db->prepare("UPDATE inventory_units SET status = 'issued', date_out = NOW() WHERE id = ?")->execute([$p['inventory_unit_id']]);
                } else {
                    $db->prepare('UPDATE inventory_stock SET quantity_on_hand = quantity_on_hand - ? WHERE item_id = ? AND branch_id = ?')
                       ->execute([$p['quantity'], $p['item_id'], $branchId]);
                }
            }
            if ($poNumber) recomputePoOutgoingStatus($db, $poNumber);
            $db->commit();

            foreach (array_unique(array_column(array_filter($prepared, fn($p) => $p['item_id']), 'item_id')) as $touchedItemId) {
                checkLowStock($db, (int)$touchedItemId, $branchId);
            }
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
            if ($iss['po_number']) recomputePoOutgoingStatus($db, $iss['po_number']);
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
