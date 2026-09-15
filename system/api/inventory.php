<?php
// Inventory & Property API.
//
// Mirrors vb/Inventory/CGSO_Inventory.vb and CGSO_PO_Select.vb: an inventory
// record is a snapshot of one purchase order's items, brought in for property
// accountability (PAR/ICS custody) once they have arrived — a step that only
// applies to durable/capitalized goods, not consumables, which is why only
// ~12% of purchase orders ever go through this module (confirmed against live
// data before writing a line of this). It runs ALONGSIDE Inspection &
// Acceptance, not instead of it: a purchase order is commonly in both.
//
// Same versioning convention as tbl_inspection / tbl_purchase_order elsewhere
// in this app: a revision does not UPDATE the header or item rows, it soft-
// deletes the current ones and inserts new ones, so every earlier version
// stays queryable. tbl_inventory and tbl_inventory_items_details are linked
// by PO_Number only — each keeps its own Inv_ID sequence, exactly as the VB
// source does (the header's Inv_ID is never referenced anywhere outside its
// own table).
//
// What this file does NOT do: generate or reprint a PAR/ICS/ITR/PTR number,
// or record a transfer. That is api/inventory_issuance.php — kept separate
// because numbering has to be transactional in a way plain item edits do not.
require_once '../config.php';
requireAuth();
requireModule('inventory');
header('Content-Type: application/json');
$db = getPoDB();
$method = $_SERVER['REQUEST_METHOD'];

function invParseDate($value) {
    if (!$value) return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

// ── PAR / ICS classification ─────────────────────────────────────────────
//
// Same three cutoffs the VB source uses (₱5,000 and ₱49,001) — confirmed
// against live data to be this office's real, deliberate cutoffs (PAR items
// on file start at ₱49,450, not the textbook COA ₱50,000). What changes is
// that the legacy's penny-wide gap — a unit cost of exactly ₱49,000.00
// satisfied neither "< 49000.00" nor ">= 49001.01" and fell through to a
// literal "ERROR" category, which really happened to 3 live items — is
// closed. Every value now lands in exactly one bucket.
function invClassify($unitCost): string {
    $c = (float)$unitCost;
    if ($c < 5000)  return 'ICS LOW VALUE';
    if ($c < 49001) return 'ICS HIGH VALUE';
    return 'PAR';
}

// A heading row carried over from the purchase order — "SET 1", "LOT 1" — has
// no unit, quantity or cost against it there either, so it is not a stocked
// item here: it cannot be classified, can never be issued a PAR/ICS number,
// and should not count toward "how many lines is this PO waiting to issue."
// Same "blank" test purchase_orders.php's item grid already uses to tell a
// title row from a real one (see invalidItemRowNumbers() in
// api/purchase_orders.php) — kept identical on purpose so the two modules
// never disagree about which rows are labels.
function invIsTitleRow($unit, $qty, $unitCost, $totalCost): bool {
    return trim((string)$unit) === ''
        && (float)$qty == 0 && (float)$unitCost == 0 && (float)$totalCost == 0;
}

// SQL form of invIsTitleRow(), negated — for queries that count/sum
// tbl_inventory_items_details rows aliased `d` and must not count a title
// row as a stocked item.
const INV_NOT_TITLE_ROW_SQL =
    "NOT (TRIM(COALESCE(d.Unit,'')) = '' AND COALESCE(d.Quantity,0) = 0
          AND COALESCE(d.Unit_Cost,0) = 0 AND COALESCE(d.Total_Cost,0) = 0)";

