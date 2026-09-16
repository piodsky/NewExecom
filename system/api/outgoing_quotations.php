<?php
// Quotation — Outgoing: EXECOM's priced reply to a client, either logged
// against an Outgoing RFQ (canvass received) or written up directly for a
// client who never sent a formal canvass. Converting one creates a single
// Outgoing PO — unlike Internal RFQ's multi-supplier split, there's exactly
// one client per quotation, so no grouping is needed. Admin-tier only.
require_once '../config.php';
requireModule('outgoing_quotations');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadQuotation(PDO $db, string $quotationNumber): ?array {
    $stmt = $db->prepare(
        'SELECT q.*, b.name AS branch_name, u.full_name AS prepared_by_name
         FROM outgoing_quotations q JOIN branches b ON b.id = q.branch_id LEFT JOIN users u ON u.id = q.prepared_by
         WHERE q.quotation_number = ? AND q.is_deleted = 0'
    );
    $stmt->execute([$quotationNumber]);
    $q = $stmt->fetch();
    if (!$q) return null;

    $itemStmt = $db->prepare(
        'SELECT qi.id, qi.item_id, i.name AS item_name, qi.description, qi.quantity, qi.unit, qi.unit_price, qi.total_price
         FROM outgoing_quotation_items qi LEFT JOIN items i ON i.id = qi.item_id
         WHERE qi.quotation_number = ? AND qi.is_deleted = 0 ORDER BY qi.id'
    );
    $itemStmt->execute([$quotationNumber]);
    $q['items'] = $itemStmt->fetchAll();
    return $q;
}

