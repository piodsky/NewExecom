<?php
// Validate all lines before writing; save the arrival and its audit entries together.
function saveManualInventory(PDO $db, array $user, ?int $scope, array $data): array {
    $batch = ($data['action'] ?? '') === 'manual_add_batch';
    $branchId = filter_var($data['branch_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$branchId) throw new DomainException('Select a branch and an item.');
    if ($scope !== null && $branchId !== $scope) throw new DomainException('Access denied for that branch.');
    $rawRows = $batch ? ($data['items'] ?? null) : [$data];
    if (!is_array($rawRows) || !array_is_list($rawRows) || count($rawRows) < 1 || count($rawRows) > 500) {
        throw new DomainException('Add between 1 and 500 rows per batch.');
    }
    $text = static function ($value): string {
        if (!is_string($value) && !is_int($value) && !is_float($value)) throw new DomainException('Invalid inventory entry.');
        return trim((string)$value);
    };
    $date = normalizeDateTime($text($data['date_received'] ?? ''), 'Date and time received');
    if ($date === null) throw new DomainException('Date and time received is required.');
    $remarks = $text($data['remarks'] ?? '');
    if ($remarks === '' || mb_strlen($remarks) > 255) throw new DomainException('Enter a reason or reference of up to 255 characters.');
    $rows = [];
    foreach ($rawRows as $index => $raw) {
        try {
            if (!is_array($raw)) throw new DomainException('Invalid inventory entry.');
            $itemId = filter_var($raw['item_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$itemId) throw new DomainException('Select a branch and an item.');
            $row = ['item_id' => $itemId];
            foreach (['quantity', 'unit_cost', 'serial_number', 'location'] as $field) $row[$field] = $text($raw[$field] ?? '');
            if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $row['quantity']) || (float)$row['quantity'] <= 0) {
                throw new DomainException('Quantity must be greater than zero, with at most two decimal places.');
            }
            if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $row['unit_cost'])) {
                throw new DomainException('Unit cost must be zero or greater, with at most two decimal places.');
            }
            if (mb_strlen($row['serial_number']) > 100 || mb_strlen($row['location']) > 100) {
                throw new DomainException('Serial number and location must be no longer than 100 characters.');
            }
            $rows[] = $row;
        } catch (DomainException $e) {
            throw new DomainException(($batch ? 'Row ' . ($index + 1) . ': ' : '') . $e->getMessage());
        }
    }

    // PHP holds the session lock during this request. A retry with the same
    // receipt token returns the successful result instead of adding bulk stock twice.
    $requestId = $data['request_id'] ?? '';
    $fingerprint = hash('sha256', json_encode([$branchId, $date, $remarks, $rows], JSON_THROW_ON_ERROR));
    if ($batch) {
        if (!is_string($requestId) || !preg_match('/^[a-f0-9]{32}$/D', $requestId)) throw new DomainException('Invalid batch reference. Reopen inventory and try again.');
        $saved = $_SESSION['inventory_batch_results']['batch_' . $requestId] ?? null;
        if ($saved) {
            if (!hash_equals($saved['fingerprint'], $fingerprint)) throw new DomainException('This batch was already saved. Refresh inventory before starting another batch.');
            return $saved['result'];
        }
    }

    $serializedCount = 0;
    $bulkCount = 0;
    $db->beginTransaction();
    try {
        $branchStmt = $db->prepare('SELECT name FROM branches WHERE id = ? AND is_active = 1');
        $branchStmt->execute([$branchId]);
        $branchName = $branchStmt->fetchColumn();
        if (!$branchName) throw new DomainException('Select an active branch.');

        // Lock items in a consistent order for concurrent manual receipts.
        $ids = array_values(array_unique(array_column($rows, 'item_id')));
        sort($ids, SORT_NUMERIC);
        $itemStmt = $db->prepare('SELECT id, name, unit, is_serialized FROM items WHERE is_active = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id FOR UPDATE');
        $itemStmt->execute($ids);
        $items = array_column($itemStmt->fetchAll(), null, 'id');
        $duplicate = $db->prepare('SELECT id FROM inventory_units WHERE item_id = ? AND serial_number = ? AND is_deleted = 0 LIMIT 1 FOR UPDATE');
        $insertUnit = $db->prepare("INSERT INTO inventory_units (item_id, branch_id, serial_number, status, unit_cost, date_received, location, remarks) VALUES (?, ?, ?, 'in_stock', ?, ?, ?, ?)");
        $stock = $db->prepare('SELECT quantity_on_hand FROM inventory_stock WHERE item_id = ? AND branch_id = ? FOR UPDATE');
        $addStock = $db->prepare('INSERT INTO inventory_stock (item_id, branch_id, quantity_on_hand) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + VALUES(quantity_on_hand)');
        $audit = $db->prepare('INSERT INTO audit_logs (user_id, username, action, module, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $hundredths = static function (string $value): int {
            $sign = str_starts_with($value, '-') ? -1 : 1;
            [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
            return $sign * ((int)$whole * 100 + (int)str_pad($fraction, 2, '0'));
        };
        foreach ($rows as $index => $row) {
            try {
                $item = $items[$row['item_id']] ?? null;
                if (!$item) throw new DomainException('Select an active item from the Item Catalog.');
                $unitId = null;
                if ($item['is_serialized']) {
                    if ((float)$row['quantity'] !== 1.0) throw new DomainException('Add one unit at a time for serial-tracked items.');
                    if ($row['serial_number'] === '') throw new DomainException('A serial number is required for this item.');
                    $duplicate->execute([$row['item_id'], $row['serial_number']]);
                    if ($duplicate->fetchColumn()) throw new DomainException('This serial number already exists for this item: ' . $row['serial_number']);
                    $insertUnit->execute([$row['item_id'], $branchId, $row['serial_number'], $row['unit_cost'], $date, $row['location'] ?: null, $remarks]);
                    $unitId = (int)$db->lastInsertId();
                    $serializedCount++;
                } else {
                    if ($row['serial_number'] !== '' || $row['location'] !== '') throw new DomainException('Serial number and shelf/bin location only apply to serialized units.');
                    $stock->execute([$row['item_id'], $branchId]);
                    $current = $stock->fetchColumn();
                    if ($hundredths((string)($current === false ? '0' : $current)) + $hundredths($row['quantity']) > 99999999999999) {
                        throw new DomainException('This addition would exceed the maximum stock quantity.');
                    }
                    $addStock->execute([$row['item_id'], $branchId, $row['quantity']]);
                    $bulkCount++;
                }
                $details = [
                    'source' => 'manual', 'branch_id' => $branchId, 'branch_name' => $branchName,
                    'item_id' => $row['item_id'], 'item_name' => $item['name'], 'inventory_unit_id' => $unitId,
                    'quantity' => $row['quantity'], 'unit' => $item['unit'], 'unit_cost' => $row['unit_cost'],
                    'serial_number' => $row['serial_number'] ?: null, 'date_received' => $date,
                    'location' => $row['location'] ?: null, 'remarks' => $remarks,
                ];
                if ($batch) $details += ['batch_id' => $requestId, 'batch_row' => $index + 1, 'batch_rows' => count($rows)];
                $audit->execute([$user['id'], $user['username'], 'MANUAL_ADD_INVENTORY', 'inventory', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $_SERVER['REMOTE_ADDR'] ?? null]);
            } catch (DomainException $e) {
                throw new DomainException(($batch ? 'Row ' . ($index + 1) . ': ' : '') . $e->getMessage());
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    $result = ['is_serialized' => $serializedCount > 0, 'row_count' => count($rows), 'serialized_count' => $serializedCount, 'bulk_count' => $bulkCount];
    if ($batch) {
        $_SESSION['inventory_batch_results']['batch_' . $requestId] = ['fingerprint' => $fingerprint, 'result' => $result];
        $_SESSION['inventory_batch_results'] = array_slice($_SESSION['inventory_batch_results'], -20, null, true);
    }
    return $result;
}
