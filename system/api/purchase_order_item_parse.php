<?php
// Parses a CSV/.xlsx file of item rows for the Create/Edit Purchase Order
// modal's item grid — a lighter-weight sibling of purchase_order_import.php
// (which imports whole purchase orders).
//
// The file needs a header row, and the only column it must contain is
// Description. Unit, Qty, Unit Cost, Total Cost and Number Series are all
// optional and may appear in any order — columns are matched by name, so a
// plain one-column list of descriptions is a valid file. That is the common
// case: a scope of work under a LOT has no unit, quantity or cost, and those
// cells stay blank all the way through to the printed form.
// purchase_order_item_import_template.csv is a ready-made starting file.
require_once '../config.php';
requireAuth();
requireModule('purchase_orders');
header('Content-Type: application/json');
require_once __DIR__ . '/lib_spreadsheet.php';

// Description is the only column that must be present. Everything else is
// optional and may appear in any order: plenty of real item lists are nothing
// but a column of descriptions (a scope of work under a LOT has no unit, no
// quantity and no cost), and demanding six columns in a fixed order for that
// is busywork. Headers are matched by name, so the file dictates the order.
$knownHeaders = [
    'description'   => 'items_description',
    'item description' => 'items_description', // the printed form's own wording
    'unit'          => 'unit',
    'units'         => 'unit',
    'qty'           => 'quantity',
    'quantity'      => 'quantity',
    'unit cost'     => 'unit_cost',
    'total cost'    => 'total_cost',
    'total'         => 'total_cost',
    'number series' => 'number_series',
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

// Column name -> position in the file.
$map     = [];
$unknown = [];
foreach ($header as $i => $name) {
    if ($name === '') continue;
    $key = strtolower($name);
    if (isset($knownHeaders[$key])) {
        $map[$knownHeaders[$key]] = $i;
    } else {
        $unknown[] = $name;
    }
}

if (!isset($map['items_description'])) {
    $quote = fn(array $a) => implode(', ', array_map(fn($v) => '"' . $v . '"', $a));
    $found = $quote(array_values(array_filter($header, fn($v) => $v !== '')));
    jsonResponse(false,
        'The file needs a "Description" column — that is the only one required. '
        . ($found !== '' ? 'Found instead: ' . $found . '.' : 'The first row was empty.')
        . ' Unit, Qty, Unit Cost, Total Cost and Number Series are optional and can be in any order.'
    );
}
if ($unknown) {
    $quote = fn(array $a) => implode(', ', array_map(fn($v) => '"' . $v . '"', $a));
    jsonResponse(false, 'These column headers were not recognised: ' . $quote($unknown)
        . '. Use any of: "Description", "Unit", "Qty", "Unit Cost", "Total Cost", "Number Series".');
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

    // A description is the one thing no row can do without — a row carrying a
    // unit or a price but no description is a mistake, and letting it through
    // just puts a nameless line on a purchase order.
    if ($cell($row, 'items_description') === '') {
        $errors[] = 'Row ' . $rowNo . ' has no Description.';
        continue;
    }

    $unit  = $cell($row, 'unit');
    $qty   = $cell($row, 'quantity');
    $cost  = $cell($row, 'unit_cost');
    $total = $cell($row, 'total_cost');

    // Two legitimate shapes, and nothing in between:
    //
    //   title row — "LOT 1", "SCOPE OF WORK:", a spec line. EVERY numeric cell
    //               is blank, and it prints as a heading with empty columns.
    //   item row  — a real purchase. Unit, Qty and Unit Cost are all filled.
    //
    // A row with some of them filled and some blank is neither, and it is
    // always a mistake: it reaches the printed form as a priced line with a
    // hole in it, or as a heading that quietly carries a cost into the total.
    // Catching it here is the whole point — the old parser accepted it and
    // silently wrote 0.
    $isTitle = ($unit === '' && $qty === '' && $cost === '' && $total === '');
    if (!$isTitle) {
        // Only demand a column the file actually provides. A deliberately
        // Unit-less file is the author's choice; a Unit column left half-empty
        // is not.
        $missing = [];
        foreach (['unit' => 'Unit', 'quantity' => 'Qty', 'unit_cost' => 'Unit Cost'] as $field => $label) {
            if (isset($map[$field]) && $cell($row, $field) === '') $missing[] = $label;
        }
        if ($missing) {
            $errors[] = 'Row ' . $rowNo . ' is missing ' . implode(', ', $missing)
                      . '. An item row needs Unit, Qty and Unit Cost — leave every one of them '
                      . 'blank if it is a title row like "LOT 1".';
            continue;
        }
    }

    // Blank stays blank, so a title line prints as an empty cell rather than
    // 0.00. Only a real number becomes a number.
    $num = function (string $v) { return $v === '' ? '' : spreadsheetNumber($v); };

    // Total Cost is derivable, so a file that omits it is not an error — it is
    // arithmetic this can do itself, exactly as the item grid does on typing.
    $totalOut = $num($total);
    if (!$isTitle && $total === '' && $qty !== '' && $cost !== '') {
        $totalOut = number_format((float)spreadsheetNumber($qty) * (float)spreadsheetNumber($cost), 2, '.', '');
    }

    $items[] = [
        'unit'              => $unit,
        'items_description' => $cell($row, 'items_description'),
        'quantity'          => $num($qty),
        'unit_cost'         => $num($cost),
        'total_cost'        => $totalOut,
        'number_series'     => $cell($row, 'number_series'),
    ];
}

if ($errors) {
    jsonResponse(false, 'Some rows could not be read: ' . implode(' ', $errors));
}
if (!$items) {
    jsonResponse(false, 'No item rows were found in the file.');
}
jsonResponse(true, count($items) . ' item(s) parsed.', ['items' => $items]);