if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $q = loadQuotation($db, $id);
        if (!$q) jsonResponse(false, 'Quotation not found.');
        if ($scope !== null && (int)$q['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');
        jsonResponse(true, '', ['quotation' => $q]);
    }

    $sql = 'SELECT q.quotation_number, q.branch_id, b.name AS branch_name, q.client_name, q.rfq_number,
                   q.total_amount, q.status, u.full_name AS prepared_by_name, q.created_at
            FROM outgoing_quotations q JOIN branches b ON b.id = q.branch_id LEFT JOIN users u ON u.id = q.prepared_by
            WHERE q.is_deleted = 0';
    $params = [];
    if ($scope !== null) { $sql .= ' AND q.branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY q.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['quotations' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage outgoing quotations.');
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $rfqNumber = trim($data['rfq_number'] ?? '') ?: null;
        $branchId  = null; $clientId = null; $clientName = null; $clientAddress = null;
        $items     = is_array($data['items'] ?? null) ? $data['items'] : [];

        if ($rfqNumber) {
            $rfqStmt = $db->prepare("SELECT * FROM outgoing_rfq WHERE rfq_number = ? AND is_deleted = 0");
            $rfqStmt->execute([$rfqNumber]);
            $rfq = $rfqStmt->fetch();
            if (!$rfq) jsonResponse(false, 'RFQ not found.');
            if ($rfq['status'] !== 'open') jsonResponse(false, 'Only an open RFQ can be quoted.');
            if ($scope !== null && (int)$rfq['branch_id'] !== $scope) jsonResponse(false, 'Access denied for that branch.');

            $branchId = (int)$rfq['branch_id'];
            $clientId = $rfq['client_id']; $clientName = $rfq['client_name']; $clientAddress = $rfq['client_address'];

            $rfqItemStmt = $db->prepare('SELECT item_id, description, quantity, unit FROM outgoing_rfq_items WHERE rfq_number = ? AND is_deleted = 0');
            $rfqItemStmt->execute([$rfqNumber]);
            $rfqItems = $rfqItemStmt->fetchAll();
            // Merge RFQ line quantities/descriptions with whatever unit prices were posted for them, matched by position.
            foreach ($rfqItems as $i => $ri) {
                $items[$i] = ['item_id' => $ri['item_id'], 'description' => $ri['description'], 'quantity' => $ri['quantity'], 'unit' => $ri['unit'], 'unit_price' => $items[$i]['unit_price'] ?? null];
            }
        } else {
            $branchId = $scope !== null ? $scope : (int)($data['branch_id'] ?? 0);
            if (!$branchId) jsonResponse(false, 'Branch is required.');
            if ($scope !== null && $branchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

            $clientId = (int)($data['client_id'] ?? 0);
            if (!$clientId) jsonResponse(false, 'Client is required.');
            $clientStmt = $db->prepare('SELECT name, address FROM clients WHERE id = ? AND is_active = 1');
            $clientStmt->execute([$clientId]);
            $client = $clientStmt->fetch();
            if (!$client) jsonResponse(false, 'Client not found.');
            $clientName = $client['name']; $clientAddress = $client['address'];
        }

        $items = array_values(array_filter($items, fn($it) =>
            trim($it['description'] ?? '') !== '' && (float)($it['quantity'] ?? 0) > 0 && (float)($it['unit_price'] ?? -1) >= 0
        ));
        if (!$items) jsonResponse(false, 'At least one item with a description, quantity and unit price is required.');

        $priceValidity = $data['price_validity_days'] !== '' && isset($data['price_validity_days']) ? (int)$data['price_validity_days'] : null;
        $leadTime      = trim($data['lead_time'] ?? '') ?: null;

        $db->beginTransaction();
        try {
            $quotationNumber = mintDocNumber($db, $branchId, 'OQ');
            $total = array_sum(array_map(fn($it) => (float)$it['quantity'] * (float)$it['unit_price'], $items));

            $db->prepare(
                'INSERT INTO outgoing_quotations (quotation_number, rfq_number, branch_id, client_id, client_name, client_address,
                                                    price_validity_days, lead_time, total_amount, prepared_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([$quotationNumber, $rfqNumber, $branchId, $clientId, $clientName, $clientAddress, $priceValidity, $leadTime, $total, $user['id']]);

            $itemStmt = $db->prepare(
                'INSERT INTO outgoing_quotation_items (quotation_number, item_id, description, quantity, unit, unit_price, total_price)
                 VALUES (?,?,?,?,?,?,?)'
            );
            foreach ($items as $it) {
                $itemStmt->execute([
                    $quotationNumber,
                    !empty($it['item_id']) ? (int)$it['item_id'] : null,
                    trim($it['description']),
                    (float)$it['quantity'],
                    trim($it['unit'] ?? '') ?: null,
                    (float)$it['unit_price'],
                    (float)$it['quantity'] * (float)$it['unit_price'],
                ]);
            }

            if ($rfqNumber) {
                $db->prepare("UPDATE outgoing_rfq SET status = 'quoted' WHERE rfq_number = ?")->execute([$rfqNumber]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Outgoing quotation create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to create quotation.');
        }

        logAudit('CREATE_OUTGOING_QUOTATION', 'outgoing_quotations', "Created {$quotationNumber} for {$clientName}");
        jsonResponse(true, 'Quotation created.', ['quotation_number' => $quotationNumber]);
    }

    // Every action below targets an existing quotation.
    $quotationNumber = trim($data['quotation_number'] ?? '');
    if ($quotationNumber === '') jsonResponse(false, 'Invalid quotation.');
    $q = loadQuotation($db, $quotationNumber);
    if (!$q) jsonResponse(false, 'Quotation not found.');
    if ($scope !== null && (int)$q['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

    if ($action === 'cancel') {
        if ($q['status'] !== 'active') jsonResponse(false, 'Only an active quotation can be cancelled.');
        $db->prepare("UPDATE outgoing_quotations SET status = 'cancelled' WHERE quotation_number = ?")->execute([$quotationNumber]);
        logAudit('CANCEL_OUTGOING_QUOTATION', 'outgoing_quotations', "Cancelled {$quotationNumber}");
        jsonResponse(true, 'Quotation cancelled.');
    }

    if ($action === 'convert_to_po') {
        if ($q['status'] !== 'active') jsonResponse(false, 'This quotation is not active.');
        if (!$q['items']) jsonResponse(false, 'This quotation has no items.');

        $endUser          = trim($data['end_user'] ?? '') ?: null;
        $placeOfDelivery  = trim($data['place_of_delivery'] ?? '') ?: null;
        $deliveryTerm     = trim($data['delivery_term'] ?? '') ?: null;
        $paymentTerm      = trim($data['payment_term'] ?? '') ?: null;

        $db->beginTransaction();
        try {
            $poNumber = mintDocNumber($db, (int)$q['branch_id'], 'POO');
            $db->prepare(
                'INSERT INTO po_outgoing (po_number, branch_id, client_id, client_name, client_address, outgoing_quotation_number,
                                           end_user, place_of_delivery, delivery_term, payment_term, status, total_amount, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $poNumber, $q['branch_id'], $q['client_id'], $q['client_name'], $q['client_address'], $quotationNumber,
                $endUser, $placeOfDelivery, $deliveryTerm, $paymentTerm, 'open', $q['total_amount'], $user['id'],
            ]);

            $lineStmt = $db->prepare(
                'INSERT INTO po_outgoing_items (po_number, item_id, description, quantity, unit, unit_price, total_price)
                 VALUES (?,?,?,?,?,?,?)'
            );
            foreach ($q['items'] as $l) {
                $lineStmt->execute([$poNumber, $l['item_id'], $l['description'], $l['quantity'], $l['unit'], $l['unit_price'], $l['total_price']]);
            }

            $db->prepare("UPDATE outgoing_quotations SET status = 'converted' WHERE quotation_number = ?")->execute([$quotationNumber]);
            if ($q['rfq_number']) {
                $db->prepare("UPDATE outgoing_rfq SET status = 'converted' WHERE rfq_number = ?")->execute([$q['rfq_number']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Quotation to PO conversion failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to convert quotation to Purchase Order.');
        }

        logAudit('CONVERT_QUOTATION_TO_PO', 'outgoing_quotations', "{$quotationNumber} -> {$poNumber}");
        notifyRoles(['branch_admin', 'super_admin'], 'Client order awarded', "{$quotationNumber} converted to {$poNumber} for {$q['client_name']}", 'po_outgoing.php');
        jsonResponse(true, "Converted to Outgoing PO: {$poNumber}", ['po_number' => $poNumber]);
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
