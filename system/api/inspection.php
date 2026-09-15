<?php
// Inspection & Acceptance API.
//
// Mirrors vb/Inspection and Acceptance/Inspect.vb, with three deliberate
// departures from the legacy behaviour, each noted at the point it happens:
//   1. validation runs BEFORE anything is soft-deleted (the VB retired the old
//      rows first and then aborted on a failed check, destroying the record);
//   2. the whole save is one transaction (the VB ran six unwrapped statements);
//   3. failures surface (the VB caught every exception into an empty block and
//      still reported "Successfully saved!").
require_once '../config.php';
requireAuth();
requireModule('inspection');
header('Content-Type: application/json');
$db = getPoDB();
$method = $_SERVER['REQUEST_METHOD'];

function inspParseDate($value) {
    if (!$value) return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

// ── Worklist ────────────────────────────────────────────────────────────────
// Lists purchase orders, not inspections — matching Inspection_Main_Form, where
// the operator starts from a delivered PO and inspects it. The LEFT JOIN tells
// each row whether an inspection already exists.
//
// Note the join is deliberately LEFT: POIAR Reports.vb in the legacy source has
// a LEFT JOIN whose isDeleted filter sits in the WHERE clause, silently turning
// it into an inner join and dropping every un-inspected PO. Keeping the filter
// in the ON clause is what preserves that.
function inspGetList(PDO $db) {
    $search = trim($_GET['search'] ?? '');
    $from   = $_GET['from'] ?? '';
    $to     = $_GET['to'] ?? '';
    $params = [];

    $sql = "SELECT po.PO_Number AS po_number, po.PR_Number AS pr_number,
                   po.Department AS department, po.Supplier_Name AS supplier_name,
                   po.Supplier_Address AS supplier_address, po.Purpose AS purpose,
                   po.Date_Created AS date_created, po.Inspect AS inspect_flag,
                   po.PO_Payment_Term AS payment_term,
                   -- How many progress billings are live on this PO. A correlated
                   -- subquery rather than another LEFT JOIN: joining a
                   -- one-to-many table onto this list would multiply the PO rows,
                   -- and the list is what the counts are computed from.
                   (SELECT COUNT(*) FROM tbl_inspection_progress_billing pb
                     WHERE pb.PO_Number = po.PO_Number AND pb.isDeleted = 0
                       AND pb.Billing_No IS NOT NULL) AS billing_count,
                   ti.I_ID AS inspection_id, ti.Invoice_Number AS invoice_number,
                   ti.Date_BAC_Received AS date_bac_received, ti.Remarks AS remarks,
                   ti.Date_Inspected AS date_inspected, ti.Accepted_by AS accepted_by
            FROM tbl_purchase_order po
            LEFT JOIN tbl_inspection ti
                   ON ti.PO_Number = po.PO_Number AND ti.isDeleted = 0
            WHERE po.isDeleted = 0 AND TRIM(po.PO_Number) <> ''";

    if ($from) { $sql .= ' AND po.Date_Created >= ?'; $params[] = date('Y-m-d 00:00:00', strtotime($from)); }
    if ($to)   { $sql .= ' AND po.Date_Created <= ?'; $params[] = date('Y-m-d 23:59:59', strtotime($to)); }
    if ($search !== '') {
        // Remarks is searchable too, now that it is a column people can see:
        // a note is only worth writing if it can be found again.
        $sql .= ' AND (po.PO_Number LIKE ? OR po.PR_Number LIKE ? OR po.Department LIKE ?
                       OR po.Supplier_Name LIKE ? OR po.Purpose LIKE ? OR ti.Invoice_Number LIKE ?
                       OR ti.Remarks LIKE ?)';
        $term = '%' . $search . '%';
        $params = array_merge($params, array_fill(0, 7, $term));
    }
    $sql .= ' ORDER BY po.Date_Created DESC LIMIT 500';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['inspected'] = !empty($r['inspection_id']);
        // Progress billing is decided by the PO's own Payment Term, so nothing
        // has to be flagged by hand. A PO that already carries billings stays
        // in the flow even if someone later edits that term.
        $r['billing_count']    = (int)$r['billing_count'];
        $r['progress_billing'] = isProgressBillingTerm($r['payment_term']) || $r['billing_count'] > 0;
        // "Awaiting" means nobody has accounted for this delivery yet — and a
        // progress billing accounts for it just as much as a whole-order
        // inspection does. Without this, a purchase order billed in tranches
        // reads "Awaiting" beside "Progress Billing Completed", because
        // `inspected` only ever meant "has a row in tbl_inspection" and
        // tranches are not written there.
        //
        // Computed once, here, so the badge, the chips and the filter cannot
        // give three different answers about the same purchase order.
        $r['awaiting'] = !$r['inspected'] && $r['billing_count'] === 0;
    }
    unset($r);
    inspAttachBillingProgress($db, $rows);
    $rows = fixLegacyEncodingDeep($rows);

    // Counted off the rows already fetched, like the PO list, so the chips and
    // the table can never disagree.
    $counts = ['total' => count($rows), 'inspected' => 0, 'pending' => 0, 'progress_billing' => 0];
    foreach ($rows as $r) {
        if ($r['inspected']) $counts['inspected']++;
        if ($r['awaiting'])  $counts['pending']++;
        if ($r['progress_billing']) $counts['progress_billing']++;
    }
    // Inspected + Awaiting no longer has to equal Total, and that is correct: a
    // purchase order part-way through its billings is neither. It is counted
    // under Progress Billing, which was already a separate lens rather than a
    // slice of the other two — an inspected progress-billing PO has always
    // counted in both.

    $status = strtolower(trim($_GET['status'] ?? ''));
    if ($status === 'inspected') {
        $rows = array_values(array_filter($rows, fn($r) => $r['inspected']));
    } elseif ($status === 'pending') {
        $rows = array_values(array_filter($rows, fn($r) => $r['awaiting']));
    } elseif ($status === 'progress_billing') {
        $rows = array_values(array_filter($rows, fn($r) => $r['progress_billing']));
    }
    return ['orders' => $rows, 'counts' => $counts];
}

