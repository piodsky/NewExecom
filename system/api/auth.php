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
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT u.*, b.name AS branch_name FROM users u
             LEFT JOIN branches b ON b.id = u.branch_id
             WHERE u.username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (!(int)$user['is_active']) {
                jsonResponse(false, 'This account has been deactivated. Contact your administrator.');
            }

            $_SESSION['pbs_user_id']     = (int)$user['id'];
            $_SESSION['pbs_username']    = $user['username'];
            $_SESSION['pbs_full_name']   = $user['full_name'];
            $_SESSION['pbs_avatar_path'] = $user['avatar_path'];
            $_SESSION['pbs_role']        = $user['role'];
            $_SESSION['pbs_branch_id']   = $user['branch_id'] !== null ? (int)$user['branch_id'] : null;
            $_SESSION['pbs_branch_name'] = $user['branch_name'];
            $_SESSION['pbs_last_login']  = $user['last_login'];

            $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
            logAudit('LOGIN', 'auth', 'User logged in from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            jsonResponse(true, 'Login successful.', ['redirect' => 'dashboard.php']);
        } else {
            jsonResponse(false, 'Invalid username or password.');
        }
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
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
