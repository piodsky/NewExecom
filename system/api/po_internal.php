<?php
// Purchase Order — Internal: EXECOM buying directly from its own supplier,
// no requisition/canvass step (mirrors Globalchips' direct PURCHTRN entry).
// Admin-tier only to create/manage.
require_once '../config.php';
requireModule('po_internal');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadPoInternal(PDO $db, string $poNumber): ?array {
    $stmt = $db->prepare(
        'SELECT p.*, b.name AS branch_name, u.full_name AS created_by_name
         FROM po_internal p JOIN branches b ON b.id = p.branch_id JOIN users u ON u.id = p.created_by
         WHERE p.po_number = ? AND p.is_deleted = 0'
    );
    $stmt->execute([$poNumber]);
    $po = $stmt->fetch();
    if (!$po) return null;

    $itemStmt = $db->prepare(
        'SELECT pi.id, pi.item_id, i.name AS item_name, i.is_serialized, i.purchase_unit, i.purchase_conversion_factor,
                pi.description, pi.quantity, pi.unit, pi.unit_cost, pi.total_cost,
                COALESCE((SELECT SUM(rri.quantity) FROM receiving_report_items rri
                          JOIN receiving_reports rr ON rr.rr_number = rri.rr_number
                          WHERE rr.po_number = pi.po_number AND rri.item_id = pi.item_id AND rri.is_deleted = 0 AND rr.is_deleted = 0), 0) AS received_qty
         FROM po_internal_items pi LEFT JOIN items i ON i.id = pi.item_id
         WHERE pi.po_number = ? AND pi.is_deleted = 0 ORDER BY pi.id'
    );
    $itemStmt->execute([$poNumber]);
    $po['items'] = $itemStmt->fetchAll();
    return $po;
}

if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $po = loadPoInternal($db, $id);
        if (!$po) jsonResponse(false, 'Purchase Order not found.');
        if ($scope !== null && (int)$po['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');
        jsonResponse(true, '', ['po' => $po]);
    }

    if (!empty($_GET['open_for_receiving'])) {
        $sql = "SELECT po_number, supplier_name FROM po_internal
                WHERE status IN ('sent','partially_received') AND is_deleted = 0";
        $params = [];
        if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['pos' => $stmt->fetchAll()]);
    }

    $sql = 'SELECT p.po_number, p.branch_id, b.name AS branch_name, p.supplier_name,
                   p.status, p.total_amount, u.full_name AS created_by_name, p.created_at
            FROM po_internal p JOIN branches b ON b.id = p.branch_id JOIN users u ON u.id = p.created_by
            WHERE p.is_deleted = 0';
    $params = [];
    if ($scope !== null) { $sql .= ' AND p.branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY p.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['pos' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage Internal Purchase Orders.');
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $branchId = $scope !== null ? $scope : (int)($data['branch_id'] ?? 0);
        if (!$branchId) jsonResponse(false, 'Branch is required.');
        if ($scope !== null && $branchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

        $supplierId = (int)($data['supplier_id'] ?? 0);
        if (!$supplierId) jsonResponse(false, 'Supplier is required.');
        $supStmt = $db->prepare('SELECT name, address FROM suppliers WHERE id = ? AND is_active = 1');
        $supStmt->execute([$supplierId]);
        $supplier = $supStmt->fetch();
        if (!$supplier) jsonResponse(false, 'Supplier not found.');

        $forwarder     = trim($data['forwarder'] ?? '');
        $shipToAddress = trim($data['ship_to_address'] ?? '');
        $items         = is_array($data['items'] ?? null) ? $data['items'] : [];
        $items = array_values(array_filter($items, fn($it) =>
            trim($it['description'] ?? '') !== '' && (float)($it['quantity'] ?? 0) > 0 && (float)($it['unit_cost'] ?? -1) >= 0
        ));
        if (!$items) jsonResponse(false, 'At least one item with description, quantity and unit cost is required.');

        $db->beginTransaction();
        try {
            $poNumber = mintDocNumber($db, $branchId, 'POI');
            $total = array_sum(array_map(fn($it) => (float)$it['quantity'] * (float)$it['unit_cost'], $items));

            $db->prepare(
                'INSERT INTO po_internal (po_number, branch_id, supplier_id, supplier_name, supplier_address,
                                           forwarder, ship_to_address, status, total_amount, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $poNumber, $branchId, $supplierId, $supplier['name'], $supplier['address'],
                $forwarder ?: null, $shipToAddress ?: null, 'draft', $total, $user['id'],
            ]);

            $itemStmt = $db->prepare(
                'INSERT INTO po_internal_items (po_number, item_id, end_user, description, quantity, unit, unit_cost, total_cost)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            foreach ($items as $it) {
                $itemStmt->execute([
                    $poNumber,
                    !empty($it['item_id']) ? (int)$it['item_id'] : null,
                    trim($it['end_user'] ?? '') ?: null,
                    trim($it['description']),
                    (float)$it['quantity'],
                    trim($it['unit'] ?? '') ?: null,
                    (float)$it['unit_cost'],
                    (float)$it['quantity'] * (float)$it['unit_cost'],
                ]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Internal PO create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to create Purchase Order.');
        }

        logAudit('CREATE_PO_INTERNAL', 'po_internal', "Created {$poNumber} for supplier {$supplier['name']}");
        jsonResponse(true, 'Purchase Order created.', ['po_number' => $poNumber]);
    }

    // Every action below targets an existing PO.
    $poNumber = trim($data['po_number'] ?? '');
    if ($poNumber === '') jsonResponse(false, 'Invalid Purchase Order.');
    $po = loadPoInternal($db, $poNumber);
    if (!$po) jsonResponse(false, 'Purchase Order not found.');
    if ($scope !== null && (int)$po['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

    if ($action === 'send') {
        if ($po['status'] !== 'draft') jsonResponse(false, 'Only a draft PO can be sent.');
        $db->prepare("UPDATE po_internal SET status = 'sent' WHERE po_number = ?")->execute([$poNumber]);
        logAudit('SEND_PO_INTERNAL', 'po_internal', "Sent {$poNumber} to {$po['supplier_name']}");
        jsonResponse(true, 'Purchase Order marked as sent.');
    }

    if ($action === 'cancel') {
        if (!in_array($po['status'], ['draft', 'sent'], true)) jsonResponse(false, 'This PO can no longer be cancelled.');
        $hasReceiving = $db->prepare('SELECT COUNT(*) FROM receiving_reports WHERE po_number = ? AND is_deleted = 0');
        $hasReceiving->execute([$poNumber]);
        if ((int)$hasReceiving->fetchColumn() > 0) jsonResponse(false, 'This PO already has receiving reports and cannot be cancelled.');
        $db->prepare("UPDATE po_internal SET status = 'cancelled' WHERE po_number = ?")->execute([$poNumber]);
        logAudit('CANCEL_PO_INTERNAL', 'po_internal', "Cancelled {$poNumber}");
        jsonResponse(true, 'Purchase Order cancelled.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
