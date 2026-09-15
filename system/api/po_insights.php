<?php
// PO Insights — one read-only pass over the live purchase order data.
//
// Everything on the page comes from ONE heavy query plus a handful of tiny
// ones. The heavy query pulls one fact row per live PO (18k rows, ~0.4s) and
// every breakdown below is then computed in PHP. Doing it the other way round
// — a GROUP BY per panel — would re-scan the 168k item rows a dozen times over
// for the same answers, on a shared production server.
//
// Read-only by design: this endpoint has no POST branch and writes nothing.
require_once '../config.php';
requireReportsAdmin();
header('Content-Type: application/json');

$db = getPoDB();

// The dashboard wants a three-figure strip, not the whole page. Everything it
// needs falls out of the two bulk reads that run either way, so summary mode
// skips the extra scans — the inspection set, the reference lists and the item
// price sweep — and answers in about half the time.
$summary = isset($_GET['summary']);

// ── helpers ─────────────────────────────────────────────────────────────────
// The three workflow dates are varchar columns holding free text, so "empty"
// has several spellings in the live data and '0000-00-00' is one of them.
$blank = function ($v): bool {
    $v = trim((string)$v);
    return $v === '' || strpos($v, '0000-00-00') === 0;
};
$isoDay = function ($v) use ($blank) {
    if ($blank($v)) return null;
    return preg_match('/^(\d{4}-\d{2}-\d{2})/', trim((string)$v), $m) ? $m[1] : null;
};
$dayDiff = function (?string $a, ?string $b): ?int {
    if (!$a || !$b) return null;
    $ta = strtotime($a);
    $tb = strtotime($b);
    if ($ta === false || $tb === false) return null;
    return (int)round(($tb - $ta) / 86400);
};
// Same normalisation the supplier cleanup tool uses to decide two spellings
// are the same name, so the counts here agree with what that tool would merge.
$canon = fn($v) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$v));
$norm  = fn($v) => preg_replace('/\s+/', ' ', trim((string)$v));

// ── the two bulk reads ──────────────────────────────────────────────────────
// Deliberately two queries merged in PHP rather than one joined query. On
// MySQL 5.5 a derived table gets no index, so LEFT JOINing the per-PO totals
// onto the headers took 11.8s against this data; read separately and matched
// up in a hash it is 0.4s, and the POs that carry no item rows survive the
// merge instead of being dropped the way an INNER JOIN drops them.
$facts = $db->query(
    'SELECT PO_Number, Date_PO, Department, Supplier_Name, Mode_of_Procurement,
            Print_PO, Print_Obligation, Barcode_Number,
            Date_Approved_PO, Date_Received_Supplier, Date_COA_Received
     FROM tbl_purchase_order WHERE isDeleted = 0'
)->fetchAll(PDO::FETCH_ASSOC);

$totals = [];
foreach ($db->query(
    'SELECT PO_Number, SUM(Total_Cost) val, COUNT(*) nl
     FROM tbl_purchase_order_items_details WHERE isDeleted = 0 GROUP BY PO_Number'
) as $t) {
    $totals[$t['PO_Number']] = ['val' => (float)$t['val'], 'nl' => (int)$t['nl']];
}

$inspected = $summary ? [] : array_fill_keys(
    $db->query('SELECT DISTINCT PO_Number FROM tbl_inspection WHERE isDeleted = 0')
       ->fetchAll(PDO::FETCH_COLUMN), true);

