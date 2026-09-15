<?php
// Inventory & Property — document issuance and transfers.
//
// Kept apart from api/inventory.php because this is the one part of the
// legacy workflow whose real persistence logic never made it into this repo.
// CGSO_Inventory.vb generates a PAR/ICS number and hands it to a report
// viewer form (`rptViewerPAR` / `rptViewerICS`) that is NOT among the VB
// files checked in here — only its call sites are. Comparing the auto_parnew
// / auto_icsnew log tables against tbl_inventory_items_details on the live
// database confirmed what that missing form actually does: it logs the
// generation (Printed_by, the Inv_ID(s) covered) AND writes the number back
// onto every covered item row. That is reproduced here, deliberately as ONE
// transaction — allocate the number, log it, write it — so the two can never
// go out of step the way separate, unwrapped statements could.
//
// Numbering format matches the live sequence exactly, including its
// unpadded month ("PAR2026800474" is year 2026, month 8, id 00474 — the VB
// source never zero-pads Now.Month), so a new number reads as part of the
// same series as the 3,900+ already on file, not a new one.
require_once '../config.php';
requireAuth();
requireModule('inventory');
header('Content-Type: application/json');
$db = getPoDB();
$method = $_SERVER['REQUEST_METHOD'];

const ISSUANCE_TABLES = [
    'par' => ['seq' => 'auto_parnew', 'idCol' => 'PAR_ID', 'numCol' => 'PAR_Number', 'prefix' => 'PAR', 'pad' => 5],
    'ics' => ['seq' => 'auto_icsnew', 'idCol' => 'ICS_ID', 'numCol' => 'ICS_Number', 'prefix' => 'ICS', 'pad' => 5],
    'itr' => ['seq' => 'auto_itr',    'idCol' => 'ITR_ID', 'numCol' => 'ITR_Number', 'prefix' => 'ITR', 'pad' => 4],
    'ptr' => ['seq' => 'auto_ptr',    'idCol' => 'PTR_ID', 'numCol' => 'PTR_Number', 'prefix' => 'PTR', 'pad' => 4],
];

