<?php
// Job Order — Repair/Service. Execom's real, currently-running computer
// repair workflow (see files/JOB ORDER.pdf), given a home here instead of
// living only on the separate execom-online.com/ecss site. Modernized from
// Globalchips' jobtrn/joblin pair — same intake shape (unit, serial, problem,
// warranty), minus its payroll/GL-adjacent columns.
//
// AR only ever applies to a registered client, never a walk-in — the same
// rule Billing & Collections uses. A walk-in repair is assumed paid in cash
// on release, so releasing one never creates a sales_invoices row.
require_once '../config.php';
requireModule('job_orders');
header('Content-Type: application/json');

$user   = getCurrentUser();
$scope  = getUserBranchScope($user);
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

function loadJobOrder(PDO $db, string $joNumber): ?array {
    $stmt = $db->prepare(
        'SELECT jo.*, b.name AS branch_name,
                COALESCE(c.name, jo.walkin_name) AS client_name,
                c.payment_terms_days,
                t.full_name AS technician_name, u.full_name AS received_by_name
         FROM job_orders jo
         JOIN branches b ON b.id = jo.branch_id
         LEFT JOIN clients c ON c.id = jo.client_id
         LEFT JOIN users t ON t.id = jo.technician_id
         JOIN users u ON u.id = jo.received_by
         WHERE jo.jo_number = ? AND jo.is_deleted = 0'
    );
    $stmt->execute([$joNumber]);
    $jo = $stmt->fetch();
    if (!$jo) return null;

    $partStmt = $db->prepare(
        "SELECT jp.id, jp.inventory_unit_id, jp.item_id, jp.quantity, jp.unit_cost, jp.unit_price,
                COALESCE(i1.name, i2.name) AS item_name, iu.serial_number
         FROM job_order_parts jp
         LEFT JOIN items i1 ON i1.id = jp.item_id
         LEFT JOIN inventory_units iu ON iu.id = jp.inventory_unit_id
         LEFT JOIN items i2 ON i2.id = iu.item_id
         WHERE jp.jo_number = ? AND jp.is_deleted = 0 ORDER BY jp.id"
    );
    $partStmt->execute([$joNumber]);
    $jo['parts'] = $partStmt->fetchAll();
    $partsTotal = 0;
    foreach ($jo['parts'] as $p) { $partsTotal += (float)$p['unit_price'] * (float)($p['quantity'] ?? 1); }
    $jo['parts_total'] = $partsTotal;
    $jo['grand_total'] = $partsTotal + (float)$jo['labor_fee'];

    $invStmt = $db->prepare('SELECT invoice_number, status, balance FROM sales_invoices WHERE jo_number = ? AND is_deleted = 0');
    $invStmt->execute([$joNumber]);
    $jo['sales_invoice'] = $invStmt->fetch() ?: null;

    return $jo;
}

