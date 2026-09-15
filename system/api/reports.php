<?php
// The four report catalog screens from the legacy VB app (PO_Reports,
// PO_Items, POIAR_Reports, Inspection_Reports — see vb/Purchase Order/*.vb),
// each a date-range filtered listing over the live HRMIS purchase_request
// schema.
//
// GET  ?type=…&from=…&to=…              → JSON rows for the on-screen table
// GET  ?type=…&from=…&to=…&export=xlsx  → streams a real .xlsx download
//
// Column definitions live in ../report_defs.php so the screen, the PDF and
// the Excel export can never drift apart — see the comment there.
require_once '../config.php';
require_once '../report_defs.php';
requireReportsAdmin();
$db = getPoDB();

$type = $_GET['type'] ?? '';
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$defs = reportDefs();

if (!isset($defs[$type])) {
    header('Content-Type: application/json');
    jsonResponse(false, 'Unknown report type.');
}
if ($from === '' || $to === '') {
    header('Content-Type: application/json');
    jsonResponse(false, 'From and To dates are required.');
}

// Row caps, kept per-report since an items listing legitimately returns far
// more rows than a PO listing. We actually fetch cap+1 rows: if that extra
// row comes back we know the result was truncated and can say so, instead
// of silently handing back a cut-off list that looks complete.
const REPORT_ROW_CAPS = ['po' => 2000, 'inspection' => 2000, 'poiar' => 2000, 'po_items' => 5000];

/**
 * Returns [sql, params] for a report type.
 *
 * DATE(...) BETWEEN on both ends (not the raw datetime BETWEEN the VB forms
 * used) so the "To" day is included in full — a raw string BETWEEN truncates
 * "To" to 00:00:00 and silently drops that day's later records.
 */
