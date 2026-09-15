<?php
// Entry point. The app itself lives in system/ (same layout as IAMS and
// PPMP); system/index.php decides between login.php and dashboard.php.
header('Location: system/index.php');
exit;