// ── shape each PO once ──────────────────────────────────────────────────────
$rows = [];
foreach ($facts as $f) {
    $day  = $isoDay($f['Date_PO']);
    $appr = $isoDay($f['Date_Approved_PO']);
    $recv = $isoDay($f['Date_Received_Supplier']);
    $t    = $totals[$f['PO_Number']] ?? ['val' => 0.0, 'nl' => 0];
    $rows[] = [
        'po'        => $f['PO_Number'],
        'day'       => $day,
        'year'      => $day ? (int)substr($day, 0, 4) : 0,
        'month'     => $day ? substr($day, 0, 7) : '',
        'dept'      => $norm($f['Department']),
        'sup'       => $norm($f['Supplier_Name']),
        'mode'      => $norm($f['Mode_of_Procurement']),
        'val'       => $t['val'],
        'lines'     => $t['nl'],
        'approved'  => $appr !== null,
        'appr_days' => $dayDiff($day, $appr),
        'recv_days' => $dayDiff($day, $recv),
        'has_coa'   => !$blank($f['Date_COA_Received']),
        'printed'   => (int)$f['Print_PO'] === 1,
        'obligated' => (int)$f['Print_Obligation'] === 1,
        'barcode'   => !$blank($f['Barcode_Number']),
        'inspected' => isset($inspected[$f['PO_Number']]),
    ];
}

// Item groups whose PO_Number matches no live header — left behind when a PO
// was deleted but its lines were not. Invisible everywhere else in the system,
// and they are the reason a raw SUM over live item rows comes out higher than
// the total of every live PO.
$orphanGroups = 0;
$orphanValue  = 0.0;
$livePo = array_fill_keys(array_column($rows, 'po'), true);
foreach ($totals as $poNumber => $t) {
    if (isset($livePo[$poNumber])) continue;
    $orphanGroups++;
    $orphanValue += $t['val'];
}
// ── period scope, for the panels where "lately" is the useful question ──────
$period = in_array($_GET['period'] ?? '', ['ytd', '12m'], true) ? $_GET['period'] : 'all';
$thisYear = (int)date('Y');
$cut12    = date('Y-m-d', strtotime('-12 months'));
$inPeriod = function (array $r) use ($period, $thisYear, $cut12): bool {
    if ($period === 'ytd') return $r['year'] === $thisYear;
    if ($period === '12m') return $r['day'] !== null && $r['day'] >= $cut12;
    return true;
};
$scoped = array_values(array_filter($rows, $inPeriod));
$periodLabel = ['all' => 'All time', 'ytd' => 'This year (' . $thisYear . ')', '12m' => 'Last 12 months'][$period];

// ── trend (always all time — the point of it is the shape over years) ───────
$years = [];
foreach ($rows as $r) {
    if (!$r['year']) continue;
    $years[$r['year']]['pos'] = ($years[$r['year']]['pos'] ?? 0) + 1;
    $years[$r['year']]['val'] = ($years[$r['year']]['val'] ?? 0) + $r['val'];
}
ksort($years);
$yearList = [];
foreach ($years as $y => $a) $yearList[] = ['y' => $y, 'pos' => $a['pos'], 'val' => round($a['val'], 2)];

// Months from a year back up to the current one. Capped at this month on
// purpose: a PO mis-dated into next December would otherwise open a gap of
// empty columns and make itself the "quietest month" of the series. It is
// still counted, and still named, under data quality.
$months = [];
$since  = date('Y-m', strtotime('-13 months'));
$until  = date('Y-m');
foreach ($rows as $r) {
    if ($r['month'] === '' || $r['month'] < $since || $r['month'] > $until) continue;
    $months[$r['month']]['pos'] = ($months[$r['month']]['pos'] ?? 0) + 1;
    $months[$r['month']]['val'] = ($months[$r['month']]['val'] ?? 0) + $r['val'];
}
ksort($months);
$monthList = [];
foreach ($months as $m => $a) $monthList[] = ['m' => $m, 'pos' => $a['pos'], 'val' => round($a['val'], 2)];

// ── top-N by value, on the canonical name so spelling drift doesn't split a
//    supplier across two bars. The label shown is the most common spelling. ──
$topBy = function (array $set, string $field, int $limit) use ($canon) {
    $g = [];
    foreach ($set as $r) {
        $name = $r[$field];
        if ($name === '') $name = '(blank)';
        $k = $canon($name) ?: '(blank)';
        $g[$k]['val']  = ($g[$k]['val'] ?? 0) + $r['val'];
        $g[$k]['pos']  = ($g[$k]['pos'] ?? 0) + 1;
        $g[$k]['spellings'][$name] = ($g[$k]['spellings'][$name] ?? 0) + 1;
    }
    uasort($g, fn($a, $b) => $b['val'] <=> $a['val']);
    $out = [];
    foreach (array_slice($g, 0, $limit, true) as $a) {
        arsort($a['spellings']);
        $out[] = [
            'name'      => (string)array_key_first($a['spellings']),
            'pos'       => $a['pos'],
            'val'       => round($a['val'], 2),
            'spellings' => count($a['spellings']),
        ];
    }
    return [$out, count($g)];
};
[$suppliers, $supplierCount] = $topBy($scoped, 'sup', 10);
[$offices,   $officeCount]   = $topBy($scoped, 'dept', 10);

