<?php
// Quick Scan: record serials before anyone has picked a master item for
// them, then Segregate assigns a batch of those serials to one item at
// once. Mirrors the idempotency and locking pattern in manual_inventory.php.

function saveQuickScanBatch(PDO $db, array $user, ?int $scope, array $data): array {
    $branchId = filter_var($data['branch_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$branchId) throw new DomainException('Select a branch.');
    if ($scope !== null && $branchId !== $scope) throw new DomainException('Access denied for that branch.');
    $date = normalizeDateTime((string)($data['date_received'] ?? ''), 'Date and time received');
    if ($date === null) throw new DomainException('Date and time received is required.');
    $remarks = trim((string)($data['remarks'] ?? ''));
    if (mb_strlen($remarks) > 255) throw new DomainException('Remarks must be no longer than 255 characters.');

    $rawSerials = $data['serials'] ?? null;
    if (!is_array($rawSerials) || !array_is_list($rawSerials) || count($rawSerials) < 1 || count($rawSerials) > 500) {
        throw new DomainException('Scan between 1 and 500 serials per batch.');
    }
    $serials = [];
    $seen = [];
    foreach ($rawSerials as $index => $raw) {
        if (!is_string($raw) && !is_int($raw)) throw new DomainException('Row ' . ($index + 1) . ': invalid serial number.');
        $serial = trim((string)$raw);
        if ($serial === '' || mb_strlen($serial) > 100) throw new DomainException('Row ' . ($index + 1) . ': serial number must be 1–100 characters.');
        $key = mb_strtolower($serial);
        if (isset($seen[$key])) throw new DomainException('Row ' . ($index + 1) . ': serial ' . $serial . ' is already in this batch.');
        $seen[$key] = true;
        $serials[] = $serial;
    }

    $requestId = $data['request_id'] ?? '';
    if (!is_string($requestId) || !preg_match('/^[a-f0-9]{32}$/D', $requestId)) throw new DomainException('Invalid batch reference. Reopen inventory and try again.');
    $fingerprint = hash('sha256', json_encode([$branchId, $date, $remarks, $serials], JSON_THROW_ON_ERROR));
    $saved = $_SESSION['inventory_batch_results']['quickscan_' . $requestId] ?? null;
    if ($saved) {
        if (!hash_equals($saved['fingerprint'], $fingerprint)) throw new DomainException('This batch was already saved. Refresh inventory before starting another batch.');
        return $saved['result'];
    }

    $db->beginTransaction();
    try {
        $branchStmt = $db->prepare('SELECT name FROM branches WHERE id = ? AND is_active = 1');
        $branchStmt->execute([$branchId]);
        $branchName = $branchStmt->fetchColumn();
        if (!$branchName) throw new DomainException('Select an active branch.');

        $dupUnassigned = $db->prepare('SELECT id FROM inventory_unassigned_units WHERE serial_number = ? AND is_deleted = 0 LIMIT 1 FOR UPDATE');
        $dupUnit = $db->prepare('SELECT id FROM inventory_units WHERE serial_number = ? AND is_deleted = 0 LIMIT 1 FOR UPDATE');
        $insert = $db->prepare('INSERT INTO inventory_unassigned_units (branch_id, serial_number, date_received, remarks, scanned_by) VALUES (?, ?, ?, ?, ?)');
        $audit = $db->prepare('INSERT INTO audit_logs (user_id, username, action, module, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');

        foreach ($serials as $index => $serial) {
            try {
                $dupUnassigned->execute([$serial]);
                if ($dupUnassigned->fetchColumn()) throw new DomainException('Serial ' . $serial . ' is already waiting to be segregated.');
                $dupUnit->execute([$serial]);
                if ($dupUnit->fetchColumn()) throw new DomainException('Serial ' . $serial . ' already exists in inventory.');
                $insert->execute([$branchId, $serial, $date, $remarks ?: null, $user['id']]);
                $details = [
                    'branch_id' => $branchId, 'branch_name' => $branchName, 'serial_number' => $serial,
                    'date_received' => $date, 'remarks' => $remarks ?: null,
                    'batch_id' => $requestId, 'batch_row' => $index + 1, 'batch_rows' => count($serials),
                ];
                $audit->execute([$user['id'], $user['username'], 'QUICK_SCAN_INVENTORY', 'inventory', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $_SERVER['REMOTE_ADDR'] ?? null]);
            } catch (DomainException $e) {
                throw new DomainException('Row ' . ($index + 1) . ': ' . $e->getMessage());
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    $result = ['row_count' => count($serials)];
    $_SESSION['inventory_batch_results']['quickscan_' . $requestId] = ['fingerprint' => $fingerprint, 'result' => $result];
    $_SESSION['inventory_batch_results'] = array_slice($_SESSION['inventory_batch_results'], -20, null, true);
    return $result;
}

// Assigns a batch of previously-scanned, still-unassigned serials to one
// master item, turning each into a normal inventory_units row.
function segregateUnassignedUnits(PDO $db, array $user, ?int $scope, array $data): array {
    $itemId = filter_var($data['item_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$itemId) throw new DomainException('Select a master item.');
    $unitCost = trim((string)($data['unit_cost'] ?? ''));
    if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $unitCost)) throw new DomainException('Unit cost must be zero or greater, with at most two decimal places.');
    $location = trim((string)($data['location'] ?? ''));
    if (mb_strlen($location) > 100) throw new DomainException('Location must be no longer than 100 characters.');
    $remarks = trim((string)($data['remarks'] ?? ''));
    if (mb_strlen($remarks) > 255) throw new DomainException('Remarks must be no longer than 255 characters.');

    $rawIds = $data['unassigned_ids'] ?? null;
    if (!is_array($rawIds) || !array_is_list($rawIds) || count($rawIds) < 1 || count($rawIds) > 500) {
        throw new DomainException('Select between 1 and 500 scanned serials to assign.');
    }
    $ids = [];
    foreach ($rawIds as $raw) {
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) throw new DomainException('Invalid scanned serial selected.');
        $ids[] = $id;
    }
    $ids = array_values(array_unique($ids));
    sort($ids, SORT_NUMERIC);

    $requestId = $data['request_id'] ?? '';
    if (!is_string($requestId) || !preg_match('/^[a-f0-9]{32}$/D', $requestId)) throw new DomainException('Invalid batch reference. Reopen inventory and try again.');
    $fingerprint = hash('sha256', json_encode([$itemId, $unitCost, $location, $remarks, $ids], JSON_THROW_ON_ERROR));
    $saved = $_SESSION['inventory_batch_results']['segregate_' . $requestId] ?? null;
    if ($saved) {
        if (!hash_equals($saved['fingerprint'], $fingerprint)) throw new DomainException('This batch was already saved. Refresh inventory before trying again.');
        return $saved['result'];
    }

    $db->beginTransaction();
    try {
        $itemStmt = $db->prepare('SELECT id, name, unit, is_serialized FROM items WHERE id = ? AND is_active = 1 FOR UPDATE');
        $itemStmt->execute([$itemId]);
        $item = $itemStmt->fetch();
        if (!$item) throw new DomainException('Select an active item from the Item Catalog.');
        if (!$item['is_serialized']) throw new DomainException('Scanned serials can only be assigned to a serialized item.');

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rowsStmt = $db->prepare("SELECT id, branch_id, serial_number, date_received, remarks FROM inventory_unassigned_units WHERE id IN ($placeholders) AND is_deleted = 0 ORDER BY id FOR UPDATE");
        $rowsStmt->execute($ids);
        $rows = $rowsStmt->fetchAll();
        if (count($rows) !== count($ids)) throw new DomainException('One or more scanned serials were already segregated or removed. Refresh and try again.');

        $dupUnit = $db->prepare('SELECT id FROM inventory_units WHERE item_id = ? AND serial_number = ? AND is_deleted = 0 LIMIT 1 FOR UPDATE');
        $insertUnit = $db->prepare("INSERT INTO inventory_units (item_id, branch_id, serial_number, status, unit_cost, date_received, location, remarks) VALUES (?, ?, ?, 'in_stock', ?, ?, ?, ?)");
        $markDone = $db->prepare('UPDATE inventory_unassigned_units SET is_deleted = 1 WHERE id = ?');
        $audit = $db->prepare('INSERT INTO audit_logs (user_id, username, action, module, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');

        foreach ($rows as $index => $row) {
            try {
                if ($scope !== null && (int)$row['branch_id'] !== $scope) throw new DomainException('Access denied for serial ' . $row['serial_number'] . '.');
                $dupUnit->execute([$itemId, $row['serial_number']]);
                if ($dupUnit->fetchColumn()) throw new DomainException('Serial ' . $row['serial_number'] . ' already exists for ' . $item['name'] . '.');
                $unitRemarks = $remarks !== '' ? $remarks : $row['remarks'];
                $insertUnit->execute([$itemId, $row['branch_id'], $row['serial_number'], $unitCost, $row['date_received'], $location ?: null, $unitRemarks]);
                $unitId = (int)$db->lastInsertId();
                $markDone->execute([$row['id']]);
                $details = [
                    'source' => 'segregate', 'unassigned_id' => $row['id'], 'branch_id' => $row['branch_id'],
                    'item_id' => $itemId, 'item_name' => $item['name'], 'inventory_unit_id' => $unitId,
                    'serial_number' => $row['serial_number'], 'unit_cost' => $unitCost, 'location' => $location ?: null,
                    'remarks' => $unitRemarks, 'batch_id' => $requestId, 'batch_row' => $index + 1, 'batch_rows' => count($rows),
                ];
                $audit->execute([$user['id'], $user['username'], 'SEGREGATE_INVENTORY', 'inventory', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $_SERVER['REMOTE_ADDR'] ?? null]);
            } catch (DomainException $e) {
                throw new DomainException('Row ' . ($index + 1) . ': ' . $e->getMessage());
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    $result = ['row_count' => count($rows), 'item_name' => $item['name']];
    $_SESSION['inventory_batch_results']['segregate_' . $requestId] = ['fingerprint' => $fingerprint, 'result' => $result];
    $_SESSION['inventory_batch_results'] = array_slice($_SESSION['inventory_batch_results'], -20, null, true);
    return $result;
}
