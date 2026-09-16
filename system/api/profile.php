<?php
// My Profile — self-service name/department/password/avatar for the
// logged-in user's own local `users` row.
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$user   = getCurrentUser();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT username, full_name, first_name, middle_name, last_name, suffix,
                department, role, avatar_path, is_active, created_at, last_login
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(false, 'Account not found.');
    jsonResponse(true, '', ['user' => $row]);
}

if ($method === 'POST') {
    $data = $_POST;
    $firstName  = trim($data['first_name']  ?? '');
    $middleName = trim($data['middle_name'] ?? '');
    $lastName   = trim($data['last_name']   ?? '');
    $suffix     = trim($data['suffix']      ?? '');
    $department = trim($data['department']  ?? '');
    $currentPw  = (string)($data['current_password'] ?? '');
    $newPw      = (string)($data['new_password']      ?? '');
    $confirmPw  = (string)($data['confirm_password']  ?? '');

    if ($firstName === '' || $lastName === '') {
        jsonResponse(false, 'First and last name are required.');
    }

    $stmt = $db->prepare('SELECT password, avatar_path FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(false, 'Account not found.');

    // full_name is a derived, denormalized column — always recomputed here,
    // never written directly. Sidebar/topbar/session/audit log all read it.
    $fullName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName, $suffix])));

    $newPasswordHash = null;
    if ($currentPw !== '' || $newPw !== '' || $confirmPw !== '') {
        if (!password_verify($currentPw, $row['password'])) {
            jsonResponse(false, 'Current password is incorrect.');
        }
        if ($newPw === '' || strlen($newPw) < 8) {
            jsonResponse(false, 'New password must be at least 8 characters.');
        }
        if ($newPw !== $confirmPw) {
            jsonResponse(false, 'New password and confirmation do not match.');
        }
        $newPasswordHash = password_hash($newPw, PASSWORD_DEFAULT);
    }

    $avatarPath = $row['avatar_path'];
    if (!empty($_FILES['avatar']['tmp_name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['avatar']['tmp_name'];
        $size = (int)$_FILES['avatar']['size'];
        if ($size > 2 * 1024 * 1024) {
            jsonResponse(false, 'Avatar image must be 2MB or smaller.');
        }
        // Validated by real image content, not just the file extension —
        // never trust the client-supplied name or MIME header.
        $info = @getimagesize($tmp);
        $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($info === false || !isset($allowedMime[$info['mime']])) {
            jsonResponse(false, 'Avatar must be a valid JPG, PNG, or WEBP image.');
        }
        $ext      = $allowedMime[$info['mime']];
        $filename = 'user_' . $user['id'] . '_' . time() . '.' . $ext;
        $destDir  = __DIR__ . '/../uploads/avatars/';
        if (!move_uploaded_file($tmp, $destDir . $filename)) {
            jsonResponse(false, 'Failed to save the uploaded avatar.');
        }
        // Remove the previous avatar file, if any.
        if ($avatarPath && strncmp($avatarPath, 'uploads/avatars/', 16) === 0) {
            @unlink(__DIR__ . '/../' . $avatarPath);
        }
        $avatarPath = 'uploads/avatars/' . $filename;
    }

    $sql = 'UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
            full_name = ?, department = ?, avatar_path = ?';
    $params = [$firstName, $middleName ?: null, $lastName, $suffix ?: null, $fullName, $department ?: null, $avatarPath];
    if ($newPasswordHash !== null) {
        $sql .= ', password = ?';
        $params[] = $newPasswordHash;
    }
    $sql .= ' WHERE id = ?';
    $params[] = $user['id'];
    $db->prepare($sql)->execute($params);

    $_SESSION['pbs_full_name']   = $fullName;
    $_SESSION['pbs_avatar_path'] = $avatarPath;

    logAudit('UPDATE_PROFILE', 'profile', 'Updated own profile' . ($newPasswordHash !== null ? ' (password changed)' : ''));

    jsonResponse(true, 'Profile updated.', ['full_name' => $fullName, 'avatar_path' => $avatarPath]);
}

jsonResponse(false, 'Method not allowed.');
