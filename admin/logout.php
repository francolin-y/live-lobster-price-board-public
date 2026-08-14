<?php
define('ADMIN_APP', true);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
session_unset();
session_destroy();

header('Location: index.php');
exit;
