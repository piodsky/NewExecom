<?php
/* Removes PO-sample2028 and everything recorded against it.
 *
 * Created 2026-09-08 to verify Add Purchase Order still works after the
 * .253 data-recovery merge — see memory project_po_db_recovery_253.md. Run
 * this when you are finished with it:
 *
 *     php tools/remove_sample2028.php              (dry run — shows what it would do)
 *     php tools/remove_sample2028.php --commit     (does it)
 *
 * SOFT delete throughout — isDeleted = 1, never DELETE FROM — same reasoning
 * as remove_sample2027.php: every other retire path in this app works this
 * way, and a row that turns out to have mattered can be brought back with
 * one UPDATE. Only tbl_purchase_order and its items were ever written to for
 * this sample — no inspection, inventory or obligation flow was exercised —
 * so nothing else needs checking.
 */
require_once __DIR__ . '/../system/config.php';

const SAMPLE_PO = 'PO-sample2028';

$db     = getPoDB();
$commit = in_array('--commit', $argv, true);

$targets = [
    ['tbl_purchase_order_items_details', 'PO_Number'],
    ['tbl_purchase_order',               'PO_Number'],
];

echo ($commit ? 'COMMITTING' : 'DRY RUN — nothing will be changed') . "\n\n";

$found = 0;
foreach ($targets as [$table, $col]) {
    $q = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ? AND isDeleted = 0");
    $q->execute([SAMPLE_PO]);
    $n = (int)$q->fetchColumn();
    $found += $n;
    printf("  %-46s %d live row%s\n", $table, $n, $n === 1 ? '' : 's');
}
if ($found === 0) {
    echo "\nNothing to remove — " . SAMPLE_PO . " is already gone.\n";
    exit(0);
}
if (!$commit) { echo "\nRe-run with --commit to remove it.\n"; exit(0); }

try {
    $db->beginTransaction();
    foreach ($targets as [$table, $col]) {
        $db->prepare("UPDATE `$table` SET isDeleted = 1 WHERE `$col` = ? AND isDeleted = 0")
           ->execute([SAMPLE_PO]);
    }
    $db->commit();
    echo "\nRemoved. " . SAMPLE_PO . " and its {$found} rows are soft-deleted.\n";
    echo "To bring it back: the same UPDATEs with isDeleted = 0 WHERE isDeleted = 1.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'FAILED, rolled back: ' . $e->getMessage() . "\n");
    exit(1);
}
