<?php
require_once '../config.php';
requireAuth();
requireModule('purchase_orders');
header('Content-Type: application/json');
require_once __DIR__ . '/lib_spreadsheet.php';
$db = getPoDB();

$allowedHeaders = [
    'PO Number', 'PR Number', 'Department', 'Division', 'Section', 'Supplier Name', 'Supplier Address',
    'Purpose', 'Category', 'Date PO', 'PR Received Name', 'Date Received Supplier', 'Date COA Received',
    'Date Approved PO', 'Remarks', 'Reference No', 'BAC Resolution No', 'Mode of Procurement',
    'Place of Delivery', 'Working Days', 'Payment Term', 'Note', 'End User',
    'Item ID', 'Unit', 'Item Description', 'Quantity', 'Unit Cost', 'Total Cost', 'Number Series'
];

// Same contract as formatPoNumber() in api/purchase_orders.php: built from an
// id the database has already allocated, and an UNPADDED month (date('n')) so
// imported numbers match the 26,000+ legacy rows instead of the padded variant
// the old code emitted.
function formatPoNumber($fundType, int $id): string {
    $prefix = in_array($fundType, ['PO', 'TF', 'SEF'], true) ? $fundType : 'PO';
    return sprintf('%s-sys%s%s%05d', $prefix, date('Y'), date('n'), $id);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Only POST is allowed.');
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'Please upload a valid Excel or CSV file.');
}

