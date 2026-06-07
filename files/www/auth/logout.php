<?php
require_once '/data/adb/php8/files/www/auth/auth_functions.php';
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>