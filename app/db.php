<?php
declare(strict_types=1);

/*
  app/db.php
  Ziel:
  - EIN zentraler Ort für DB-Verbindung (keine Kopien in login/dashboard/etc.)
  - DB-Credentials kommen aus /etc/notenportal/db.ini (nicht im Webroot)
  - Verbindung über Unix Socket (lokal, kein TCP-Port nach aussen)
*/

function db_config_path(): string
{
    return '/etc/notenportal/db.ini';
}

/**
 * Liefert das DB-Config-Array oder wirft eine Exception mit klarer Fehlermeldung.
 */
function load_db_config(): array
{
    $path = db_config_path();

    $cfg = parse_ini_file($path, false, INI_SCANNER_RAW);
    if ($cfg === false) {
        throw new RuntimeException("DB config unreadable: {$path}");
    }

    foreach (['DB_NAME','DB_USER','DB_PASS','DB_SOCKET'] as $k) {
        if (!isset($cfg[$k]) || $cfg[$k] === '') {
            throw new RuntimeException("DB config missing key: {$k}");
        }
    }

    return $cfg;
}

/**
 * Zentrale PDO-Verbindung (benutzen alle Seiten).
 */
function get_pdo(): PDO
{
    $cfg = load_db_config();

    $dsn = sprintf(
        'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
        $cfg['DB_SOCKET'],
        $cfg['DB_NAME']
    );

    return new PDO($dsn, $cfg['DB_USER'], $cfg['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/**
 * Optional: DB-Healthcheck/Debug (für Testseiten).
 * Gibt KEIN Passwort zurück.
 */
function db_health(): array
{
    $info = [
        'config_path' => db_config_path(),
        'config_readable' => is_readable(db_config_path()),
        'php_version' => PHP_VERSION,
    ];

    try {
        $cfg = load_db_config();

        $info['db_name'] = $cfg['DB_NAME'];
        $info['db_user'] = $cfg['DB_USER'];
        $info['db_socket'] = $cfg['DB_SOCKET'];
        $info['socket_exists'] = file_exists($cfg['DB_SOCKET']);

        $pdo = get_pdo();
        $row = $pdo->query("SELECT NOW() AS ts, CURRENT_USER() AS cu")->fetch();

        $info['db_now'] = $row['ts'] ?? null;
        $info['db_current_user'] = $row['cu'] ?? null;
        $info['db_server_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        return ['ok' => true, 'info' => $info];
    } catch (Throwable $e) {
        $info['exception'] = get_class($e);
        return ['ok' => false, 'info' => $info, 'error' => $e->getMessage()];
    }
}
