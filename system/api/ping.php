<?php
// Connection heartbeat for the session watchdog (js/connection.js).
//
// Answers three separate questions in one cheap request, and the watchdog
// treats each answer differently:
//
//   web server reachable at all  ->  any HTTP response comes back
//   session still valid          ->  401 from requireAuth() (sign in again)
//   database reachable           ->  503 here (the app is unusable even
//                                    though the network is plainly fine)
//
// Deliberately does NOT call requireModule(). Losing access to one module is
// something that module should say for itself; it is not grounds for signing
// the person out of the whole application.
require_once '../config.php';

// Emits the 401 the watchdog looks for when the session has gone. isApiRequest()
// matches on '/api/' in the URI, which this file satisfies.
requireAuth();

header('Content-Type: application/json');
// Never cached, by anything. A proxy replaying a stale 200 would mask exactly
// the outage this endpoint exists to detect.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// getPoDB() handles a failure to CONNECT on its own — dbConnectionFailure()
// exits with a 500 for an /api/ route. This catches the other case: a
// connection that was established earlier in the process and has since died,
// where the failure only surfaces on use.
try {
    getPoDB()->query('SELECT 1');
} catch (Throwable $e) {
    error_log('ping: database unreachable: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database unreachable.']);
    exit;
}

jsonResponse(true, '', ['ok' => true]);
