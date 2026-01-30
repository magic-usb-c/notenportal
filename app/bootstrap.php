<?php
declare(strict_types=1);

/*
  app/bootstrap.php
  Zweck:
  - zentraler Einstieg für alle public/*.php Seiten
  - lädt Helper (HTTP/Auth/DB/Context/Logging/Flash/Validation)
  - startet sichere Session, stellt CSRF Token bereit
  - stellt $pdo (PDO) und $ctx (User-Kontext) bereit
*/

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/user_context.php';

// Security-basics (du hast einiges bereits im Apache; hier als Backup)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

// Session + CSRF
start_secure_session();

if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// HTML escaping helper
if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// DB verbinden (außer in status.php; der nutzt db.php direkt)
try {
    $pdo = get_pdo();
} catch (Throwable $e) {
    app_log('error', 'DB connect failed', [
        'exception' => get_class($e),
    ]);

    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Service temporarily unavailable');
}

// Kontext laden (auch wenn nicht eingeloggt, ctx ist definiert)
try {
    $ctx = current_user_context($pdo);
} catch (Throwable $e) {
    app_log('error', 'Loading user context failed', [
        'exception' => get_class($e),
    ]);
    $ctx = [
        'user_id' => 0,
        'username' => '',
        'roles' => [],
        'is_admin' => false,
        'lernender_id' => null,
        'berufsbildner_id' => null,
    ];
}
