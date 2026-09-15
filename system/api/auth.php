<?php
require_once '../config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data     = getInput();
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if (empty($username) || empty($password)) {
        jsonResponse(false, 'Username and password are required.');
    }

    try {
        // Accounts live on the real HRMIS server, not this app's own DB —
        // login-only: we authenticate against it but never write to it
        // (that table is shared by every other HRMIS module). See
        // getPoDB() in config.php.
        $poDb = getPoDB();
        $stmt = $poDb->prepare("SELECT * FROM users WHERE user_name = ? LIMIT 1");
        $stmt->execute([$username]);
        $hrmisUser = $stmt->fetch();

        // Legacy HRMIS passwords are unsalted MD5 — hash_equals() keeps the
        // comparison itself timing-safe even though the hash algorithm isn't strong.
        if ($hrmisUser && hash_equals((string)$hrmisUser['password'], md5($password))) {
            // Checked only after the password already matched, not folded into
            // the WHERE above — a wrong password and a deactivated account
            // should read as the same generic failure to anyone probing
            // usernames, not two distinguishable error states. This column is
            // enforced only here; the other HRMIS modules sharing this same
            // `users` table (see api/hrmis_users.php's own note on that) don't
            // query it and are unaffected.
            if (isset($hrmisUser['is_active']) && !(int)$hrmisUser['is_active']) {
                jsonResponse(false, 'This account has been deactivated. Contact your administrator.');
            }
            $roleStmt = $poDb->prepare("SELECT role FROM table_role WHERE role_id = ?");
            $roleStmt->execute([$hrmisUser['role_id']]);
            $roleName = $roleStmt->fetchColumn() ?: '';
            $role = deriveAppRole($hrmisUser['role_id'], $roleName);

            $fullName = $hrmisUser['user_name'];
            if (!empty($hrmisUser['piid'])) {
                $piStmt = $poDb->prepare("SELECT FirstName, MiddleName, SurName, NameExt FROM tblpersonalinformation WHERE PIID = ?");
                $piStmt->execute([$hrmisUser['piid']]);
                if ($pi = $piStmt->fetch()) {
                    $fullName = trim(implode(' ', array_filter([$pi['FirstName'], $pi['MiddleName'], $pi['SurName'], $pi['NameExt']])));
                }
            }

            // We never write to the HRMIS `users` table, so last_login isn't
            // tracked there — derive it from our own audit log of previous
            // LOGIN events instead. That log lives on the HRMIS server too
            // (tbl_po_audit_logs), so login needs exactly one database.
            //
            // Wrapped separately and allowed to fail: this is a cosmetic
            // "Last Login" line on the profile modal. It previously ran
            // against a local database whose absence turned every *correct*
            // password into an HTTP 500 — a display detail must never be able
            // to block an authentication that already succeeded.
            $lastLogin = null;
            try {
                $lastLoginStmt = $poDb->prepare(
                    "SELECT Date_Created FROM tbl_po_audit_logs
                     WHERE Username = ? AND Action = 'LOGIN'
                     ORDER BY Date_Created DESC LIMIT 1"
                );
                $lastLoginStmt->execute([$username]);
                $lastLogin = $lastLoginStmt->fetchColumn() ?: null;
            } catch (Throwable $e) {
                error_log('Last-login lookup failed (non-fatal): ' . $e->getMessage());
            }

            $_SESSION['pbs_user_id']     = (int)$hrmisUser['user_id'];
            $_SESSION['pbs_username']    = $hrmisUser['user_name'];
            $_SESSION['pbs_full_name']   = $fullName ?: $hrmisUser['user_name'];
            $_SESSION['pbs_avatar_path'] = null;
            $_SESSION['pbs_role']        = $role;
            $_SESSION['pbs_role_id']     = (int)$hrmisUser['role_id'];
            // Shown on the profile modal as "Last Login" — captured *before* this
            // session's own LOGIN row is written, so it reads as "before now".
            $_SESSION['pbs_last_login']  = $lastLogin;

            logAudit('LOGIN', 'auth', "User logged in from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            jsonResponse(true, 'Login successful.', ['redirect' => 'dashboard.php']);
        } else {
            jsonResponse(false, 'Invalid username or password.');
        }
    } catch (Exception $e) {
        jsonResponse(false, 'Login error. Please try again.');
    }
}

if ($method === 'DELETE') {
    if (isLoggedIn()) {
        logAudit('LOGOUT', 'auth', 'User logged out');
    }
    session_destroy();
    jsonResponse(true, 'Logged out successfully.');
}

jsonResponse(false, 'Method not allowed.');
?>