// ── Pull a purchase order's items in, ready to stock in ─────────────────
// Same SELECT CGSO_PO_Select.vb runs on double-clicking a PO. Each item is
// pre-classified so the operator sees where it will land before anything is
// saved — the VB left this to a CellEndEdit handler that only ever looked at
// the LAST selected row, so opening a multi-item PO left every row but one
// unclassified until clicked individually. All rows are classified here.
// A search over purchase orders, scoped to this file rather than reused from
// api/purchase_orders.php on purpose: that endpoint requires
// requireModule('purchase_orders'), and an Inventory-only account (the real,
// distinct HRMIS flag confirmed earlier) must be able to find a PO to receive
// without also holding procurement access. Read-only, five columns, capped —
// nothing that endpoint's own broader access rule needs to guard.
function invSearchPO(PDO $db, string $term): array {
    $stmt = $db->prepare(
        "SELECT PO_Number AS po_number, PR_Number AS pr_number, Supplier_Name AS supplier_name,
                Department AS department, Purpose AS purpose
         FROM tbl_purchase_order
         WHERE isDeleted = 0 AND (PO_Number LIKE ? OR PR_Number LIKE ? OR Supplier_Name LIKE ? OR Department LIKE ?)
         ORDER BY Date_Created DESC LIMIT 20"
    );
    $like = '%' . $term . '%';
    $stmt->execute([$like, $like, $like, $like]);
    return fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function invPreviewPO(PDO $db, string $poNumber): ?array {
    // No Source_of_Fund here on purpose: the purchase order carries no such
    // column, and CGSO_PO_Select.vb never populated it either — it is filled
    // in by hand at Inventory intake, same as the legacy form's txtSource.
    $stmt = $db->prepare(
        "SELECT PO_Number, PR_Number, Department, Division, Section, Supplier_Name,
                Supplier_Address, Category, Mode_of_Procurement, Date_PO, Purpose
         FROM tbl_purchase_order WHERE PO_Number = ? AND isDeleted = 0"
    );
    $stmt->execute([$poNumber]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$po) return null;

    $stmt = $db->prepare(
        "SELECT Item_ID, Items_Description, Unit, Quantity, Unit_Cost, Total_Cost, NumberSeries
         FROM tbl_purchase_order_items_details WHERE PO_Number = ? AND isDeleted = 0 ORDER BY ID"
    );
    $stmt->execute([$poNumber]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$it) {
        $isTitle = invIsTitleRow($it['Unit'], $it['Quantity'], $it['Unit_Cost'], $it['Total_Cost']);
        $it['is_title']          = $isTitle;
        $it['inventory_category'] = $isTitle ? '' : invClassify($it['Unit_Cost']);
    }
    unset($it);

    return fixLegacyEncodingDeep([
        'po_number'           => $po['PO_Number'],
        'pr_number'           => $po['PR_Number'],
        'department'          => $po['Department'],
        'division'            => $po['Division'],
        'section'             => $po['Section'],
        'supplier_name'       => $po['Supplier_Name'],
        'supplier_address'    => $po['Supplier_Address'],
        'category'            => $po['Category'],
        'mode_of_procurement' => $po['Mode_of_Procurement'],
        'po_date'             => $po['Date_PO'],
        'purpose'             => $po['Purpose'],
        'source_of_fund'      => $po['Source_of_Fund'],
        'items'               => $items,
    ]);
}

// ── Worklist ──────────────────────────────────────────────────────────────
function invGetList(PDO $db): array {
    $search = trim($_GET['search'] ?? '');
    $from   = $_GET['from'] ?? '';
    $to     = $_GET['to']   ?? '';
    $status = strtolower(trim($_GET['status'] ?? ''));
    $params = [];

    // One row per live inventory header, with the item count / total value /
    // "has this been issued a PAR or ICS yet" folded in as correlated
    // subqueries — the same shape api/inspection.php uses for its billing
    // count, for the same reason: a LEFT JOIN onto the item-detail table
    // would multiply the header rows.
    //
    // item_count/issued_count both exclude title rows (INV_NOT_TITLE_ROW_SQL)
    // — a row like 'SET 1' can never be issued a PAR/ICS number, so it must
    // not inflate the "how many lines are left to issue" denominator.
    $sql = "SELECT ti.Inv_ID AS header_id, ti.PO_Number AS po_number, ti.PR_Number AS pr_number,
                   ti.Department AS department, ti.Supplier_Name AS supplier_name,
                   ti.Category AS category, ti.Purpose AS purpose, ti.PO_Date AS po_date,
                   ti.Date_Entry AS date_entry, ti.Date_Created AS date_created,
                   ti.Is_Manual AS is_manual,
                   (SELECT COUNT(*) FROM tbl_inventory_items_details d
                     WHERE d.PO_Number = ti.PO_Number AND d.isDeleted = '0'
                       AND " . INV_NOT_TITLE_ROW_SQL . ") AS item_count,
                   (SELECT COALESCE(SUM(d.Total_Cost), 0) FROM tbl_inventory_items_details d
                     WHERE d.PO_Number = ti.PO_Number AND d.isDeleted = '0') AS total_value,
                   (SELECT COUNT(*) FROM tbl_inventory_items_details d
                     WHERE d.PO_Number = ti.PO_Number AND d.isDeleted = '0'
                       AND d.PAR_ICS_Number IS NOT NULL AND d.PAR_ICS_Number <> ''
                       AND " . INV_NOT_TITLE_ROW_SQL . ") AS issued_count
            FROM tbl_inventory ti
            WHERE ti.isDeleted = '0'";

    if ($from) { $sql .= ' AND ti.Date_Created >= ?'; $params[] = date('Y-m-d 00:00:00', strtotime($from)); }
    if ($to)   { $sql .= ' AND ti.Date_Created <= ?'; $params[] = date('Y-m-d 23:59:59', strtotime($to)); }
    if ($search !== '') {
        $sql .= ' AND (ti.PO_Number LIKE ? OR ti.PR_Number LIKE ? OR ti.Department LIKE ?
                       OR ti.Supplier_Name LIKE ? OR ti.Purpose LIKE ?)';
        $term = '%' . $search . '%';
        $params = array_merge($params, array_fill(0, 5, $term));
    }
    $sql .= ' ORDER BY ti.Date_Created DESC LIMIT 500';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['item_count']    = (int)$r['item_count'];
        $r['issued_count']  = (int)$r['issued_count'];
        $r['total_value']   = (float)$r['total_value'];
        $r['is_manual']     = (bool)$r['is_manual'];
        // "Awaiting issuance" = at least one line still has no PAR/ICS number.
        // Confirmed against live data this is common (up to 84% of ICS LOW
        // VALUE lines) — a real backlog, not an edge case, so it is a first-
        // class, filterable state rather than something the operator has to
        // notice on their own.
        $r['awaiting_issuance'] = $r['issued_count'] < $r['item_count'];
    }
    unset($r);

    $counts = ['total' => count($rows), 'awaiting' => 0, 'issued' => 0];
    foreach ($rows as $r) {
        $r['awaiting_issuance'] ? $counts['awaiting']++ : $counts['issued']++;
    }

    if ($status === 'awaiting') {
        $rows = array_values(array_filter($rows, fn($r) => $r['awaiting_issuance']));
    } elseif ($status === 'issued') {
        $rows = array_values(array_filter($rows, fn($r) => !$r['awaiting_issuance']));
    }

    return ['records' => fixLegacyEncodingDeep($rows), 'counts' => $counts];
}

