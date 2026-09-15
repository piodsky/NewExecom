<?php
// Errors are logged, never printed. A stack trace or PDO message rendered on a
// live page discloses file paths, queries and connection details; on an /api/
// route it also corrupts the JSON body. Set APP_DEBUG=1 in .env to see them
// on screen while developing — applied after the .env loader below.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Unique session name so this app's login doesn't collide with sibling
// projects under the same SystemsMISPYO document root (they'd otherwise
// all share one PHPSESSID cookie at path "/").
session_name('pbs_session');
if (session_status() === PHP_SESSION_NONE) {
    // httponly keeps the session cookie out of JavaScript's reach, so an XSS
    // bug can't lift a logged-in session. samesite=Lax stops another site
    // silently issuing authenticated requests here. secure is set only when
    // actually on HTTPS — forcing it on plain HTTP would break login outright.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || ($_SERVER['SERVER_PORT'] ?? null) == 443,
    ]);
    session_start();
}

// Load .env
(function () {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
})();

// Local development only. Never leave APP_DEBUG=1 on a deployed instance —
// it puts PHP errors and database connection details back on screen.
function appDebug(): bool {
    return in_array(strtolower(trim($_ENV['APP_DEBUG'] ?? '')), ['1', 'true', 'yes', 'on'], true);
}
if (appDebug() && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
    ini_set('display_errors', '1');
}

// Purchase Order module's own connection — the live HRMIS procurement
// database. Kept separate from DB_* above (which stays on this app's own
// database. This is now the app's only write target.
define('PO_DB_HOST',    $_ENV['PO_DB_HOST'] ?? null);
define('PO_DB_NAME',    $_ENV['PO_DB_NAME'] ?? null);
define('PO_DB_USER',    $_ENV['PO_DB_USER'] ?? null);
define('PO_DB_PASS',    $_ENV['PO_DB_PASS'] ?? null);
define('PO_DB_CHARSET', $_ENV['PO_DB_CHARSET'] ?? 'utf8mb4');

// HRMIS platform's shared personnel database (same server as PO_DB_* above,
// different schema) — read-only lookups only (Settings > HRMIS Users). Kept
// as its own connection/constants rather than reused from PO_DB_* so it's
// never accidentally pointed anywhere else, even though cross-schema SELECTs
// against `hrmis.*` already work fine over the PO_DB_* connection too (same
// MySQL server) — this just makes the intent explicit in code.
define('HRMIS_DB_HOST',    $_ENV['HRMIS_DB_HOST'] ?? null);
define('HRMIS_DB_NAME',    $_ENV['HRMIS_DB_NAME'] ?? null);
define('HRMIS_DB_USER',    $_ENV['HRMIS_DB_USER'] ?? null);
define('HRMIS_DB_PASS',    $_ENV['HRMIS_DB_PASS'] ?? null);
define('HRMIS_DB_CHARSET', $_ENV['HRMIS_DB_CHARSET'] ?? 'utf8mb4');

// Document Tracking System (TAS). A third database, owned by another team:
// read-only here, and never written to.
define('TASDB_DB_HOST',    $_ENV['TASDB_DB_HOST'] ?? null);
define('TASDB_DB_NAME',    $_ENV['TASDB_DB_NAME'] ?? null);
define('TASDB_DB_USER',    $_ENV['TASDB_DB_USER'] ?? null);
define('TASDB_DB_PASS',    $_ENV['TASDB_DB_PASS'] ?? null);
define('TASDB_DB_CHARSET', $_ENV['TASDB_DB_CHARSET'] ?? 'utf8mb4');

// Pure-PHP QR encoder used by the Purchase Order printout's verification code.
require_once __DIR__ . '/lib/qrcode.php';
// Pure-PHP Code 128 encoder for the PO-number barcode on the printouts.
require_once __DIR__ . '/lib/barcode128.php';

define('APP_NAME',      $_ENV['APP_NAME']      ?? 'Pyo Base System');
define('APP_FULL_NAME', $_ENV['APP_FULL_NAME']  ?? 'Pyo Base System');

// A connection failure must not tell an anonymous visitor which host, schema
// or account we tried — on this network that is a map of the internal servers.
// The specifics go to the error log; they only reach the screen under APP_DEBUG.
function dbConnectionFailure(string $what, string $detail): void {
    error_log("DB connection failed ({$what}): {$detail}");
    if (isApiRequest()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }
    $extra = appDebug()
        ? '<p><small>' . htmlspecialchars($what . ' — ' . $detail) . '</small></p>'
          . '<p><small>If this is a fresh install, create the database and run '
          . '<code>database/schema.sql</code> against it, then check <code>.env</code>.</small></p>'
        : '<p><small>Details have been recorded in the server error log.</small></p>';
    die('<div style="font-family:sans-serif;padding:40px;color:#dc2626;">'
        . '<h2>Database Error</h2>'
        . '<p>The application could not reach its database. Please contact your administrator.</p>'
        . $extra . '</div>');
}


// Separate connection to the live HRMIS purchase_request database — used only
// by the Purchase Order module — the app's primary database.
function getPoDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . PO_DB_HOST . ";dbname=" . PO_DB_NAME . ";charset=" . PO_DB_CHARSET;
            $pdo = new PDO($dsn, PO_DB_USER, PO_DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            dbConnectionFailure(PO_DB_NAME . ' @ ' . PO_DB_HOST, $e->getMessage());
        }
    }
    return $pdo;
}

// Separate connection to the live HRMIS platform's `hrmis` schema (personnel
// data) — read-only lookups only (Settings > HRMIS Users). Never mix this
// or write to it from this app.
function getHrmisDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . HRMIS_DB_HOST . ";dbname=" . HRMIS_DB_NAME . ";charset=" . HRMIS_DB_CHARSET;
            $pdo = new PDO($dsn, HRMIS_DB_USER, HRMIS_DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            dbConnectionFailure(HRMIS_DB_NAME . ' @ ' . HRMIS_DB_HOST, $e->getMessage());
        }
    }
    return $pdo;
}

// Document Tracking System (TAS) — a third database owned by another team.
// READ-ONLY from this app: it is the system of record for where a physical
// document currently sits, and nothing here ever writes to it.
//
// Unlike getPoDB()/getHrmisDB(), a failure here THROWS rather than calling
// dbConnectionFailure(). That function ends the request, which would jump
// straight past every caller's try/catch — so an unreachable or unconfigured
// DTS would take a Purchase Order screen down with it, and report itself as
// "Database connection failed" even though this app's own database was fine.
// DTS is a nice-to-have: every caller already degrades gracefully, and they can
// only do that if the failure arrives as an exception they can catch.
function dtsIsConfigured(): bool {
    return (string)TASDB_DB_HOST !== '' && (string)TASDB_DB_NAME !== '';
}

