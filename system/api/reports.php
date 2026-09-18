<?php
// Example report over audit_logs — a template for the real Cost/Profit
// Reports module (Phase 5 of the structure plan): filters -> table -> CSV.
// Branch-scoped like every other module: a branch_admin/branch_user only
// ever sees their own branch's activity.
require_once '../config.php';
requireModule('reports');
header('Content-Type: application/json');

$user  = getCurrentUser();
$scope = getUserBranchScope($user);
$db    = getDB();

const MAX_ROWS = 500;

$where  = ["module <> 'auth'"];
$params = [];

if ($scope !== null) {
    $where[] = 'user_id IN (SELECT id FROM users WHERE branch_id = ?)';
    $params[] = $scope;
}

[$from, $to] = inputDateTimeRange($_GET['from'] ?? null, $_GET['to'] ?? null);
if ($from !== null) { $where[] = 'created_at >= ?'; $params[] = $from; }
if ($to !== null) { $where[] = 'created_at <= ?'; $params[] = $to; }

$sqlWhere = implode(' AND ', $where);
$stmt = $db->prepare(
    "SELECT username, action, module, details, created_at FROM audit_logs
     WHERE {$sqlWhere} ORDER BY created_at DESC LIMIT " . MAX_ROWS
);
$stmt->execute($params);

jsonResponse(true, '', ['rows' => $stmt->fetchAll()]);
