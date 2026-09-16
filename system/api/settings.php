<?php
// User Management — local `users` CRUD, branch- and role-scoped.
//
// super_admin manages every account in every branch. branch_admin manages
// only branch_user accounts within their own branch — canManageRole()/
// canAssignRole() in config.php enforce the role side of that, and every
// branch-touching query below is filtered by getUserBranchScope() so a
// branch_admin can never see or edit another branch's accounts even via a
// direct API call.
require_once '../config.php';
requireModule('settings');
header('Content-Type: application/json');

$actor = getCurrentUser();
$scope = getUserBranchScope($actor); // null = super_admin, sees every branch
$db    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

function currentFullName(string $first, string $middle, string $last, string $suffix): string {
    return trim(implode(' ', array_filter([$first, $middle, $last, $suffix])));
}

if ($method === 'GET') {
    $sql = 'SELECT u.id, u.username, u.full_name, u.first_name, u.middle_name, u.last_name,
                   u.suffix, u.department, u.role, u.branch_id, b.name AS branch_name,
                   u.is_active, u.last_login, u.created_at
            FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE 1=1';
    $params = [];
    if ($scope !== null) {
        $sql .= ' AND u.branch_id = ?';
        $params[] = $scope;
    }
    $sql .= ' ORDER BY u.full_name';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $branchStmt = $scope !== null
        ? $db->prepare('SELECT id, code, name FROM branches WHERE id = ? AND is_active = 1')
        : $db->prepare('SELECT id, code, name FROM branches WHERE is_active = 1 ORDER BY name');
    $branchStmt->execute($scope !== null ? [$scope] : []);

    jsonResponse(true, '', [
        'users'    => $users,
        'branches' => $branchStmt->fetchAll(),
        'roles'    => array_keys(ROLE_RANK),
    ]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $username   = trim($data['username'] ?? '');
        $password   = (string)($data['password'] ?? '');
        $firstName  = trim($data['first_name'] ?? '');
        $middleName = trim($data['middle_name'] ?? '');
        $lastName   = trim($data['last_name'] ?? '');
        $suffix     = trim($data['suffix'] ?? '');
        $department = trim($data['department'] ?? '');
        $role       = trim($data['role'] ?? 'branch_user');
        $branchId   = $data['branch_id'] ?? null;

        if ($username === '' || $password === '' || $firstName === '' || $lastName === '') {
            jsonResponse(false, 'Username, password, first name and last name are required.');
        }
        if (strlen($password) < 8) {
            jsonResponse(false, 'Password must be at least 8 characters.');
        }
        if (!canAssignRole($actor['role'], $role)) {
            jsonResponse(false, 'You are not allowed to create an account with that role.');
        }
        // super_admin has no branch; every other role must have one.
        if ($role !== 'super_admin') {
            $branchId = $scope !== null ? $scope : (int)$branchId;
            if (!$branchId) jsonResponse(false, 'Branch is required for this role.');
        } else {
            $branchId = null;
        }

        $exists = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $exists->execute([$username]);
        if ($exists->fetchColumn() > 0) {
            jsonResponse(false, 'That username is already taken.');
        }

        $fullName = currentFullName($firstName, $middleName, $lastName, $suffix);
        $stmt = $db->prepare(
            'INSERT INTO users (username, password, full_name, first_name, middle_name, last_name, suffix,
                                 department, role, branch_id, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,1)'
        );
        $stmt->execute([
            $username, password_hash($password, PASSWORD_DEFAULT), $fullName,
            $firstName, $middleName ?: null, $lastName, $suffix ?: null,
            $department ?: null, $role, $branchId,
        ]);

        logAudit('CREATE_USER', 'settings', "Created account \"{$username}\" ({$role})");
        jsonResponse(true, 'Account created.');
    }

    // Every action below targets an existing user — load and scope-check it first.
    $targetId = (int)($data['id'] ?? 0);
    if (!$targetId) jsonResponse(false, 'Invalid account.');

    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();
    if (!$target) jsonResponse(false, 'Account not found.');

    if ($scope !== null && (int)$target['branch_id'] !== $scope) {
        jsonResponse(false, 'Access denied for that account.');
    }
    if (!canManageRole($actor['role'], $target['role'])) {
        jsonResponse(false, 'You are not allowed to manage that account.');
    }

    if ($action === 'update') {
        $firstName  = trim($data['first_name'] ?? $target['first_name']);
        $middleName = trim($data['middle_name'] ?? $target['middle_name']);
        $lastName   = trim($data['last_name'] ?? $target['last_name']);
        $suffix     = trim($data['suffix'] ?? $target['suffix']);
        $department = trim($data['department'] ?? $target['department']);
        $role       = trim($data['role'] ?? $target['role']);
        $branchId   = $target['branch_id'];

        if ($firstName === '' || $lastName === '') {
            jsonResponse(false, 'First and last name are required.');
        }
        if ($role !== $target['role'] && !canAssignRole($actor['role'], $role)) {
            jsonResponse(false, 'You are not allowed to assign that role.');
        }
        if ($scope === null && isset($data['branch_id'])) {
            $branchId = $role === 'super_admin' ? null : ((int)$data['branch_id'] ?: null);
        }

        $fullName = currentFullName($firstName, $middleName, $lastName, $suffix);
        $db->prepare(
            'UPDATE users SET first_name=?, middle_name=?, last_name=?, suffix=?, full_name=?,
                               department=?, role=?, branch_id=? WHERE id=?'
        )->execute([
            $firstName, $middleName ?: null, $lastName, $suffix ?: null, $fullName,
            $department ?: null, $role, $branchId, $targetId,
        ]);

        logAudit('UPDATE_USER', 'settings', "Updated account \"{$target['username']}\"");
        jsonResponse(true, 'Account updated.');
    }

    if ($action === 'reset_password') {
        $newPassword = (string)($data['password'] ?? '');
        if (strlen($newPassword) < 8) {
            jsonResponse(false, 'Password must be at least 8 characters.');
        }
        $db->prepare('UPDATE users SET password = ? WHERE id = ?')
           ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
        logAudit('RESET_PASSWORD', 'settings', "Reset password for \"{$target['username']}\"");
        jsonResponse(true, 'Password reset.');
    }

    if ($action === 'toggle_active') {
        if ($targetId === (int)$actor['id']) {
            jsonResponse(false, 'You cannot deactivate your own account.');
        }
        $newState = (int)$target['is_active'] ? 0 : 1;
        if ($newState === 0 && $target['role'] === 'super_admin') {
            $countStmt = $db->prepare(
                "SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1 AND id <> ?"
            );
            $countStmt->execute([$targetId]);
            if ((int)$countStmt->fetchColumn() < 1) {
                jsonResponse(false, 'At least one active Super Administrator must remain.');
            }
        }
        $db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$newState, $targetId]);
        logAudit($newState ? 'ACTIVATE_USER' : 'DEACTIVATE_USER', 'settings', "\"{$target['username']}\"");
        jsonResponse(true, $newState ? 'Account activated.' : 'Account deactivated.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
