<?php
declare(strict_types=1);

/*
  app/auth.php
  Zweck:
  - sichere Session-Konfiguration
  - Login/Logout
  - Guards (require_login etc.)
  - Rollen werden über DB abgefragt (benutzer_rollen -> rollen)
*/

require_once __DIR__ . '/http.php';

const NP_SESSION_NAME = 'np_session';
const NP_SESSION_IDLE_TIMEOUT = 60 * 60; // 60 Minuten

/**
 * Session start mit sicheren Defaults.
 * Achtung: funktioniert am besten, wenn storage/sessions existiert (PHP session.save_path in php.ini),
 * sonst nutzt PHP den System-default.
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    // Cookie-Params (merken wir uns für Logout-Löschung)
    $params = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',      // leer = current host
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    session_name(NP_SESSION_NAME);
    session_set_cookie_params($params);
    session_start();

    // Light binding gegen Session hijacking (nicht zu aggressiv, sonst Probleme hinter Proxies)
    if (!isset($_SESSION['_sess_sig'])) {
        $_SESSION['_sess_sig'] = session_signature();
    } else {
        if (!hash_equals((string)$_SESSION['_sess_sig'], session_signature())) {
            // Signatur passt nicht -> Session kill
            logout_user();
            redirect('/login.php');
        }
    }
}

/**
 * Signature über UA + grobe IP (nur /24 bei IPv4, /64 bei IPv6) um nicht zu strict zu sein.
 */
function session_signature(): string
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

    // IP grob maskieren, damit DHCP/NAT nicht sofort killt, aber Replay schwerer wird
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $ip = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // very rough /64-ish
        $ip = preg_replace('/(^([0-9a-fA-F]{0,4}:){4}).*$/', '$1::', $ip) ?: $ip;
    }

    return hash('sha256', $ua . '|' . $ip);
}

function login_user(int $benutzer_id, string $benutzername): void
{
    start_secure_session();

    // Neue Session-ID nach Login
    session_regenerate_id(true);

    $_SESSION['user_id'] = $benutzer_id;
    $_SESSION['username'] = $benutzername;

    $_SESSION['logged_in_at'] = time();
    $_SESSION['last_activity'] = time();

    // Reset Login-Fails
    unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
}

function logout_user(): void
{
    start_secure_session();

    $_SESSION = [];

    // Cookie korrekt löschen: gleiche params wie gesetzt
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieParams = session_get_cookie_params();

    // PHPs session_get_cookie_params() liefert kein samesite, daher setzen wir mindestens path/domain/secure/httponly.
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 3600,
            'path'     => $cookieParams['path'] ?? '/',
            'domain'   => $cookieParams['domain'] ?? '',
            'secure'   => $cookieParams['secure'] ?? $isHttps,
            'httponly' => $cookieParams['httponly'] ?? true,
            'samesite' => 'Lax',
        ]
    );

    session_destroy();
}

function is_logged_in(): bool
{
    start_secure_session();
    return isset($_SESSION['user_id']) && ctype_digit((string)$_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
}

/**
 * Session-Timeout: wenn zu lange inaktiv, wird ausgeloggt.
 */
function enforce_idle_timeout(): void
{
    start_secure_session();

    if (!is_logged_in()) {
        return;
    }

    $last = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : 0;
    if ($last > 0 && (time() - $last) > NP_SESSION_IDLE_TIMEOUT) {
        logout_user();
        redirect('/login.php');
    }

    $_SESSION['last_activity'] = time();
}

function require_login(): void
{
    start_secure_session();

    if (!is_logged_in()) {
        redirect('/login.php');
    }

    enforce_idle_timeout();
}

/**
 * Rollen aus DB (z.B. ['Admin','Lernender']).
 */
function current_user_roles(PDO $pdo): array
{
    start_secure_session();

    if (!isset($_SESSION['user_id']) || !ctype_digit((string)$_SESSION['user_id'])) {
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

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $row) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name !== '') $out[] = $name;
    }
    return $out;
}

function user_has_role(PDO $pdo, string $roleName): bool
{
    $roleName = trim($roleName);
    if ($roleName === '') return false;

    foreach (current_user_roles($pdo) as $r) {
        if (strcasecmp($r, $roleName) === 0) {
            return true;
        }
    }
    return false;
}

function require_role(PDO $pdo, string $roleName): void
{
    if (!user_has_role($pdo, $roleName)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

/**
 * Sehr simples Login-Rate-Limit pro Session (Lab-tauglich).
 */
function login_rate_limit_check(): bool
{
    start_secure_session();
    $lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);
    return $lockedUntil > time();
}

function login_rate_limit_register_fail(): void
{
    start_secure_session();

    $fails = $_SESSION['login_failures'] ?? ['count' => 0, 'since' => time()];
    if (!is_array($fails) || !isset($fails['count'], $fails['since'])) {
        $fails = ['count' => 0, 'since' => time()];
    }

    // Fenster: 10 Minuten
    $window = 10 * 60;
    if ((time() - (int)$fails['since']) > $window) {
        $fails = ['count' => 0, 'since' => time()];
    }

    $fails['count'] = (int)$fails['count'] + 1;

    // ab 8 Fehlversuchen -> 10 Minuten lock
    if ($fails['count'] >= 8) {
        $_SESSION['login_locked_until'] = time() + 10 * 60;
    }

    $_SESSION['login_failures'] = $fails;
}
