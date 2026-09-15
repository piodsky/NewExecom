<?php
require_once 'config.php';
if (isLoggedIn()) {
    logAudit('LOGOUT', 'auth', 'User logged out');
}
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>