// DTS stores "no date yet" as MySQL's zero date rather than NULL, so an
// unreleased document has DateOut = '0000-00-00 00:00:00'. Lives here rather
// than in api/document_tracking.php because the reports API needs the same
// test to work out where a document currently sits.
function dtsIsBlankDate($v): bool {
    $v = (string)$v;
    return $v === '' || strpos($v, '0000') === 0;
}

function getDtsDB() {
    static $pdo = null;
    if ($pdo === null) {
        if (!dtsIsConfigured()) {
            throw new RuntimeException('DTS is not configured: TASDB_DB_* missing from .env');
        }
        try {
            $dsn = "mysql:host=" . TASDB_DB_HOST . ";dbname=" . TASDB_DB_NAME . ";charset=" . TASDB_DB_CHARSET;
            $pdo = new PDO($dsn, TASDB_DB_USER, TASDB_DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // A dead host must fail fast: without this the page hangs on the
                // OS-level TCP timeout while someone waits for a popup.
                PDO::ATTR_TIMEOUT            => 5,
            ]);
        } catch (PDOException $e) {
            error_log('DTS connection failed (' . TASDB_DB_NAME . ' @ ' . TASDB_DB_HOST . '): ' . $e->getMessage());
            throw new RuntimeException('DTS is unreachable', 0, $e);
        }
    }
    return $pdo;
}

// Derives a document's DTS barcode from a purchase order's PR number.
//
// The two systems record the same PR differently: this app stores it as one
// 8-digit string ("20261605"), while DTS splits it across tas_prno.Year_PR
// ("2026") and tas_prno.PRNo ("1605", unpadded). Splitting ours the same way
// resolves the barcode — measured at 11 of 12 recent POs, and where the PO
// already carried a Barcode_Number it agreed with the derived one.
//
// Returns null rather than guessing when the PR number is not the 8-digit
// shape, or when DTS has no record of it.
function dtsBarcodeForPrNumber($prNumber): ?string {
    $pr = trim((string)$prNumber);
    if (!preg_match('/^[0-9]{8}$/', $pr)) return null;
    $year = substr($pr, 0, 4);
    $no   = ltrim(substr($pr, 4), '0');
    if ($no === '') return null;
    try {
        $stmt = getDtsDB()->prepare(
            'SELECT BarCodeNo FROM tas_prno WHERE Year_PR = ? AND TRIM(PRNo) = ? LIMIT 1'
        );
        $stmt->execute([$year, $no]);
        $code = $stmt->fetchColumn();
        return ($code !== false && trim((string)$code) !== '') ? trim((string)$code) : null;
    } catch (Throwable $e) {
        // DTS being unreachable must never break a purchase order screen.
        error_log("DTS barcode lookup failed for PR {$pr}: " . $e->getMessage());
        return null;
    }
}

// Some free-text columns in the live HRMIS DB (e.g. tbl_purchase_order.End_User)
// have raw Windows-1252 bytes sitting directly in a utf8mb4 column — likely
// from the old VB.NET app inserting via a Windows-1252 connection at some
// point without conversion (confirmed: "BEREÑA" is stored as the single raw
// byte 0xD1 for "Ñ" instead of UTF-8's 2-byte 0xC3 0x91). That byte is not
// valid UTF-8, so it renders as U+FFFD (�). Already-valid UTF-8 values are
// left untouched — this only repairs the ones that fail validation, so it's
// safe to run over columns that are a mix of correct and legacy data.
function fixLegacyEncoding($value) {
    if (!is_string($value) || $value === '') return $value;
    if (mb_check_encoding($value, 'UTF-8')) return $value;
    $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    return $converted !== false ? $converted : $value;
}

function fixLegacyEncodingDeep($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = fixLegacyEncodingDeep($value);
        }
        return $data;
    }
    return fixLegacyEncoding($data);
}

function isLoggedIn() {
    return isset($_SESSION['pbs_user_id']) && !empty($_SESSION['pbs_user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'          => $_SESSION['pbs_user_id'],
        'username'    => $_SESSION['pbs_username'],
        'full_name'   => $_SESSION['pbs_full_name'],
        'avatar_path' => $_SESSION['pbs_avatar_path'] ?? null,
        'role'        => $_SESSION['pbs_role'],
        'role_id'     => $_SESSION['pbs_role_id'] ?? null,
        'last_login'  => $_SESSION['pbs_last_login'] ?? null,
    ];
}

function requireAuth() {
    if (!isLoggedIn()) {
        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
            exit;
        }
        $base = getBasePath();
        header("Location: {$base}/login.php?expired=1");
        exit;
    }
}

function requireRole($roles) {
    requireAuth();
    $user  = getCurrentUser();
    $roles = (array)$roles;
    if (!in_array($user['role'], $roles, true)) {
        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        header('Location: ' . getBasePath() . '/dashboard.php?error=access_denied');
        exit;
    }
}

// Role rank — higher can manage lower. Keep in sync with the `role` ENUM in the users table.
const ROLE_RANK = ['user' => 1, 'admin' => 2, 'super_admin' => 3];

function roleLabel($role) {
    return match ($role) {
        'super_admin' => 'Super Administrator',
        'admin'       => 'Admin',
        'user'        => 'User',
        default       => ucfirst($role),
    };
}

// The real HRMIS role name (e.g. "Inspection User", "Procurement
// Administrator") for DISPLAY only — never for permission decisions, which
// stay on the collapsed 3-tier role everywhere else (see hrmisRoleId()'s note
// on why every "...Administrator" role collapses to 'admin' at login). Access
// Levels and System Users already show a person their real role name; the
// sidebar/topbar/dashboard used to fall back to the generic collapsed label
// ("User") instead, which read as wrong even though access itself was fine.
function hrmisRoleName(?int $roleId): ?string {
    static $cache = [];
    if (!$roleId) return null;
    if (array_key_exists($roleId, $cache)) return $cache[$roleId];
    try {
        $stmt = getPoDB()->prepare('SELECT role FROM table_role WHERE role_id = ?');
        $stmt->execute([$roleId]);
        $name = $stmt->fetchColumn();
        return $cache[$roleId] = ($name !== false && $name !== '') ? $name : null;
    } catch (Throwable $e) {
        error_log('hrmisRoleName lookup failed: ' . $e->getMessage());
        return $cache[$roleId] = null;
    }
}

// Collapses a live HRMIS table_role row into this app's 3-tier role
// (user/admin/super_admin) — role_id=1 is Super Administrator, any role
// name containing "Administrator" (Administrator, Procurement
// Administrator, Inventory Administrator, PPMP Administrator, Warehousing
// Administrator, …) maps to 'admin', everything else is a plain 'user'.
// Single source of truth for this mapping — api/auth.php (session role at
// login) and api/hrmis_users.php (the User Management tab) both need it to
// agree, or an account could get a different effective role at login than
// what Settings shows for it.
function deriveAppRole($roleId, $roleName) {
    if ((int)$roleId === 1) return 'super_admin';
    return stripos((string)$roleName, 'Administrator') !== false ? 'admin' : 'user';
}

