<?php
declare(strict_types=1);

/*
  app/db.php
  Zweck:
  - DB-Config aus /etc/notenportal/db.ini lesen
  - PDO Verbindung aufbauen (Unix-Socket ODER TCP)
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

    // Required minimal
    foreach (['DB_NAME', 'DB_USER', 'DB_PASS'] as $k) {
        if (!array_key_exists($k, $ini)) {
            throw new RuntimeException('DB config missing key: ' . $k);
        }
    }

    // Optional with defaults
    $ini['DB_CHARSET'] = isset($ini['DB_CHARSET']) && is_string($ini['DB_CHARSET']) && $ini['DB_CHARSET'] !== ''
        ? $ini['DB_CHARSET']
        : 'utf8mb4';

    // Either DB_SOCKET or DB_HOST (TCP)
    if (!isset($ini['DB_SOCKET']) && !isset($ini['DB_HOST'])) {
        // Default: socket typical on Debian/MariaDB
        $ini['DB_SOCKET'] = '/run/mysqld/mysqld.sock';
    }

    if (isset($ini['DB_HOST'])) {
        $ini['DB_PORT'] = isset($ini['DB_PORT']) ? (int)$ini['DB_PORT'] : 3306;
        if ($ini['DB_PORT'] <= 0 || $ini['DB_PORT'] > 65535) {
            throw new RuntimeException('DB config invalid DB_PORT');
        }
    }

    return $ini;
}

function get_pdo(): PDO
{
    $cfg = load_db_config();

    $dbName   = (string)$cfg['DB_NAME'];
    $dbUser   = (string)$cfg['DB_USER'];
    $dbPass   = (string)$cfg['DB_PASS'];
    $charset  = (string)$cfg['DB_CHARSET'];

    // DSN: prefer socket if present and non-empty, else TCP
    if (!empty($cfg['DB_SOCKET']) && is_string($cfg['DB_SOCKET'])) {
        $socket = $cfg['DB_SOCKET'];
        $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $dbName, $charset);
    } else {
        $host = (string)($cfg['DB_HOST'] ?? '127.0.0.1');
        $port = (int)($cfg['DB_PORT'] ?? 3306);
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    // Optional: Connection timeout (only works for TCP)
    if (defined('PDO::ATTR_TIMEOUT')) {
        $options[PDO::ATTR_TIMEOUT] = 5;
    }

    return new PDO($dsn, $dbUser, $dbPass, $options);
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
