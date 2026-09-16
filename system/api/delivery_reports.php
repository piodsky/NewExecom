<?php
// Delivery Report — Outgoing: EXECOM's proof of delivery to a client,
// countersigned by the client's own representative. This is paperwork, not
// a stock event — the stock for an Outgoing PO actually leaves the branch
// through Issuance (api/issuances.php), which is what carries the PO from
// open through partially_delivered/delivered. A Delivery Report only ever
// pushes a PO from delivered to completed, once every line is covered on
// paper AND the client has signed for it (received_by + date_accepted).
require_once '../config.php';
requireModule('delivery_reports');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadDr(PDO $db, string $drNumber): ?array {
    $stmt = $db->prepare(
        'SELECT d.*, b.name AS branch_name, u.full_name AS delivered_by_name
         FROM delivery_reports d JOIN branches b ON b.id = d.branch_id JOIN users u ON u.id = d.delivered_by
         WHERE d.dr_number = ? AND d.is_deleted = 0'
    );
    $stmt->execute([$drNumber]);
    $dr = $stmt->fetch();
    if (!$dr) return null;

    $itemStmt = $db->prepare(
        'SELECT dri.id, dri.item_id, i.name AS item_name, dri.quantity
         FROM delivery_report_items dri JOIN items i ON i.id = dri.item_id
         WHERE dri.dr_number = ? AND dri.is_deleted = 0 ORDER BY dri.id'
    );
    $itemStmt->execute([$drNumber]);
    $dr['items'] = $itemStmt->fetchAll();
    return $dr;
}

if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $dr = loadDr($db, $id);
        if (!$dr) jsonResponse(false, 'Delivery report not found.');
        if ($scope !== null && (int)$dr['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');
        jsonResponse(true, '', ['dr' => $dr]);
    }

    $sql = 'SELECT d.dr_number, d.branch_id, b.name AS branch_name, d.po_number, d.date_delivered,
                   d.received_by, d.date_accepted, u.full_name AS delivered_by_name, d.created_at
            FROM delivery_reports d JOIN branches b ON b.id = d.branch_id JOIN users u ON u.id = d.delivered_by
            WHERE d.is_deleted = 0';
    $params = [];
    if ($scope !== null) { $sql .= ' AND d.branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY d.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['delivery_reports' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can post delivery reports.');
    $data = getInput();

    $poNumber = trim($data['po_number'] ?? '');
    if ($poNumber === '') jsonResponse(false, 'A Purchase Order is required.');

    $poStmt = $db->prepare('SELECT * FROM po_outgoing WHERE po_number = ? AND is_deleted = 0');
    $poStmt->execute([$poNumber]);
    $po = $poStmt->fetch();
    if (!$po) jsonResponse(false, 'Purchase Order not found.');
    if ($scope !== null && (int)$po['branch_id'] !== $scope) jsonResponse(false, 'Access denied for that branch.');
    if (!in_array($po['status'], ['open', 'partially_delivered', 'delivered'], true)) {
        jsonResponse(false, 'This Purchase Order is not open for delivery.');
    }

    $forwarder     = trim($data['forwarder'] ?? '');
    $dateDelivered = trim($data['date_delivered'] ?? '') ?: date('Y-m-d');
    $receivedBy    = trim($data['received_by'] ?? '');
    $dateAccepted  = trim($data['date_accepted'] ?? '') ?: null;
    $lines         = is_array($data['items'] ?? null) ? $data['items'] : [];
    $lines = array_values(array_filter($lines, fn($l) => !empty($l['item_id']) && (float)($l['quantity'] ?? 0) > 0));
    if (!$lines) jsonResponse(false, 'At least one delivered item is required.');

    // Ordered vs. already-delivered-on-paper per item, so this report can
    // never claim more was delivered than the PO actually ordered.
    $orderedStmt = $db->prepare('SELECT item_id, SUM(quantity) AS qty FROM po_outgoing_items WHERE po_number = ? AND is_deleted = 0 AND item_id IS NOT NULL GROUP BY item_id');
    $orderedStmt->execute([$poNumber]);
    $ordered = [];
    foreach ($orderedStmt->fetchAll() as $row) { $ordered[$row['item_id']] = (float)$row['qty']; }

    $deliveredStmt = $db->prepare(
        "SELECT dri.item_id, SUM(dri.quantity) AS qty FROM delivery_report_items dri
         JOIN delivery_reports dr ON dr.dr_number = dri.dr_number
         WHERE dr.po_number = ? AND dri.is_deleted = 0 AND dr.is_deleted = 0 GROUP BY dri.item_id"
    );
    $deliveredStmt->execute([$poNumber]);
    $alreadyDelivered = [];
    foreach ($deliveredStmt->fetchAll() as $row) { $alreadyDelivered[$row['item_id']] = (float)$row['qty']; }

    foreach ($lines as $l) {
        $itemId = (int)$l['item_id'];
        if (!isset($ordered[$itemId])) jsonResponse(false, 'One of the selected items is not a catalog-linked line on this PO.');
        $remaining = $ordered[$itemId] - ($alreadyDelivered[$itemId] ?? 0);
        if ((float)$l['quantity'] > $remaining + 0.0001) {
            jsonResponse(false, "Cannot deliver more than the remaining {$remaining} ordered for this item.");
        }
    }

    $db->beginTransaction();
    try {
        $drNumber = mintDocNumber($db, (int)$po['branch_id'], 'DR');
        $db->prepare(
            'INSERT INTO delivery_reports (dr_number, branch_id, po_number, delivered_by, forwarder, date_delivered, received_by, date_accepted)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$drNumber, $po['branch_id'], $poNumber, $user['id'], $forwarder ?: null, $dateDelivered, $receivedBy ?: null, $dateAccepted]);

        $lineStmt = $db->prepare('INSERT INTO delivery_report_items (dr_number, item_id, quantity) VALUES (?,?,?)');
        foreach ($lines as $l) {
            $lineStmt->execute([$drNumber, (int)$l['item_id'], (float)$l['quantity']]);
        }

        // Fully covered on paper + the client has actually signed for it —
        // that's the only thing allowed to close this PO to 'completed'.
        $newDeliveredStmt = $db->prepare(
            "SELECT dri.item_id, SUM(dri.quantity) AS qty FROM delivery_report_items dri
             JOIN delivery_reports dr ON dr.dr_number = dri.dr_number
             WHERE dr.po_number = ? AND dri.is_deleted = 0 AND dr.is_deleted = 0 GROUP BY dri.item_id"
        );
        $newDeliveredStmt->execute([$poNumber]);
        $totals = [];
        foreach ($newDeliveredStmt->fetchAll() as $row) { $totals[$row['item_id']] = (float)$row['qty']; }

        $fullyCovered = true;
        foreach ($ordered as $itemId => $orderedQty) {
            if (($totals[$itemId] ?? 0) + 0.0001 < $orderedQty) { $fullyCovered = false; break; }
        }

        if ($fullyCovered && $receivedBy !== '' && $dateAccepted && in_array($po['status'], ['delivered', 'partially_delivered', 'open'], true)) {
            $db->prepare("UPDATE po_outgoing SET status = 'completed' WHERE po_number = ?")->execute([$poNumber]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Delivery report post failed: ' . $e->getMessage());
        jsonResponse(false, 'Failed to post delivery report.');
    }

    logAudit('CREATE_DR', 'delivery_reports', "Posted {$drNumber} against {$poNumber}");
    jsonResponse(true, 'Delivery report posted.', ['dr_number' => $drNumber]);
}

jsonResponse(false, 'Method not allowed.');