function reportQuery(string $type, string $from, string $to, int $fetch): array {
    // Per-PO item totals. Two things matter here:
    //
    //  1. isDeleted = 0. Editing a PO soft-deletes its old item rows and
    //     inserts new ones (row-versioning — see save_po_update() in the VB
    //     source and the migration plan), so an unfiltered SUM adds every
    //     historical version together. POIAR_Reports.vb's own subquery
    //     omitted this filter and consequently reported inflated totals —
    //     often 2-6x, e.g. a real 890,000 PO showing as 5,340,000. Not
    //     reproduced here: this is a legacy bug, not a spec.
    //  2. The date filter. Without it this derived table aggregates all
    //     307k+ item rows on every run regardless of the range asked for,
    //     which is what made the POIAR report take ~20s.
    $costSubquery =
        "SELECT poid.PO_Number, SUM(poid.Total_Cost) AS Total_Cost
           FROM tbl_purchase_order_items_details poid
           INNER JOIN tbl_purchase_order rpo ON rpo.PO_Number = poid.PO_Number
          WHERE poid.isDeleted = 0 AND rpo.isDeleted = 0
            AND DATE(rpo.Date_Created) BETWEEN ? AND ?
          GROUP BY poid.PO_Number";

    switch ($type) {

        // PO_Reports.vb — every PO in range with its item totals.
        case 'po':
            return [
                "SELECT po.PO_Number AS po_number, po.PR_Number AS pr_number, po.Barcode_Number AS barcode_number,
                        po.Department AS department, po.Division AS division, po.Supplier_Name AS supplier_name,
                        po.Supplier_Address AS supplier_address, pod.Total_Cost AS total_cost, po.Purpose AS purpose,
                        po.Category AS category, po.Date_Created AS date_created, po.PR_Received_Name AS pr_received_name,
                        po.Date_Received_Supplier AS date_received_supplier, po.Date_COA_Received AS date_coa_received,
                        po.Date_Approved_PO AS date_approved_po, po.Remarks AS remarks, po.Date_PO AS date_po,
                        po.Mode_of_Procurement AS mode_of_procurement, po.Place_of_Delivery AS place_of_delivery,
                        po.Reference_No AS reference_no, po.BAC_Resolution_No AS bac_resolution_no,
                        po.PO_Date_Printed AS po_date_printed, po.Note AS note, po.End_User AS end_user
                   FROM tbl_purchase_order po
                   JOIN ($costSubquery) pod ON po.PO_Number = pod.PO_Number
                  WHERE DATE(po.Date_Created) BETWEEN ? AND ? AND po.isDeleted = 0
                  ORDER BY po.Date_Created DESC
                  LIMIT $fetch",
                [$from, $to, $from, $to],
            ];

        // PO_Items.vb ("PO with Items Reports") — one row per line item.
        case 'po_items':
            return [
                "SELECT PO_Number AS po_number, Items_Description AS items_description, Unit AS unit,
                        Quantity AS quantity, Unit_Cost AS unit_cost, Total_Cost AS total_cost,
                        Date_PO AS date_po, Date_Created AS date_created
                   FROM tbl_purchase_order_items_details
                  WHERE isDeleted = 0 AND DATE(Date_Created) BETWEEN ? AND ?
                  ORDER BY Date_Created DESC
                  LIMIT $fetch",
                [$from, $to],
            ];

        // POIAR_Reports.vb. Its WHERE had `i.isDeleted = 0` alongside a LEFT
        // JOIN to tbl_inspection, which silently turns that into an INNER
        // JOIN — dropping every not-yet-inspected PO instead of showing it
        // with blank inspection columns. Moved into the JOIN condition.
        case 'poiar':
            return [
                "SELECT po.PO_Number AS po_number, po.PR_Number AS pr_number, po.Department AS department,
                        po.Division AS division, po.Supplier_Name AS supplier_name, po.Supplier_Address AS supplier_address,
                        cost.Total_Cost AS total_cost, po.Purpose AS purpose, po.Category AS category,
                        po.Date_Created AS date_created, po.PR_Received_Name AS pr_received_name,
                        po.Date_Received_Supplier AS date_received_supplier, po.Date_COA_Received AS date_coa_received,
                        po.Date_Approved_PO AS date_approved_po, po.Remarks AS remarks, po.Date_PO AS date_po,
                        po.Mode_of_Procurement AS mode_of_procurement, po.Place_of_Delivery AS place_of_delivery,
                        po.Reference_No AS reference_no, po.BAC_Resolution_No AS bac_resolution_no,
                        po.PO_Date_Printed AS po_date_printed, po.Note AS note, po.End_User AS end_user,
                        i.Date_Inspected AS date_inspected, i.Invoice_Number AS invoice_number,
                        i.Date_BAC_Received AS date_bac_received, i.BARCODE_Number AS inspection_barcode_number,
                        i.Remarks AS inspection_remarks, i.Inspected_by_1 AS inspected_by_1, i.Inspected_by_2 AS inspected_by_2,
                        i.Inspected_by_3 AS inspected_by_3, i.Inspected_by_4 AS inspected_by_4,
                        i.End_User1 AS end_user1, i.End_User2 AS end_user2, i.End_User3 AS end_user3,
                        i.End_User4 AS end_user4, i.End_User5 AS end_user5,
                        i.Category AS inspection_category, i.Category_Explanation AS category_explanation,
                        i.Accepted_by AS accepted_by
                   FROM tbl_purchase_order po
                   LEFT JOIN tbl_inspection i ON po.PO_Number = i.PO_Number AND i.isDeleted = 0
                   LEFT JOIN ($costSubquery) cost ON po.PO_Number = cost.PO_Number
                  WHERE DATE(po.Date_Created) BETWEEN ? AND ? AND po.isDeleted = 0
                  ORDER BY po.Date_Created DESC
                  LIMIT $fetch",
                [$from, $to, $from, $to],
            ];

        // Inspection_Reports.vb. Unlike POIAR this INNER JOINs tbl_inspection
        // on purpose — it is specifically the list of POs that HAVE been
        // inspected, not every PO with the inspection columns left blank.
        case 'inspection':
            return [
                "SELECT po.PO_Number AS po_number, po.PR_Number AS pr_number, po.Department AS department,
                        po.Division AS division, po.Supplier_Name AS supplier_name, po.Supplier_Address AS supplier_address,
                        cost.Total_Cost AS total_cost, po.Purpose AS purpose, po.Category AS category,
                        po.Date_Created AS date_created, po.PR_Received_Name AS pr_received_name,
                        po.Date_Received_Supplier AS date_received_supplier, po.Date_COA_Received AS date_coa_received,
                        po.Date_Approved_PO AS date_approved_po, po.Remarks AS remarks, po.Date_PO AS date_po,
                        po.Mode_of_Procurement AS mode_of_procurement, po.Place_of_Delivery AS place_of_delivery,
                        po.Reference_No AS reference_no, po.BAC_Resolution_No AS bac_resolution_no,
                        po.PO_Date_Printed AS po_date_printed, po.Note AS note, po.End_User AS end_user,
                        ti.Invoice_Number AS invoice_number, ti.Date_BAC_Received AS date_bac_received,
                        ti.BARCODE_Number AS inspection_barcode_number, ti.Remarks AS inspection_remarks
                   FROM tbl_purchase_order po
                   INNER JOIN tbl_inspection ti ON po.PO_Number = ti.PO_Number AND ti.isDeleted = 0
                   -- INNER, not LEFT: the version this replaced joined the item
                   -- table directly, so a PO with no item rows never appeared.
                   INNER JOIN ($costSubquery) cost ON po.PO_Number = cost.PO_Number
                  WHERE DATE(po.Date_Created) BETWEEN ? AND ? AND po.isDeleted = 0
                  ORDER BY po.Date_Created DESC
                  LIMIT $fetch",
                // The derived table's two dates come first — it is built into
                // the FROM clause, ahead of the outer WHERE.
                [$from, $to, $from, $to],
            ];
    }
    return ['', []];
}

