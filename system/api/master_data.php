<?php
// Manages the shared HRMIS picklist tables the PO module's autosuggest
// fields draw from. Reading the list is open to anyone who can use the PO
// module (the Create/Edit PO form needs it for autosuggest); adding/removing
// entries stays admin/super_admin only — these tables (ref_department,
// ref_supplier, etc.) are shared across other HRMIS modules too, not PO-only.
require_once '../config.php';
requireModule('purchase_orders');
header('Content-Type: application/json');
$db = getPoDB();

// One entry per manageable category: which table/column holds the value,
// whether it has an isDeleted column (soft-delete) or not (hard delete —
// ref_division/ref_supplier predate that convention in this HRMIS schema).
// 'extraCol' (optional) pulls one more column along for autosuggest to use
// (e.g. supplier address, so picking a supplier can autofill its address).
// 'extraCol2' (optional, suppliers only so far) pulls a third column —
// Contact_Number is a legacy `bigint` column, not varchar, so a Philippine
// mobile number's leading 0 doesn't survive storage; formatContactNumber()
// below re-adds it for display. Changing that column to varchar would fix
// this properly but is a live schema change, so it's left as-is.
const CATEGORIES = [
    'categories'  => ['table' => 'ref_category',             'idCol' => 'ID',         'valueCol' => 'Category',            'softDelete' => true],
    'departments' => ['table' => 'ref_department',          'idCol' => 'ID',         'valueCol' => 'Department',          'softDelete' => true],
    // 'parentCol'/'parentCat' link a table to the one above it in the
    // department -> division -> section chain. Before this existed, adding a
    // division here left department_ID at its 0 default, i.e. an orphan that
    // belongs to no department — 12 of the 71 live divisions are in that
    // state. Anything with a parent now has to be given one.
    'divisions'   => ['table' => 'ref_division',             'idCol' => 'ID',         'valueCol' => 'Division',            'softDelete' => false, 'parentCol' => 'department_ID', 'parentCat' => 'departments'],
    'sections'    => ['table' => 'ref_section',               'idCol' => 'ID',         'valueCol' => 'Section',             'softDelete' => false, 'parentCol' => 'division_ID',   'parentCat' => 'divisions'],
    'suppliers'   => ['table' => 'ref_supplier',              'idCol' => 'SupplierID', 'valueCol' => 'Supplier_Name',       'softDelete' => false, 'extraCol' => 'Supplier_Address', 'extraCol2' => 'Contact_Number'],
    'modes'       => ['table' => 'ref_mode_of_procurement',   'idCol' => 'ID',         'valueCol' => 'Mode_of_Procurement', 'softDelete' => true],
    // 'Initials' is what this person writes beside "***nothing follows***" on a
    // printed purchase order. Optional — the print dialog falls back to initials
    // derived from the name when it is blank.
    'po_users'    => ['table' => 'ref_po_users',              'idCol' => 'ID',         'valueCol' => 'Name',                'softDelete' => true, 'extraCol' => 'Initials', 'extraMax' => 10],
    // Units of issue (pc, box, liters...). Shared with the other HRMIS
    // modules that write tbl_purchase_order_items_details.
    'units'       => ['table' => 'ref_unit',                  'idCol' => 'ID',         'valueCol' => 'Unit_Name',           'softDelete' => true],
    // These are seeded in a deliberate order (0, 5, 10… then weekdays, then
    // Daily/Weekly/…; CGSO Warehouse/Job Site/Pick Up) — sort by insertion
    // order (ID), not alphabetically.
    'working_days'=> ['table' => 'ref_working_days',          'idCol' => 'ID',         'valueCol' => 'Value',               'softDelete' => true, 'orderById' => true],
    'payment_term'=> ['table' => 'ref_payment_term',          'idCol' => 'ID',         'valueCol' => 'Value',               'softDelete' => true, 'orderById' => true],
    // New table — see database/ref_place_of_delivery.sql. Not auto-run
    // against the live server; queries below degrade to an empty list
    // rather than erroring until that SQL has actually been applied.
    'place_of_delivery' => ['table' => 'ref_place_of_delivery', 'idCol' => 'ID',        'valueCol' => 'Value',               'softDelete' => true, 'orderById' => true],
    // New table — see database/ref_signatory.sql. Also not auto-run yet.
    // Shaped like suppliers (name + one extra column) since the print flow
    // needs both the signatory's name and their title together.
    'signatories' => ['table' => 'ref_signatory',              'idCol' => 'ID',         'valueCol' => 'Name',                'softDelete' => true, 'extraCol' => 'Title'],
    // New table — see database/tracking_dates_and_remarks.sql. Shared by the
    // Purchase Order and Competitive Bidding Remarks fields, which stay free
    // text: this only offers the common wordings so the common ones stay
    // identical, the same problem the supplier-name cleanup exists to fix.
    'remarks'     => ['table' => 'ref_remarks',                'idCol' => 'ID',         'valueCol' => 'Value',               'softDelete' => true, 'orderById' => true],
    // Property classification for Inventory & Property (e.g. "OFFICE
    // EQUIPMENT", "COMMUNICATION EQUIPMENT") — the same table
    // vb/Inventory/CGSO_Inventory.vb already reads (`ref_classification`,
    // 72 live rows), reused rather than duplicated.
    'inventory_classification' => ['table' => 'ref_classification', 'idCol' => 'ID', 'valueCol' => 'Classification', 'softDelete' => true],
];