// ── size bands ──────────────────────────────────────────────────────────────
$bands = [
    ['label' => 'under 10k',  'lo' => 0,       'hi' => 10000],
    ['label' => '10k – 50k',  'lo' => 10000,   'hi' => 50000],
    ['label' => '50k – 100k', 'lo' => 50000,   'hi' => 100000],
    ['label' => '100k – 500k','lo' => 100000,  'hi' => 500000],
    ['label' => '500k – 1M',  'lo' => 500000,  'hi' => 1000000],
    ['label' => 'over 1M',    'lo' => 1000000, 'hi' => INF],
];
foreach ($bands as &$b) {
    $b['pos'] = $b['val'] = $b['unapproved'] = $b['unapproved_val'] = 0;
    foreach ($scoped as $r) {
        if ($r['val'] < $b['lo'] || $r['val'] >= $b['hi']) continue;
        $b['pos']++;
        $b['val'] += $r['val'];
        if (!$r['approved']) { $b['unapproved']++; $b['unapproved_val'] += $r['val']; }
    }
    $b['val'] = round($b['val'], 2);
    $b['unapproved_val'] = round($b['unapproved_val'], 2);
    unset($b['lo'], $b['hi']);
}
unset($b);

// ── the approval gap: the headline finding, so it gets its own numbers ──────
$big      = array_values(array_filter($rows, fn($r) => $r['val'] >= 1000000));
$small    = array_values(array_filter($rows, fn($r) => $r['val'] <  1000000));
// Public / competitive bidding only. "Negotiated (Sec. 53.1 Two Failed
// Biddings)" also contains the word and is a different route entirely, so it
// is excluded rather than quietly inflating the bidding figure.
$isBid    = fn($r) => stripos($r['mode'], 'bidding') !== false
                   && stripos($r['mode'], 'negotiated') === false;
$unappr   = array_values(array_filter($rows, fn($r) => !$r['approved']));
$bigBid   = array_values(array_filter($big, $isBid));
usort($unappr, fn($a, $b) => $b['val'] <=> $a['val']);

$sum = fn(array $set) => round(array_sum(array_column($set, 'val')), 2);
$approval = [
    'unapproved_pos'   => count($unappr),
    'unapproved_val'   => $sum($unappr),
    'big_pos'          => count($big),
    'big_unapproved'   => count(array_filter($big, fn($r) => !$r['approved'])),
    'big_unapproved_val' => $sum(array_values(array_filter($big, fn($r) => !$r['approved']))),
    'small_pos'        => count($small),
    'small_unapproved' => count(array_filter($small, fn($r) => !$r['approved'])),
    'bid_big_pos'      => count($bigBid),
    'bid_big_unapproved' => count(array_filter($bigBid, fn($r) => !$r['approved'])),
    'biggest'          => array_map(fn($r) => [
        'po' => $r['po'], 'day' => $r['day'], 'sup' => $r['sup'], 'val' => round($r['val'], 2),
    ], array_slice($unappr, 0, 6)),
];

