<?php
// Competitive Bidding API — the BAC contract document set.
//
// One record drives three printed documents (Notice of Award, Contract
// Agreement, Notice to Proceed), so everything here is keyed on Bid_Number,
// the number BAC files the whole set under.
//
// Deliberately narrower than api/purchase_orders.php: there is no fund-type
// numbering, no lock/unlock, no per-field quick update and no barcode/QR. A
// bid document set is typed once and printed, not tracked through a lifecycle.
require_once '../config.php';
requireAuth();
// The sidebar link hides the page; this is what actually blocks a direct hit
// on the API. Both are required — see the checklist in CLAUDE.md.
requireModule('competitive_bidding');
header('Content-Type: application/json');
$db = getPoDB();
$method = $_SERVER['REQUEST_METHOD'];

const CB_TABLE       = 'tbl_competitive_bidding';
const CB_ITEMS_TABLE = 'tbl_competitive_bidding_items_details';

// Scalar columns of a bid, as request key => DB column. Central so the INSERT,
// the UPDATE and the SELECT cannot drift apart — adding a field to the form
// means adding one line here and one to the SQL migration, nothing else.
const CB_FIELDS = [
    'bid_number'            => 'Bid_Number',
    'contract_number'       => 'Contract_Number',
    'contract_name'         => 'Contract_Name',
    'contract_location'     => 'Contract_Location',
    'date_pr'               => 'Date_PR',
    'pr_amount'             => 'PR_Amount',
    'supplier_name'         => 'Supplier_Name',
    'supplier_address'      => 'Supplier_Address',
    'supplier_address2'     => 'Supplier_Address2',
    'supplier_org_place'    => 'Supplier_Org_Place',
    'representative_name'   => 'Representative_Name',
    'representative_title'  => 'Representative_Title',
    'contract_amount'       => 'Contract_Amount',
    'cash_bond_amount'      => 'Cash_Bond_Amount',
    'surety_bond_amount'    => 'Surety_Bond_Amount',
    'place_of_delivery'     => 'Place_of_Delivery',
    'delivery_term'         => 'Delivery_Term',
    'bid_date'              => 'Bid_Date',
    'noa_date'              => 'NOA_Date',
    'signatory_name'        => 'Signatory_Name',
    'signatory_title'       => 'Signatory_Title',
    'witness_entity_name'   => 'Witness_Entity_Name',
    'witness_entity_title'  => 'Witness_Entity_Title',
    'witness_supplier_name' => 'Witness_Supplier_Name',
    'notary_name'           => 'Notary_Name',
    'notary_place'          => 'Notary_Place',
    'notary_doc_no'         => 'Notary_Doc_No',
    'notary_page_no'        => 'Notary_Page_No',
    'notary_book_no'        => 'Notary_Book_No',
    'notary_series'         => 'Notary_Series',
];

// Columns holding a DATE. Empty string must become NULL, not '0000-00-00':
// a blank contract date prints as the ruled line the parties fill in by hand,
// and a zero date would print as a real (wrong) date instead.
const CB_DATE_FIELDS = ['date_pr', 'bid_date', 'noa_date'];

// Operational tracking fields, each saved on its own through the quick-field
// PATCH below rather than as part of the main form.
//
// Separate for two reasons. They are filled in over weeks as the paperwork
// moves, long after the header was written, so bundling them into the form
// would mean a save of unrelated header edits could quietly reset one. And
// they are still writable once the set is locked — a locked document is
// closed to changes of substance, but recording that it reached CGSO is not
// a change of substance, it is the tracking the lock exists to protect.
const CB_QUICK_FIELDS = [
    'date_received_noa'     => ['col' => 'Date_Received_NOA',     'type' => 'date'],
    'date_performance_bond' => ['col' => 'Date_Performance_Bond', 'type' => 'date'],
    'contract_date'         => ['col' => 'Contract_Date',         'type' => 'date'],
    'ntp_date'              => ['col' => 'NTP_Date',              'type' => 'date'],
    'remarks'               => ['col' => 'Remarks',               'type' => 'text'],
    'barcode_number'        => ['col' => 'Barcode_Number',        'type' => 'text'],
];

