<?php
// System-wide activity log: who changed what, across every module that calls
// logAudit() — purchase orders, master data, inspections, access levels,
// reports, user accounts and sign-ins.
//
// settings.php has its own Audit Log tab that shows the last 200 rows with no
// filters. This is the same table (tbl_po_audit_logs on the HRMIS server) with
// the filters that make it usable once there are more than a screenful, and it
// is open to admins rather than super_admin only.
require_once '../config.php';
requireAuth();
requireSystemToolsAdmin();
header('Content-Type: application/json');
$db = getPoDB();

// Hard ceiling on any single response. The table grows without bound and
// nobody reads ten thousand rows in a browser; the filters are how you narrow
// it, and the response says when it has been cut short.
const MAX_ROWS = 1000;

$where  = ['1=1'];
$params = [];

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
if ($from !== '') { $where[] = 'Date_Created >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to   !== '') { $where[] = 'Date_Created <= ?'; $params[] = $to   . ' 23:59:59'; }

foreach (['user' => 'Username', 'module' => 'Module', 'action' => 'Action'] as $key => $col) {
    $val = trim($_GET[$key] ?? '');
    if ($val !== '') { $where[] = "{$col} = ?"; $params[] = $val; }
}

// Sign-ins are two thirds of this table (123 of the first 190 rows) and they
// bury the thing the page is for — who changed what. Hidden unless asked for,
// or unless 'auth' was picked in the module filter, which is an explicit
// request to see exactly those.
$includeAuth = ($_GET['include_auth'] ?? '') === '1';
if (!$includeAuth && trim($_GET['module'] ?? '') === '') {
    $where[] = "Module <> 'auth'";
}

// Free-text across the columns someone would actually search: the detail line
// (which carries the PO number), the username, and the action.
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $where[] = '(Details LIKE ? OR Username LIKE ? OR Action LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$sqlWhere = implode(' AND ', $where);

$count = $db->prepare("SELECT COUNT(*) FROM tbl_po_audit_logs WHERE {$sqlWhere}");
$count->execute($params);
$total = (int)$count->fetchColumn();

$stmt = $db->prepare(
    "SELECT ID, Username, Action, Module, Details, IP_Address, Date_Created
       FROM tbl_po_audit_logs
      WHERE {$sqlWhere}
      ORDER BY Date_Created DESC, ID DESC
      LIMIT " . MAX_ROWS
);
$stmt->execute($params);
$rows = fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_ASSOC));

// Filter choices come from the whole table, not the filtered set, so picking
// one value never empties the other dropdowns.
$distinct = function (string $col) use ($db): array {
    return $db->query("SELECT DISTINCT {$col} AS v FROM tbl_po_audit_logs
                        WHERE {$col} IS NOT NULL AND {$col} <> '' ORDER BY {$col}")
              ->fetchAll(PDO::FETCH_COLUMN);
};

jsonResponse(true, '', [
    'rows'    => $rows,
    'total'   => $total,
    'capped'  => $total > MAX_ROWS,
    'max'     => MAX_ROWS,
    'filters' => [
        'users'   => fixLegacyEncodingDeep($distinct('Username')),
        'modules' => $distinct('Module'),
        'actions' => $distinct('Action'),
    ],
]);