// ── process health ──────────────────────────────────────────────────────────
$recent   = array_values(array_filter($rows, fn($r) => $r['year'] >= $thisYear - 1));
$apprDays = array_values(array_filter(array_column($recent, 'appr_days'), fn($d) => $d !== null));
$recvDays = array_values(array_filter(array_column($recent, 'recv_days'), fn($d) => $d !== null));
$avg = fn(array $a) => $a ? round(array_sum($a) / count($a), 1) : null;
$process = [
    'window'        => ($thisYear - 1) . '–' . $thisYear,
    'appr_n'        => count($apprDays),
    'appr_avg'      => $avg($apprDays),
    'appr_worst'    => $apprDays ? max($apprDays) : null,
    'appr_negative' => count(array_filter($apprDays, fn($d) => $d < 0)),
    'recv_n'        => count($recvDays),
    'recv_avg'      => $avg($recvDays),
    'printed'       => count(array_filter($rows, fn($r) => $r['printed'])),
    'obligated'     => count(array_filter($rows, fn($r) => $r['obligated'])),
    'coa'           => count(array_filter($rows, fn($r) => $r['has_coa'])),
    'coa_recent'    => count(array_filter($recent, fn($r) => $r['has_coa'])),
    'total'         => count($rows),
    'recent_total'  => count($recent),
];

// The dashboard strip stops here: it shows the approval gap and the orphan
// lines, and nothing below this point would ever reach it.
if ($summary) {
    jsonResponse(true, '', [
        'scale'    => ['pos' => count($rows), 'value' => round(array_sum(array_column($rows, 'val')), 2)],
        'approval' => $approval,
        'process'  => $process,
        'orphans'  => ['groups' => $orphanGroups, 'value' => round($orphanValue, 2)],
    ]);
}

// ── coverage by year: DTS barcode and inspection ────────────────────────────
$coverage = [];
foreach ($rows as $r) {
    if ($r['year'] < 2022) continue;
    $coverage[$r['year']]['pos']       = ($coverage[$r['year']]['pos'] ?? 0) + 1;
    $coverage[$r['year']]['barcode']   = ($coverage[$r['year']]['barcode'] ?? 0) + ($r['barcode'] ? 1 : 0);
    $coverage[$r['year']]['inspected'] = ($coverage[$r['year']]['inspected'] ?? 0) + ($r['inspected'] ? 1 : 0);
}
ksort($coverage);
$coverageList = [];
foreach ($coverage as $y => $a) $coverageList[] = ['y' => $y] + $a;

// ── data quality ────────────────────────────────────────────────────────────
$one = fn(string $sql) => $db->query($sql)->fetchColumn();
$dupes = $db->query(
    'SELECT PO_Number, COUNT(*) c FROM tbl_purchase_order WHERE isDeleted = 0
     GROUP BY PO_Number HAVING c > 1'
)->fetchAll(PDO::FETCH_ASSOC);
$odd = $db->query(
    'SELECT PO_Number, Date_PO, Date_Created FROM tbl_purchase_order
     WHERE isDeleted = 0 AND (Date_PO > NOW() OR YEAR(Date_PO) < 2020) ORDER BY Date_PO LIMIT 6'
)->fetchAll(PDO::FETCH_ASSOC);


$quality = [
    ['label' => 'Item lines belonging to no live PO',
     'count' => $orphanGroups,
     'note'  => 'Worth ₱' . number_format($orphanValue, 2)
              . '. Lines left behind when a PO was deleted — they inflate any total taken straight off the item table.'],
    ['label' => 'POs with no item rows at all',
     'count' => count(array_filter($rows, fn($r) => $r['lines'] === 0)),
     'note'  => 'A purchase order with nothing on it. Nothing else in the system flags these.'],
    ['label' => 'PO numbers used more than once',
     'count' => count($dupes),
     'note'  => $dupes ? 'Live rows sharing a number: ' . implode(', ',
                    array_map(fn($d) => $d['PO_Number'] . ' (' . $d['c'] . ')', $dupes))
                       : 'Every live PO number is unique.'],
    ['label' => 'Dates that cannot be right',
     'count' => count($odd),
     'note'  => $odd ? implode(' · ', array_map(
                    fn($o) => $o['PO_Number'] . ' dated ' . substr($o['Date_PO'], 0, 10), $odd))
                     : 'No PO dated in the future or before 2020.'],
    ['label' => 'Item lines with a zero total',
     'count' => (int)$one('SELECT COUNT(*) FROM tbl_purchase_order_items_details WHERE isDeleted = 0 AND Total_Cost = 0'),
     'note'  => 'Mostly the LOT / heading rows that carry no price — expected, not a fault.'],
    ['label' => 'POs with no mode of procurement',
     'count' => count(array_filter($rows, fn($r) => $r['mode'] === '')),
     'note'  => 'The printed PO leaves the mode blank on these.'],
    ['label' => 'POs still to be printed',
     'count' => count($rows) - $process['printed'],
     'note'  => 'Never printed from this system, so never locked.'],
];

