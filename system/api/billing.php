<?php
// Billing (Accounts Receivable) — the follow-through Globalchips had and
// EXECOM didn't: an issuance or job order on credit terms needs an invoice
// balance, a due date, and an aging view. AR only ever applies to a
// registered client — walk-ins are assumed paid in cash on the spot and
// never get a row here (see api/issuances.php and api/job_orders.php).
require_once '../config.php';
requireModule('billing');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

if ($method === 'GET') {
    // Issuances that could become a credit invoice: has a registered client,
    // not already invoiced, not voided.
    if (isset($_GET['unbilled_issuances'])) {
        $sql = "SELECT iss.issuance_number, iss.branch_id, iss.client_id, c.name AS client_name,
                       c.payment_terms_days, iss.total_amount, iss.date_created
                FROM issuances iss
                JOIN clients c ON c.id = iss.client_id
                LEFT JOIN sales_invoices si ON si.issuance_number = iss.issuance_number AND si.is_deleted = 0
                WHERE iss.is_deleted = 0 AND si.id IS NULL";
        $params = [];
        if ($scope !== null) { $sql .= ' AND iss.branch_id = ?'; $params[] = $scope; }
        $sql .= ' ORDER BY iss.date_created DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['issuances' => $stmt->fetchAll()]);
    }

    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $stmt = $db->prepare(
            'SELECT si.*, c.name AS client_name, c.contact_number AS client_contact
             FROM sales_invoices si LEFT JOIN clients c ON c.id = si.client_id
             WHERE si.invoice_number = ? AND si.is_deleted = 0'
        );
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) jsonResponse(false, 'Invoice not found.');

        $appStmt = $db->prepare(
            'SELECT ca.amount_applied, co.or_number, co.payment_date, co.payment_method
             FROM collection_applications ca JOIN collections co ON co.id = ca.collection_id
             WHERE ca.sales_invoice_id = ? ORDER BY co.payment_date'
        );
        $appStmt->execute([$inv['id']]);
        $inv['payments'] = $appStmt->fetchAll();
        jsonResponse(true, '', ['invoice' => $inv]);
    }

    $status = trim($_GET['status'] ?? '');
    $sql = "SELECT si.id, si.invoice_number, si.issuance_number, si.jo_number, si.client_id,
                   c.name AS client_name, si.due_date, si.total_amount, si.amount_paid, si.balance, si.status,
                   DATEDIFF(CURDATE(), si.due_date) AS days_overdue
            FROM sales_invoices si
            LEFT JOIN clients c ON c.id = si.client_id
            LEFT JOIN issuances iss ON iss.issuance_number = si.issuance_number
            WHERE si.is_deleted = 0";
    $params = [];
    if ($scope !== null) {
        $sql .= " AND (iss.branch_id = ? OR EXISTS (SELECT 1 FROM job_orders jo WHERE jo.jo_number = si.jo_number AND jo.branch_id = ?))";
        $params[] = $scope; $params[] = $scope;
    }
    if ($status !== '' && $status !== 'all') { $sql .= ' AND si.status = ?'; $params[] = $status; }
    $sql .= ' ORDER BY si.due_date IS NULL, si.due_date ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();

    // Aging summary — current / 1-30 / 31-60 / 61-90 / 90+, unpaid+partial only.
    $buckets = ['current' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'd90_plus' => 0.0];
    foreach ($invoices as $inv) {
        if (!in_array($inv['status'], ['unpaid', 'partial'], true)) continue;
        $overdue = (int)($inv['days_overdue'] ?? 0);
        if ($overdue <= 0) $buckets['current'] += (float)$inv['balance'];
        elseif ($overdue <= 30) $buckets['d1_30'] += (float)$inv['balance'];
        elseif ($overdue <= 60) $buckets['d31_60'] += (float)$inv['balance'];
        elseif ($overdue <= 90) $buckets['d61_90'] += (float)$inv['balance'];
        else $buckets['d90_plus'] += (float)$inv['balance'];
    }

    jsonResponse(true, '', ['invoices' => $invoices, 'aging' => $buckets]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? '';

    if ($action === 'create_from_issuance') {
        $issuanceNumber = trim($data['issuance_number'] ?? '');
        $stmt = $db->prepare(
            'SELECT iss.*, c.payment_terms_days FROM issuances iss
             JOIN clients c ON c.id = iss.client_id
             WHERE iss.issuance_number = ? AND iss.is_deleted = 0'
        );
        $stmt->execute([$issuanceNumber]);
        $iss = $stmt->fetch();
        if (!$iss) jsonResponse(false, 'Issuance not found or has no registered client.');
        if ($scope !== null && (int)$iss['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

        $dupCheck = $db->prepare('SELECT COUNT(*) FROM sales_invoices WHERE issuance_number = ? AND is_deleted = 0');
        $dupCheck->execute([$issuanceNumber]);
        if ($dupCheck->fetchColumn()) jsonResponse(false, 'This issuance has already been invoiced.');

        $termsDays = (int)($iss['payment_terms_days'] ?? 0);
        $dueDate = $termsDays > 0 ? date('Y-m-d', strtotime("+{$termsDays} days")) : date('Y-m-d');

        $db->beginTransaction();
        try {
            $invoiceNumber = mintDocNumber($db, (int)$iss['branch_id'], 'SI');
            $db->prepare(
                'INSERT INTO sales_invoices (invoice_number, issuance_number, client_id, due_date, total_amount, balance, created_by)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$invoiceNumber, $issuanceNumber, $iss['client_id'], $dueDate, $iss['total_amount'], $iss['total_amount'], $user['id']]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Invoice create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to create invoice.');
        }

        logAudit('CREATE_SALES_INVOICE', 'billing', "Created {$invoiceNumber} from {$issuanceNumber}");
        jsonResponse(true, 'Invoice created.', ['invoice_number' => $invoiceNumber]);
    }

    if ($action === 'void') {
        if (!$isAdminTier) jsonResponse(false, 'Only administrators can void an invoice.');
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM sales_invoices WHERE id = ? AND is_deleted = 0');
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) jsonResponse(false, 'Invoice not found.');
        if ((float)$inv['amount_paid'] > 0) jsonResponse(false, 'Cannot void an invoice that already has payments applied.');

        $db->prepare("UPDATE sales_invoices SET status = 'void' WHERE id = ?")->execute([$id]);
        logAudit('VOID_SALES_INVOICE', 'billing', "Voided {$inv['invoice_number']}");
        jsonResponse(true, 'Invoice voided.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
