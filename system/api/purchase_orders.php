<?php
require_once '../config.php';
requireAuth();
requireModule('purchase_orders');
header('Content-Type: application/json');
$db = getPoDB();
$method = $_SERVER['REQUEST_METHOD'];

// True when the column is actually on the table.
//
// Date_PR is added by database/tracking_dates_and_remarks.sql, and that
// migration REBUILDS tbl_purchase_order — 26,000+ rows, write-locked for the
// duration — so it has to wait for a maintenance window. Without this guard,
// deploying the code before that window breaks every Purchase Order screen
// with "Unknown column 'Date_PR'", which is a far worse outcome than the field
// simply not being there yet. Once the migration has run the guard is true
// forever and costs one cached SHOW COLUMNS per request.
//
// $table is a compile-time constant at every call site, never request data —
// which matters, because a table name cannot be a bound parameter and so is
// interpolated.
//
// The whole column list is fetched and matched in PHP rather than asking the
// server to filter it with `SHOW COLUMNS ... LIKE ?`. That form looks tidier
// and does not work here: MySQL 5.5 will not accept a placeholder in a SHOW
// statement, and with PDO::ATTR_EMULATE_PREPARES off there is no client-side
// substitution to paper over it, so prepare() throws a 1064 syntax error. The
// catch below then returned false for a column that was plainly there, which
// silently switched Date_PR off across the whole screen AFTER the migration
// adding it had already been run — a failure with no symptom except the field
// quietly never saving.
//
// Matched case-insensitively because MySQL column names are, and one cached
// round trip per table per request is the entire cost.
function tableHasColumn(PDO $db, string $table, string $column): bool {
    static $cache = [];
    if (!isset($cache[$table])) {
        try {
            $cols = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN, 0);
            $cache[$table] = array_change_key_case(array_flip($cols), CASE_LOWER);
        } catch (Throwable $e) {
            error_log('tableHasColumn failed for ' . $table . ': ' . $e->getMessage());
            $cache[$table] = [];
        }
    }
    return isset($cache[$table][strtolower($column)]);
}

// Blank stays NULL rather than becoming '0000-00-00'. A zero date sorts and
// compares as a real date, which would quietly poison exactly the lead-time
// reports this column was added for.
function parseDateOnly($value) {
    $value = trim((string)$value);
    if ($value === '') return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : null;
}