$file = $_FILES['file'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$rows = [];
if ($ext === 'csv') {
    $rows = csvToArray($file['tmp_name']);
} elseif ($ext === 'xlsx') {
    $rows = parseXlsxRows($file['tmp_name']);
    if ($rows === null) jsonResponse(false, 'Unable to parse the uploaded Excel file.');
} else {
    jsonResponse(false, 'Only .xlsx and .csv files are supported.');
}

if (count($rows) < 2) {
    jsonResponse(false, 'The import file must contain a header row and at least one data row.');
}

// Same tolerances as the item import: strip Excel's BOM and its habit of
// adding one more comma than there are columns, and compare case-insensitively.
$header = array_map('normalizeHeader', trimTrailingEmptyCells($rows[0]));
if (array_map('strtolower', $header) !== array_map('strtolower', $allowedHeaders)) {
    jsonResponse(false, 'Invalid template headers. Please use the provided import template.');
}

$orders = [];
$errors = [];
$existingPoNumbers = [];
foreach (array_slice($rows, 1) as $index => $row) {
    $row = array_pad(trimTrailingEmptyCells($row), count($allowedHeaders), '');
    if (count($row) !== count($allowedHeaders)) {
        $errors[] = "Row " . ($index + 2) . " must contain " . count($allowedHeaders) . " columns.";
        continue;
    }
    $data = array_combine($allowedHeaders, array_map('trim', $row));
    if ($data['PO Number'] !== '') {
        $existingPoNumbers[] = $data['PO Number'];
    }
    $key = $data['PO Number'] ?: 'ROW_' . ($index + 2);
    if (!isset($orders[$key])) {
        $orders[$key] = [
            'po_number' => $data['PO Number'],
            'pr_number' => $data['PR Number'],
            'department' => $data['Department'],
            'division' => $data['Division'],
            'section' => $data['Section'],
            'supplier_name' => $data['Supplier Name'],
            'supplier_address' => $data['Supplier Address'],
            'purpose' => $data['Purpose'],
            'category' => $data['Category'],
            'date_po' => $data['Date PO'],
            'pr_received_name' => $data['PR Received Name'],
            'date_received_supplier' => $data['Date Received Supplier'],
            'date_coa_received' => $data['Date COA Received'],
            'date_approved_po' => $data['Date Approved PO'],
            'remarks' => $data['Remarks'],
            'reference_no' => $data['Reference No'],
            'bac_resolution_no' => $data['BAC Resolution No'],
            'mode_of_procurement' => $data['Mode of Procurement'],
            'place_of_delivery' => $data['Place of Delivery'],
            'po_working_days' => $data['Working Days'],
            'po_payment_term' => $data['Payment Term'],
            'note' => $data['Note'],
            'end_user' => $data['End User'],
            'items' => [],
            'rows' => [],
        ];
    }
    if ($data['Item Description'] === '') {
        $errors[] = "Row " . ($index + 2) . " is missing Item Description.";
        continue;
    }
    $orders[$key]['items'][] = [
        'item_id' => $data['Item ID'],
        'unit' => $data['Unit'],
        'items_description' => $data['Item Description'],
        'quantity' => spreadsheetNumber($data['Quantity']),
        'unit_cost' => spreadsheetNumber($data['Unit Cost']),
        'total_cost' => spreadsheetNumber($data['Total Cost']),
        'number_series' => $data['Number Series'],
    ];
    $orders[$key]['rows'][] = $index + 2;
}

if (!empty($existingPoNumbers)) {
    $placeholders = implode(',', array_fill(0, count($existingPoNumbers), '?'));
    $stmt = $db->prepare("SELECT PO_Number FROM tbl_purchase_order WHERE PO_Number IN ($placeholders) AND isDeleted = 0");
    $stmt->execute($existingPoNumbers);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($existing as $duplicate) {
        $errors[] = "PO Number {$duplicate} already exists in the system.";
    }
}

if ($errors) {
    jsonResponse(false, 'Validation failed.', ['errors' => $errors]);
}

$imported = 0;
try {
    $db->beginTransaction();
    foreach ($orders as $key => $order) {
        // Same two-step as the Create handler: a spreadsheet row without its
        // own PO number is inserted under a placeholder, then renumbered from
        // the id the database allocated.
        $autoNumber     = !$order['po_number'];
        $poNumber       = $order['po_number'] ?: '';
        $insertPoNumber = $autoNumber ? '~NEW-' . bin2hex(random_bytes(6)) : $poNumber;
        $datePo = $order['date_po'] ? date('Y-m-d H:i:s', strtotime($order['date_po'])) : date('Y-m-d H:i:s');
        $dateCreated = date('Y-m-d H:i:s');
        $insertOrder = $db->prepare('INSERT INTO tbl_purchase_order (PO_Number, PR_Number, PR_Received_Name, Category, Department, Division, Section, Reference_No, BAC_Resolution_No, Supplier_Name, Supplier_Address, Date_PO, Purpose, End_User, Date_Created, isDeleted, Mode_of_Procurement, Place_of_Delivery, PO_Working_Days, PO_Payment_Term, Note, Requested_by, Barcode_Number, Date_Received_Supplier, Date_COA_Received, Date_Approved_PO, Remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insertOrder->execute([
            $insertPoNumber,
            $order['pr_number'],
            $order['pr_received_name'],
            $order['category'],
            // Snapped onto the reference tables' own spelling — a spreadsheet is
            // the single biggest source of supplier-name variants.
            canonicalDepartment($db, $order['department']),
            canonicalDivision($db, $order['division']),
            canonicalSection($db, $order['section']),
            $order['reference_no'],
            $order['bac_resolution_no'],
            canonicalSupplierName($db, $order['supplier_name']),
            $order['supplier_address'],
            $datePo,
            $order['purpose'],
            $order['end_user'],
            $dateCreated,
            $order['mode_of_procurement'],
            $order['place_of_delivery'],
            $order['po_working_days'],
            $order['po_payment_term'],
            $order['note'],
            '',
            // Same default as the Create form (see the note in
            // api/purchase_orders.php): a bulk-imported PO gets the scannable
            // form of its PO number rather than a blank barcode number. For an
            // auto-numbered row the number is not known yet, so this is set in
            // the renumbering UPDATE below.
            $autoNumber ? '' : (dtsBarcodeForPrNumber($order['pr_number'] ?? '') ?? ''),
            $order['date_received_supplier'] ? date('Y-m-d H:i:s', strtotime($order['date_received_supplier'])) : null,
            $order['date_coa_received'] ? date('Y-m-d H:i:s', strtotime($order['date_coa_received'])) : null,
            $order['date_approved_po'] ? date('Y-m-d H:i:s', strtotime($order['date_approved_po'])) : null,
            $order['remarks'],
        ]);

        if ($autoNumber) {
            $newId    = (int)$db->lastInsertId();
            $poNumber = formatPoNumber($order['category'] ?: 'GEN', $newId);
            $db->prepare('UPDATE tbl_purchase_order SET PO_Number = ?, Barcode_Number = ? WHERE ID = ?')
               ->execute([$poNumber, dtsBarcodeForPrNumber($order['pr_number'] ?? '') ?? '', $newId]);
        }

        $itemStmt = $db->prepare('INSERT INTO tbl_purchase_order_items_details (PO_Number, Item_ID, Unit, Items_Description, Quantity, Unit_Cost, Total_Cost, NumberSeries, Date_PO, Date_Created, End_User, isDeleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
        foreach ($order['items'] as $item) {
            $itemId = null;
            if ($item['items_description']) {
                $itemId = null;
                $stmtCatalog = $db->prepare('SELECT Item_ID FROM tbl_ppmp_bac_items WHERE Items_Description = ? AND isDeleted = 0 LIMIT 1');
                $stmtCatalog->execute([$item['items_description']]);
                $existingCatalog = $stmtCatalog->fetch(PDO::FETCH_ASSOC);
                if ($existingCatalog) {
                    $itemId = $existingCatalog['Item_ID'];
                } else {
                    $insertCatalog = $db->prepare('INSERT INTO tbl_ppmp_bac_items (Unit, Items_Description, Unit_Cost, isDeleted) VALUES (?, ?, ?, 0)');
                    $insertCatalog->execute([$item['unit'], $item['items_description'], $item['unit_cost']]);
                    $itemId = $db->lastInsertId();
                }
            }
            $itemStmt->execute([
                $poNumber,
                $itemId,
                $item['unit'],
                $item['items_description'],
                $item['quantity'],
                $item['unit_cost'],
                $item['total_cost'],
                $item['number_series'],
                $datePo,
                $dateCreated,
                $order['end_user'],
            ]);
        }
        $imported++;
    }
    $db->commit();
    logAudit('IMPORT_PO', 'purchase_orders', "Imported {$imported} purchase order(s) from Excel.");
    jsonResponse(true, 'Import completed successfully.', ['imported_count' => $imported]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('PO import failed: ' . $e->getMessage());
    jsonResponse(false, 'Import failed and nothing was saved. The reason has been written to the server error log.');
}
