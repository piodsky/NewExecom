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

$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
if ($from !== '') { $where[] = 'created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to   !== '') { $where[] = 'created_at <= ?'; $params[] = $to   . ' 23:59:59'; }

$sqlWhere = implode(' AND ', $where);
$stmt = $db->prepare(
    "SELECT username, action, module, details, created_at FROM audit_logs
     WHERE {$sqlWhere} ORDER BY created_at DESC LIMIT " . MAX_ROWS
);
$stmt->execute($params);

jsonResponse(true, '', ['rows' => $stmt->fetchAll()]);
