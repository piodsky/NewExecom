<?php
// Full lookup + account management over the live HRMIS platform's shared
// user directory — this is settings.php's "User Management" tab. This app's
// actual login (api/auth.php) authenticates directly against this same live
// `users` table, so accounts created here can log into this app, the legacy
// VB.NET desktop app, and every other HRMIS module (PPMP, Inventory,
// Inspection, Warehousing, etc.) — this is genuinely shared production auth,
// not sandboxed to this module. Mirrors vb/Purchase Order/System User's
// Sys_User.vb / User.vb / Choose_User.vb flow: pick an existing HR personnel
// record (hrmis.tblpersonalinformation), then create/edit their login +
// role. Never creates new personnel records from scratch — that's HR's own
// onboarding process, out of scope here.
require_once '../config.php';
requireAuth();
// Settings (the only page that links here) is now super_admin-only — see
// requireRole() in settings.php — so this stays consistent with that
// rather than leaving a direct-URL loophole for an admin-tier account.
requireSystemUsersAdmin();
header('Content-Type: application/json');

$db = getHrmisDB();
$method = $_SERVER['REQUEST_METHOD'];
$actor = getCurrentUser();
$actorIsSuperAdmin = $actor['role'] === 'super_admin';

// An 'admin' actor (this app's own role, derived from role_id=1 or a role
// name containing "Administrator" — see api/auth.php) can't grant HRMIS
// roles at or above that same level; only a super_admin can. Mirrors
// Settings > User Management's own canManage() rule client-side, enforced
// here server-side since this writes to shared platform-wide auth.
function roleAssignable(PDO $db, int $roleId, bool $actorIsSuperAdmin): bool {
    if ($actorIsSuperAdmin) return true;
    $stmt = $db->prepare('SELECT role_id, role FROM purchase_request.table_role WHERE role_id = ?');
    $stmt->execute([$roleId]);
    $row = $stmt->fetch();
    if (!$row) return false;
    return (int)$row['role_id'] !== 1 && stripos($row['role'], 'Administrator') === false;
}

// Copies the chosen role's 19 permission flags from table_role onto a
// system_access_level row for this piid — table_role is the authoritative,
// live-maintained source (confirmed its flag values exactly match what the
// legacy Sys_User.vb's default_role() sub had hardcoded per role_id, but
// table_role also covers every role_id, not just the 7 the old hardcoded
// version handled). $piid's existing rows are cleared first so this is safe
// to call on both create and role changes on edit.
function applyRoleFlags(PDO $db, int $piid, int $roleId): void {
    // Schema-qualified: this connection is on hrmis, and the table is in
    // purchase_request — the same one api/access_levels.php reads. Left
    // unqualified it resolved to hrmis.system_access_level, which does not
    // exist, so creating a user or changing a role failed outright.
    //
    // Every identifier is back-quoted because `system` is a reserved word in
    // MySQL 8 and the bare name is a syntax error there. Quoting the whole
    // list rather than the one word keeps the next addition safe too.
    $db->prepare('DELETE FROM purchase_request.system_access_level WHERE piid = ?')->execute([$piid]);
    $db->prepare(
        "INSERT INTO purchase_request.system_access_level
            (`role_id`, `piid`, `system`, `system_user`, `system_access_level`, `maintainance_table`, `personel_administration`, `database_management`, `procurement`, `purchase_request`, `canvas_quotation`, `abstract`, `purchase_order`, `inspection_and_acceptance`, `inventory`, `segregation`, `ics`, `are`, `archive`, `warehousing`, `reports`)
         SELECT `role_id`, ?, `system`, `system_user`, `system_access_level`, `maintainance_table`, `personel_administration`, `database_management`, `procurement`, `purchase_request`, `canvas_quotation`, `abstract`, `purchase_order`, `inspection`, `inventory`, `segregation`, `ics`, `are`, `archive`, `warehousing`, `reports`
         FROM purchase_request.table_role WHERE `role_id` = ?"
    )->execute([$piid, $roleId]);
}

