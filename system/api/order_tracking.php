<?php
// Order Tracking — read-only. Every document chain in this app already
// links forward via a foreign key (po_internal.po_number on receiving_reports,
// outgoing_quotation_number on po_outgoing, etc.) but each chain's stages
// only ever show up on separate pages. This just walks those same links from
// one root document and hands back the whole chain in one response, so
// "where is this order right now" doesn't mean opening several modules and
// matching numbers by eye.
//
// Internal chain:  PO (Internal) -> Receiving Report — a direct purchase,
//   no requisition/canvass step (EXECOM buys straight from its own supplier).
// Outgoing chain:  RFQ (Outgoing) -> Quotation (Outgoing) -> PO (Outgoing) -> Delivery Report + Issuance
require_once '../config.php';
requireModule('order_tracking');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();

$type = trim($_GET['type'] ?? '');
$root = trim($_GET['root'] ?? '');
if (!in_array($type, ['internal', 'outgoing'], true)) jsonResponse(false, 'Invalid tracking type.');

// Fills in how far each PO has progressed against what was ordered, so the
// tracker can show "6 of 10 received" instead of just a status word.
function poProgress(PDO $db, string $poNumber, string $itemsTable, string $movedTable, string $movedParentTable, string $movedParentKey): array {
    $ordered = $db->prepare("SELECT COALESCE(SUM(quantity),0) FROM {$itemsTable} WHERE po_number = ? AND is_deleted = 0");
    $ordered->execute([$poNumber]);
    $moved = $db->prepare(
        "SELECT COALESCE(SUM(m.quantity),0) FROM {$movedTable} m
         JOIN {$movedParentTable} p ON p.{$movedParentKey} = m.{$movedParentKey}
         WHERE p.po_number = ? AND m.is_deleted = 0 AND p.is_deleted = 0"
    );
    $moved->execute([$poNumber]);
    return ['ordered_qty' => (float)$ordered->fetchColumn(), 'moved_qty' => (float)$moved->fetchColumn()];
}

if ($type === 'internal') {
    // No root: the picker's list of every internal PO in scope, newest first.
    if ($root === '') {
        $sql = "SELECT p.po_number, p.status, p.supplier_name, b.name AS branch_name
                FROM po_internal p JOIN branches b ON b.id = p.branch_id
                WHERE p.is_deleted = 0";
        $params = [];
        if ($scope !== null) { $sql .= ' AND p.branch_id = ?'; $params[] = $scope; }
        $sql .= ' ORDER BY p.created_at DESC LIMIT 200';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['roots' => $stmt->fetchAll()]);
    }

    $stmt = $db->prepare(
        'SELECT p.*, b.name AS branch_name, u.full_name AS created_by_name
         FROM po_internal p JOIN branches b ON b.id = p.branch_id JOIN users u ON u.id = p.created_by
         WHERE p.po_number = ? AND p.is_deleted = 0'
    );
    $stmt->execute([$root]);
    $po = $stmt->fetch();
    if (!$po) jsonResponse(false, 'Purchase Order not found.');
    if ($scope !== null && (int)$po['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

    $progress = poProgress($db, $po['po_number'], 'po_internal_items', 'receiving_report_items', 'receiving_reports', 'rr_number');
    $po['ordered_qty']  = $progress['ordered_qty'];
    $po['received_qty'] = $progress['moved_qty'];

    $stmt = $db->prepare(
        'SELECT rr_number, po_number, date_received, created_at FROM receiving_reports
         WHERE is_deleted = 0 AND po_number = ? ORDER BY created_at'
    );
    $stmt->execute([$root]);
    $rrs = $stmt->fetchAll();

    jsonResponse(true, '', ['chain' => [
        'root'               => $po,
        'receiving_reports'  => $rrs,
    ]]);
}

if ($type === 'outgoing') {
    if ($root === '') {
        $sql = "SELECT r.rfq_number, r.status, r.client_name, b.name AS branch_name
                FROM outgoing_rfq r JOIN branches b ON b.id = r.branch_id
                WHERE r.is_deleted = 0";
        $params = [];
        if ($scope !== null) { $sql .= ' AND r.branch_id = ?'; $params[] = $scope; }
        $sql .= ' ORDER BY r.created_at DESC LIMIT 200';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['roots' => $stmt->fetchAll()]);
    }

    $stmt = $db->prepare(
        'SELECT r.*, b.name AS branch_name, u.full_name AS created_by_name
         FROM outgoing_rfq r JOIN branches b ON b.id = r.branch_id JOIN users u ON u.id = r.created_by
         WHERE r.rfq_number = ? AND r.is_deleted = 0'
    );
    $stmt->execute([$root]);
    $rfq = $stmt->fetch();
    if (!$rfq) jsonResponse(false, 'RFQ not found.');
    if ($scope !== null && (int)$rfq['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

    $stmt = $db->prepare(
        'SELECT quotation_number, status, total_amount, created_at FROM outgoing_quotations
         WHERE rfq_number = ? AND is_deleted = 0 ORDER BY created_at'
    );
    $stmt->execute([$root]);
    $quotations = $stmt->fetchAll();
    $quotationNumbers = array_column($quotations, 'quotation_number');

    $pos = [];
    if ($quotationNumbers) {
        $sql = 'SELECT po_number, client_name, outgoing_quotation_number, status, total_amount, created_at
                 FROM po_outgoing WHERE is_deleted = 0
                 AND outgoing_quotation_number IN (' . implode(',', array_fill(0, count($quotationNumbers), '?')) . ')
                 ORDER BY created_at';
        $stmt = $db->prepare($sql);
        $stmt->execute($quotationNumbers);
        $pos = $stmt->fetchAll();
    }
    foreach ($pos as &$po) {
        $progress = poProgress($db, $po['po_number'], 'po_outgoing_items', 'delivery_report_items', 'delivery_reports', 'dr_number');
        $po['ordered_qty']   = $progress['ordered_qty'];
        $po['delivered_qty'] = $progress['moved_qty'];
    }
    unset($po);
    $poNumbers = array_column($pos, 'po_number');

    $deliveryReports = [];
    $issuances = [];
    if ($poNumbers) {
        $inClause = implode(',', array_fill(0, count($poNumbers), '?'));

        $stmt = $db->prepare(
            "SELECT dr_number, po_number, date_delivered, date_accepted, created_at FROM delivery_reports
             WHERE is_deleted = 0 AND po_number IN ({$inClause}) ORDER BY created_at"
        );
        $stmt->execute($poNumbers);
        $deliveryReports = $stmt->fetchAll();

        $stmt = $db->prepare(
            "SELECT issuance_number, po_number, total_amount, date_created, created_at FROM issuances
             WHERE is_deleted = 0 AND po_number IN ({$inClause}) ORDER BY created_at"
        );
        $stmt->execute($poNumbers);
        $issuances = $stmt->fetchAll();
    }

    jsonResponse(true, '', ['chain' => [
        'root'               => $rfq,
        'quotations'         => $quotations,
        'purchase_orders'    => $pos,
        'delivery_reports'   => $deliveryReports,
        'issuances'          => $issuances,
    ]]);
}

jsonResponse(false, 'Invalid tracking type.');
