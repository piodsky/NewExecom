<?php
// Item Catalog — item_categories + items, the product master every
// operational module (PR, RFQ, PO, Receiving, Inventory, Issuance) picks
// from. Company-wide master data. Anyone with module access can view; only
// branch_admin/super_admin can create, edit or deactivate.
require_once '../config.php';
requireModule('items');
header('Content-Type: application/json');

$user   = getCurrentUser();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$isAdminTier = in_array($user['role'], ['super_admin', 'branch_admin'], true);

if ($method === 'GET') {
    $categories = $db->query('SELECT id, name, parent_id, is_serialized, is_active FROM item_categories ORDER BY name')->fetchAll();

    $q = trim($_GET['q'] ?? '');
    $sql = 'SELECT i.id, i.sku, i.name, i.category_id, c.name AS category_name, i.brand, i.unit,
                   i.purchase_unit, i.purchase_conversion_factor,
                   i.is_serialized, i.default_cost, i.default_price, i.is_active
            FROM items i LEFT JOIN item_categories c ON c.id = i.category_id';
    $params = [];
    if ($q !== '') {
        $sql .= ' WHERE i.name LIKE ? OR i.sku LIKE ? OR i.brand LIKE ?';
        $params = ["%{$q}%", "%{$q}%", "%{$q}%"];
    }
    $sql .= ' ORDER BY i.name';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    jsonResponse(true, '', ['categories' => $categories, 'items' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!$isAdminTier) jsonResponse(false, 'Only administrators can manage the item catalog.');

    $data   = getInput();
    $action = $data['action'] ?? '';

    if ($action === 'create_category' || $action === 'update_category') {
        $name         = trim($data['name'] ?? '');
        $isSerialized = !empty($data['is_serialized']) ? 1 : 0;
        $parentId     = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        if ($name === '') jsonResponse(false, 'Category name is required.');

        if ($action === 'create_category') {
            $exists = $db->prepare('SELECT COUNT(*) FROM item_categories WHERE name = ?');
            $exists->execute([$name]);
            if ($exists->fetchColumn() > 0) jsonResponse(false, 'That category already exists.');
            $db->prepare('INSERT INTO item_categories (name, parent_id, is_serialized) VALUES (?,?,?)')->execute([$name, $parentId, $isSerialized]);
            logAudit('CREATE_CATEGORY', 'items', "Created category \"{$name}\"");
            jsonResponse(true, 'Category added.');
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid category.');

        // A self-referencing tree only needs one invariant enforced: a category
        // can't become its own ancestor. Categories are never many levels deep
        // in practice, so a bounded walk up the chosen parent's own chain is
        // simpler and just as correct as a recursive CTE here.
        if ($parentId !== null) {
            if ($parentId === $id) jsonResponse(false, 'A category cannot be its own parent.');
            $walk = $parentId;
            for ($i = 0; $i < 20 && $walk !== null; $i++) {
                if ($walk === $id) jsonResponse(false, 'That would create a category loop.');
                $stmt = $db->prepare('SELECT parent_id FROM item_categories WHERE id = ?');
                $stmt->execute([$walk]);
                $walk = $stmt->fetchColumn();
                $walk = $walk !== false && $walk !== null ? (int)$walk : null;
            }
        }

        $db->prepare('UPDATE item_categories SET name=?, parent_id=?, is_serialized=? WHERE id=?')->execute([$name, $parentId, $isSerialized, $id]);
        logAudit('UPDATE_CATEGORY', 'items', "Updated category \"{$name}\"");
        jsonResponse(true, 'Category updated.');
    }

    if ($action === 'create_item' || $action === 'update_item') {
        $sku          = trim($data['sku'] ?? '');
        $name         = trim($data['name'] ?? '');
        $categoryId   = $data['category_id'] ?? null;
        $brand        = trim($data['brand'] ?? '');
        $unit         = trim($data['unit'] ?? '') ?: 'unit';
        $isSerialized = !empty($data['is_serialized']) ? 1 : 0;
        $defaultCost  = $data['default_cost']  !== '' && $data['default_cost']  !== null ? (float)$data['default_cost']  : null;
        $defaultPrice = $data['default_price'] !== '' && $data['default_price'] !== null ? (float)$data['default_price'] : null;

        // Buy-by-box-sell-by-piece only makes sense for pooled/bulk stock —
        // a serialized item is always received one at a time (enforced in
        // api/receiving_reports.php), so force no conversion for those.
        $purchaseUnit = !$isSerialized ? (trim($data['purchase_unit'] ?? '') ?: null) : null;
        $conversionFactor = !$isSerialized && $purchaseUnit !== null
            ? max(0.001, (float)($data['purchase_conversion_factor'] ?? 1))
            : 1.0;

        if ($name === '') jsonResponse(false, 'Item name is required.');
        $categoryId = $categoryId !== '' && $categoryId !== null ? (int)$categoryId : null;

        if ($action === 'create_item') {
            if ($sku !== '') {
                $exists = $db->prepare('SELECT COUNT(*) FROM items WHERE sku = ?');
                $exists->execute([$sku]);
                if ($exists->fetchColumn() > 0) jsonResponse(false, 'That SKU is already in use.');
            }
            $db->prepare(
                'INSERT INTO items (sku, name, category_id, brand, unit, purchase_unit, purchase_conversion_factor, is_serialized, default_cost, default_price)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([$sku ?: null, $name, $categoryId, $brand ?: null, $unit, $purchaseUnit, $conversionFactor, $isSerialized, $defaultCost, $defaultPrice]);
            logAudit('CREATE_ITEM', 'items', "Created item \"{$name}\"");
            jsonResponse(true, 'Item added.');
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid item.');
        $db->prepare(
            'UPDATE items SET sku=?, name=?, category_id=?, brand=?, unit=?, purchase_unit=?, purchase_conversion_factor=?, is_serialized=?, default_cost=?, default_price=? WHERE id=?'
        )->execute([$sku ?: null, $name, $categoryId, $brand ?: null, $unit, $purchaseUnit, $conversionFactor, $isSerialized, $defaultCost, $defaultPrice, $id]);
        logAudit('UPDATE_ITEM', 'items', "Updated item \"{$name}\"");
        jsonResponse(true, 'Item updated.');
    }

    if ($action === 'toggle_item_active') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid item.');
        $stmt = $db->prepare('SELECT name, is_active FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, 'Item not found.');
        $newState = (int)$row['is_active'] ? 0 : 1;
        $db->prepare('UPDATE items SET is_active = ? WHERE id = ?')->execute([$newState, $id]);
        logAudit($newState ? 'ACTIVATE_ITEM' : 'DEACTIVATE_ITEM', 'items', $row['name']);
        jsonResponse(true, $newState ? 'Item activated.' : 'Item deactivated.');
    }

    if ($action === 'toggle_category_active') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid category.');
        $stmt = $db->prepare('SELECT name, is_active FROM item_categories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, 'Category not found.');
        $newState = (int)$row['is_active'] ? 0 : 1;
        $db->prepare('UPDATE item_categories SET is_active = ? WHERE id = ?')->execute([$newState, $id]);
        logAudit($newState ? 'ACTIVATE_CATEGORY' : 'DEACTIVATE_CATEGORY', 'items', $row['name']);
        jsonResponse(true, $newState ? 'Category activated.' : 'Category deactivated.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
