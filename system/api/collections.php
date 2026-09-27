<?php
// Collections — recording a customer payment (Official Receipt) and applying
// it against one or more open sales_invoices, plus the Post-Dated Check
// register for either side (received from a client, issued to a supplier).
// gross/withholding/net mirrors Globalchips' SPAYTRN — LGU clients withhold
// tax by law (BIR Form 2307), so that split is captured on every OR.
require_once '../config.php';
requireModule('collections');
header('Content-Type: application/json');

$user   = getCurrentUser();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

if ($method === 'GET') {
    if (isset($_GET['open_invoices_for'])) {
        $clientId = (int)$_GET['open_invoices_for'];
        $stmt = $db->prepare(
            "SELECT id, invoice_number, due_date, total_amount, amount_paid, balance, status
             FROM sales_invoices WHERE client_id = ? AND status IN ('unpaid','partial') AND is_deleted = 0
             ORDER BY due_date IS NULL, due_date ASC"
        );
        $stmt->execute([$clientId]);
        jsonResponse(true, '', ['invoices' => $stmt->fetchAll()]);
    }

    if (isset($_GET['pdc'])) {
        $direction = trim($_GET['direction'] ?? '');
        $sql = "SELECT pdc.*, c.name AS client_name, s.name AS supplier_name, u.full_name AS created_by_name
                FROM post_dated_checks pdc
                LEFT JOIN clients c ON c.id = pdc.client_id
                LEFT JOIN suppliers s ON s.id = pdc.supplier_id
                JOIN users u ON u.id = pdc.created_by
                WHERE pdc.is_deleted = 0";
        $params = [];
        if (in_array($direction, ['received', 'issued'], true)) { $sql .= ' AND pdc.direction = ?'; $params[] = $direction; }
        $sql .= ' ORDER BY pdc.check_date';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['checks' => $stmt->fetchAll()]);
    }

    $clientId = trim($_GET['client_id'] ?? '');
    $sql = 'SELECT co.*, c.name AS client_name, u.full_name AS received_by_name
            FROM collections co JOIN clients c ON c.id = co.client_id JOIN users u ON u.id = co.received_by
            WHERE co.is_deleted = 0';
    $params = [];
    if ($clientId !== '') { $sql .= ' AND co.client_id = ?'; $params[] = (int)$clientId; }
    $sql .= ' ORDER BY co.payment_date DESC, co.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['collections' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $clientId = (int)($data['client_id'] ?? 0);
        if (!$clientId) jsonResponse(false, 'Client is required.');
        $clientCheck = $db->prepare('SELECT COUNT(*) FROM clients WHERE id = ?');
        $clientCheck->execute([$clientId]);
        if (!$clientCheck->fetchColumn()) jsonResponse(false, 'Client not found.');

        $paymentDate = trim($data['payment_date'] ?? '') ?: date('Y-m-d');
        $method_     = in_array($data['payment_method'] ?? '', ['cash', 'check', 'bank_transfer'], true) ? $data['payment_method'] : 'cash';
        $gross       = (float)($data['gross_amount'] ?? 0);
        $wht         = (float)($data['withholding_tax_amount'] ?? 0);
        if ($gross <= 0) jsonResponse(false, 'Gross amount must be greater than zero.');
        if ($wht < 0 || $wht > $gross) jsonResponse(false, 'Withholding tax must be between zero and the gross amount.');
        $net = $gross - $wht;
        $remarks = trim($data['remarks'] ?? '') ?: null;

        $applications = is_array($data['applications'] ?? null) ? $data['applications'] : [];
        $applications = array_filter($applications, fn($a) => (float)($a['amount_applied'] ?? 0) > 0);
        if (!$applications) jsonResponse(false, 'Apply this payment to at least one invoice.');

        $sumApplied = array_sum(array_map(fn($a) => (float)$a['amount_applied'], $applications));
        if ($sumApplied > $net + 0.01) jsonResponse(false, 'Applied amounts exceed the net amount (after withholding tax).');

        $invStmt = $db->prepare('SELECT id, client_id, balance FROM sales_invoices WHERE id = ? AND is_deleted = 0 AND status IN (\'unpaid\',\'partial\')');

        $db->beginTransaction();
        try {
            // Collections aren't branch-scoped (a client can pay any branch),
            // so the doc number still needs *a* branch bucket — the lowest
            // branch id is the shared numbering home rather than adding a
            // branch_id column to a company-wide AR document.
            $anyBranch = (int)($db->query('SELECT id FROM branches ORDER BY id LIMIT 1')->fetchColumn() ?: 1);
            $orNumber = mintDocNumber($db, $anyBranch, 'OR');
            $db->prepare(
                'INSERT INTO collections (or_number, client_id, payment_date, payment_method, gross_amount, withholding_tax_amount, net_amount, remarks, received_by)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([$orNumber, $clientId, $paymentDate, $method_, $gross, $wht, $net, $remarks, $user['id']]);
            $collectionId = (int)$db->lastInsertId();

            foreach ($applications as $app) {
                $invoiceId = (int)$app['sales_invoice_id'];
                $amount    = (float)$app['amount_applied'];
                $invStmt->execute([$invoiceId]);
                $inv = $invStmt->fetch();
                if (!$inv) throw new RuntimeException('One of the selected invoices is no longer open.');
                if ((int)$inv['client_id'] !== $clientId) throw new RuntimeException('One of the selected invoices does not belong to this client.');
                if ($amount > (float)$inv['balance'] + 0.01) throw new RuntimeException('Applied amount exceeds an invoice\'s remaining balance.');

                $db->prepare('INSERT INTO collection_applications (collection_id, sales_invoice_id, amount_applied) VALUES (?,?,?)')
                   ->execute([$collectionId, $invoiceId, $amount]);

                $totalStmt = $db->prepare('SELECT total_amount, amount_paid FROM sales_invoices WHERE id = ?');
                $totalStmt->execute([$invoiceId]);
                $row = $totalStmt->fetch();
                $total = (float)$row['total_amount'];
                $newPaid = round((float)$row['amount_paid'] + $amount, 2);
                $newBalance = max(0, round($total - $newPaid, 2));
                $newStatus  = $newBalance <= 0.01 ? 'paid' : 'partial';
                $db->prepare('UPDATE sales_invoices SET amount_paid = ?, balance = ?, status = ? WHERE id = ?')
                   ->execute([$newPaid, $newBalance, $newStatus, $invoiceId]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Collection create failed: ' . $e->getMessage());
            jsonResponse(false, $e->getMessage() ?: 'Failed to record collection.');
        }

        logAudit('CREATE_COLLECTION', 'collections', "Recorded {$orNumber} for " . number_format($gross, 2));
        jsonResponse(true, 'Collection recorded.', ['or_number' => $orNumber]);
    }

    if ($action === 'pdc_create') {
        $direction = in_array($data['direction'] ?? '', ['received', 'issued'], true) ? $data['direction'] : null;
        if (!$direction) jsonResponse(false, 'Invalid direction.');
        $clientId   = $direction === 'received' ? (int)($data['client_id'] ?? 0) ?: null : null;
        $supplierId = $direction === 'issued'   ? (int)($data['supplier_id'] ?? 0) ?: null : null;
        if ($direction === 'received' && !$clientId) jsonResponse(false, 'Client is required for a received check.');
        if ($direction === 'issued' && !$supplierId) jsonResponse(false, 'Supplier is required for an issued check.');

        $checkNumber = trim($data['check_number'] ?? '');
        if ($checkNumber === '') jsonResponse(false, 'Check number is required.');
        $bankName  = trim($data['bank_name'] ?? '') ?: null;
        $checkDate = trim($data['check_date'] ?? '');
        if ($checkDate === '') jsonResponse(false, 'Check date is required.');
        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) jsonResponse(false, 'Amount must be greater than zero.');
        $remarks = trim($data['remarks'] ?? '') ?: null;

        $anyBranch = (int)($db->query('SELECT id FROM branches ORDER BY id LIMIT 1')->fetchColumn() ?: 1);
        $pdcNumber = mintDocNumber($db, $anyBranch, 'PDC');
        $db->prepare(
            'INSERT INTO post_dated_checks (pdc_number, direction, client_id, supplier_id, check_number, bank_name, check_date, amount, remarks, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([$pdcNumber, $direction, $clientId, $supplierId, $checkNumber, $bankName, $checkDate, $amount, $remarks, $user['id']]);

        logAudit('CREATE_PDC', 'collections', "Recorded {$pdcNumber}");
        jsonResponse(true, 'Post-dated check recorded.', ['pdc_number' => $pdcNumber]);
    }

    if ($action === 'pdc_update_status') {
        $id = (int)($data['id'] ?? 0);
        $newStatus = trim($data['status'] ?? '');
        if (!in_array($newStatus, ['on_hand', 'deposited', 'cleared', 'bounced'], true)) jsonResponse(false, 'Invalid status.');
        $stmt = $db->prepare('SELECT * FROM post_dated_checks WHERE id = ? AND is_deleted = 0');
        $stmt->execute([$id]);
        $pdc = $stmt->fetch();
        if (!$pdc) jsonResponse(false, 'Check not found.');

        $db->prepare('UPDATE post_dated_checks SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        logAudit('UPDATE_PDC_STATUS', 'collections', "{$pdc['pdc_number']} -> {$newStatus}");
        jsonResponse(true, 'Status updated.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
