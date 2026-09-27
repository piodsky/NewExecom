<?php
require_once __DIR__ . '/config.php';
requireAuth();

// The record APIs remain the source of truth for line items and branch access.
$documents = [
    'receiving_reports' => ['receiving_reports', 'rr', 'Receiving Report'],
    'stock_transfer' => ['stock_transfers', 'transfer', 'Stock Transfer'],
    'outgoing_rfq' => ['outgoing_rfq', 'rfq', 'Request for Quotation (Outgoing)'],
    'po_outgoing' => ['po_outgoing', 'po', 'Purchase Order (Outgoing)'],
    'delivery_reports' => ['delivery_reports', 'dr', 'Delivery Report'],
];
$module = $_GET['module'] ?? '';
$number = $_GET['id'] ?? '';
if (!is_string($module) || !isset($documents[$module]) || !is_string($number) || trim($number) === '' || strlen($number) > 30) {
    http_response_code(400);
    exit('Select a valid document to print.');
}
requireModule($module);
$user = getCurrentUser();
if ($user['role'] !== 'super_admin' && getUserBranchScope($user) === null) {
    http_response_code(403);
    exit('A branch assignment is required to print documents.');
}
[$page, $key, $title] = $documents[$module];
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> <?= e(trim($number)) ?> | <?= e(APP_NAME) ?></title>
<link rel="icon" href="image/execom_icon.png">
<link rel="stylesheet" href="vendor/css/bootstrap.min.css">
<link rel="stylesheet" href="css/base.css">
<?php include __DIR__ . '/report_tools.php'; ?>
<?php include __DIR__ . '/date_time.php'; ?>
<script src="js/report-document.js" defer></script>
</head>
<body class="report-document-page"
      data-module="<?= e($module) ?>" data-record-id="<?= e(trim($number)) ?>"
      data-api="<?= e('api/' . $page . '.php') ?>" data-key="<?= e($key) ?>" data-title="<?= e($title) ?>">
<div class="document-actions">
    <a href="<?= e($page) ?>.php" class="btn btn-light btn-sm">Back</a>
    <button type="button" class="btn btn-primary-app btn-sm" id="documentPreviewButton" disabled>Print / Download PDF</button>
    <span id="documentStatus" role="status">Loading document…</span>
</div>
</body>
</html>
