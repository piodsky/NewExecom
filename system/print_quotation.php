<?php
// Standalone printable Quotation letter (Outgoing), matching the format of
// files/LGU SUMILAO - ENGINEERING.docx — EXECOM's real reply letter to a
// client's canvass.
require_once 'config.php';
requireAuth();
requireModule('outgoing_quotations');

$user  = getCurrentUser();
$scope = getUserBranchScope($user);
$db    = getDB();

$quotationNumber = trim($_GET['quotation_number'] ?? '');
$stmt = $db->prepare(
    'SELECT q.*, u.full_name AS prepared_by_name FROM outgoing_quotations q LEFT JOIN users u ON u.id = q.prepared_by
     WHERE q.quotation_number = ? AND q.is_deleted = 0'
);
$stmt->execute([$quotationNumber]);
$q = $stmt->fetch();
if (!$q) { http_response_code(404); die('Quotation not found.'); }
if ($scope !== null && (int)$q['branch_id'] !== $scope) { http_response_code(403); die('Access denied.'); }

$itemStmt = $db->prepare(
    'SELECT qi.*, i.name AS item_name FROM outgoing_quotation_items qi LEFT JOIN items i ON i.id = qi.item_id
     WHERE qi.quotation_number = ? AND qi.is_deleted = 0 ORDER BY qi.id'
);
$itemStmt->execute([$quotationNumber]);
$items = $itemStmt->fetchAll();

$peso = fn($n) => number_format((float)$n, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quotation <?= e($q['quotation_number']) ?> | <?= e(APP_NAME) ?></title>
<style>
body { font-family: 'Times New Roman', Times, serif; color: #1e293b; max-width: 780px; margin: 2rem auto; padding: 0 1rem; font-size: 14px; line-height: 1.6; }
.q-date { text-align: right; margin-bottom: 1.5rem; }
.q-items { font-family: 'Courier New', monospace; white-space: pre; margin: 1rem 0; font-size: .95rem; }
.q-total { font-family: 'Courier New', monospace; white-space: pre; font-weight: 700; }
.q-note { font-weight: 700; margin-top: 1rem; }
.q-sign { margin-top: 3rem; }
.no-print { text-align: center; margin-bottom: 1rem; font-family: Arial, sans-serif; }
@media print { .no-print { display: none; } body { margin: 0; } }
</style>
<?php include __DIR__ . '/report_tools.php'; ?>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Print</button> <button type="button" data-report-document="documentPrintSource" data-report-title="Quotation <?= e($q['quotation_number']) ?>">Preview / Download PDF</button></div>
<main id="documentPrintSource">
<?php include 'print_letterhead.php'; ?>

<div class="q-date"><?= strtoupper(date('F j, Y', strtotime($q['created_at']))) ?></div>

<div><?= e($q['client_name']) ?></div>
<div><?= e($q['client_address'] ?? '') ?></div>
<br>
<div>Dear Ma'am/ Sir;</div>
<br>
<div>We are pleased to submit our price quotation as follows:</div>
<br>

<div class="q-items"><?php
$leaderWidth = 70;
foreach ($items as $it) {
    $label = $it['item_name'] ?? $it['description'];
    $price = number_format((float)$it['total_price'], 2);
    $dashes = max(3, $leaderWidth - strlen($label) - strlen($price));
    echo e($label) . ' ' . str_repeat('-', $dashes) . ' ' . e($price) . "\n";
}
?></div>
<div class="q-total"><?= str_repeat(' ', max(0, $leaderWidth - 5)) ?>TOTAL <?= $peso($q['total_amount']) ?></div>

<div class="q-note">NOTE: PRICE AND AVAILABILITY ARE SUBJECT TO CHANGE WITHOUT PRIOR NOTICE.</div>
<br>
<?php if ($q['price_validity_days']): ?><div>Price Validity: <?= (int)$q['price_validity_days'] ?> Calendar Days.</div><?php endif; ?>
<?php if ($q['lead_time']): ?><div>Stock Availability: <?= e($q['lead_time']) ?></div><?php endif; ?>
<br>
<div>Hope you will find our offer most competitive and we will be pleased to place your valuable order with us. Please do not hesitate to call the undersigned for more inquires needed.</div>
<br>
<div>Thank you and more power.</div>

<div class="q-sign">
    <div><?= e($q['prepared_by_name'] ?? '') ?></div>
    <div>Sales Representative</div>
</div>
</main>
</body>
</html>
