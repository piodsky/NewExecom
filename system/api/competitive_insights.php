<?php
// Competitive Insights — one read-only pass over the bid document set.
//
// The question this page exists to answer is the one nobody could answer
// before: how much did competitive bidding actually save? That is the approved
// budget the Purchase Request was raised for (PR_Amount) less what the winning
// bidder was awarded (Contract_Amount), and it can only be computed where BOTH
// are on file — so coverage is reported as prominently as the figure itself.
//
// Read-only by design: no POST branch, nothing here writes.
//
// One bulk read plus one item-total read; every breakdown is computed in PHP.
// The bid table is small (hundreds of rows, not the purchase order table's
// 26,000), so this is quick either way — but a GROUP BY per panel would still
// be several needless round trips to a shared production server.
require_once '../config.php';
requireReportsAdmin();
requireModule('competitive_bidding');
header('Content-Type: application/json');

$db = getPoDB();

const CI_TABLE = 'tbl_competitive_bidding';

// ── helpers ─────────────────────────────────────────────────────────────────
$money = fn($v) => $v === null || $v === '' ? null : (float)$v;
// These are real DATE columns, unlike the purchase order table's varchars —
// but a legacy '0000-00-00' still comes back from PDO as a string.
$day = function ($v): ?string {
    $v = trim((string)$v);
    if ($v === '' || strpos($v, '0000') === 0) return null;
    return preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $m) ? $m[1] : null;
};
$dayDiff = function (?string $a, ?string $b): ?int {
    if (!$a || !$b) return null;
    $ta = strtotime($a); $tb = strtotime($b);
    if ($ta === false || $tb === false) return null;
    return (int)round(($tb - $ta) / 86400);
};
$norm = fn($v) => preg_replace('/\s+/', ' ', trim((string)$v));

// ── the read ────────────────────────────────────────────────────────────────
// PR_Amount arrives in database/cb_pr_amount.sql. Until that has been run the
// column is not there, and the honest answer is to say so rather than to 500
// on every panel — the page then renders everything that does not depend on it
// and shows the migration notice where the savings figures would be.
$hasPrAmount = true;
try {
    $db->query('SELECT PR_Amount FROM ' . CI_TABLE . ' LIMIT 0');
} catch (Throwable $e) {
    $hasPrAmount = false;
}
$prCol = $hasPrAmount ? 'PR_Amount' : 'NULL AS PR_Amount';