// ref_po_users.Initials is varchar(10) and this server does not run in strict
// mode, so a longer value would be cut down to ten characters without a word
// of complaint. Categories whose extra column is narrow declare 'extraMax'
// and get told off instead.
function checkExtraLength(array $meta, $value): void {
    if (empty($meta['extraMax']) || $value === null) return;
    if (mb_strlen(trim((string)$value)) > $meta['extraMax']) {
        jsonResponse(false, 'That value is too long — ' . $meta['extraMax'] . ' characters at most.');
    }
}

function categoryOrFail($key) {
    if (!isset(CATEGORIES[$key])) jsonResponse(false, 'Unknown master data category.');
    return CATEGORIES[$key];
}

// Resolves the submitted parent for a category that has one, and refuses
// anything that is not a real row in the parent table. Returns null for
// categories with no parent, so callers can just test for null.
//
// A parent is required, deliberately: a section with division_ID = 0 does not
// show up under any division in this app or in the other HRMIS modules that
// read the same chain, so it is invisible rather than merely untidy.
function resolveParentId(PDO $db, array $meta, $submitted) {
    if (empty($meta['parentCol'])) return null;
    $parentMeta = CATEGORIES[$meta['parentCat']];
    $id = (int)$submitted;
    if ($id <= 0) {
        jsonResponse(false, 'Please choose the ' . rtrim($meta['parentCat'], 's') . ' this belongs to.');
    }
    $where = !empty($parentMeta['softDelete']) ? 'AND isDeleted = 0' : '';
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$parentMeta['table']} WHERE {$parentMeta['idCol']} = ? {$where}");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() === 0) jsonResponse(false, 'That parent no longer exists — reload the page.');
    return $id;
}

// Counts rows in any category that hangs off this one, so removing a
// department does not silently strand its divisions (and their sections).
function childRowCount(PDO $db, string $key, $id): array {
    $found = [];
    foreach (CATEGORIES as $childKey => $child) {
        if (($child['parentCat'] ?? null) !== $key) continue;
        $stmt = $db->prepare("SELECT COUNT(*) FROM {$child['table']} WHERE {$child['parentCol']} = ?");
        $stmt->execute([$id]);
        $n = (int)$stmt->fetchColumn();
        if ($n > 0) $found[$childKey] = $n;
    }
    return $found;
}

