<?php
// Purchase Order — Outgoing: the client's award to EXECOM, created either by
// converting an Outgoing Quotation (api/outgoing_quotations.php) or directly
// here for an award that skipped a formal quotation. Unlike Internal PO,
// there's no "send" step — the client already sent this to EXECOM, so it
// starts straight at 'open'. Two different modules move its status forward:
// Issuance (stock actually leaving the branch for this order) carries it
// open -> partially_delivered -> delivered; Delivery Report (the client's
// signed proof of delivery) is the only thing that can close it to
// 'completed'. Admin-tier only to create/manage.
require_once '../config.php';
requireModule('po_outgoing');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadPoOutgoing(PDO $db, string $poNumber): ?array {
    $stmt = $db->prepare(
        'SELECT p.*, b.name AS branch_name, u.full_name AS created_by_name
         FROM po_outgoing p JOIN branches b ON b.id = p.branch_id JOIN users u ON u.id = p.created_by
         WHERE p.po_number = ? AND p.is_deleted = 0'
    );
    $stmt->execute([$poNumber]);
    $po = $stmt->fetch();
    if (!$po) return null;

    $itemStmt = $db->prepare(
        'SELECT pi.id, pi.item_id, i.name AS item_name, pi.description, pi.quantity, pi.unit, pi.unit_price, pi.total_price,
                COALESCE((SELECT SUM(ii.quantity) FROM issuance_items ii JOIN issuances iss ON iss.issuance_number = ii.issuance_number
                          WHERE iss.po_number = pi.po_number AND ii.item_id = pi.item_id AND ii.is_deleted = 0 AND iss.is_deleted = 0), 0)
                  + COALESCE((SELECT COUNT(*) FROM issuance_items ii2 JOIN issuances iss2 ON iss2.issuance_number = ii2.issuance_number
                              JOIN inventory_units u2 ON u2.id = ii2.inventory_unit_id
                          WHERE iss2.po_number = pi.po_number AND u2.item_id = pi.item_id AND ii2.is_deleted = 0 AND iss2.is_deleted = 0), 0) AS issued_qty,
                COALESCE((SELECT SUM(dri.quantity) FROM delivery_report_items dri JOIN delivery_reports dr ON dr.dr_number = dri.dr_number
                          WHERE dr.po_number = pi.po_number AND dri.item_id = pi.item_id AND dri.is_deleted = 0 AND dr.is_deleted = 0), 0) AS delivered_qty
         FROM po_outgoing_items pi LEFT JOIN items i ON i.id = pi.item_id
         WHERE pi.po_number = ? AND pi.is_deleted = 0 ORDER BY pi.id'
    );
    $itemStmt->execute([$poNumber]);
    $po['items'] = $itemStmt->fetchAll();
    return $po;
}

