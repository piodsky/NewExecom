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

// Use the same local clock for form defaults, document numbers and SQL writes.
define('APP_TIMEZONE', $_ENV['APP_TIMEZONE'] ?? 'Asia/Manila');
date_default_timezone_set(APP_TIMEZONE);
require_once __DIR__ . '/lib/datetime.php';

// Local development only. Never leave APP_DEBUG=1 on a deployed instance —
// it puts PHP errors and database connection details back on screen.
function appDebug(): bool {
    return in_array(strtolower(trim($_ENV['APP_DEBUG'] ?? '')), ['1', 'true', 'yes', 'on'], true);
}
if (appDebug() && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
    ini_set('display_errors', '1');
}

define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'execom_db');
define('DB_USER',    $_ENV['DB_USER']    ?? 'root');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

define('APP_NAME',      $_ENV['APP_NAME']      ?? 'EXECOM');
define('APP_FULL_NAME', $_ENV['APP_FULL_NAME']  ?? 'EXECOM Computer Sales & Services');

// A connection failure must not tell an anonymous visitor which host, schema
// or account we tried. The specifics go to the error log; they only reach the
// screen under APP_DEBUG.
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

// EXECOM's own local database — the app's only connection.
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec('SET time_zone = ' . $pdo->quote((new DateTimeImmutable())->format('P')));
        } catch (PDOException $e) {
            dbConnectionFailure(DB_NAME . ' @ ' . DB_HOST, $e->getMessage());
        }
    }
    return $pdo;
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
        'branch_id'   => $_SESSION['pbs_branch_id'] ?? null,
        'branch_name' => $_SESSION['pbs_branch_name'] ?? null,
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

// Role rank — higher can manage lower. Keep in sync with `users.role` values.
// A plain array, not an ENUM in the database, because the owner intends to
// add more roles later and that must never need a schema migration — see
// canManageRole()/canAssignRole() below, which fall back to rank 0 (lowest,
// manageable by everyone above it) for any role not listed here yet.
const ROLE_RANK = ['branch_user' => 1, 'branch_admin' => 2, 'super_admin' => 3];

function roleLabel($role) {
    return match ($role) {
        'super_admin'  => 'Super Administrator',
        'branch_admin' => 'Branch Administrator',
        'branch_user'  => 'Branch User',
        default        => ucwords(str_replace('_', ' ', (string)$role)),
    };
}

// Can $actorRole manage (edit/delete) an account currently holding $targetRole?
function canManageRole($actorRole, $targetRole) {
    if ($actorRole === 'super_admin') return true;
    if ($actorRole === 'branch_admin') return (ROLE_RANK[$targetRole] ?? 0) < ROLE_RANK['branch_admin'];
    return false;
}

// Can $actorRole assign $newRole to an account (create or role change)?
function canAssignRole($actorRole, $newRole) {
    if ($actorRole === 'super_admin') return true;
    if ($actorRole === 'branch_admin') return $newRole === 'branch_user';
    return false;
}

// ── Branch scoping ──
// Every operational table carries a branch_id. A super_admin sees every
// branch (no filter applied); branch_admin/branch_user are confined to their
// own branch, in both the sidebar AND every API endpoint — hiding a link
// alone does not block a direct URL/API call.

// Returns the branch_id this user is confined to, or null for a super_admin
// (meaning "no filter — every branch").
function getUserBranchScope(array $user): ?int {
    if ($user['role'] === 'super_admin') return null;
    return $user['branch_id'] !== null ? (int)$user['branch_id'] : null;
}

// Guard for any page/API action that receives a branch_id from the browser
// (a dropdown, a query string, a posted form field). Never trust it without
// this check — see the branch-visibility rule in the structure plan.
function requireBranchAccess(?int $branchId): void {
    requireAuth();
    $user  = getCurrentUser();
    $scope = getUserBranchScope($user);
    if ($scope === null) return; // super_admin
    if ($branchId !== null && $branchId === $scope) return;
    if (isApiRequest()) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied for that branch.']);
        exit;
    }
    header('Location: ' . getBasePath() . '/dashboard.php?error=access_denied');
    exit;
}

