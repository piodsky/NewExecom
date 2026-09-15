<?php
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$db   = getPoDB();
$user = getCurrentUser();

/* ── Accounts + audit, both on the HRMIS server ──
   The local pyobasesystem_db was retired, so these read from HRMIS: real
   login accounts come from purchase_request.users, and the audit trail from
   tbl_po_audit_logs. The HRMIS users table has no is_active column — every
   row in it is a usable account — so "active" and "total" are the same
   number and the dashboard shows the one figure that is actually true. */
$totalUsers  = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers = $totalUsers;

$recentLogins = fixLegacyEncodingDeep($db->query(
    "SELECT Username AS username, Action AS action, Details AS details, Date_Created AS created_at
     FROM tbl_po_audit_logs
     WHERE Action IN ('LOGIN','LOGOUT') ORDER BY Date_Created DESC LIMIT 8"
)->fetchAll());

/* ── Quote of the day ──
   Picked by day-of-year so it's stable for the whole day and rotates at
   midnight. The file is local (system/data/quotes.json) — nothing is fetched
   over the network, matching this repo's no-CDN rule. */
function quoteOfTheDay(): ?array {
    $path = __DIR__ . '/../data/quotes.json';
    if (!is_readable($path)) return null;
    $all = json_decode((string)file_get_contents($path), true);
    if (!is_array($all) || !$all) return null;
    // 'z' is 0-based day of year; modulo keeps it in range whatever the file size.
    $idx = ((int)date('z')) % count($all);
    $q   = $all[$idx];
    $q['day']   = (int)date('z') + 1;
    $q['total'] = count($all);
    return $q;
}

/* ── Purchase Order figures (live HRMIS DB) ──
   Wrapped separately so a blip on 192.168.10.254 degrades the PO widgets
   instead of blanking the whole dashboard. */
$po       = null;
$poError  = null;
$poHealth = ['ok' => false, 'ms' => null];

