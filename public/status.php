<?php
declare(strict_types=1);

/**
 * public/status.php
 * Minimaler Health-Check (low info), robust bei DB-Problemen.
 * Erwartet: Apache BasicAuth + IP-Restriktion bereits auf vHost-Ebene.
 */

ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET','HEAD'], true)) {
    http_response_code(405);
    echo "method: not_allowed\n";
    exit;
}

$root = dirname(__DIR__); // /var/www/notenportal
$checks = [];
$okAll = true;

$checks['web'] = ['ok' => true, 'hint' => null];

// Storage: Sessions
$sessionsDir = $root . '/storage/sessions';
$sessionsOk = is_dir($sessionsDir) && is_writable($sessionsDir);
if (!$sessionsOk) $okAll = false;
$checks['sessions'] = [
    'ok' => $sessionsOk,
    'hint' => $sessionsOk ? null : 'Check Rechte/Owner von storage/sessions (www-data muss schreiben können).',
];

// Storage: Logs
$logsDir = $root . '/storage/logs';
$logFile = $logsDir . '/app.log';
$logsOk = is_dir($logsDir) && (is_writable($logsDir) || (file_exists($logFile) && is_writable($logFile)));
if (!$logsOk) $okAll = false;
$checks['logging'] = [
    'ok' => $logsOk,
    'hint' => $logsOk ? null : 'Check Rechte/Owner von storage/logs bzw. app.log (www-data muss schreiben können).',
];

// DB Check (ohne bootstrap, damit es nicht fatal crasht)
$dbOk = false;
$dbHint = null;
try {
    require_once $root . '/app/db.php';

    $pdo = get_pdo();
    $pdo->query('SELECT 1')->fetch();
    $dbOk = true;
} catch (Throwable $e) {
    $msg = $e->getMessage();

    if (str_contains($msg, 'DB config unreadable') || str_contains($msg, 'missing key')) {
        $dbHint = 'DB config Problem: /etc/notenportal/db.ini prüfen (Keys + Leserechte für www-data).';
    } elseif (str_contains($msg, 'No such file') || str_contains($msg, 'unix_socket')) {
        $dbHint = 'Socket/Service Problem: MariaDB läuft? Socket vorhanden? (systemctl status mariadb, /run/mysqld/...).';
    } else {
        $dbHint = 'DB Connection Problem: MariaDB-Status/Logs prüfen (systemctl status mariadb, journalctl -u mariadb).';
    }
}
if (!$dbOk) $okAll = false;
$checks['db'] = ['ok' => $dbOk, 'hint' => $dbHint];

http_response_code($okAll ? 200 : 503);

foreach ($checks as $name => $c) {
    echo $name . ': ' . ($c['ok'] ? "ok" : "fail") . "\n";
}
if (!$okAll) {
    echo "\n";
    foreach ($checks as $name => $c) {
        if (!$c['ok'] && !empty($c['hint'])) {
            echo $name . '_hint: ' . $c['hint'] . "\n";
        }
    }
}
