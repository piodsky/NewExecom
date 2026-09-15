<?php
// Collapses the accumulated spellings of the same supplier on existing
// purchase orders.
//
// tbl_purchase_order.Supplier_Name is free text with no foreign key, so the
// same company is on file under many spellings: 1,377 distinct values across
// the live purchase orders against 808 rows in ref_supplier. "7 DIAMONDS
// EDUCATIONAL & CONSUMER GOODS TRADING" alone has twelve. That makes any
// per-supplier total wrong, quietly, in a way nobody notices.
//
// canonicalizeAgainstRef() in config.php already stops NEW saves adding to the
// pile; this is the other half — the rows that were saved before it existed.
//
// Grouping uses the same key that canonicalisation uses (refCanonicalKey:
// case, punctuation and spacing removed), so this only ever merges spellings
// that a future save would have merged anyway. It deliberately does NOT do
// fuzzy matching: "…& CONSUMER GOODS…" and "…AND CONSUMER GOODS…" are left as
// separate suppliers, because deciding those two are the same company is a
// judgement call, not a formatting difference, and getting it wrong silently
// reassigns somebody's purchase history.
require_once '../config.php';
requireModule('purchase_orders');
requireSystemToolsAdmin();
header('Content-Type: application/json');
$db = getPoDB();

// Where the undo files land. The merge rewrites live rows and there is no
// column to keep the previous value in, so every run writes down exactly what
// it changed before it changes it.
const UNDO_DIR = __DIR__ . '/../uploads/supplier_cleanup';

// Reads ref_supplier once, keyed the same way, so a group can prefer the
// official spelling over whichever variant happens to be most common.
function refSupplierByKey(PDO $db): array {
    $byKey = [];
    foreach ($db->query('SELECT Supplier_Name FROM ref_supplier')->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $key = refCanonicalKey($name);
        if ($key !== '' && !isset($byKey[$key])) {
            $byKey[$key] = trim(preg_replace('/\s+/u', ' ', (string)$name));
        }
    }
    return $byKey;
}

// Every canonical key that currently has more than one spelling on live POs,
// with the suggested target for each.
//
// Suggested target = the ref_supplier spelling when the supplier is on file
// there (25 of 146 groups), otherwise the variant used on the most purchase
// orders. Never a variant that appears once.
function supplierGroups(PDO $db): array {
    $refByKey = refSupplierByKey($db);
    $rows = $db->query(
        'SELECT Supplier_Name AS name, COUNT(*) AS pos
           FROM tbl_purchase_order
          WHERE isDeleted = 0 AND Supplier_Name <> \'\'
          GROUP BY Supplier_Name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $byKey = [];
    foreach ($rows as $row) {
        $key = refCanonicalKey($row['name']);
        if ($key === '') continue;
        $byKey[$key][] = ['name' => $row['name'], 'pos' => (int)$row['pos']];
    }

    $groups = [];
    foreach ($byKey as $key => $variants) {
        if (count($variants) < 2) continue;
        usort($variants, fn($a, $b) => $b['pos'] <=> $a['pos'] ?: strcmp($a['name'], $b['name']));
        $target = $refByKey[$key] ?? $variants[0]['name'];
        $changes = 0;
        foreach ($variants as $v) if ($v['name'] !== $target) $changes += $v['pos'];
        $groups[] = [
            'key'         => $key,
            'target'      => $target,
            'target_from' => isset($refByKey[$key]) ? 'ref' : 'usage',
            'variants'    => $variants,
            'changes'     => $changes,
        ];
    }
    // Biggest cleanups first — that is the order someone wants to work in.
    usort($groups, fn($a, $b) => $b['changes'] <=> $a['changes'] ?: strcmp($a['target'], $b['target']));
    return fixLegacyEncodingDeep($groups);
}

// A merge may only ever collapse a group onto one of that group's own
// spellings — never an arbitrary string from the request, which would be a way
// to rewrite Supplier_Name on live purchase orders to anything at all.
function assertTargetInGroup(array $group, string $target): void {
    if (!in_array($target, array_column($group['variants'], 'name'), true)) {
        jsonResponse(false, 'That spelling is not one of this supplier\'s variants.');
    }
}

// Rewrites one group. Returns the per-PO record of what changed, which the
// caller writes to the undo file BEFORE committing.
function mergeGroup(PDO $db, array $group, string $target): array {
    $names = array_column($group['variants'], 'name');
    $changed = [];
    foreach ($names as $name) {
        if ($name === $target) continue;
        $find = $db->prepare('SELECT PO_Number FROM tbl_purchase_order WHERE isDeleted = 0 AND Supplier_Name = ?');
        $find->execute([$name]);
        foreach ($find->fetchAll(PDO::FETCH_COLUMN) as $po) {
            $changed[] = ['po' => $po, 'from' => $name, 'to' => $target];
        }
        $db->prepare('UPDATE tbl_purchase_order SET Supplier_Name = ? WHERE isDeleted = 0 AND Supplier_Name = ?')
           ->execute([$target, $name]);
    }
    return $changed;
}

// The undo records name real purchase orders and suppliers, so the folder is
// closed off the same way uploads/avatars is — and the guard is written by the
// code that creates the folder, so a fresh deployment is covered without
// anyone remembering to copy a file across.
function ensureUndoDir(): bool {
    if (!is_dir(UNDO_DIR) && !@mkdir(UNDO_DIR, 0775, true)) return false;
    $guard = UNDO_DIR . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard,
            "# Merge undo records. These name real purchase orders and suppliers —\n"
          . "# nothing here is meant to be reachable over HTTP.\n"
          . "Require all denied\nOptions -Indexes\nphp_flag engine off\n");
    }
    return true;
}