try {
    $t0    = microtime(true);
    $poDb  = getPoDB();
    $poDb->query('SELECT 1')->fetchColumn();
    $poHealth = ['ok' => true, 'ms' => (int)round((microtime(true) - $t0) * 1000)];

    // Every count is bounded on BOTH ends: a handful of legacy rows carry
    // mistyped future Date_Created values (e.g. 2026-12-26), which an
    // open-ended ">= start of month" would wrongly pull into this month.
    // Takes the table alias so BOTH sides get qualified — prefixing the
    // string with "po." by hand only qualifies the first column and makes
    // the second one ambiguous once the query has a JOIN.
    $monthRange = fn(string $a = '') => ($p = $a ? "$a." : '')
        . "Date_Created >= DATE_FORMAT(NOW(), '%Y-%m-01')
           AND {$p}Date_Created < DATE_FORMAT(NOW(), '%Y-%m-01') + INTERVAL 1 MONTH";
    $monthWhere = $monthRange();
    $approved   = "Date_Approved_PO IS NOT NULL AND Date_Approved_PO <> ''";
    $received   = "Date_Received_Supplier IS NOT NULL AND Date_Received_Supplier <> ''";
    $coa        = "Date_COA_Received IS NOT NULL AND Date_COA_Received <> ''";

    $count = function (string $where) use ($poDb): int {
        return (int)$poDb->query("SELECT COUNT(*) FROM tbl_purchase_order WHERE isDeleted = 0 AND $where")->fetchColumn();
    };

    // Built into a local and only published to $po once every query has
    // succeeded — a half-filled object would render tiles but blank charts.
    $stats = [
        'this_month'       => $count($monthWhere),
        'pending_approval' => $count("(Date_Approved_PO IS NULL OR Date_Approved_PO = '')"),
        'approved_ytd'     => $count("$approved AND YEAR(Date_Created) = YEAR(CURDATE())"),
        'suppliers'        => (int)$poDb->query("SELECT COUNT(*) FROM ref_supplier")->fetchColumn(),
    ];

    $stats['amount_this_month'] = (float)$poDb->query(
        "SELECT COALESCE(SUM(pd.Total_Cost), 0)
         FROM tbl_purchase_order_items_details pd
         INNER JOIN tbl_purchase_order po ON po.PO_Number = pd.PO_Number
         WHERE pd.isDeleted = 0 AND po.isDeleted = 0 AND {$monthRange('po')}"
    )->fetchColumn();

    /* Status distribution — a real pipeline, not invented statuses.
       The four stages are mutually exclusive and come straight from the date
       columns the Additional Details panel already maintains. Scoped to 12
       months because the current month's POs have not progressed yet (they'd
       all land in one slice and the donut would say nothing). */
    $win = "Date_Created >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND Date_Created < CURDATE() + INTERVAL 1 DAY";
    $stats['status'] = [
        ['label' => 'Awaiting approval', 'value' => $count("$win AND (Date_Approved_PO IS NULL OR Date_Approved_PO = '')"),        'color' => '#f59e0b'],
        ['label' => 'Approved',          'value' => $count("$win AND $approved AND NOT ($received)"),                              'color' => '#2563eb'],
        ['label' => 'With supplier',     'value' => $count("$win AND $received AND NOT ($coa)"),                                   'color' => '#22c55e'],
        ['label' => 'COA received',      'value' => $count("$win AND $coa"),                                                       'color' => '#8b5cf6'],
    ];

    /* Daily trend — this month vs last month, keyed by day-of-month so the
       two series line up on the same x-axis. */
    $trend = function (string $monthExpr) use ($poDb): array {
        $rows = $poDb->query(
            "SELECT DAY(Date_Created) d, COUNT(*) c
             FROM tbl_purchase_order
             WHERE isDeleted = 0
               AND Date_Created >= DATE_FORMAT($monthExpr, '%Y-%m-01')
               AND Date_Created <  DATE_FORMAT($monthExpr, '%Y-%m-01') + INTERVAL 1 MONTH
             GROUP BY DAY(Date_Created)"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $out = [];
        for ($d = 1; $d <= 31; $d++) $out[] = (int)($rows[$d] ?? 0);
        return $out;
    };
    $stats['trend'] = [
        'this_month' => $trend('NOW()'),
        'last_month' => $trend('NOW() - INTERVAL 1 MONTH'),
        'days'       => (int)date('t'),
        'today'      => (int)date('j'),
    ];

    /* Recent POs. Total_Cost is summed in a correlated subquery with
       isDeleted = 0 — editing a PO soft-deletes its old item rows and inserts
       new ones, so an unfiltered SUM double-counts every revision. */
    $recentPo = $poDb->query(
        "SELECT po.PO_Number AS po_number,
                po.Supplier_Name AS supplier_name,
                po.Date_Created  AS date_created,
                po.Date_Approved_PO AS date_approved,
                po.Date_Received_Supplier AS date_received,
                po.Date_COA_Received AS date_coa,
                (SELECT COALESCE(SUM(pd.Total_Cost), 0)
                   FROM tbl_purchase_order_items_details pd
                  WHERE pd.PO_Number = po.PO_Number AND pd.isDeleted = 0) AS amount
         FROM tbl_purchase_order po
         WHERE po.isDeleted = 0 AND po.Date_Created < CURDATE() + INTERVAL 1 DAY
         ORDER BY po.Date_Created DESC
         LIMIT 6"
    )->fetchAll(PDO::FETCH_ASSOC);
    $stats['recent'] = fixLegacyEncodingDeep($recentPo);

    $po = $stats;
} catch (Throwable $e) {
    $poError = 'Purchase Order database unavailable.';
    error_log('Dashboard PO stats failed: ' . $e->getMessage());
}

/* ── System summary — every row is an actual probe, nothing hardcoded. ── */
$hrmis = ['ok' => false, 'ms' => null];
try {
    $t0 = microtime(true);
    getHrmisDB()->query('SELECT 1')->fetchColumn();
    $hrmis = ['ok' => true, 'ms' => (int)round((microtime(true) - $t0) * 1000)];
} catch (Throwable $e) {
    error_log('Dashboard HRMIS probe failed: ' . $e->getMessage());
}

jsonResponse(true, '', [
    'total_users'  => $totalUsers,
    'active_users' => $activeUsers,
    'your_role'    => hrmisRoleName($user['role_id'] ?? null) ?? roleLabel($user['role']),
    'recent'       => $recentLogins,
    'quote'        => quoteOfTheDay(),
    'po'           => $po,
    'po_error'     => $poError,
    'system'       => [
        // No local_db entry any more — the local pyobasesystem_db was retired
        // and its constants removed with it, so reporting on it was a fatal
        // "Undefined constant DB_NAME". The two HRMIS connections below are
        // now the whole of this app's data tier.
        'po_db'    => ['ok' => $poHealth['ok'], 'detail' => PO_DB_NAME . ' @ ' . PO_DB_HOST, 'ms' => $poHealth['ms']],
        'hrmis_db' => ['ok' => $hrmis['ok'],    'detail' => HRMIS_DB_NAME . ' @ ' . HRMIS_DB_HOST, 'ms' => $hrmis['ms']],
        'php'      => ['ok' => true,            'detail' => 'PHP ' . PHP_VERSION],
    ],
]);