/* How far along each progress-billing purchase order is, for the worklist badge.
 *
 * Two grouped queries rather than correlated subqueries in the main SELECT:
 * tbl_purchase_order_items_details holds 311,759 rows, and asking it once per
 * listed purchase order would be 500 lookups to fill in a badge. Restricted to
 * the progress-billing rows only — typically ~100 of the 500 — and both tables
 * are hit by an index on PO_Number, so the pair measured 5.8 ms together.
 *
 * Ordered amounts come from the PO's own lines, exactly as the billing dialog
 * does it, so the badge and the dialog are reading the same two numbers.
 */
function inspAttachBillingProgress(PDO $db, array &$rows): void {
    $pos = [];
    foreach ($rows as $r) if (!empty($r['progress_billing'])) $pos[] = $r['po_number'];
    $pos = array_values(array_unique($pos));

    $ordered = $billed = [];
    if ($pos) {
        $in = implode(',', array_fill(0, count($pos), '?'));
        $q = $db->prepare("SELECT PO_Number, SUM(Total_Cost) t FROM tbl_purchase_order_items_details
                           WHERE PO_Number IN ($in) AND isDeleted = 0 GROUP BY PO_Number");
        $q->execute($pos);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $ordered[$r['PO_Number']] = (float)$r['t'];

        $q = $db->prepare("SELECT PO_Number, SUM(Total_Cost) t
                           FROM tbl_inspection_items_details_progress_billing
                           WHERE PO_Number IN ($in) AND isDeleted = 0 AND Billing_No IS NOT NULL
                           GROUP BY PO_Number");
        $q->execute($pos);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $billed[$r['PO_Number']] = (float)$r['t'];
    }

    foreach ($rows as &$r) {
        if (empty($r['progress_billing'])) { $r['billing_status'] = null; $r['billed_percent'] = null; continue; }
        $st = progressBillingStatus($ordered[$r['po_number']] ?? 0, $billed[$r['po_number']] ?? 0,
                                    (int)$r['billing_count']);
        $r['billing_status']  = $st['status'];
        $r['billed_percent']  = $st['percent'];
    }
    unset($r);
}
// ── One PO, ready to inspect or to view ─────────────────────────────────────
// When an inspection exists its own saved lines are returned; otherwise the
// PO's lines are handed over as the starting point, which is what
// Inspect.vb's form_load_items() does.
function inspGetDetail(PDO $db, string $poNumber) {
    $stmt = $db->prepare(
        'SELECT PO_Number AS po_number, PR_Number AS pr_number, Department AS department,
                Supplier_Name AS supplier_name, Supplier_Address AS supplier_address,
                Purpose AS purpose, Date_Created AS date_created, Inspect AS inspect_flag,
                Category AS po_category, PO_Payment_Term AS payment_term,
                -- The Delivery Term. Named PO_Working_Days on this table, and
                -- shown as "Delivery Term" everywhere a human sees it — the
                -- Date of Receipt suggestion is derived from it.
                PO_Working_Days AS delivery_term, Date_PO AS date_po
         FROM tbl_purchase_order WHERE PO_Number = ? AND isDeleted = 0'
    );
    $stmt->execute([$poNumber]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$po) return null;
    // Read by the page only to offer the Billings button; it changes nothing
    // about how this inspection is loaded, validated or saved.
    $po['is_progress_billing'] = isProgressBillingTerm($po['payment_term']);

    $stmt = $db->prepare(
        'SELECT I_ID AS id, Invoice_Number AS invoice_number, BARCODE_Number AS barcode_number,
                Date_BAC_Received AS date_bac_received, Delivery_Date AS delivery_date,
                Date_Inspected AS date_inspected,
                Remarks AS remarks, Accepted_by AS accepted_by,
                Inspected_by_1 AS inspected_by_1, Inspected_by_2 AS inspected_by_2,
                Inspected_by_3 AS inspected_by_3, Inspected_by_4 AS inspected_by_4,
                End_User1 AS end_user1, End_User2 AS end_user2, End_User3 AS end_user3,
                End_User4 AS end_user4, End_User5 AS end_user5,
                Category AS category, Category_Explanation AS category_explanation,
                Item_Transit_Receipt_Number AS item_transit_receipt_number
         FROM tbl_inspection WHERE PO_Number = ? AND isDeleted = 0 ORDER BY I_ID DESC LIMIT 1'
    );
    $stmt->execute([$poNumber]);
    $insp = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($insp) {
        $stmt = $db->prepare(
            'SELECT Item_ID AS item_id, Unit AS unit, Items_Description AS items_description,
                    Quantity AS quantity, Unit_Cost AS unit_cost, Total_Cost AS total_cost
             FROM tbl_inspection_items_details
             WHERE PO_Number = ? AND isDeleted = 0 ORDER BY ID'
        );
    } else {
        $stmt = $db->prepare(
            'SELECT Item_ID AS item_id, Unit AS unit, Items_Description AS items_description,
                    Quantity AS quantity, Unit_Cost AS unit_cost, Total_Cost AS total_cost
             FROM tbl_purchase_order_items_details
             WHERE PO_Number = ? AND isDeleted = 0 ORDER BY ID'
        );
    }
    $stmt->execute([$poNumber]);

    // The version an undo would put back, if there is one. Returned so the
    // confirmation can state exactly what is about to happen — "restore the
    // version of 19 Sep 2022" and "take this purchase order off the inspected
    // list" are very different actions behind one button, and the operator has
    // to be told which one they are pressing. Same lookup the undo handler
    // uses; see the long note there for why isDeleted = 1 and I_ID < are both
    // part of it.
    $previous = null;
    if ($insp) {
        $q = $db->prepare('SELECT Date_Inspected AS date_inspected, Invoice_Number AS invoice_number
                           FROM tbl_inspection
                           WHERE PO_Number = ? AND isDeleted = 1 AND I_ID < ?
                           ORDER BY I_ID DESC LIMIT 1');
        $q->execute([$poNumber, $insp['id']]);
        $previous = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    return fixLegacyEncodingDeep([
        'po'         => $po,
        'inspection' => $insp,
        'items'      => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'exists'     => (bool)$insp,
        'previous'   => $previous,
    ]);
}

// Signatory picklists. Inspectors come from ref_signaturies_inspector; the
// office printed beside each name is resolved from ref_signaturies, which is a
// different table with different contents — see the note in inspSignatories().
function inspSignatories(PDO $db) {
    $inspectors = $db->query(
        "SELECT Signaturies AS name, Department AS department, Category AS category
         FROM ref_signaturies_inspector
         WHERE isDeleted = 0 AND TRIM(Signaturies) <> '' AND Signaturies <> '-'
         ORDER BY Signaturies"
    )->fetchAll(PDO::FETCH_ASSOC);

    // The legacy print dialog looks the office up here, by name match, so a name
    // present in one table and absent from the other prints a blank office. We
    // return both lists and let the UI resolve it the same way, but the office
    // is editable on the print dialog so a blank can be corrected before it is
    // committed to paper.
    $offices = $db->query(
        "SELECT Signaturies AS name, Department AS department, Position AS position
         FROM ref_signaturies
         WHERE isDeleted = 0 AND TRIM(Signaturies) <> '' AND Signaturies <> '-'
         ORDER BY Signaturies"
    )->fetchAll(PDO::FETCH_ASSOC);

    // The disbursement voucher's classification list. Voucher_Print.vb's
    // combo_category was an empty editable combo — the operator retyped the
    // category from memory every time, which is why the live data holds
    // "OFFICE SUPPLIES" and "Office Supplies and Consumables" as separate
    // things. Offered here as suggestions from the master data list the rest
    // of the system already maintains; the field stays free text, so nothing
    // that could be typed before is refused now.
    $categories = $db->query(
        "SELECT Category AS name FROM ref_category
         WHERE isDeleted = 0 AND TRIM(Category) <> '' AND Category <> '-'
         ORDER BY Category"
    )->fetchAll(PDO::FETCH_COLUMN);

    return fixLegacyEncodingDeep([
        'inspectors' => $inspectors,
        'offices'    => $offices,
        'categories' => $categories,
    ]);
}

function inspStats(PDO $db) {
    $s = [];
    $s['inspected_today'] = (int)$db->query(
        "SELECT COUNT(*) FROM tbl_inspection WHERE isDeleted = 0 AND DATE(Date_Inspected) = CURDATE()"
    )->fetchColumn();
    $s['inspected_month'] = (int)$db->query(
        "SELECT COUNT(*) FROM tbl_inspection WHERE isDeleted = 0
          AND Date_Inspected >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    )->fetchColumn();
    // Same definition of "awaiting" the list uses: nobody has accounted for the
    // delivery, by a whole-order inspection OR by a progress billing. The second
    // NOT EXISTS is what stops a fully-billed purchase order being counted here
    // as though nothing had happened to it.
    $s['awaiting'] = (int)$db->query(
        "SELECT COUNT(*) FROM tbl_purchase_order po
         WHERE po.isDeleted = 0 AND TRIM(po.PO_Number) <> ''
           AND po.Date_Created >= DATE_FORMAT(NOW(), '%Y-%m-01')
           AND NOT EXISTS (SELECT 1 FROM tbl_inspection ti
                           WHERE ti.PO_Number = po.PO_Number AND ti.isDeleted = 0)
           AND NOT EXISTS (SELECT 1 FROM tbl_inspection_progress_billing pb
                           WHERE pb.PO_Number = po.PO_Number AND pb.isDeleted = 0
                             AND pb.Billing_No IS NOT NULL)"
    )->fetchColumn();
    $s['amount_month'] = (float)$db->query(
        "SELECT COALESCE(SUM(d.Total_Cost), 0) FROM tbl_inspection_items_details d
         INNER JOIN tbl_inspection ti ON ti.PO_Number = d.PO_Number AND ti.isDeleted = 0
         WHERE d.isDeleted = 0 AND ti.Date_Inspected >= DATE_FORMAT(NOW(), '%Y-%m-01')"
    )->fetchColumn();
    return $s;
}

// End User picker — searches the live HRMIS personnel directory
// (hrmis.tblpersonalinformation, ~9,400 rows) the same way Choose_User.vb /
// api/hrmis_users.php's employee lookup does, but gated on the Inspection
// module rather than System Users admin: an Inspection User (the role that
// actually fills this field) has no System Users access to reach the other
// endpoint. Just the name — tblpersonalinformation carries no department/
// office column (confirmed via SHOW COLUMNS; that only exists on this app's
// own `users` row, which most personnel filling this field don't have), and
// nothing PII-heavy like the Settings picker needs (address, birth date,
// contact info) either, since this is only ever filling in a signature line,
// not creating an account.
function inspEmployees(string $q): array {
    $q = trim($q);
    if ($q === '') return ['rows' => []];
    $hrmis = getHrmisDB();
    $like = '%' . $q . '%';
    $stmt = $hrmis->prepare(
        "SELECT CONCAT_WS(' ', p.FirstName, NULLIF(p.MiddleName,''), p.SurName, NULLIF(p.NameExt,'')) AS full_name
         FROM tblpersonalinformation p
         WHERE p.ID_NUM > ' '
           AND (p.FirstName LIKE ? OR p.SurName LIKE ?
                OR CONCAT_WS(' ', p.FirstName, p.SurName) LIKE ?)
         ORDER BY p.SurName ASC, p.FirstName ASC LIMIT 20"
    );
    $stmt->execute([$like, $like, $like]);
    return ['rows' => fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_ASSOC))];
}

// Remarks picklist — database/remarks_inspection.sql, tbl_remarks_inspection.
// Own table rather than reusing ref_remarks (the PO/Competitive Bidding
// Remarks picklist): these ten are inspection/billing status labels
// ("WAITING FOR PAR/ICS", "FOR TERMINATION"...), a different vocabulary from
// ref_remarks' general procurement wording. Suggestion only — both Remarks
// fields (the main dialog's textarea and Progress Billing's input) stay free
// text either way.
function inspRemarksList(PDO $db): array {
    $stmt = $db->query('SELECT Value FROM tbl_remarks_inspection WHERE isDeleted = 0 ORDER BY ID');
    return ['rows' => fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_COLUMN))];
}

