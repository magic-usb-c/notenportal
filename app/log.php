<?php
declare(strict_types=1);

/*
  Zweck:
  - einfache App-Logs unabhängig von Apache-Logs
  - schreibt in /var/www/notenportal/storage/logs/app.log
  - für Debug/Tracing deiner eigenen Logik (Login, DB-Aktionen, Errors)

  Format:
  - ISO-Zeitstempel, Level, Message, optional Context als JSON
*/

function app_log(string $level, string $message, array $context = []): void
{
    $logFile = __DIR__ . '/../storage/logs/app.log';

    $ts = (new DateTimeImmutable('now'))->format('c'); // ISO 8601
    $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

    $line = sprintf("[%s] %s: %s%s\n", $ts, strtoupper($level), $message, $ctx);

    // LOCK_EX verhindert, dass parallele Requests Zeilen ineinander schreiben
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
