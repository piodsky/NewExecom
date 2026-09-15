<?php
// Single source of truth for the four report definitions — their labels,
// descriptions and (most importantly) their column lists.
//
// Shared deliberately: reports.php renders the on-screen table and the PDF
// from this, api/reports.php builds the .xlsx export from it. When the
// column list lived only in reports.php's JavaScript, the Excel export had
// no way to know the human labels without duplicating all 40 of them, and
// any future column change would silently drift between the screen and the
// exports. One list, four consumers.
//
// 'fmt' is a NAME, not a function, so the same definition works on both
// sides: JS maps it to a formatter in reports.php, PHP maps it to a cell
// type/format in the xlsx writer below.

// The 23 columns every tbl_purchase_order-based report starts from.
const REPORT_PO_BASE_COLUMNS = [
    ['key' => 'po_number',              'label' => 'PO Number'],
    ['key' => 'pr_number',              'label' => 'PR Number'],
    ['key' => 'department',             'label' => 'Department'],
    ['key' => 'division',               'label' => 'Division'],
    ['key' => 'supplier_name',          'label' => 'Supplier Name'],
    ['key' => 'supplier_address',       'label' => 'Supplier Address'],
    ['key' => 'total_cost',             'label' => 'Total Cost',              'fmt' => 'currency'],
    ['key' => 'purpose',                'label' => 'Purpose'],
    ['key' => 'category',               'label' => 'Category'],
    ['key' => 'date_created',           'label' => 'Date Created',            'fmt' => 'datetime'],
    ['key' => 'pr_received_name',       'label' => 'PR Received By'],
    ['key' => 'date_received_supplier', 'label' => 'Date Received (Supplier)'],
    ['key' => 'date_coa_received',      'label' => 'COA Date Received'],
    ['key' => 'date_approved_po',       'label' => 'Approved PO Date'],
    ['key' => 'remarks',                'label' => 'Remarks'],
    ['key' => 'date_po',                'label' => 'PO Date',                 'fmt' => 'datetime'],
    ['key' => 'mode_of_procurement',    'label' => 'Mode of Procurement'],
    ['key' => 'place_of_delivery',      'label' => 'Place of Delivery'],
    ['key' => 'reference_no',           'label' => 'Reference No.'],
    ['key' => 'bac_resolution_no',      'label' => 'BAC Resolution No.'],
    ['key' => 'po_date_printed',        'label' => 'Date Printed',            'fmt' => 'datetime'],
    ['key' => 'note',                   'label' => 'Note'],
    ['key' => 'end_user',               'label' => 'End User'],
];

const REPORT_INSPECTION_EXTRA = [
    ['key' => 'invoice_number',            'label' => 'Invoice Number'],
    ['key' => 'date_bac_received',         'label' => 'Date BAC Received',   'fmt' => 'datetime'],
    ['key' => 'inspection_barcode_number', 'label' => 'Inspection Barcode No.'],
    ['key' => 'inspection_remarks',        'label' => 'Inspection Remarks'],
];

function reportDefs(): array {
    $base = REPORT_PO_BASE_COLUMNS;

    return [
        'po' => [
            'label'      => 'Purchase Order Reports',
            'pdfTitle'   => 'PURCHASE ORDER REPORT',
            'desc'       => 'Every Purchase Order in a date range, with the totals from its item lines.',
            'emptyLabel' => 'purchase order',
            'icon'       => 'fa-file-invoice-dollar',
            'accent'     => 'blue',
            // Barcode_Number is selected by the legacy PO Reports.vb query
            // (and by ours), so it belongs on screen too — it was being
            // fetched but never shown before.
            // DTS Location comes from the other team's document-tracking
            // database (tasdb), matched on the barcode, and answers the
            // question this report could not before: which office is holding
            // the paper right now. Blank when the PO has no barcode, when the
            // barcode is not in DTS, or when DTS cannot be reached — the
            // response carries a note explaining which.
            'columns'    => array_merge($base, [
                ['key' => 'barcode_number', 'label' => 'Barcode Number'],
                ['key' => 'dts_location',   'label' => 'DTS Location'],
                ['key' => 'dts_status',     'label' => 'DTS Status'],
                ['key' => 'dts_remarks',    'label' => 'DTS Remarks'],
            ]),
        ],
        'inspection' => [
            'label'      => 'Inspection and Acceptance Reports',
            'pdfTitle'   => 'INSPECTION AND ACCEPTANCE REPORT',
            'desc'       => 'Purchase Orders that have gone through inspection — includes barcode, invoice, and BAC-received info.',
            'emptyLabel' => 'inspection and acceptance',
            'icon'       => 'fa-clipboard-check',
            'accent'     => 'green',
            'columns'    => array_merge($base, REPORT_INSPECTION_EXTRA),
        ],
        'poiar' => [
            'label'      => 'POIAR Reports',
            'pdfTitle'   => 'POIAR REPORT',
            'desc'       => 'Every Purchase Order with its full inspection & acceptance record — blank inspection columns just mean it has not been inspected yet.',
            'emptyLabel' => 'POIAR',
            'icon'       => 'fa-clipboard-list',
            'accent'     => 'purple',
            'columns'    => array_merge($base, [
                ['key' => 'date_inspected',            'label' => 'Date Inspected', 'fmt' => 'datetime'],
                ['key' => 'invoice_number',            'label' => 'Invoice Number'],
                ['key' => 'date_bac_received',         'label' => 'Date BAC Received', 'fmt' => 'datetime'],
                ['key' => 'inspection_barcode_number', 'label' => 'Inspection Barcode No.'],
                ['key' => 'inspection_remarks',        'label' => 'Inspection Remarks'],
                ['key' => 'inspected_by_1',            'label' => 'Inspected By 1'],
                ['key' => 'inspected_by_2',            'label' => 'Inspected By 2'],
                ['key' => 'inspected_by_3',            'label' => 'Inspected By 3'],
                ['key' => 'inspected_by_4',            'label' => 'Inspected By 4'],
                ['key' => 'end_user1',                 'label' => 'End User 1'],
                ['key' => 'end_user2',                 'label' => 'End User 2'],
                ['key' => 'end_user3',                 'label' => 'End User 3'],
                ['key' => 'end_user4',                 'label' => 'End User 4'],
                ['key' => 'end_user5',                 'label' => 'End User 5'],
                ['key' => 'inspection_category',       'label' => 'Inspection Category'],
                ['key' => 'category_explanation',      'label' => 'Category Explanation'],
                ['key' => 'accepted_by',               'label' => 'Accepted By'],
            ]),
        ],
        'po_items' => [
            'label'      => 'PO Items Reports',
            'pdfTitle'   => 'PO ITEMS REPORT',
            'desc'       => 'One row per item line across every Purchase Order in a date range.',
            'emptyLabel' => 'PO item',
            'icon'       => 'fa-boxes-stacked',
            'accent'     => 'amber',
            'columns'    => [
                ['key' => 'po_number',         'label' => 'PO Number'],
                ['key' => 'items_description', 'label' => 'Item Description'],
                ['key' => 'unit',              'label' => 'Unit'],
                ['key' => 'quantity',          'label' => 'Quantity',   'fmt' => 'number'],
                ['key' => 'unit_cost',         'label' => 'Unit Cost',  'fmt' => 'currency'],
                ['key' => 'total_cost',        'label' => 'Total Cost', 'fmt' => 'currency'],
                ['key' => 'date_po',           'label' => 'PO Date',      'fmt' => 'datetime'],
                ['key' => 'date_created',      'label' => 'Date Created', 'fmt' => 'datetime'],
            ],
        ],
    ];
}