// ── free-text sprawl against the reference lists ────────────────────────────
$sprawl = [];
foreach ([
    ['col' => 'Supplier_Name', 'label' => 'Supplier', 'ref' => 'ref_supplier',   'tab' => 'suppliers'],
    ['col' => 'Department',    'label' => 'Office',   'ref' => 'ref_department', 'tab' => 'departments'],
    ['col' => 'Category',      'label' => 'Category', 'ref' => 'ref_category',   'tab' => 'categories'],
] as $s) {
    $vals = $db->query("SELECT DISTINCT TRIM({$s['col']}) v FROM tbl_purchase_order
                        WHERE isDeleted = 0 AND TRIM({$s['col']}) <> ''")->fetchAll(PDO::FETCH_COLUMN);
    $keys = [];
    foreach ($vals as $v) $keys[$canon($v)] = true;
    $sprawl[] = [
        'label'       => $s['label'],
        'tab'         => $s['tab'],
        'spellings'   => count($vals),
        'canonical'   => count($keys),
        'collapsible' => count($vals) - count($keys),
        'ref_rows'    => (int)$one("SELECT COUNT(*) FROM {$s['ref']}"),
        'ref'         => $s['ref'],
    ];
}

// ── unit cost spread: same words, very different price ──────────────────────
// Alias-free HAVING/ORDER BY: MySQL 5.5 refuses to reference an aggregate by
// its alias there.
$prices = $db->query(
    "SELECT UPPER(TRIM(SUBSTRING(Items_Description, 1, 60))) item, Unit unit, COUNT(*) buys,
            MIN(Unit_Cost) lo, MAX(Unit_Cost) hi, AVG(Unit_Cost) av
     FROM tbl_purchase_order_items_details
     WHERE isDeleted = 0 AND Unit_Cost > 0 AND Date_PO >= '" . ($thisYear - 1) . "-01-01'
       AND Items_Description REGEXP '[A-Za-z]{3}'
     GROUP BY item, unit
     HAVING COUNT(*) >= 15 AND MAX(Unit_Cost) / MIN(Unit_Cost) >= 3
     ORDER BY MAX(Unit_Cost) / MIN(Unit_Cost) DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

// ── scale ───────────────────────────────────────────────────────────────────
$scale = [
    'pos'      => count($rows),
    'history'  => (int)$one('SELECT COUNT(*) FROM tbl_purchase_order WHERE isDeleted <> 0'),
    'lines'    => array_sum(array_column($rows, 'lines')),
    'value'    => round(array_sum(array_column($rows, 'val')), 2),
    'catalog'  => (int)$one('SELECT COUNT(*) FROM tbl_ppmp_bac_items'),
    'first'    => $yearList ? $yearList[0]['y'] : null,
    'suppliers'=> $supplierCount,
    'offices'  => $officeCount,
];

jsonResponse(true, '', fixLegacyEncodingDeep([
    'scale'     => $scale,
    'period'    => ['key' => $period, 'label' => $periodLabel,
                    'pos' => count($scoped), 'val' => round(array_sum(array_column($scoped, 'val')), 2)],
    'years'     => $yearList,
    'months'    => $monthList,
    'suppliers' => $suppliers,
    'offices'   => $offices,
    'bands'     => $bands,
    'approval'  => $approval,
    'process'   => $process,
    'coverage'  => $coverageList,
    'quality'   => $quality,
    'sprawl'    => $sprawl,
    'prices'    => $prices,
    'prices_window' => ($thisYear - 1) . ' onward',
]));
