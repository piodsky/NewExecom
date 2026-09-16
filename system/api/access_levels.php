<?php
// Role x module access matrix — super_admin only. Toggling a cell writes
// role_module_access; a role with no row for a module defaults to allowed
// (see getEffectiveModuleAccess() in config.php).
require_once '../config.php';
requireRole(['super_admin']);
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Never let $role be assigned super_admin: it must always see every module.
$editableRoles = array_diff(array_keys(ROLE_RANK), ['super_admin']);

if ($method === 'GET') {
    $stmt = $db->query('SELECT role, module_key, can_access FROM role_module_access');
    $rows = $stmt->fetchAll();

    $matrix = [];
    foreach ($editableRoles as $role) {
        foreach (ACCESS_MODULES as $key => $label) {
            $matrix[$role][$key] = true; // default
        }
    }
    foreach ($rows as $row) {
        if (isset($matrix[$row['role']][$row['module_key']])) {
            $matrix[$row['role']][$row['module_key']] = (bool)$row['can_access'];
        }
    }

    jsonResponse(true, '', [
        'roles'   => array_values($editableRoles),
        'modules' => ACCESS_MODULES,
        'matrix'  => $matrix,
    ]);
}

if ($method === 'POST') {
    $data      = getInput();
    $role      = trim($data['role'] ?? '');
    $moduleKey = trim($data['module_key'] ?? '');
    $canAccess = !empty($data['can_access']) ? 1 : 0;

    if (!in_array($role, $editableRoles, true) || !isset(ACCESS_MODULES[$moduleKey])) {
        jsonResponse(false, 'Invalid role or module.');
    }

    $db->prepare(
        'INSERT INTO role_module_access (role, module_key, can_access) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE can_access = VALUES(can_access)'
    )->execute([$role, $moduleKey, $canAccess]);

    logAudit('UPDATE_ACCESS', 'access_levels',
        "Set {$moduleKey} = " . ($canAccess ? 'allowed' : 'denied') . " for role {$role}");

    jsonResponse(true, 'Access updated.');
}

jsonResponse(false, 'Method not allowed.');
