<?php
declare(strict_types=1);

/*
  app/flash.php
  Zweck:
  - Flash Messages via Session (für PRG: Post-Redirect-Get)
*/

require_once __DIR__ . '/auth.php';

function flash_add(string $type, string $message): void
{
    start_secure_session();

    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_consume_all(): array
{
    start_secure_session();
    $msgs = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($msgs) ? $msgs : [];
}

function flash_render_html(): string
{
    $msgs = flash_consume_all();
    if (!$msgs) return '';

    $out = '';
    foreach ($msgs as $m) {
        $type = (string)($m['type'] ?? 'info');
        $msg  = (string)($m['message'] ?? '');
        $safe = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $out .= '<p data-flash="' . htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="font-weight:bold;">' . $safe . '</p>' . "\n";
    }
    return $out;
}
