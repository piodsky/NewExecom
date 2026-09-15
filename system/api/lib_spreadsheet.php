<?php
// Shared CSV/.xlsx row reader — used by both the full-PO import
// (purchase_order_import.php) and the item-grid import
// (purchase_order_item_parse.php). No external library: PHP's built-in
// ZipArchive + SimpleXML are enough to read a worksheet, consistent with
// this repo's no-Composer/no-CDN convention.

function csvToArray($filePath) {
    $rows = [];
    if (($handle = fopen($filePath, 'r')) === false) return [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function normalizeHeader($value) {
    $value = (string)$value;
    // Excel writes a UTF-8 BOM at the front of every CSV it saves. It sticks to
    // the FIRST header cell, so "Unit" compares unequal to "Unit" while looking
    // identical in the error message — which is exactly as confusing as it
    // sounds. Strip it before anything else.
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
    // Real control characters. The previous list was single-quoted ('\r', '\n',
    // '\t'), so it matched the literal two-character sequences and stripped
    // nothing at all.
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    // Collapse runs of whitespace, including the non-breaking spaces that come
    // in when a header row is pasted from a web page or a Word table.
    $collapsed = preg_replace('/[\s\x{00A0}]+/u', ' ', $value);
    return trim($collapsed ?? $value);
}

// Drops trailing empty cells from a row.
//
// Excel very often writes one more comma than there are columns — a stray click
// in column G is enough — which made a 6-column file arrive as 7 and fail the
// header check against a list that looked identical.
function trimTrailingEmptyCells(array $row): array {
    $i = count($row) - 1;
    while ($i >= 0 && trim((string)$row[$i]) === '') { unset($row[$i]); $i--; }
    return array_values($row);
}

// Reads a number the way a spreadsheet actually writes one.
//
// is_numeric() rejects "1,250.00" and "P1,250.00", and the old code silently
// substituted 0 for both — importing an item priced at zero rather than
// refusing the file. Wrong data is worse than a rejected upload, so these are
// parsed instead.
//
// A comma is treated as a thousands separator, not a decimal point: this app
// formats money en-PH throughout, and "1,250.00" is by far the common case.
function spreadsheetNumber($value, $default = 0) {
    $s = trim((string)$value);
    if ($s === '')       return $default;
    if (is_numeric($s))  return $s;
    $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $s));
    return ($clean !== '' && $clean !== '-' && is_numeric($clean)) ? $clean : $default;
}

function xlsxColumnIndex($cellRef) {
    preg_match('/^([A-Z]+)/', $cellRef, $m);
    $col = 0;
    foreach (str_split($m[1] ?? 'A') as $ch) {
        $col = $col * 26 + (ord($ch) - 64);
    }
    return $col - 1;
}

// Reads the first worksheet of a .xlsx file: resolves shared strings, walks
// <row>/<c> cells by column letter, returns the same shape as csvToArray()
// (array of rows, each an array of cell strings).
function parseXlsxRows($filePath) {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return null;

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false && ($ss = @simplexml_load_string($ssXml))) {
        foreach ($ss->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string)$si->t;
            } else {
                $text = '';
                foreach ($si->r as $r) { $text .= (string)$r->t; }
                $sharedStrings[] = $text;
            }
        }
    }

    // Resolve the first sheet's actual file path via workbook.xml + its
    // rels (sheet order can differ from worksheets/sheet1.xml); fall back
    // to the conventional path if that resolution fails for any reason.
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wbXml   = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wbXml !== false && $relsXml !== false
        && ($wb = @simplexml_load_string($wbXml))
        && ($rels = @simplexml_load_string($relsXml))
        && isset($wb->sheets->sheet[0])
    ) {
        $rAttrs = $wb->sheets->sheet[0]->attributes('r', true);
        $rId = (string)($rAttrs['id'] ?? '');
        foreach ($rels->Relationship as $rel) {
            if ((string)$rel['Id'] === $rId) {
                $sheetPath = 'xl/' . ltrim((string)$rel['Target'], '/');
                break;
            }
        }
    }

    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheetXml === false) return null;
    $sheet = @simplexml_load_string($sheetXml);
    if (!$sheet) return null;

    $rows = [];
    foreach ($sheet->sheetData->row as $rowEl) {
        $rowIndex = ((int)$rowEl['r']) - 1;
        if ($rowIndex < 0) continue;
        $cells = [];
        foreach ($rowEl->c as $cellEl) {
            $colIndex = xlsxColumnIndex((string)$cellEl['r']);
            $type = (string)$cellEl['t'];
            if ($type === 's') {
                $value = $sharedStrings[(int)$cellEl->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string)($cellEl->is->t ?? '');
            } else {
                $value = (string)$cellEl->v;
            }
            $cells[$colIndex] = $value;
        }
        if (!empty($cells)) {
            $maxCol = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $maxCol; $i++) $line[] = $cells[$i] ?? '';
            $rows[$rowIndex] = $line;
        }
    }
    if (empty($rows)) return [];
    ksort($rows);
    $maxRow = max(array_keys($rows));
    $result = [];
    for ($i = 0; $i <= $maxRow; $i++) $result[] = $rows[$i] ?? [];
    return $result;
}

// Reads an uploaded spreadsheet ($_FILES-style array) by extension, returning
// rows in the csvToArray() shape, or null if the format is unsupported/unparsable.
function readSpreadsheetRows(array $file) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === 'csv') return csvToArray($file['tmp_name']);
    if ($ext === 'xlsx') return parseXlsxRows($file['tmp_name']);
    return null;
}
