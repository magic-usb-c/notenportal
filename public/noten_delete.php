<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/notes.php';

require_login();

if (!can_manage_notes($ctx)) {
    http_response_code(403);
    exit('Forbidden');
}

require_post();
require_csrf();

$noteId = v_int_id($_POST['note_id'] ?? null);
if (!$noteId) {
    http_response_code(400);
    exit('Bad Request');
}

try {
    $note = fetch_note($pdo, $noteId);
    if (!$note) {
        http_response_code(404);
        exit('Not Found');
    }

    if (!can_manage_note($ctx, (int)$note['lernender_id'])) {
        http_response_code(403);
        exit('Forbidden');
    }

    soft_delete_note($pdo, $noteId);

    app_log('info', 'Note deleted (soft)', [
        'note_id' => $noteId,
        'by_user_id' => $ctx['user_id'] ?? null,
    ]);

    flash_add('success', 'Note gelöscht.');
    redirect('/noten.php');
} catch (Throwable $e) {
    app_log_exception('Delete note failed', $e, [
        'note_id' => $noteId,
        'by_user_id' => $ctx['user_id'] ?? null,
    ]);
    flash_add('error', 'Interner Fehler beim Löschen.');
    redirect('/noten.php', 303);
}