// ── Detail ────────────────────────────────────────────────────────────────
function invGetDetail(PDO $db, string $poNumber): ?array {
    $stmt = $db->prepare(
        "SELECT Inv_ID AS header_id, PO_Number AS po_number, PR_Number AS pr_number,
                Department AS department, Category AS category, Mode_of_Procurement AS mode_of_procurement,
                Supplier_Name AS supplier_name, Supplier_Address AS supplier_address,
                Purpose AS purpose, Source_of_Fund AS source_of_fund,
                PO_Date AS po_date, Date_Entry AS date_entry, Date_Created AS date_created,
                Initial AS initial, Is_Manual AS is_manual
         FROM tbl_inventory WHERE PO_Number = ? AND isDeleted = '0' ORDER BY Inv_ID DESC LIMIT 1"
    );
    $stmt->execute([$poNumber]);
    $header = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$header) return null;
    $header['is_manual'] = (bool)$header['is_manual'];

    $stmt = $db->prepare(
        "SELECT Inv_ID AS inv_id, Item_ID AS item_id, Items_Description AS items_description,
                Unit AS unit, Quantity AS quantity, Unit_Cost AS unit_cost, Total_Cost AS total_cost,
                Tag_Number AS tag_number, Serial_Number AS serial_number,
                Inventory_Category AS inventory_category, PAR_ICS_Number AS par_ics_number,
                Accountable_Officer AS accountable_officer, Position AS position, Location AS location,
                Classification AS classification, Remarks AS remarks, `Condition` AS `condition`,
                NumberSeries AS number_series, Fund_Cluster AS fund_cluster,
                Date_Transfered AS date_transferred, PTR_ITR_Number AS ptr_itr_number,
                Estimated_Life AS estimated_life
         FROM tbl_inventory_items_details WHERE PO_Number = ? AND isDeleted = '0' ORDER BY Inv_ID"
    );
    $stmt->execute([$poNumber]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sub-details (serialized breakdown) for every item line in one query,
    // grouped in PHP — same "one round trip regardless of how many lines"
    // shape api/inspection_progress.php uses for its billing items.
    $subByInv = [];
    if ($items) {
        $ids = array_column($items, 'inv_id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT sub_ID AS sub_id, Inv_ID AS inv_id, Items_Description AS items_description,
                    Unit_Cost AS unit_cost, Serial_Number AS serial_number, Tag_Number AS tag_number,
                    Accountable_Officer AS accountable_officer, Location AS location,
                    Remarks AS remarks, `Condition` AS `condition`, Date_Entry AS date_entry
             FROM tbl_inventory_sub_details WHERE Inv_ID IN ($in) AND isDeleted = 0 ORDER BY sub_ID"
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) $subByInv[(int)$s['inv_id']][] = $s;
    }
    foreach ($items as &$it) {
        $it['sub_details'] = $subByInv[(int)$it['inv_id']] ?? [];
    }
    unset($it);

    // What an undo would restore, same lookup shape as inspection's — named so
    // the confirmation dialog can say exactly what pressing it will do.
    $prev = $db->prepare(
        "SELECT Date_Created AS date_created FROM tbl_inventory
         WHERE PO_Number = ? AND isDeleted = '1' AND Inv_ID < ? ORDER BY Inv_ID DESC LIMIT 1"
    );
    $prev->execute([$poNumber, $header['header_id']]);
    $previous = $prev->fetch(PDO::FETCH_ASSOC) ?: null;

    return fixLegacyEncodingDeep([
        'header'   => $header,
        'items'    => $items,
        'exists'   => true,
        'previous' => $previous,
    ]);
}