function parseDate($value) {
    if (!$value) return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

// Legacy VB (PO_Choices.vb) prefixed this with a fund-source code the user
// picked before opening the form — PO (General Fund) / TF (Trust Fund) /
// SEF (Special Education Fund) — a separate concept from the procurement
// Category field (ref_category) saved onto the row. purchase_orders.php's
// fund-choice modal sends that pick as fund_type; whitelisted here since it
// feeds straight into the generated PO Number.
// Builds the PO number from a row id that has ALREADY been allocated by the
// database, rather than guessing the next one with MAX(ID)+1 before inserting.
// MAX(ID)+1 handed two people saving in the same moment the same number; the
// AUTO_INCREMENT value is unique by construction, so the race cannot happen.
//
// date('n'), not date('m'): the month is NOT zero-padded. All 26,000+ legacy
// rows are "…sys20268…" for August; the padded form the previous code emitted
// ("…sys202608…") is a different string that no other office recognises. Ten
// test rows already carry the padded shape — everything from here matches the
// legacy format.
// Rejects item rows that are neither a title nor a priced item — see the note
// on invalidItemRows() in purchase_orders.php for why Total Cost is the test
// and Quantity/Unit Cost are not. Enforced here as well as in the browser
// because the browser is not a place to enforce anything.
// Returns ['item' => [row numbers], 'title' => [row numbers]] for rows that do
// not match the type they were saved as. See invalidItemRows() in
// purchase_orders.php for why the declared type is used and not inferred.
//
// is_title arrives from the item grid. When it is absent — an older client, or
// any caller that does not send it — the shape is inferred as before, so
// nothing that used to save starts failing.
function invalidItemRowNumbers(array $items): array {
    $bad = ['item' => [], 'title' => []];
    foreach ($items as $i => $item) {
        if (trim((string)($item['items_description'] ?? '')) === '') continue;
        $num  = fn($k) => (float)($item[$k] ?? 0);
        $unit = trim((string)($item['unit'] ?? ''));
        $blank = $unit === '' && $num('quantity') == 0 && $num('unit_cost') == 0 && $num('total_cost') == 0;

        $isTitle = array_key_exists('is_title', $item) ? (bool)$item['is_title'] : $blank;
        if ($isTitle) {
            if (!$blank) $bad['title'][] = $i + 1;
        } elseif ($num('total_cost') == 0) {
            $bad['item'][] = $i + 1;
        }
    }
    return $bad;
}

function rejectInvalidItems(array $items): void {
    $bad = invalidItemRowNumbers($items);
    if (!$bad['item'] && !$bad['title']) return;
    $list = function (array $rows) {
        $shown = array_slice($rows, 0, 6);
        return '#' . implode(', #', $shown)
             . (count($rows) > 6 ? ' and ' . (count($rows) - 6) . ' more' : '');
    };
    $parts = [];
    if ($bad['item']) {
        $parts[] = 'Row ' . $list($bad['item']) . ' has no Total Cost. Fill in Qty and Unit Cost, '
                 . 'enter a Total Cost directly, or make it a LOT / title row.';
    }
    if ($bad['title']) {
        $parts[] = 'Row ' . $list($bad['title']) . ' is a LOT / title row but still carries a unit or a cost.';
    }
    jsonResponse(false, implode(' ', $parts));
}

function formatPoNumber($fundType, int $id): string {
    $prefix = in_array($fundType, ['PO', 'TF', 'SEF'], true) ? $fundType : 'PO';
    return sprintf('%s-sys%s%s%05d', $prefix, date('Y'), date('n'), $id);
}

function getOrderList(PDO $db) {
    $search = trim($_GET['search'] ?? '');
    $from   = $_GET['from'] ?? '';
    $to     = $_GET['to'] ?? '';
    $params = [];
    $sql = "SELECT id, PO_Number AS po_number, PR_Number AS pr_number, Supplier_Name AS supplier_name, Department AS department, Barcode_Number AS barcode_number, Category AS category, Purpose AS purpose, Date_Created AS date_created, Date_Approved_PO AS date_approved_po, Print_PO AS print_po, Status AS status FROM tbl_purchase_order WHERE isDeleted = 0";

    if ($from) {
        $sql .= ' AND Date_Created >= ?';
        $params[] = date('Y-m-d 00:00:00', strtotime($from));
    }
    if ($to) {
        $sql .= ' AND Date_Created <= ?';
        $params[] = date('Y-m-d 23:59:59', strtotime($to));
    }
    if ($search !== '') {
        // Barcode_Number is searchable because it is the DTS document number
        // printed on the paperwork — someone holding a document searches by
        // what is printed on it, not by the PO number it happens to map to.
        $sql .= ' AND (PO_Number LIKE ? OR PR_Number LIKE ? OR Department LIKE ? OR Supplier_Name LIKE ?
                       OR Supplier_Address LIKE ? OR Date_Created LIKE ? OR Category LIKE ?
                       OR Barcode_Number LIKE ?)';
        $term = '%' . $search . '%';
        $params = array_merge($params, array_fill(0, 8, $term));
    }
    $sql .= ' ORDER BY Date_Created DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orders as &$order) {
        $order['approved'] = $order['date_approved_po'] ? true : false;
        $order['locked']   = ((int)$order['print_po']) === 1;
    }
    unset($order);
    $orders = fixLegacyEncodingDeep($orders);

    // Counts are taken BEFORE the status filter is applied, so the chips above
    // the table always describe the same date/search scope the user is looking
    // at — picking "Pending" narrows the rows without making its own count
    // collapse to the total. Counted in PHP off the rows already fetched
    // rather than as extra COUNT(*) queries, so the numbers and the rows can
    // never disagree.
    // no_supplier cuts across pending/approved rather than being a fourth
    // state — a PO missing its supplier is still pending or approved, so this
    // count deliberately overlaps the other two and the three will not sum
    // to the total.
    $counts = ['pending' => 0, 'approved' => 0, 'no_supplier' => 0, 'total' => count($orders)];
    foreach ($orders as $o) {
        $o['approved'] ? $counts['approved']++ : $counts['pending']++;
        if (trim((string)($o['supplier_name'] ?? '')) === '') $counts['no_supplier']++;
    }

    $status = strtolower(trim($_GET['status'] ?? ''));
    if ($status === 'pending') {
        $orders = array_values(array_filter($orders, fn($o) => !$o['approved']));
    } elseif ($status === 'approved') {
        $orders = array_values(array_filter($orders, fn($o) => $o['approved']));
    } elseif ($status === 'no_supplier') {
        $orders = array_values(array_filter($orders, fn($o) => trim((string)($o['supplier_name'] ?? '')) === ''));
    }

    return ['orders' => $orders, 'counts' => $counts];
}

