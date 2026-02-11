<?php
declare(strict_types=1);

/*
  app/http.php
  Zweck:
  - kleine HTTP/Request Helper (Method Guards, Redirects, CSRF)
  - bewusst "low info": bei Fehlern keine Details im Output
*/

function require_method(string $method): void
{
    $actual = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $want = strtoupper($method);

    if ($actual !== $want) {
        http_response_code(405);
        header('Allow: ' . $want);
        exit('Method Not Allowed');
    }
}

function require_post(): void
{
    require_method('POST');
}

function redirect(string $path, int $code = 302): void
{
    // nur interne, absolute Pfade erlauben -> keine Open Redirects
    if ($path === '' || $path[0] !== '/') {
        $path = '/';
    }

    header('Location: ' . $path, true, $code);
    exit;
}

function csrf_token(): string
{
    // bootstrap erzeugt das Token, aber falls jemand es ohne bootstrap nutzt:
    if (function_exists('start_secure_session')) {
        start_secure_session();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $t = $_SESSION['csrf_token'] ?? '';
    if (!is_string($t) || $t === '') {
        $t = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $t;
    }
    return $t;
}

function csrf_field(): string
{
    $t = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
}

function require_csrf(): void
{
    if (function_exists('start_secure_session')) {
        start_secure_session();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $sent = (string)($_POST['csrf_token'] ?? '');
    $sess = (string)($_SESSION['csrf_token'] ?? '');

    if ($sent === '' || $sess === '' || !hash_equals($sess, $sent)) {
        http_response_code(400);
        exit('Bad Request');
    }
}
