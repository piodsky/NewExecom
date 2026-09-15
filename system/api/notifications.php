<?php
// Notification bell.
//
// One source now: the Purchase Order module's notifications on the HRMIS
// server (tbl_po_notifications, written by notifyPoUsers() in config.php).
// The Pyo Base System's own local notifications table went away with
// pyobasesystem_db — nothing ever wrote to it in this app, so merging it in
// only added a second set of ids to disambiguate and a second database that
// had to be reachable for the bell to render.
//
// Ids are still prefixed "po:" so the mark-read routing and the frontend that
// parses that prefix keep working unchanged, and so a second source can be
// reintroduced later without another id collision.
require_once '../config.php';
requireAuth();
header('Content-Type: application/json');

$user   = getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $notifications = array_map(
        fn($n) => $n + ['source' => 'po'],
        getRecentPoNotifications($user['id'])
    );
    usort($notifications, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    $notifications = array_slice($notifications, 0, 10);
    foreach ($notifications as &$n) {
        $n['id'] = 'po:' . $n['id'];
    }
    unset($n);

    jsonResponse(true, '', [
        'notifications' => $notifications,
        'unread_count'  => getUnreadPoNotificationCount($user['id']),
    ]);
}

if ($method === 'POST') {
    $data   = getInput();
    $action = $data['action'] ?? '';

    if ($action === 'mark_read') {
        $raw = (string)($data['id'] ?? '');
        // Accepts both "po:7" and a bare "7" so an older cached page doesn't break.
        [$source, $id] = array_pad(explode(':', $raw, 2), 2, null);
        $id = (int)($id ?? $source);
        if (!$id) jsonResponse(false, 'Invalid notification ID.');
        markPoNotificationRead($id, $user['id']);
        jsonResponse(true, 'Marked as read.');
    }

    if ($action === 'mark_all_read') {
        markAllPoNotificationsRead($user['id']);
        jsonResponse(true, 'All notifications marked as read.');
    }

    jsonResponse(false, 'Invalid action.');
}

jsonResponse(false, 'Method not allowed.');
