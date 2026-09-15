<?php
// My Profile — read-only.
//
// This used to read and write the local pyobasesystem_db `users` table. That
// stopped making sense once login moved to HRMIS: a session carries an HRMIS
// users.user_id, while the local table was keyed by its own ids, so the lookup
// only ever matched by coincidence (for the one account whose ids happened to
// collide). With the local database retired there is nothing to write to at
// all, so the endpoint now reports what HRMIS knows and edits nothing.
//
// Account details — name, role, password — are maintained in HRMIS itself, via
// Settings > User Management (api/hrmis_users.php), which is the directory
// every other HRMIS module shares.
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$user   = getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $poDb = getPoDB();

    // Split the display name back into parts for the modal's name fields.
    // HRMIS keeps the authoritative breakdown in tblpersonalinformation.
    $first = $middle = $last = $suffix = '';
    $department = '';
    try {
        $stmt = $poDb->prepare(
            "SELECT u.Department, p.FirstName, p.MiddleName, p.SurName, p.NameExt
             FROM users u
             LEFT JOIN tblpersonalinformation p ON p.PIID = u.piid
             WHERE u.user_id = ? LIMIT 1"
        );
        $stmt->execute([$user['id']]);
        if ($row = $stmt->fetch()) {
            $row        = fixLegacyEncodingDeep($row);
            $first      = $row['FirstName']  ?? '';
            $middle     = $row['MiddleName'] ?? '';
            $last       = $row['SurName']    ?? '';
            $suffix     = $row['NameExt']    ?? '';
            $department = $row['Department'] ?? '';
        }
    } catch (Throwable $e) {
        // Fall back to the session's display name — the modal still renders.
        error_log('Profile lookup failed (non-fatal): ' . $e->getMessage());
    }

    jsonResponse(true, '', ['user' => [
        'username'    => $user['username'],
        'full_name'   => $user['full_name'],
        'first_name'  => $first,
        'middle_name' => $middle,
        'last_name'   => $last,
        'suffix'      => $suffix,
        'department'  => $department,
        'role'        => $user['role'],
        'avatar_path' => null,   // avatars needed the retired local table
        'is_active'   => 1,      // HRMIS has no inactive flag; a row can log in
        'created_at'  => null,   // not tracked in HRMIS
        'last_login'  => $user['last_login'] ?? null,
        'read_only'   => true,
    ]]);
}

// Editing is intentionally unavailable — see the header comment.
jsonResponse(false, 'Profile details are managed in HRMIS. Ask an administrator to update them via Settings > User Management.');
