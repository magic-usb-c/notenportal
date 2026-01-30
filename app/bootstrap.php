<?php
declare(strict_types=1);

/*
  app/bootstrap.php
  Zweck:
  - ein zentraler Einstieg für alle Seiten
  - reduziert Copy-Paste (auth/db/user_context/helpers)
  - sorgt für einheitliches Verhalten (Session/Encoding/DB)
*/

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/user_context.php';
require_once __DIR__ . '/log.php';


start_secure_session();

// CSRF Token für Formulare (Login/Logout/etc.)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// HTML escaping helper (XSS-Schutz)
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// DB + Context global verfügbar machen (simpel für jetzt)
try { $pdo = get_pdo(); $ctx = current_user_context($pdo); }
catch(Throwable $e) { app_log('error','DB connect failed',['ex' => get_class($e)]); $pdo = null; $ctx = []; }