$cap   = REPORT_ROW_CAPS[$type] ?? 2000;
$fetch = $cap + 1;                       // +1 sentinel row — see REPORT_ROW_CAPS
[$sql, $params] = reportQuery($type, $from, $to, $fetch);

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_ASSOC));

$truncated = count($rows) > $cap;
if ($truncated) array_pop($rows);        // drop the sentinel

// Where the paper physically is, for the reports that carry a barcode.
$dtsNote = attachDtsLocations($rows);

// Fills a dts_location on every row from the document-tracking database.
//
// tasdb belongs to another team and is a separate connection (different
// credentials), so this cannot be a SQL join — it is one batched query on
// tas_inout, which has an index on BarCodeNo. Only 515 of the 18,334 live
// purchase orders carry a barcode at all, so the IN list stays small even on a
// full 2,000-row report.
//
// Returns a note when the column could not be filled, or null when it could.
// A report must never fail because somebody else's server is down, so every
// failure path here leaves the column blank and says why.
function attachDtsLocations(array &$rows): ?string {
    $needed = [];
    foreach ($rows as &$row) {
        if (!array_key_exists('barcode_number', $row)) return null;   // report has no barcode column
        $row['dts_location'] = '';
        $row['dts_status']   = '';
        $row['dts_remarks']  = '';
        $barcode = trim((string)$row['barcode_number']);
        if ($barcode !== '') $needed[$barcode] = true;
    }
    unset($row);
    if (!$needed) return null;

    if (!dtsIsConfigured()) {
        return 'Document Tracking is not set up on this server, so DTS Location is blank.';
    }
    try {
        $dts = getDtsDB();
    } catch (Throwable $e) {
        return 'The Document Tracking system could not be reached, so DTS Location is blank.';
    }

    // Per barcode: the hop it is sitting in (no DateOut yet), and the last hop
    // overall. Which one wins is decided after all rows are in — exactly the
    // rule dtsDocument() uses on the Document Trail screen.
    $open = [];
    $last = [];
    try {
        // Chunked so the IN list stays a sane length whatever the cap is set to.
        foreach (array_chunk(array_keys($needed), 500) as $chunk) {
            $in   = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $dts->prepare(
                "SELECT BarCodeNo, OfficeFrom, OfficeTo, DateIn, DateOut, Remarks
                   FROM tas_inout WHERE BarCodeNo IN ({$in})
                  ORDER BY BarCodeNo, DateIn, DateOut"
            );
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $hop) {
                $office = trim((string)$hop['OfficeTo']) !== '' ? $hop['OfficeTo'] : $hop['OfficeFrom'];
                $hop['office'] = trim((string)$office);
                if ($hop['office'] === '') continue;
                $code = (string)$hop['BarCodeNo'];
                // Rows arrive in trail order, so plain assignment leaves the
                // LAST one of each kind — which is the point. An earlier version
                // kept the first released hop instead and reported the office a
                // document left weeks ago.
                $last[$code] = $hop;
                if (dtsIsBlankDate($hop['DateOut'])) $open[$code] = $hop;
            }
        }
    } catch (Throwable $e) {
        error_log('DTS location lookup failed: ' . $e->getMessage());
        return 'The Document Tracking lookup failed, so DTS Location is blank.';
    }

    $days = function ($stamp): ?int {
        if (dtsIsBlankDate($stamp)) return null;
        $t = strtotime((string)$stamp);
        return $t ? (int)floor((time() - $t) / 86400) : null;
    };
    // "today" already reads as a time, so it must not collect an "ago" — the
    // first cut printed "Released today ago".
    $since = function (?int $d): string {
        if ($d === null) return '';
        if ($d <= 0)     return 'today';
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    };
    $forDays = function (?int $d): string {
        if ($d === null) return '';
        if ($d <= 0)     return 'since today';
        return $d . ' day' . ($d === 1 ? '' : 's');
    };

    $found = 0;
    foreach ($rows as &$row) {
        $barcode = trim((string)$row['barcode_number']);
        if ($barcode === '') continue;

        // Sitting in an office beats "last place it passed through".
        $hop = $open[$barcode] ?? $last[$barcode] ?? null;
        if (!$hop) continue;

        $row['dts_location'] = $hop['office'];
        $row['dts_remarks']  = trim((string)($hop['Remarks'] ?? ''));
        if (isset($open[$barcode])) {
            // Received and not released: someone is holding it, and how long
            // is the part that says whether it is stuck.
            $held = $days($hop['DateIn']);
            $row['dts_status'] = $held === null ? 'Held' : 'Held ' . $forDays($held);
        } else {
            // Released with nobody having logged it in yet. The next office is
            // not knowable until they receive it, so name the one that let it go.
            $out = $days($hop['DateOut']);
            $row['dts_status'] = trim('Released ' . $since($out)) . ' — in transit';
        }
        $found++;
    }
    unset($row);

    return $found === 0
        ? 'None of the barcodes in this range are on file in Document Tracking.'
        : null;
}

