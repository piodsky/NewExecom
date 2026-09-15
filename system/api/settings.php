<?php
require_once '../config.php';
requireSystemUsersAdmin();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET — audit log only. User account management moved to api/hrmis_users.php
// (the real, shared HRMIS login directory) — see settings.php's User
// Management tab.
if ($method === 'GET') {
    $tab = $_GET['tab'] ?? '';
    if ($tab === 'audit') {
        // Audit trail moved to the HRMIS server (tbl_po_audit_logs) when the
        // local pyobasesystem_db was retired. Columns are aliased back to the
        // lowercase names the Audit Log tab already renders, so the frontend
        // is unchanged.
        $stmt = getPoDB()->query(
            "SELECT ID AS id, User_ID AS user_id, Username AS username, Action AS action,
                    Module AS module, Details AS details, IP_Address AS ip_address,
                    Date_Created AS created_at
             FROM tbl_po_audit_logs ORDER BY Date_Created DESC LIMIT 200"
        );
        jsonResponse(true, '', ['rows' => fixLegacyEncodingDeep($stmt->fetchAll())]);
    }
    jsonResponse(false, 'Invalid tab.');
}

jsonResponse(false, 'Method not allowed.');
