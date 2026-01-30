<?php
declare(strict_types=1);

/*
  app/log.php
  Zweck:
  - App-Logs unabhängig von Apache/PHP error log
  - schreibt in storage/logs/app.log
  - low-risk: wenn Schreiben fehlschlägt, nicht fatal werden
*/

function app_log(string $level, string $message, array $context = []): void
{
    $logFile = __DIR__ . '/../storage/logs/app.log';

    $ts = (new DateTimeImmutable('now'))->format('c');
    $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    $line = sprintf("[%s] %s: %s%s\n", $ts, strtoupper($level), $message, $ctx);

    try {
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // Fallback ins PHP error log, aber ohne Context-Spam
        error_log("notenportal app_log failed: " . $message);
    }
}

function app_log_exception(string $message, Throwable $e, array $context = []): void
{
    $context = array_merge($context, [
        'exception' => get_class($e),
        // message kann sensitive sein; trotzdem hilfreich intern
        'error' => $e->getMessage(),
    ]);
    app_log('error', $message, $context);
}