// Contact_Number is stored as a bigint, so a Philippine mobile number saved
// as "09171234567" comes back out of the database as 9171234567 — re-add
// the leading 0 for display when it looks like that's what happened
// (10 digits, doesn't already start with 0).
function formatContactNumber($value) {
    if ($value === null || $value === '') return '';
    $digits = preg_replace('/\D/', '', (string)$value);
    if (strlen($digits) === 10 && $digits[0] !== '0') $digits = '0' . $digits;
    return $digits;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = [];
    foreach (CATEGORIES as $key => $meta) {
        $where = $meta['softDelete'] ? 'WHERE isDeleted = 0' : '';
        $orderCol = !empty($meta['orderById']) ? $meta['idCol'] : $meta['valueCol'];
        $extraSelect = !empty($meta['extraCol']) ? ", {$meta['extraCol']} AS extra" : '';
        $extraSelect2 = !empty($meta['extraCol2']) ? ", {$meta['extraCol2']} AS extra2" : '';
        $parentSelect = !empty($meta['parentCol']) ? ", {$meta['parentCol']} AS parent_id" : '';
        try {
            $stmt = $db->query("SELECT {$meta['idCol']} AS id, {$meta['valueCol']} AS name{$extraSelect}{$extraSelect2}{$parentSelect} FROM {$meta['table']} {$where} ORDER BY {$orderCol} ASC");
            $rows = fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_ASSOC));
            if (!empty($meta['extraCol2']) && $meta['extraCol2'] === 'Contact_Number') {
                foreach ($rows as &$row) $row['extra2'] = formatContactNumber($row['extra2']);
                unset($row);
            }
            $result[$key] = $rows;
        } catch (PDOException $e) {
            // A category whose table hasn't been created yet (e.g. a new
            // .sql deliverable not run yet) shouldn't take down every other
            // category on this page — degrade to empty instead of a 500.
            $result[$key] = [];
        }
    }
    // Purpose isn't a curated picklist like the categories above — it's a
    // full free-text sentence, and there are ~11,000 distinct values on
    // 26,000+ POs, far too many to hand-manage as add/remove entries. So
    // this isn't a real "category": no table/idCol, read-only, sourced
    // directly from past POs — the 300 most-reused Purpose values, which is
    // what actually makes a useful autosuggest list out of free text.
    $stmt = $db->query(
        "SELECT Purpose AS name, COUNT(*) AS uses FROM tbl_purchase_order
         WHERE isDeleted = 0 AND Purpose <> ''
         GROUP BY Purpose ORDER BY uses DESC, Purpose ASC LIMIT 300"
    );
    $result['purposes'] = fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_ASSOC));
    jsonResponse(true, '', ['categories' => $result]);
}

// Everything past this point mutates a shared reference table — admin/super_admin only.
requireSystemToolsAdmin();
$data = getInput();

if ($method === 'POST') {
    $meta = categoryOrFail($data['category'] ?? '');
    $name = trim($data['name'] ?? '');
    if ($name === '') jsonResponse(false, 'Please enter a value.');

    $parentId = resolveParentId($db, $meta, $data['parent'] ?? '');

    // Uniqueness is scoped to the parent where there is one: two different
    // divisions may each legitimately have an "Administrative Section".
    $where = $meta['softDelete'] ? 'AND isDeleted = 0' : '';
    $scope = $parentId === null ? '' : " AND {$meta['parentCol']} = ?";
    $dupe = $db->prepare("SELECT COUNT(*) FROM {$meta['table']} WHERE {$meta['valueCol']} = ? {$where}{$scope}");
    $dupe->execute($parentId === null ? [$name] : [$name, $parentId]);
    if ((int)$dupe->fetchColumn() > 0) {
        jsonResponse(false, 'That value already exists.');
    }

    $cols = [$meta['valueCol']];
    $placeholders = ['?'];
    $params = [$name];
    // Categories with an extraCol (suppliers' address, signatories' title)
    // accept a second value alongside the name — optional, so categories
    // without one keep working exactly as before.
    if (!empty($meta['extraCol'])) {
        checkExtraLength($meta, $data['extra'] ?? '');
        $cols[] = $meta['extraCol'];
        $placeholders[] = '?';
        $params[] = trim($data['extra'] ?? '') ?: null;
    }
    if (!empty($meta['extraCol2'])) {
        $cols[] = $meta['extraCol2'];
        $placeholders[] = '?';
        $params[] = preg_replace('/\D/', '', trim($data['extra2'] ?? '')) ?: null;
    }
    if ($parentId !== null) {
        $cols[] = $meta['parentCol'];
        $placeholders[] = '?';
        $params[] = $parentId;
    }
    if ($meta['softDelete']) {
        $cols[] = 'isDeleted';
        $placeholders[] = '0';
    }
    $db->prepare('INSERT INTO ' . $meta['table'] . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')')
        ->execute($params);

    logAudit('CREATE_MASTER_DATA', 'master_data', "Added \"{$name}\" to {$meta['table']}");
    jsonResponse(true, 'Added.', ['id' => $db->lastInsertId()]);
}