// Columns holding money. Same reasoning — an unset bond amount is NULL, so the
// printout leaves the box blank rather than asserting ₱0.00 is owed.
const CB_MONEY_FIELDS = ['pr_amount', 'contract_amount', 'cash_bond_amount', 'surety_bond_amount'];

function cbDate($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

function cbMoney($value) {
    if ($value === null) return null;
    $value = trim((string)$value);
    if ($value === '') return null;
    // Strip thousands separators and any currency mark before casting, so a
    // pasted "10,650,000.00" does not silently become 10.
    $clean = preg_replace('/[^0-9.\-]/', '', $value);
    return $clean === '' ? null : (float)$clean;
}

// One bid's scalar values, in CB_FIELDS order, ready to bind.
function cbFieldValues(array $data): array {
    $values = [];
    foreach (CB_FIELDS as $key => $_column) {
        if (in_array($key, CB_DATE_FIELDS, true))       $values[] = cbDate($data[$key] ?? '');
        elseif (in_array($key, CB_MONEY_FIELDS, true))  $values[] = cbMoney($data[$key] ?? '');
        else                                            $values[] = trim((string)($data[$key] ?? ''));
    }
    return $values;
}

// Rejects item rows that are neither a description-only row nor a priced row.
//
// Identical rule to the purchase order grid, and for the same reason: Total
// Cost is the test, NOT Unit + Qty + Unit Cost. A lump-sum line legitimately
// carries a total with no quantity behind it, and on this form the pattern is
// the norm — the sample contract has one priced row ("WHEEL LOADER") and
// thirty-odd description-only specification lines under it.
//
// Enforced here as well as in the browser because the browser is not a place
// to enforce anything.
function cbInvalidItemRows(array $items): array {
    $bad = ['item' => [], 'title' => []];
    foreach ($items as $i => $item) {
        if (trim((string)($item['items_description'] ?? '')) === '') continue;
        $num  = fn($k) => (float)($item[$k] ?? 0);
        $unit = trim((string)($item['unit'] ?? ''));
        $blank = $unit === ''
              && $num('quantity') == 0 && $num('unit_cost') == 0 && $num('total_cost') == 0;

        $isTitle = array_key_exists('is_title', $item) ? (bool)$item['is_title'] : $blank;
        if ($isTitle) {
            if (!$blank) $bad['title'][] = $i + 1;
        } elseif ($num('total_cost') == 0) {
            $bad['item'][] = $i + 1;
        }
    }
    return $bad;
}

function cbRejectInvalidItems(array $items): void {
    $bad = cbInvalidItemRows($items);
    if (!$bad['item'] && !$bad['title']) return;
    // Same shape as rejectInvalidItems() in api/purchase_orders.php: "#1, #4"
    // rather than bare numbers, capped at six so pasting a bad spreadsheet
    // gives a readable sentence instead of a wall of three hundred row numbers.
    $list = function (array $rows) {
        $shown = array_slice($rows, 0, 6);
        return (count($rows) === 1 ? 'Row ' : 'Rows ') . '#' . implode(', #', $shown)
             . (count($rows) > 6 ? ' and ' . (count($rows) - 6) . ' more' : '');
    };
    $parts = [];
    if ($bad['item']) {
        $one = count($bad['item']) === 1;
        $parts[] = $list($bad['item']) . ($one ? ' has' : ' have') . ' no Amount. Fill in QTY and Unit Cost, '
                 . 'enter an Amount directly, or leave the row as a description-only line.';
    }
    if ($bad['title']) {
        $one = count($bad['title']) === 1;
        $parts[] = $list($bad['title'])
                 . ($one ? ' is a description-only line but still carries' : ' are description-only lines but still carry')
                 . ' a unit or a cost.';
    }
    jsonResponse(false, implode(' ', $parts));
}

// Replaces a bid's item rows wholesale. The grid is edited as a whole — rows
// are added, reordered and removed together — so reconciling row-by-row would
// buy nothing but a chance to get it wrong.
function cbSaveItems(PDO $db, string $bidNumber, array $items): void {
    $db->prepare('UPDATE ' . CB_ITEMS_TABLE . ' SET isDeleted = 1 WHERE Bid_Number = ?')->execute([$bidNumber]);
    $stmt = $db->prepare(
        'INSERT INTO ' . CB_ITEMS_TABLE . '
            (Bid_Number, Items_Description, Unit, Quantity, Unit_Cost, Total_Cost, Sort_Order, Date_Created, isDeleted)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)'
    );
    $sort = 0;
    foreach ($items as $item) {
        $description = trim((string)($item['items_description'] ?? ''));
        if ($description === '') continue;
        $stmt->execute([
            $bidNumber,
            $description,
            trim((string)($item['unit'] ?? '')),
            cbMoney($item['quantity']   ?? ''),
            cbMoney($item['unit_cost']  ?? ''),
            cbMoney($item['total_cost'] ?? '') ?? 0,
            $sort++,
        ]);
    }
}

