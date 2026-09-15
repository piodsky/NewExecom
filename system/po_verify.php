<?php
// Public landing page for the QR code printed on a Purchase Order.
//
// Deliberately NOT behind requireAuth(): the people who most need to check a
// printout — auditors, COA, a supplier holding their copy — do not have
// accounts here. Access is instead limited by the token itself, which is
// AES-256-GCM encrypted and therefore not guessable or forgeable without
// QR_SECRET; you can only reach a PO's page by holding paper that shows it.
//
// Because it is public, this page discloses the minimum needed to answer "is
// this document genuine and current": PO number, lifecycle status, whether
// this copy is the original or a later revision, and the PO date. It must
// never grow to show amounts, line items, or supplier details — that is the
// whole basis on which making it public is defensible.
require_once 'config.php';

$token  = $_GET['t'] ?? '';
$claim  = poVerifyDecodeToken($token);

$state  = 'invalid';   // invalid | notfound | authentic | superseded
$po     = null;
$live   = null;

if ($claim) {
    $live = poVerificationData($claim['po']);
    if ($live['status'] === 'error' || $live['status'] === 'unknown') {
        $state = 'notfound';
    } else {
        $po = $claim['po'];
        // The token carries the fingerprint as it was at print time. The live
        // record recomputes it from the current row. Equal means the paper
        // still reflects the system; different means the PO was edited after
        // this copy was printed (an edit soft-deletes the old row and inserts
        // a new one with a fresh Date_Created, changing the hash).
        $state = hash_equals($live['code'], $claim['code']) ? 'authentic' : 'superseded';
    }
}

$banner = [
    'authentic'  => ['#047857', '#ecfdf5', '#a7f3d0', 'fa-circle-check',        'AUTHENTIC',   'This printout matches the current record.'],
    'superseded' => ['#b45309', '#fffbeb', '#fde68a', 'fa-triangle-exclamation','SUPERSEDED',  'This purchase order has been edited since this copy was printed. Ask for the current version.'],
    'notfound'   => ['#b91c1c', '#fef2f2', '#fecaca', 'fa-circle-xmark',        'NOT FOUND',   'No active purchase order matches this code. It may have been deleted.'],
    'invalid'    => ['#b91c1c', '#fef2f2', '#fecaca', 'fa-circle-xmark',        'INVALID CODE','This QR code could not be read as a valid verification link.'],
][$state];
[$fg, $bg, $bd, $icon, $headline, $detail] = $banner;

function verifyDate($v) {
    if (!$v || strpos((string)$v, '0000-00-00') === 0) return '—';
    $ts = strtotime($v);
    return $ts ? date('F j, Y', $ts) : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Purchase Order Verification | <?= e(APP_NAME) ?></title>
<link rel="icon" type="image/png" href="image/po-32.png">
<link rel="stylesheet" href="vendor/css/all.min.css">
<style>
    /* Self-contained styling — this page is reached on a phone, off the app
       shell, so it deliberately does not pull in base.css or the sidebar. */
    * { box-sizing: border-box; }
    body {
        margin: 0; padding: 24px 16px 40px;
        font-family: 'Segoe UI', system-ui, -apple-system, Arial, sans-serif;
        background: #f1f5f9; color: #0f172a;
        display: flex; justify-content: center;
    }
    .v-card { width: 100%; max-width: 460px; }
    .v-brand { text-align: center; margin-bottom: 18px; }
    .v-brand img { width: 68px; height: 68px; object-fit: contain; }
    .v-brand h1 { font-size: 16px; margin: 8px 0 2px; letter-spacing: .01em; }
    .v-brand p { margin: 0; font-size: 12.5px; color: #64748b; }

    .v-banner {
        border: 1px solid <?= $bd ?>; background: <?= $bg ?>; color: <?= $fg ?>;
        border-radius: 14px; padding: 20px 18px; text-align: center; margin-bottom: 16px;
    }
    .v-banner i { font-size: 40px; display: block; margin-bottom: 10px; }
    .v-banner .v-headline { font-size: 19px; font-weight: 700; letter-spacing: .03em; }
    .v-banner .v-detail { font-size: 13px; margin-top: 6px; line-height: 1.45; color: #334155; }

    .v-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
    .v-row { display: flex; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    .v-row:last-child { border-bottom: none; }
    .v-row dt { flex: 0 0 118px; margin: 0; color: #64748b; font-size: 12.5px; padding-top: 1px; }
    .v-row dd { margin: 0; flex: 1; font-weight: 600; word-break: break-word; }
    .v-po { font-family: 'Consolas', ui-monospace, monospace; letter-spacing: .01em; }
    .v-pill {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        font-size: 12px; font-weight: 700; letter-spacing: .03em;
    }
    .v-pill-orig { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .v-pill-edit { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }

    .v-foot { margin-top: 16px; text-align: center; font-size: 11.5px; color: #94a3b8; line-height: 1.6; }
</style>
</head>
<body>
<div class="v-card">

    <div class="v-brand">
        <img src="image/po-128.png" alt="">
        <h1><?= e(APP_FULL_NAME) ?></h1>
        <p>Purchase Order Verification</p>
    </div>

    <div class="v-banner">
        <i class="fas <?= $icon ?>"></i>
        <div class="v-headline"><?= e($headline) ?></div>
        <div class="v-detail"><?= e($detail) ?></div>
    </div>

    <?php if ($po !== null): ?>
    <dl class="v-panel">
        <div class="v-row">
            <dt>PO Number</dt>
            <dd class="v-po"><?= e($po) ?></dd>
        </div>
        <div class="v-row">
            <dt>Current status</dt>
            <dd><?= e($live['status']) ?></dd>
        </div>
        <div class="v-row">
            <dt>This copy</dt>
            <dd>
                <?php if ($state === 'superseded'): ?>
                    <span class="v-pill v-pill-edit">OUTDATED PRINT</span>
                <?php elseif ($live['original']): ?>
                    <span class="v-pill v-pill-orig">ORIGINAL</span>
                <?php else: ?>
                    <span class="v-pill v-pill-edit">EDITED &middot; v<?= (int)$live['versions'] ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div class="v-row">
            <dt>Record created</dt>
            <dd><?= e(verifyDate($live['created'])) ?></dd>
        </div>
        <div class="v-row">
            <dt>Verified on</dt>
            <dd><?= e(date('F j, Y \a\t g:i A')) ?></dd>
        </div>
    </dl>
    <?php endif; ?>

    <div class="v-foot">
        Verification only — amounts, items and supplier details are not shown here.<br>
        Sign in to <?= e(APP_NAME) ?> for the full purchase order.
    </div>

</div>
</body>
</html>
