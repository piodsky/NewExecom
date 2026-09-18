<?php
// CLI integration checks: php tools/test_manual_inventory.php
// All writes use connection-local temporary tables that shadow the app tables.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }

ob_start();
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../system/config.php';
require_once __DIR__ . '/../system/lib/manual_inventory.php';
$testDb = getDB();
$testDb->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
foreach (['branches', 'items', 'item_categories', 'inventory_units', 'inventory_stock', 'audit_logs', 'role_module_access', 'user_module_access'] as $table) {
    $definition = $testDb->query("SHOW CREATE TABLE `$table`")->fetch()['Create Table'];
    $definition = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $definition);
    $definition = preg_replace('/^\s*CONSTRAINT .*FOREIGN KEY.*\R/m', '', $definition);
    $definition = preg_replace('/,(\r?\n\))/', '$1', $definition);
    $testDb->exec($definition);
}

final class InventoryTestResponse extends RuntimeException {
    public function __construct(public array $response) { parent::__construct($response['message']); }
}
function inventoryTestRespond($success, $message = '', $data = []): never {
    throw new InventoryTestResponse(['success' => $success, 'message' => $message, ...$data]);
}
function checkInventory($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function resetInventoryFixtures(PDO $db): void {
    foreach (['inventory_units', 'inventory_stock', 'audit_logs', 'items', 'branches'] as $table) {
        $db->exec("DELETE FROM `$table`");
    }
    $db->exec("INSERT INTO branches (id, code, name, is_active) VALUES (1, 'T1', 'Test Branch One', 1), (2, 'T2', 'Test Branch Two', 1), (3, 'T3', 'Inactive Branch', 0)");
    $db->exec("INSERT INTO items (id, name, unit, is_serialized, is_active) VALUES (1, 'Test Laptop', 'unit', 1, 1), (2, 'Test Cable', 'meter', 0, 1), (3, 'Inactive Item', 'unit', 0, 0)");
    $_SESSION = [
        'pbs_user_id' => 1, 'pbs_username' => 'inventory_test', 'pbs_full_name' => 'Inventory Test',
        'pbs_role' => 'branch_admin', 'pbs_branch_id' => 1, 'inventory_csrf_token' => 'test-token',
    ];
    $_SERVER['REQUEST_URI'] = '/api/inventory.php';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}
function requestInventory(array $overrides = [], string $method = 'POST', array $query = []): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = $query;
    $_POST = array_replace([
        'action' => 'manual_add', 'csrf_token' => 'test-token', 'branch_id' => '1', 'item_id' => '2',
        'quantity' => '2.25', 'unit_cost' => '123.45', 'serial_number' => '',
        'date_received' => '2026-09-17T09:30:45', 'location' => '', 'remarks' => 'Opening stock from physical count',
    ], $overrides);
    $source = file_get_contents(__DIR__ . '/../system/api/inventory.php');
    $source = str_replace(["<?php", "require_once '../config.php';", "require_once __DIR__ . '/../lib/manual_inventory.php';", 'jsonResponse('], ['', '', '', 'inventoryTestRespond('], $source);
    try {
        eval($source);
    } catch (InventoryTestResponse $response) {
        return $response->response;
    }
    throw new RuntimeException('Endpoint returned no response.');
}
function assertInventoryRejected(array $data, string $message): void {
    $response = requestInventory($data);
    checkInventory(!$response['success'] && str_contains($response['message'], $message), 'Expected rejection: ' . $message);
    $db = getDB();
    checkInventory((int)$db->query('SELECT COUNT(*) FROM inventory_units')->fetchColumn() === 0, 'Rejected entry wrote a unit.');
    checkInventory((int)$db->query('SELECT COUNT(*) FROM inventory_stock')->fetchColumn() === 0, 'Rejected entry wrote bulk stock.');
    checkInventory((int)$db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 0, 'Rejected entry wrote an audit record.');
}