if ($method === 'GET') {
    // Employee picker — search hrmis.tblpersonalinformation directly
    // (mirrors Choose_User.vb), flags rows that already have a login so the
    // "Add User" picker can show that up front instead of failing on submit.
    if (($_GET['lookup'] ?? '') === 'employees') {
        // Choose_User.vb's grid: three independent search boxes (ID number,
        // first name, surname) over the same personnel list, and the same
        // columns it showed — ID_NUM, the concatenated name, residential
        // address and sex. 'q' is kept as a catch-all for anything that still
        // sends a single search term.
        //
        // The `ID_NUM > ' '` filter is Choose_User.vb's, not an invention:
        // 1,026 of the 9,424 personnel rows have no ID number and were never
        // offered for an account in the desktop app either.
        $where  = ["p.ID_NUM > ' '"];
        $params = [];
        $add = function (string $column, string $key) use (&$where, &$params) {
            $val = trim($_GET[$key] ?? '');
            if ($val === '') return;
            $where[]  = "{$column} LIKE ?";
            $params[] = '%' . $val . '%';
        };
        $add('p.ID_NUM',    'id_num');
        $add('p.FirstName', 'first');
        $add('p.SurName',   'surname');

        $q = trim($_GET['q'] ?? '');
        if ($q !== '') {
            $where[] = '(p.FirstName LIKE ? OR p.SurName LIKE ? OR p.ID_NUM LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }

        // Nothing typed at all would mean scanning 8,398 rows into a dropdown.
        if (count($where) === 1) jsonResponse(true, '', ['rows' => []]);

        $stmt = $db->prepare(
            "SELECT p.PIID AS piid, p.ID_NUM AS id_num,
                    CONCAT_WS(' ', p.FirstName, NULLIF(p.MiddleName,''), p.SurName, NULLIF(p.NameExt,'')) AS full_name,
                    p.ResidentialAdd AS address, p.Sex AS sex, p.CivilStatus AS civil_status,
                    p.BirthDate AS birth_date, p.BirthPlace AS birth_place,
                    p.Email AS email, p.CellNo AS cell_no,
                    (SELECT COUNT(*) FROM purchase_request.users u WHERE u.piid = p.PIID) AS already_registered
             FROM tblpersonalinformation p
             WHERE " . implode(' AND ', $where) . "
             ORDER BY p.SurName ASC, p.FirstName ASC LIMIT 50"
        );
        $stmt->execute($params);
        jsonResponse(true, '', ['rows' => fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_ASSOC))]);
    }

    // Role dropdown — filtered to what the current actor may assign.
    if (($_GET['lookup'] ?? '') === 'roles') {
        $stmt = $db->query('SELECT role_id, role FROM purchase_request.table_role ORDER BY role_id ASC');
        $rows = array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), function ($r) use ($actorIsSuperAdmin) {
            if ($actorIsSuperAdmin) return true;
            return (int)$r['role_id'] !== 1 && stripos($r['role'], 'Administrator') === false;
        }));
        jsonResponse(true, '', ['rows' => $rows]);
    }

    // Default: the directory list (unchanged from the read-only version).
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT u.user_id, u.user_name, u.piid, u.Department AS department, tr.role, u.role_id,
                   u.is_active,
                   CONCAT_WS(' ', p.FirstName, NULLIF(p.MiddleName, ''), p.SurName) AS full_name
            FROM purchase_request.users u
            LEFT JOIN purchase_request.table_role tr ON tr.role_id = u.role_id
            LEFT JOIN tblpersonalinformation p ON p.PIID = u.piid";
    $params = [];
    if ($search !== '') {
        $sql .= " WHERE u.user_name LIKE ? OR p.FirstName LIKE ? OR p.SurName LIKE ?";
        $like = '%' . $search . '%';
        $params = [$like, $like, $like];
    }
    $sql .= " ORDER BY u.user_name ASC LIMIT 500";
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(true, '', ['rows' => fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_ASSOC))]);
    } catch (PDOException $e) {
        jsonResponse(false, 'Unable to load HRMIS users.');
    }
}

$data = getInput();