// ── Excel export ────────────────────────────────────────────────────────
// Hand-rolled minimal .xlsx via ZipArchive + SimpleXML-shaped strings, per
// this repo's no-Composer / no-build-step convention (same reasoning as the
// Excel import in the migration plan). Inline strings rather than a shared
// string table: simpler, and these exports are read-once files, not
// documents anyone re-edits at scale.
if (($_GET['export'] ?? '') === 'xlsx') {
    exportReportXlsx($type, $defs[$type], $rows, $from, $to, $truncated, $cap);
    exit;
}

header('Content-Type: application/json');
jsonResponse(true, '', [
    'rows'      => $rows,
    'truncated' => $truncated,
    'cap'       => $cap,
    'dts_note'  => $dtsNote,
]);


// ── .xlsx writer ────────────────────────────────────────────────────────

/** 1-based column index → spreadsheet column letters (1→A, 27→AA, 40→AN). */
function xlsxColumnLetter(int $n): string {
    $s = '';
    while ($n > 0) {
        $m = ($n - 1) % 26;
        $s = chr(65 + $m) . $s;
        $n = intdiv($n - 1 - $m, 26);
    }
    return $s;
}

/**
 * XML-escapes a cell value and strips control characters. The source tables
 * are latin1 and hold legacy free text, which can carry bytes that are legal
 * in the DB but illegal in XML — leaving them in produces a .xlsx that Excel
 * refuses to open with an unhelpful "unreadable content" error.
 */
