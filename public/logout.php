<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$csrf = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    http_response_code(400);
    exit('Bad Request (CSRF)');
}

app_log('info', 'Logout', [
    'user_id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
]);

logout_user();

header('Location: /login.php');
exit;
