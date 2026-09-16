<?php
// RFQ / Supplier Quotation — Internal: EXECOM canvassing its own suppliers.
// Admin-tier only to create/manage (branch_admin/super_admin) — canvassing
// suppliers and awarding lines is a purchasing decision, not a front-line
// task. Converting selected quotes creates one Internal PO per supplier
// (an RFQ can legitimately award different items to different suppliers).
require_once '../config.php';
requireModule('internal_rfq');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadRfq(PDO $db, string $rfqNumber): ?array {
    $stmt = $db->prepare(
        'SELECT r.*, b.name AS branch_name, u.full_name AS created_by_name
         FROM internal_rfq r JOIN branches b ON b.id = r.branch_id JOIN users u ON u.id = r.created_by
         WHERE r.rfq_number = ? AND r.is_deleted = 0'
    );
    $stmt->execute([$rfqNumber]);
    $rfq = $stmt->fetch();
    if (!$rfq) return null;

    $itemStmt = $db->prepare(
        'SELECT ri.id, ri.item_id, i.name AS item_name, ri.description, ri.quantity, ri.unit
         FROM internal_rfq_items ri LEFT JOIN items i ON i.id = ri.item_id
         WHERE ri.rfq_number = ? AND ri.is_deleted = 0 ORDER BY ri.id'
    );
    $itemStmt->execute([$rfqNumber]);
    $items = $itemStmt->fetchAll();

    $quoteStmt = $db->prepare(
        'SELECT q.id, q.rfq_item_id, q.supplier_id, s.name AS supplier_name, q.quoted_price, q.lead_time_days, q.is_selected
         FROM internal_rfq_quotes q JOIN suppliers s ON s.id = q.supplier_id
         WHERE q.rfq_number = ? ORDER BY q.quoted_price ASC'
    );
    $quoteStmt->execute([$rfqNumber]);
    $quotes = $quoteStmt->fetchAll();

    foreach ($items as &$item) {
        $item['quotes'] = array_values(array_filter($quotes, fn($q) => (int)$q['rfq_item_id'] === (int)$item['id']));
    }
    unset($item);
    $rfq['items'] = $items;
    return $rfq;
}

