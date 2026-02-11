<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

require_post();
require_csrf();

app_log('info', 'Logout', [
    'user_id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
]);

logout_user();

flash_add('info', 'Du bist ausgeloggt.');
redirect('/login.php');
