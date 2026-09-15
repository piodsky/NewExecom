<?php
// System Access Level — the role matrix, backed by HRMIS's own
// system_access_level table. No new tables and no new columns: this reads and
// writes the exact rows the legacy VB.NET screen edits.
//
// Storage is per PERSON (one row per piid, seeded from a per-role template by
// Sys_User.vb's default_role() when an account is created). The screen is per
// ROLE, so it is a bulk editor: ticking a box writes that value to every row
// carrying that role_id. That is the same thing the legacy screen does, and it
// is why individuals can drift from their role until someone saves the role
// again.
//
// Resolution is deliberately NOT changed by this file — getEffectiveModuleAccess()
// still reads a person's own rows by piid, so per-person edits made in HRMIS keep
// working.
require_once '../config.php';
requireAccessLevelAdmin();
header('Content-Type: application/json');

// The only columns this screen may touch. Column names are concatenated into
// SQL (an identifier cannot be bound), so nothing outside this list is ever
// allowed near a query — the request only ever supplies a key from it.
//
// 'nav_group' mirrors sidebar.php's own section headers (Procurement /
// Inventory Management / System) — user-requested 2026-09-09, so this screen
// reads the same way the app's own navigation does instead of an unordered
// flat list. Order within each group matches the sidebar's link order too.
// Warehouse Management has no group of columns here: that module has no page
// and no HRMIS flag yet (see the "Soon" stub in sidebar.php) — nothing to
// list until it exists.
//
// The legacy-only ('hrmis') columns below are UNCHANGED and still writable
// via POST (nothing here narrows what this endpoint accepts) — they are just
// no longer sent to or rendered by this screen's own table (see the GET
// handler's filter), because not one of them affects this app. An admin who
// genuinely needs to edit Maintainance/ICS/ARE/etc. for the legacy VB.NET
// apps still has those apps themselves for that.
const AL_COLUMNS = [
    // ── Procurement ──────────────────────────────────────────────────────
    'purchase_order'   => ['label' => 'Purchase Orders',    'group' => 'app', 'nav_group' => 'Procurement'],
    'canvas_quotation' => ['label' => 'Competitive Bidding', 'group' => 'app', 'nav_group' => 'Procurement'],
    // Wired for real 2026-09-09: gates Reports, PO Insights and Competitive
    // Insights via canAccessReports()/requireReportsAdmin() in config.php —
    // used to be a bare admin-role check with no flag behind it at all.
    'reports'          => ['label' => 'Reports & Insights', 'group' => 'app', 'nav_group' => 'Procurement'],
    // ── Inventory Management ─────────────────────────────────────────────
    'inspection_and_acceptance' => ['label' => 'Inspection & Acceptance', 'group' => 'app', 'nav_group' => 'Inventory Management'],
    // Was miscategorised as legacy-only until 2026-09-09:
    // system_access_level.inventory is exactly what gates Inventory &
    // Property in this app (see MODULE_HRMIS_FLAG in config.php).
    'inventory'                 => ['label' => 'Inventory & Property',    'group' => 'app', 'nav_group' => 'Inventory Management'],
    // ── System ───────────────────────────────────────────────────────────
    'system_user'         => ['label' => 'System Users',             'group' => 'app', 'nav_group' => 'System'],
    'system_access_level' => ['label' => 'Access Levels',            'group' => 'app', 'nav_group' => 'System'],
    'system'               => ['label' => 'Master Data & Activity Log', 'group' => 'app', 'nav_group' => 'System'],
    // Read by the legacy HRMIS apps only — not shown on this screen.
    'maintainance_table'        => ['label' => 'Maintainance',            'group' => 'hrmis'],
    'personel_administration'   => ['label' => 'Personel Admin',          'group' => 'hrmis'],
    'database_management'       => ['label' => 'Database Management',    'group' => 'hrmis'],
    'procurement'               => ['label' => 'Procurement (legacy)',    'group' => 'hrmis'],
    'purchase_request'          => ['label' => 'Purchase Request',       'group' => 'hrmis'],
    'abstract'                  => ['label' => 'Abstract',                'group' => 'hrmis'],
    'segregation'               => ['label' => 'Segregation',             'group' => 'hrmis'],
    'ics'                       => ['label' => 'ICS',                     'group' => 'hrmis'],
    'are'                       => ['label' => 'ARE',                     'group' => 'hrmis'],
    'archive'                   => ['label' => 'Archive',                 'group' => 'hrmis'],
    'warehousing'               => ['label' => 'Warehousing',             'group' => 'hrmis'],
];