if ($method === 'PUT') {
    $meta = categoryOrFail($data['category'] ?? '');
    $id = trim($data['id'] ?? '');
    $name = trim($data['name'] ?? '');
    if ($id === '') jsonResponse(false, 'Missing id.');
    if ($name === '') jsonResponse(false, 'Please enter a value.');

    $parentId = resolveParentId($db, $meta, $data['parent'] ?? '');

    $where = $meta['softDelete'] ? 'AND isDeleted = 0' : '';
    $scope = $parentId === null ? '' : " AND {$meta['parentCol']} = ?";
    $dupe = $db->prepare("SELECT COUNT(*) FROM {$meta['table']} WHERE {$meta['valueCol']} = ? AND {$meta['idCol']} <> ? {$where}{$scope}");
    $dupe->execute($parentId === null ? [$name, $id] : [$name, $id, $parentId]);
    if ((int)$dupe->fetchColumn() > 0) {
        jsonResponse(false, 'That value already exists.');
    }

    $sets = ["{$meta['valueCol']} = ?"];
    $params = [$name];
    if (!empty($meta['extraCol'])) {
        checkExtraLength($meta, $data['extra'] ?? '');
        $sets[] = "{$meta['extraCol']} = ?";
        $params[] = trim($data['extra'] ?? '') ?: null;
    }
    if (!empty($meta['extraCol2'])) {
        $sets[] = "{$meta['extraCol2']} = ?";
        $params[] = preg_replace('/\D/', '', trim($data['extra2'] ?? '')) ?: null;
    }
    if ($parentId !== null) {
        $sets[] = "{$meta['parentCol']} = ?";
        $params[] = $parentId;
    }
    $params[] = $id;
    $db->prepare('UPDATE ' . $meta['table'] . ' SET ' . implode(', ', $sets) . " WHERE {$meta['idCol']} = ?")
        ->execute($params);

    logAudit('UPDATE_MASTER_DATA', 'master_data', "Edited id {$id} (\"{$name}\") in {$meta['table']}");
    jsonResponse(true, 'Updated.');
}

if ($method === 'DELETE') {
    $meta = categoryOrFail($_GET['category'] ?? '');
    $id = trim($_GET['id'] ?? '');
    if ($id === '') jsonResponse(false, 'Missing id.');

    // ref_division/ref_section point at their parent by plain integer — there
    // are no foreign keys on this schema, so nothing at the database level
    // stops a delete from orphaning them. This is that check.
    $children = childRowCount($db, $_GET['category'], $id);
    if ($children) {
        $parts = [];
        foreach ($children as $childKey => $n) $parts[] = $n . ' ' . ($n === 1 ? rtrim($childKey, 's') : $childKey);
        jsonResponse(false, 'Still has ' . implode(' and ', $parts) . ' under it. Move or remove those first.');
    }

    if ($meta['softDelete']) {
        $db->prepare("UPDATE {$meta['table']} SET isDeleted = 1 WHERE {$meta['idCol']} = ?")->execute([$id]);
    } else {
        $db->prepare("DELETE FROM {$meta['table']} WHERE {$meta['idCol']} = ?")->execute([$id]);
    }
    logAudit('DELETE_MASTER_DATA', 'master_data', "Removed id {$id} from {$meta['table']}");
    jsonResponse(true, 'Removed.');
}

jsonResponse(false, 'Method not allowed.');
