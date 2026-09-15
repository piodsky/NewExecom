<?php
require_once 'config.php';
$next = isLoggedIn() ? 'dashboard.php' : 'login.php';
header('Location: ' . $next);
exit;