$checks = [
    'bulk entry creates stock and a complete audit record' => function () use ($testDb) {
        $response = requestInventory();
        checkInventory($response['success'] && !$response['is_serialized'], 'Bulk addition failed.');
        checkInventory($testDb->query('SELECT quantity_on_hand FROM inventory_stock')->fetchColumn() === '2.25', 'Bulk quantity differs.');
        $audit = $testDb->query('SELECT * FROM audit_logs')->fetch();
        $details = json_decode($audit['details'], true, 512, JSON_THROW_ON_ERROR);
        checkInventory($audit['action'] === 'MANUAL_ADD_INVENTORY' && $audit['username'] === 'inventory_test', 'Audit actor/action missing.');
        checkInventory($details['source'] === 'manual' && $details['unit_cost'] === '123.45' && $details['date_received'] === '2026-09-17 09:30:45' && $details['branch_id'] === 1 && $details['remarks'] === 'Opening stock from physical count', 'Audit entry is incomplete.');
    },
    'bulk addition increments existing stock and preserves reorder level' => function () use ($testDb) {
        $testDb->exec('INSERT INTO inventory_stock (item_id, branch_id, quantity_on_hand, reorder_level) VALUES (2, 1, 10.50, 5)');
        checkInventory(requestInventory()['success'], 'Bulk increment failed.');
        $row = $testDb->query('SELECT quantity_on_hand, reorder_level FROM inventory_stock')->fetch();
        checkInventory($row === ['quantity_on_hand' => '12.75', 'reorder_level' => '5.00'], 'Existing stock/reorder level changed incorrectly.');
    },
    'serialized entry saves one in-stock unit without a receiving report' => function () use ($testDb) {
        $response = requestInventory(['item_id' => '1', 'quantity' => '1', 'serial_number' => ' SN-001 ', 'location' => ' Shelf A ']);
        checkInventory($response['success'] && $response['is_serialized'], 'Serialized entry failed.');
        $unit = $testDb->query('SELECT * FROM inventory_units')->fetch();
        checkInventory($unit['serial_number'] === 'SN-001' && $unit['location'] === 'Shelf A' && $unit['status'] === 'in_stock' && $unit['source_rr_number'] === null && $unit['unit_cost'] === '123.45', 'Serialized details differ.');
    },
    'duplicate serial is rejected across branches' => function () use ($testDb) {
        $testDb->exec("INSERT INTO inventory_units (item_id, branch_id, serial_number) VALUES (1, 2, 'SN-001')");
        $response = requestInventory(['item_id' => '1', 'quantity' => '1', 'serial_number' => 'sn-001']);
        checkInventory(!$response['success'] && str_contains($response['message'], 'already exists'), 'Duplicate serial was accepted.');
        checkInventory((int)$testDb->query('SELECT COUNT(*) FROM inventory_units')->fetchColumn() === 1, 'Duplicate entry wrote stock.');
        checkInventory((int)$testDb->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 0, 'Duplicate entry wrote an audit record.');
    },
    'missing serial is rejected' => fn() => assertInventoryRejected(['item_id' => '1', 'quantity' => '1'], 'serial number is required'),
    'multiple units per serial are rejected' => fn() => assertInventoryRejected(['item_id' => '1', 'quantity' => '2', 'serial_number' => 'SN-002'], 'one unit at a time'),
    'serial metadata on a bulk item is rejected' => fn() => assertInventoryRejected(['serial_number' => 'SN-003'], 'only apply to serialized'),
    'zero quantity is rejected' => fn() => assertInventoryRejected(['quantity' => '0'], 'Quantity must'),
    'negative quantity is rejected' => fn() => assertInventoryRejected(['quantity' => '-1'], 'Quantity must'),
    'excess quantity precision is rejected' => fn() => assertInventoryRejected(['quantity' => '0.001'], 'Quantity must'),
    'oversized quantity is rejected' => fn() => assertInventoryRejected(['quantity' => '1000000000000'], 'Quantity must'),
    'negative unit cost is rejected' => fn() => assertInventoryRejected(['unit_cost' => '-1'], 'Unit cost must'),
    'excess cost precision is rejected' => fn() => assertInventoryRejected(['unit_cost' => '1.001'], 'Unit cost must'),
    'invalid date is rejected' => fn() => assertInventoryRejected(['date_received' => '2026-02-30'], 'valid date'),
    'malformed date is rejected' => fn() => assertInventoryRejected(['date_received' => "2026-09\0-17"], 'valid date'),
    'missing reason is rejected' => fn() => assertInventoryRejected(['remarks' => '  '], 'reason or reference'),
    'oversized reason is rejected' => fn() => assertInventoryRejected(['remarks' => str_repeat('a', 256)], 'reason or reference'),
    'oversized serial is rejected' => fn() => assertInventoryRejected(['serial_number' => str_repeat('a', 101)], '100 characters'),
    'invalid field type is rejected' => fn() => assertInventoryRejected(['quantity' => []], 'Invalid inventory entry'),
    'inactive item is rejected' => fn() => assertInventoryRejected(['item_id' => '3'], 'active item'),
    'missing item is rejected' => fn() => assertInventoryRejected(['item_id' => '999'], 'active item'),
    'cross-branch addition is rejected' => fn() => assertInventoryRejected(['branch_id' => '2'], 'Access denied'),
    'branch user cannot manually add stock' => function () {
        $_SESSION['pbs_role'] = 'branch_user';
        assertInventoryRejected([], 'Only administrators');
    },
    'unassigned branch administrator is rejected' => function () {
        $_SESSION['pbs_branch_id'] = null;
        assertInventoryRejected([], 'assigned to a branch');
    },
    'invalid CSRF token is rejected' => fn() => assertInventoryRejected(['csrf_token' => 'wrong'], 'form has expired'),
    'super administrator can choose another active branch' => function () use ($testDb) {
        $_SESSION['pbs_role'] = 'super_admin';
        checkInventory(requestInventory(['branch_id' => '2'])['success'], 'Super administrator branch selection failed.');
        checkInventory((int)$testDb->query('SELECT branch_id FROM inventory_stock')->fetchColumn() === 2, 'Stock entered in wrong branch.');
    },
    'inactive branch is rejected' => function () {
        $_SESSION['pbs_role'] = 'super_admin';
        assertInventoryRejected(['branch_id' => '3'], 'active branch');
    },
    'entry options contain only active items and assigned branch' => function () {
        $response = requestInventory([], 'GET', ['action' => 'entry_options']);
        checkInventory($response['success'] && count($response['items']) === 2 && array_map('intval', array_column($response['branches'], 'id')) === [1], 'Entry options escaped branch scope or include inactive items.');
    },
    'inventory listing remains branch-scoped after manual entry' => function () use ($testDb) {
        requestInventory();
        $testDb->exec('INSERT INTO inventory_stock (item_id, branch_id, quantity_on_hand) VALUES (2, 2, 99)');
        $response = requestInventory([], 'GET');
        checkInventory($response['success'] && count($response['stock']) === 1 && $response['stock'][0]['quantity_on_hand'] === '2.25', 'Listing differs.');
    },
    'bulk overflow leaves stock unchanged' => function () use ($testDb) {
        $testDb->exec('INSERT INTO inventory_stock (item_id, branch_id, quantity_on_hand) VALUES (2, 1, 999999999999.99)');
        $response = requestInventory(['quantity' => '0.01']);
        checkInventory(!$response['success'] && str_contains($response['message'], 'maximum stock quantity'), 'Stock overflow was accepted.');
        checkInventory($testDb->query('SELECT quantity_on_hand FROM inventory_stock')->fetchColumn() === '999999999999.99', 'Stock changed on overflow.');
    },
    'audit failure rolls back the stock addition' => function () use ($testDb) {
        $testDb->exec('ALTER TABLE audit_logs MODIFY action VARCHAR(1) NOT NULL');
        try { assertInventoryRejected([], 'Failed to add inventory'); }
        finally { $testDb->exec('ALTER TABLE audit_logs MODIFY action VARCHAR(100) NOT NULL'); }
    },
    'unsupported method is rejected' => function () {
        $response = requestInventory([], 'DELETE');
        checkInventory(!$response['success'] && $response['message'] === 'Method not allowed.', 'Unsupported method was accepted.');
    },
];

