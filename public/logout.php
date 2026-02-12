<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

require_post();
require_csrf();

// Werte fürs Logging sichern, bevor logout_user() die Session leert
$uid = $_SESSION['user_id'] ?? null;
$uname = $_SESSION['username'] ?? null;

app_log('info', 'Logout', [
    'user_id' => $uid,
    'username' => $uname,
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
]);

logout_user();

flash_add('info', 'Du bist ausgeloggt.');
redirect('/login.php');
