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
 * Hinweis: Optimal, wenn PHP session.save_path sauber gesetzt ist (z.B. storage/sessions).
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

    $params = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    session_name(NP_SESSION_NAME);
    session_set_cookie_params($params);
    session_start();

    // Light binding gegen Session hijacking (nicht zu aggressiv, sonst Proxy/NAT-Probleme)
    if (!isset($_SESSION['_sess_sig']) || !is_string($_SESSION['_sess_sig'])) {
        $_SESSION['_sess_sig'] = session_signature();
    } else {
        if (!hash_equals($_SESSION['_sess_sig'], session_signature())) {
            // Signatur passt nicht -> Session kill + Login erzwingen
            logout_user();
            redirect('/login.php');
        }
    }
}

/**
 * Signature über UA + grobe IP (/24 bei IPv4, grob /64 bei IPv6).
 */
function session_signature(): string
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $ip = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
        }
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // grob /64-ish
        $masked = preg_replace('/(^([0-9a-fA-F]{0,4}:){4}).*$/', '$1::', $ip);
        $ip = is_string($masked) && $masked !== '' ? $masked : $ip;
    }

    return hash('sha256', $ua . '|' . $ip);
}

function login_user(int $benutzer_id, string $benutzername): void
{
    start_secure_session();

    session_regenerate_id(true);

    $_SESSION['user_id'] = $benutzer_id;
    $_SESSION['username'] = $benutzername;

    $_SESSION['logged_in_at'] = time();
    $_SESSION['last_activity'] = time();

    unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
}

function logout_user(): void
{
    start_secure_session();

    $_SESSION = [];

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieParams = session_get_cookie_params();

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

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // user_id kann int oder string sein, aber muss positiv sein
    $uid = $_SESSION['user_id'];
    if (is_int($uid)) {
        return $uid > 0;
    }
    if (is_string($uid) && ctype_digit($uid)) {
        return (int)$uid > 0;
    }
    return false;
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

    if (!is_logged_in()) {
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
        if ($name !== '') {
            $out[] = $name;
        }
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