if ($method === 'GET') {
    if (isset($_GET['signatories'])) jsonResponse(true, '', inspSignatories($db));
    if (isset($_GET['stats']))       jsonResponse(true, '', ['stats' => inspStats($db)]);
    if (isset($_GET['employees']))   jsonResponse(true, '', inspEmployees($_GET['q'] ?? ''));
    if (isset($_GET['remarks']))     jsonResponse(true, '', inspRemarksList($db));

    $poNumber = trim($_GET['po_number'] ?? '');
    if ($poNumber !== '') {
        $detail = inspGetDetail($db, $poNumber);
        if (!$detail) jsonResponse(false, 'Purchase order not found.');
        jsonResponse(true, '', $detail);
    }
    $list = inspGetList($db);
    jsonResponse(true, '', ['orders' => $list['orders'], 'counts' => $list['counts']]);
}

$data = getInput();

// ── Save (create or revise) ─────────────────────────────────────────────────
// POST creates the first inspection for a PO; PUT revises an existing one.
// Both run the identical write sequence, because in the legacy design an edit
// is not an UPDATE — it retires the current rows and inserts new ones, keeping
// every earlier version queryable. That is preserved exactly.
if ($method === 'POST' || $method === 'PUT') {
    $poNumber = trim($data['po_number'] ?? '');
    if ($poNumber === '') jsonResponse(false, 'PO number is required.');

    $po = $db->prepare('SELECT PO_Number, PR_Number, Department, Supplier_Name, Supplier_Address, Purpose
                        FROM tbl_purchase_order WHERE PO_Number = ? AND isDeleted = 0');
    $po->execute([$poNumber]);
    $poRow = $po->fetch(PDO::FETCH_ASSOC);
    if (!$poRow) jsonResponse(false, 'Purchase order not found.');

    $existing = $db->prepare('SELECT COUNT(*) FROM tbl_inspection WHERE PO_Number = ? AND isDeleted = 0');
    $existing->execute([$poNumber]);
    $hasExisting = (int)$existing->fetchColumn() > 0;

    if ($method === 'POST' && $hasExisting) {
        jsonResponse(false, 'This purchase order has already been inspected. Open it to revise instead.');
    }
    if ($method === 'PUT' && !$hasExisting) {
        jsonResponse(false, 'No inspection exists for this purchase order yet.');
    }

    $inspectors = [
        trim($data['inspected_by_1'] ?? ''), trim($data['inspected_by_2'] ?? ''),
        trim($data['inspected_by_3'] ?? ''), trim($data['inspected_by_4'] ?? ''),
    ];
    $items = $data['items'] ?? [];

    // ─────────────────────────────────────────────────────────────────────────
    // EVERY check happens here, before a single row is touched. This is the one
    // change that matters most: Inspect.vb's save_click_edit() marked the old
    // header and all its line items deleted and only THEN checked the invoice
    // number, so a save that failed validation destroyed the inspection.
    // ─────────────────────────────────────────────────────────────────────────
    if (count(array_filter($inspectors, fn($v) => $v !== '')) === 0) {
        jsonResponse(false, 'Add at least one inspection officer.');
    }
    // The same officer must not appear twice — four signature lines print on the
    // acceptance report, and one person cannot occupy two of them. Compared with
    // refCanonicalKey() so "DELA CRUZ, JUAN" and "Dela Cruz Juan" are caught as
    // the same person, not just byte-identical strings.
    $seen = [];
    foreach ($inspectors as $i => $name) {
        if ($name === '') continue;
        $key = refCanonicalKey($name);
        if ($key === '') continue;
        if (isset($seen[$key])) {
            jsonResponse(false, 'Inspector ' . ($i + 1) . ' is the same person as Inspector '
                              . ($seen[$key] + 1) . '. Each officer can only be listed once.');
        }
        $seen[$key] = $i;
    }
    if (trim($data['invoice_number'] ?? '') === '') {
        jsonResponse(false, 'Invoice / receipt number is required.');
    }
    if (!is_array($items) || count($items) === 0) {
        jsonResponse(false, 'At least one item is required.');
    }
    foreach ($items as $i => $item) {
        if (trim($item['items_description'] ?? '') === '') {
            jsonResponse(false, 'Item ' . ($i + 1) . ' has no description.');
        }
        if (!is_numeric($item['quantity'] ?? null) || (float)$item['quantity'] < 0) {
            jsonResponse(false, 'Item ' . ($i + 1) . ' has an invalid quantity.');
        }
        if (!is_numeric($item['unit_cost'] ?? null) || (float)$item['unit_cost'] < 0) {
            jsonResponse(false, 'Item ' . ($i + 1) . ' has an invalid unit cost.');
        }
    }

    $invoice   = trim($data['invoice_number']);
    $now       = date('Y-m-d H:i:s');
    $bacDate   = inspParseDate($data['date_bac_received'] ?? null) ?: $now;
    // Date of Receipt. Unlike Date BAC Received this does NOT fall back to now:
    // the date the goods arrived is either known or it is not, and quietly
    // stamping today's date onto an unknown one invents a fact. NULL prints as
    // a blank line somebody fills in by hand.
    $receiptDate = inspParseDate($data['delivery_date'] ?? null);
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';

    try {
        $db->beginTransaction();

        // Retire the current version. Only reached once validation has passed.
        $db->prepare('UPDATE tbl_inspection SET isDeleted = 1, Edited_by = ? WHERE PO_Number = ? AND isDeleted = 0')
           ->execute([$actorName, $poNumber]);
        $db->prepare('UPDATE tbl_inspection_items_details SET isDeleted = 1 WHERE PO_Number = ? AND isDeleted = 0')
           ->execute([$poNumber]);

        $itemStmt = $db->prepare(
            'INSERT INTO tbl_inspection_items_details
                (Item_Transit_Receipt_Number, PO_Number, Item_ID, Items_Description, Unit,
                 Quantity, Unit_Cost, Total_Cost, Date_Inspected, isDeleted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );
        foreach ($items as $item) {
            $qty  = (float)$item['quantity'];
            $cost = (float)$item['unit_cost'];
            // Recomputed server-side rather than trusting the posted total —
            // the legacy grid recalculated on every cell edit, and this is the
            // same rule enforced where it cannot be bypassed.
            $itemStmt->execute([
                $invoice,
                $poNumber,
                ($item['item_id'] ?? '') !== '' ? $item['item_id'] : null,
                trim($item['items_description']),
                trim($item['unit'] ?? ''),
                $qty,
                $cost,
                round($qty * $cost, 2),
                $now,
            ]);
        }

        // Header. PR number, department, supplier and purpose are snapshotted
        // from the PO exactly as the legacy form did, so an inspection always
        // reflects what the PO said on the day it was inspected.
        $db->prepare(
            'INSERT INTO tbl_inspection
                (Item_Transit_Receipt_Number, Invoice_Number, Date_BAC_Received, Delivery_Date,
                 BARCODE_Number,
                 Remarks, PO_Number, PR_Number, Department, Supplier_Name, Supplier_Address, Purpose,
                 End_User1, End_User2, End_User3, End_User4, End_User5,
                 Inspected_by_1, Inspected_by_2, Inspected_by_3, Inspected_by_4,
                 Date_Inspected, Accepted_by, Edited_by, isDeleted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        )->execute([
            $invoice, $invoice, $bacDate, $receiptDate, trim($data['barcode_number'] ?? ''),
            trim($data['remarks'] ?? ''), $poNumber,
            $poRow['PR_Number'], $poRow['Department'], $poRow['Supplier_Name'],
            $poRow['Supplier_Address'], $poRow['Purpose'],
            trim($data['end_user1'] ?? ''), trim($data['end_user2'] ?? ''),
            trim($data['end_user3'] ?? ''), trim($data['end_user4'] ?? ''),
            trim($data['end_user5'] ?? ''),
            $inspectors[0], $inspectors[1], $inspectors[2], $inspectors[3],
            $now, trim($data['accepted_by'] ?? ''), $actorName,
        ]);

        // Flag the PO, same as the legacy save.
        $db->prepare("UPDATE tbl_purchase_order
                      SET Item_Transit_Receipt_Number = ?, Inspect = 'YES'
                      WHERE PO_Number = ? AND isDeleted = 0")
           ->execute([$invoice, $poNumber]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        // Deliberately not swallowed. The legacy code caught every exception
        // into an empty block and still told the user it had saved.
        error_log('Inspection save failed for ' . $poNumber . ': ' . $e->getMessage());
        jsonResponse(false, 'Could not save the inspection. Nothing was changed.');
    }

    $verb = $hasExisting ? 'revised' : 'recorded';
    logAudit($hasExisting ? 'UPDATE_INSPECTION' : 'CREATE_INSPECTION', 'inspection',
             ucfirst($verb) . " inspection for PO {$poNumber}");
    notifyPoUsers(
        $hasExisting ? 'Inspection Revised' : 'Inspection Recorded',
        "{$actorName} {$verb} the inspection for PO {$poNumber}",
        'inspection.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber,
        'all'
    );
    jsonResponse(true, 'Inspection ' . $verb . ' successfully.', ['po_number' => $poNumber]);
}

// ── Undo the last save ──────────────────────────────────────────────────────
// "Wrong purchase order inspected" and "that revision was a mistake" are the
// same button, because in this module a save is always an append: every earlier
// version of an inspection is still on file, retired rather than overwritten
// (see the save block above). Undo therefore has nothing to reconstruct — it
// puts the previous version back, or, when the save being undone was the first
// one, takes the inspection off the purchase order and returns it to the
// worklist as awaiting inspection.
//
// After this, isDeleted carries THREE values on these two tables:
//     0  live
//     1  superseded by a later save
//     2  retracted by an undo
// Two values rather than one specifically so a retracted version can never be
// handed back by a later undo. Collapse them and the sequence "undo, revise,
// undo" walks back into the version the FIRST undo threw away instead of the
// one the revision replaced, because by I_ID alone the two are
// indistinguishable. Nothing in this app or the legacy VB reads anything but
// isDeleted = 0 on these tables — the only writes are in this file — so the
// extra value is invisible everywhere else, which is why it needs no schema
// change and no migration.
//
// Versions are paired to their line items by (PO_Number, Date_Inspected): one
// timestamp is computed per save and stamped on the header and every item row
// alike. Verified against all 16,738 inspection headers on file — no purchase
// order has two versions sharing a timestamp, and I_ID order agrees with
// timestamp order in every single case, including the rows the legacy VB wrote.
//
// Open to anyone with module access, deliberately, and not narrowed to the
// admin tier the way deleting a purchase order is: the same person can already
// press Revise and overwrite every field on the record, which is strictly more
// destructive than restoring a version that already exists. Gating undo behind
// an administrator while leaving Revise open would only mean mistakes get fixed
// by retyping. Nothing is hard-deleted, and every undo is audited and notified.
if ($method === 'PATCH' && ($data['action'] ?? '') === 'undo') {
    $poNumber = trim($data['po_number'] ?? '');
    if ($poNumber === '') jsonResponse(false, 'PO number is required.');

    $cur = $db->prepare('SELECT I_ID, Date_Inspected, Invoice_Number FROM tbl_inspection
                         WHERE PO_Number = ? AND isDeleted = 0 ORDER BY I_ID DESC LIMIT 1');
    $cur->execute([$poNumber]);
    $current = $cur->fetch(PDO::FETCH_ASSOC);
    if (!$current) jsonResponse(false, 'This purchase order has no inspection to undo.');

    // The version this one replaced: the newest still-superseded version below
    // it. isDeleted = 1 excludes anything an earlier undo already retracted,
    // and I_ID < the live one excludes retracted rows sitting ABOVE it.
    $prevStmt = $db->prepare('SELECT I_ID, Date_Inspected, Invoice_Number FROM tbl_inspection
                              WHERE PO_Number = ? AND isDeleted = 1 AND I_ID < ?
                              ORDER BY I_ID DESC LIMIT 1');
    $prevStmt->execute([$poNumber, $current['I_ID']]);
    $previous = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // 596 of the legacy headers have no line items findable under their own
    // timestamp. Restoring one of those would leave an inspection with a header
    // and no items, which prints as a blank acceptance report — refuse, and say
    // why, rather than quietly producing it.
    if ($previous) {
        $n = $db->prepare('SELECT COUNT(*) FROM tbl_inspection_items_details
                           WHERE PO_Number = ? AND Date_Inspected = ?');
        $n->execute([$poNumber, $previous['Date_Inspected']]);
        if ((int)$n->fetchColumn() === 0) {
            jsonResponse(false, 'The previous version has no item lines on file, so restoring it '
                              . 'would leave the inspection empty. Revise the current one instead.');
        }
    }

    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    try {
        $db->beginTransaction();

        // Retract the current version. Marked 2, not 1, so a later undo cannot
        // walk back into it — see the note above.
        $db->prepare('UPDATE tbl_inspection SET isDeleted = 2, Edited_by = ?
                      WHERE PO_Number = ? AND isDeleted = 0')
           ->execute([$actorName, $poNumber]);
        $db->prepare('UPDATE tbl_inspection_items_details SET isDeleted = 2
                      WHERE PO_Number = ? AND isDeleted = 0')
           ->execute([$poNumber]);

        if ($previous) {
            $db->prepare('UPDATE tbl_inspection SET isDeleted = 0 WHERE PO_Number = ? AND I_ID = ?')
               ->execute([$poNumber, $previous['I_ID']]);
            // By timestamp, which is the version key. Every live line for this
            // PO was just retired above, so whatever this restores IS the live
            // set — a stray line left over from some earlier state cannot
            // survive alongside it.
            $db->prepare('UPDATE tbl_inspection_items_details SET isDeleted = 0
                          WHERE PO_Number = ? AND Date_Inspected = ?')
               ->execute([$poNumber, $previous['Date_Inspected']]);
            $db->prepare("UPDATE tbl_purchase_order
                          SET Item_Transit_Receipt_Number = ?, Inspect = 'YES'
                          WHERE PO_Number = ? AND isDeleted = 0")
               ->execute([trim((string)$previous['Invoice_Number']), $poNumber]);
        } else {
            // Nothing behind it: the purchase order goes back to being
            // uninspected. 'NO' rather than NULL — that is the value every
            // never-inspected PO already carries, and the legacy VB reads it.
            $db->prepare("UPDATE tbl_purchase_order
                          SET Item_Transit_Receipt_Number = NULL, Inspect = 'NO'
                          WHERE PO_Number = ? AND isDeleted = 0")
               ->execute([$poNumber]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Inspection undo failed for ' . $poNumber . ': ' . $e->getMessage());
        jsonResponse(false, 'Could not undo the inspection. Nothing was changed.');
    }

    $restored = (bool)$previous;
    logAudit('UNDO_INSPECTION', 'inspection', $restored
        ? "Undid the last revision on PO {$poNumber}, restoring the version of {$previous['Date_Inspected']}"
        : "Removed the inspection on PO {$poNumber}");
    notifyPoUsers(
        $restored ? 'Inspection Revision Undone' : 'Inspection Removed',
        $restored
            ? "{$actorName} undid the last revision on PO {$poNumber}"
            : "{$actorName} removed the inspection on PO {$poNumber} — it is awaiting inspection again",
        'inspection.php?po_number=' . urlencode($poNumber) . ($restored ? '&view=1' : ''),
        $poNumber,
        'all'
    );
    jsonResponse(true, $restored
        ? 'Undone. The previous version has been restored.'
        : 'Undone. This purchase order is back on the awaiting list.',
        ['restored' => $restored, 'po_number' => $poNumber]);
}

// ── Disbursement voucher classification ─────────────────────────────────────

// The legacy Voucher_Print wrote these two columns as a side effect of pressing
// Print. Here it is an explicit save, because a print button that silently
// mutates the record is a surprise nobody benefits from.
if ($method === 'PATCH' && isset($data['category'])) {
    $poNumber = trim($data['po_number'] ?? '');
    if ($poNumber === '') jsonResponse(false, 'PO number is required.');

    $cur = $db->prepare('SELECT Category, Category_Explanation FROM tbl_inspection
                         WHERE PO_Number = ? AND isDeleted = 0 ORDER BY I_ID DESC LIMIT 1');
    $cur->execute([$poNumber]);
    $row = $cur->fetch(PDO::FETCH_ASSOC);
    if (!$row) jsonResponse(false, 'No inspection exists for this purchase order.');

    $category    = trim($data['category']);
    $explanation = trim($data['category_explanation'] ?? '');
    // Same no-op guard as the PO barcode field: re-printing must not spawn a
    // fresh audit entry and notification every time.
    if ($category === trim((string)$row['Category'])
        && $explanation === trim((string)$row['Category_Explanation'])) {
        jsonResponse(true, 'Classification unchanged.');
    }

    $db->prepare('UPDATE tbl_inspection SET Category = ?, Category_Explanation = ?
                  WHERE PO_Number = ? AND isDeleted = 0')
       ->execute([$category, $explanation, $poNumber]);
    logAudit('UPDATE_INSPECTION', 'inspection', "Set voucher classification on PO {$poNumber}");
    jsonResponse(true, 'Classification saved.');
}

jsonResponse(false, 'Unsupported request.');
