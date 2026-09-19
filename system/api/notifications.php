<?php
// Notification bell — local notifications/notification_reads tables (see
// notifyRoles() in config.php).
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$user   = getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    jsonResponse(true, '', [
        'notifications' => getRecentNotifications($user['id'], $user['role']),
        'unread_count'  => getUnreadNotificationCount($user['id'], $user['role']),
    ]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid notification ID.');
        markNotificationRead($id, $user['id']);
        jsonResponse(true, 'Marked as read.');
    }

    if ($action === 'mark_unread') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid notification ID.');
        markNotificationUnread($id, $user['id']);
        jsonResponse(true, 'Marked as unread.');
    }

    if ($action === 'mark_all_read') {
        markAllNotificationsRead($user['id'], $user['role']);
        jsonResponse(true, 'All notifications marked as read.');
    }

    if ($action === 'dismiss') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonResponse(false, 'Invalid notification ID.');
        dismissNotification($id, $user['id']);
        jsonResponse(true, 'Notification deleted.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
