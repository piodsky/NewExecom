<?php
require_once 'config.php';
requireAuth();
requireModule('purchase_orders');

$poNumber = trim($_GET['po_number'] ?? '');
$type     = ($_GET['type'] ?? 'standard') === 'lot' ? 'lot' : 'standard';
if ($poNumber === '') {
    die('<div style="font-family:sans-serif;padding:40px;">Missing PO number.</div>');
}

$db = getPoDB();
$stmt = $db->prepare("SELECT * FROM tbl_purchase_order WHERE PO_Number = ? AND isDeleted = 0");
$stmt->execute([$poNumber]);
$po = $stmt->fetch();
if (!$po) {
    die('<div style="font-family:sans-serif;padding:40px;">Purchase order not found.</div>');
}
$po = fixLegacyEncodingDeep($po);

$stmt = $db->prepare(
    "SELECT Item_ID, Unit, Items_Description, Quantity, Unit_Cost, Total_Cost, NumberSeries
     FROM tbl_purchase_order_items_details WHERE PO_Number = ? AND isDeleted = 0 ORDER BY ID"
);
$stmt->execute([$poNumber]);
$items = fixLegacyEncodingDeep($stmt->fetchAll());

// "By LOT" groups items under their NumberSeries tag (falls back to a single
// unlabeled lot when a PO predates that field being used).
$lots = [];
if ($type === 'lot') {
    foreach ($items as $item) {
        $lot = trim($item['NumberSeries'] ?? '') ?: '—';
        $lots[$lot][] = $item;
    }
}

$grandTotal = array_sum(array_column($items, 'Total_Cost'));

function fmtMoney($v) { return number_format((float)$v, 2); }
function fmtDate($v) { return $v ? date('F j, Y', strtotime($v)) : '—'; }

