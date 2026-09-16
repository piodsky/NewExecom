<?php
// Purchase Requisition — Internal only. A branch requests stock/operating
// items; once approved it can be turned into an Internal RFQ (canvass) or
// an Internal PO directly. Branch-scoped: a branch_user only ever sees and
// creates PRs for their own branch; branch_admin approves/rejects within
// their branch; super_admin sees and approves every branch.
require_once '../config.php';
requireModule('purchase_requisition');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadPr(PDO $db, string $prNumber): ?array {
    $stmt = $db->prepare(
        'SELECT p.*, b.name AS branch_name, u1.full_name AS requested_by_name, u2.full_name AS approved_by_name
         FROM pr_requests p
         JOIN branches b ON b.id = p.branch_id
         JOIN users u1 ON u1.id = p.requested_by
         LEFT JOIN users u2 ON u2.id = p.approved_by
         WHERE p.pr_number = ? AND p.is_deleted = 0'
    );
    $stmt->execute([$prNumber]);
    $pr = $stmt->fetch();
    if (!$pr) return null;

    $itemStmt = $db->prepare(
        'SELECT pi.id, pi.item_id, i.name AS item_name, pi.description, pi.quantity, pi.unit
         FROM pr_items pi LEFT JOIN items i ON i.id = pi.item_id
         WHERE pi.pr_number = ? AND pi.is_deleted = 0 ORDER BY pi.id'
    );
    $itemStmt->execute([$prNumber]);
    $pr['items'] = $itemStmt->fetchAll();
    return $pr;
}

if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $pr = loadPr($db, $id);
        if (!$pr) jsonResponse(false, 'Requisition not found.');
        if ($scope !== null && (int)$pr['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');
        jsonResponse(true, '', ['pr' => $pr]);
    }

    $sql = 'SELECT p.pr_number, p.branch_id, b.name AS branch_name, p.department, p.purpose,
                   p.needed_date, p.status, p.remarks, u.full_name AS requested_by_name, p.created_at
            FROM pr_requests p JOIN branches b ON b.id = p.branch_id JOIN users u ON u.id = p.requested_by
            WHERE p.is_deleted = 0';
    $params = [];
    if ($scope !== null) { $sql .= ' AND p.branch_id = ?'; $params[] = $scope; }
    $sql .= ' ORDER BY p.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['requisitions' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $branchId = $scope !== null ? $scope : (int)($data['branch_id'] ?? 0);
        if (!$branchId) jsonResponse(false, 'Branch is required.');
        if ($scope !== null && $branchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

        $department = trim($data['department'] ?? '');
        $purpose    = trim($data['purpose'] ?? '');
        $neededDate = trim($data['needed_date'] ?? '') ?: null;
        $items      = is_array($data['items'] ?? null) ? $data['items'] : [];
        $submit     = !empty($data['submit']);

        $items = array_values(array_filter($items, fn($it) => trim($it['description'] ?? '') !== '' && (float)($it['quantity'] ?? 0) > 0));
        if (!$items) jsonResponse(false, 'At least one item with a description and quantity is required.');

        $db->beginTransaction();
        try {
            $prNumber = mintDocNumber($db, $branchId, 'PR');
            $db->prepare(
                'INSERT INTO pr_requests (pr_number, branch_id, requested_by, department, purpose, needed_date, status)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$prNumber, $branchId, $user['id'], $department ?: null, $purpose ?: null, $neededDate, $submit ? 'pending' : 'draft']);

            $itemStmt = $db->prepare(
                'INSERT INTO pr_items (pr_number, item_id, description, quantity, unit) VALUES (?,?,?,?,?)'
            );
            foreach ($items as $it) {
                $itemStmt->execute([
                    $prNumber,
                    !empty($it['item_id']) ? (int)$it['item_id'] : null,
                    trim($it['description']),
                    (float)$it['quantity'],
                    trim($it['unit'] ?? '') ?: null,
                ]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('PR create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to create requisition.');
        }

        logAudit('CREATE_PR', 'purchase_requisition', "Created {$prNumber}" . ($submit ? ' and submitted' : ' (draft)'));
        jsonResponse(true, 'Requisition created.', ['pr_number' => $prNumber]);
    }

    // Every action below targets an existing PR.
    $prNumber = trim($data['pr_number'] ?? '');
    if ($prNumber === '') jsonResponse(false, 'Invalid requisition.');
    $pr = loadPr($db, $prNumber);
    if (!$pr) jsonResponse(false, 'Requisition not found.');
    if ($scope !== null && (int)$pr['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

    if ($action === 'submit') {
        if ($pr['status'] !== 'draft') jsonResponse(false, 'Only a draft requisition can be submitted.');
        if ((int)$pr['requested_by'] !== (int)$user['id'] && !$isAdminTier) jsonResponse(false, 'Access denied.');
        $db->prepare("UPDATE pr_requests SET status = 'pending' WHERE pr_number = ?")->execute([$prNumber]);
        logAudit('SUBMIT_PR', 'purchase_requisition', "Submitted {$prNumber} for approval");
        notifyRoles(['branch_admin', 'super_admin'], 'Requisition awaiting approval', "{$prNumber} needs your approval", 'purchase_requisitions.php');
        jsonResponse(true, 'Requisition submitted for approval.');
    }

    if ($action === 'delete') {
        if ($pr['status'] !== 'draft') jsonResponse(false, 'Only a draft requisition can be deleted.');
        if ((int)$pr['requested_by'] !== (int)$user['id'] && !$isAdminTier) jsonResponse(false, 'Access denied.');
        $db->prepare('UPDATE pr_requests SET is_deleted = 1 WHERE pr_number = ?')->execute([$prNumber]);
        logAudit('DELETE_PR', 'purchase_requisition', "Deleted draft {$prNumber}");
        jsonResponse(true, 'Requisition deleted.');
    }

    if ($action === 'approve' || $action === 'reject') {
        if (!$isAdminTier) jsonResponse(false, 'Only administrators can approve or reject requisitions.');
        if ($pr['status'] !== 'pending') jsonResponse(false, 'Only a pending requisition can be approved or rejected.');
        $remarks = trim($data['remarks'] ?? '');
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $db->prepare('UPDATE pr_requests SET status = ?, approved_by = ?, approved_at = NOW(), remarks = ? WHERE pr_number = ?')
           ->execute([$newStatus, $user['id'], $remarks ?: null, $prNumber]);
        logAudit(strtoupper($action) . '_PR', 'purchase_requisition', "{$action}d {$prNumber}" . ($remarks ? ": {$remarks}" : ''));
        notifyRoles(['branch_admin', 'super_admin'], "Requisition {$newStatus}", "{$prNumber} was {$newStatus} by {$user['full_name']}", 'purchase_requisitions.php');
        jsonResponse(true, "Requisition {$newStatus}.");
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
