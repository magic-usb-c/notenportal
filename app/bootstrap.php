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

// Security headers (Apache kann das auch setzen; hier als Fallback)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Für Auth/Noten-Seiten sinnvoll: nicht cachen
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// CSP Baseline (vorsichtig, damit dein CSS/JS nicht unerwartet bricht)
// Falls du später Inline-Skripte brauchst: lieber nonce-basiert lösen.
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'; form-action 'self'");
}

// Session + CSRF
start_secure_session();

// Optionales Timeout-Backup (wenn du Seiten hast, die kein require_login() nutzen)
if (is_logged_in()) {
    enforce_idle_timeout();
}

if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// HTML escaping helper
if (!function_exists('h')) {
    function h(string $s): string
    {
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
