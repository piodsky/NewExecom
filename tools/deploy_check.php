<?php
// Deployment self-check. Run from the command line ON the deploy machine:
//     php tools/deploy_check.php
//
// CLI-only on purpose — it reports whether secrets and databases are reachable,
// which is not something to expose over HTTP. tools/ is denied by .htaccess too.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

$root = dirname(__DIR__) . '/system';
$ok = 0; $bad = 0;
function line($pass, $label, $detail = '') {
    global $ok, $bad;
    $pass ? $ok++ : $bad++;
    printf("  [%s] %-42s %s\n", $pass ? 'OK' : '!!', $label, $detail);
}

echo "\n=== PHP ===\n";
line(PHP_VERSION_ID >= 80000, 'PHP >= 8.0', PHP_VERSION);
foreach (['pdo_mysql', 'gd', 'openssl', 'mbstring', 'json'] as $ext) {
    line(extension_loaded($ext), "extension: $ext");
}

echo "\n=== FILES (all must exist) ===\n";
foreach ([
    'config.php', 'login.php', 'po_verify.php', 'lib/qrcode.php',
    'api/auth.php', 'api/purchase_orders.php', '.env',
] as $f) {
    line(file_exists("$root/$f"), $f);
}

echo "\n=== .htaccess protection ===\n";
foreach ([
    dirname(__DIR__) . '/.htaccess'      => 'root .htaccess',
    "$root/database/.htaccess"           => 'system/database/.htaccess',
    "$root/lib/.htaccess"                => 'system/lib/.htaccess',
    "$root/uploads/avatars/.htaccess"    => 'uploads/avatars/.htaccess',
] as $path => $label) {
    line(file_exists($path), $label);
}

if (!file_exists("$root/.env")) {
    echo "\n!! .env is missing — nothing below can work. Copy it to $root/.env\n";
    exit(1);
}

echo "\n=== .env keys ===\n";
require_once "$root/config.php";
foreach ([
    'PO_DB_HOST','PO_DB_NAME','PO_DB_USER','PO_DB_PASS',
    'HRMIS_DB_HOST','HRMIS_DB_NAME','QR_SECRET','APP_NAME',
] as $k) {
    $present = isset($_ENV[$k]) && trim((string)$_ENV[$k]) !== '';
    line($present, "$k set", $present && !str_contains($k, 'PASS') && $k !== 'QR_SECRET' ? $_ENV[$k] : '');
}
line(strlen(trim($_ENV['QR_SECRET'] ?? '')) >= 32, 'QR_SECRET long enough');
echo "  APP_URL   = " . (trim($_ENV['APP_URL'] ?? '') ?: '(auto-detect)') . "\n";
echo "  APP_DEBUG = " . (trim($_ENV['APP_DEBUG'] ?? '') ?: '(off)') . "\n";

echo "\n=== DATABASE CONNECTIVITY ===\n";
foreach ([
    'HRMIS  purchase_request' => ['PO_DB_HOST','PO_DB_NAME','PO_DB_USER','PO_DB_PASS'],
    'HRMIS  hrmis'            => ['HRMIS_DB_HOST','HRMIS_DB_NAME','HRMIS_DB_USER','HRMIS_DB_PASS'],
] as $label => [$h, $n, $u, $p]) {
    try {
        new PDO("mysql:host={$_ENV[$h]};dbname={$_ENV[$n]};charset=utf8mb4", $_ENV[$u], $_ENV[$p],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        line(true, $label, "{$_ENV[$n]} @ {$_ENV[$h]}");
    } catch (PDOException $e) {
        line(false, $label, $e->getMessage());
    }
}

echo "\n=== LOGIN PATH (what auth.php actually does) ===\n";
try {
    $n = getPoDB()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    line(true, 'read purchase_request.users', "$n accounts");
    getPoDB()->query('SELECT COUNT(*) FROM table_role')->fetchColumn();
    line(true, 'read purchase_request.table_role');
} catch (Throwable $e) {
    line(false, 'HRMIS login tables', $e->getMessage());
}

echo "\n=== SESSIONS ===\n";
$sp = session_save_path() ?: sys_get_temp_dir();
line(is_writable($sp), 'session dir writable', $sp);

printf("\n%d passed, %d failed\n\n", $ok, $bad);
exit($bad ? 1 : 0);