// Can $actorRole manage (edit/delete) an account currently holding $targetRole?
function canManageRole($actorRole, $targetRole) {
    if ($actorRole === 'super_admin') return true;
    if ($actorRole === 'admin')       return (ROLE_RANK[$targetRole] ?? 0) < ROLE_RANK['admin'];
    return false;
}

// Can $actorRole assign $newRole to an account (create or role change)?
function canAssignRole($actorRole, $newRole) {
    if ($actorRole === 'super_admin') return true;
    if ($actorRole === 'admin')       return $newRole === 'user';
    return false;
}

// ── Per-role / per-account sidebar module access ──
// Add an entry here for every new page you want to be individually
// toggleable from Access Levels. Dashboard is intentionally excluded — it's
// the login/redirect landing page and must always stay reachable.
//
// Reports and the System section used to live here too. They are gated
// elsewhere now: the System screens each read their own HRMIS flag through
// hrmisSystemFlag() further down this file, and Reports is still the fixed
// admin+super_admin rule in reports.php. Listing a key here whose real gate
// runs first would let a super_admin "turn it back on" for a role from the
// Access Levels screen with no effect at all, which reads as a bug rather
// than a feature — so a key only belongs here while requireModule() is the
// only thing standing in front of the page.
const ACCESS_MODULES = [
    'purchase_orders' => 'Purchase Orders',
    'competitive_bidding' => 'Competitive Bidding',
    'inspection'      => 'Inspection & Acceptance',
    'document_tracking' => 'Document Tracking',
    'inventory'       => 'Inventory & Property',
];

// Who may use the Purchase Order module now comes from HRMIS's own per-user
// switch, system_access_level.purchase_order — the same flag the legacy VB.NET
// app and every other HRMIS module honour. The local role_module_access /
// user_module_access tables went away with pyobasesystem_db, and the Access
// Levels screen that edited them went with them: there is now exactly one
// source of truth for procurement access, managed in HRMIS.
//
// The old per-account override layer was already broken anyway — it keyed on
// local user ids while sessions carry HRMIS ids, so overrides only ever
// matched by coincidence.
// Which system_access_level column governs each module.
//
// This is also the whitelist: hrmisModuleFlag() refuses any column not named
// here, because the column name is concatenated into the SQL (it cannot be a
// bound parameter) and so must never come from anywhere a request can reach.
//
// 'inspection' is NOT listed below on purpose, even though it now reads
// inspection_and_acceptance too — it needs the OR-with-the-old-rule logic in
// getEffectiveModuleAccess() (see the comment there), not a straight 1:1
// column lookup, so it stays out of this simple mapping.
//
// 'reports' still isn't mapped: Reports/PO Insights/Competitive Insights are
// on the fixed admin+super_admin rule in reports.php et al., not this table
// at all yet — see project_po_access_levels memory for the live-data numbers
// behind that undecided call.
// Every system_access_level column this app is allowed to read. This is the
// whitelist hrmisModuleFlag() checks against: a column name cannot be a bound
// parameter, so it is concatenated into SQL, so it must never come from
// anywhere a request can reach.
const HRMIS_FLAG_COLUMNS = [
    'purchase_order',
    'inspection_and_acceptance',
    'inventory',
    'reports',
    'canvas_quotation',
    // The System section of the sidebar, one column per screen.
    'system',              // Master Data + Activity Log
    'system_user',         // System Users
    'system_access_level', // Access Levels
];

const MODULE_HRMIS_FLAG = [
    'purchase_orders'   => 'purchase_order',
    // Document Tracking is a read-only view over purchase orders and has no
    // column of its own in HRMIS, so it rides the same switch.
    'document_tracking' => 'purchase_order',
    // Competitive Bidding used to ride purchase_order too (system_access_level
    // has no column literally named for it). Switched 2026-09-09 to
    // canvas_quotation — HRMIS's flag for Canvass/Quotation, one of the two
    // real BAC documents this module covers (the other, abstract, is always
    // identical to it in live data, so one column is enough). User-requested
    // after being shown the tradeoff: ~126 people had bidding access via
    // purchase_order; only the 42 HRMIS already flags with canvas_quotation
    // keep it now — re-grant anyone missed from the Access Levels screen.
    'competitive_bidding' => 'canvas_quotation',
    // Unlike the two above, HRMIS carries a real column for this one —
    // `system_access_level.inventory` — held by a distinct set of people
    // (GSO/property-custodian staff, not simply everyone with purchase_order),
    // confirmed against live data before wiring it in. No workaround needed.
    'inventory'          => 'inventory',
];

// True when HRMIS grants this user the named access flag.
//
// A person (piid) can hold more than one system_access_level row — 8 piids do,
// one of them 46 rows — so MAX() is used: any row granting it wins. NULL means
// no access row exists at all, which must read as "no", not "yes": a permission
// check has to fail closed.
function hrmisModuleFlag($userId, string $column): bool {
    static $cache = [];
    // Whitelist check first — never interpolate an unvetted column name.
    if (!in_array($column, HRMIS_FLAG_COLUMNS, true)) {
        error_log("hrmisModuleFlag(): refused unmapped column '{$column}'");
        return false;
    }
    $userId = (int)$userId;
    if (!$userId) return false;
    $key = $userId . '|' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];

    try {
        $stmt = getPoDB()->prepare(
            "SELECT MAX(s.`{$column}`)
             FROM users u
             JOIN system_access_level s ON s.piid = u.piid
             WHERE u.user_id = ?"
        );
        $stmt->execute([$userId]);
        return $cache[$key] = ((int)$stmt->fetchColumn() === 1);
    } catch (Throwable $e) {
        error_log("access check failed for {$column}: " . $e->getMessage());
        return $cache[$key] = false;
    }
}

// Kept as a named shorthand for the module every other gate keys off.
function hrmisHasPurchaseOrderAccess($userId): bool {
    return hrmisModuleFlag($userId, MODULE_HRMIS_FLAG['purchase_orders']);
}

