<?php
// Standalone printable Job Order slip, matching the layout of the real
// files/JOB ORDER.pdf currently issued from execom-online.com/ecss.
require_once 'config.php';
requireAuth();
requireModule('job_orders');

$user  = getCurrentUser();
$scope = getUserBranchScope($user);
$db    = getDB();

$joNumber = trim($_GET['jo'] ?? '');
$stmt = $db->prepare(
    'SELECT jo.*, COALESCE(c.name, jo.walkin_name) AS client_name, c.contact_number AS client_contact,
            t.full_name AS technician_name
     FROM job_orders jo
     LEFT JOIN clients c ON c.id = jo.client_id
     LEFT JOIN users t ON t.id = jo.technician_id
     WHERE jo.jo_number = ? AND jo.is_deleted = 0'
);
$stmt->execute([$joNumber]);
$jo = $stmt->fetch();
if (!$jo) { http_response_code(404); die('Job order not found.'); }
if ($scope !== null && (int)$jo['branch_id'] !== $scope) { http_response_code(403); die('Access denied.'); }

$partStmt = $db->prepare(
    "SELECT jp.*, COALESCE(i1.name, i2.name) AS item_name, iu.serial_number
     FROM job_order_parts jp
     LEFT JOIN items i1 ON i1.id = jp.item_id
     LEFT JOIN inventory_units iu ON iu.id = jp.inventory_unit_id
     LEFT JOIN items i2 ON i2.id = iu.item_id
     WHERE jp.jo_number = ? AND jp.is_deleted = 0 ORDER BY jp.id"
);
$partStmt->execute([$joNumber]);
$parts = $partStmt->fetchAll();

$contact = $jo['client_contact'] ?: $jo['walkin_contact'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Job Order <?= e($jo['jo_number']) ?> | <?= e(APP_NAME) ?></title>
<style>
body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; max-width: 900px; margin: 2rem auto; padding: 0 1rem; font-size: 13px; }
h1 { text-align: center; font-size: 1.6rem; margin: .5rem 0 1.25rem; letter-spacing: .05em; }
.row { display: flex; justify-content: space-between; margin-bottom: .4rem; }
table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
th, td { border: 1px solid #94a3b8; padding: .4rem .5rem; font-size: .85rem; }
th { background: #eef2ff; }
.terms { font-size: .78rem; line-height: 1.5; margin-top: 1.25rem; }
.terms li { margin-bottom: .25rem; }
.sign-block { margin-top: 2.5rem; display: flex; justify-content: space-between; }
.sign-line { border-top: 1px solid #1e293b; width: 260px; margin-top: 2.5rem; padding-top: .2rem; text-align: center; }
.no-print { text-align: center; margin-bottom: 1rem; }
@media print { .no-print { display: none; } body { margin: 0; } }
</style>
<?php include __DIR__ . '/report_tools.php'; ?>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Print</button> <button type="button" data-report-document="documentPrintSource" data-report-title="Job Order <?= e($jo['jo_number']) ?>">Preview / Download PDF</button></div>
<main id="documentPrintSource">
<?php include 'print_letterhead.php'; ?>
<h1>JOB ORDER</h1>

<div class="row">
    <div><strong>JOB ORDER #:</strong> <?= e($jo['jo_number']) ?></div>
    <div><strong>DATE:</strong> <?= $jo['date_received'] ? date('M d, Y', strtotime($jo['date_received'])) : '' ?></div>
</div>
<div class="row">
    <div><strong>CUSTOMER:</strong> <?= e($jo['client_name'] ?: '—') ?></div>
    <div><strong>CONTACT NUMBER:</strong> <?= e($contact ?: '—') ?></div>
</div>
<?php if ($jo['technician_name']): ?>
<div class="row"><div><strong>TECHNICIAN:</strong> <?= e($jo['technician_name']) ?></div><div></div></div>
<?php endif; ?>

<table>
    <thead><tr><th>Qty</th><th>Unit Description</th><th>Serial Number</th><th>Problem / Issue</th><th>Accessories</th><th>Remarks</th></tr></thead>
    <tbody>
        <tr>
            <td>1.0</td>
            <td><?= e($jo['unit_description']) ?></td>
            <td><?= e($jo['serial_number'] ?: '—') ?></td>
            <td><?= e($jo['problem_description'] ?: '—') ?></td>
            <td><?= e($jo['accessories_received'] ?: '—') ?></td>
            <td><?= e($jo['is_under_warranty'] ? 'UNDER WARRANTY' : ($jo['remarks'] ?: '—')) ?></td>
        </tr>
    <?php foreach ($parts as $p): ?>
        <tr>
            <td><?= number_format((float)($p['quantity'] ?: 1), 2) ?></td>
            <td colspan="4"><?= e($p['item_name']) ?><?= $p['serial_number'] ? ' — SN:' . e($p['serial_number']) : '' ?></td>
            <td><?= number_format((float)$p['unit_price'], 2) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="terms">
    <strong>TERMS AND CONDITIONS:</strong>
    <ul>
        <li><?= e(APP_NAME) ?> shall not be held liable for the loss/damage/files of the equipment in the event of fire, typhoon, flood and similar natural calamities beyond its control.</li>
        <li>Equipment in the shop, if not claimed within (15) days after completion or rejection notice will be charged a storage fee of Php50 per day.</li>
        <li>A minimum fee shall be charged for cancelled repair to covered inspection diagnostic and storage fee. Some spare parts may not be available immediately. Please allow 30 to 45 days' lead-time.</li>
        <li><?= e(APP_NAME) ?> has the right to dispose units not claimed within 90 days after completion of the job order.</li>
        <li>In case unit/item was not powered on upon physical checkup and receipt of unit, <?= e(APP_NAME) ?> shall not be responsible for any problems that may arise during diagnosis.</li>
        <li>In claiming the unit/item, this job order slip must be presented.</li>
    </ul>
</div>

<div class="sign-block">
    <div class="sign-line">Received By</div>
    <div class="sign-line">Customer — Signature Over Printed Name</div>
</div>
</main>
</body>
</html>
