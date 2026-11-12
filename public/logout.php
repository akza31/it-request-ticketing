<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if (!empty($_SESSION['user']['id'])) audit($pdo, (int)$_SESSION['user']['id'], 'LOGOUT', 'user', (int)$_SESSION['user']['id']);
$_SESSION = [];
session_destroy();
redirect('login.php');