function xlsxText($value): string {
    $s = (string)$value;
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function exportReportXlsx(string $type, array $def, array $rows, string $from, string $to, bool $truncated, int $cap): void {
    $columns = $def['columns'];

    // Style indexes match cellXfs order in the styles.xml below:
    // 0 = default, 1 = bold white on navy (header), 2 = 2-decimal number,
    // 3 = bold (title/meta lines).
    $sheet  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

    // Freeze the header row so 40-column reports stay navigable.
    $sheet .= '<sheetViews><sheetView workbookViewId="0">'
            . '<pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>';

    $sheet .= '<sheetData>';

    // Row 1: report title. Row 2: date range + row count. Row 3: headers.
    $sheet .= '<row r="1"><c r="A1" t="inlineStr" s="3"><is><t>' . xlsxText($def['pdfTitle']) . '</t></is></c></row>';
    $meta = 'Date Range: ' . $from . ' to ' . $to . '  |  ' . count($rows) . ' record(s)'
          . ($truncated ? '  |  NOTE: capped at the first ' . $cap . ' rows — narrow the date range to see the rest' : '');
    $sheet .= '<row r="2"><c r="A2" t="inlineStr"><is><t>' . xlsxText($meta) . '</t></is></c></row>';

    $sheet .= '<row r="3">';
    foreach ($columns as $i => $col) {
        $ref = xlsxColumnLetter($i + 1) . '3';
        $sheet .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . xlsxText($col['label']) . '</t></is></c>';
    }
    $sheet .= '</row>';

    $r = 4;
    foreach ($rows as $row) {
        $sheet .= '<row r="' . $r . '">';
        foreach ($columns as $i => $col) {
            $ref = xlsxColumnLetter($i + 1) . $r;
            $raw = $row[$col['key']] ?? '';
            $fmt = $col['fmt'] ?? '';
            // Numbers go in as real numbers (not pre-formatted strings) so
            // they stay sortable and summable inside Excel.
            if (($fmt === 'currency' || $fmt === 'number') && is_numeric($raw)) {
                $sheet .= '<c r="' . $ref . '" s="2"><v>' . (0 + $raw) . '</v></c>';
            } elseif ($raw !== null && $raw !== '') {
                $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . xlsxText($raw) . '</t></is></c>';
            }
        }
        $sheet .= '</row>';
        $r++;
    }
    $sheet .= '</sheetData></worksheet>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
        . '<fonts count="3">'
        .   '<font><sz val="11"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        .   '<font><b/><sz val="13"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        .   '<fill><patternFill patternType="none"/></fill>'
        .   '<fill><patternFill patternType="gray125"/></fill>'
        .   '<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4">'
        .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .   '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        .   '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $tmp = tempnam(sys_get_temp_dir(), 'rpt') . '.xlsx';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        header('Content-Type: application/json');
        jsonResponse(false, 'Unable to create the Excel file.');
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $filename = $type . '-report-' . $from . '_to_' . $to . '.xlsx';
    logAudit('EXPORT_REPORT', 'reports', "Exported {$type} report ({$from} to {$to}) as Excel");

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
    unlink($tmp);
}