// Everything currently in ONE person's custody, across every PO — the
// Personnel Memorandum Receipt. Every other read in this file scopes by
// PO_Number/Inv_ID; this is the first one scoped by Accountable_Officer
// instead (see database/inventory_officer_index.sql for the index this
// needs). Combines both tables that carry that column: line-item rows on
// tbl_inventory_items_details, and — separately — individually-tracked
// UNITS on tbl_inventory_sub_details, because a person can be custodian of
// just one unit out of a multi-unit line rather than the whole line. A
// sub-detail row has no Quantity/Total_Cost/Unit of its own (it IS one
// physical unit; qty is implicitly 1), so those are synthesised here rather
// than left null, and Unit is inherited from the parent line.
function invMemorandumReceipt(PDO $db, string $officer): array {
    $officer = trim($officer);
    if ($officer === '') return ['rows' => [], 'total_value' => 0];

    $stmt = $db->prepare(
        "SELECT d.Inv_ID AS row_id, d.PO_Number AS po_number, d.Items_Description AS items_description,
                d.Unit AS unit, d.Quantity AS quantity, d.Unit_Cost AS unit_cost, d.Total_Cost AS total_cost,
                d.Tag_Number AS tag_number, d.Serial_Number AS serial_number, d.Position AS position,
                d.Location AS location, d.`Condition` AS `condition`, d.Date_Entry AS date_entry,
                h.Department AS department, 'line' AS source
         FROM tbl_inventory_items_details d
         INNER JOIN tbl_inventory h ON h.PO_Number = d.PO_Number AND h.isDeleted = '0'
         WHERE d.Accountable_Officer = ? AND d.isDeleted = '0'"
    );
    $stmt->execute([$officer]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare(
        "SELECT s.sub_ID AS row_id, s.PO_Number AS po_number, s.Items_Description AS items_description,
                p.Unit AS unit, 1 AS quantity, s.Unit_Cost AS unit_cost, s.Unit_Cost AS total_cost,
                s.Tag_Number AS tag_number, s.Serial_Number AS serial_number, NULL AS position,
                s.Location AS location, s.`Condition` AS `condition`, s.Date_Entry AS date_entry,
                h.Department AS department, 'unit' AS source
         FROM tbl_inventory_sub_details s
         INNER JOIN tbl_inventory h ON h.PO_Number = s.PO_Number AND h.isDeleted = '0'
         LEFT JOIN tbl_inventory_items_details p ON p.Inv_ID = s.Inv_ID
         WHERE s.Accountable_Officer = ? AND s.isDeleted = 0"
    );
    $stmt->execute([$officer]);
    $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));

    usort($rows, fn($a, $b) => strcmp((string)($b['date_entry'] ?? ''), (string)($a['date_entry'] ?? '')));
    $total = array_sum(array_map(fn($r) => (float)$r['total_cost'], $rows));
    // Position/Department aren't asked for separately — they ride along on
    // the item rows (Position only exists on tbl_inventory_items_details, not
    // the sub-detail table), so the most recent row's is offered as a
    // reasonable default for the printed header rather than left for the
    // caller to dig out itself.
    $first = $rows[0] ?? [];

    return fixLegacyEncodingDeep([
        'rows' => $rows,
        'total_value' => $total,
        'position' => $first['position'] ?? '',
        'department' => $first['department'] ?? '',
    ]);
}

