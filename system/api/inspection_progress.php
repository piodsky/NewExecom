<?php
/* Progress billing for Inspection & Acceptance — a SECOND flow, alongside the
 * single-inspection one in api/inspection.php, which this file does not touch.
 *
 * A normal purchase order is delivered once, inspected once, and paid once.
 * A progress-billing purchase order is delivered and paid in tranches, and the
 * office needs to know, per item, how much of the ordered quantity each
 * tranche covered and how much is still outstanding. That is the whole reason
 * this exists: tbl_inspection holds exactly one live row per PO, so it cannot
 * express "Progress Billing - 1 took 40 of the 200 covers".
 *
 * WHICH POs. Detected from the purchase order's own Payment Term containing
 * "Progress Billing" — the value the office already sets in Master Data — so
 * nothing has to be flagged by hand. isProgressBillingTerm() is the single
 * definition of that test and both this file and the worklist use it.
 *
 * THE LEGACY. vb/Inspection and Acceptance/Inspect.vb has
 * save_click_progress_billing(), and the two tables it writes to are on the
 * live database. It has never worked once: the header insert names four
 * columns the table did not have, it throws, and the routine's `Catch ex As
 * Exception` is empty — the operator was shown "Successfully saved!" either
 * way. See database/inspection_progress_billing.sql. Departures from it, each
 * marked where it happens:
 *   1. validation runs before anything is written;
 *   2. the save is one transaction;
 *   3. failures surface;
 *   4. items are paired to their header by (PO_Number, Billing_No), not by the
 *      Progress_Billing label alone — the legacy join omits PO_Number entirely,
 *      so "Progress Billing - 1" of one PO matches every other PO's first
 *      billing.
 */
require_once '../config.php';
requireAuth();
requireModule('inspection');
header('Content-Type: application/json');
$db     = getPoDB();
$method = $_SERVER['REQUEST_METHOD'];

/* The legacy combo offered ten. Nothing enforces a business limit — a long
 * infrastructure contract can run past it — so this is only a sanity bound to
 * keep a typo out of the table. */
const PB_MAX_BILLING = 99;

/* The printed label, byte-for-byte what the legacy combo box put in the column
 * and what the Crystal report expects to find there. */
function pbLabel(int $n): string { return 'Progress Billing - ' . $n; }

