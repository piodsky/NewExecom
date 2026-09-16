<?php
// Branch structure — company-wide, super_admin only (same hard-gate pattern
// as Access Levels: this defines the boundaries every other module's branch
// scoping is built on, so it is never individually toggleable).
require_once '../config.php';
requireRole(['super_admin']);
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query('SELECT id, code, name, address, contact_no, is_active, created_at FROM branches ORDER BY name');
    jsonResponse(true, '', ['branches' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create' || $action === 'update') {
        $code    = strtoupper(trim($data['code'] ?? ''));
        $name    = trim($data['name'] ?? '');
        $address = trim($data['address'] ?? '');
        $contact = trim($data['contact_no'] ?? '');

        if ($code === '' || $name === '') jsonResponse(false, 'Branch code and name are required.');
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $code)) {
            jsonResponse(false, 'Branch code must be 2-10 letters/numbers.');
        }

        $dupCheck = $action === 'create'
            ? $db->prepare('SELECT COUNT(*) FROM branches WHERE code = ?')
            : $db->prepare('SELECT COUNT(*) FROM branches WHERE code = ? AND id <> ?');
        $dupParams = $action === 'create' ? [$code] : [$code, (int)($data['id'] ?? 0)];
        $dupCheck->execute($dupParams);
        if ($dupCheck->fetchColumn() > 0) jsonResponse(false, 'That branch code is already in use.');

        if ($action === 'create') {
            $db->prepare('INSERT INTO branches (code, name, address, contact_no) VALUES (?,?,?,?)')
               ->execute([$code, $name, $address ?: null, $contact ?: null]);
            logAudit('CREATE_BRANCH', 'branches', "Created branch \"{$code} — {$name}\"");
            jsonResponse(true, 'Branch added.');
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid branch.');
        $db->prepare('UPDATE branches SET code=?, name=?, address=?, contact_no=? WHERE id=?')
           ->execute([$code, $name, $address ?: null, $contact ?: null, $id]);
        logAudit('UPDATE_BRANCH', 'branches', "Updated branch \"{$code} — {$name}\"");
        jsonResponse(true, 'Branch updated.');
    }

    if ($action === 'toggle_active') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid branch.');
        $stmt = $db->prepare('SELECT code, name, is_active FROM branches WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, 'Branch not found.');

        $newState = (int)$row['is_active'] ? 0 : 1;
        if ($newState === 0) {
            $userCount = $db->prepare('SELECT COUNT(*) FROM users WHERE branch_id = ? AND is_active = 1');
            $userCount->execute([$id]);
            if ((int)$userCount->fetchColumn() > 0) {
                jsonResponse(false, 'Reassign or deactivate this branch\'s active users first.');
            }
        }
        $db->prepare('UPDATE branches SET is_active = ? WHERE id = ?')->execute([$newState, $id]);
        logAudit($newState ? 'ACTIVATE_BRANCH' : 'DEACTIVATE_BRANCH', 'branches', "{$row['code']} — {$row['name']}");
        jsonResponse(true, $newState ? 'Branch activated.' : 'Branch deactivated.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
