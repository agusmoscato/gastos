<?php
require_once __DIR__ . '/includes/auth.php';
clear_remember_token();
$_SESSION = [];
session_destroy();
header('Location: /login.php');
exit;