// QR verification data: original/edited status, lifecycle stage, fingerprint
$verifyData = poVerificationData($poNumber);
$qrSvg = renderPoQrCodeSvg($poNumber, 140);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Order <?= e($po['PO_Number']) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #111; margin: 30px; position: relative; }
    .po-header { text-align: center; margin-bottom: 20px; }
    .po-header h1 { font-size: 16px; margin: 0 0 4px; text-transform: uppercase; }
    .po-header .sub { font-size: 11px; color: #444; }
    table.meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.meta td { padding: 3px 6px; vertical-align: top; }
    table.meta .label { font-weight: 600; width: 140px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.items th, table.items td { border: 1px solid #333; padding: 5px 7px; font-size: 11.5px; }
    table.items th { background: #f0f0f0; text-transform: uppercase; font-size: 10px; }
    table.items td.num { text-align: right; }
    .lot-title { background: #e5e7eb; font-weight: 700; padding: 5px 7px; border: 1px solid #333; }
    tfoot td { font-weight: 700; }
    .sign-row { display: flex; justify-content: space-between; margin-top: 50px; }
    .sign-block { width: 30%; text-align: center; font-size: 11.5px; }
    .sign-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; }
    .no-print { margin-bottom: 16px; }
    .po-qr-block {
        position: absolute; top: 30px; right: 30px; text-align: center;
        padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 4px;
    }
    .po-qr-block svg { display: block; margin-bottom: 4px; }
    .po-qr-meta { font-size: 9px; color: #666; line-height: 1.3; white-space: nowrap; }
    @media print { .no-print { display: none !important; } body { margin: 10mm; } }
</style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">Print</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="po-qr-block">
    <?php echo $qrSvg; ?>
    <div class="po-qr-meta">
        <strong><?= htmlspecialchars($verifyData['original'] ? 'ORIGINAL' : 'EDITED', ENT_QUOTES) ?></strong><br>
        <?= htmlspecialchars($verifyData['status'], ENT_QUOTES) ?><br>
        <span style="font-size: 8px; letter-spacing: 1px;">Code: <?= htmlspecialchars($verifyData['code'], ENT_QUOTES) ?></span>
    </div>
</div>

<div class="po-header-wrapper">
    <div class="po-header">
        <h1>Purchase Order<?= $type === 'lot' ? ' (By LOT)' : '' ?></h1>
        <div class="sub"><?= e(APP_FULL_NAME) ?></div>
    </div>
</div>

<table class="meta">
    <tr>
        <td class="label">PO Number</td><td><?= e($po['PO_Number']) ?></td>
        <td class="label">PO Date</td><td><?= fmtDate($po['Date_PO']) ?></td>
    </tr>
    <tr>
        <td class="label">PR Number</td><td><?= e($po['PR_Number']) ?></td>
        <td class="label">Reference No.</td><td><?= e($po['Reference_No']) ?></td>
    </tr>
    <tr>
        <td class="label">Supplier</td><td><?= e($po['Supplier_Name']) ?></td>
        <td class="label">BAC Resolution No.</td><td><?= e($po['BAC_Resolution_No']) ?></td>
    </tr>
    <tr>
        <td class="label">Supplier Address</td><td><?= e($po['Supplier_Address']) ?></td>
        <td class="label">Mode of Procurement</td><td><?= e($po['Mode_of_Procurement']) ?></td>
    </tr>
    <tr>
        <td class="label">Department</td><td><?= e($po['Department']) ?></td>
        <td class="label">Place of Delivery</td><td><?= e($po['Place_of_Delivery']) ?></td>
    </tr>
    <tr>
        <td class="label">Division / Section</td><td><?= e($po['Division']) ?> / <?= e($po['Section']) ?></td>
        <td class="label">Delivery Term</td><td><?= e($po['PO_Working_Days']) ?></td>
    </tr>
    <tr>
        <td class="label">Category</td><td><?= e($po['Category']) ?></td>
        <td class="label">Payment Term</td><td><?= e($po['PO_Payment_Term']) ?></td>
    </tr>
    <tr>
        <td class="label">Purpose</td><td colspan="3"><?= nl2br(e($po['Purpose'])) ?></td>
    </tr>
    <?php if (trim($po['Note'] ?? '') !== ''): ?>
    <tr>
        <td class="label">Note</td><td colspan="3"><?= nl2br(e($po['Note'])) ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php if ($type === 'lot'): ?>
    <?php foreach ($lots as $lotName => $lotItems): ?>
        <div class="lot-title">LOT: <?= e($lotName) ?></div>
        <table class="items">
            <thead>
                <tr><th>Unit</th><th>Description</th><th>Qty</th><th>Unit Cost</th><th>Total Cost</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lotItems as $item): ?>
                <tr>
                    <td><?= e($item['Unit']) ?></td>
                    <td><?= e($item['Items_Description']) ?></td>
                    <td class="num"><?= fmtMoney($item['Quantity']) ?></td>
                    <td class="num"><?= fmtMoney($item['Unit_Cost']) ?></td>
                    <td class="num"><?= fmtMoney($item['Total_Cost']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="4" style="text-align:right;">Lot Subtotal</td>
                    <td class="num"><?= fmtMoney(array_sum(array_column($lotItems, 'Total_Cost'))) ?></td>
                </tr>
            </tbody>
        </table>
        <br>
    <?php endforeach; ?>
<?php else: ?>
    <table class="items">
        <thead>
            <tr><th>Unit</th><th>Description</th><th>Qty</th><th>Unit Cost</th><th>Total Cost</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= e($item['Unit']) ?></td>
                <td><?= e($item['Items_Description']) ?></td>
                <td class="num"><?= fmtMoney($item['Quantity']) ?></td>
                <td class="num"><?= fmtMoney($item['Unit_Cost']) ?></td>
                <td class="num"><?= fmtMoney($item['Total_Cost']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<table class="items" style="margin-top:0;">
    <tfoot>
        <tr><td colspan="4" style="text-align:right;border:none;">Grand Total</td><td class="num"><?= fmtMoney($grandTotal) ?></td></tr>
    </tfoot>
</table>

<div class="sign-row">
    <div class="sign-block">
        <div class="sign-line">Requested By</div>
    </div>
    <div class="sign-block">
        <div class="sign-line">Funds Available / Approved By</div>
    </div>
    <div class="sign-block">
        <div class="sign-line">Conforme (Supplier)</div>
    </div>
</div>

</body>
</html>