function cbSelectColumns(): string {
    $parts = ['ID AS id'];
    foreach (CB_FIELDS as $key => $column) $parts[] = "{$column} AS {$key}";
    // Not in CB_FIELDS: neither is set by the main form. Print_Bid is moved
    // only by lock/unlock and Barcode_Number only by its own PATCH, so
    // including them in the field list would let an ordinary save reset one.
    $parts[] = 'Print_Bid AS print_bid';
    foreach (CB_QUICK_FIELDS as $key => $meta) $parts[] = "{$meta['col']} AS {$key}";
    $parts[] = 'Date_Created AS date_created';
    $parts[] = 'Created_By AS created_by';
    return implode(', ', $parts);
}

// Where a bid document has got to, derived from the tracking dates on
// Additional Details. Nothing is stored: there is no status column on this
// table, and adding one would create a second source of truth that could
// disagree with the dates the screen already shows.
//
// The ladder names the stage the document is IN, which is why the label after
// the supplier receives the award is "Submission of Performance Bond" — the
// award is out, and what everyone is now waiting on is the bond.
//
//     (no NOA date)          Draft
//     NOA_Date               Awarded
//     Date_Received_NOA      Submission of Performance Bond
//     Date_Performance_Bond  For Contract Signing
//     Contract_Date          Signed
//     NTP_Date               Notice to Proceed
//     all five               Forwarded to CGSO
//
// Checked newest-first, because the dates are filled in over weeks and not
// always in order — a document with a contract date but no bond date has still
// got as far as signing.
const CB_STAGES = [
    'draft'    => 'Draft',
    'awarded'  => 'Awarded',
    'bond'     => 'Submission of Performance Bond',
    'signing'  => 'For Contract Signing',
    'signed'   => 'Signed',
    'ntp'      => 'Notice to Proceed',
    'cgso'     => 'Forwarded to CGSO',
];

// The five dates that make a set complete, in the order they are worked.
const CB_TRACK_DATES = ['noa_date', 'date_received_noa', 'date_performance_bond',
                        'contract_date', 'ntp_date'];

function cbStageKey(array $r): string {
    $has = function ($k) use ($r) {
        $v = trim((string)($r[$k] ?? ''));
        return $v !== '' && strpos($v, '0000') !== 0;
    };

    // Complete means every tracking date is filled in. At that point the set
    // leaves this office, and "Forwarded to CGSO" is what the file is marked
    // as — the same wording that is already in the ref_remarks picklist.
    $complete = true;
    foreach (CB_TRACK_DATES as $d) { if (!$has($d)) { $complete = false; break; } }

    // Honoured even before every date is in, because a remark saying the set
    // has gone to CGSO is a person stating where the paperwork physically is,
    // and that beats an inference drawn from a blank date box.
    $saidSo = stripos((string)($r['remarks'] ?? ''), 'forwarded to cgso') !== false;

    if ($complete || $saidSo)          return 'cgso';
    if ($has('ntp_date'))              return 'ntp';
    if ($has('contract_date'))         return 'signed';
    if ($has('date_performance_bond')) return 'signing';
    if ($has('date_received_noa'))     return 'bond';
    if ($has('noa_date'))              return 'awarded';
    return 'draft';
}

