<?php
// Standalone printable Purchase Order (Internal), matching the layout of
// files/SAMPLE P.O.pdf — EXECOM's real PO to its own suppliers.
require_once 'config.php';
requireAuth();
requireModule('po_internal');

$user  = getCurrentUser();
$scope = getUserBranchScope($user);
$db    = getDB();

$poNumber = trim($_GET['po_number'] ?? '');
$stmt = $db->prepare(
    'SELECT p.*, s.contact_person, s.contact_number, u.full_name AS created_by_name
     FROM po_internal p
     LEFT JOIN suppliers s ON s.id = p.supplier_id
     JOIN users u ON u.id = p.created_by
     WHERE p.po_number = ? AND p.is_deleted = 0'
);
$stmt->execute([$poNumber]);
$po = $stmt->fetch();
if (!$po) { http_response_code(404); die('Purchase Order not found.'); }
if ($scope !== null && (int)$po['branch_id'] !== $scope) { http_response_code(403); die('Access denied.'); }

$itemStmt = $db->prepare(
    'SELECT pi.*, i.name AS item_name FROM po_internal_items pi LEFT JOIN items i ON i.id = pi.item_id
     WHERE pi.po_number = ? AND pi.is_deleted = 0 ORDER BY pi.id'
);
$itemStmt->execute([$poNumber]);
$items = $itemStmt->fetchAll();

$peso = fn($n) => number_format((float)$n, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>PO <?= e($po['po_number']) ?> | <?= e(APP_NAME) ?></title>
<style>
body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; max-width: 900px; margin: 2rem auto; padding: 0 1rem; font-size: 13px; }
h1 { text-align: center; font-size: 1.6rem; margin: .5rem 0 1.25rem; letter-spacing: .05em; }
.row { display: flex; justify-content: space-between; margin-bottom: .4rem; }
table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
th, td { border: 1px solid #94a3b8; padding: .4rem .5rem; font-size: .85rem; }
th { background: #eef2ff; }
.total-row td { font-weight: 700; background: #fef9c3; }
.info-box { background: #bbf7d0; padding: .6rem .8rem; margin-top: .5rem; font-size: .85rem; line-height: 1.5; }
.sign-block { margin-top: 2.5rem; }
.sign-line { border-top: 1px solid #1e293b; width: 260px; margin-top: 2.5rem; padding-top: .2rem; }
.no-print { text-align: center; margin-bottom: 1rem; }
@media print { .no-print { display: none; } body { margin: 0; } }
</style>
<?php include __DIR__ . '/report_tools.php'; ?>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Print</button> <button type="button" data-report-document="documentPrintSource" data-report-title="Purchase Order <?= e($po['po_number']) ?>">Preview / Download PDF</button></div>
<main id="documentPrintSource">
<?php include 'print_letterhead.php'; ?>
<h1>PURCHASE ORDER</h1>

<div class="row"><div><strong>P.O No.:</strong> <?= e($po['po_number']) ?></div><div><strong>Date:</strong> <?= date('m/d/y', strtotime($po['created_at'])) ?></div></div>
<div><strong>Supplier:</strong> <?= e($po['supplier_name']) ?></div>
<div><strong>Address:</strong> <?= e($po['supplier_address'] ?? '') ?></div>

<table>
    <thead><tr><th>Item No.</th><th>End-User</th><th>Qty</th><th>Unit</th><th>Items</th><th>Unit Cost</th><th>Total Amount</th></tr></thead>
    <tbody>
    <?php foreach ($items as $i => $it): ?>
        <tr>
            <td><?= $i + 1 ?>.00</td>
            <td><?= e($it['end_user'] ?? '') ?></td>
            <td><?= number_format((float)$it['quantity'], 2) ?></td>
            <td><?= e($it['unit'] ?? '') ?></td>
            <td><?= e($it['item_name'] ?? $it['description']) ?></td>
            <td><?= $peso($it['unit_cost']) ?></td>
            <td><?= $peso($it['total_cost']) ?></td>
        </tr>
    <?php endforeach; ?>
        <tr class="total-row"><td colspan="6" style="text-align:right">TOTAL</td><td><?= $peso($po['total_amount']) ?></td></tr>
    </tbody>
</table>

<div class="info-box">
    <?php if ($po['contact_person']): ?><div><strong>Contact person:</strong> <?= e($po['contact_person']) ?></div><?php endif; ?>
    <?php if ($po['contact_number']): ?><div><strong>Contact number:</strong> <?= e($po['contact_number']) ?></div><?php endif; ?>
    <?php if ($po['ship_to_address']): ?><div><strong>Ship to address:</strong> <?= e($po['ship_to_address']) ?></div><?php endif; ?>
    <?php if ($po['forwarder']): ?><div><strong>Forwarder:</strong> <?= e($po['forwarder']) ?></div><?php endif; ?>
</div>

<div class="sign-block">
    <div>Prepared By:</div>
    <div class="sign-line"><?= e($po['created_by_name']) ?></div>
</div>
<div class="sign-block">
    <div>Received By:</div>
    <div class="sign-line">Printed Name &amp; Signature</div>
</div>
</main>
</body>
</html>