if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $rfq = loadRfq($db, $id);
        if (!$rfq) jsonResponse(false, 'RFQ not found.');
        if ($scope !== null && (int)$rfq['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');
        $suppliers = $db->query('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name')->fetchAll();
        jsonResponse(true, '', ['rfq' => $rfq, 'suppliers' => $suppliers]);
    }

    if (!empty($_GET['approved_prs'])) {
        $sql = "SELECT pr_number, purpose FROM pr_requests WHERE status = 'approved' AND is_deleted = 0";
        $params = [];
        if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['prs' => $stmt->fetchAll()]);
    }

    $sql = 'SELECT r.rfq_number, r.branch_id, b.name AS branch_name, r.pr_number, r.sent_date, r.status,
                   u.full_name AS created_by_name, r.created_at
            FROM internal_rfq r JOIN branches b ON b.id = r.branch_id JOIN users u ON u.id = r.created_by
            WHERE r.is_deleted = 0';
    $params = [];
    if ($scope !== null) { $sql .= ' AND r.branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY r.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['rfqs' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage RFQs.');
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $branchId = $scope !== null ? $scope : (int)($data['branch_id'] ?? 0);
        if (!$branchId) jsonResponse(false, 'Branch is required.');
        if ($scope !== null && $branchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

        $prNumber = trim($data['pr_number'] ?? '') ?: null;
        $sentDate = trim($data['sent_date'] ?? '') ?: null;
        $items    = is_array($data['items'] ?? null) ? $data['items'] : [];

        if ($prNumber) {
            $prStmt = $db->prepare("SELECT status, branch_id FROM pr_requests WHERE pr_number = ? AND is_deleted = 0");
            $prStmt->execute([$prNumber]);
            $pr = $prStmt->fetch();
            if (!$pr) jsonResponse(false, 'Requisition not found.');
            if ($pr['status'] !== 'approved') jsonResponse(false, 'Only an approved requisition can be canvassed.');
            if ((int)$pr['branch_id'] !== $branchId) jsonResponse(false, 'Requisition belongs to a different branch.');

            $prItemStmt = $db->prepare('SELECT item_id, description, quantity, unit FROM pr_items WHERE pr_number = ? AND is_deleted = 0');
            $prItemStmt->execute([$prNumber]);
            $items = $prItemStmt->fetchAll();
        }

        $items = array_values(array_filter($items, fn($it) => trim($it['description'] ?? '') !== '' && (float)($it['quantity'] ?? 0) > 0));
        if (!$items) jsonResponse(false, 'At least one item with a description and quantity is required.');

        $db->beginTransaction();
        try {
            $rfqNumber = mintDocNumber($db, $branchId, 'IRFQ');
            $db->prepare('INSERT INTO internal_rfq (rfq_number, branch_id, pr_number, sent_date, created_by) VALUES (?,?,?,?,?)')
               ->execute([$rfqNumber, $branchId, $prNumber, $sentDate, $user['id']]);

            $itemStmt = $db->prepare('INSERT INTO internal_rfq_items (rfq_number, item_id, description, quantity, unit) VALUES (?,?,?,?,?)');
            foreach ($items as $it) {
                $itemStmt->execute([
                    $rfqNumber,
                    !empty($it['item_id']) ? (int)$it['item_id'] : null,
                    trim($it['description']),
                    (float)$it['quantity'],
                    trim($it['unit'] ?? '') ?: null,
                ]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Internal RFQ create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to create RFQ.');
        }

        logAudit('CREATE_INTERNAL_RFQ', 'internal_rfq', "Created {$rfqNumber}" . ($prNumber ? " from {$prNumber}" : ''));
        jsonResponse(true, 'RFQ created.', ['rfq_number' => $rfqNumber]);
    }

    // Every action below targets an existing RFQ.
    $rfqNumber = trim($data['rfq_number'] ?? '');
    if ($rfqNumber === '') jsonResponse(false, 'Invalid RFQ.');
    $rfq = loadRfq($db, $rfqNumber);
    if (!$rfq) jsonResponse(false, 'RFQ not found.');
    if ($scope !== null && (int)$rfq['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

    if ($action === 'quote') {
        if ($rfq['status'] !== 'open') jsonResponse(false, 'This RFQ is no longer open.');
        $rfqItemId = (int)($data['rfq_item_id'] ?? 0);
        $supplierId = (int)($data['supplier_id'] ?? 0);
        $price = $data['quoted_price'] ?? null;
        $leadTime = $data['lead_time_days'] !== '' && isset($data['lead_time_days']) ? (int)$data['lead_time_days'] : null;
        if (!$rfqItemId || !$supplierId || $price === null || $price === '') {
            jsonResponse(false, 'Item, supplier and price are required.');
        }
        $itemOwned = false;
        foreach ($rfq['items'] as $it) { if ((int)$it['id'] === $rfqItemId) { $itemOwned = true; break; } }
        if (!$itemOwned) jsonResponse(false, 'That line does not belong to this RFQ.');

        $existing = $db->prepare('SELECT id FROM internal_rfq_quotes WHERE rfq_number = ? AND supplier_id = ? AND rfq_item_id = ?');
        $existing->execute([$rfqNumber, $supplierId, $rfqItemId]);
        if ($row = $existing->fetch()) {
            $db->prepare('UPDATE internal_rfq_quotes SET quoted_price = ?, lead_time_days = ? WHERE id = ?')
               ->execute([(float)$price, $leadTime, $row['id']]);
        } else {
            $db->prepare('INSERT INTO internal_rfq_quotes (rfq_number, supplier_id, rfq_item_id, quoted_price, lead_time_days) VALUES (?,?,?,?,?)')
               ->execute([$rfqNumber, $supplierId, $rfqItemId, (float)$price, $leadTime]);
        }
        logAudit('QUOTE_INTERNAL_RFQ', 'internal_rfq', "Recorded quote on {$rfqNumber}");
        jsonResponse(true, 'Quote saved.');
    }

    if ($action === 'select_quote') {
        if ($rfq['status'] !== 'open') jsonResponse(false, 'This RFQ is no longer open.');
        $quoteId = (int)($data['quote_id'] ?? 0);
        $stmt = $db->prepare('SELECT rfq_item_id FROM internal_rfq_quotes WHERE id = ? AND rfq_number = ?');
        $stmt->execute([$quoteId, $rfqNumber]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, 'Quote not found.');

        $db->beginTransaction();
        $db->prepare('UPDATE internal_rfq_quotes SET is_selected = 0 WHERE rfq_item_id = ?')->execute([$row['rfq_item_id']]);
        $db->prepare('UPDATE internal_rfq_quotes SET is_selected = 1 WHERE id = ?')->execute([$quoteId]);
        $db->commit();
        jsonResponse(true, 'Winning quote selected.');
    }

    if ($action === 'cancel') {
        if ($rfq['status'] !== 'open') jsonResponse(false, 'This RFQ is not open.');
        $db->prepare("UPDATE internal_rfq SET status = 'cancelled' WHERE rfq_number = ?")->execute([$rfqNumber]);
        logAudit('CANCEL_INTERNAL_RFQ', 'internal_rfq', "Cancelled {$rfqNumber}");
        jsonResponse(true, 'RFQ cancelled.');
    }

    if ($action === 'convert_to_po') {
        if ($rfq['status'] !== 'open') jsonResponse(false, 'This RFQ is not open.');

        // Group every selected winning quote by supplier — one Internal PO
        // per supplier, since a PO can only carry one supplier.
        $selectedStmt = $db->prepare(
            'SELECT q.supplier_id, s.name AS supplier_name, s.address AS supplier_address,
                    q.rfq_item_id, q.quoted_price, ri.item_id, ri.description, ri.quantity, ri.unit
             FROM internal_rfq_quotes q
             JOIN internal_rfq_items ri ON ri.id = q.rfq_item_id
             JOIN suppliers s ON s.id = q.supplier_id
             WHERE q.rfq_number = ? AND q.is_selected = 1'
        );
        $selectedStmt->execute([$rfqNumber]);
        $selected = $selectedStmt->fetchAll();
        if (!$selected) jsonResponse(false, 'Select a winning supplier for at least one item first.');

        $bySupplier = [];
        foreach ($selected as $row) {
            $bySupplier[$row['supplier_id']]['info'] = $row;
            $bySupplier[$row['supplier_id']]['lines'][] = $row;
        }

        $db->beginTransaction();
        try {
            $poNumbers = [];
            foreach ($bySupplier as $supplierId => $group) {
                $info = $group['info'];
                $poNumber = mintDocNumber($db, (int)$rfq['branch_id'], 'POI');
                $total = array_sum(array_map(fn($l) => (float)$l['quoted_price'] * (float)$l['quantity'], $group['lines']));

                $db->prepare(
                    'INSERT INTO po_internal (po_number, branch_id, supplier_id, supplier_name, supplier_address,
                                               internal_rfq_number, pr_number, status, total_amount, created_by)
                     VALUES (?,?,?,?,?,?,?, ?, ?, ?)'
                )->execute([
                    $poNumber, $rfq['branch_id'], $supplierId, $info['supplier_name'], $info['supplier_address'],
                    $rfqNumber, $rfq['pr_number'], 'draft', $total, $user['id'],
                ]);

                $lineStmt = $db->prepare(
                    'INSERT INTO po_internal_items (po_number, item_id, description, quantity, unit, unit_cost, total_cost)
                     VALUES (?,?,?,?,?,?,?)'
                );
                foreach ($group['lines'] as $l) {
                    $lineStmt->execute([
                        $poNumber, $l['item_id'], $l['description'], $l['quantity'], $l['unit'],
                        $l['quoted_price'], (float)$l['quoted_price'] * (float)$l['quantity'],
                    ]);
                }
                $poNumbers[] = $poNumber;
            }

            $db->prepare("UPDATE internal_rfq SET status = 'converted' WHERE rfq_number = ?")->execute([$rfqNumber]);
            if ($rfq['pr_number']) {
                $db->prepare("UPDATE pr_requests SET status = 'converted' WHERE pr_number = ?")->execute([$rfq['pr_number']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('RFQ to PO conversion failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to convert RFQ to Purchase Order.');
        }

        logAudit('CONVERT_RFQ_TO_PO', 'internal_rfq', "{$rfqNumber} -> " . implode(', ', $poNumbers));
        notifyRoles(['branch_admin', 'super_admin'], 'Internal PO created', "{$rfqNumber} converted to " . implode(', ', $poNumbers), 'po_internal.php');
        jsonResponse(true, 'Converted to Internal PO' . (count($poNumbers) > 1 ? 's' : '') . ': ' . implode(', ', $poNumbers), ['po_numbers' => $poNumbers]);
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