// Purchase Order action permissions — a SEPARATE table (tbl_po_permissions,
// database/po_permissions.sql), not HRMIS's system_access_level. Added
// 2026-09-09, user-requested ("the administrator can lock unlock delete edit
// the inside of the PO"). One row PER ROLE (role_id is that table's own
// PRIMARY KEY) rather than per-piid, so there is no "mixed" state and no
// "no rows exist for this role" refusal here — see poActionPermission() in
// config.php for the fallback a role with no row yet resolves to (identical
// to what api/purchase_orders.php hardcoded before this table existed).
const AL_PO_ACTION_COLUMNS = [
    'can_lock'   => ['label' => 'Lock / Unlock PO', 'nav_group' => 'Procurement'],
    'can_delete' => ['label' => 'Delete PO',        'nav_group' => 'Procurement'],
    'can_edit'   => ['label' => 'Edit PO',           'nav_group' => 'Procurement'],
];

// Render order across BOTH sources, interleaved so each nav_group's columns
// stay contiguous on screen (access_levels.php's renderHead() groups
// consecutive same-nav_group columns under one spanning header). The three
// PO actions sit right after Purchase Orders itself, ahead of the other
// Procurement modules — they are permissions ON purchase orders, not a
// separate module.
const AL_DISPLAY_ORDER = [
    'purchase_order', 'can_lock', 'can_delete', 'can_edit', 'canvas_quotation', 'reports',
    'inspection_and_acceptance', 'inventory',
    'system_user', 'system_access_level', 'system',
];

// Label + nav_group for a column key, whichever of the two sources it lives
// in — the one place that needs to know both exist.
function alColumnMeta(string $key): ?array {
    if (isset(AL_COLUMNS[$key]) && AL_COLUMNS[$key]['group'] === 'app') return AL_COLUMNS[$key];
    if (isset(AL_PO_ACTION_COLUMNS[$key])) return AL_PO_ACTION_COLUMNS[$key];
    return null;
}

$db     = getPoDB();
$method = $_SERVER['REQUEST_METHOD'];

// Columns this screen actually renders — the 'hrmis'-only ones are real
// columns and stay POST-writable (see AL_COLUMNS's own note), but there is
// no point computing on/off/mixed counts for a column nothing ever displays.
function alShownColumns(): array {
    return array_keys(array_filter(AL_COLUMNS, fn($m) => $m['group'] === 'app'));
}

