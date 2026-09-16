<?php
// Dashboard stat cards + recent activity feed. Branch-scoped: a branch_admin/
// branch_user sees only their own branch's numbers; super_admin sees the
// whole company (see getUserBranchScope() in config.php).
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$user  = getCurrentUser();
$scope = getUserBranchScope($user);
$db    = getDB();

$userCountSql = 'SELECT COUNT(*) FROM users WHERE is_active = 1';
$userParams   = [];
if ($scope !== null) {
    $userCountSql .= ' AND branch_id = ?';
    $userParams[] = $scope;
}
$stmt = $db->prepare($userCountSql);
$stmt->execute($userParams);
$activeUsers = (int)$stmt->fetchColumn();

$branchCount = $scope !== null
    ? 1
    : (int)$db->query('SELECT COUNT(*) FROM branches WHERE is_active = 1')->fetchColumn();

$supplierCount = (int)$db->query('SELECT COUNT(*) FROM suppliers WHERE is_active = 1')->fetchColumn();
$clientCount   = (int)$db->query('SELECT COUNT(*) FROM clients WHERE is_active = 1')->fetchColumn();

// Recent activity — same audit_logs table the Activity Log page filters,
// just the newest handful for a landing-page glance.
$activitySql = 'SELECT username, action, module, details, created_at FROM audit_logs WHERE 1=1';
$activityParams = [];
if ($scope !== null) {
    // audit_logs has no branch_id of its own (it predates branch scoping);
    // narrowing by the acting user's branch keeps this feed relevant without
    // a schema change.
    $activitySql .= ' AND user_id IN (SELECT id FROM users WHERE branch_id = ?)';
    $activityParams[] = $scope;
}
$activitySql .= ' ORDER BY created_at DESC LIMIT 8';
$stmt = $db->prepare($activitySql);
$stmt->execute($activityParams);
$recentActivity = $stmt->fetchAll();

jsonResponse(true, '', [
    'stats' => [
        'active_users'   => $activeUsers,
        'branches'       => $branchCount,
        'suppliers'      => $supplierCount,
        'clients'        => $clientCount,
    ],
    'recent_activity' => $recentActivity,
]);
