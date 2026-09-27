<?php
// Disbursements — recording a payment EXECOM makes to a supplier (DV) and
// applying it against one or more open supplier_invoices. The Post-Dated
// Check register itself lives in api/collections.php (shared, direction-
// aware table); this file only reads it to offer on-hand issued checks as a
// payment method, and marks one cleared/linked once used.
require_once '../config.php';
requireModule('payables');
header('Content-Type: application/json');

$user   = getCurrentUser();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['open_invoices_for'])) {
        $supplierId = (int)$_GET['open_invoices_for'];
        $stmt = $db->prepare(
            "SELECT id, invoice_number, due_date, total_amount, amount_paid, balance, status
             FROM supplier_invoices WHERE supplier_id = ? AND status IN ('unpaid','partial') AND is_deleted = 0
             ORDER BY due_date IS NULL, due_date ASC"
        );
        $stmt->execute([$supplierId]);
        jsonResponse(true, '', ['invoices' => $stmt->fetchAll()]);
    }

    if (isset($_GET['available_pdcs_for'])) {
        $supplierId = (int)$_GET['available_pdcs_for'];
        $stmt = $db->prepare(
            "SELECT id, pdc_number, check_number, bank_name, check_date, amount
             FROM post_dated_checks
             WHERE direction = 'issued' AND supplier_id = ? AND status = 'on_hand' AND is_deleted = 0
             ORDER BY check_date"
        );
        $stmt->execute([$supplierId]);
        jsonResponse(true, '', ['checks' => $stmt->fetchAll()]);
    }

    $supplierId = trim($_GET['supplier_id'] ?? '');
    $sql = 'SELECT d.*, s.name AS supplier_name, u.full_name AS paid_by_name
            FROM disbursements d JOIN suppliers s ON s.id = d.supplier_id JOIN users u ON u.id = d.paid_by
            WHERE d.is_deleted = 0';
    $params = [];
    if ($supplierId !== '') { $sql .= ' AND d.supplier_id = ?'; $params[] = (int)$supplierId; }
    $sql .= ' ORDER BY d.payment_date DESC, d.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['disbursements' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $supplierId = (int)($data['supplier_id'] ?? 0);
        if (!$supplierId) jsonResponse(false, 'Supplier is required.');
        $supCheck = $db->prepare('SELECT COUNT(*) FROM suppliers WHERE id = ?');
        $supCheck->execute([$supplierId]);
        if (!$supCheck->fetchColumn()) jsonResponse(false, 'Supplier not found.');

        $paymentDate = trim($data['payment_date'] ?? '') ?: date('Y-m-d');
        $paymentMethod = in_array($data['payment_method'] ?? '', ['cash', 'check', 'bank_transfer'], true) ? $data['payment_method'] : 'cash';
        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) jsonResponse(false, 'Amount must be greater than zero.');
        $remarks = trim($data['remarks'] ?? '') ?: null;
        $pdcId = !empty($data['pdc_id']) ? (int)$data['pdc_id'] : null;

        $applications = is_array($data['applications'] ?? null) ? $data['applications'] : [];
        $applications = array_filter($applications, fn($a) => (float)($a['amount_applied'] ?? 0) > 0);
        if (!$applications) jsonResponse(false, 'Apply this payment to at least one invoice.');

        $sumApplied = array_sum(array_map(fn($a) => (float)$a['amount_applied'], $applications));
        if ($sumApplied > $amount + 0.01) jsonResponse(false, 'Applied amounts exceed the payment amount.');

        $pdc = null;
        if ($pdcId) {
            $pdcStmt = $db->prepare("SELECT * FROM post_dated_checks WHERE id = ? AND direction = 'issued' AND supplier_id = ? AND status = 'on_hand' AND is_deleted = 0");
            $pdcStmt->execute([$pdcId, $supplierId]);
            $pdc = $pdcStmt->fetch();
            if (!$pdc) jsonResponse(false, 'Selected check is not an on-hand issued check for this supplier.');
        }

        $invStmt = $db->prepare('SELECT id, supplier_id, balance FROM supplier_invoices WHERE id = ? AND is_deleted = 0 AND status IN (\'unpaid\',\'partial\')');

        $db->beginTransaction();
        try {
            // Disbursements aren't branch-scoped (any branch can pay a
            // shared supplier), same numbering-home approach as collections.
            $anyBranch = (int)($db->query('SELECT id FROM branches ORDER BY id LIMIT 1')->fetchColumn() ?: 1);
            $dvNumber = mintDocNumber($db, $anyBranch, 'DV');
            $db->prepare(
                'INSERT INTO disbursements (dv_number, supplier_id, payment_date, payment_method, amount, remarks, paid_by)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$dvNumber, $supplierId, $paymentDate, $paymentMethod, $amount, $remarks, $user['id']]);
            $disbursementId = (int)$db->lastInsertId();

            foreach ($applications as $app) {
                $invoiceId = (int)$app['supplier_invoice_id'];
                $appliedAmount = (float)$app['amount_applied'];
                $invStmt->execute([$invoiceId]);
                $inv = $invStmt->fetch();
                if (!$inv) throw new RuntimeException('One of the selected invoices is no longer open.');
                if ((int)$inv['supplier_id'] !== $supplierId) throw new RuntimeException('One of the selected invoices does not belong to this supplier.');
                if ($appliedAmount > (float)$inv['balance'] + 0.01) throw new RuntimeException('Applied amount exceeds an invoice\'s remaining balance.');

                $db->prepare('INSERT INTO disbursement_applications (disbursement_id, supplier_invoice_id, amount_applied) VALUES (?,?,?)')
                   ->execute([$disbursementId, $invoiceId, $appliedAmount]);

                $totalStmt = $db->prepare('SELECT total_amount, amount_paid FROM supplier_invoices WHERE id = ?');
                $totalStmt->execute([$invoiceId]);
                $row = $totalStmt->fetch();
                $total = (float)$row['total_amount'];
                $newPaid = round((float)$row['amount_paid'] + $appliedAmount, 2);
                $newBalance = max(0, round($total - $newPaid, 2));
                $newStatus  = $newBalance <= 0.01 ? 'paid' : 'partial';
                $db->prepare('UPDATE supplier_invoices SET amount_paid = ?, balance = ?, status = ? WHERE id = ?')
                   ->execute([$newPaid, $newBalance, $newStatus, $invoiceId]);
            }

            if ($pdc) {
                $db->prepare("UPDATE post_dated_checks SET status = 'cleared', disbursement_id = ? WHERE id = ?")
                   ->execute([$disbursementId, $pdc['id']]);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Disbursement create failed: ' . $e->getMessage());
            jsonResponse(false, $e->getMessage() ?: 'Failed to record disbursement.');
        }

        logAudit('CREATE_DISBURSEMENT', 'payables', "Recorded {$dvNumber} for " . number_format($amount, 2));
        jsonResponse(true, 'Disbursement recorded.', ['dv_number' => $dvNumber]);
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
