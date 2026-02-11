<?php
declare(strict_types=1);

/*
  app/db.php
  Zweck:
  - DB-Config aus /etc/notenportal/db.ini lesen
  - PDO Verbindung via Unix-Socket aufbauen
  - robustes Error-Handling (keine Secrets im Output)
*/

const NP_DB_INI = '/etc/notenportal/db.ini';

function load_db_config(): array
{
    if (!is_readable(NP_DB_INI)) {
        throw new RuntimeException('DB config unreadable');
    }

    $ini = parse_ini_file(NP_DB_INI, false, INI_SCANNER_TYPED);
    if (!is_array($ini)) {
        throw new RuntimeException('DB config parse failed');
    }

    $required = ['DB_NAME', 'DB_USER', 'DB_PASS', 'DB_SOCKET'];
    foreach ($required as $k) {
        if (!array_key_exists($k, $ini)) {
            throw new RuntimeException('DB config missing key: ' . $k);
        }
    }

    return $ini;
}

function get_pdo(): PDO
{
    $cfg = load_db_config();

    // Verbindung via Unix-Socket (kein TCP Port nötig)
    $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $cfg['DB_SOCKET'], $cfg['DB_NAME']);

    $pdo = new PDO($dsn, (string)$cfg['DB_USER'], (string)$cfg['DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

/**
 * Optional: kleiner Health-Check
 */
function db_health(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1')->fetch();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