function cbGetList(PDO $db): array {
    $search = trim($_GET['search'] ?? '');
    $from   = $_GET['from'] ?? '';
    $to     = $_GET['to'] ?? '';
    $params = [];
    $sql = 'SELECT ' . cbSelectColumns() . ' FROM ' . CB_TABLE . ' WHERE isDeleted = 0';

    if ($from) { $sql .= ' AND Date_Created >= ?'; $params[] = date('Y-m-d 00:00:00', strtotime($from)); }
    if ($to)   { $sql .= ' AND Date_Created <= ?'; $params[] = date('Y-m-d 23:59:59', strtotime($to)); }
    if ($search !== '') {
        $sql .= ' AND (Bid_Number LIKE ? OR Contract_Number LIKE ? OR Contract_Name LIKE ?
                       OR Contract_Location LIKE ? OR Supplier_Name LIKE ?)';
        $params = array_merge($params, array_fill(0, 5, '%' . $search . '%'));
    }
    $sql .= ' ORDER BY Date_Created DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_ASSOC));

    // Counted in PHP off the rows already fetched, not as extra COUNT(*)
    // queries, so the chips and the table can never disagree.
    foreach ($rows as &$row) {
        $row['locked'] = ((int)($row['print_bid'] ?? 0)) === 1;
    }
    unset($row);

    // The stage travels with the row, so the badge, the chips and the filter
    // are all reading one answer rather than three implementations of it.
    foreach ($rows as &$row) {
        $row['stage']       = cbStageKey($row);
        $row['stage_label'] = CB_STAGES[$row['stage']];
    }
    unset($row);

    $counts = ['total' => count($rows)];
    foreach (CB_STAGES as $key => $_label) $counts[$key] = 0;
    foreach ($rows as $r) $counts[$r['stage']]++;

    $status = strtolower(trim($_GET['status'] ?? ''));
    if (isset(CB_STAGES[$status])) {
        $rows = array_values(array_filter($rows, fn($r) => $r['stage'] === $status));
    }
    return ['bids' => $rows, 'counts' => $counts];
}

function cbGetDetail(PDO $db, string $bidNumber) {
    $stmt = $db->prepare('SELECT ' . cbSelectColumns() . ' FROM ' . CB_TABLE . ' WHERE Bid_Number = ? AND isDeleted = 0');
    $stmt->execute([$bidNumber]);
    $bid = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bid) return null;
    $stmt = $db->prepare(
        'SELECT ID AS id, Items_Description AS items_description, Unit AS unit,
                Quantity AS quantity, Unit_Cost AS unit_cost, Total_Cost AS total_cost
         FROM ' . CB_ITEMS_TABLE . '
         WHERE Bid_Number = ? AND isDeleted = 0
         ORDER BY Sort_Order, ID'
    );
    $stmt->execute([$bidNumber]);
    $bid['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $bid['locked'] = ((int)($bid['print_bid'] ?? 0)) === 1;
    $bid['stage'] = cbStageKey($bid);
    $bid['stage_label'] = CB_STAGES[$bid['stage']];
    return fixLegacyEncodingDeep($bid);
}

