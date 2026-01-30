<?php
declare(strict_types=1);

/*
  app/auth.php
  Zweck:
  - sichere Session-Konfiguration (Cookies, strict mode, regeneration)
  - Hilfsfunktionen für Login/Logout/Guards

  WICHTIG:
  - Passwörter werden NICHT neu gehashed zum Vergleichen.
    Stattdessen: password_verify($plainPassword, $storedHash)
*/

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Strict mode verhindert Session-Fixation über frei gewählte Session-IDs
    ini_set('session.use_strict_mode', '1');

    // Cookies-only: keine Session-ID in URLs
    ini_set('session.use_only_cookies', '1');

    // In Prod: sichere Session-ID Länge/Entropie ist ok by default bei PHP 8.3,
    // wir lassen Standardwerte.

    // Cookie-Parameter setzen
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    // SameSite=Lax passt gut für Login-Forms (schützt gegen viele CSRF-Fälle)
    // Secure-Flag nur bei HTTPS, sonst würdest du dich in HTTP aussperren.
    session_set_cookie_params([
        'lifetime' => 0,                 // Session-Cookie bis Browser zu
        'path'     => '/',
        'domain'   => '',                // default Host
        'secure'   => $isHttps,          // nur über HTTPS senden
        'httponly' => true,              // JS kann Cookie nicht lesen
        'samesite' => 'Lax',
    ]);

    session_name('np_session');
    session_start();
}

/**
 * Nach erfolgreichem Login: Session ID rotieren (gegen Fixation)
 * und Userdaten in Session speichern.
 */
function login_user(int $benutzer_id, string $benutzername): void
{
    start_secure_session();

    // Regeneriert die Session-ID und löscht die alte
    session_regenerate_id(true);

    $_SESSION['user_id'] = $benutzer_id;
    $_SESSION['username'] = $benutzername;
    $_SESSION['logged_in_at'] = time();
}

/**
 * Session komplett zerstören (Logout)
 */
function logout_user(): void
{
    start_secure_session();

    $_SESSION = [];

    // Cookie invalidieren
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

/**
 * True/False: User eingeloggt?
 */
function is_logged_in(): bool
{
    start_secure_session();
    return isset($_SESSION['user_id']) && ctype_digit((string)$_SESSION['user_id']);
}

/**
 * Eingeloggte User-ID oder null
 */
function current_user_id(): ?int
{
    start_secure_session();
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
}

/**
 * Guard: Seite nur für eingeloggte User.
 * Redirect auf Login, wenn nicht eingeloggt.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Holt alle Rollen-Namen des eingeloggten Users aus der DB.
 * Rückgabe z.B. ['Admin','Lernender']
 */
function current_user_roles(PDO $pdo): array
{
    start_secure_session();

    if (!isset($_SESSION['user_id'])) {
        return [];
    }

    $uid = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare(
        'SELECT r.name
         FROM benutzer_rollen br
         JOIN rollen r ON r.rolle_id = br.rolle_id
         WHERE br.benutzer_id = :uid'
    );
    $stmt->execute([':uid' => $uid]);

    return array_map(
        static fn($row) => (string)$row['name'],
        $stmt->fetchAll()
    );
}

/**
 * True, wenn User eine bestimmte Rolle hat.
 */
function user_has_role(PDO $pdo, string $roleName): bool
{
    $roles = current_user_roles($pdo);
    foreach ($roles as $r) {
        if (strcasecmp($r, $roleName) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Guard: nur User mit Rolle dürfen weiter.
 * Beispiel: require_role($pdo, 'Admin');
 */
function require_role(PDO $pdo, string $roleName): void
{
    if (!user_has_role($pdo, $roleName)) {
        http_response_code(403);
        exit('Forbidden');
    }
}