function batchInventoryPayload(array $rows, array $overrides = []): array {
    return array_replace(['action' => 'manual_add_batch', 'request_id' => str_repeat('b', 32), 'items' => $rows], $overrides);
}
function batchInventoryRow(array $overrides = []): array {
    return array_replace(['item_id' => '1', 'quantity' => '1', 'unit_cost' => '125.25', 'serial_number' => 'BATCH-001', 'location' => 'Shelf A'], $overrides);
}
function bulkInventoryRow(array $overrides = []): array {
    return batchInventoryRow(array_replace(['item_id' => '2', 'quantity' => '2.25', 'serial_number' => '', 'location' => ''], $overrides));
}
$checks += [
    'mixed batch adds serial units and bulk lines with a common audit reference' => function () use ($testDb) {
        $response = requestInventory(batchInventoryPayload([batchInventoryRow(), bulkInventoryRow(), batchInventoryRow(['serial_number' => ' BATCH-002 ']), bulkInventoryRow(['quantity' => '0.75', 'unit_cost' => '5.00'])]));
        checkInventory($response['success'] && $response['row_count'] === 4 && $response['serialized_count'] === 2 && $response['bulk_count'] === 2, 'Batch totals differ.');
        checkInventory($testDb->query('SELECT quantity_on_hand FROM inventory_stock')->fetchColumn() === '3.00', 'Bulk quantities were not accumulated.');
        // Existing installations can still have DATE columns pending the separate
        // datetime migration. Check the stored date and the full audited timestamp.
        checkInventory((int)$testDb->query("SELECT COUNT(*) FROM inventory_units WHERE serial_number IN ('BATCH-001','BATCH-002') AND status='in_stock' AND DATE(date_received)='2026-09-17'")->fetchColumn() === 2, 'Serialized receipt fields differ.');
        $audits = $testDb->query('SELECT details FROM audit_logs ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        checkInventory(count($audits) === 4, 'Expected one audit per row.');
        foreach ($audits as $index => $audit) {
            $details = json_decode($audit, true);
            checkInventory($details['batch_id'] === str_repeat('b', 32) && $details['batch_row'] === $index + 1 && $details['batch_rows'] === 4 && $details['date_received'] === '2026-09-17 09:30:45', 'Batch audit references differ.');
        }
    },
    'duplicate serial within batch rolls back every prior row and audit' => fn() => assertInventoryRejected(batchInventoryPayload([bulkInventoryRow(), batchInventoryRow(), batchInventoryRow(['serial_number' => 'batch-001'])]), 'Row 3: This serial number already exists'),
    'duplicate in another branch rolls back bulk stock in the batch' => function () use ($testDb) {
        $testDb->exec("INSERT INTO inventory_units (item_id, branch_id, serial_number) VALUES (1,2,'BATCH-001')");
        $response = requestInventory(batchInventoryPayload([bulkInventoryRow(), batchInventoryRow()]));
        checkInventory(!$response['success'] && str_contains($response['message'], 'Row 2:'), 'Expected row-specific duplicate error.');
        checkInventory((int)$testDb->query('SELECT COUNT(*) FROM inventory_stock')->fetchColumn() === 0 && (int)$testDb->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 0, 'Partial batch persisted.');
        checkInventory((int)$testDb->query('SELECT COUNT(*) FROM inventory_units')->fetchColumn() === 1, 'Existing serial changed.');
    },
    'inactive item later in batch rolls back earlier units' => fn() => assertInventoryRejected(batchInventoryPayload([batchInventoryRow(), bulkInventoryRow(['item_id' => '3'])]), 'Row 2: Select an active item'),
    'malformed later quantity writes nothing' => fn() => assertInventoryRejected(batchInventoryPayload([batchInventoryRow(), bulkInventoryRow(['quantity' => '2.001'])]), 'Row 2: Quantity must'),
    'serialized batch row cannot represent multiple units' => fn() => assertInventoryRejected(batchInventoryPayload([batchInventoryRow(['quantity' => '2'])]), 'one unit at a time'),
    'serial is mandatory on each serialized batch row' => fn() => assertInventoryRejected(batchInventoryPayload([batchInventoryRow(['serial_number' => ''])]), 'serial number is required'),
    'bulk rows reject serial metadata' => fn() => assertInventoryRejected(batchInventoryPayload([bulkInventoryRow(['serial_number' => 'NOT-BULK'])]), 'only apply to serialized'),
    'batch cost precision is validated' => fn() => assertInventoryRejected(batchInventoryPayload([batchInventoryRow(['unit_cost' => '1.234'])]), 'Row 1: Unit cost must'),
    'empty batch is rejected' => fn() => assertInventoryRejected(batchInventoryPayload([]), 'between 1 and 500'),
    'oversized batch is rejected' => fn() => assertInventoryRejected(batchInventoryPayload(array_fill(0, 501, bulkInventoryRow())), 'between 1 and 500'),
    'batch row must be an object' => fn() => assertInventoryRejected(batchInventoryPayload(['bad row']), 'Row 1: Invalid inventory entry'),
    'batch rows must be a list' => fn() => assertInventoryRejected(batchInventoryPayload(['first' => batchInventoryRow()]), 'between 1 and 500'),
    'batch cannot override branch scope' => fn() => assertInventoryRejected(batchInventoryPayload([batchInventoryRow()], ['branch_id' => '2']), 'Access denied'),
    'batch CSRF is enforced' => fn() => assertInventoryRejected(batchInventoryPayload([batchInventoryRow()], ['csrf_token' => 'bad']), 'form has expired'),
    'batch requires valid receipt token' => fn() => assertInventoryRejected(batchInventoryPayload([batchInventoryRow()], ['request_id' => []]), 'Invalid batch reference'),
    'branch users cannot post a batch' => function () {
        $_SESSION['pbs_role'] = 'branch_user';
        assertInventoryRejected(batchInventoryPayload([batchInventoryRow()]), 'Only administrators');
    },
    'retrying the same batch does not increment stock or audit twice' => function () use ($testDb) {
        $payload = batchInventoryPayload([bulkInventoryRow(), batchInventoryRow()]);
        $first = requestInventory($payload);
        $second = requestInventory($payload);
        checkInventory($first === $second && $first['success'], 'Retry must return the original successful result.');
        checkInventory($testDb->query('SELECT quantity_on_hand FROM inventory_stock')->fetchColumn() === '2.25', 'Retry duplicated bulk stock.');
        checkInventory((int)$testDb->query('SELECT COUNT(*) FROM inventory_units')->fetchColumn() === 1 && (int)$testDb->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 2, 'Retry duplicated units or audit.');
    },
    'changed payload cannot reuse an already saved batch token' => function () use ($testDb) {
        requestInventory(batchInventoryPayload([bulkInventoryRow()]));
        $response = requestInventory(batchInventoryPayload([bulkInventoryRow(['quantity' => '8'])]));
        checkInventory(!$response['success'] && str_contains($response['message'], 'already saved'), 'Changed retry accepted.');
        checkInventory($testDb->query('SELECT quantity_on_hand FROM inventory_stock')->fetchColumn() === '2.25', 'Changed retry wrote stock.');
    },
    'failed batch can be corrected and retried with the same token' => function () {
        $first = requestInventory(batchInventoryPayload([batchInventoryRow(), batchInventoryRow()]));
        $second = requestInventory(batchInventoryPayload([batchInventoryRow(), batchInventoryRow(['serial_number' => 'BATCH-002'])]));
        checkInventory(!$first['success'] && $second['success'] && $second['row_count'] === 2, 'Failed receipt blocked correction.');
    },
    'cumulative bulk overflow rolls back the entire batch' => function () use ($testDb) {
        $testDb->exec('INSERT INTO inventory_stock (item_id, branch_id, quantity_on_hand) VALUES (2,1,999999999999.98)');
        $response = requestInventory(batchInventoryPayload([batchInventoryRow(), bulkInventoryRow(['quantity' => '0.01']), bulkInventoryRow(['quantity' => '0.01'])]));
        checkInventory(!$response['success'] && str_contains($response['message'], 'Row 3:'), 'Cumulative overflow accepted.');
        checkInventory($testDb->query('SELECT quantity_on_hand FROM inventory_stock')->fetchColumn() === '999999999999.98', 'Overflow changed existing stock.');
        checkInventory((int)$testDb->query('SELECT COUNT(*) FROM inventory_units')->fetchColumn() === 0 && (int)$testDb->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 0, 'Overflow left partial receipt.');
    },
    'maximum batch size is accepted' => function () use ($testDb) {
        $response = requestInventory(batchInventoryPayload(array_map(fn($i) => batchInventoryRow(['serial_number' => 'LIMIT-' . $i]), range(1,500))));
        checkInventory($response['success'] && $response['serialized_count'] === 500, '500-row batch failed.');
        checkInventory((int)$testDb->query('SELECT COUNT(*) FROM inventory_units')->fetchColumn() === 500 && (int)$testDb->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 500, '500-row receipt incomplete.');
    },
];

$failed = 0;
foreach ($checks as $label => $check) {
    resetInventoryFixtures($testDb);
    try {
        $check();
        echo "PASS: $label\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL: $label: {$e->getMessage()}\n";
    }
    if ($testDb->inTransaction()) $testDb->rollBack();
}
session_destroy();
echo count($checks) - $failed, '/', count($checks), " checks passed. Temporary fixtures disappear when this connection closes.\n";
ob_end_flush();
exit($failed ? 1 : 0);
