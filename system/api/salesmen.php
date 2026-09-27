<?php
// Salesman List — a plain lookup for "who sold this," not tied to system
// logins (mirrors Globalchips' salesman.dbf being a separate roster from
// user accounts). Company-wide master data. Anyone with module access can
// view; only branch_admin/super_admin can create, edit or deactivate.
require_once '../config.php';
requireModule('salesmen');
header('Content-Type: application/json');

$user   = getCurrentUser();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

if ($method === 'GET') {
    $q = trim($_GET['q'] ?? '');
    $sql = 'SELECT id, name, code, contact_number, email, is_active, created_at FROM salesmen';
    $params = [];
    if ($q !== '') {
        $sql .= ' WHERE name LIKE ? OR code LIKE ?';
        $params = ["%{$q}%", "%{$q}%"];
    }
    $sql .= ' ORDER BY name';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['salesmen' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage the salesman list.');

    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create' || $action === 'update') {
        $name    = trim($data['name'] ?? '');
        $code    = trim($data['code'] ?? '');
        $number  = trim($data['contact_number'] ?? '');
        $email   = trim($data['email'] ?? '');
        if ($name === '') jsonResponse(false, 'Salesman name is required.');

        if ($code !== '') {
            $exists = $db->prepare('SELECT COUNT(*) FROM salesmen WHERE code = ?' . ($action === 'update' ? ' AND id != ?' : ''));
            $params = $action === 'update' ? [$code, (int)($data['id'] ?? 0)] : [$code];
            $exists->execute($params);
            if ($exists->fetchColumn() > 0) jsonResponse(false, 'That code is already in use.');
        }

        if ($action === 'create') {
            $db->prepare(
                'INSERT INTO salesmen (name, code, contact_number, email) VALUES (?,?,?,?)'
            )->execute([$name, $code ?: null, $number ?: null, $email ?: null]);
            logAudit('CREATE_SALESMAN', 'salesmen', "Created salesman \"{$name}\"");
            jsonResponse(true, 'Salesman added.');
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid salesman.');
        $db->prepare(
            'UPDATE salesmen SET name=?, code=?, contact_number=?, email=? WHERE id=?'
        )->execute([$name, $code ?: null, $number ?: null, $email ?: null, $id]);
        logAudit('UPDATE_SALESMAN', 'salesmen', "Updated salesman \"{$name}\"");
        jsonResponse(true, 'Salesman updated.');
    }

    if ($action === 'toggle_active') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid salesman.');
        $stmt = $db->prepare('SELECT name, is_active FROM salesmen WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, 'Salesman not found.');
        $newState = (int)$row['is_active'] ? 0 : 1;
        $db->prepare('UPDATE salesmen SET is_active = ? WHERE id = ?')->execute([$newState, $id]);
        logAudit($newState ? 'ACTIVATE_SALESMAN' : 'DEACTIVATE_SALESMAN', 'salesmen', $row['name']);
        jsonResponse(true, $newState ? 'Salesman activated.' : 'Salesman deactivated.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
