<?php
// Payables (Accounts Payable) — the buy-side mirror of Billing/AR. A
// receiving_report obligates payment to a supplier the same way an issuance
// obligates a client to pay us; mirrors Globalchips' PPAYTRN/ppaylin.
require_once '../config.php';
requireModule('payables');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

if ($method === 'GET') {
    // Receiving reports that could become a payable: tied to a PO with a
    // real supplier, not already invoiced.
    if (isset($_GET['unbilled_receiving'])) {
        $sql = "SELECT rr.rr_number, rr.branch_id, rr.date_received, po.supplier_id, s.name AS supplier_name,
                       s.payment_terms_days,
                       (SELECT COALESCE(SUM(rri.quantity * rri.unit_cost), 0) FROM receiving_report_items rri
                        WHERE rri.rr_number = rr.rr_number AND rri.is_deleted = 0) AS total_amount
                FROM receiving_reports rr
                JOIN po_internal po ON po.po_number = rr.po_number
                JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN supplier_invoices si ON si.rr_number = rr.rr_number AND si.is_deleted = 0
                WHERE rr.is_deleted = 0 AND po.supplier_id IS NOT NULL AND si.id IS NULL";
        $params = [];
        if ($scope !== null) { $sql .= ' AND rr.branch_id = ?'; $params[] = $scope; }
        $sql .= ' ORDER BY rr.date_received DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['receiving_reports' => $stmt->fetchAll()]);
    }

    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $stmt = $db->prepare(
            'SELECT pi.*, s.name AS supplier_name, s.contact_number AS supplier_contact
             FROM supplier_invoices pi LEFT JOIN suppliers s ON s.id = pi.supplier_id
             WHERE pi.invoice_number = ? AND pi.is_deleted = 0'
        );
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) jsonResponse(false, 'Purchase invoice not found.');

        $appStmt = $db->prepare(
            'SELECT da.amount_applied, d.dv_number, d.payment_date, d.payment_method
             FROM disbursement_applications da JOIN disbursements d ON d.id = da.disbursement_id
             WHERE da.supplier_invoice_id = ? ORDER BY d.payment_date'
        );
        $appStmt->execute([$inv['id']]);
        $inv['payments'] = $appStmt->fetchAll();
        jsonResponse(true, '', ['invoice' => $inv]);
    }

    $status = trim($_GET['status'] ?? '');
    $sql = "SELECT pi.id, pi.invoice_number, pi.rr_number, pi.supplier_id,
                   s.name AS supplier_name, pi.due_date, pi.total_amount, pi.amount_paid, pi.balance, pi.status,
                   DATEDIFF(CURDATE(), pi.due_date) AS days_overdue
            FROM supplier_invoices pi
            LEFT JOIN suppliers s ON s.id = pi.supplier_id
            LEFT JOIN receiving_reports rr ON rr.rr_number = pi.rr_number
            WHERE pi.is_deleted = 0";
    $params = [];
    if ($scope !== null) { $sql .= ' AND rr.branch_id = ?'; $params[] = $scope; }
    if ($status !== '' && $status !== 'all') { $sql .= ' AND pi.status = ?'; $params[] = $status; }
    $sql .= ' ORDER BY pi.due_date IS NULL, pi.due_date ASC';
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

    if ($action === 'create_from_receiving') {
        $rrNumber = trim($data['rr_number'] ?? '');
        $stmt = $db->prepare(
            'SELECT rr.rr_number, rr.branch_id, po.supplier_id, s.payment_terms_days,
                    (SELECT COALESCE(SUM(rri.quantity * rri.unit_cost), 0) FROM receiving_report_items rri
                     WHERE rri.rr_number = rr.rr_number AND rri.is_deleted = 0) AS total_amount
             FROM receiving_reports rr
             JOIN po_internal po ON po.po_number = rr.po_number
             JOIN suppliers s ON s.id = po.supplier_id
             WHERE rr.rr_number = ? AND rr.is_deleted = 0'
        );
        $stmt->execute([$rrNumber]);
        $rr = $stmt->fetch();
        if (!$rr) jsonResponse(false, 'Receiving report not found or has no linked supplier.');
        if ($scope !== null && (int)$rr['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

        $dupCheck = $db->prepare('SELECT COUNT(*) FROM supplier_invoices WHERE rr_number = ? AND is_deleted = 0');
        $dupCheck->execute([$rrNumber]);
        if ($dupCheck->fetchColumn()) jsonResponse(false, 'This receiving report has already been invoiced.');

        $termsDays = (int)($rr['payment_terms_days'] ?? 0);
        $dueDate = $termsDays > 0 ? date('Y-m-d', strtotime("+{$termsDays} days")) : date('Y-m-d');

        $db->beginTransaction();
        try {
            $invoiceNumber = mintDocNumber($db, (int)$rr['branch_id'], 'PINV');
            $db->prepare(
                'INSERT INTO supplier_invoices (invoice_number, rr_number, supplier_id, due_date, total_amount, balance, created_by)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([$invoiceNumber, $rrNumber, $rr['supplier_id'], $dueDate, $rr['total_amount'], $rr['total_amount'], $user['id']]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Supplier invoice create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to create purchase invoice.');
        }

        logAudit('CREATE_SUPPLIER_INVOICE', 'payables', "Created {$invoiceNumber} from {$rrNumber}");
        jsonResponse(true, 'Purchase invoice created.', ['invoice_number' => $invoiceNumber]);
    }

    if ($action === 'void') {
        if (!$isAdminTier) jsonResponse(false, 'Only administrators can void a purchase invoice.');
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM supplier_invoices WHERE id = ? AND is_deleted = 0');
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) jsonResponse(false, 'Purchase invoice not found.');
        if ((float)$inv['amount_paid'] > 0) jsonResponse(false, 'Cannot void an invoice that already has payments applied.');

        $db->prepare("UPDATE supplier_invoices SET status = 'void' WHERE id = ?")->execute([$id]);
        logAudit('VOID_SUPPLIER_INVOICE', 'payables', "Voided {$inv['invoice_number']}");
        jsonResponse(true, 'Purchase invoice voided.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
