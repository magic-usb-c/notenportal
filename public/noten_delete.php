<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

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
    // Note holen (nur was wir fürs Permission-Check brauchen)
    $stmt = $pdo->prepare(
        'SELECT note_id, lernender_id
         FROM noten
         WHERE note_id = :id AND geloescht_am IS NULL
         LIMIT 1'
    );
    $stmt->execute([':id' => $noteId]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        http_response_code(404);
        exit('Not Found');
    }

    if (!can_manage_note($ctx, (int)$note['lernender_id'])) {
        http_response_code(403);
        exit('Forbidden');
    }

    // Soft-Delete + Audit
    $del = $pdo->prepare(
        'UPDATE noten
         SET geloescht_am = NOW(),
             aktualisiert_von_benutzer_id = :uid
         WHERE note_id = :id AND geloescht_am IS NULL
         LIMIT 1'
    );
    $del->execute([
        ':id'  => $noteId,
        ':uid' => (int)($ctx['user_id'] ?? 0),
    ]);

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