try {
    $bids = $db->query(
        'SELECT ID, Bid_Number, Contract_Number, Contract_Name, Contract_Location,
                Supplier_Name, Contract_Amount, ' . $prCol . ', Date_PR, Bid_Date,
                NOA_Date, Date_Received_NOA, Date_Performance_Bond, Contract_Date,
                NTP_Date, Print_Bid, Barcode_Number, Date_Created
         FROM ' . CI_TABLE . ' WHERE isDeleted = 0'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('competitive_insights: ' . $e->getMessage());
    jsonResponse(false, 'The Competitive Bidding tables have not been created yet. '
        . 'Run database/tbl_competitive_bidding.sql against the HRMIS server, then reload this page.');
}
$bids = fixLegacyEncodingDeep($bids);

// Item totals, read separately and matched up in PHP for the same reason
// api/po_insights.php does it: a bid carrying no item rows survives the merge,
// where an INNER JOIN would drop it.
$itemTotals = [];
try {
    foreach ($db->query(
        'SELECT Bid_Number, SUM(Total_Cost) val, COUNT(*) nl
         FROM tbl_competitive_bidding_items_details WHERE isDeleted = 0 GROUP BY Bid_Number'
    ) as $t) {
        $itemTotals[$t['Bid_Number']] = ['val' => (float)$t['val'], 'nl' => (int)$t['nl']];
    }
} catch (Throwable $e) { /* items table optional on a part-migrated server */ }

// ── shape each bid once ─────────────────────────────────────────────────────
$rows = [];
foreach ($bids as $b) {
    $pr     = $money($b['PR_Amount']);
    $award  = $money($b['Contract_Amount']);
    $datePr = $day($b['Date_PR']);
    $bidDay = $day($b['Bid_Date']);
    $noa    = $day($b['NOA_Date']);
    $ntp    = $day($b['NTP_Date']);
    $items  = $itemTotals[$b['Bid_Number']] ?? ['val' => 0.0, 'nl' => 0];

    // Savings only where both figures are real. See the note in
    // database/cb_pr_amount.sql: a missing PR amount treated as zero would
    // report the whole contract price as a saving.
    $measurable = ($pr !== null && $pr > 0 && $award !== null && $award > 0);

    // The year a bid belongs to is the year it was awarded where that is
    // known, and the year the request was raised otherwise — NOT the year the
    // row was typed into this system, which for anything backfilled is simply
    // the day somebody got round to it.
    $anchor = $noa ?: ($bidDay ?: ($datePr ?: $day($b['Date_Created'])));

    $rows[] = [
        'bid'        => $b['Bid_Number'],
        'contract'   => $b['Contract_Number'],
        'name'       => $norm($b['Contract_Name']),
        'office'     => $norm($b['Contract_Location']),
        'supplier'   => $norm($b['Supplier_Name']),
        'pr'         => $pr,
        'award'      => $award,
        'saving'     => $measurable ? $pr - $award : null,
        'measurable' => $measurable,
        'date_pr'    => $datePr,
        'bid_date'   => $bidDay,
        'noa'        => $noa,
        'recv_noa'   => $day($b['Date_Received_NOA']),
        'bond'       => $day($b['Date_Performance_Bond']),
        'signed'     => $day($b['Contract_Date']),
        'ntp'        => $ntp,
        'anchor'     => $anchor,
        'year'       => $anchor ? (int)substr($anchor, 0, 4) : null,
        'awarded'    => $noa !== null,
        'locked'     => ((int)$b['Print_Bid']) === 1,
        'barcode'    => trim((string)$b['Barcode_Number']) !== '',
        'items_val'  => $items['val'],
        'items_n'    => $items['nl'],
        // The full run, request to notice to proceed — how long a
        // competitively bid procurement actually takes end to end.
        'cycle'      => $dayDiff($datePr, $ntp),
    ];
}

// ── period scope ────────────────────────────────────────────────────────────
$period   = $_GET['period'] ?? 'all';
$thisYear = (int)date('Y');
$cut12m   = date('Y-m-d', strtotime('-12 months'));
$inScope = function (array $r) use ($period, $thisYear, $cut12m): bool {
    if ($period === 'ytd') return $r['year'] === $thisYear;
    if ($period === '12m') return $r['anchor'] !== null && $r['anchor'] >= $cut12m;
    return true;
};
$scoped = array_values(array_filter($rows, $inScope));
$periodLabel = ['all' => 'All time', 'ytd' => 'This year', '12m' => 'Last 12 months'][$period] ?? 'All time';

// ── the savings headline ────────────────────────────────────────────────────
// Summed over the measurable set ONLY, with the size of that set returned
// alongside so the page can say what share of the bids the figure covers. A
// total presented without its denominator invites the reader to assume it
// covers everything.
$measured = array_values(array_filter($scoped, fn($r) => $r['measurable']));
$sumPr    = array_sum(array_column($measured, 'pr'));
$sumAward = array_sum(array_column($measured, 'award'));
$saved    = $sumPr - $sumAward;

// Split, because they answer different questions. The net figure is what the
// city kept overall; the gross is what the bids that came in under budget
// saved, which stops being the same number the moment anything is awarded
// above its approved budget.
$under = array_values(array_filter($measured, fn($r) => $r['saving'] > 0));
$over  = array_values(array_filter($measured, fn($r) => $r['saving'] < 0));

$savings = [
    'measured'   => count($measured),
    'scoped'     => count($scoped),
    'unmeasured' => count($scoped) - count($measured),
    'pr'         => $sumPr,
    'award'      => $sumAward,
    'net'        => $saved,
    'rate'       => $sumPr > 0 ? $saved / $sumPr * 100 : null,
    'under_n'    => count($under),
    'under_val'  => array_sum(array_column($under, 'saving')),
    'over_n'     => count($over),
    'over_val'   => -array_sum(array_column($over, 'saving')),
];

// ── by year ─────────────────────────────────────────────────────────────────
$byYear = [];
foreach ($scoped as $r) {
    if ($r['year'] === null) continue;
    $y = $r['year'];
    if (!isset($byYear[$y])) {
        $byYear[$y] = ['y' => $y, 'bids' => 0, 'pr' => 0.0, 'award' => 0.0,
                       'measured' => 0, 'measured_award' => 0.0];
    }
    $byYear[$y]['bids']++;
    $byYear[$y]['award'] += (float)($r['award'] ?? 0);
    if ($r['measurable']) {
        $byYear[$y]['pr'] += $r['pr'];
        $byYear[$y]['measured_award'] += $r['award'];
        $byYear[$y]['measured']++;
    }
}
ksort($byYear);
foreach ($byYear as &$y) {
    // A year's saving is computed off the measured subset of that year, so a
    // year with poor coverage shows a small saving on a small base rather than
    // a wrong saving on the whole year's award value.
    $y['saved'] = $y['pr'] - $y['measured_award'];
    $y['rate']  = $y['pr'] > 0 ? $y['saved'] / $y['pr'] * 100 : null;
    unset($y['measured_award']);
}
unset($y);
$byYear = array_values($byYear);

// ── biggest savings, and the overruns ───────────────────────────────────────
$slim = fn(array $r) => [
    'bid'      => $r['bid'],
    'name'     => $r['name'],
    'supplier' => $r['supplier'],
    'pr'       => $r['pr'],
    'award'    => $r['award'],
    'saving'   => $r['saving'],
    'rate'     => $r['pr'] > 0 ? abs($r['saving']) / $r['pr'] * 100 : null,
    'day'      => $r['noa'] ?: $r['bid_date'],
];
$ranked = $measured;
usort($ranked, fn($a, $b) => $b['saving'] <=> $a['saving']);
$top      = array_slice(array_values(array_filter($ranked, fn($r) => $r['saving'] > 0)), 0, 10);
$overruns = array_slice(array_values(array_filter(array_reverse($ranked), fn($r) => $r['saving'] < 0)), 0, 10);

// ── who the savings came from ───────────────────────────────────────────────
$bySupplier = [];
foreach ($measured as $r) {
    $key = $r['supplier'] !== '' ? $r['supplier'] : '(no supplier recorded)';
    if (!isset($bySupplier[$key])) {
        $bySupplier[$key] = ['name' => $key, 'bids' => 0, 'award' => 0.0, 'pr' => 0.0];
    }
    $bySupplier[$key]['bids']++;
    $bySupplier[$key]['award'] += $r['award'];
    $bySupplier[$key]['pr']    += $r['pr'];
}
foreach ($bySupplier as &$sup) {
    $sup['saved'] = $sup['pr'] - $sup['award'];
    $sup['rate']  = $sup['pr'] > 0 ? $sup['saved'] / $sup['pr'] * 100 : null;
}
unset($sup);
$bySupplier = array_values($bySupplier);
usort($bySupplier, fn($a, $b) => $b['saved'] <=> $a['saved']);
$bySupplier = array_slice($bySupplier, 0, 8);

// ── how far each document set has got ───────────────────────────────────────
// Every stage is a date column, so "reached" means the date is filled in — the
// same trail the assistant walks when somebody asks where a bid is.
$stageDefs = [
    ['bid_date', 'Bid opened'],
    ['noa',      'Notice of Award issued'],
    ['recv_noa', 'Award received by supplier'],
    ['bond',     'Performance security posted'],
    ['signed',   'Contract signed'],
    ['ntp',      'Notice to Proceed'],
];
$stages = [];
foreach ($stageDefs as $def) {
    $stages[] = [
        'label' => $def[1],
        'count' => count(array_filter($scoped, fn($r) => $r[$def[0]] !== null)),
        'total' => count($scoped),
    ];
}

// ── lead times ──────────────────────────────────────────────────────────────
$avg = function (array $vals): ?float {
    $vals = array_values(array_filter($vals, fn($v) => $v !== null && $v >= 0));
    return $vals ? array_sum($vals) / count($vals) : null;
};
$leg = function (string $a, string $b) use ($scoped, $dayDiff, $avg): ?float {
    return $avg(array_map(fn($r) => $dayDiff($r[$a], $r[$b]), $scoped));
};
$legDefs = [
    ['PR raised → bid opened',       'date_pr',  'bid_date'],
    ['Bid opened → Notice of Award', 'bid_date', 'noa'],
    ['Award issued → received',      'noa',      'recv_noa'],
    ['Award received → security',    'recv_noa', 'bond'],
    ['Security → contract signed',   'bond',     'signed'],
    ['Contract signed → NTP',        'signed',   'ntp'],
];
$lead = [];
foreach ($legDefs as $d) {
    $days = $leg($d[1], $d[2]);
    $lead[] = ['label' => $d[0], 'days' => $days === null ? null : round($days, 1)];
}
$cycle = $avg(array_column($scoped, 'cycle'));

// ── data quality: what is stopping the savings figure from being complete ──
$quality = [
    [
        'count' => count(array_filter($scoped, fn($r) => $r['pr'] === null || $r['pr'] <= 0)),
        'label' => 'No PR amount on file',
        'note'  => 'These cannot be counted in the savings figure at all — there is nothing to compare the award against.',
    ],
    [
        'count' => count(array_filter($scoped, fn($r) => $r['date_pr'] === null)),
        'label' => 'No PR date on file',
        'note'  => 'Every lead time below starts from this date, so these drop out of the timings.',
    ],
    [
        'count' => count(array_filter($scoped, fn($r) => $r['award'] === null || $r['award'] <= 0)),
        'label' => 'No contract amount',
        'note'  => 'The awarded total is typed on the record rather than summed from the items.',
    ],
    [
        'count' => count(array_filter($scoped, fn($r) => !$r['awarded'])),
        'label' => 'Not yet awarded',
        'note'  => 'No Notice of Award date, so still a draft document set — correctly outside an awarded-value total.',
    ],
    [
        'count' => count(array_filter($scoped, fn($r) =>
            $r['measurable'] && $r['items_val'] > 0 && abs($r['items_val'] - $r['award']) > 0.5)),
        'label' => 'Items total differs from the contract amount',
        'note'  => 'Legitimate when a contract was executed at a figure the item grid was later edited away from — worth an eye either way.',
    ],
];

jsonResponse(true, '', [
    'has_pr_amount' => $hasPrAmount,
    'period'        => ['key' => $period, 'label' => $periodLabel, 'bids' => count($scoped)],
    'scale'         => [
        'bids'    => count($rows),
        'awarded' => count(array_filter($rows, fn($r) => $r['awarded'])),
        'value'   => array_sum(array_map(fn($r) => (float)($r['award'] ?? 0), $rows)),
        'locked'  => count(array_filter($rows, fn($r) => $r['locked'])),
        'lines'   => array_sum(array_column($rows, 'items_n')),
    ],
    'savings'   => $savings,
    'years'     => $byYear,
    'top'       => array_values(array_map($slim, $top)),
    'overruns'  => array_values(array_map($slim, $overruns)),
    'suppliers' => $bySupplier,
    'stages'    => $stages,
    'lead'      => $lead,
    'cycle'     => $cycle === null ? null : round($cycle, 1),
    'quality'   => $quality,
]);
