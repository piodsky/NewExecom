<?php
// Parses a CSV/.xlsx file of item rows for the Competitive Bidding modal's
// item grid — the sibling of purchase_order_item_parse.php, matched to this
// form's columns.
//
// Two differences from the purchase order version, both taken from the printed
// contract rather than invented here:
//
//   * the columns are QTY / Unit / Description / Unit Cost / Amount, so
//     "Amount" is accepted for what the PO calls Total Cost, and there is no
//     Number Series;
//   * a priced row needs an Amount and nothing more. The PO parser also demands
//     Unit, Qty and Unit Cost, which is stricter than its own API and would
//     reject a lump sum. On a bid contract that shape is the norm, not the
//     exception — the sample has one priced line and thirty-odd specification
//     lines under it — so the rule here is the same one the API enforces:
//     Amount is the test.
//
// The file needs a header row, and Description is the only column it must
// contain. Everything else is optional and may appear in any order, so a plain
// one-column list of specification lines is a valid file — which is exactly
// what a scope of work pasted out of a bid document looks like.
require_once '../config.php';
requireAuth();
requireModule('competitive_bidding');
header('Content-Type: application/json');
require_once __DIR__ . '/lib_spreadsheet.php';

// Header text -> field. Matched by name, so the file dictates the order.
// Both this form's wording and the purchase order's are accepted: people
// reuse an item list between the two documents, and rejecting a file for
// saying "Total Cost" instead of "Amount" would be pedantry.
$knownHeaders = [
    // Still recognised, still ignored. The item grid has no Item No. column
    // and nothing stores one, but a file exported from the printed contract
    // does carry that heading — rejecting it as "column not recognised"
    // would turn a perfectly good file into an error for no reason.
    'item no'          => 'ignored_item_no',
    'item no.'         => 'ignored_item_no',
    'item number'      => 'ignored_item_no',
    'description'      => 'items_description',
    'item description' => 'items_description',
    'unit'             => 'unit',
    'units'            => 'unit',
    'qty'              => 'quantity',
    'quantity'         => 'quantity',
    'unit cost'        => 'unit_cost',
    'amount'           => 'total_cost',
    'total cost'       => 'total_cost',
    'total'            => 'total_cost',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Only POST is allowed.');
}
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'Please upload a valid Excel or CSV file.');
}

$rows = readSpreadsheetRows($_FILES['file']);
if ($rows === null) {
    jsonResponse(false, 'Only .xlsx and .csv files are supported, and the file must be readable.');
}
if (count($rows) < 1) {
    jsonResponse(false, 'The file appears to be empty.');
}

// Trailing empties first: Excel writes a spare comma often enough that a
// perfectly good file arrives one column wider than it looks.
$header = array_map('normalizeHeader', trimTrailingEmptyCells($rows[0]));

$map     = [];
$unknown = [];
foreach ($header as $i => $name) {
    if ($name === '') continue;
    // Trailing full stops are dropped so "Item No." and "Item No" are the same
    // header — the printed form writes it with the stop.
    $key = rtrim(strtolower($name), '.');
    if (isset($knownHeaders[$key]))      $map[$knownHeaders[$key]] = $i;
    elseif (isset($knownHeaders[$key . '.'])) $map[$knownHeaders[$key . '.']] = $i;
    else $unknown[] = $name;
}

$quote = fn(array $a) => implode(', ', array_map(fn($v) => '"' . $v . '"', $a));

if (!isset($map['items_description'])) {
    $found = $quote(array_values(array_filter($header, fn($v) => $v !== '')));
    jsonResponse(false,
        'The file needs a "Description" column — that is the only one required. '
        . ($found !== '' ? 'Found instead: ' . $found . '.' : 'The first row was empty.')
        . ' QTY, Unit, Unit Cost and Amount are optional and can be in any order.'
    );
}
if ($unknown) {
    jsonResponse(false, 'These column headers were not recognised: ' . $quote($unknown)
        . '. Use any of: "QTY", "Unit", "Description", "Unit Cost", "Amount".');
}
if (count($rows) < 2) {
    jsonResponse(false, 'The file has a header row but no item rows below it.');
}

// Reads one cell by column name, or '' when the file omits that column.
$cell = function (array $row, string $field) use ($map): string {
    if (!isset($map[$field])) return '';
    return trim((string)($row[$map[$field]] ?? ''));
};

$items  = [];
$errors = [];
foreach (array_slice($rows, 1) as $index => $row) {
    $row = trimTrailingEmptyCells($row);
    if (!$row) continue; // fully-blank row
    $rowNo = $index + 2;

    // A description is the one thing no row can do without. A row carrying a
    // unit or a price but no description just puts a nameless line on a
    // contract, so it is a mistake worth naming rather than importing.
    if ($cell($row, 'items_description') === '') {
        $errors[] = 'Row ' . $rowNo . ' has no Description.';
        continue;
    }

    $unit   = $cell($row, 'unit');
    $qty    = $cell($row, 'quantity');
    $cost   = $cell($row, 'unit_cost');
    $amount = $cell($row, 'total_cost');

    // Two shapes, nothing in between — the same rule the grid and the API use.
    $isDescriptionOnly = ($unit === '' && $qty === '' && $cost === '' && $amount === '');

    // Amount is derivable, so a file that omits it is not an error — that is
    // arithmetic this can do itself, exactly as the grid does on typing.
    $num      = fn(string $v) => $v === '' ? '' : spreadsheetNumber($v);
    $amountOut = $num($amount);
    if (!$isDescriptionOnly && $amount === '' && $qty !== '' && $cost !== '') {
        $amountOut = number_format((float)spreadsheetNumber($qty) * (float)spreadsheetNumber($cost), 2, '.', '');
    }

    // Amount is the test, exactly as in cbInvalidItemRows(). A row that looks
    // priced but has no amount and nothing to derive one from would reach the
    // contract as a line with a hole in it.
    if (!$isDescriptionOnly && ($amountOut === '' || (float)$amountOut == 0)) {
        $errors[] = 'Row ' . $rowNo . ' has no Amount, and none can be worked out from QTY and Unit Cost. '
                  . 'Give it an Amount, or leave QTY, Unit and Unit Cost all blank to make it a '
                  . 'description-only line.';
        continue;
    }

    $items[] = [
        'items_description' => $cell($row, 'items_description'),
        'unit'              => $unit,
        'quantity'          => $num($qty),
        'unit_cost'         => $num($cost),
        'total_cost'        => $amountOut,
        // Told, not guessed. The grid infers a row's shape only when nothing
        // says what it is; saying so here keeps a heading that happens to read
        // like an item from flipping type on import.
        'is_title'          => $isDescriptionOnly,
    ];
}

if ($errors) {
    jsonResponse(false, 'Some rows could not be read: ' . implode(' ', $errors));
}
if (!$items) {
    jsonResponse(false, 'No item rows were found in the file.');
}
jsonResponse(true, count($items) . ' item(s) parsed.', ['items' => $items]);