// ── Per-role / per-account module access ──
// Add an entry here for every new page you want to be individually
// toggleable from Access Levels. Dashboard is intentionally excluded — it's
// the login/redirect landing page and must always stay reachable.
//
// Starts with just the two screens every base install needs; each module in
// the structure plan (Purchase Requisition, RFQ, PO, Receiving/Delivery
// Reports, Inventory, Stock Transfer, Issuance, Suppliers, Clients, Cost/
// Profit Reports) gets its own key added here as that page is built.
const ACCESS_MODULES = [
    'purchase_requisition' => 'Purchase Requisition',
    'internal_rfq'         => 'RFQ (Internal)',
    'po_internal'          => 'Purchase Order (Internal)',
    'receiving_reports'    => 'Receiving Reports',
    'inventory'            => 'Inventory',
    'stock_transfer'       => 'Stock Transfer',
    'issuance'             => 'Issuance (POS)',
    'outgoing_rfq'         => 'RFQ (Outgoing)',
    'outgoing_quotations'  => 'Quotations (Outgoing)',
    'po_outgoing'          => 'Purchase Order (Outgoing)',
    'delivery_reports'     => 'Delivery Reports',
    'order_tracking'       => 'Order Tracking',
    'cost_profit_reports'  => 'Cost/Profit Reports',
    'suppliers'            => 'Supplier Management',
    'clients'              => 'Client Management',
    'items'                => 'Item Catalog',
    'reports'              => 'Reports',
    'settings'             => 'User Management',
    'system_guide'         => 'System Guide',
];

// Resolution order: per-account override (user_module_access) > per-role
// default (role_module_access) > true if neither row exists.
function getEffectiveModuleAccess($userId, $role) {
    $access = [];
    foreach (ACCESS_MODULES as $key => $label) {
        $access[$key] = true;
    }
    if ($role === 'super_admin') return $access; // never gated

    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT module_key, can_access FROM role_module_access WHERE role = ?');
        $stmt->execute([$role]);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($access[$row['module_key']])) {
                $access[$row['module_key']] = (bool)$row['can_access'];
            }
        }
        if ($userId) {
            $stmt = $db->prepare('SELECT module_key, can_access FROM user_module_access WHERE user_id = ?');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll() as $row) {
                if (isset($access[$row['module_key']])) {
                    $access[$row['module_key']] = (bool)$row['can_access'];
                }
            }
        }
    } catch (Throwable $e) {
        error_log('getEffectiveModuleAccess failed: ' . $e->getMessage());
    }
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

// ── Document numbering ──
// Mints {BranchCode}-{DocType}-{Year}-{Sequence}, e.g. MLB-PR-2026-0001, from
// doc_sequences (branch_id, doc_type, year) UNIQUE key — atomic under a
// transaction so two concurrent submissions never get the same number.
function mintDocNumber(PDO $db, int $branchId, string $docType): string {
    $year = (int)date('Y');
    $db->prepare(
        'INSERT INTO doc_sequences (branch_id, doc_type, year, last_number) VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE last_number = last_number + 1'
    )->execute([$branchId, $docType, $year]);

    $stmt = $db->prepare('SELECT last_number FROM doc_sequences WHERE branch_id = ? AND doc_type = ? AND year = ?');
    $stmt->execute([$branchId, $docType, $year]);
    $seq = (int)$stmt->fetchColumn();

    $codeStmt = $db->prepare('SELECT code FROM branches WHERE id = ?');
    $codeStmt->execute([$branchId]);
    $code = $codeStmt->fetchColumn() ?: 'HQ';

    return sprintf('%s-%s-%d-%04d', $code, $docType, $year, $seq);
}

// Fires a low-stock notification when a bulk item's quantity_on_hand at a
// branch has just dropped to or below its reorder_level. Called after
// anything that decrements inventory_stock (Issuance, Stock Transfer ship).
// No "already notified" flag — a repeat sale while still below the
// threshold fires again, which is fine: each recipient's own read state
// (notification_reads) is what actually controls whether it nags them.
function checkLowStock(PDO $db, int $itemId, int $branchId): void {
    try {
        $stmt = $db->prepare(
            'SELECT s.quantity_on_hand, s.reorder_level, i.name AS item_name, b.name AS branch_name
             FROM inventory_stock s JOIN items i ON i.id = s.item_id JOIN branches b ON b.id = s.branch_id
             WHERE s.item_id = ? AND s.branch_id = ?'
        );
        $stmt->execute([$itemId, $branchId]);
        $row = $stmt->fetch();
        if (!$row || (float)$row['reorder_level'] <= 0) return;
        if ((float)$row['quantity_on_hand'] > (float)$row['reorder_level']) return;

        notifyRoles(
            ['branch_admin', 'super_admin'],
            'Low stock alert',
            "{$row['item_name']} at {$row['branch_name']} is down to {$row['quantity_on_hand']} (reorder level {$row['reorder_level']}).",
            'inventory.php'
        );
    } catch (Throwable $e) {
        error_log('checkLowStock failed: ' . $e->getMessage());
    }
}