function getOrderDetail(PDO $db, $poNumber) {
    $datePrSelect = tableHasColumn($db, 'tbl_purchase_order', 'Date_PR') ? 'Date_PR AS date_pr, ' : '';
    // Explicit aliases — `SELECT *` returns the DB's native PascalCase column
    // names, but the frontend (fillForm() etc.) reads lowercase snake_case.
    $stmt = $db->prepare(
        'SELECT ID AS id, PO_Number AS po_number, PR_Number AS pr_number, ' . $datePrSelect . 'Department AS department,
                Division AS division, Section AS section, Supplier_Name AS supplier_name,
                Supplier_Address AS supplier_address, Category AS category,
                Date_Received_Supplier AS date_received_supplier, Date_COA_Received AS date_coa_received,
                Date_Approved_PO AS date_approved_po, Remarks AS remarks, Date_PO AS date_po,
                Purpose AS purpose, PR_Received_Name AS pr_received_name, Requested_by AS requested_by,
                Date_Created AS date_created, Mode_of_Procurement AS mode_of_procurement,
                Place_of_Delivery AS place_of_delivery, Reference_No AS reference_no,
                BAC_Resolution_No AS bac_resolution_no, PO_Working_Days AS po_working_days,
                PO_Payment_Term AS po_payment_term, Note AS note, End_User AS end_user,
                Barcode_Number AS barcode_number, Print_PO AS print_po, Print_Obligation AS print_obligation,
                Status AS status
         FROM tbl_purchase_order WHERE PO_Number = ? AND isDeleted = 0'
    );
    $stmt->execute([$poNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) return null;
    $stmt = $db->prepare('SELECT Item_ID AS item_id, Unit AS unit, Items_Description AS items_description, Quantity AS quantity, Unit_Cost AS unit_cost, Total_Cost AS total_cost, NumberSeries AS number_series FROM tbl_purchase_order_items_details WHERE PO_Number = ? AND isDeleted = 0 ORDER BY ID');
    $stmt->execute([$poNumber]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return fixLegacyEncodingDeep($order);
}

function ensureCatalogItem(PDO $db, array $item) {
    $stmt = $db->prepare('SELECT Item_ID FROM tbl_ppmp_bac_items WHERE Items_Description = ? AND isDeleted = 0 LIMIT 1');
    $stmt->execute([$item['items_description']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return $existing['Item_ID'];
    }
    $stmt = $db->prepare('INSERT INTO tbl_ppmp_bac_items (Unit, Items_Description, Unit_Cost, isDeleted) VALUES (?, ?, ?, 0)');
    $stmt->execute([$item['unit'], $item['items_description'], $item['unit_cost']]);
    return $db->lastInsertId();
}

function getStats(PDO $db) {
    $stats = [];
    $stats['pos_today'] = (int)$db->query(
        "SELECT COUNT(*) FROM tbl_purchase_order WHERE isDeleted = 0 AND DATE(Date_Created) = CURDATE()"
    )->fetchColumn();
    $stats['pos_this_month'] = (int)$db->query(
        "SELECT COUNT(*) FROM tbl_purchase_order WHERE isDeleted = 0 AND Date_Created >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    )->fetchColumn();
    $stats['amount_this_month'] = (float)$db->query(
        "SELECT COALESCE(SUM(pd.Total_Cost), 0) FROM tbl_purchase_order_items_details pd
         INNER JOIN tbl_purchase_order po ON po.PO_Number = pd.PO_Number
         WHERE pd.isDeleted = 0 AND po.isDeleted = 0 AND po.Date_Created >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    )->fetchColumn();
    $stats['pending_approval'] = (int)$db->query(
        "SELECT COUNT(*) FROM tbl_purchase_order WHERE isDeleted = 0 AND (Date_Approved_PO IS NULL OR Date_Approved_PO = '')"
    )->fetchColumn();

    $recent = $db->query(
        "SELECT PO_Number AS po_number, Supplier_Name AS supplier_name, Category AS category, Date_Created AS date_created
         FROM tbl_purchase_order WHERE isDeleted = 0 ORDER BY Date_Created DESC LIMIT 6"
    )->fetchAll(PDO::FETCH_ASSOC);
    $stats['recent'] = fixLegacyEncodingDeep($recent);
    return $stats;
}

if ($method === 'GET') {
    if (isset($_GET['stats'])) {
        jsonResponse(true, '', ['stats' => getStats($db)]);
    }
    $poNumber = $_GET['po_number'] ?? null;
    if ($poNumber) {
        $order = getOrderDetail($db, $poNumber);
        if (!$order) jsonResponse(false, 'Purchase order not found.');
        // Verification block for the printout's QR code — whether this copy is
        // the original or an edited revision, plus the current lifecycle stage.
        $order['verification'] = poVerificationData($poNumber);
        // Scale 6 renders well above the ~104px it is displayed at, so the
        // html2canvas rasterisation downsamples rather than blurring upward.
        $order['qr_png']       = renderPoQrCodePng($poNumber, 6);
        // Code 128 of the PO number, shown in the letterhead of both the
        // Purchase Order and the Obligation Request printouts. Derived from
        // the row's own id + creation date (see poNumberBarcode()), not
        // parsed from PO_Number's text — falls back to the old
        // punctuation-stripped PO number only for the rare row missing a
        // usable Date_Created, so a barcode is never left blank.
        $barcodeValue = poNumberBarcode($order['id'] ?? 0, $order['date_created'] ?? null);
        if ($barcodeValue === '') $barcodeValue = poBarcodeValue($poNumber);
        $order['barcode_png']  = renderPoBarcodePng($barcodeValue);
        // The caption printed under the bars must be what the scanner will
        // actually return, not the hyphenated PO number — otherwise anyone
        // comparing a scan against the paper thinks the scanner misread.
        $order['barcode_text']  = poBarcodeValue($barcodeValue);
        $order['barcode_width'] = poBarcodeDisplayWidth($barcodeValue);
        jsonResponse(true, '', ['order' => $order]);
    }
    $list = getOrderList($db);
    jsonResponse(true, '', ['orders' => $list['orders'], 'counts' => $list['counts']]);
}

$data = getInput();
if ($method === 'POST') {
    $items = $data['items'] ?? [];
    // Validate before ANY write. Placed after the soft-delete it would rely on
    // the transaction being rolled back implicitly when jsonResponse() exits —
    // which happens to work, but leaves a purchase order one dropped connection
    // away from losing every item row it had.
    rejectInvalidItems($items);
    if (!is_array($items) || count($items) === 0) jsonResponse(false, 'At least one item is required.');
    $poNumber   = trim($data['po_number'] ?? '');
    $fundType   = $data['fund_type'] ?? 'PO';
    // Auto-numbered POs get their number from the row's own AUTO_INCREMENT id,
    // which is only known after the insert — see the placeholder below.
    $autoNumber = ($poNumber === '' || $poNumber === 'AUTO_GENERATED');
    if (!$autoNumber) {
        $exists = $db->prepare('SELECT COUNT(*) FROM tbl_purchase_order WHERE PO_Number = ? AND isDeleted = 0');
        $exists->execute([$poNumber]);
        if ((int)$exists->fetchColumn() > 0) {
            jsonResponse(false, 'A purchase order with that PO number already exists.');
        }
    }
    $datePo = parseDate($data['date_po'] ?? null) ?: date('Y-m-d H:i:s');
    $dateCreated = date('Y-m-d H:i:s');
    // Default the Barcode Number to the scannable form of the PO number — the
    // same string the printed Code 128 encodes — so a new PO is never created
    // with a blank one. The Create form has no barcode field, so in practice
    // this always supplies the value; the Obligation Request dialog can still
    // overwrite it with a real DTS number afterwards.
    //
    // Only new POs. The ~17,800 existing blanks are deliberately left as they
    // are, where blank still means "no obligation was ever raised".
    $suppliedBarcode = trim($data['barcode_number'] ?? '');
    // For an auto-numbered PO the number does not exist yet, so the default is
    // filled in right after the insert, once the id is known.
    $barcodeNumber = $autoNumber
        ? $suppliedBarcode
        : ($suppliedBarcode !== '' ? $suppliedBarcode : (dtsBarcodeForPrNumber($data['pr_number'] ?? '') ?? ''));
    try {
        $db->beginTransaction();
        // A short-lived placeholder: the row must exist before the database
        // will tell us its id. It is replaced a few lines down, inside this
        // same transaction, so no caller ever observes it — and a rollback
        // removes the row entirely rather than leaving a stray number.
        $insertPoNumber = $autoNumber ? '~NEW-' . bin2hex(random_bytes(6)) : $poNumber;
        $hasDatePr = tableHasColumn($db, 'tbl_purchase_order', 'Date_PR');
        $stmt = $db->prepare('INSERT INTO tbl_purchase_order (PO_Number, PR_Number, ' . ($hasDatePr ? 'Date_PR, ' : '') . 'PR_Received_Name, Category, Department, Division, Section, Reference_No, BAC_Resolution_No, Supplier_Name, Supplier_Address, Date_PO, Purpose, End_User, Date_Created, isDeleted, Mode_of_Procurement, Place_of_Delivery, PO_Working_Days, PO_Payment_Term, Note, Requested_by, Barcode_Number, Date_Received_Supplier, Date_COA_Received, Date_Approved_PO, Remarks) VALUES (?, ?, ' . ($hasDatePr ? '?, ' : '') . '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insertValues = array_merge([
            $insertPoNumber,
            trim($data['pr_number'] ?? ''),
        ], $hasDatePr ? [parseDateOnly($data['date_pr'] ?? '')] : [], [
            trim($data['pr_received_name'] ?? ''),
            trim($data['category'] ?? ''),
            canonicalDepartment($db, $data['department'] ?? ''),
            canonicalDivision($db, $data['division'] ?? ''),
            canonicalSection($db, $data['section'] ?? ''),
            trim($data['reference_no'] ?? ''),
            trim($data['bac_resolution_no'] ?? ''),
            canonicalSupplierName($db, $data['supplier_name'] ?? ''),
            trim($data['supplier_address'] ?? ''),
            $datePo,
            trim($data['purpose'] ?? ''),
            trim($data['end_user'] ?? ''),
            $dateCreated,
            trim($data['mode_of_procurement'] ?? ''),
            trim($data['place_of_delivery'] ?? ''),
            trim($data['po_working_days'] ?? ''),
            trim($data['po_payment_term'] ?? ''),
            trim($data['note'] ?? ''),
            trim($data['requested_by'] ?? ''),
            $barcodeNumber,
            parseDate($data['date_received_supplier'] ?? null),
            parseDate($data['date_coa_received'] ?? null),
            parseDate($data['date_approved_po'] ?? null),
            trim($data['remarks'] ?? ''),
        ]);
        $stmt->execute($insertValues);

        // Now the id exists, so the real number can be built from it. One
        // UPDATE by primary key — it replaces the SELECT MAX(ID) this used to
        // run before the insert, so the statement count per save is unchanged.
        if ($autoNumber) {
            $newId    = (int)$db->lastInsertId();
            $poNumber = formatPoNumber($fundType, $newId);
            if ($barcodeNumber === '') $barcodeNumber = dtsBarcodeForPrNumber($data['pr_number'] ?? '') ?? '';
            $db->prepare('UPDATE tbl_purchase_order SET PO_Number = ?, Barcode_Number = ? WHERE ID = ?')
               ->execute([$poNumber, $barcodeNumber, $newId]);
        }

        $itemStmt = $db->prepare('INSERT INTO tbl_purchase_order_items_details (PO_Number, Item_ID, Unit, Items_Description, Quantity, Unit_Cost, Total_Cost, NumberSeries, Date_PO, Date_Created, End_User, isDeleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
        foreach ($items as $item) {
            if (trim($item['items_description'] ?? '') === '') continue;
            $itemId = null;
            if (!empty($item['items_description'])) {
                $itemId = ensureCatalogItem($db, [
                    'unit' => trim($item['unit'] ?? ''),
                    'items_description' => trim($item['items_description']),
                    'unit_cost' => is_numeric($item['unit_cost'] ?? null) ? $item['unit_cost'] : 0,
                ]);
            }
            $quantity = is_numeric($item['quantity'] ?? null) ? $item['quantity'] : 0;
            $unitCost = is_numeric($item['unit_cost'] ?? null) ? $item['unit_cost'] : 0;
            $totalCost = is_numeric($item['total_cost'] ?? null) ? $item['total_cost'] : ($quantity * $unitCost);
            $itemStmt->execute([
                $poNumber,
                $itemId,
                trim($item['unit'] ?? ''),
                trim($item['items_description'] ?? ''),
                $quantity,
                $unitCost,
                $totalCost,
                trim($item['number_series'] ?? ''),
                $datePo,
                $dateCreated,
                trim($data['end_user'] ?? ''),
            ]);
        }
        $db->commit();
        logAudit('CREATE_PO', 'purchase_orders', "Created PO {$poNumber}");
        $actorName = getCurrentUser()['full_name'] ?? 'Someone';
        notifyPoUsers(
            'New Purchase Order Created',
            // Supplier and status are appended by notifyPoUsers() itself, so
            // they stay consistent across every event and aren't repeated here.
            "{$actorName} created PO {$poNumber}",
            'purchase_orders.php?po_number=' . urlencode($poNumber) . '&view=1',
            $poNumber,
            'all'
        );
        jsonResponse(true, 'Purchase order created successfully.', ['po_number' => $poNumber]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, 'Unable to create purchase order.');
    }
}

// Edit permission, checked here on 2026-09-09 — previously ANY account with
// Purchase Orders module access could edit ANY unlocked PO via this endpoint,
// with nothing enforcing it server-side (the edit button itself had no
// permission gate either). Configurable per role from Access Levels now, same
// table/function lock and delete already use — see poActionPermission() in
// config.php. Defaults to "yes" for every role until an admin says otherwise,
// so this closes the gap without silently locking anyone out on deploy.
if ($method === 'PUT') {
    if (!canEditPurchaseOrder(hrmisRoleId(getCurrentUser()['id'] ?? 0))) {
        jsonResponse(false, 'You do not have permission to edit purchase orders.');
    }
    $poNumber = trim($_GET['po_number'] ?? '');
    if (!$poNumber) jsonResponse(false, 'PO number is required for update.');
    $existing = getOrderDetail($db, $poNumber);
    if (!$existing) jsonResponse(false, 'Purchase order not found.');
    if ((int)($existing['print_po'] ?? 0) === 1) {
        jsonResponse(false, 'This purchase order is locked. Unlock it first to make changes.');
    }
    $items = $data['items'] ?? [];
    // Validate before ANY write. Placed after the soft-delete it would rely on
    // the transaction being rolled back implicitly when jsonResponse() exits —
    // which happens to work, but leaves a purchase order one dropped connection
    // away from losing every item row it had.
    rejectInvalidItems($items);
    if (!is_array($items) || count($items) === 0) jsonResponse(false, 'At least one item is required.');
    $datePo = parseDate($data['date_po'] ?? null) ?: $existing['Date_PO'];
    try {
        $db->beginTransaction();
        // Barcode_Number, Date_Received_Supplier, Date_COA_Received,
        // Date_Approved_PO, and Remarks are deliberately NOT set here — those
        // five are the "Additional Details" quick fields, each saved
        // independently via the PATCH handlers below (see openQuickUpdate()/
        // saveQuickUpdate() in purchase_orders.php). Including them in this
        // header/items save would let an unrelated edit silently blank them
        // out whenever the form doesn't happen to carry their current value.
        $hasDatePr = tableHasColumn($db, 'tbl_purchase_order', 'Date_PR');
        $stmt = $db->prepare('UPDATE tbl_purchase_order SET PR_Number = ?, ' . ($hasDatePr ? 'Date_PR = ?, ' : '') . 'PR_Received_Name = ?, Category = ?, Department = ?, Division = ?, Section = ?, Reference_No = ?, BAC_Resolution_No = ?, Supplier_Name = ?, Supplier_Address = ?, Date_PO = ?, Purpose = ?, End_User = ?, Mode_of_Procurement = ?, Place_of_Delivery = ?, PO_Working_Days = ?, PO_Payment_Term = ?, Note = ?, Requested_by = ? WHERE PO_Number = ?');
        $updateValues = array_merge([
            trim($data['pr_number'] ?? ''),
        ], $hasDatePr ? [parseDateOnly($data['date_pr'] ?? '')] : [], [
            trim($data['pr_received_name'] ?? ''),
            trim($data['category'] ?? ''),
            canonicalDepartment($db, $data['department'] ?? ''),
            canonicalDivision($db, $data['division'] ?? ''),
            canonicalSection($db, $data['section'] ?? ''),
            trim($data['reference_no'] ?? ''),
            trim($data['bac_resolution_no'] ?? ''),
            canonicalSupplierName($db, $data['supplier_name'] ?? ''),
            trim($data['supplier_address'] ?? ''),
            $datePo,
            trim($data['purpose'] ?? ''),
            trim($data['end_user'] ?? ''),
            trim($data['mode_of_procurement'] ?? ''),
            trim($data['place_of_delivery'] ?? ''),
            trim($data['po_working_days'] ?? ''),
            trim($data['po_payment_term'] ?? ''),
            trim($data['note'] ?? ''),
            trim($data['requested_by'] ?? ''),
            $poNumber,
        ]);
        $stmt->execute($updateValues);
        $db->prepare('UPDATE tbl_purchase_order_items_details SET isDeleted = 1 WHERE PO_Number = ?')->execute([$poNumber]);
        $itemStmt = $db->prepare('INSERT INTO tbl_purchase_order_items_details (PO_Number, Item_ID, Unit, Items_Description, Quantity, Unit_Cost, Total_Cost, NumberSeries, Date_PO, Date_Created, End_User, isDeleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
        foreach ($items as $item) {
            if (trim($item['items_description'] ?? '') === '') continue;
            $itemId = null;
            if (!empty($item['items_description'])) {
                $itemId = ensureCatalogItem($db, [
                    'unit' => trim($item['unit'] ?? ''),
                    'items_description' => trim($item['items_description']),
                    'unit_cost' => is_numeric($item['unit_cost'] ?? null) ? $item['unit_cost'] : 0,
                ]);
            }
            $quantity = is_numeric($item['quantity'] ?? null) ? $item['quantity'] : 0;
            $unitCost = is_numeric($item['unit_cost'] ?? null) ? $item['unit_cost'] : 0;
            $totalCost = is_numeric($item['total_cost'] ?? null) ? $item['total_cost'] : ($quantity * $unitCost);
            $itemStmt->execute([
                $poNumber,
                $itemId,
                trim($item['unit'] ?? ''),
                trim($item['items_description'] ?? ''),
                $quantity,
                $unitCost,
                $totalCost,
                trim($item['number_series'] ?? ''),
                $datePo,
                date('Y-m-d H:i:s'),
                trim($data['end_user'] ?? ''),
            ]);
        }
        $db->commit();
        logAudit('UPDATE_PO', 'purchase_orders', "Updated PO {$poNumber}");
        $actorName = getCurrentUser()['full_name'] ?? 'Someone';
        notifyPoUsers(
            'Purchase Order Updated',
            "{$actorName} edited PO {$poNumber}",
            'purchase_orders.php?po_number=' . urlencode($poNumber) . '&view=1',
            $poNumber,
            'all'
        );
        jsonResponse(true, 'Purchase order updated successfully.');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, 'Unable to update purchase order.');
    }
}

// Deleting is gated by canDeletePurchaseOrder() — configurable per role from
// Access Levels (see poActionPermission() in config.php); defaults to the
// original role_id 1/2/3 rule (Super Administrator / Administrator /
// Procurement Administrator) for any role Access Levels hasn't touched yet.
//
// Everyone with module access can create and edit — that is the procurement
// desk's job — but removing a purchase order someone else raised is not, and
// module access is now held by 40 accounts rather than a handful. Read from
// HRMIS rather than the session, for the reason on hrmisRoleId().
if ($method === 'DELETE') {
    if (!canDeletePurchaseOrder(hrmisRoleId(getCurrentUser()['id'] ?? 0))) {
        jsonResponse(false, 'You do not have permission to delete purchase orders.');
    }
    $poNumber = trim($_GET['po_number'] ?? '');
    if (!$poNumber) jsonResponse(false, 'PO number is required.');
    $order = getOrderDetail($db, $poNumber);
    if (!$order) jsonResponse(false, 'Purchase order not found.');
    try {
        $stmt = $db->prepare('UPDATE tbl_purchase_order SET isDeleted = 1, Deleted_By = ? WHERE PO_Number = ?');
        $stmt->execute([getCurrentUser()['username'] ?? null, $poNumber]);
        $db->prepare('UPDATE tbl_purchase_order_items_details SET isDeleted = 1 WHERE PO_Number = ?')->execute([$poNumber]);
        logAudit('DELETE_PO', 'purchase_orders', "Deleted PO {$poNumber}");
        jsonResponse(true, 'Purchase order deleted.');
    } catch (Exception $e) {
        jsonResponse(false, 'Unable to delete purchase order.');
    }
}

// Lock/unlock — mirrors the WinForms app's Print_PO/Print_Obligation flags,
// which gate further edits once a PO has been formally printed/obligated.
// Gated by canLockPurchaseOrder() — configurable per role from Access Levels
// (see poActionPermission() in config.php); defaults to the original role_id
// 1/2/3 rule (Super Administrator / Administrator / Procurement
// Administrator) for any role Access Levels hasn't touched yet — narrower
// than this app's coarse admin/super_admin tier, which also covers unrelated
// HRMIS roles like Inventory Administrator or PPMP Administrator (any
// table_role.role containing "Administrator" collapses to 'admin' at login —
// see api/auth.php). Looked up fresh here rather than trusting session role,
// since the session only stores the collapsed 3-tier value.
if ($method === 'PATCH' && isset($data['lock_action'])) {
    $currentRoleId = hrmisRoleId(getCurrentUser()['id'] ?? 0);
    if (!canLockPurchaseOrder($currentRoleId)) {
        jsonResponse(false, 'You do not have permission to lock or unlock purchase orders.');
    }
    $poNumber = trim($data['po_number'] ?? '');
    $action   = $data['lock_action'] ?? '';
    if (!$poNumber || !in_array($action, ['lock', 'unlock'], true)) {
        jsonResponse(false, 'PO number and a valid lock action are required.');
    }
    $order = getOrderDetail($db, $poNumber);
    if (!$order) jsonResponse(false, 'Purchase order not found.');
    $flag = $action === 'lock' ? 1 : 0;
    $db->prepare('UPDATE tbl_purchase_order SET Print_PO = ?, Print_Obligation = ? WHERE PO_Number = ?')
       ->execute([$flag, $flag, $poNumber]);
    logAudit(strtoupper($action) . '_PO', 'purchase_orders', ucfirst($action) . "ed PO {$poNumber}");
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    notifyPoUsers(
        $action === 'lock' ? 'Purchase Order Locked' : 'Purchase Order Unlocked',
        "{$actorName} {$action}ed PO {$poNumber}",
        'purchase_orders.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber
    );
    jsonResponse(true, 'Purchase order ' . ($action === 'lock' ? 'locked.' : 'unlocked.'));
}

// Barcode Number save from the Obligation Request modal — matches
// Obligation_Print.vb's btnSave_Click/btnSave2_Click, which both stamp the
// barcode onto the PO before rendering either report. Open to any user with
// module access (not admin-gated like lock/unlock above), same as the
// legacy dialog had no role check either.
if ($method === 'PATCH' && isset($data['barcode_number'])) {
    $poNumber = trim($data['po_number'] ?? '');
    if (!$poNumber) jsonResponse(false, 'PO number is required.');
    $order = getOrderDetail($db, $poNumber);
    if (!$order) jsonResponse(false, 'Purchase order not found.');

    // No-op guard. saveObligationBarcodeAndPrint() PATCHes here on EVERY press
    // of Print on the Obligation Request — that is how the legacy dialog worked
    // — so printing the same form three times used to write the same value three
    // times and fire three identical "Barcode Number Set" notifications. Nothing
    // changed, so there is nothing to announce or audit: return early.
    $newBarcode = trim($data['barcode_number']);
    if ($newBarcode === trim((string)($order['barcode_number'] ?? ''))) {
        jsonResponse(true, 'Barcode number unchanged.');
    }

    $db->prepare('UPDATE tbl_purchase_order SET Barcode_Number = ? WHERE PO_Number = ?')
       ->execute([$newBarcode, $poNumber]);
    logAudit('UPDATE_PO', 'purchase_orders', "Set Barcode Number on PO {$poNumber}");
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    notifyPoUsers(
        'Barcode Number Set',
        "{$actorName} set the Barcode Number on PO {$poNumber}",
        'purchase_orders.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber
    );
    jsonResponse(true, 'Barcode number saved.');
}

// The remaining "Additional Details" quick fields — same one-field-at-a-time
// PATCH pattern as Barcode Number above, wired to the pencil buttons next to
// each field in purchase_orders.php's Additional Details card.
if ($method === 'PATCH' && isset($data['date_received_supplier'])) {
    $poNumber = trim($data['po_number'] ?? '');
    if (!$poNumber) jsonResponse(false, 'PO number is required.');
    $order = getOrderDetail($db, $poNumber);
    if (!$order) jsonResponse(false, 'Purchase order not found.');
    $db->prepare('UPDATE tbl_purchase_order SET Date_Received_Supplier = ? WHERE PO_Number = ?')
       ->execute([parseDate($data['date_received_supplier']), $poNumber]);
    logAudit('UPDATE_PO', 'purchase_orders', "Set Date Received Supplier on PO {$poNumber}");
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    notifyPoUsers(
        'Date Received Supplier Updated',
        "{$actorName} updated Date Received Supplier on PO {$poNumber}",
        'purchase_orders.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber,
        'all'
    );
    jsonResponse(true, 'Date Received Supplier saved.');
}

if ($method === 'PATCH' && isset($data['date_coa_received'])) {
    $poNumber = trim($data['po_number'] ?? '');
    if (!$poNumber) jsonResponse(false, 'PO number is required.');
    $order = getOrderDetail($db, $poNumber);
    if (!$order) jsonResponse(false, 'Purchase order not found.');
    $db->prepare('UPDATE tbl_purchase_order SET Date_COA_Received = ? WHERE PO_Number = ?')
       ->execute([parseDate($data['date_coa_received']), $poNumber]);
    logAudit('UPDATE_PO', 'purchase_orders', "Set COA Date Received on PO {$poNumber}");
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    notifyPoUsers(
        'COA Date Received Updated',
        "{$actorName} updated COA Date Received on PO {$poNumber}",
        'purchase_orders.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber,
        'all'
    );
    jsonResponse(true, 'COA Date Received saved.');
}

if ($method === 'PATCH' && isset($data['date_approved_po'])) {
    $poNumber = trim($data['po_number'] ?? '');
    if (!$poNumber) jsonResponse(false, 'PO number is required.');
    $order = getOrderDetail($db, $poNumber);
    if (!$order) jsonResponse(false, 'Purchase order not found.');
    $db->prepare('UPDATE tbl_purchase_order SET Date_Approved_PO = ? WHERE PO_Number = ?')
       ->execute([parseDate($data['date_approved_po']), $poNumber]);
    logAudit('UPDATE_PO', 'purchase_orders', "Set Approved PO Date on PO {$poNumber}");
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    notifyPoUsers(
        trim($data['date_approved_po']) !== '' ? 'Purchase Order Approved' : 'Approved PO Date Cleared',
        trim($data['date_approved_po']) !== ''
            ? "{$actorName} marked PO {$poNumber} as approved"
            : "{$actorName} cleared the Approved PO Date on PO {$poNumber}",
        'purchase_orders.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber,
        'all'
    );
    jsonResponse(true, 'Approved PO Date saved.');
}

if ($method === 'PATCH' && isset($data['remarks'])) {
    $poNumber = trim($data['po_number'] ?? '');
    if (!$poNumber) jsonResponse(false, 'PO number is required.');
    $order = getOrderDetail($db, $poNumber);
    if (!$order) jsonResponse(false, 'Purchase order not found.');
    $db->prepare('UPDATE tbl_purchase_order SET Remarks = ? WHERE PO_Number = ?')
       ->execute([trim($data['remarks']), $poNumber]);
    logAudit('UPDATE_PO', 'purchase_orders', "Updated Remarks on PO {$poNumber}");
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    notifyPoUsers(
        'Remarks Updated',
        "{$actorName} updated Remarks on PO {$poNumber}",
        'purchase_orders.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber
    );
    jsonResponse(true, 'Remarks saved.');
}

// Manual Status override — sits on top of the automatic Pending/Approved
// badge (see setModalStatusBadge()/formatRow() in purchase_orders.php).
// Blank clears the override and returns the badge to that automatic logic.
if ($method === 'PATCH' && isset($data['status'])) {
    $poNumber = trim($data['po_number'] ?? '');
    if (!$poNumber) jsonResponse(false, 'PO number is required.');
    $order = getOrderDetail($db, $poNumber);
    if (!$order) jsonResponse(false, 'Purchase order not found.');
    $status = trim($data['status']);
    $db->prepare('UPDATE tbl_purchase_order SET Status = ? WHERE PO_Number = ?')
       ->execute([$status !== '' ? $status : null, $poNumber]);
    logAudit('UPDATE_PO', 'purchase_orders', ($status !== '' ? "Set Status to \"{$status}\"" : 'Cleared Status') . " on PO {$poNumber}");
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    notifyPoUsers(
        $status !== '' ? "PO Status: {$status}" : 'PO Status Cleared',
        $status !== '' ? "{$actorName} marked PO {$poNumber} as {$status}" : "{$actorName} cleared the Status on PO {$poNumber}",
        'purchase_orders.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber,
        'all'
    );
    jsonResponse(true, 'Status saved.');
}

// Note: the Change-of-Supplier letter (purchase_orders.php's "Print Letter"
// button) is intentionally stateless — nothing about it is saved here. It
// reads the PO's current Supplier Name and item list straight from the
// detail GET above and builds the printable letter client-side; the actual
// supplier change is just an edit to Supplier Name via the normal PUT below.

jsonResponse(false, 'Method not allowed.');