function cbExists(PDO $db, string $bidNumber, $exceptId = null): bool {
    $sql = 'SELECT ID FROM ' . CB_TABLE . ' WHERE Bid_Number = ? AND isDeleted = 0';
    $params = [$bidNumber];
    if ($exceptId) { $sql .= ' AND ID <> ?'; $params[] = (int)$exceptId; }
    $stmt = $db->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

// Turns a database failure into a JSON answer instead of an uncaught fatal.
//
// Worth the handler for one case in particular: until database/
// tbl_competitive_bidding.sql has been run against the HRMIS server, EVERY
// query here raises 42S02 (base table not found). Left uncaught, that is a
// 500 with an HTML body, which the page's res.json() then fails to parse —
// so the first thing anyone opening this screen would see is a broken page
// and a parser error in the console, with nothing pointing at the real cause.
function cbDbFailure(Throwable $e, string $fallback): void {
    error_log('competitive_bidding: ' . $e->getMessage());
    $code = ($e instanceof PDOException) ? $e->getCode() : '';
    // 42S02 = no such table, 42S22 = no such column. Both mean the same thing
    // in practice — a migration in database/ has not been run yet — and both
    // otherwise surface as an unexplained failure on every screen in the
    // module, with the actual cause only in the server log.
    if ($code === '42S02') {
        jsonResponse(false, 'The Competitive Bidding tables have not been created yet. '
            . 'Run database/tbl_competitive_bidding.sql against the HRMIS server, then reload this page.');
    }
    if ($code === '42S22') {
        jsonResponse(false, 'This screen needs a database column that is not there yet. '
            . 'Run the migrations in database/ that have not been applied — tbl_competitive_bidding_alter.sql, '
            . 'tracking_dates_and_remarks.sql and cb_pr_amount.sql — against the HRMIS server, then reload this page.');
    }
    jsonResponse(false, $fallback);
}

if ($method === 'GET') {
    $bidNumber = $_GET['bid_number'] ?? null;
    try {
        if ($bidNumber) {
            $bid = cbGetDetail($db, $bidNumber);
            if (!$bid) jsonResponse(false, 'Bid document not found.');
            jsonResponse(true, '', ['bid' => $bid]);
        }
        $list = cbGetList($db);
    } catch (Throwable $e) {
        cbDbFailure($e, 'Unable to load bid documents.');
    }
    jsonResponse(true, '', ['bids' => $list['bids'], 'counts' => $list['counts']]);
}

$data = getInput();

if ($method === 'POST' || $method === 'PUT') {
    $bidNumber = trim($data['bid_number'] ?? '');
    $items     = $data['items'] ?? [];

    // Validated before any write. A bid saved with its old item rows already
    // soft-deleted and the new ones rejected would be a bid with no items.
    if (!is_array($items)) $items = [];
    cbRejectInvalidItems($items);

    if ($bidNumber === '')                jsonResponse(false, 'Bid Number is required.');
    if (trim($data['contract_name'] ?? '') === '') jsonResponse(false, 'Name of the Contract is required.');
    if (count($items) === 0)              jsonResponse(false, 'At least one item is required.');
}

if ($method === 'POST') {
    // Bid_Number is BAC's own numbering, typed in rather than minted here, so
    // it is the one thing that can collide. Checked rather than left to a
    // UNIQUE constraint: the live table has 18 years of habits behind it and
    // a hard constraint on a hand-typed column is a support call waiting to
    // happen, while a clear message here is not.
    if (cbExists($db, $bidNumber)) {
        jsonResponse(false, "Bid Number {$bidNumber} already exists.");
    }
    try {
        $db->beginTransaction();
        $columns = array_values(CB_FIELDS);
        $sql = 'INSERT INTO ' . CB_TABLE . ' (' . implode(', ', $columns) . ', Date_Created, Created_By, isDeleted) '
             . 'VALUES (' . rtrim(str_repeat('?, ', count($columns)), ', ') . ', NOW(), ?, 0)';
        $values = cbFieldValues($data);
        $values[] = getCurrentUser()['username'] ?? null;
        $db->prepare($sql)->execute($values);
        cbSaveItems($db, $bidNumber, $items);
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        cbDbFailure($e, 'Unable to save the bid document.');
    }
    logAudit('CREATE_BID', 'competitive_bidding', "Created bid document {$bidNumber}");
    notifyPoUsers(
        'Bid Document Created',
        (getCurrentUser()['full_name'] ?? 'Someone') . " created bid document {$bidNumber}",
        "competitive_bidding.php?bid_number=" . urlencode($bidNumber)
    );
    jsonResponse(true, 'Bid document saved.', ['bid_number' => $bidNumber]);
}

if ($method === 'PUT') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Bid document id is required.');
    // Locked means the set has been formally printed and issued. Editing it
    // afterwards would leave the file disagreeing with the paper in a
    // supplier's hands, so the lock has to come off first — deliberately, by
    // someone entitled to do it.
    if (cbIsLocked($db, $id)) {
        jsonResponse(false, 'This bid document is locked. Unlock it first to make changes.');
    }
    if (cbExists($db, $bidNumber, $id)) {
        jsonResponse(false, "Bid Number {$bidNumber} is already used by another document.");
    }
    // The number can be corrected, so the item rows have to follow it: they
    // are joined by Bid_Number, and leaving them behind would orphan them.
    $stmt = $db->prepare('SELECT Bid_Number FROM ' . CB_TABLE . ' WHERE ID = ? AND isDeleted = 0');
    $stmt->execute([$id]);
    $previousNumber = $stmt->fetchColumn();
    if ($previousNumber === false) jsonResponse(false, 'Bid document not found.');

    try {
        $db->beginTransaction();
        $assignments = implode(', ', array_map(fn($c) => "{$c} = ?", array_values(CB_FIELDS)));
        $values = cbFieldValues($data);
        $values[] = getCurrentUser()['username'] ?? null;
        $values[] = $id;
        $db->prepare('UPDATE ' . CB_TABLE . " SET {$assignments}, Edited_By = ? WHERE ID = ?")->execute($values);
        if ($previousNumber !== $bidNumber) {
            $db->prepare('UPDATE ' . CB_ITEMS_TABLE . ' SET Bid_Number = ? WHERE Bid_Number = ?')
               ->execute([$bidNumber, $previousNumber]);
        }
        cbSaveItems($db, $bidNumber, $items);
        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        cbDbFailure($e, 'Unable to update the bid document.');
    }
    logAudit('UPDATE_BID', 'competitive_bidding', "Updated bid document {$bidNumber}");
    jsonResponse(true, 'Bid document updated.', ['bid_number' => $bidNumber]);
}