if ($method === 'GET') {
    $id = trim($_GET['id'] ?? '');
    if ($id !== '') {
        $jo = loadJobOrder($db, $id);
        if (!$jo) jsonResponse(false, 'Job order not found.');
        if ($scope !== null && (int)$jo['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');
        jsonResponse(true, '', ['job_order' => $jo]);
    }

    // Same shape as api/issuances.php's catalog_for — sellable stock at a
    // branch, reused here for picking parts to attach to a job order.
    $catalogFor = trim($_GET['catalog_for'] ?? '');
    if ($catalogFor !== '') {
        $branchId = (int)$catalogFor;
        if ($scope !== null && $branchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

        $unitStmt = $db->prepare(
            "SELECT u.id, u.item_id, i.name AS item_name, i.sku, i.default_price, u.serial_number, u.unit_cost
             FROM inventory_units u JOIN items i ON i.id = u.item_id
             WHERE u.branch_id = ? AND u.status = 'in_stock' AND u.is_deleted = 0 ORDER BY i.name"
        );
        $unitStmt->execute([$branchId]);

        $stockStmt = $db->prepare(
            'SELECT s.item_id, i.name AS item_name, i.sku, i.default_price, s.quantity_on_hand
             FROM inventory_stock s JOIN items i ON i.id = s.item_id
             WHERE s.branch_id = ? AND s.quantity_on_hand > 0 ORDER BY i.name'
        );
        $stockStmt->execute([$branchId]);

        jsonResponse(true, '', ['units' => $unitStmt->fetchAll(), 'stock' => $stockStmt->fetchAll()]);
    }

    if (isset($_GET['technicians'])) {
        $techStmt = $db->prepare(
            $scope !== null
                ? "SELECT id, full_name FROM users WHERE is_active = 1 AND (branch_id = ? OR branch_id IS NULL) ORDER BY full_name"
                : 'SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name'
        );
        $scope !== null ? $techStmt->execute([$scope]) : $techStmt->execute();
        jsonResponse(true, '', ['technicians' => $techStmt->fetchAll()]);
    }

    $status = trim($_GET['status'] ?? '');
    $sql = "SELECT jo.jo_number, jo.branch_id, b.name AS branch_name,
                   COALESCE(c.name, jo.walkin_name) AS client_name,
                   jo.unit_description, jo.serial_number, jo.status, jo.is_under_warranty,
                   jo.labor_fee, jo.date_received, jo.date_released,
                   t.full_name AS technician_name
            FROM job_orders jo
            JOIN branches b ON b.id = jo.branch_id
            LEFT JOIN clients c ON c.id = jo.client_id
            LEFT JOIN users t ON t.id = jo.technician_id
            WHERE jo.is_deleted = 0";
    $params = [];
    if ($scope !== null) { $sql .= ' AND jo.branch_id = ?'; $params[] = $scope; }
    if ($status !== '' && $status !== 'all') { $sql .= ' AND jo.status = ?'; $params[] = $status; }
    $sql .= ' ORDER BY jo.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['job_orders' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $branchId = $scope !== null ? $scope : (int)($data['branch_id'] ?? 0);
        if (!$branchId) jsonResponse(false, 'Branch is required.');
        if ($scope !== null && $branchId !== $scope) jsonResponse(false, 'Access denied for that branch.');

        $clientId = !empty($data['client_id']) ? (int)$data['client_id'] : null;
        if ($clientId) {
            $clientCheck = $db->prepare('SELECT COUNT(*) FROM clients WHERE id = ? AND is_active = 1');
            $clientCheck->execute([$clientId]);
            if (!$clientCheck->fetchColumn()) jsonResponse(false, 'Client not found.');
        }
        $walkinName = $walkinContact = null;
        if (!$clientId) {
            $walkinName = trim($data['walkin_name'] ?? '');
            if ($walkinName === '') jsonResponse(false, 'Customer name is required.');
            $walkinContact = trim($data['walkin_contact'] ?? '') ?: null;
        }

        $unitDescription = trim($data['unit_description'] ?? '');
        if ($unitDescription === '') jsonResponse(false, 'Unit description is required.');
        $serialNumber   = trim($data['serial_number'] ?? '') ?: null;
        $problem        = trim($data['problem_description'] ?? '') ?: null;
        $accessories    = trim($data['accessories_received'] ?? '') ?: null;
        $technicianId   = !empty($data['technician_id']) ? (int)$data['technician_id'] : null;
        $isWarranty     = !empty($data['is_under_warranty']) ? 1 : 0;
        $remarks        = trim($data['remarks'] ?? '') ?: null;

        $db->beginTransaction();
        try {
            $joNumber = mintDocNumber($db, $branchId, 'JO');
            $db->prepare(
                'INSERT INTO job_orders (jo_number, branch_id, client_id, walkin_name, walkin_contact, unit_description, serial_number, problem_description, accessories_received, technician_id, is_under_warranty, remarks, received_by, date_received)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
            )->execute([$joNumber, $branchId, $clientId, $walkinName, $walkinContact, $unitDescription, $serialNumber, $problem, $accessories, $technicianId, $isWarranty, $remarks, $user['id']]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Job order create failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to create job order.');
        }

        logAudit('CREATE_JOB_ORDER', 'job_orders', "Created {$joNumber}");
        jsonResponse(true, 'Job order created.', ['jo_number' => $joNumber]);
    }

    // Every action below targets an existing job order.
    $joNumber = trim($data['jo_number'] ?? '');
    if ($joNumber === '') jsonResponse(false, 'Invalid job order.');
    $jo = loadJobOrder($db, $joNumber);
    if (!$jo) jsonResponse(false, 'Job order not found.');
    if ($scope !== null && (int)$jo['branch_id'] !== $scope) jsonResponse(false, 'Access denied.');

    if ($action === 'update_status') {
        if (in_array($jo['status'], ['released', 'cancelled'], true)) jsonResponse(false, 'This job order is already closed.');
        $newStatus = trim($data['status'] ?? '');
        $allowed = ['received', 'in_diagnosis', 'awaiting_parts', 'repaired', 'cancelled'];
        if (!in_array($newStatus, $allowed, true)) jsonResponse(false, 'Invalid status.');
        $db->prepare('UPDATE job_orders SET status = ? WHERE jo_number = ?')->execute([$newStatus, $joNumber]);
        logAudit('UPDATE_JOB_ORDER_STATUS', 'job_orders', "{$joNumber} -> {$newStatus}");
        jsonResponse(true, 'Status updated.');
    }

    if ($action === 'set_labor_fee') {
        if (in_array($jo['status'], ['released', 'cancelled'], true)) jsonResponse(false, 'This job order is already closed.');
        $fee = (float)($data['labor_fee'] ?? 0);
        if ($fee < 0) jsonResponse(false, 'Labor fee cannot be negative.');
        $db->prepare('UPDATE job_orders SET labor_fee = ? WHERE jo_number = ?')->execute([$fee, $joNumber]);
        jsonResponse(true, 'Labor fee updated.');
    }

    if ($action === 'add_part') {
        if (in_array($jo['status'], ['released', 'cancelled'], true)) jsonResponse(false, 'This job order is already closed.');
        $unitPrice = (float)($data['unit_price'] ?? -1);
        if ($unitPrice < 0) jsonResponse(false, 'Selling price is required for every part.');

        $db->beginTransaction();
        try {
            if (!empty($data['inventory_unit_id'])) {
                $unitId = (int)$data['inventory_unit_id'];
                $unitCheck = $db->prepare("SELECT id, item_id, unit_cost FROM inventory_units WHERE id = ? AND branch_id = ? AND status = 'in_stock' AND is_deleted = 0");
                $unitCheck->execute([$unitId, $jo['branch_id']]);
                $unit = $unitCheck->fetch();
                if (!$unit) throw new RuntimeException('That serialized unit is no longer available at this branch.');
                $db->prepare('INSERT INTO job_order_parts (jo_number, inventory_unit_id, item_id, quantity, unit_cost, unit_price) VALUES (?,?,?,?,?,?)')
                   ->execute([$joNumber, $unitId, null, 1, $unit['unit_cost'], $unitPrice]);
                $db->prepare("UPDATE inventory_units SET status = 'issued', date_out = NOW() WHERE id = ?")->execute([$unitId]);
            } elseif (!empty($data['item_id']) && (float)($data['quantity'] ?? 0) > 0) {
                $itemId = (int)$data['item_id'];
                $qty    = (float)$data['quantity'];
                $stockCheck = $db->prepare('SELECT quantity_on_hand FROM inventory_stock WHERE item_id = ? AND branch_id = ?');
                $stockCheck->execute([$itemId, $jo['branch_id']]);
                $stock = $stockCheck->fetch();
                if (!$stock || (float)$stock['quantity_on_hand'] < $qty) throw new RuntimeException('Not enough stock on hand for that part.');
                $itemStmt = $db->prepare('SELECT default_cost FROM items WHERE id = ?');
                $itemStmt->execute([$itemId]);
                $unitCost = (float)($itemStmt->fetchColumn() ?: 0);
                $db->prepare('INSERT INTO job_order_parts (jo_number, inventory_unit_id, item_id, quantity, unit_cost, unit_price) VALUES (?,?,?,?,?,?)')
                   ->execute([$joNumber, null, $itemId, $qty, $unitCost, $unitPrice]);
                $db->prepare('UPDATE inventory_stock SET quantity_on_hand = quantity_on_hand - ? WHERE item_id = ? AND branch_id = ?')
                   ->execute([$qty, $itemId, $jo['branch_id']]);
            } else {
                throw new RuntimeException('Select a serialized unit or an item with a quantity.');
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(false, $e->getMessage() ?: 'Failed to add part.');
        }
        if (!empty($data['item_id'])) checkLowStock($db, (int)$data['item_id'], (int)$jo['branch_id']);
        logAudit('ADD_JOB_ORDER_PART', 'job_orders', "Added part to {$joNumber}");
        jsonResponse(true, 'Part added.');
    }

    if ($action === 'remove_part') {
        if (in_array($jo['status'], ['released', 'cancelled'], true)) jsonResponse(false, 'This job order is already closed.');
        $partId = (int)($data['part_id'] ?? 0);
        $part = null;
        foreach ($jo['parts'] as $p) { if ((int)$p['id'] === $partId) { $part = $p; break; } }
        if (!$part) jsonResponse(false, 'Part not found on this job order.');

        $db->beginTransaction();
        try {
            if ($part['inventory_unit_id']) {
                $db->prepare("UPDATE inventory_units SET status = 'in_stock', date_out = NULL WHERE id = ?")->execute([$part['inventory_unit_id']]);
            } else {
                $db->prepare('UPDATE inventory_stock SET quantity_on_hand = quantity_on_hand + ? WHERE item_id = ? AND branch_id = ?')
                   ->execute([$part['quantity'], $part['item_id'], $jo['branch_id']]);
            }
            $db->prepare('UPDATE job_order_parts SET is_deleted = 1 WHERE id = ?')->execute([$partId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Job order part removal failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to remove part.');
        }
        jsonResponse(true, 'Part removed and stock restored.');
    }

    if ($action === 'release') {
        if (in_array($jo['status'], ['released', 'cancelled'], true)) jsonResponse(false, 'This job order is already closed.');
        $grandTotal = $jo['grand_total'];
        $invoiceNumber = null;

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE job_orders SET status = ?, date_released = NOW() WHERE jo_number = ?')->execute(['released', $joNumber]);

            // AR only ever applies to a registered client — a walk-in repair
            // is assumed paid in cash on release, same rule as Issuance/POS.
            if ($jo['client_id'] && $grandTotal > 0.0001) {
                $invoiceNumber = mintDocNumber($db, $jo['branch_id'], 'SI');
                $termsDays = (int)($jo['payment_terms_days'] ?? 0);
                $dueDate = $termsDays > 0 ? date('Y-m-d', strtotime("+{$termsDays} days")) : date('Y-m-d');
                $db->prepare(
                    'INSERT INTO sales_invoices (invoice_number, jo_number, client_id, due_date, total_amount, balance, created_by)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([$invoiceNumber, $joNumber, $jo['client_id'], $dueDate, $grandTotal, $grandTotal, $user['id']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Job order release failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to release job order.');
        }

        logAudit('RELEASE_JOB_ORDER', 'job_orders', "Released {$joNumber}" . ($invoiceNumber ? " -> invoice {$invoiceNumber}" : ''));
        jsonResponse(true, $invoiceNumber ? "Released and billed as {$invoiceNumber}." : 'Released.', ['invoice_number' => $invoiceNumber]);
    }

    if ($action === 'cancel') {
        if (!$isAdminTier) jsonResponse(false, 'Only administrators can cancel a job order.');
        if (in_array($jo['status'], ['released', 'cancelled'], true)) jsonResponse(false, 'This job order is already closed.');

        $db->beginTransaction();
        try {
            foreach ($jo['parts'] as $part) {
                if ($part['inventory_unit_id']) {
                    $db->prepare("UPDATE inventory_units SET status = 'in_stock', date_out = NULL WHERE id = ?")->execute([$part['inventory_unit_id']]);
                } else {
                    $db->prepare('UPDATE inventory_stock SET quantity_on_hand = quantity_on_hand + ? WHERE item_id = ? AND branch_id = ?')
                       ->execute([$part['quantity'], $part['item_id'], $jo['branch_id']]);
                }
            }
            $db->prepare("UPDATE job_order_parts SET is_deleted = 1 WHERE jo_number = ?")->execute([$joNumber]);
            $db->prepare("UPDATE job_orders SET status = 'cancelled' WHERE jo_number = ?")->execute([$joNumber]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Job order cancel failed: ' . $e->getMessage());
            jsonResponse(false, 'Failed to cancel job order.');
        }
        logAudit('CANCEL_JOB_ORDER', 'job_orders', "Cancelled {$joNumber}");
        jsonResponse(true, 'Job order cancelled and parts restocked.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
