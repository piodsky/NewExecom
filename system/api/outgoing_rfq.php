<?php
// RFQ / Canvass — Outgoing: a canvass EXECOM received FROM a client (an LGU
// office, etc), logged so it can be turned into an Outgoing Quotation. This
// is the mirror image of the Internal RFQ (there EXECOM canvasses its own
// suppliers; here a client canvasses EXECOM). Admin-tier only, same rank as
// every other purchasing/sales decision in this app.
require_once '../config.php';
requireModule('outgoing_rfq');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadOutgoingRfq(PDO $db, string $rfqNumber): ?array {
    $stmt = $db->prepare(
        'SELECT r.*, b.name AS branch_name, u.full_name AS created_by_name
         FROM outgoing_rfq r JOIN branches b ON b.id = r.branch_id JOIN users u ON u.id = r.created_by
         WHERE r.rfq_number = ? AND r.is_deleted = 0'
    );
    $stmt->execute([$rfqNumber]);
    $rfq = $stmt->fetch();
    if (!$rfq) return null;

    $itemStmt = $db->prepare(
        'SELECT ri.id, ri.item_id, i.name AS item_name, ri.description, ri.quantity, ri.unit
         FROM outgoing_rfq_items ri LEFT JOIN items i ON i.id = ri.item_id
         WHERE ri.rfq_number = ? AND ri.is_deleted = 0 ORDER BY ri.id'
    );
    $itemStmt->execute([$rfqNumber]);
    $rfq['items'] = $itemStmt->fetchAll();
    return $rfq;
}

if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $rfq = loadOutgoingRfq($db, $id);
        if (!$rfq) jsonResponse(false, 'RFQ not found.');
        if ($scope !== null && (int)$rfq['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');
        jsonResponse(true, '', ['rfq' => $rfq]);
    }

    if (!empty($_GET['open_only'])) {
        $sql = "SELECT rfq_number, client_name FROM outgoing_rfq WHERE status = 'open' AND is_deleted = 0";
        $params = [];
        if ($scope !== null) { $sql .= ' AND branch_id = ?'; $params[] = $scope; }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['rfqs' => $stmt->fetchAll()]);
    }

    $sql = 'SELECT r.rfq_number, r.branch_id, b.name AS branch_name, r.client_name, r.received_date,
                   r.due_date, r.status, u.full_name AS created_by_name, r.created_at
            FROM outgoing_rfq r JOIN branches b ON b.id = r.branch_id JOIN users u ON u.id = r.created_by
            WHERE r.is_deleted = 0';
    $params = [];
    if ($scope !== null) { $sql .= ' AND r.branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY r.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['rfqs' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage outgoing RFQs.');
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

        $receivedDate = inputDateTime($data['received_date'] ?? null, 'Date and time received', date('Y-m-d H:i:s'));
        $dueDate      = inputDateTime($data['due_date'] ?? null, 'Due date and time');
        $items        = is_array($data['items'] ?? null) ? $data['items'] : [];
        $items = array_values(array_filter($items, fn($it) => trim($it['description'] ?? '') !== '' && (float)($it['quantity'] ?? 0) > 0));
        if (!$items) jsonResponse(false, 'At least one item with a description and quantity is required.');

        $db->beginTransaction();
        try {
            $rfqNumber = mintDocNumber($db, $branchId, 'ORFQ');
            $db->prepare(
                'INSERT INTO outgoing_rfq (rfq_number, branch_id, client_id, client_name, client_address, received_date, due_date, created_by)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$rfqNumber, $branchId, $clientId, $client['name'], $client['address'], $receivedDate, $dueDate, $user['id']]);

            $itemStmt = $db->prepare('INSERT INTO outgoing_rfq_items (rfq_number, item_id, description, quantity, unit) VALUES (?,?,?,?,?)');
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
            error_log('Outgoing RFQ create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to log RFQ.');
        }

        logAudit('CREATE_OUTGOING_RFQ', 'outgoing_rfq', "Logged {$rfqNumber} from {$client['name']}");
        notifyRoles(['branch_admin', 'super_admin'], 'Canvass received', "{$rfqNumber} from {$client['name']} needs a quotation", 'outgoing_rfq.php');
        jsonResponse(true, 'RFQ logged.', ['rfq_number' => $rfqNumber]);
    }

    // Every action below targets an existing RFQ.
    $rfqNumber = trim($data['rfq_number'] ?? '');
    if ($rfqNumber === '') jsonResponse(false, 'Invalid RFQ.');
    $rfq = loadOutgoingRfq($db, $rfqNumber);
    if (!$rfq) jsonResponse(false, 'RFQ not found.');
    if ($scope !== null && (int)$rfq['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

    if ($action === 'cancel') {
        if ($rfq['status'] !== 'open') jsonResponse(false, 'Only an open RFQ can be cancelled.');
        $db->prepare("UPDATE outgoing_rfq SET status = 'cancelled' WHERE rfq_number = ?")->execute([$rfqNumber]);
        logAudit('CANCEL_OUTGOING_RFQ', 'outgoing_rfq', "Cancelled {$rfqNumber}");
        jsonResponse(true, 'RFQ cancelled.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