if ($method === 'POST') {
    $piid     = (int)($data['piid'] ?? 0);
    $username = trim($data['username'] ?? '');
    $password = (string)($data['password'] ?? '');
    $department = trim($data['department'] ?? '');
    $roleId   = (int)($data['role_id'] ?? 0);
    // Defaults Active — a brand-new account with no reason yet to be
    // disabled — rather than trusting the client to always send this field.
    $isActive = array_key_exists('is_active', $data) ? (int)!!$data['is_active'] : 1;

    if (!$piid || $username === '' || $password === '' || !$roleId) {
        jsonResponse(false, 'Employee, username, password, and role are all required.');
    }
    if (!roleAssignable($db, $roleId, $actorIsSuperAdmin)) {
        jsonResponse(false, 'You are not allowed to assign that role.');
    }

    $dupe = $db->prepare('SELECT COUNT(*) FROM purchase_request.users WHERE piid = ?');
    $dupe->execute([$piid]);
    if ((int)$dupe->fetchColumn() > 0) {
        jsonResponse(false, 'This person already has an account.');
    }
    $dupeName = $db->prepare('SELECT COUNT(*) FROM purchase_request.users WHERE user_name = ?');
    $dupeName->execute([$username]);
    if ((int)$dupeName->fetchColumn() > 0) {
        jsonResponse(false, 'That username is already taken.');
    }

    try {
        $db->beginTransaction();
        $db->prepare('INSERT INTO purchase_request.users (piid, role_id, user_name, password, Department, is_active) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$piid, $roleId, $username, md5($password), $department, $isActive]);
        applyRoleFlags($db, $piid, $roleId);
        $db->commit();
        logAudit('CREATE_HRMIS_USER', 'settings', "Created HRMIS login \"{$username}\" (piid {$piid}, role_id {$roleId})");
        jsonResponse(true, 'Account created.');
    } catch (PDOException $e) {
        $db->rollBack();
        jsonResponse(false, 'Unable to create account.');
    }
}

if ($method === 'PUT') {
    $userId   = (int)($data['user_id'] ?? 0);
    $username = trim($data['username'] ?? '');
    $password = (string)($data['password'] ?? '');
    $department = trim($data['department'] ?? '');
    $roleId   = (int)($data['role_id'] ?? 0);
    $isActive = array_key_exists('is_active', $data) ? (int)!!$data['is_active'] : 1;

    if (!$userId || $username === '' || !$roleId) {
        jsonResponse(false, 'Username and role are required.');
    }
    if (!roleAssignable($db, $roleId, $actorIsSuperAdmin)) {
        jsonResponse(false, 'You are not allowed to assign that role.');
    }

    $existing = $db->prepare('SELECT piid, role_id FROM purchase_request.users WHERE user_id = ?');
    $existing->execute([$userId]);
    $row = $existing->fetch();
    if (!$row) jsonResponse(false, 'Account not found.');
    if (!$actorIsSuperAdmin && !roleAssignable($db, (int)$row['role_id'], false)) {
        // Also block edits to an account whose *current* role is admin+ —
        // an admin actor shouldn't be able to demote/reconfigure another admin.
        jsonResponse(false, 'You are not allowed to edit this account.');
    }

    $dupeName = $db->prepare('SELECT COUNT(*) FROM purchase_request.users WHERE user_name = ? AND user_id <> ?');
    $dupeName->execute([$username, $userId]);
    if ((int)$dupeName->fetchColumn() > 0) {
        jsonResponse(false, 'That username is already taken.');
    }

    try {
        $db->beginTransaction();
        if ($password !== '') {
            $db->prepare('UPDATE purchase_request.users SET user_name = ?, role_id = ?, Department = ?, password = ?, is_active = ? WHERE user_id = ?')
               ->execute([$username, $roleId, $department, md5($password), $isActive, $userId]);
        } else {
            $db->prepare('UPDATE purchase_request.users SET user_name = ?, role_id = ?, Department = ?, is_active = ? WHERE user_id = ?')
               ->execute([$username, $roleId, $department, $isActive, $userId]);
        }
        if ((int)$row['role_id'] !== $roleId) {
            applyRoleFlags($db, (int)$row['piid'], $roleId);
        }
        $db->commit();
        logAudit('UPDATE_HRMIS_USER', 'settings', "Updated HRMIS login \"{$username}\" (user_id {$userId})");
        jsonResponse(true, 'Account updated.');
    } catch (PDOException $e) {
        $db->rollBack();
        jsonResponse(false, 'Unable to update account.');
    }
}

if ($method === 'DELETE') {
    $userId = (int)($_GET['user_id'] ?? 0);
    if (!$userId) jsonResponse(false, 'Missing user_id.');

    $existing = $db->prepare('SELECT user_name, piid, role_id FROM purchase_request.users WHERE user_id = ?');
    $existing->execute([$userId]);
    $row = $existing->fetch();
    if (!$row) jsonResponse(false, 'Account not found.');
    if (!$actorIsSuperAdmin && !roleAssignable($db, (int)$row['role_id'], false)) {
        jsonResponse(false, 'You are not allowed to delete this account.');
    }

    try {
        $db->beginTransaction();
        $db->prepare('DELETE FROM purchase_request.users WHERE user_id = ?')->execute([$userId]);
        // Qualified for the same reason as the line above it and as
        // applyRoleFlags(): this connection is on hrmis, the table is not.
        $db->prepare('DELETE FROM purchase_request.system_access_level WHERE piid = ?')->execute([$row['piid']]);
        $db->commit();
        logAudit('DELETE_HRMIS_USER', 'settings', "Deleted HRMIS login \"{$row['user_name']}\" (user_id {$userId})");
        jsonResponse(true, 'Account deleted.');
    } catch (PDOException $e) {
        $db->rollBack();
        jsonResponse(false, 'Unable to delete account.');
    }
}

jsonResponse(false, 'Method not allowed.');