// ── Master lists this screen needs ───────────────────────────────────────
function invSignatories(PDO $db): array {
    // Accountable-officer suggestions: every HRMIS personnel name, same
    // source sourceload() in the VB uses.
    $officers = $db->query(
        "SELECT CONCAT(firstname,' ',LEFT(middlename,1),'. ',surname) AS name
         FROM hrmis.tblpersonalinformation
         WHERE CONCAT(firstname,' ',LEFT(middlename,1),'. ',surname) IS NOT NULL
         ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);

    $positions = $db->query(
        "SELECT DISTINCT Position FROM hrmis.tbl_syl_payroll_parent
         WHERE YEAR = " . (int)date('Y') . " AND Position IS NOT NULL AND Position <> ''
         ORDER BY Position"
    )->fetchAll(PDO::FETCH_COLUMN);

    // Location suggestions: ref_department already carries every city office
    // AND every barangay (47 of them) — confirmed live, so nothing new is
    // needed here; Location on an inventory item is "which office or
    // barangay currently holds this", and that is exactly what this list is.
    $locations = $db->query(
        "SELECT Department FROM ref_department WHERE isDeleted = 0 ORDER BY Department"
    )->fetchAll(PDO::FETCH_COLUMN);

    // The CGSO officer who signs every PAR/ICS/Property Card. The legacy VB
    // hardcodes ref_signaturies.id = 6; here the same row is found by its
    // title instead, so replacing whoever holds that post is a Master Data
    // edit, not a code change — the same rule the Disbursement Voucher's
    // three officers already use. Returned as a suggestion, not locked: the
    // field stays editable, exactly like every other signatory field in this
    // app.
    $cgso = $db->query(
        "SELECT Signaturies AS name, Position AS position FROM ref_signaturies
         WHERE isDeleted = 0 AND Position LIKE '%CGSO%DESIGNATE%' LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    // Everyone who might sign as CGSO Officer / Approved-by / Issued-by on a
    // transfer — the same CGSO office roster used elsewhere in this app.
    $cgsoStaff = $db->query(
        "SELECT Signaturies AS name, Position AS position FROM ref_signaturies
         WHERE isDeleted = 0 AND Department = 'CITY GENERAL SERVICES OFFICE'
         ORDER BY Signaturies"
    )->fetchAll(PDO::FETCH_ASSOC);

    $classifications = $db->query(
        "SELECT Classification FROM ref_classification
         WHERE isDeleted = 0 AND TRIM(COALESCE(Classification,'')) <> '' ORDER BY Classification"
    )->fetchAll(PDO::FETCH_COLUMN);

    return fixLegacyEncodingDeep([
        'officers'        => array_values(array_unique($officers)),
        'positions'       => $positions,
        'locations'       => $locations,
        'cgso_officer'    => $cgso ?: null,
        'cgso_staff'      => $cgsoStaff,
        'classifications' => $classifications,
        // Free text on the live column, but stable in practice — 7 distinct
        // values across the whole table — offered as a picklist that stays
        // editable, the same treatment Condition gets nowhere near a rework
        // of the column itself.
        'conditions'      => ['IN GOOD CONDITION', 'NEEDING REPAIR', 'DAMAGE', 'UNSERVICEABLE',
                               'OBSOLETE', 'NO LONGER NEEDED', 'STOLEN'],
    ]);
}

function invStats(PDO $db): array {
    $s = [];
    $s['received_today'] = (int)$db->query(
        "SELECT COUNT(*) FROM tbl_inventory WHERE isDeleted = '0' AND DATE(Date_Created) = CURDATE()"
    )->fetchColumn();
    $s['received_month'] = (int)$db->query(
        "SELECT COUNT(*) FROM tbl_inventory WHERE isDeleted = '0'
          AND Date_Created >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    )->fetchColumn();
    $s['value_month'] = (float)$db->query(
        "SELECT COALESCE(SUM(d.Total_Cost), 0) FROM tbl_inventory_items_details d
         INNER JOIN tbl_inventory h ON h.PO_Number = d.PO_Number AND h.isDeleted = '0'
         WHERE d.isDeleted = '0' AND h.Date_Created >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    )->fetchColumn();
    // A title row can never be issued a PAR/ICS number, so it is not a "line
    // awaiting PAR/ICS" — see invIsTitleRow()'s note.
    $s['awaiting_issuance'] = (int)$db->query(
        "SELECT COUNT(*) FROM tbl_inventory_items_details d
         INNER JOIN tbl_inventory h ON h.PO_Number = d.PO_Number AND h.isDeleted = '0'
         WHERE d.isDeleted = '0' AND (d.PAR_ICS_Number IS NULL OR d.PAR_ICS_Number = '')
           AND " . INV_NOT_TITLE_ROW_SQL
    )->fetchColumn();
    return $s;
}

if ($method === 'GET') {
    if (isset($_GET['signatories'])) jsonResponse(true, '', invSignatories($db));
    if (isset($_GET['stats']))       jsonResponse(true, '', ['stats' => invStats($db)]);
    // A Code128 PNG for the Property Tag printout — same encoder
    // renderPoBarcodePng() already uses for the Purchase Order barcode
    // (lib/barcode128.php, loaded once in config.php). Barcodes the PAR/ICS
    // number, matching what Cells(11).Value fed fontencoder.Code128() in the
    // legacy tag button — the tag identifies which custody document covers
    // this property, not a separate property-number scheme.
    if (isset($_GET['barcode'])) {
        $value = poBarcodeValue($_GET['barcode']);
        jsonResponse(true, '', [
            'png'   => $value === '' ? '' : renderPoBarcodePng($_GET['barcode']),
            'width' => $value === '' ? 0 : poBarcodeDisplayWidth($_GET['barcode']),
        ]);
    }
    if (isset($_GET['search_po'])) {
        $term = trim($_GET['search_po']);
        jsonResponse(true, '', ['orders' => $term === '' ? [] : invSearchPO($db, $term)]);
    }
    if (isset($_GET['preview_po'])) {
        $preview = invPreviewPO($db, trim($_GET['preview_po']));
        if (!$preview) jsonResponse(false, 'Purchase order not found.');
        jsonResponse(true, '', ['preview' => $preview]);
    }
    if (isset($_GET['mr'])) {
        $officer = trim($_GET['officer'] ?? '');
        if ($officer === '') jsonResponse(false, 'Select a person first.');
        jsonResponse(true, '', invMemorandumReceipt($db, $officer));
    }

    $poNumber = trim($_GET['po_number'] ?? '');
    if ($poNumber !== '') {
        $detail = invGetDetail($db, $poNumber);
        if (!$detail) jsonResponse(false, 'This purchase order has not been received into Inventory.');
        jsonResponse(true, '', $detail);
    }
    $list = invGetList($db);
    jsonResponse(true, '', $list);
}

$data = getInput();

// ── Save (create or revise) ─────────────────────────────────────────────
// POST brings a purchase order into Inventory for the first time; PUT revises
// an existing intake. Both retire the current header + item rows and insert
// new ones — the identical soft-delete-then-reinsert sequence
// save_inventory_update() runs, kept so every earlier version stays on file.
//
// PAR_ICS_Number / PTR_ITR_Number are NOT part of this save: they are set
// only by api/inventory_issuance.php, transactionally, at the moment a number
// is actually generated — never guessed or carried over by hand here.
if ($method === 'POST' || $method === 'PUT') {
    // Manual entry — legacy property with no real PO behind it (see
    // inventory.php's "Add Manually" flow). Create only: a manual record's
    // PO_Number does not exist yet at this point (it is synthesized below,
    // after the header row has an id to derive it from), so there is
    // nothing to look up an existing record by, and nothing to revise from
    // — revising an already-manual record later is just a normal PUT, since
    // by then it has the same kind of PO_Number value any other record does.
    $isManual = $method === 'POST' && (bool)($data['manual'] ?? false);
    $poNumber = $isManual ? '' : trim($data['po_number'] ?? '');
    if (!$isManual && $poNumber === '') jsonResponse(false, 'Purchase order number is required.');

    $items = $data['items'] ?? [];
    if (!is_array($items) || !count($items)) jsonResponse(false, 'Add at least one item before saving.');

    foreach ($items as $i => $item) {
        if (trim((string)($item['items_description'] ?? '')) === '') {
            jsonResponse(false, 'Item ' . ($i + 1) . ' has no description.');
        }
        if (isset($item['unit_cost']) && $item['unit_cost'] !== '' && !is_numeric($item['unit_cost'])) {
            jsonResponse(false, 'Item ' . ($i + 1) . ' has an invalid unit cost.');
        }
    }

    $hasExisting = false;
    if (!$isManual) {
        $cur = $db->prepare("SELECT 1 FROM tbl_inventory WHERE PO_Number = ? AND isDeleted = '0'");
        $cur->execute([$poNumber]);
        $hasExisting = (bool)$cur->fetchColumn();
        if ($method === 'POST' && $hasExisting) {
            jsonResponse(false, 'This purchase order is already in Inventory. Use Revise instead.');
        }
        if ($method === 'PUT' && !$hasExisting) {
            jsonResponse(false, 'No inventory record exists yet for this purchase order.');
        }
    }

    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    $now       = date('Y-m-d H:i:s');
    $dateEntry = invParseDate($data['date_entry'] ?? null) ?: $now;
    $poDate    = invParseDate($data['po_date'] ?? null);

    try {
        $db->beginTransaction();

        if ($hasExisting) {
            $db->prepare("UPDATE tbl_inventory SET isDeleted = '1', Deleted_by = ? WHERE PO_Number = ? AND isDeleted = '0'")
               ->execute([$actorName, $poNumber]);
            $db->prepare("UPDATE tbl_inventory_items_details SET isDeleted = '1', Deleted_by = ? WHERE PO_Number = ? AND isDeleted = '0'")
               ->execute([$actorName, $poNumber]);
        }

        $db->prepare(
            "INSERT INTO tbl_inventory
                (PO_Number, PR_Number, Department, Category, Mode_of_Procurement, Supplier_Name,
                 Supplier_Address, Purpose, Source_of_Fund, PO_Date, Date_Entry, Date_Created,
                 End_User, Initial, isDeleted, Is_Manual)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '0', ?)"
        )->execute([
            $poNumber, trim($data['pr_number'] ?? ''), trim($data['department'] ?? ''),
            trim($data['category'] ?? ''), trim($data['mode_of_procurement'] ?? ''),
            trim($data['supplier_name'] ?? ''), trim($data['supplier_address'] ?? ''),
            trim($data['purpose'] ?? ''), trim($data['source_of_fund'] ?? ''),
            $poDate, $dateEntry, $now, $actorName, trim($data['initial'] ?? ''),
            $isManual ? 1 : 0,
        ]);

        // Derive the synthetic PO_Number from the header row's own id — same
        // "derive from the row's own reliable id" approach used elsewhere
        // this session (the PRS number, the PO barcode) — and stamp it back
        // onto the row that was just inserted. $poNumber is reassigned here
        // so the item insert loop right below uses the real, final value.
        if ($isManual) {
            $headerId = (int)$db->lastInsertId();
            $poNumber = 'MANUAL-' . $headerId;
            $db->prepare("UPDATE tbl_inventory SET PO_Number = ? WHERE Inv_ID = ?")
               ->execute([$poNumber, $headerId]);
        }

        $itemStmt = $db->prepare(
            "INSERT INTO tbl_inventory_items_details
                (PO_Number, Item_ID, Items_Description, Unit, Quantity, Unit_Cost, Total_Cost,
                 Tag_Number, Serial_Number, Inventory_Category, Accountable_Officer, Position,
                 Location, Classification, Remarks, `Condition`, NumberSeries, Estimated_Life,
                 Date_Entry, Date_Created, End_User, isDeleted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '0')"
        );
        foreach ($items as $item) {
            $qty  = ($item['quantity'] ?? '') === '' ? null : (float)$item['quantity'];
            $cost = ($item['unit_cost'] ?? '') === '' ? null : (float)$item['unit_cost'];
            // Recomputed server-side, never trusted from the client, same rule
            // every other item grid in this app enforces.
            $total = ($qty !== null && $cost !== null) ? round($qty * $cost, 2) : $cost;
            // Classified server-side too — every row, not just the one the
            // operator last clicked (the VB's CellEndEdit limitation) — except
            // a title row ('SET 1', a LOT divider), which stays unclassified:
            // it was never a priced item on the purchase order either, and
            // stamping it "ICS LOW VALUE" for having a $0 cost is exactly the
            // kind of item it isn't.
            $isTitle  = invIsTitleRow($item['unit'] ?? '', $qty ?? 0, $cost ?? 0, $total ?? 0);
            $category = $isTitle ? '' : invClassify($cost ?? 0);
            $itemStmt->execute([
                $poNumber,
                ($item['item_id'] ?? '') !== '' ? $item['item_id'] : null,
                trim($item['items_description'] ?? ''),
                trim($item['unit'] ?? ''),
                $qty, $cost, $total,
                trim($item['tag_number'] ?? ''), trim($item['serial_number'] ?? ''),
                $category,
                trim($item['accountable_officer'] ?? ''), trim($item['position'] ?? ''),
                trim($item['location'] ?? ''), trim($item['classification'] ?? ''),
                trim($item['remarks'] ?? ''), trim($item['condition'] ?? ''),
                ($item['number_series'] ?? '') !== '' ? $item['number_series'] : null,
                trim($item['estimated_life'] ?? ''),
                $dateEntry, $now, $actorName,
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        jsonResponse(false, 'Save failed: ' . $e->getMessage());
    }

    logAudit($hasExisting ? 'REVISE_INVENTORY' : 'CREATE_INVENTORY', 'inventory',
        ($hasExisting ? 'Revised' : 'Received') . " PO {$poNumber} into Inventory (" . count($items) . ' items)');
    notifyPoUsers(
        $hasExisting ? 'Inventory Record Revised' : 'Received into Inventory',
        "{$actorName} " . ($hasExisting ? 're-saved' : 'recorded') . " PO {$poNumber} in Inventory & Property",
        'inventory.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber, 'all'
    );
    jsonResponse(true, $hasExisting ? 'Inventory record updated.' : 'Received into Inventory.', ['po_number' => $poNumber]);
}

// ── Undo ──────────────────────────────────────────────────────────────────
// Same restore-or-remove behaviour as Inspection's undo: if an earlier
// version exists it comes back; otherwise the record is taken off Inventory
// entirely and the purchase order returns to "not yet received".
if ($method === 'PATCH' && ($data['action'] ?? '') === 'undo') {
    $poNumber = trim($data['po_number'] ?? '');
    if ($poNumber === '') jsonResponse(false, 'Purchase order number is required.');

    // Re-read fresh, in the same request that acts on it — never trust a copy
    // read earlier in the session, since someone else may be using this
    // record right now.
    $cur = $db->prepare(
        "SELECT Inv_ID FROM tbl_inventory WHERE PO_Number = ? AND isDeleted = '0' ORDER BY Inv_ID DESC LIMIT 1"
    );
    $cur->execute([$poNumber]);
    $currentId = $cur->fetchColumn();
    if (!$currentId) jsonResponse(false, 'No live inventory record exists for this purchase order.');

    $prev = $db->prepare(
        "SELECT Inv_ID, Date_Created FROM tbl_inventory
         WHERE PO_Number = ? AND isDeleted = '1' AND Inv_ID < ? ORDER BY Inv_ID DESC LIMIT 1"
    );
    $prev->execute([$poNumber, $currentId]);
    $previous = $prev->fetch(PDO::FETCH_ASSOC);

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE tbl_inventory SET isDeleted = '1' WHERE Inv_ID = ?")->execute([$currentId]);
        $db->prepare("UPDATE tbl_inventory_items_details SET isDeleted = '1' WHERE PO_Number = ? AND isDeleted = '0'")
           ->execute([$poNumber]);

        if ($previous) {
            $db->prepare("UPDATE tbl_inventory SET isDeleted = '0' WHERE Inv_ID = ?")->execute([$previous['Inv_ID']]);
            // Items were retired by Date_Created + PO_Number, matching the
            // header version being restored — the same scoping rule
            // inspection's undo uses, and for the same reason: an item row
            // has no back-reference to which header version it belongs to
            // other than sharing that timestamp.
            $db->prepare(
                "UPDATE tbl_inventory_items_details SET isDeleted = '0'
                 WHERE PO_Number = ? AND isDeleted = '1' AND Date_Created = ?"
            )->execute([$poNumber, $previous['Date_Created']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        jsonResponse(false, 'Undo failed: ' . $e->getMessage());
    }

    $restored = (bool)$previous;
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    logAudit('UNDO_INVENTORY', 'inventory', ($restored
        ? "Undid the last revision on PO {$poNumber}, restoring the version of {$previous['Date_Created']}"
        : "Removed PO {$poNumber} from Inventory"));
    notifyPoUsers(
        $restored ? 'Inventory Revision Undone' : 'Removed from Inventory',
        $restored
            ? "{$actorName} undid the last revision on PO {$poNumber} in Inventory"
            : "{$actorName} removed PO {$poNumber} from Inventory — it is awaiting receipt again",
        'inventory.php?po_number=' . urlencode($poNumber) . ($restored ? '&view=1' : ''),
        $poNumber, 'all'
    );
    jsonResponse(true, $restored ? 'Undone. The previous version has been restored.'
                                  : 'Undone. This purchase order is off Inventory.',
        ['restored' => $restored]);
}

// ── Sub-details (serialized breakdown) ───────────────────────────────────
// One inventory line (one Inv_ID) can cover several physically distinct
// units — five identical desktop computers under one PO line, say — each
// with its own serial number, tag and, in principle, its own custodian. This
// replaces CGSO_Inventory_Sub_Details.vb's save_subinventory() /
// update_subinventory(), same soft-delete-then-reinsert versioning.
if ($method === 'PATCH' && ($data['action'] ?? '') === 'sub_details') {
    $invId = (int)($data['inv_id'] ?? 0);
    $poNumber = trim($data['po_number'] ?? '');
    if (!$invId || $poNumber === '') jsonResponse(false, 'Item is required.');

    $rows = $data['rows'] ?? [];
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    $now = date('Y-m-d H:i:s');

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE tbl_inventory_sub_details SET isDeleted = 1 WHERE Inv_ID = ? AND isDeleted = 0")
           ->execute([$invId]);

        if (is_array($rows) && count($rows)) {
            $stmt = $db->prepare(
                "INSERT INTO tbl_inventory_sub_details
                    (Inv_ID, PO_Number, Items_Description, Unit_Cost, Serial_Number, Tag_Number,
                     Accountable_Officer, Location, Remarks, `Condition`, Date_Entry, Date_Created,
                     End_User, isDeleted)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
            );
            foreach ($rows as $r) {
                $stmt->execute([
                    $invId, $poNumber,
                    trim($r['items_description'] ?? ''),
                    ($r['unit_cost'] ?? '') !== '' ? (float)$r['unit_cost'] : null,
                    trim($r['serial_number'] ?? ''), trim($r['tag_number'] ?? ''),
                    trim($r['accountable_officer'] ?? ''), trim($r['location'] ?? ''),
                    trim($r['remarks'] ?? ''), trim($r['condition'] ?? ''),
                    $now, $now, $actorName,
                ]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        jsonResponse(false, 'Save failed: ' . $e->getMessage());
    }

    logAudit('UPDATE_INVENTORY_SUB_DETAILS', 'inventory', "Updated the unit breakdown for Inv_ID {$invId} (PO {$poNumber})");
    jsonResponse(true, 'Unit breakdown saved.', ['inv_id' => $invId]);
}

jsonResponse(false, 'Unsupported request.');