// A user's HRMIS role_id, or 0 if unknown.
//
// The session only carries the collapsed 3-tier role ('user'/'admin'/
// 'super_admin'), and EVERY HRMIS role whose name contains "Administrator"
// collapses to 'admin' at login (see api/auth.php) — so a Procurement
// Administrator is indistinguishable from a real Administrator there. Any
// rule that must tell those apart has to read role_id from HRMIS directly,
// which is what this does. Cached per request: it is called once per module
// gate and the answer cannot change mid-request.
function hrmisRoleId($userId): int {
    static $cache = [];
    $userId = (int)$userId;
    if (!$userId) return 0;
    if (array_key_exists($userId, $cache)) return $cache[$userId];
    try {
        $stmt = getPoDB()->prepare('SELECT role_id FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $cache[$userId] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('role_id lookup failed: ' . $e->getMessage());
        return $cache[$userId] = 0;
    }
}

// Per-role Purchase Order action permissions (Lock/Unlock, Delete, Edit) —
// database/po_permissions.sql, `tbl_po_permissions`, one row per role_id, not
// HRMIS's shared system_access_level. User-requested 2026-09-09: these three
// used to be role_id IN (1,2,3) hardcoded in api/purchase_orders.php (Edit
// had no gate at all), now editable from Access Levels like everything else.
//
// The whitelist: a column name is concatenated into SQL below, so nothing
// outside this list may ever reach that query.
const PO_PERMISSION_COLUMNS = ['can_lock', 'can_delete', 'can_edit'];

// True when this role may perform $action on a purchase order.
//
// Super Administrator (role_id 1) always passes, same rule every other
// admin-tier gate in this app uses — a bad row in this table must never be
// able to lock the system's own administrators out of their own purchase
// orders. Below that: a role with NO row here yet is not "denied" — it falls
// back to the exact rule this replaced (role_id 1/2/3 get Lock+Delete,
// everyone gets Edit), so an untouched role behaves identically to before
// this table existed. Once Access Levels writes a real row for a role, that
// row wins outright.
function poActionPermission(int $roleId, string $action): bool {
    static $cache = [];
    if ($roleId === 1) return true;
    if (!in_array($action, PO_PERMISSION_COLUMNS, true)) return false;
    $key = $roleId . '|' . $action;
    if (array_key_exists($key, $cache)) return $cache[$key];

    $default = $action === 'can_edit' ? true : in_array($roleId, [1, 2, 3], true);
    try {
        $stmt = getPoDB()->prepare("SELECT `{$action}` FROM tbl_po_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        $val = $stmt->fetchColumn();
        return $cache[$key] = ($val === false) ? $default : ((int)$val === 1);
    } catch (Throwable $e) {
        error_log("poActionPermission({$action}) failed: " . $e->getMessage());
        return $cache[$key] = $default;
    }
}
function canLockPurchaseOrder(int $roleId): bool   { return poActionPermission($roleId, 'can_lock'); }
function canDeletePurchaseOrder(int $roleId): bool { return poActionPermission($roleId, 'can_delete'); }
function canEditPurchaseOrder(int $roleId): bool   { return poActionPermission($roleId, 'can_edit'); }

// HRMIS roles that run the procurement desk: they raise, edit and print
// purchase orders, but do not carry out the inspection of what arrives.
// Keeping those two jobs in different hands is the point of the separate
// Inspection & Acceptance stage, so the module is hidden from them.
const INSPECTION_HIDDEN_ROLE_IDS = [
    3, // Procurement Administrator
    4, // Procurement User
];

// ── The System section ───────────────────────────────────────────────────────
// System Users, Access Levels, Master Data and Activity Log are each governed
// by their own column in HRMIS's system_access_level — the same boxes the
// Access Levels matrix ticks — so unticking one genuinely hides that screen.
//
// Two deliberate limits on that:
//
// The flag alone is not enough. system_user and system_access_level are each
// set to 1 for two ordinary accounts (a Procurement User and a Warehousing
// User) who have no business creating logins or rewriting the role matrix, so
// the admin tier is still required on top of the flag.
//
// A Super Administrator is never gated. 6 of the 8 hold no system_access_level
// row that grants any of this (5 have no rows at all, 1 has every flag at 0) —
// honouring the flag for them would lock the system's own administrators out
// of the very screen that fixes it. Same rule getEffectiveModuleAccess()
// already applies to the module flags.
function hrmisSystemFlag($user, string $column): bool {
    $role = $user['role'] ?? '';
    if ($role === 'super_admin') return true;
    if ($role !== 'admin')       return false;
    return hrmisModuleFlag($user['id'] ?? null, $column);
}

function canManageSystemUsers($user): bool {
    return hrmisSystemFlag($user, 'system_user');
}

// This screen rewrites what every account in every HRMIS module can reach, so
// it is the one most worth being careful with — but the tick is now what says
// who gets in, which is the whole point of the matrix.
function canManageAccessLevels($user): bool {
    return hrmisSystemFlag($user, 'system_access_level');
}

// Master Data + Activity Log: the maintenance screens, HRMIS's own 'system'.
function canManageSystemTools($user): bool {
    return hrmisSystemFlag($user, 'system');
}

// Reports / PO Insights / Competitive Insights. Used to be a bare
// role-in-(admin,super_admin) check with no HRMIS flag behind it at all —
// every admin saw it, no exceptions, not editable from Access Levels. Wired
// 2026-09-09, user-requested, same hrmisSystemFlag() shape as the three
// above: super_admin always in, admin needs system_access_level.reports=1,
// plain user is never eligible regardless of the flag.
function canAccessReports($user): bool {
    return hrmisSystemFlag($user, 'reports');
}

// Same shape as requireModule(): JSON for an API call, redirect for a page.
function requireSystemScreen(callable $check): void {
    requireAuth();
    if ($check(getCurrentUser())) return;
    if (isApiRequest()) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }
    header('Location: ' . getBasePath() . '/dashboard.php?error=access_denied');
    exit;
}

function requireSystemUsersAdmin(): void { requireSystemScreen('canManageSystemUsers');  }
function requireAccessLevelAdmin(): void { requireSystemScreen('canManageAccessLevels'); }
function requireSystemToolsAdmin(): void { requireSystemScreen('canManageSystemTools');  }
function requireReportsAdmin(): void      { requireSystemScreen('canAccessReports');     }
function getEffectiveModuleAccess($userId, $role) {
    $access = [];
    foreach (ACCESS_MODULES as $key => $label) {
        $access[$key] = true;
    }
    // Every mapped module resolves straight from its HRMIS flag. A Super
    // Administrator is never gated, so a bad flag in HRMIS can't leave the
    // system with nobody able to administer it.
    foreach (MODULE_HRMIS_FLAG as $moduleKey => $column) {
        $access[$moduleKey] = ($role === 'super_admin')
            ? true
            : hrmisModuleFlag($userId, $column);
    }
    // Inspection & Acceptance: the purchase_order-derived path (procurement
    // roles, minus the ones deliberately kept out), OR HRMIS's own
    // system_access_level.inspection_and_acceptance column directly.
    //
    // The column used to be unusable — set to 1 for the exact same people
    // purchase_order already covered, so reading it would have been a no-op
    // at best. Re-checked against live data 2026-09-09: it no longer is.
    // Inventory Administrator / Inventory User / Warehousing User — real
    // roles, confirmed on System Roles — carry inspection_and_acceptance=1
    // with purchase_order=0: warehouse/receiving staff who inspect
    // deliveries but never create a PO, exactly the access this app was
    // otherwise refusing them. The Access Levels matrix already showed their
    // Inspection box ticked; the app was just not honouring it. OR'd in
    // rather than replacing the old rule, so nobody who has access today
    // through purchase_order loses it — this can only ever grant more,
    // matching what is already explicitly set in HRMIS, never less.
    //
    // Checked by role_id, not the collapsed $role, for the reason spelled out
    // on hrmisRoleId().
    $access['inspection'] = ($access['purchase_orders']
            && ($role === 'super_admin'
                || !in_array(hrmisRoleId($userId), INSPECTION_HIDDEN_ROLE_IDS, true)))
        || ($role === 'super_admin')
        || hrmisModuleFlag($userId, 'inspection_and_acceptance');
    return $access;
}
function canAccessModule($user, $moduleKey) {
    if (!isset(ACCESS_MODULES[$moduleKey])) return true;
    $access = getEffectiveModuleAccess($user['id'] ?? null, $user['role']);
    return $access[$moduleKey] ?? true;
}

function requireModule($moduleKey) {
    requireAuth();
    $user = getCurrentUser();
    if (!canAccessModule($user, $moduleKey)) {
        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        header('Location: ' . getBasePath() . '/dashboard.php?error=access_denied');
        exit;
    }
}

// Where a purchase order sits in its lifecycle, derived from the same date
// columns the dashboard donut counts, so the two can never disagree. The
// stages are mutually exclusive and checked newest-first.
function poStatusLabel(array $row): string {
    $has = fn($k) => isset($row[$k]) && trim((string)$row[$k]) !== '' && strpos((string)$row[$k], '0000-00-00') !== 0;
    if ($has('Date_COA_Received'))      return 'COA received';
    if ($has('Date_Received_Supplier')) return 'With supplier';
    if ($has('Date_Approved_PO'))       return 'Approved';
    return 'Pending approval';
}

// Supplier + current status for a PO, appended to every notification so the
// bell answers "which PO, whose supplier, where is it now" without opening it.
// Returns '' rather than throwing if the PO can't be read — a notification is
// a convenience and must never break the write that triggered it.
function poNotifyContext($poNumber): string {
    if (!$poNumber) return '';
    try {
        $stmt = getPoDB()->prepare(
            "SELECT Supplier_Name, Date_Approved_PO, Date_Received_Supplier, Date_COA_Received
             FROM tbl_purchase_order WHERE PO_Number = ? AND isDeleted = 0 LIMIT 1"
        );
        $stmt->execute([$poNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return '';
        $row = fixLegacyEncodingDeep($row);
        $parts = [];
        if (trim((string)$row['Supplier_Name']) !== '') $parts[] = trim($row['Supplier_Name']);
        $parts[] = poStatusLabel($row);
        return ' · ' . implode(' · ', $parts);
    } catch (Exception $e) {
        return '';
    }
}

// Generate a compact verification code + status for a PO. The QR code encodes
// this so scanning it reveals whether the printed copy is the original or an
// edited version, and its current lifecycle status.
function poVerificationData($poNumber): array {
    try {
        $db = getPoDB();
        $stmt = $db->prepare(
            "SELECT Date_Created, Date_Approved_PO, Date_Received_Supplier, Date_COA_Received, isDeleted
             FROM tbl_purchase_order WHERE PO_Number = ? ORDER BY ID DESC LIMIT 1"
        );
        $stmt->execute([$poNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['po' => $poNumber, 'status' => 'unknown', 'code' => '?'];

        // Status: where is it in the workflow?
        $has = fn($k) => isset($row[$k]) && trim((string)$row[$k]) !== '' && strpos((string)$row[$k], '0000-00-00') !== 0;
        if ($has('Date_COA_Received'))      $status = 'COA Received';
        elseif ($has('Date_Received_Supplier')) $status = 'With Supplier';
        elseif ($has('Date_Approved_PO'))   $status = 'Approved';
        else                                $status = 'Pending Approval';

        // Edit count: how many versions exist?
        $countStmt = $db->prepare(
            "SELECT COUNT(*) FROM tbl_purchase_order WHERE PO_Number = ? AND isDeleted = 0"
        );
        $countStmt->execute([$poNumber]);
        $versions = (int)$countStmt->fetchColumn();

        // Verification hash: first 8 chars of MD5(po_number + created_date) so
        // each original has a unique fingerprint; an edited version would have
        // a different created date and thus a different hash.
        $hash = substr(md5($poNumber . $row['Date_Created']), 0, 8);

        return [
            'po'       => $poNumber,
            'status'   => $status,
            'versions' => $versions,
            'original' => $versions === 1,
            'created'  => $row['Date_Created'],
            'code'     => $hash . ($versions === 1 ? 'A' : 'B'),  // A=original, B=edited
        ];
    } catch (Exception $e) {
        return [
            'po' => $poNumber, 'status' => 'error', 'code' => '!',
            'versions' => 0, 'original' => false, 'created' => null
        ];
    }
}

// ── Printed-QR verification tokens ──
// The QR on a printed PO carries a link to po_verify.php with an encrypted
// token. The token is a *snapshot of the PO as printed*; the verifier compares
// it against the live record, which is what lets a scan distinguish "this
// paper matches the system" from "this paper is a stale copy of a PO that has
// since been edited". Encrypting rather than plain-texting it means a token
// can't be hand-crafted to make a forged printout validate.

// Base URL the QR links to. Auto-derived from the request so a print made
// while browsing at the LAN address bakes in that address; APP_URL overrides
// it when a fixed public hostname is wanted instead.
function appBaseUrl(): string {
    $configured = trim($_ENV['APP_URL'] ?? '');
    if ($configured !== '') return rtrim($configured, '/');

    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return $scheme . '://' . $host . getBasePath();
}

// True when the QR would embed a host only this machine can resolve — printing
// then produces codes that fail on any phone. Surfaced as a warning in the
// print modal rather than blocked, since previewing on localhost is normal.
function appUrlIsLocalOnly(): bool {
    $host = parse_url(appBaseUrl(), PHP_URL_HOST) ?: '';
    return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
}

function poQrSecret(): string {
    $hex = trim($_ENV['QR_SECRET'] ?? '');
    if ($hex === '' || strlen($hex) < 32) {
        throw new RuntimeException('QR_SECRET is missing or too short in .env — printed QR codes cannot be signed.');
    }
    return hash('sha256', $hex, true); // 32 raw bytes for AES-256
}

function b64uEncode(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function b64uDecode(string $s): string {
    return (string)base64_decode(strtr($s, '-_', '+/'), true);
}

// AES-256-GCM: encryption plus a built-in authentication tag, so a modified
// token fails to decrypt rather than silently decoding to something else. The
// tag is kept at 8 bytes (the spec's minimum) purely to keep the QR small —
// forging one still needs the key, and a wrong guess is caught on decrypt.
function poVerifyToken($poNumber): string {
    $d    = poVerificationData($poNumber);
    // Snapshot as printed: PO number + the fingerprint at print time.
    $plain = $d['po'] . '|' . $d['code'];
    $iv    = random_bytes(12);
    $tag   = '';
    $ct    = openssl_encrypt($plain, 'aes-256-gcm', poQrSecret(), OPENSSL_RAW_DATA, $iv, $tag, '', 8);
    if ($ct === false) throw new RuntimeException('Unable to encrypt verification token.');
    return b64uEncode($iv . $tag . $ct);
}

// Returns ['po' => ..., 'code' => ...] or null if the token is absent,
// malformed, or fails its authentication tag.
function poVerifyDecodeToken($token): ?array {
    $raw = b64uDecode((string)$token);
    if (strlen($raw) < 21) return null;                 // 12 IV + 8 tag + >=1 byte
    $iv  = substr($raw, 0, 12);
    $tag = substr($raw, 12, 8);
    $ct  = substr($raw, 20);
    try {
        $plain = openssl_decrypt($ct, 'aes-256-gcm', poQrSecret(), OPENSSL_RAW_DATA, $iv, $tag);
    } catch (Throwable $e) {
        return null;
    }
    if ($plain === false || !str_contains($plain, '|')) return null;
    [$po, $code] = explode('|', $plain, 2);
    if ($po === '' || $code === '') return null;
    return ['po' => $po, 'code' => $code];
}

// The verification URL encoded into the printed QR.
function poQrPayload($poNumber): string {
    return appBaseUrl() . '/po_verify.php?t=' . poVerifyToken($poNumber);
}

// A real, scannable QR code (ISO/IEC 18004, byte mode) rendered by
// system/lib/qrcode.php. PNG rather than SVG because the print preview is
// rasterised by html2canvas, which does not reliably render inline SVG.
//
// EC level L, not M: a URL plus an encrypted token is enough data that level M
// would push the symbol to ~49 modules, shrinking each module below what a
// phone camera reads reliably at this print size. L keeps it near 41 modules.
function renderPoQrCodePng($poNumber, $scale = 6): string {
    try {
        return qrPngDataUri(poQrPayload($poNumber), $scale, 4, 'L');
    } catch (Throwable $e) {
        return '';
    }
}

// Code 128 barcode of the PO number itself, for the printout's letterhead.
// This is the human-workflow counterpart to the QR: a warehouse scanner reads
// a linear barcode straight into a "PO number" field, where a QR would paste a
// whole verification URL. Rendered at scale 2 and displayed at half that width
// so the bars land on exact pixel boundaries after html2canvas rasterises it.
// ── Reference-value canonicalisation ────────────────────────────────────────
//
// tbl_purchase_order stores Supplier_Name / Department / Division / Section as
// free text with no foreign key, so the same supplier accumulates spellings:
// 1,377 distinct Supplier_Name values against 808 rows in ref_supplier, with
// 146 groups differing only by punctuation or spacing ("5 J CONSUMER  GOODS"
// vs "5J CONSUMER GOODS"). That makes "total spend per supplier" unanswerable.
//
// This does NOT change the schema and never rewrites existing rows. It only
// snaps a value being SAVED onto the reference table's own spelling when the
// two match ignoring case, punctuation and spacing. A genuinely new supplier is
// stored as typed, with runs of whitespace collapsed.
//
// It lives here rather than in the page so the Excel import and any direct API
// call get it too — a client-side picker alone can always be bypassed.

// Comparison key: case, punctuation and spacing removed.
function refCanonicalKey($value): string {
    return preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$value));
}

// Does this purchase order's Payment Term put it on progress billing?
//
// THE single definition of that test — the worklist, the API and the page all
// call it, so a purchase order can never be a progress-billing one in the list
// and an ordinary one in the modal.
//
// A substring match, not equality, and that is deliberate. The live table holds
// the term in three shapes, all meaning the same thing:
//     "Progress Billing"                                (what Master Data now stores)
//     "Progress Billing Working Days Payment Term"      (2,742 rows — the legacy VB
//                                                        form concatenated its own
//                                                        label onto the value)
//     "progress billing"                                (hand-typed, any casing)
// Anchoring on equality would classify the 2,742 legacy rows as ordinary POs.
function isProgressBillingTerm($term): bool {
    return stripos((string)$term, 'progress billing') !== false;
}

// How far through its purchase order a set of progress billings has got.
//
// THE single definition, called by the worklist (api/inspection.php) and by the
// billing dialog (api/inspection_progress.php) alike. The page renders what
// this returns and decides nothing for itself — a badge saying 100% beside a
// dialog saying 99.9% would be two answers to a question with one answer.
//
// 'complete' is decided by what is LEFT, never by the percentage: on a
// ₱1,000,000 contract with ₱4 outstanding the percentage rounds to 100.00, and
// a purchase order that still owes money is not finished. Half a centavo of
// tolerance, because the amounts are decimals summed from many rows.
function progressBillingStatus($orderedAmount, $billedAmount, int $billingCount): array {
    $ordered = (float)$orderedAmount;
    $billed  = (float)$billedAmount;
    $remaining = $ordered - $billed;
    // No amount to measure against — a percentage of zero would be a made-up
    // number, so there isn't one.
    $percent = $ordered > 0 ? round($billed / $ordered * 100, 2) : null;

    if ($billingCount <= 0)          $status = 'idle';
    elseif ($percent === null)       $status = 'unmeasured';
    elseif ($remaining < -0.005)     $status = 'over';
    elseif ($remaining <= 0.005)     $status = 'complete';
    else                             $status = 'partial';

    return [
        'status'           => $status,
        'percent'          => $percent,
        'ordered_amount'   => $ordered,
        'billed_amount'    => $billed,
        'remaining_amount' => $remaining,
        'billing_count'    => $billingCount,
    ];
}

function canonicalizeAgainstRef(PDO $db, $value, string $table, string $column): string {
    $value = trim(preg_replace('/\s+/u', ' ', (string)$value));
    if ($value === '') return '';
    $key = refCanonicalKey($value);
    if ($key === '') return $value;

    // One query per reference table per request; these tables are tiny
    // (ref_supplier is the biggest at ~800 rows).
    static $cache = [];
    $cacheKey = $table . '|' . $column;
    if (!isset($cache[$cacheKey])) {
        $cache[$cacheKey] = [];
        try {
            foreach ($db->query("SELECT `$column` FROM `$table`")->fetchAll(PDO::FETCH_COLUMN) as $ref) {
                $refKey = refCanonicalKey($ref);
                // First spelling wins, so the result is stable run to run.
                if ($refKey !== '' && !isset($cache[$cacheKey][$refKey])) {
                    $cache[$cacheKey][$refKey] = trim(preg_replace('/\s+/u', ' ', (string)$ref));
                }
            }
        } catch (Throwable $e) {
            error_log("Canonicalisation lookup failed for $table.$column: " . $e->getMessage());
            return $value;      // fail open: never block a save over tidying
        }
    }
    return $cache[$cacheKey][$key] ?? $value;
}

function canonicalSupplierName(PDO $db, $v): string { return canonicalizeAgainstRef($db, $v, 'ref_supplier',   'Supplier_Name'); }
function canonicalDepartment(PDO $db, $v): string   { return canonicalizeAgainstRef($db, $v, 'ref_department', 'Department'); }
function canonicalDivision(PDO $db, $v): string     { return canonicalizeAgainstRef($db, $v, 'ref_division',   'Division'); }
function canonicalSection(PDO $db, $v): string      { return canonicalizeAgainstRef($db, $v, 'ref_section',    'Section'); }

// What the Code 128 actually encodes for a value that ISN'T a full PO row —
// a manually-typed PO number, or (this function is shared) the PAR/ICS
// number on a Property Tag. Just punctuation stripped, so "PAR-2026-001"
// scans as "PAR2026001". See poNumberBarcode() below for the PO print path,
// which builds a purely numeric value instead — DTS's own document numbers
// are numeric only, and this generic fallback can't guarantee that for
// arbitrary text.
function poBarcodeValue($poNumber): string {
    return preg_replace('/[^A-Za-z0-9]/', '', (string)$poNumber);
}

// The barcode actually printed on a Purchase Order / Obligation Request —
// derived from the row's own id and creation date rather than parsed out of
// PO_Number's text. That text turns out to have drifted through at least
// three different shapes over the years (confirmed against the live table):
// today's PREFIX-sysYYYYM##### with a zero-padded 5-digit id, an earlier
// PREFIX-sysYYYYM# variant with an UNPADDED id, and the very first
// PREFIX-YYYYM#sys shape from before the "-sys" prefix convention existed at
// all. Regex-parsing all three reliably turned out to be the wrong problem —
// the id this needs already lives on the row itself, unambiguous and always
// present, and IS the exact same number formatPoNumber() burns into new PO
// Numbers at creation time. Using it directly here means every existing PO
// gets a correct barcode too, not just ones shaped like the current format.
//
// DTS's own document numbers are purely numeric, so this is 99 + 2-digit
// year + 2-digit month (both from Date_Created, matching what formatPoNumber()
// used at creation) + the id, zero-padded to 5 digits = 11 digits, e.g. id
// 927257 created in September 2026 -> 99260927257. The PO Number itself is
// untouched everywhere else (display, search, storage) — only what gets
// printed as the scannable Code128 symbol uses this.
function poNumberBarcode($id, $dateCreated): string {
    $id = (int)$id;
    $ts = $dateCreated ? strtotime((string)$dateCreated) : false;
    if ($id <= 0 || $ts === false) return '';
    return '99' . date('y', $ts) . date('m', $ts) . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

// Width, in CSS pixels, at which the PNG from renderPoBarcodePng() must be
// displayed: exactly half its natural width, so every bar lands on a whole
// pixel. A fractional scale blurs the bar edges and scanners stop reading it.
//
// This has to be computed rather than hardcoded because the symbol's width
// depends on the PO number's length — dropping the hyphen alone took it from
// 167 modules to 156. A constant here silently goes wrong the next time the
// encoded value changes shape.
function poBarcodeDisplayWidth($poNumber, $scale = 2, $quiet = 10): int {
    try {
        $value = poBarcodeValue($poNumber);
        if ($value === '') return 0;
        return intdiv((count(code128Modules($value)) + $quiet * 2) * $scale, 2);
    } catch (Throwable $e) {
        return 0;
    }
}

function renderPoBarcodePng($poNumber, $scale = 2, $height = 38): string {
    try {
        $value = poBarcodeValue($poNumber);
        if ($value === '') return '';
        return code128PngDataUri($value, $scale, $height);
    } catch (Throwable $e) {
        error_log('Barcode generation failed: ' . $e->getMessage());
        return '';
    }
}

function renderPoQrCodeSvg($poNumber, $size = 140): string {
    try {
        return qrSvg(poQrPayload($poNumber), $size, 4, 'L');
    } catch (Throwable $e) {
        return '';
    }
}

// Same idea as notifyRoles() above, but for the Purchase Order module's own
// notification tables on the live HRMIS server (tbl_po_notifications /
// tbl_po_notification_reads — see database/tbl_po_notifications.sql). Used
// for PO events (create/edit/lock/approve) rather than this app's own local
// features.
//
// Recipients are resolved from the LOCAL users table, not the live HRMIS one.
// This app authenticates only against pyobasesystem_db, so getCurrentUser()
// returns a local id — and getRecentPoNotifications() reads the bell back with
// that same local id. An earlier version resolved targets from the HRMIS
// `users`/`table_role` tables instead, which wrote every row keyed to a
// different id space: 152 notifications accumulated that literally no one
// could ever see.
// $audience picks who hears about it:
//   'all'    — every active account, plain `user` role included. Used for the
//              events any staff member has a reason to see: a PO being saved,
//              edited, or moving through approval.
//   'admins' — super_admin + admin only. Used for the operational events a
//              plain user can't act on anyway (lock/unlock, barcode, remarks).
function notifyPoUsers($title, $message = '', $link = null, $poNumber = null, $audience = 'admins') {
    try {
        $actor  = getCurrentUser();
        $poDb   = getPoDB();
        $actorId = (int)($actor['id'] ?? 0);

        // Recipients MUST come from the HRMIS `users` table, because that is
        // the ID space a session carries: api/auth.php authenticates against
        // purchase_request.users and stores its user_id in pbs_user_id, which
        // is the same id getRecentPoNotifications() reads the bell back with.
        // Resolving them from the local pyobasesystem_db instead writes rows
        // keyed to an unrelated id space that nobody can ever see — that table
        // holds a single seeded row and is not what anyone logs in as.
        //
        // Audience is narrowed by HRMIS's own per-user access flags rather
        // than by role name: system_access_level.purchase_order is what marks
        // someone as working with POs at all. Without it every Inventory,
        // PPMP and Warehousing account (125 in total) would be notified on
        // every save.
        $rows = $poDb->query(
            "SELECT DISTINCT u.user_id, u.role_id, tr.role
             FROM users u
             JOIN system_access_level s ON s.piid = u.piid AND s.purchase_order = 1
             LEFT JOIN table_role tr ON tr.role_id = u.role_id"
        )->fetchAll(PDO::FETCH_ASSOC);

        $targets = [];
        foreach ($rows as $r) {
            $appRole = deriveAppRole($r['role_id'], $r['role'] ?? '');
            if ($audience !== 'all' && !in_array($appRole, ['super_admin', 'admin'], true)) continue;
            $targets[] = (int)$r['user_id'];
        }
        $targets = array_values(array_unique($targets));

        // The actor IS notified about their own action. The usual "don't
        // notify yourself" rule was actively harmful here: procurement is run
        // by one or two people, so nearly every event is raised by the same
        // account that would want it in the feed, and the bell simply stayed
        // empty for whoever was doing the work.
        //
        // This feed is a record of what happened to a PO, not a to-do list —
        // seeing your own "PO edited" line is the point, and it's what makes
        // the trail complete when several staff touch the same order.
        $recipients = $targets;
        if (!$recipients) return;

        $message = trim((string)$message) . poNotifyContext($poNumber);

        $stmt = $poDb->prepare(
            "INSERT INTO tbl_po_notifications (User_ID, Title, Message, Link, PO_Number, Created_By) VALUES (?,?,?,?,?,?)"
        );
        foreach ($recipients as $userId) {
            $stmt->execute([$userId, $title, $message, $link, $poNumber, $actorId ?: null]);
        }
    } catch (Exception $e) {
        // silently fail — notifications are a convenience, not critical path
    }
}

function getRecentPoNotifications($userId, $limit = 10) {
    $stmt = getPoDB()->prepare(
        "SELECT n.ID AS id, n.Title AS title, n.Message AS message, n.Link AS link, n.Date_Created AS created_at,
                (nr.User_ID IS NOT NULL) AS is_read
         FROM tbl_po_notifications n
         LEFT JOIN tbl_po_notification_reads nr ON nr.Notification_ID = n.ID AND nr.User_ID = ?
         WHERE n.User_ID = ? AND n.isDeleted = 0
         ORDER BY n.Date_Created DESC
         LIMIT " . (int)$limit
    );
    $stmt->execute([$userId, $userId]);
    return fixLegacyEncodingDeep($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getUnreadPoNotificationCount($userId) {
    $stmt = getPoDB()->prepare(
        "SELECT COUNT(*) FROM tbl_po_notifications n
         LEFT JOIN tbl_po_notification_reads nr ON nr.Notification_ID = n.ID AND nr.User_ID = ?
         WHERE n.User_ID = ? AND n.isDeleted = 0 AND nr.User_ID IS NULL"
    );
    $stmt->execute([$userId, $userId]);
    return (int)$stmt->fetchColumn();
}

function markPoNotificationRead($notificationId, $userId) {
    getPoDB()->prepare("INSERT IGNORE INTO tbl_po_notification_reads (Notification_ID, User_ID) VALUES (?,?)")
             ->execute([$notificationId, $userId]);
}

function markAllPoNotificationsRead($userId) {
    getPoDB()->prepare(
        "INSERT IGNORE INTO tbl_po_notification_reads (Notification_ID, User_ID)
         SELECT ID, ? FROM tbl_po_notifications WHERE User_ID = ?"
    )->execute([$userId, $userId]);
}

// Web-relative <img src> path for a user's uploaded avatar, or null if they
// haven't set one — in which case the caller falls back to their initial.
//
// The path is only returned if the file is actually on disk. Logins have set
// avatar_path to null ever since avatars stopped being stored (they needed the
// retired local database), but a session created BEFORE that change still
// carries the old `uploads/avatars/user_N_….png` value. That file is long gone,
// so the topbar emitted an <img> pointing at nothing and the browser drew its
// broken-image icon in place of the avatar. Checking existence turns a stale
// session back into the plain initial instead.
//
// Paths are also confined to uploads/avatars/ — avatar_path reaches this from
// the session, and a session value should never be able to name an arbitrary
// file on the server.
function avatarUrl($user) {
    $path = trim((string)($user['avatar_path'] ?? ''));
    if ($path === '') return null;
    if (strpos($path, '..') !== false) return null;
    if (strncmp($path, 'uploads/avatars/', 16) !== 0) return null;
    return is_file(__DIR__ . '/' . $path) ? $path : null;
}

// Audit trail lives on the HRMIS server (tbl_po_audit_logs), not in a local
// database — the deploy box runs no MySQL of its own. User_ID is therefore an
// HRMIS users.user_id, matching what api/auth.php puts in the session.
function logAudit($action, $module, $details = '') {
    try {
        $db = getPoDB();
        $user = getCurrentUser();
        $stmt = $db->prepare(
            "INSERT INTO tbl_po_audit_logs (User_ID, Username, Action, Module, Details, IP_Address) VALUES (?,?,?,?,?,?)"
        );
        $stmt->execute([
            $user['id'] ?? null,
            $user['username'] ?? 'system',
            $action,
            $module,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        // silently fail
    }
}

function isApiRequest() {
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
}

function getBasePath() {
    return '/SystemsMISPYO/PurchaseOrder/system';
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// A local asset URL with a cache-busting version on it: "js/foo.js" comes back
// as "js/foo.js?v=1735372800".
//
// This app has no build step and no fingerprinted filenames, so a stylesheet or
// script edited on the server keeps its URL — and browsers cache .js and .css
// hard. The failure that causes is nasty precisely because it does not look
// like a cache problem: the page runs old code against new markup and reports
// something like "PrintPdf.renderToPagedPdf is not a function", naming a
// function that no longer exists anywhere on disk. It cost a real debugging
// session, hence this.
//
// The version is the file's own mtime, so it changes exactly when the file
// changes and never otherwise — no manual bumping, and no defeating the cache
// on every request the way a timestamp would.
//
// Falls back to the bare path if the file cannot be stat'ed. A missing version
// is a cache risk; refusing to emit the tag at all would be a broken page.
function asset(string $path): string {
    $full = __DIR__ . '/' . ltrim($path, '/');
    $stamp = is_file($full) ? filemtime($full) : false;
    return $stamp === false ? $path : $path . '?v=' . $stamp;
}

function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    // JSON_INVALID_UTF8_SUBSTITUTE: guards against any external/legacy data with
    // invalid UTF-8 bytes silently turning json_encode() into a blank response.
    echo json_encode(['success' => $success, 'message' => $message, ...$data], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function getInput() {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) return $decoded;
    parse_str($raw, $form);
    return [...$_POST, ...$form];
}
?>
