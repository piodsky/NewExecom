<?php
// php tools/migrate_datetime.php
// Widen date-only fields without changing dates or existing DATETIME values.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

function migrateDateTimeColumns(PDO $db, string $backupDirectory): array {
    $columns = [
        'pr_requests' => ['needed_date'],
        'internal_rfq' => ['sent_date'],
        'outgoing_rfq' => ['received_date', 'due_date'],
        'receiving_reports' => ['date_received'],
        'delivery_reports' => ['date_delivered', 'date_accepted'],
        'inventory_units' => ['date_received', 'date_out'],
    ];
    $pending = [];
    $backup = ['created_at' => date('c'), 'tables' => []];
    foreach ($columns as $table => $fields) {
        $schema = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        $changes = [];
        foreach ($fields as $field) {
            $column = $schema[$field] ?? null;
            if (!$column) throw new RuntimeException("Missing column: $table.$field");
            if (preg_match('/^datetime(?:\(\d+\))?$/i', $column['Type'])) continue;
            if (strtolower($column['Type']) !== 'date' || $column['Null'] !== 'YES' || $column['Default'] !== null) {
                throw new RuntimeException("Unexpected definition for $table.$field; no changes applied.");
            }
            $changes[] = $field;
        }
        if (!$changes) continue;
        $select = implode(', ', array_map(fn($field) => "`$field`", $changes));
        $backup['tables'][$table] = [
            'columns' => $changes,
            'rows' => $db->query("SELECT id, $select FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),
        ];
        $pending[$table] = $changes;
    }
    if (!$pending) return ['converted' => 0, 'backup' => null];
    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
        throw new RuntimeException('Could not create the date backup directory.');
    }
    $path = $backupDirectory . '/dates-before-datetime-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) {
        throw new RuntimeException('Could not save the date backup. No schema changes applied.');
    }
    $converted = 0;
    // MySQL DDL commits automatically. The saved backup and per-column checks
    // make this safe to inspect and rerun if a later table cannot be altered.
    foreach ($pending as $table => $fields) {
        $changes = implode(', ', array_map(fn($field) => "MODIFY COLUMN `$field` DATETIME NULL DEFAULT NULL", $fields));
        $db->exec("ALTER TABLE `$table` $changes");
        $converted += count($fields);
    }
    return ['converted' => $converted, 'backup' => $path];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    session_save_path(sys_get_temp_dir());
    require_once __DIR__ . '/../system/config.php';
    try {
        $result = migrateDateTimeColumns(getDB(), __DIR__ . '/../system/database/backups');
        echo "Converted {$result['converted']} date columns to DATETIME.\n";
        if ($result['backup']) echo "Date backup: {$result['backup']}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'Date/time migration failed: ' . $e->getMessage() . "\n");
        session_destroy();
        exit(1);
    }
    session_destroy();
}