// True when the document has been formally issued. Read fresh rather than
// trusted from the request: the client's copy may be minutes old, and this is
// the check that protects an issued contract.
function cbIsLocked(PDO $db, int $id): bool {
    $stmt = $db->prepare('SELECT Print_Bid FROM ' . CB_TABLE . ' WHERE ID = ? AND isDeleted = 0');
    $stmt->execute([$id]);
    return ((int)$stmt->fetchColumn()) === 1;
}

// Lock / unlock. Restricted to role_id 1/2/3 (Super Administrator,
// Administrator, Procurement Administrator) exactly as the purchase order
// lock is — narrower than this app's coarse admin tier, which also covers
// unrelated HRMIS roles like Inventory Administrator. Looked up fresh from
// HRMIS because the session only carries the collapsed 3-tier value.
if ($method === 'PATCH' && isset($data['lock_action'])) {
    if (!in_array(hrmisRoleId(getCurrentUser()['id'] ?? 0), [1, 2, 3], true)) {
        jsonResponse(false, 'Only a Super Administrator, Administrator, or Procurement Administrator can lock or unlock a bid document.');
    }
    $bidNumber = trim($data['bid_number'] ?? '');
    $action    = $data['lock_action'] ?? '';
    if (!$bidNumber || !in_array($action, ['lock', 'unlock'], true)) {
        jsonResponse(false, 'Bid Number and a lock action are required.');
    }
    try {
        $db->prepare('UPDATE ' . CB_TABLE . ' SET Print_Bid = ? WHERE Bid_Number = ? AND isDeleted = 0')
           ->execute([$action === 'lock' ? 1 : 0, $bidNumber]);
    } catch (Throwable $e) {
        cbDbFailure($e, 'Unable to change the lock.');
    }
    logAudit(strtoupper($action) . '_BID', 'competitive_bidding', ucfirst($action) . "ed bid document {$bidNumber}");
    notifyPoUsers(
        $action === 'lock' ? 'Bid Document Locked' : 'Bid Document Unlocked',
        (getCurrentUser()['full_name'] ?? 'Someone') . " {$action}ed bid document {$bidNumber}",
        'competitive_bidding.php?bid_number=' . urlencode($bidNumber)
    );
    jsonResponse(true, 'Bid document ' . ($action === 'lock' ? 'locked.' : 'unlocked.'));
}