if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $po = loadPoOutgoing($db, $id);
        if (!$po) jsonResponse(false, 'Purchase Order not found.');
        if ($scope !== null && (int)$po['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');
        jsonResponse(true, '', ['po' => $po]);
    }

    if (!empty($_GET['open_for_issuance'])) {
        $sql = "SELECT po_number, client_name FROM po_outgoing WHERE status IN ('open','partially_delivered') AND is_deleted = 0";
        $params = [];
        if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['pos' => $stmt->fetchAll()]);
    }

    if (!empty($_GET['open_for_delivery'])) {
        $sql = "SELECT po_number, client_name FROM po_outgoing WHERE status IN ('open','partially_delivered','delivered') AND is_deleted = 0";
        $params = [];
        if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['pos' => $stmt->fetchAll()]);
    }

    $sql = 'SELECT p.po_number, p.branch_id, b.name AS branch_name, p.client_name, p.outgoing_quotation_number,
                   p.status, p.total_amount, u.full_name AS created_by_name, p.created_at
            FROM po_outgoing p JOIN branches b ON b.id = p.branch_id JOIN users u ON u.id = p.created_by
            WHERE p.is_deleted = 0';
    $params = [];
    if ($scope !== null) { $sql .= ' AND p.branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY p.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['pos' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage Outgoing Purchase Orders.');
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $branchId = $scope !== null ? $scope : (int)($data['branch_id'] ?? 0);
        if (!$branchId) jsonResponse(false, 'Branch is required.');
        if ($scope !== null && $branchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

        $clientId = (int)($data['client_id'] ?? 0);
        if (!$clientId) jsonResponse(false, 'Client is required.');
        $clientStmt = $db->prepare('SELECT name, address FROM clients WHERE id = ? AND is_active = 1');
        $clientStmt->execute([$clientId]);
        $client = $clientStmt->fetch();
        if (!$client) jsonResponse(false, 'Client not found.');

        $endUser         = trim($data['end_user'] ?? '') ?: null;
        $placeOfDelivery = trim($data['place_of_delivery'] ?? '') ?: null;
        $deliveryTerm    = trim($data['delivery_term'] ?? '') ?: null;
        $paymentTerm     = trim($data['payment_term'] ?? '') ?: null;
        $items           = is_array($data['items'] ?? null) ? $data['items'] : [];
        $items = array_values(array_filter($items, fn($it) =>
            trim($it['description'] ?? '') !== '' && (float)($it['quantity'] ?? 0) > 0 && (float)($it['unit_price'] ?? -1) >= 0
        ));
        if (!$items) jsonResponse(false, 'At least one item with description, quantity and unit price is required.');

        $db->beginTransaction();
        try {
            $poNumber = mintDocNumber($db, $branchId, 'POO');
            $total = array_sum(array_map(fn($it) => (float)$it['quantity'] * (float)$it['unit_price'], $items));

            $db->prepare(
                'INSERT INTO po_outgoing (po_number, branch_id, client_id, client_name, client_address, end_user,
                                           place_of_delivery, delivery_term, payment_term, status, total_amount, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $poNumber, $branchId, $clientId, $client['name'], $client['address'], $endUser,
                $placeOfDelivery, $deliveryTerm, $paymentTerm, 'open', $total, $user['id'],
            ]);

            $itemStmt = $db->prepare(
                'INSERT INTO po_outgoing_items (po_number, item_id, description, quantity, unit, unit_price, total_price)
                 VALUES (?,?,?,?,?,?,?)'
            );
            foreach ($items as $it) {
                $itemStmt->execute([
                    $poNumber,
                    !empty($it['item_id']) ? (int)$it['item_id'] : null,
                    trim($it['description']),
                    (float)$it['quantity'],
                    trim($it['unit'] ?? '') ?: null,
                    (float)$it['unit_price'],
                    (float)$it['quantity'] * (float)$it['unit_price'],
                ]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Outgoing PO create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to create Purchase Order.');
        }

        logAudit('CREATE_PO_OUTGOING', 'po_outgoing', "Created {$poNumber} for {$client['name']}");
        jsonResponse(true, 'Purchase Order created.', ['po_number' => $poNumber]);
    }

    // Every action below targets an existing PO.
    $poNumber = trim($data['po_number'] ?? '');
    if ($poNumber === '') jsonResponse(false, 'Invalid Purchase Order.');
    $po = loadPoOutgoing($db, $poNumber);
    if (!$po) jsonResponse(false, 'Purchase Order not found.');
    if ($scope !== null && (int)$po['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

    if ($action === 'cancel') {
        if ($po['status'] !== 'open') jsonResponse(false, 'Only an open PO with nothing issued or delivered yet can be cancelled.');
        $db->prepare("UPDATE po_outgoing SET status = 'cancelled' WHERE po_number = ?")->execute([$poNumber]);
        logAudit('CANCEL_PO_OUTGOING', 'po_outgoing', "Cancelled {$poNumber}");
        jsonResponse(true, 'Purchase Order cancelled.');
    }

    if ($action === 'complete') {
        if ($po['status'] !== 'delivered') jsonResponse(false, 'Only a fully delivered PO can be marked completed.');
        $db->prepare("UPDATE po_outgoing SET status = 'completed' WHERE po_number = ?")->execute([$poNumber]);
        logAudit('COMPLETE_PO_OUTGOING', 'po_outgoing', "Completed {$poNumber}");
        notifyRoles(['branch_admin', 'super_admin'], 'Order completed', "{$poNumber} for {$po['client_name']} is fully delivered and closed", 'po_outgoing.php');
        jsonResponse(true, 'Purchase Order marked completed.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