function writeUndoFile(array $changed): ?string {
    if (!$changed) return null;
    if (!ensureUndoDir()) return null;
    $name = 'supplier-merge-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.json';
    $ok = @file_put_contents(UNDO_DIR . '/' . $name, json_encode([
        'at'      => date('c'),
        'by'      => getCurrentUser()['username'] ?? 'unknown',
        'changes' => $changed,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    return $ok === false ? null : $name;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $groups = supplierGroups($db);
    jsonResponse(true, '', [
        'groups'  => $groups,
        'summary' => [
            'groups'   => count($groups),
            'changes'  => array_sum(array_column($groups, 'changes')),
            // Not count(variants) - 1: when the target comes from ref_supplier it
            // is sometimes not one of the spellings in use, so every variant goes.
            'variants' => array_sum(array_map(
                fn($g) => count(array_filter($g['variants'], fn($v) => $v['name'] !== $g['target'])),
                $groups)),
        ],
    ]);
}

if ($method === 'POST') {
    $data = getInput();
    // The group list is always rebuilt from the database rather than taken
    // from the request: the client cannot be allowed to name arbitrary
    // Supplier_Name values to overwrite, and the counts may have moved since
    // the page loaded.
    $groups = supplierGroups($db);
    $applyAll = !empty($data['all']);

    $todo = [];
    if ($applyAll) {
        // 'targets' carries any spelling the user picked by hand in the list, so
        // "merge all" honours those instead of quietly reverting them to the
        // suggestion. Anything not named here keeps its suggested target, and
        // mergeGroup() still checks every value against that group's variants.
        $picked = is_array($data['targets'] ?? null) ? $data['targets'] : [];
        foreach ($groups as $g) $todo[] = [$g, (string)($picked[$g['key']] ?? $g['target'])];
    } else {
        $key = (string)($data['key'] ?? '');
        $found = null;
        foreach ($groups as $g) if ($g['key'] === $key) { $found = $g; break; }
        if (!$found) jsonResponse(false, 'That supplier group is no longer there — reload the page.');
        $todo[] = [$found, trim((string)($data['target'] ?? '')) ?: $found['target']];
    }

    // Checked up front, before anything is written: jsonResponse() exits, and
    // exiting halfway through a multi-group merge would rely on the connection
    // teardown to undo the groups already done.
    foreach ($todo as [$group, $target]) assertTargetInGroup($group, $target);

    $changed = [];
    $undo = null;
    // beginTransaction() is INSIDE the try, not before it. Outside, anything it
    // threw was an uncaught fatal — a 500 with an HTML body, which the browser
    // then could not parse as JSON, so the screen showed only the generic
    // "The merge did not go through." and the actual reason went nowhere.
    try {
        $db->beginTransaction();
        foreach ($todo as [$group, $target]) {
            $changed = array_merge($changed, mergeGroup($db, $group, $target));
        }
        // Written inside the transaction on purpose: if the undo record cannot
        // be saved, the rewrite does not happen either.
        $undo = writeUndoFile($changed);
        if ($changed && $undo === null) {
            throw new RuntimeException('Could not write the undo file.');
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        jsonResponse(false, 'Nothing was changed: ' . $e->getMessage());
    }

    // Deliberately after the commit and deliberately not allowed to matter: the
    // rows are already rewritten by this point, so a failure to write the audit
    // line must not be reported as a failed merge. The undo file, not this, is
    // the record that makes a merge reversible.
    try {
        logAudit('MERGE_SUPPLIER_NAMES', 'master_data',
            count($changed) . ' purchase order(s) across ' . count($todo) . ' supplier group(s); undo file ' . ($undo ?? 'none'));
    } catch (Throwable $e) {
        error_log('supplier_cleanup: merge committed but audit log failed: ' . $e->getMessage());
    }

    jsonResponse(true, $changed
        ? count($changed) . ' purchase order' . (count($changed) === 1 ? '' : 's') . ' updated.'
        : 'Nothing needed changing.', ['changed' => count($changed), 'undo' => $undo]);
}

jsonResponse(false, 'Method not allowed.');