// One quick field at a time. The field name is checked against CB_QUICK_FIELDS
// before it goes anywhere near the SQL: the column name cannot be a bound
// parameter, so it is concatenated into the statement, so it must never come
// from anywhere a request can reach. Same rule as HRMIS_FLAG_COLUMNS.
if ($method === 'PATCH' && isset($data['quick_field'])) {
    $bidNumber = trim($data['bid_number'] ?? '');
    $field     = (string)$data['quick_field'];
    if (!$bidNumber) jsonResponse(false, 'Bid Number is required.');
    if (!isset(CB_QUICK_FIELDS[$field])) {
        error_log("competitive_bidding: refused unmapped quick field '{$field}'");
        jsonResponse(false, 'That field cannot be updated here.');
    }
    $meta  = CB_QUICK_FIELDS[$field];
    $raw   = trim((string)($data['value'] ?? ''));
    // Blank clears the field rather than storing an empty string, so a cleared
    // date is NULL and prints as the ruled line, not as 0000-00-00.
    $value = $raw === '' ? null : ($meta['type'] === 'date' ? cbDate($raw) : $raw);
    try {
        $db->prepare('UPDATE ' . CB_TABLE . " SET {$meta['col']} = ? WHERE Bid_Number = ? AND isDeleted = 0")
           ->execute([$value, $bidNumber]);
    } catch (Throwable $e) {
        cbDbFailure($e, 'Unable to save that field.');
    }
    logAudit('UPDATE_BID_FIELD', 'competitive_bidding',
        "Set {$meta['col']} on {$bidNumber} to " . ($value === null ? '(none)' : $value));
    jsonResponse(true, 'Saved.', ['field' => $field, 'value' => $value]);
}

if ($method === 'DELETE') {

    // Same narrow role gate the purchase order delete uses — role_id 1/2/3
    // (Super Administrator / Administrator / Procurement Administrator), read
    // fresh from HRMIS rather than from the session's collapsed 3-tier role.
    if (!in_array(hrmisRoleId(getCurrentUser()['id'] ?? 0), [1, 2, 3], true)) {
        jsonResponse(false, 'Only a Super Administrator, Administrator, or Procurement Administrator can delete a bid document.');
    }
    $bidNumber = trim($_GET['bid_number'] ?? '');
    if (!$bidNumber) jsonResponse(false, 'Bid Number is required.');
    $existing = cbGetDetail($db, $bidNumber);
    if (!$existing) jsonResponse(false, 'Bid document not found.');
    if (!empty($existing['locked'])) {
        jsonResponse(false, 'This bid document is locked. Unlock it first to delete it.');
    }
    try {
        $db->prepare('UPDATE ' . CB_TABLE . ' SET isDeleted = 1, Deleted_by = ? WHERE Bid_Number = ?')
           ->execute([getCurrentUser()['username'] ?? null, $bidNumber]);
        $db->prepare('UPDATE ' . CB_ITEMS_TABLE . ' SET isDeleted = 1 WHERE Bid_Number = ?')->execute([$bidNumber]);
    } catch (Exception $e) {
        cbDbFailure($e, 'Unable to delete the bid document.');
    }
    logAudit('DELETE_BID', 'competitive_bidding', "Deleted bid document {$bidNumber}");
    jsonResponse(true, 'Bid document deleted.');
}

jsonResponse(false, 'Unsupported request.');