// ── Notifications ──
// Broadcast per-role (notifyRoles(['branch_admin'], ...)); per-user read
// state tracked in notification_reads so each recipient dismisses
// independently.
function notifyRoles(array $roles, string $title, string $message = '', ?string $link = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO notifications (role, title, message, link) VALUES (?, ?, ?, ?)');
        foreach (array_unique($roles) as $role) {
            $stmt->execute([$role, $title, $message, $link]);
        }
    } catch (Throwable $e) {
        error_log('notifyRoles failed: ' . $e->getMessage());
    }
}

function getRecentNotifications($userId, $userRole, $limit = 10) {
    $stmt = getDB()->prepare(
        "SELECT n.id, n.title, n.message, n.link, n.created_at,
                (nr.user_id IS NOT NULL) AS is_read
         FROM notifications n
         LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = ?
         WHERE n.role = ?
         ORDER BY n.created_at DESC
         LIMIT " . (int)$limit
    );
    $stmt->execute([$userId, $userRole]);
    return $stmt->fetchAll();
}

function getUnreadNotificationCount($userId, $userRole) {
    $stmt = getDB()->prepare(
        "SELECT COUNT(*) FROM notifications n
         LEFT JOIN notification_reads nr ON nr.notification_id = n.id AND nr.user_id = ?
         WHERE n.role = ? AND nr.user_id IS NULL"
    );
    $stmt->execute([$userId, $userRole]);
    return (int)$stmt->fetchColumn();
}

function markNotificationRead($notificationId, $userId) {
    getDB()->prepare('INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)')
           ->execute([$notificationId, $userId]);
}

function markAllNotificationsRead($userId, $userRole) {
    getDB()->prepare(
        'INSERT IGNORE INTO notification_reads (notification_id, user_id)
         SELECT id, ? FROM notifications WHERE role = ?'
    )->execute([$userId, $userRole]);
}

// Web-relative <img src> path for a user's uploaded avatar, or null if they
// haven't set one — the caller falls back to their initial. Confined to
// uploads/avatars/ — avatar_path reaches this from the session, and a
// session value should never be able to name an arbitrary file on the server.
function avatarUrl($user) {
    $path = trim((string)($user['avatar_path'] ?? ''));
    if ($path === '') return null;
    if (strpos($path, '..') !== false) return null;
    if (strncmp($path, 'uploads/avatars/', 16) !== 0) return null;
    return is_file(__DIR__ . '/' . $path) ? $path : null;
}

function logAudit($action, $module, $details = '') {
    try {
        $db = getDB();
        $user = getCurrentUser();
        $stmt = $db->prepare(
            'INSERT INTO audit_logs (user_id, username, action, module, details, ip_address) VALUES (?,?,?,?,?,?)'
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
        // silently fail — an audit entry is a convenience, not critical path
    }
}

function isApiRequest() {
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
}

function getBasePath() {
    return '/SystemsMISPYO/EXECOM/system';
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// A local asset URL with a cache-busting version on it: "js/foo.js" comes back
// as "js/foo.js?v=1735372800". This app has no build step, so a stylesheet or
// script edited on the server keeps its URL and browsers cache it hard — the
// version is the file's own mtime, so it changes exactly when the file
// changes and never otherwise.
function asset(string $path): string {
    $full = __DIR__ . '/' . ltrim($path, '/');
    $stamp = is_file($full) ? filemtime($full) : false;
    return $stamp === false ? $path : $path . '?v=' . $stamp;
}

function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    // JSON_INVALID_UTF8_SUBSTITUTE guards against invalid UTF-8 bytes silently
    // turning json_encode() into a blank response.
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