// One row per role, with each flag reported as on / off / mixed.
//
// "mixed" is real and matters: a role's rows genuinely disagree once someone
// has been edited individually (2 of 10 roles are like this today). Collapsing
// that to a plain checkbox would quietly show one answer and then overwrite
// everyone with it on the next save.
function loadMatrix(PDO $db): array {
    $sel = [];
    foreach (alShownColumns() as $c) {
        $sel[] = "SUM(s.`{$c}` = 1) AS `on_{$c}`";
        $sel[] = "SUM(s.`{$c}` <> 1 OR s.`{$c}` IS NULL) AS `off_{$c}`";
    }
    $stmt = $db->query(
        "SELECT r.role_id, r.role,
                (SELECT COUNT(*) FROM system_access_level x WHERE x.role_id = r.role_id) AS rows_count,
                (SELECT COUNT(*) FROM users u WHERE u.role_id = r.role_id) AS users_count,
                " . implode(",\n                ", $sel) . "
         FROM table_role r
         LEFT JOIN system_access_level s ON s.role_id = r.role_id
         GROUP BY r.role_id, r.role
         ORDER BY r.role_id"
    );

    // tbl_po_permissions: one row per role at most, keyed by role_id — no
    // per-piid variance is possible, so these three never report 'mixed'.
    // A role with no row falls back to poActionPermission()'s default,
    // reproduced here (not called directly — this needs it for every role in
    // one pass, not one DB round trip per role).
    $poPermByRole = [];
    foreach ($db->query('SELECT role_id, can_lock, can_delete, can_edit FROM tbl_po_permissions') as $p) {
        $poPermByRole[(int)$p['role_id']] = $p;
    }
    $poActionDefault = function (int $roleId, string $col): bool {
        return $col === 'can_edit' ? true : in_array($roleId, [1, 2, 3], true);
    };

    $roles = [];
    foreach ($stmt as $r) {
        $roleId = (int)$r['role_id'];
        $flags = [];
        foreach (alShownColumns() as $c) {
            $on  = (int)$r["on_{$c}"];
            $off = (int)$r["off_{$c}"];
            $flags[$c] = ($on && $off) ? 'mixed' : ($on ? 'on' : 'off');
        }
        foreach (AL_PO_ACTION_COLUMNS as $c => $meta) {
            $row = $poPermByRole[$roleId] ?? null;
            $on  = $row !== null ? ((int)$row[$c] === 1) : $poActionDefault($roleId, $c);
            $flags[$c] = $on ? 'on' : 'off';
        }
        // People who hold this role but whose access rows are filed under a
        // DIFFERENT role_id — saving this role will not touch them. It happens
        // when someone is moved between roles: users.role_id changes, the
        // system_access_level rows keep the role they were created under.
        // Silent, and impossible to guess from the grid, so it is counted here
        // and shown as a warning on the row.
        $stray = $db->prepare(
            'SELECT COUNT(*) FROM users u
             WHERE u.role_id = ?
               AND EXISTS (SELECT 1 FROM system_access_level s WHERE s.piid = u.piid)
               AND NOT EXISTS (SELECT 1 FROM system_access_level s
                               WHERE s.piid = u.piid AND s.role_id = u.role_id)'
        );
        $stray->execute([$r['role_id']]);

        // People who hold this role but have NO system_access_level row at
        // all, under any role_id. Found live 2026-09-09 (56 accounts across 7
        // roles): a role can look fully granted here — every EXISTING row on
        // — while these accounts still see nothing, because there was never
        // a row for an UPDATE to reach. Saving any box for this role now
        // creates a row for them too (cloned from an existing peer), but
        // until someone saves, this is invisible without the count.
        $orphan = $db->prepare(
            'SELECT COUNT(*) FROM users u
             WHERE u.role_id = ?
               AND NOT EXISTS (SELECT 1 FROM system_access_level s WHERE s.piid = u.piid)'
        );
        $orphan->execute([$r['role_id']]);

        $roles[] = [
            'role_id'      => (int)$r['role_id'],
            'role'         => $r['role'],
            'rows_count'   => (int)$r['rows_count'],
            'users_count'  => (int)$r['users_count'],
            'stray_users'  => (int)$stray->fetchColumn(),
            'orphan_users' => (int)$orphan->fetchColumn(),
            'flags'        => $flags,
        ];
    }
    return $roles;
}

