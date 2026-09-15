<?php
/* Removes PO-sample2027 and everything recorded against it.
 *
 * The sample purchase order created for trying the Inspection & Acceptance
 * progress-billing flow. Run this when you are finished with it:
 *
 *     php tools/remove_sample2027.php              (dry run — shows what it would do)
 *     php tools/remove_sample2027.php --commit     (does it)
 *
 * SOFT delete throughout — isDeleted = 1, never DELETE FROM — because that is
 * what every other retire path in this app does, and because a row that turns
 * out to have mattered can be brought back with one UPDATE. Nothing outside
 * this purchase order is touched: no reference data was created for it, so
 * none has to be cleaned up.
 */
require_once __DIR__ . '/../system/config.php';

const SAMPLE_PO = 'PO-sample2027';

$db     = getPoDB();
$commit = in_array('--commit', $argv, true);

/* Every table the sample can have reached, in the order they would be undone.
 * Progress billing writes to two of them and flips Inspect on the purchase
 * order; an ordinary inspection writes to the other two. All five are checked
 * whether or not they were used, so this stays correct however far the sample
 * was taken. */
$targets = [
    ['tbl_inspection_items_details_progress_billing', 'PO_Number'],
    ['tbl_inspection_progress_billing',               'PO_Number'],
    ['tbl_inspection_items_details',                  'PO_Number'],
    ['tbl_inspection',                                'PO_Number'],
    ['tbl_purchase_order_items_details',              'PO_Number'],
    ['tbl_purchase_order',                            'PO_Number'],
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
