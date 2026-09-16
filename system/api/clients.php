<?php
// Client Management — government/other end-clients for the Outgoing side
// (an LGU office, etc). Company-wide master data: any branch can serve any
// client. Anyone with module access can view; only branch_admin/super_admin
// can create, edit or deactivate.
require_once '../config.php';
requireModule('clients');
header('Content-Type: application/json');

$user   = getCurrentUser();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

if ($method === 'GET') {
    $q = trim($_GET['q'] ?? '');
    $sql = 'SELECT id, name, address, contact_person, contact_number, is_active, created_at FROM clients';
    $params = [];
    if ($q !== '') {
        $sql .= ' WHERE name LIKE ? OR contact_person LIKE ?';
        $params = ["%{$q}%", "%{$q}%"];
    }
    $sql .= ' ORDER BY name';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['clients' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage clients.');

    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create' || $action === 'update') {
        $name    = trim($data['name'] ?? '');
        $address = trim($data['address'] ?? '');
        $contact = trim($data['contact_person'] ?? '');
        $number  = trim($data['contact_number'] ?? '');
        if ($name === '') jsonResponse(false, 'Client name is required.');

        if ($action === 'create') {
            $db->prepare(
                'INSERT INTO clients (name, address, contact_person, contact_number) VALUES (?,?,?,?)'
            )->execute([$name, $address ?: null, $contact ?: null, $number ?: null]);
            logAudit('CREATE_CLIENT', 'clients', "Created client \"{$name}\"");
            jsonResponse(true, 'Client added.');
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid client.');
        $db->prepare(
            'UPDATE clients SET name=?, address=?, contact_person=?, contact_number=? WHERE id=?'
        )->execute([$name, $address ?: null, $contact ?: null, $number ?: null, $id]);
        logAudit('UPDATE_CLIENT', 'clients', "Updated client \"{$name}\"");
        jsonResponse(true, 'Client updated.');
    }

    if ($action === 'toggle_active') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid client.');
        $stmt = $db->prepare('SELECT name, is_active FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, 'Client not found.');
        $newState = (int)$row['is_active'] ? 0 : 1;
        $db->prepare('UPDATE clients SET is_active = ? WHERE id = ?')->execute([$newState, $id]);
        logAudit($newState ? 'ACTIVATE_CLIENT' : 'DEACTIVATE_CLIENT', 'clients', $row['name']);
        jsonResponse(true, $newState ? 'Client activated.' : 'Client deactivated.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
