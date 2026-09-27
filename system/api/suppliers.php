<?php
// Supplier Management — EXECOM's own upstream vendors. Company-wide master
// data (no branch_id): every branch buys from the same supplier list.
// Anyone with module access can view; only branch_admin/super_admin can
// create, edit or deactivate.
require_once '../config.php';
requireModule('suppliers');
header('Content-Type: application/json');

$user   = getCurrentUser();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

if ($method === 'GET') {
    $q = trim($_GET['q'] ?? '');
    $sql = 'SELECT id, name, address, contact_person, contact_number, email, payment_terms, payment_terms_days, credit_limit, is_active, created_at FROM suppliers';
    $params = [];
    if ($q !== '') {
        $sql .= ' WHERE name LIKE ? OR contact_person LIKE ?';
        $params = ["%{$q}%", "%{$q}%"];
    }
    $sql .= ' ORDER BY name';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, '', ['suppliers' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage suppliers.');

    $data   = getInput();
    $action = $data['action'] ?? 'create';

    if ($action === 'create' || $action === 'update') {
        $name    = trim($data['name'] ?? '');
        $address = trim($data['address'] ?? '');
        $contact = trim($data['contact_person'] ?? '');
        $number  = trim($data['contact_number'] ?? '');
        $email   = trim($data['email'] ?? '');
        $terms   = trim($data['payment_terms'] ?? '');
        $termsDays = trim((string)($data['payment_terms_days'] ?? ''));
        $termsDays = $termsDays === '' ? null : max(0, (int)$termsDays);
        $creditLimit = (float)($data['credit_limit'] ?? 0);
        if ($creditLimit < 0) jsonResponse(false, 'Credit limit cannot be negative.');
        if ($name === '') jsonResponse(false, 'Supplier name is required.');

        if ($action === 'create') {
            $db->prepare(
                'INSERT INTO suppliers (name, address, contact_person, contact_number, email, payment_terms, payment_terms_days, credit_limit) VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$name, $address ?: null, $contact ?: null, $number ?: null, $email ?: null, $terms ?: null, $termsDays, $creditLimit]);
            logAudit('CREATE_SUPPLIER', 'suppliers', "Created supplier \"{$name}\"");
            jsonResponse(true, 'Supplier added.');
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid supplier.');
        $db->prepare(
            'UPDATE suppliers SET name=?, address=?, contact_person=?, contact_number=?, email=?, payment_terms=?, payment_terms_days=?, credit_limit=? WHERE id=?'
        )->execute([$name, $address ?: null, $contact ?: null, $number ?: null, $email ?: null, $terms ?: null, $termsDays, $creditLimit, $id]);
        logAudit('UPDATE_SUPPLIER', 'suppliers', "Updated supplier \"{$name}\"");
        jsonResponse(true, 'Supplier updated.');
    }

    if ($action === 'toggle_active') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid supplier.');
        $stmt = $db->prepare('SELECT name, is_active FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, 'Supplier not found.');
        $newState = (int)$row['is_active'] ? 0 : 1;
        $db->prepare('UPDATE suppliers SET is_active = ? WHERE id = ?')->execute([$newState, $id]);
        logAudit($newState ? 'ACTIVATE_SUPPLIER' : 'DEACTIVATE_SUPPLIER', 'suppliers', $row['name']);
        jsonResponse(true, $newState ? 'Supplier activated.' : 'Supplier deactivated.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
