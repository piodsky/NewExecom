<?php
// System-wide activity log: who changed what, across every module that calls
// logAudit() — user accounts, sign-ins, and every operational module as it
// ships. Open to branch_admin and super_admin.
require_once '../config.php';
requireRole(['super_admin', 'branch_admin']);
header('Content-Type: application/json');
$db = getDB();

// Hard ceiling on any single response. The table grows without bound and
// nobody reads ten thousand rows in a browser; the filters are how you narrow
// it, and the response says when it has been cut short.
const MAX_ROWS = 1000;

$where  = ['1=1'];
$params = [];

[$from, $to] = inputDateTimeRange($_GET['from'] ?? null, $_GET['to'] ?? null);
if ($from !== null) { $where[] = 'created_at >= ?'; $params[] = $from; }
if ($to !== null) { $where[] = 'created_at <= ?'; $params[] = $to; }

foreach (['user' => 'username', 'module' => 'module', 'action' => 'action'] as $key => $col) {
    $val = trim($_GET[$key] ?? '');
    if ($val !== '') { $where[] = "{$col} = ?"; $params[] = $val; }
}

// Sign-ins tend to bury the thing the page is for — who changed what. Hidden
// unless asked for, or unless 'auth' was picked in the module filter.
$includeAuth = ($_GET['include_auth'] ?? '') === '1';
if (!$includeAuth && trim($_GET['module'] ?? '') === '') {
    $where[] = "module <> 'auth'";
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $where[] = '(details LIKE ? OR username LIKE ? OR action LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$sqlWhere = implode(' AND ', $where);

$count = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE {$sqlWhere}");
$count->execute($params);
$total = (int)$count->fetchColumn();

// Aliased back to the capitalized column names the page's JS already reads.
$stmt = $db->prepare(
    "SELECT id AS ID, username AS Username, action AS Action, module AS Module,
            details AS Details, ip_address AS IP_Address, created_at AS Date_Created
       FROM audit_logs
      WHERE {$sqlWhere}
      ORDER BY created_at DESC, id DESC
      LIMIT " . MAX_ROWS
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Filter choices come from the whole table, not the filtered set, so picking
// one value never empties the other dropdowns.
$distinct = function (string $col) use ($db): array {
    return $db->query("SELECT DISTINCT {$col} AS v FROM audit_logs
                        WHERE {$col} IS NOT NULL AND {$col} <> '' ORDER BY {$col}")
              ->fetchAll(PDO::FETCH_COLUMN);
};

jsonResponse(true, '', [
    'rows'    => $rows,
    'total'   => $total,
    'capped'  => $total > MAX_ROWS,
    'max'     => MAX_ROWS,
    'filters' => [
        'users'   => $distinct('username'),
        'modules' => $distinct('module'),
        'actions' => $distinct('action'),
    ],
]);