function pbParseDate($value, bool $dateOnly = false) {
    if (!$value) return null;
    $ts = strtotime($value);
    if (!$ts) return null;
    return date($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s', $ts);
}

/* What KIND of purchase order line is this?
 *
 * A PO's item list is a document, not a flat table. Three shapes occur, and
 * only two of them can be billed:
 *
 *   caption  Quantity, Unit Cost and Total Cost all zero, no unit — a heading
 *            the operator typed to group what follows ("Lot 1",
 *            "TRAINING MATERIALS") or a continuation line spelling out what a
 *            priced line above contains. Billing one is meaningless.
 *   lump     A price but no quantity — one indivisible sum. Billed as a single
 *            unit, so the quantity for it is 1 and its unit cost is the whole
 *            amount.
 *   item     Quantity and unit cost both present. The ordinary case.
 *
 * Total Cost is the test for whether a line is priced at all, not the presence
 * of Unit + Quantity + Unit Cost — a lump sum legitimately has none of those
 * three and is still a real, billable line.
 */
function pbLineKind($quantity, $unitCost, $totalCost): string {
    $q = (float)$quantity; $u = (float)$unitCost; $t = (float)$totalCost;
    if ($t <= 0 && $u <= 0) return 'caption';
    return $q > 0 ? 'item' : 'lump';
}

/* What the editor should multiply the entered quantity by. For a lump sum that
 * is the whole amount, because its quantity is 1. */
function pbBillUnitCost(array $item): float {
    $kind = pbLineKind($item['quantity'] ?? 0, $item['unit_cost'] ?? 0, $item['total_cost'] ?? 0);
    if ($kind === 'lump') {
        $t = (float)($item['total_cost'] ?? 0);
        return $t > 0 ? $t : (float)($item['unit_cost'] ?? 0);
    }
    return (float)($item['unit_cost'] ?? 0);
}
/* One purchase order, its ordered lines, and every billing recorded against it.
 *
 * Ordered lines come from the PO itself, never from tbl_inspection: a progress
 * billing is measured against what was ORDERED, and the inspection's own lines
 * are editable. */
function pbGetDetail(PDO $db, string $poNumber): ?array {
    $stmt = $db->prepare(
        'SELECT PO_Number AS po_number, PR_Number AS pr_number, Department AS department,
                Supplier_Name AS supplier_name, Supplier_Address AS supplier_address,
                Purpose AS purpose, Date_Created AS date_created, Inspect AS inspect_flag,
                PO_Payment_Term AS payment_term, Category AS po_category
         FROM tbl_purchase_order WHERE PO_Number = ? AND isDeleted = 0'
    );
    $stmt->execute([$poNumber]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$po) return null;
    $po['is_progress_billing'] = isProgressBillingTerm($po['payment_term']);

    $stmt = $db->prepare(
        'SELECT ID AS row_id, Item_ID AS item_id, Unit AS unit,
                Items_Description AS items_description, Quantity AS quantity,
                Unit_Cost AS unit_cost, Total_Cost AS total_cost
         FROM tbl_purchase_order_items_details
         WHERE PO_Number = ? AND isDeleted = 0 ORDER BY ID'
    );
    $stmt->execute([$poNumber]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orderItems as &$oi) {
        $oi['line_kind']      = pbLineKind($oi['quantity'], $oi['unit_cost'], $oi['total_cost']);
        $oi['bill_unit_cost'] = pbBillUnitCost($oi);
        // A lump sum is one unit of itself. Stated here rather than left for the
        // page to infer, so "1 of 1 billed" reads the same in both views.
        $oi['bill_quantity']  = $oi['line_kind'] === 'lump' ? 1.0 : (float)$oi['quantity'];
    }
    unset($oi);

    $stmt = $db->prepare(
        'SELECT I_ID AS id, Billing_No AS billing_no, Progress_Billing AS label,
                Date_From_Billing AS date_from, Date_To_Billing AS date_to,
                Invoice_Number AS invoice_number, BARCODE_Number AS barcode_number,
                Date_BAC_Received AS date_bac_received, Remarks AS remarks,
                Delivery_Date AS delivery_date, Date_Inspected AS date_inspected,
                Inspected_by_1 AS inspected_by_1, Inspected_by_2 AS inspected_by_2,
                Inspected_by_3 AS inspected_by_3, Inspected_by_4 AS inspected_by_4,
                End_User1 AS end_user1, End_User2 AS end_user2, End_User3 AS end_user3,
                End_User4 AS end_user4, End_User5 AS end_user5,
                Accepted_by AS accepted_by, Edited_by AS edited_by
         FROM tbl_inspection_progress_billing
         WHERE PO_Number = ? AND isDeleted = 0 AND Billing_No IS NOT NULL
         ORDER BY Billing_No'
    );
    $stmt->execute([$poNumber]);
    $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Every live billing line for this PO in one query, then grouped in PHP.
     * One round trip regardless of how many tranches a contract runs to. */
    $stmt = $db->prepare(
        'SELECT Billing_No AS billing_no, Item_ID AS item_id, Unit AS unit,
                Items_Description AS items_description, Quantity AS quantity,
                Unit_Cost AS unit_cost, Total_Cost AS total_cost
         FROM tbl_inspection_items_details_progress_billing
         WHERE PO_Number = ? AND isDeleted = 0 AND Billing_No IS NOT NULL
         ORDER BY Billing_No, ID'
    );
    $stmt->execute([$poNumber]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $byBilling = [];
    foreach ($lines as $l) { $byBilling[(int)$l['billing_no']][] = $l; }

    /* What an undo would put back, for each billing. Returned so the
     * confirmation can name the actual consequence: "restore the version of
     * 3 Sep" and "remove this billing entirely" are very different things
     * behind one button, and the operator has to be told which one they are
     * pressing. Same lookup the undo handler runs — see the long note there
     * for why isDeleted = 1 and I_ID < are both part of it. */
    $prevStmt = $db->prepare(
        'SELECT Date_Inspected AS date_inspected, Invoice_Number AS invoice_number,
                Date_From_Billing AS date_from, Date_To_Billing AS date_to
         FROM tbl_inspection_progress_billing
         WHERE PO_Number = ? AND Billing_No = ? AND isDeleted = 1 AND I_ID < ?
         ORDER BY I_ID DESC LIMIT 1'
    );
    foreach ($billings as &$b) {
        $n = (int)$b['billing_no'];
        $b['billing_no'] = $n;
        $b['items'] = $byBilling[$n] ?? [];
        $b['total'] = 0.0;
        foreach ($b['items'] as $it) $b['total'] += (float)$it['total_cost'];
        $prevStmt->execute([$poNumber, $n, $b['id']]);
        $b['previous'] = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    unset($b);

    /* The disbursement voucher's classification. It lives on tbl_inspection,
     * where Voucher_Print.vb put it, and a progress-billing purchase order does
     * not necessarily have a row there — 657 of the 2,124 on file do not,
     * because tranches were recorded without anyone ever raising a single
     * whole-order inspection. Returned with a flag rather than as a blank, so
     * the page can prefill the voucher when there is something to prefill from
     * and can say plainly when a classification cannot be remembered. */
    $q = $db->prepare('SELECT Category AS category, Category_Explanation AS category_explanation
                       FROM tbl_inspection WHERE PO_Number = ? AND isDeleted = 0
                       ORDER BY I_ID DESC LIMIT 1');
    $q->execute([$poNumber]);
    $classification = $q->fetch(PDO::FETCH_ASSOC) ?: null;

    return fixLegacyEncodingDeep([
        'po'              => $po,
        'classification'  => $classification,
        'order_items'     => $orderItems,
        'billings'        => $billings,
        'progress'        => pbProgress($orderItems, $billings),
        'totals'          => pbTotals($orderItems, $billings),
        'next_billing_no' => pbNextNumber($billings),
    ]);
}

/* The lowest unused billing number, not simply max+1: after a billing is
 * retracted the office bills that number again rather than leaving a gap in a
 * sequence that gets printed on a voucher. */
function pbNextNumber(array $billings): int {
    $used = [];
    foreach ($billings as $b) $used[(int)$b['billing_no']] = true;
    for ($n = 1; $n <= PB_MAX_BILLING; $n++) if (!isset($used[$n])) return $n;
    return PB_MAX_BILLING;
}

/* Per ordered item: how much was ordered, how much each billing took, and what
 * is left. This is the answer to "how many have been in progress billing 1, 2".
 *
 * Items are matched on Item_ID, which is the PO line's own id and is what the
 * billing rows carry. A billing line whose Item_ID matches nothing on the PO
 * (an item added by hand during inspection) is still counted into the totals
 * but has no ordered quantity to measure against — reported separately rather
 * than silently folded into another row. */
function pbProgress(array $orderItems, array $billings): array {
    $rows = [];
    $index = [];
    foreach ($orderItems as $i => $item) {
        $key = (string)($item['item_id'] ?? '');
        $rows[$i] = [
            'item_id'          => $item['item_id'],
            'unit'             => $item['unit'],
            'items_description' => $item['items_description'],
            'line_kind'        => $item['line_kind'] ?? 'item',
            // A lump sum's ordered quantity is 1 — see pbLineKind(). Using the
            // stored 0 would make every lump-sum line look permanently
            // un-billable, with a remainder that can never reach zero.
            'ordered_quantity' => (float)($item['bill_quantity'] ?? $item['quantity']),
            'unit_cost'        => (float)$item['unit_cost'],
            'ordered_amount'   => (float)$item['total_cost'],
            'billed_quantity'  => 0.0,
            'billed_amount'    => 0.0,
            'per_billing'      => [],
            'unmatched'        => false,
        ];
        if ($key !== '') $index[$key] = $i;
    }

    foreach ($billings as $b) {
        $n = (int)$b['billing_no'];
        foreach ($b['items'] as $line) {
            $key = (string)($line['item_id'] ?? '');
            $qty = (float)$line['quantity'];
            $amt = (float)$line['total_cost'];
            if ($key !== '' && isset($index[$key])) {
                $r = &$rows[$index[$key]];
            } else {
                /* An off-PO line. Keyed by description so the same added item
                 * billed across several tranches still accumulates into one
                 * row instead of one row per tranche. */
                $extraKey = 'x:' . mb_strtolower(trim((string)$line['items_description']));
                if (!isset($index[$extraKey])) {
                    $index[$extraKey] = count($rows);
                    $rows[] = [
                        'item_id'          => $line['item_id'],
                        'unit'             => $line['unit'],
                        'items_description' => $line['items_description'],
                        'line_kind'        => 'item',
                        'ordered_quantity' => 0.0,
                        'unit_cost'        => (float)$line['unit_cost'],
                        'ordered_amount'   => 0.0,
                        'billed_quantity'  => 0.0,
                        'billed_amount'    => 0.0,
                        'per_billing'      => [],
                        'unmatched'        => true,
                    ];
                }
                $r = &$rows[$index[$extraKey]];
            }
            $r['billed_quantity'] += $qty;
            $r['billed_amount']   += $amt;
            $r['per_billing'][$n] = ($r['per_billing'][$n] ?? 0) + $qty;
            unset($r);
        }
    }

    foreach ($rows as &$r) {
        $r['remaining_quantity'] = $r['ordered_quantity'] - $r['billed_quantity'];
        $r['remaining_amount']   = $r['ordered_amount']   - $r['billed_amount'];
        /* Reported, never enforced. A lump-sum PO line legitimately carries no
         * quantity at all (see the two valid item-row shapes on the purchase
         * order), so a server-side cap on quantity would reject correct data. */
        $r['over_billed'] = $r['ordered_quantity'] > 0 && $r['billed_quantity'] > $r['ordered_quantity'] + 0.0001;
    }
    unset($r);
    return array_values($rows);
}

function pbTotals(array $orderItems, array $billings): array {
    $ordered = 0.0;
    foreach ($orderItems as $i) $ordered += (float)$i['total_cost'];
    $billed = 0.0;
    foreach ($billings as $b) $billed += (float)$b['total'];
    /* progressBillingStatus() in config.php is the one place that decides
     * idle / partial / complete / over, so this dialog and the worklist badge
     * can never give different answers about the same purchase order. */
    $t = progressBillingStatus($ordered, $billed, count($billings));
    $t['billed_percent'] = $t['percent'];   // the name the page already reads
    return $t;
}

if ($method === 'GET') {
    $poNumber = trim($_GET['po_number'] ?? '');
    if ($poNumber === '') jsonResponse(false, 'PO number is required.');
    $detail = pbGetDetail($db, $poNumber);
    if (!$detail) jsonResponse(false, 'Purchase order not found.');
    jsonResponse(true, '', $detail);
}

$data = getInput();

/* ── Save one billing ────────────────────────────────────────────────────────
 * POST records a billing number for the first time; PUT revises one already on
 * file. Both write identically, and a revision retires the old rows rather than
 * updating them — the same append-only rule the single-inspection flow follows,
 * so every earlier version of a billing stays queryable and undo has something
 * to put back. */
if ($method === 'POST' || $method === 'PUT') {
    $poNumber  = trim($data['po_number'] ?? '');
    $billingNo = (int)($data['billing_no'] ?? 0);
    if ($poNumber === '') jsonResponse(false, 'PO number is required.');
    if ($billingNo < 1 || $billingNo > PB_MAX_BILLING) {
        jsonResponse(false, 'Billing number must be between 1 and ' . PB_MAX_BILLING . '.');
    }

    $po = $db->prepare('SELECT PO_Number, PR_Number, Department, Supplier_Name, Supplier_Address,
                               Purpose, PO_Payment_Term
                        FROM tbl_purchase_order WHERE PO_Number = ? AND isDeleted = 0');
    $po->execute([$poNumber]);
    $poRow = $po->fetch(PDO::FETCH_ASSOC);
    if (!$poRow) jsonResponse(false, 'Purchase order not found.');

    $existing = $db->prepare('SELECT COUNT(*) FROM tbl_inspection_progress_billing
                              WHERE PO_Number = ? AND Billing_No = ? AND isDeleted = 0');
    $existing->execute([$poNumber, $billingNo]);
    $hasExisting = (int)$existing->fetchColumn() > 0;

    /* The payment term is the gate, but only for a NEW billing. A PO already
     * carrying billings stays editable even if someone later changes its term
     * to something else — otherwise that edit strands the records. */
    if (!$hasExisting && !isProgressBillingTerm($poRow['PO_Payment_Term'])) {
        $anyBilling = $db->prepare('SELECT COUNT(*) FROM tbl_inspection_progress_billing
                                    WHERE PO_Number = ? AND isDeleted = 0');
        $anyBilling->execute([$poNumber]);
        if ((int)$anyBilling->fetchColumn() === 0) {
            jsonResponse(false, 'This purchase order is not on progress billing. '
                              . 'Set its Payment Term to a Progress Billing entry first.');
        }
    }

    if ($method === 'POST' && $hasExisting) {
        jsonResponse(false, pbLabel($billingNo) . ' is already on file. Open it to revise instead.');
    }
    if ($method === 'PUT' && !$hasExisting) {
        jsonResponse(false, pbLabel($billingNo) . ' has not been recorded yet.');
    }

    $inspectors = [
        trim($data['inspected_by_1'] ?? ''), trim($data['inspected_by_2'] ?? ''),
        trim($data['inspected_by_3'] ?? ''), trim($data['inspected_by_4'] ?? ''),
    ];
    $items = $data['items'] ?? [];

    /* Departure 1: every check happens here, before a single row is touched.
     * save_click_progress_billing() inserts the item rows first and validates
     * afterwards, which is how three orphan lines with no header ended up on
     * the live table. */
    if (count(array_filter($inspectors, fn($v) => $v !== '')) === 0) {
        jsonResponse(false, 'Add at least one inspection officer.');
    }
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
        jsonResponse(false, 'Add at least one item to this billing.');
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
    $billed = 0.0;
    foreach ($items as $item) $billed += (float)$item['quantity'] * (float)$item['unit_cost'];
    if ($billed <= 0) {
        jsonResponse(false, 'This billing comes to zero. Enter the quantities being billed.');
    }

    $dateFrom = pbParseDate($data['date_from'] ?? null, true);
    $dateTo   = pbParseDate($data['date_to'] ?? null, true);
    if ($dateFrom && $dateTo && $dateTo < $dateFrom) {
        jsonResponse(false, 'The billing period ends before it starts.');
    }

    $invoice   = trim($data['invoice_number']);
    $label     = pbLabel($billingNo);
    $now       = date('Y-m-d H:i:s');
    $bacDate   = pbParseDate($data['date_bac_received'] ?? null) ?: $now;
    $delivery  = pbParseDate($data['delivery_date'] ?? null);
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';

    try {
        /* Departure 2: one transaction. The legacy ran its item loop, its
         * header insert and two PO updates as separate unwrapped statements. */
        $db->beginTransaction();

        $db->prepare('UPDATE tbl_inspection_progress_billing SET isDeleted = 1, Edited_by = ?
                      WHERE PO_Number = ? AND Billing_No = ? AND isDeleted = 0')
           ->execute([$actorName, $poNumber, $billingNo]);
        $db->prepare('UPDATE tbl_inspection_items_details_progress_billing SET isDeleted = 1
                      WHERE PO_Number = ? AND Billing_No = ? AND isDeleted = 0')
           ->execute([$poNumber, $billingNo]);

        $itemStmt = $db->prepare(
            'INSERT INTO tbl_inspection_items_details_progress_billing
                (Item_Transit_Receipt_Number, Progress_Billing, Billing_No,
                 Date_From_Billing, Date_To_Billing, PO_Number, Item_ID,
                 Items_Description, Unit, Quantity, Unit_Cost, Total_Cost,
                 Date_Created, Date_Inspected, isDeleted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );
        foreach ($items as $item) {
            $qty  = (float)$item['quantity'];
            $cost = (float)$item['unit_cost'];
            $itemStmt->execute([
                $invoice, $label, $billingNo, $dateFrom, $dateTo, $poNumber,
                ($item['item_id'] ?? '') !== '' ? $item['item_id'] : null,
                trim($item['items_description']),
                trim($item['unit'] ?? ''),
                $qty, $cost,
                /* Recomputed server-side, never taken from the payload. */
                round($qty * $cost, 2),
                $now, $now,
            ]);
        }

        /* Header. PR number, department, supplier and purpose are snapshotted
         * off the PO, matching what the single-inspection save does. */
        $db->prepare(
            'INSERT INTO tbl_inspection_progress_billing
                (Item_Transit_Receipt_Number, Invoice_Number, BARCODE_Number, Date_BAC_Received,
                 Remarks, PO_Number, PR_Number, Progress_Billing, Billing_No,
                 Date_From_Billing, Date_To_Billing, Department, Supplier_Name, Supplier_Address,
                 Purpose, End_User1, End_User2, End_User3, End_User4, End_User5,
                 Delivery_Date, Date_Inspected, Date_Created,
                 Inspected_by_1, Inspected_by_2, Inspected_by_3, Inspected_by_4,
                 Accepted_by, Edited_by, isDeleted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        )->execute([
            $invoice, $invoice, trim($data['barcode_number'] ?? ''), $bacDate,
            trim($data['remarks'] ?? ''), $poNumber, $poRow['PR_Number'], $label, $billingNo,
            $dateFrom, $dateTo, $poRow['Department'], $poRow['Supplier_Name'],
            $poRow['Supplier_Address'], $poRow['Purpose'],
            trim($data['end_user1'] ?? ''), trim($data['end_user2'] ?? ''),
            trim($data['end_user3'] ?? ''), trim($data['end_user4'] ?? ''),
            trim($data['end_user5'] ?? ''),
            $delivery, $now, $now,
            $inspectors[0], $inspectors[1], $inspectors[2], $inspectors[3],
            trim($data['accepted_by'] ?? ''), $actorName,
        ]);

        /* Flag the PO, using the legacy's own marker string so a machine still
         * running the VB reads this the way it expects. Deliberately NOT
         * 'YES': that is the single-inspection flow's value, and overwriting it
         * here would make a progress-billed PO indistinguishable from a fully
         * inspected one on the worklist. The legacy also stamped the receipt
         * number over the PO's item rows; that is not reproduced, because it
         * overwrites the whole PO's lines with the latest tranche's receipt. */
        $db->prepare("UPDATE tbl_purchase_order SET Inspect = 'YES - Progressbilling'
                      WHERE PO_Number = ? AND isDeleted = 0")
           ->execute([$poNumber]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        /* Departure 3: not swallowed. */
        error_log('Progress billing save failed for ' . $poNumber . ' #' . $billingNo . ': ' . $e->getMessage());
        jsonResponse(false, 'Could not save this billing. Nothing was changed.');
    }

    $verb = $hasExisting ? 'revised' : 'recorded';
    logAudit($hasExisting ? 'UPDATE_PROGRESS_BILLING' : 'CREATE_PROGRESS_BILLING', 'inspection',
             ucfirst($verb) . " {$label} for PO {$poNumber}");
    notifyPoUsers(
        $hasExisting ? 'Progress Billing Revised' : 'Progress Billing Recorded',
        "{$actorName} {$verb} {$label} for PO {$poNumber}",
        'inspection.php?po_number=' . urlencode($poNumber) . '&billing=1',
        $poNumber,
        'all'
    );
    jsonResponse(true, $label . ' ' . $verb . ' successfully.',
                 ['po_number' => $poNumber, 'billing_no' => $billingNo]);
}

/* ── Undo one billing ────────────────────────────────────────────────────────
 * "That revision was wrong" and "that billing should not exist" are the same
 * button, exactly as in the single-inspection flow: a save here is always an
 * append, so undo has nothing to reconstruct. It puts the previous version of
 * this billing back, or — when the save being undone was the first one — takes
 * the billing off the purchase order and frees its number to be used again.
 *
 * Everything is keyed on Billing_No as well as PO_Number: undoing Progress
 * Billing - 2 must not disturb 1 or 3.
 *
 * isDeleted carries THREE values on these two tables:
 *     0  live
 *     1  superseded by a later save
 *     2  retracted by an undo
 * Two values rather than one so a retracted version can never be handed back
 * by a later undo. Collapse them and "undo, revise, undo" walks back into the
 * version the FIRST undo threw away instead of the one the revision replaced,
 * because by I_ID alone the two are indistinguishable.
 *
 * Versions are paired to their line items by (PO_Number, Billing_No,
 * Date_Inspected): one timestamp is computed per save and stamped on the header
 * and every item row alike. */
if ($method === 'DELETE') {
    $poNumber  = trim($data['po_number'] ?? '');
    $billingNo = (int)($data['billing_no'] ?? 0);
    if ($poNumber === '' || $billingNo < 1) jsonResponse(false, 'PO number and billing number are required.');

    $cur = $db->prepare('SELECT I_ID FROM tbl_inspection_progress_billing
                         WHERE PO_Number = ? AND Billing_No = ? AND isDeleted = 0');
    $cur->execute([$poNumber, $billingNo]);
    $currentId = $cur->fetchColumn();
    if (!$currentId) jsonResponse(false, pbLabel($billingNo) . ' is not on file.');

    /* The version this undo would restore. isDeleted = 1 only — a 2 was thrown
     * away by an earlier undo and must not come back. I_ID < the live one so a
     * row saved after it can never be mistaken for the one before it. */
    $prev = $db->prepare('SELECT I_ID, Date_Inspected, Invoice_Number
                          FROM tbl_inspection_progress_billing
                          WHERE PO_Number = ? AND Billing_No = ? AND isDeleted = 1 AND I_ID < ?
                          ORDER BY I_ID DESC LIMIT 1');
    $prev->execute([$poNumber, $billingNo, $currentId]);
    $previous = $prev->fetch(PDO::FETCH_ASSOC) ?: null;

    /* Refused rather than performed: restoring a header whose item rows are
     * gone would leave a billing that says it is worth something and lists
     * nothing. Revising the current one is the way out of that. */
    if ($previous) {
        $n = $db->prepare('SELECT COUNT(*) FROM tbl_inspection_items_details_progress_billing
                           WHERE PO_Number = ? AND Billing_No = ? AND Date_Inspected = ? AND isDeleted = 1');
        $n->execute([$poNumber, $billingNo, $previous['Date_Inspected']]);
        if ((int)$n->fetchColumn() === 0) {
            jsonResponse(false, 'The previous version of ' . pbLabel($billingNo) . ' has no item lines '
                              . 'on file, so restoring it would leave the billing empty. '
                              . 'Revise the current one instead.');
        }
    }

    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    try {
        $db->beginTransaction();
        $db->prepare('UPDATE tbl_inspection_progress_billing SET isDeleted = 2, Edited_by = ?
                      WHERE PO_Number = ? AND Billing_No = ? AND isDeleted = 0')
           ->execute([$actorName, $poNumber, $billingNo]);
        $db->prepare('UPDATE tbl_inspection_items_details_progress_billing SET isDeleted = 2
                      WHERE PO_Number = ? AND Billing_No = ? AND isDeleted = 0')
           ->execute([$poNumber, $billingNo]);

        if ($previous) {
            $db->prepare('UPDATE tbl_inspection_progress_billing SET isDeleted = 0
                          WHERE PO_Number = ? AND I_ID = ?')
               ->execute([$poNumber, $previous['I_ID']]);
            /* Paired by timestamp, scoped to this billing. Every live line for
             * this billing was just retired above, so whatever this restores IS
             * the live set. isDeleted = 1 in the WHERE keeps a set retracted by
             * an earlier undo from being pulled back alongside it. */
            $db->prepare('UPDATE tbl_inspection_items_details_progress_billing SET isDeleted = 0
                          WHERE PO_Number = ? AND Billing_No = ? AND Date_Inspected = ? AND isDeleted = 1')
               ->execute([$poNumber, $billingNo, $previous['Date_Inspected']]);
        }

        /* If nothing is left billed, the PO goes back to being un-billed. Left
         * to the single-inspection flow to set 'YES' again if it applies.
         * Counted after the restore, so undoing a REVISION — which leaves a
         * live version behind — never clears the flag. */
        $left = $db->prepare('SELECT COUNT(*) FROM tbl_inspection_progress_billing
                              WHERE PO_Number = ? AND isDeleted = 0');
        $left->execute([$poNumber]);
        if ((int)$left->fetchColumn() === 0) {
            $db->prepare("UPDATE tbl_purchase_order SET Inspect = ''
                          WHERE PO_Number = ? AND isDeleted = 0 AND Inspect = 'YES - Progressbilling'")
               ->execute([$poNumber]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Progress billing undo failed for ' . $poNumber . ' #' . $billingNo . ': ' . $e->getMessage());
        jsonResponse(false, 'Could not undo this billing. Nothing was changed.');
    }

    $label    = pbLabel($billingNo);
    $restored = (bool)$previous;
    logAudit('UNDO_PROGRESS_BILLING', 'inspection', $restored
        ? "Undid the last revision of {$label} on PO {$poNumber}, restoring the version of {$previous['Date_Inspected']}"
        : "Removed {$label} from PO {$poNumber}");
    notifyPoUsers($restored ? 'Progress Billing Revision Undone' : 'Progress Billing Removed',
                  $restored
                      ? "{$actorName} undid the last revision of {$label} on PO {$poNumber}"
                      : "{$actorName} removed {$label} from PO {$poNumber}",
                  'inspection.php?po_number=' . urlencode($poNumber) . '&billing=1',
                  $poNumber, 'all');
    jsonResponse(true, $restored
        ? $label . ' — the previous version has been restored.'
        : $label . ' removed. Its number is free to use again.',
        ['po_number' => $poNumber, 'billing_no' => $billingNo, 'restored' => $restored]);
}

jsonResponse(false, 'Unsupported request.');
