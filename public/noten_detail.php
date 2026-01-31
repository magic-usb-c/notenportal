<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/notes.php';

require_login();
header('Content-Type: text/html; charset=utf-8');

$noteId = v_int_id($_GET['note_id'] ?? null);
if (!$noteId) {
    http_response_code(400);
    exit('Bad Request');
}

$errors = [];

try {
    $note = fetch_note_detail_for_ctx($pdo, $ctx, $noteId);
    if (!$note) {
        http_response_code(404);
        exit('Not Found');
    }
} catch (Throwable $e) {
    app_log_exception('Load note detail failed', $e, ['note_id' => $noteId, 'user_id' => $ctx['user_id'] ?? null]);
    http_response_code(500);
    exit('Internal Server Error');
}

/**
 * Seen-Status Liste (für alle Rollen sichtbar, sobald sie Zugriff auf die Note haben).
 */
$seenList = [];
try {
    $stmt = $pdo->prepare(
        'SELECT ng.berufsbildner_id, ng.gesehen_am, b.benutzername AS berufsbildner_username
         FROM noten_gesehen ng
         JOIN berufsbildner bb ON bb.berufsbildner_id = ng.berufsbildner_id
         JOIN benutzer b ON b.benutzer_id = bb.benutzer_id
         WHERE ng.note_id = :nid
         ORDER BY ng.gesehen_am DESC'
    );
    $stmt->execute([':nid' => $noteId]);
    $seenList = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    app_log_exception('Load note seen list failed', $e, ['note_id' => $noteId, 'user_id' => $ctx['user_id'] ?? null]);
    $seenList = [];
}

$isBb = !empty($ctx['berufsbildner_id']);
$myBbId = $isBb ? (int)$ctx['berufsbildner_id'] : 0;

$alreadySeenForMe = false;
if ($isBb) {
    foreach ($seenList as $s) {
        if ((int)$s['berufsbildner_id'] === $myBbId) {
            $alreadySeenForMe = true;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    require_csrf();

    $action = (string)($_POST['action'] ?? '');

    try {
        // Permission erneut erzwingen (falls jemand mit note_id herumspielt)
        $note = fetch_note_detail_for_ctx($pdo, $ctx, $noteId);
        if (!$note) {
            http_response_code(404);
            exit('Not Found');
        }

        if ($action === 'seen') {
            if (!$isBb) {
                http_response_code(403);
                exit('Forbidden');
            }
            mark_note_seen($pdo, $noteId, $myBbId);
            flash_add('success', 'Als gesehen markiert.');
            redirect('/noten_detail.php?note_id=' . $noteId);
        }

        if ($action === 'comment') {
            $txt = trim((string)($_POST['kommentar_text'] ?? ''));

            if ($txt === '') {
                $errors['kommentar_text'] = 'Kommentar darf nicht leer sein.';
            } elseif (mb_strlen($txt, 'UTF-8') > 2000) {
                $errors['kommentar_text'] = 'Kommentar ist zu lang (max. 2000 Zeichen).';
            } else {
                add_note_comment($pdo, $noteId, (int)$ctx['user_id'], $txt);
                flash_add('success', 'Kommentar gespeichert.');
                redirect('/noten_detail.php?note_id=' . $noteId);
            }
        }

        if ($action !== 'seen' && $action !== 'comment') {
            http_response_code(400);
            exit('Bad Request');
        }
    } catch (Throwable $e) {
        app_log_exception('Note detail action failed', $e, [
            'note_id' => $noteId,
            'user_id' => $ctx['user_id'] ?? null,
            'action'  => $action,
        ]);
        $errors['form'] = 'Interner Fehler.';
    }
}

try {
    $comments = fetch_note_comments($pdo, $noteId);
} catch (Throwable $e) {
    app_log_exception('Load note comments failed', $e, ['note_id' => $noteId, 'user_id' => $ctx['user_id'] ?? null]);
    $comments = [];
    $errors['form'] = 'Interner Fehler beim Laden der Kommentare.';
}

$objekt = (string)($note['fach_name'] ?: ($note['modul_titel'] ?: ''));
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Note Details – Notenportal</title>
</head>
<body>
  <h1>Note Details</h1>

  <?= flash_render_html() ?>

  <p>
    <a href="/noten.php">Zurück</a> | <a href="/dashboard.php">Dashboard</a>
    | <form method="post" action="/logout.php" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit">Logout</button>
      </form>
  </p>

  <?php if (!empty($errors['form'])): ?>
    <p style="color:red;font-weight:bold;"><?= h((string)$errors['form']) ?></p>
  <?php endif; ?>

  <h2>Info</h2>
  <ul>
    <li><b>Datum:</b> <?= h((string)$note['pruefungsdatum']) ?></li>
    <li><b>Kategorie:</b> <?= h((string)$note['kategorie']) ?></li>
    <li><b>Fach/Modul:</b> <?= h($objekt) ?></li>
    <li><b>Semester:</b> <?= h((string)$note['semester']) ?></li>
    <li><b>Note:</b> <?= h((string)$note['note_wert']) ?></li>
    <li><b>Gewichtung %:</b> <?= h((string)($note['gewichtung_prozent'] ?? '')) ?></li>

    <?php if (!empty($note['lernender_username'])): ?>
      <li><b>Lernender:</b> <?= h((string)$note['lernender_username']) ?></li>
    <?php endif; ?>
  </ul>

  <?php if ($isBb && !$alreadySeenForMe): ?>
    <form method="post" action="/noten_detail.php?note_id=<?= h((string)$noteId) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="seen">
      <button type="submit">Als gesehen markieren</button>
    </form>
  <?php endif; ?>

  <h2>Gesehen</h2>
  <?php if (!$seenList): ?>
    <p>Noch nicht als gesehen markiert.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($seenList as $s): ?>
        <li>
          <b><?= h((string)$s['berufsbildner_username']) ?></b>:
          <?= h((string)$s['gesehen_am']) ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h2>Kommentare</h2>
  <?php if (!$comments): ?>
    <p>Keine Kommentare.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($comments as $c): ?>
        <li>
          <b><?= h((string)$c['autor_username']) ?></b>
          (<?= h((string)$c['erstellt_am']) ?>):<br>
          <?= nl2br(h((string)$c['kommentar_text'])) ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h3>Kommentar hinzufügen</h3>
  <form method="post" action="/noten_detail.php?note_id=<?= h((string)$noteId) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="comment">
    <textarea name="kommentar_text" rows="4" cols="70" required></textarea><br>
    <?php if (!empty($errors['kommentar_text'])): ?>
      <small style="color:red;"><?= h((string)$errors['kommentar_text']) ?></small><br>
    <?php endif; ?>
    <button type="submit">Speichern</button>
  </form>
</body>
</html>