// PRS numbering does NOT go through ISSUANCE_TABLES/invNextNumber() below —
// those pre-allocate a number from a separate "auto_*" log table, the shape
// the legacy VB.NET app needed to keep its own sequence. PRS has no legacy
// history to stay compatible with, so it gets a cleaner, more modern
// version of the same idea: the number is derived straight from
// tbl_inventory_prs's own PRS_ID right after that row is inserted (see the
// 'prs' action below) — the same "derive from the row's own reliable id"
// approach poNumberBarcode() in config.php already uses for PO barcodes.
// One table is the whole story; no second log table to keep in step with it.
function prsNumberFromId(int $id): string {
    return 'PRS' . date('Y') . (int)date('n') . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

// Same shape Now.Year & Now.Month & id.ToString("00000") produces — month is
// NOT zero-padded, on purpose, to match the >3,900 numbers already issued.
function invNextNumber(PDO $db, string $docType): array {
    $t = ISSUANCE_TABLES[$docType];
    $max = (int)$db->query("SELECT MAX({$t['idCol']}) FROM {$t['seq']}")->fetchColumn();
    $id  = $max + 1;
    $number = $t['prefix'] . date('Y') . (int)date('n') . str_pad((string)$id, $t['pad'], '0', STR_PAD_LEFT);
    return [$number, $id];
}

function invLogIssuance(PDO $db, string $docType, string $number, array $invIds, string $category, string $printedBy): void {
    $t = ISSUANCE_TABLES[$docType];
    $db->prepare("INSERT INTO {$t['seq']} ({$t['numCol']}, Printed_by, Inv_ID, Inventory_Category) VALUES (?, ?, ?, ?)")
       ->execute([$number, $printedBy, implode(',', $invIds), $category]);
}

$data = getInput();

// ── Issue / reprint / renew a PAR or ICS number ──────────────────────────
if ($method === 'POST' && ($data['action'] ?? '') === 'issue') {
    $docType = strtolower(trim($data['doc_type'] ?? ''));
    $mode    = strtolower(trim($data['mode'] ?? 'new'));
    $poNumber = trim($data['po_number'] ?? '');
    $invIds  = array_values(array_filter(array_map('intval', $data['inv_ids'] ?? [])));

    if (!in_array($docType, ['par', 'ics'], true)) jsonResponse(false, 'Unknown document type.');
    if (!in_array($mode, ['new', 'reprint', 'renew'], true)) jsonResponse(false, 'Unknown mode.');
    if ($poNumber === '' || !$invIds) jsonResponse(false, 'Select at least one item to issue.');

    // Re-read fresh — never trust what the browser thinks these rows say,
    // since another operator may be acting on the same purchase order right
    // now, and printing the wrong number onto a government form is not a
    // mistake that is easy to walk back.
    $in = implode(',', array_fill(0, count($invIds), '?'));
    $stmt = $db->prepare(
        "SELECT Inv_ID, Items_Description, Unit_Cost, Inventory_Category, PAR_ICS_Number
         FROM tbl_inventory_items_details
         WHERE PO_Number = ? AND isDeleted = '0' AND Inv_ID IN ($in)"
    );
    $stmt->execute([$poNumber, ...$invIds]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($invIds)) jsonResponse(false, 'One or more selected items are no longer on file.');

    $category = $rows[0]['Inventory_Category'] ?: ($docType === 'par' ? 'PAR' : 'ICS');
    $actorName = getCurrentUser()['full_name'] ?? 'Someone';

    if ($mode === 'reprint') {
        // No number is generated and nothing is written — a reprint shows
        // exactly what was already issued. All selected rows must already
        // share one number; if they do not, there is nothing coherent to
        // reprint as a single document.
        $numbers = array_unique(array_filter(array_column($rows, 'PAR_ICS_Number')));
        if (count($numbers) !== 1) {
            jsonResponse(false, count($numbers) === 0
                ? 'These items have not been issued a number yet — use New instead.'
                : 'These items carry different numbers and cannot be reprinted together.');
        }
        jsonResponse(true, 'Reprinting the existing number.', ['number' => reset($numbers), 'mode' => 'reprint']);
    }

    if ($mode === 'renew') {
        $numbers = array_unique(array_filter(array_column($rows, 'PAR_ICS_Number')));
        if (count($numbers) !== 1) {
            jsonResponse(false, count($numbers) === 0
                ? 'These items have not been issued a number yet — use New instead.'
                : 'These items carry different numbers and cannot be renewed together.');
        }
        // Same "-R" + year suffix RENEW_PAR() / ICS_HIGH_VALUE_RENEW() use —
        // an annual re-acknowledgment of the same property, not a new item.
        $number = reset($numbers) . '-R' . date('Y');
    } else {
        [$number] = invNextNumber($db, $docType);
    }

    try {
        $db->beginTransaction();
        invLogIssuance($db, $docType, $number, $invIds, (string)$category, $actorName);
        $upd = $db->prepare(
            "UPDATE tbl_inventory_items_details SET PAR_ICS_Number = ? WHERE PO_Number = ? AND Inv_ID IN ($in)"
        );
        $upd->execute([$number, $poNumber, ...$invIds]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        jsonResponse(false, 'Could not issue the number: ' . $e->getMessage());
    }

    logAudit(strtoupper($docType) . '_' . strtoupper($mode), 'inventory',
        "{$actorName} " . ($mode === 'renew' ? 'renewed' : 'issued') . " {$number} on PO {$poNumber} ("
        . count($invIds) . ' item' . (count($invIds) === 1 ? '' : 's') . ')');
    jsonResponse(true, ($mode === 'renew' ? 'Renewed as ' : 'Issued ') . $number . '.',
        ['number' => $number, 'mode' => $mode, 'inv_ids' => $invIds]);
}

// ── Transfer (ITR / PTR) ──────────────────────────────────────────────────
// Replaces Transfer Form.vb's btnITRSave_Click / btnPTRSave_Click. The legacy
// form runs one UPDATE that overwrites Accountable_Officer / Position /
// Location / Fund_Cluster / Date_Transfered directly — the item forgets who
// held it before. Here the FROM side is captured into
// tbl_inventory_transfers / tbl_inventory_transfer_items FIRST, in the same
// transaction as the update, so the current-custodian columns still read
// exactly as they always have (nothing that reads them today has to change)
// while a full chain of custody is now kept alongside.
//
// Property Return Slip (PRS) is its OWN action below, not a third docType
// here — a return has its own shape (Condition, Reason for Return, Returned
// To) that doesn't map cleanly onto a transfer's "To another employee"
// fields, and its own tbl_inventory_prs/tbl_inventory_prs_items tables.
if ($method === 'POST' && ($data['action'] ?? '') === 'transfer') {
    $docType = strtolower(trim($data['doc_type'] ?? ''));
    $poNumber = trim($data['po_number'] ?? '');
    $invIds  = array_values(array_filter(array_map('intval', $data['inv_ids'] ?? [])));

    if (!in_array($docType, ['itr', 'ptr'], true)) jsonResponse(false, 'Unknown transfer type.');
    if ($poNumber === '' || !$invIds) jsonResponse(false, 'Select at least one item to transfer.');

    $toOfficer  = trim($data['to_officer'] ?? '');
    $toPosition = trim($data['to_position'] ?? '');
    $toLocation = trim($data['to_location'] ?? '');
    if ($toOfficer === '') jsonResponse(false, 'The receiving officer is required.');

    $in = implode(',', array_fill(0, count($invIds), '?'));
    $stmt = $db->prepare(
        "SELECT Inv_ID, Items_Description, Tag_Number, Serial_Number, Quantity, Unit_Cost,
                Accountable_Officer, Position, Location
         FROM tbl_inventory_items_details
         WHERE PO_Number = ? AND isDeleted = '0' AND Inv_ID IN ($in)"
    );
    $stmt->execute([$poNumber, ...$invIds]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($invIds)) jsonResponse(false, 'One or more selected items are no longer on file.');

    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    [$number] = invNextNumber($db, $docType);
    $fundCluster = trim($data['fund_cluster'] ?? '');
    $dateTransferred = (function ($v) {
        if (!$v) return date('Y-m-d H:i:s');
        $ts = strtotime($v);
        return $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    })($data['date_transferred'] ?? null);

    try {
        $db->beginTransaction();

        // One transfer event, "from" a snapshot of the FIRST selected row —
        // every selected item is moving to the same new custodian in one
        // action, so one FROM/TO pair describes the whole event; the item
        // detail underneath still records each line's own description/tag/
        // serial/cost as it stood at the moment of transfer.
        $from = $rows[0];
        $db->prepare(
            "INSERT INTO tbl_inventory_transfers
                (Transfer_Type, Transfer_Number, PO_Number,
                 From_Accountable_Officer, From_Position, From_Location,
                 To_Accountable_Officer, To_Position, To_Location,
                 Fund_Cluster, Reason_for_Transfer,
                 Approved_By, Approved_Position, Issued_By, Issued_Position,
                 Date_Transferred, Printed_By, Date_Created, isDeleted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)"
        )->execute([
            strtoupper($docType), $number, $poNumber,
            $from['Accountable_Officer'], $from['Position'], $from['Location'],
            $toOfficer, $toPosition, $toLocation,
            $fundCluster, trim($data['reason'] ?? ''),
            trim($data['approved_by'] ?? ''), trim($data['approved_position'] ?? ''),
            trim($data['issued_by'] ?? ''), trim($data['issued_position'] ?? ''),
            $dateTransferred, $actorName,
        ]);
        $transferId = (int)$db->lastInsertId();

        $itemStmt = $db->prepare(
            "INSERT INTO tbl_inventory_transfer_items
                (Transfer_ID, Inv_ID, Items_Description, Tag_Number, Serial_Number, Quantity, Unit_Cost)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($rows as $r) {
            $itemStmt->execute([
                $transferId, $r['Inv_ID'], $r['Items_Description'], $r['Tag_Number'],
                $r['Serial_Number'], $r['Quantity'], $r['Unit_Cost'],
            ]);
        }

        // The current-custodian columns, updated exactly as the legacy form
        // does — same columns, same semantics, just no longer the ONLY copy.
        $db->prepare(
            "UPDATE tbl_inventory_items_details
             SET Accountable_Officer = ?, Position = ?, Location = ?,
                 Fund_Cluster = ?, Date_Transfered = ?, PTR_ITR_Number = ?
             WHERE PO_Number = ? AND Inv_ID IN ($in)"
        )->execute([$toOfficer, $toPosition, $toLocation, $fundCluster, $dateTransferred, $number, $poNumber, ...$invIds]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        jsonResponse(false, 'Transfer failed: ' . $e->getMessage());
    }

    logAudit(strtoupper($docType) . '_TRANSFER', 'inventory',
        "{$actorName} recorded {$number} on PO {$poNumber}: " . count($invIds) . ' item(s) to '
        . $toOfficer . ($toLocation ? " ({$toLocation})" : ''));
    notifyPoUsers(
        strtoupper($docType) . ' Transfer Recorded',
        "{$actorName} transferred " . count($invIds) . ' item(s) on PO ' . $poNumber . " to {$toOfficer} ({$number})",
        'inventory.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber, 'all'
    );
    jsonResponse(true, 'Transfer recorded as ' . $number . '.', ['number' => $number, 'transfer_id' => $transferId]);
}

// ── Property Return Slip (PRS) ────────────────────────────────────────────
// A return to GSO custody. Own tables (tbl_inventory_prs/
// tbl_inventory_prs_items — see database/inventory_prs_tables.sql), own
// number derived from the row's own PRS_ID (prsNumberFromId() above) rather
// than a pre-allocated sequence — same transaction shape as the transfer
// action above (record first, then update the item's current-custodian
// columns), just with this document's own fields (Condition, Reason for
// Return, Returned To) instead of a transfer's To-officer/position/fund.
if ($method === 'POST' && ($data['action'] ?? '') === 'prs') {
    $poNumber = trim($data['po_number'] ?? '');
    $invIds  = array_values(array_filter(array_map('intval', $data['inv_ids'] ?? [])));
    if ($poNumber === '' || !$invIds) jsonResponse(false, 'Select at least one item to return.');

    $returnedTo = trim($data['returned_to'] ?? '');
    if ($returnedTo === '') jsonResponse(false, 'Where the property is returned to is required.');

    $in = implode(',', array_fill(0, count($invIds), '?'));
    $stmt = $db->prepare(
        "SELECT Inv_ID, Items_Description, Tag_Number, Serial_Number, Quantity, Unit_Cost,
                Accountable_Officer, Position, Location
         FROM tbl_inventory_items_details
         WHERE PO_Number = ? AND isDeleted = '0' AND Inv_ID IN ($in)"
    );
    $stmt->execute([$poNumber, ...$invIds]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($invIds)) jsonResponse(false, 'One or more selected items are no longer on file.');

    $actorName = getCurrentUser()['full_name'] ?? 'Someone';
    $from = $rows[0];
    $dateReturned = date('Y-m-d H:i:s');

    try {
        $db->beginTransaction();

        $db->prepare(
            "INSERT INTO tbl_inventory_prs
                (PO_Number, From_Accountable_Officer, From_Position, From_Location,
                 Returned_To, Condition_On_Return, Reason_for_Return,
                 Received_By, Received_Position, Approved_By, Approved_Position,
                 Date_Returned, Printed_By, Date_Created, isDeleted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)"
        )->execute([
            $poNumber, $from['Accountable_Officer'], $from['Position'], $from['Location'],
            $returnedTo, trim($data['condition'] ?? ''), trim($data['reason'] ?? ''),
            trim($data['received_by'] ?? ''), trim($data['received_position'] ?? ''),
            trim($data['approved_by'] ?? ''), trim($data['approved_position'] ?? ''),
            $dateReturned, $actorName,
        ]);
        $prsId = (int)$db->lastInsertId();
        $number = prsNumberFromId($prsId);
        $db->prepare('UPDATE tbl_inventory_prs SET PRS_Number = ? WHERE PRS_ID = ?')->execute([$number, $prsId]);

        $itemStmt = $db->prepare(
            "INSERT INTO tbl_inventory_prs_items
                (PRS_ID, Inv_ID, Items_Description, Tag_Number, Serial_Number, Quantity, Unit_Cost)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($rows as $r) {
            $itemStmt->execute([
                $prsId, $r['Inv_ID'], $r['Items_Description'], $r['Tag_Number'],
                $r['Serial_Number'], $r['Quantity'], $r['Unit_Cost'],
            ]);
        }

        // Custody returns to GSO — the accountable officer/position clear,
        // location becomes wherever it was returned to. Same columns the
        // transfer action above updates, so every existing view that reads
        // an item's current custodian (the grid, Personnel MR, Property
        // Card) reflects a return the same way it reflects a transfer.
        $db->prepare(
            "UPDATE tbl_inventory_items_details
             SET Accountable_Officer = '', Position = '', Location = ?, PTR_ITR_Number = ?
             WHERE PO_Number = ? AND Inv_ID IN ($in)"
        )->execute([$returnedTo, $number, $poNumber, ...$invIds]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        jsonResponse(false, 'Return failed: ' . $e->getMessage());
    }

    logAudit('PRS_RETURN', 'inventory',
        "{$actorName} recorded {$number} on PO {$poNumber}: " . count($invIds) . ' item(s) returned to ' . $returnedTo);
    notifyPoUsers(
        'Property Returned (PRS)',
        "{$actorName} returned " . count($invIds) . ' item(s) on PO ' . $poNumber . " to {$returnedTo} ({$number})",
        'inventory.php?po_number=' . urlencode($poNumber) . '&view=1',
        $poNumber, 'all'
    );
    jsonResponse(true, 'Return recorded as ' . $number . '.', ['number' => $number, 'prs_id' => $prsId]);
}

// ── Transfer history for a purchase order (stock movement) ──────────────
if ($method === 'GET' && isset($_GET['transfers'])) {
    $poNumber = trim($_GET['po_number'] ?? '');
    if ($poNumber === '') jsonResponse(false, 'Purchase order number is required.');

    $stmt = $db->prepare(
        "SELECT Transfer_ID AS transfer_id, Transfer_Type AS transfer_type, Transfer_Number AS transfer_number,
                From_Accountable_Officer AS from_officer, From_Position AS from_position, From_Location AS from_location,
                To_Accountable_Officer AS to_officer, To_Position AS to_position, To_Location AS to_location,
                Fund_Cluster AS fund_cluster, Reason_for_Transfer AS reason,
                Approved_By AS approved_by, Issued_By AS issued_by,
                Date_Transferred AS date_transferred, Printed_By AS printed_by
         FROM tbl_inventory_transfers WHERE PO_Number = ? AND isDeleted = 0 ORDER BY Transfer_ID DESC"
    );
    $stmt->execute([$poNumber]);
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($transfers) {
        $ids = array_column($transfers, 'transfer_id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT Transfer_ID AS transfer_id, Inv_ID AS inv_id, Items_Description AS items_description,
                    Tag_Number AS tag_number, Serial_Number AS serial_number, Quantity AS quantity, Unit_Cost AS unit_cost
             FROM tbl_inventory_transfer_items WHERE Transfer_ID IN ($in)"
        );
        $stmt->execute($ids);
        $itemsByTransfer = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $itemsByTransfer[(int)$r['transfer_id']][] = $r;
        foreach ($transfers as &$t) $t['items'] = $itemsByTransfer[(int)$t['transfer_id']] ?? [];
        unset($t);
    }

    jsonResponse(true, '', ['transfers' => fixLegacyEncodingDeep($transfers)]);
}

// ── Property Return Slip list — every PRS ever issued, across every PO ──
// (unlike the per-PO history above), from its own tables. Capped rather than
// unbounded since this is a system-wide log, not one purchase order's own
// history.
if ($method === 'GET' && isset($_GET['prs_list'])) {
    $stmt = $db->query(
        "SELECT PRS_ID AS prs_id, PRS_Number AS prs_number, PO_Number AS po_number,
                From_Accountable_Officer AS from_officer, From_Position AS from_position, From_Location AS from_location,
                Returned_To AS returned_to, Condition_On_Return AS condition_on_return, Reason_for_Return AS reason,
                Received_By AS received_by, Received_Position AS received_position,
                Approved_By AS approved_by, Approved_Position AS approved_position,
                Date_Returned AS date_returned, Printed_By AS printed_by
         FROM tbl_inventory_prs WHERE isDeleted = 0
         ORDER BY PRS_ID DESC LIMIT 200"
    );
    $prs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($prs) {
        $ids = array_column($prs, 'prs_id');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT PRS_ID AS prs_id, Inv_ID AS inv_id, Items_Description AS items_description,
                    Tag_Number AS tag_number, Serial_Number AS serial_number, Quantity AS quantity, Unit_Cost AS unit_cost
             FROM tbl_inventory_prs_items WHERE PRS_ID IN ($in)"
        );
        $stmt->execute($ids);
        $itemsByPrs = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $itemsByPrs[(int)$r['prs_id']][] = $r;
        foreach ($prs as &$p) $p['items'] = $itemsByPrs[(int)$p['prs_id']] ?? [];
        unset($p);
    }

    jsonResponse(true, '', ['prs' => fixLegacyEncodingDeep($prs)]);
}

jsonResponse(false, 'Unsupported request.');