if ($method === 'GET') {
    // Only the columns that actually drive this app, in AL_DISPLAY_ORDER —
    // see AL_COLUMNS's note on why the legacy-only ones are gone entirely,
    // and AL_PO_ACTION_COLUMNS's note on the second source these merge in.
    $columns = [];
    foreach (AL_DISPLAY_ORDER as $key) {
        $meta = alColumnMeta($key);
        if (!$meta) continue;
        $columns[] = [
            'key' => $key, 'label' => $meta['label'], 'nav_group' => $meta['nav_group'],
            'source' => isset(AL_PO_ACTION_COLUMNS[$key]) ? 'po_permissions' : 'system_access_level',
        ];
    }
    jsonResponse(true, '', [
        'columns' => $columns,
        'roles'   => fixLegacyEncodingDeep(loadMatrix($db)),
        // Which app module each column drives, so the screen can say so out loud
        // rather than leaving an admin to guess what a tick actually does here.
        'wiring'  => [
            // Document Tracking has no HRMIS column of its own — it rides
            // this same flag (see MODULE_HRMIS_FLAG in config.php) — so
            // ticking Purchase Orders for a role grants both at once.
            'purchase_order'            => ['Purchase Orders', 'Document Tracking'],
            // OR'd together with a legacy purchase_order-derived rule rather
            // than the sole gate (see getEffectiveModuleAccess() in
            // config.php) — ticking this can only ever ADD Inspection access
            // for a role, never remove access the old rule already grants.
            'inspection_and_acceptance' => ['Inspection & Acceptance'],
            'reports'                   => ['Reports', 'PO Insights', 'Competitive Insights'],
            'inventory'                 => ['Inventory & Property'],
            'canvas_quotation'          => ['Competitive Bidding', 'Competitive Insights'],
            'system'                    => ['Master Data', 'Activity Log'],
            'system_user'               => ['System Users'],
            'system_access_level'       => ['Access Levels'],
            // Own table (tbl_po_permissions), not system_access_level — a
            // role with no row yet shows the default this replaced (role_id
            // 1/2/3 for Lock/Delete, everyone for Edit), not "off".
            'can_lock'                  => ['Lock/Unlock button on Purchase Orders'],
            'can_delete'                => ['Delete button on Purchase Orders'],
            'can_edit'                  => ['Edit button on Purchase Orders'],
        ],
    ]);
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?: [];
    $roleId = (int)($data['role_id'] ?? 0);
    $column = (string)($data['column'] ?? '');
    $value  = !empty($data['value']) ? 1 : 0;

    if (!$roleId) jsonResponse(false, 'A role is required.');
    $meta = alColumnMeta($column);
    if (!$meta)   jsonResponse(false, 'Unknown access flag.');

    $role = $db->prepare('SELECT role FROM table_role WHERE role_id = ?');
    $role->execute([$roleId]);
    $roleName = $role->fetchColumn();
    if ($roleName === false) jsonResponse(false, 'Role not found.');

    // tbl_po_permissions: one row per role, UPSERT — no "no rows exist"
    // refusal, because there is nothing to match against; a role with 0
    // members today can still have its PO permissions pre-configured for
    // whoever joins it later.
    if (isset(AL_PO_ACTION_COLUMNS[$column])) {
        // A role's first-ever tick here creates its row. The two columns NOT
        // being changed must be seeded with the SAME fallback
        // poActionPermission() already returns for an absent row — not the
        // table's own DEFAULT clause — or ticking just one box would reset
        // the others out from under a role relying on the fallback (role_id
        // 1/2/3 would silently lose Lock+Delete the moment someone touched
        // only their Edit box, since that column's SQL default is 0). The ON
        // DUPLICATE branch only ever writes the one column actually changed,
        // so an existing row's other two values are never touched by this.
        $seed = [
            'can_lock'   => in_array($roleId, [1, 2, 3], true) ? 1 : 0,
            'can_delete' => in_array($roleId, [1, 2, 3], true) ? 1 : 0,
            'can_edit'   => 1,
        ];
        $seed[$column] = $value;
        try {
            $stmt = $db->prepare(
                'INSERT INTO tbl_po_permissions (role_id, can_lock, can_delete, can_edit, Date_Updated, Updated_By)
                 VALUES (?, ?, ?, ?, NOW(), ?)
                 ON DUPLICATE KEY UPDATE `' . $column . '` = VALUES(`' . $column . '`),
                     Date_Updated = VALUES(Date_Updated), Updated_By = VALUES(Updated_By)'
            );
            $stmt->execute([
                $roleId, $seed['can_lock'], $seed['can_delete'], $seed['can_edit'],
                getCurrentUser()['username'] ?? null,
            ]);
            logAudit('UPDATE_ACCESS_LEVEL', 'access_levels',
                sprintf('%s %s for %s', $meta['label'], $value ? 'granted' : 'revoked', $roleName));
            jsonResponse(true, sprintf('%s %s for %s.', $meta['label'],
                $value ? 'enabled' : 'disabled', $roleName), ['affected' => 1]);
        } catch (Throwable $e) {
            error_log('PO permission save failed: ' . $e->getMessage());
            jsonResponse(false, 'Unable to save the change.');
        }
    }

    // system_access_level: bulk UPDATE across every piid row this role holds.
    // A role with no rows at all has nothing to update or clone from. Say so
    // plainly instead of reporting success on an UPDATE that matched nothing.
    $template = $db->prepare('SELECT * FROM system_access_level WHERE role_id = ? LIMIT 1');
    $template->execute([$roleId]);
    $templateRow = $template->fetch(PDO::FETCH_ASSOC);
    if ($templateRow === false) {
        jsonResponse(false, "No access rows exist for {$roleName}, so there is nothing to change. "
                          . 'A row is created in HRMIS when an account is given this role.');
    }

    try {
        $stmt = $db->prepare("UPDATE system_access_level SET `{$column}` = ? WHERE role_id = ?");
        $stmt->execute([$value, $roleId]);
        $affected = $stmt->rowCount();

        // Accounts that hold this role but have NO system_access_level row at
        // all — an UPDATE can't reach them because there is nothing to match.
        // This is exactly why a role can look "fully granted" on this screen
        // (every EXISTING row says on) while specific people still see
        // nothing: the row simply was never created for them (56 accounts
        // across 7 roles, found live 2026-09-09 — cherry/"Inspection User"
        // was the case that surfaced this). Fix: clone an existing row of the
        // same role as the new row's starting point (same shape the legacy
        // VB.NET default_role() seeding was supposed to produce), then apply
        // this save's change on top, so the new row ends up in sync with the
        // rest of the role rather than blank.
        $orphans = $db->prepare(
            'SELECT u.piid FROM users u
             WHERE u.role_id = ?
               AND NOT EXISTS (SELECT 1 FROM system_access_level s WHERE s.piid = u.piid)'
        );
        $orphans->execute([$roleId]);
        $orphanPiids = $orphans->fetchAll(PDO::FETCH_COLUMN);

        $created = 0;
        if ($orphanPiids) {
            $cloneCols = array_diff(array_keys($templateRow), ['AL_ID', 'piid']);
            $insert = $db->prepare(
                'INSERT INTO system_access_level (piid, ' . implode(', ', array_map(fn($c) => "`{$c}`", $cloneCols)) . ')
                 VALUES (?, ' . implode(', ', array_fill(0, count($cloneCols), '?')) . ')'
            );
            foreach ($orphanPiids as $piid) {
                $values = [$piid];
                foreach ($cloneCols as $c) {
                    $values[] = $c === $column ? $value : $templateRow[$c];
                }
                $insert->execute($values);
                $created++;
            }
        }

        $msg = sprintf('%s %s for %s (%d existing row(s) updated', $meta['label'],
            $value ? 'granted' : 'revoked', $roleName, $affected);
        $msg .= $created ? sprintf(', %d missing row(s) created)', $created) : ')';
        logAudit('UPDATE_ACCESS_LEVEL', 'access_levels', $msg);
        jsonResponse(true, sprintf('%s %s for %s.%s', $meta['label'],
            $value ? 'enabled' : 'disabled', $roleName,
            $created ? " Also created {$created} missing access row(s) for accounts that had none." : ''),
            ['affected' => $affected, 'created' => $created]);
    } catch (Throwable $e) {
        error_log('access level save failed: ' . $e->getMessage());
        jsonResponse(false, 'Unable to save the change.');
    }
}

jsonResponse(false, 'Unsupported request.');
