<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/notes.php';

require_login();
header('Content-Type: text/html; charset=utf-8');

$notes = [];
$error = '';

$isAdmin = !empty($ctx['is_admin']);
$isLearner = !empty($ctx['lernender_id']);
$isBb = !empty($ctx['berufsbildner_id']);

$canManage = ($isAdmin || $isLearner);                 // Edit/Delete/Create
$canSeeLearnerColumn = ($isAdmin || $isBb);            // Lernender-Spalte

// --- POST Actions direkt in der Tabelle ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    require_csrf();

    $action = (string)($_POST['action'] ?? '');
    $noteId = v_int_id($_POST['note_id'] ?? null);

    if (!$noteId) {
        flash_add('error', 'Ungültige Note.');
        redirect('/noten.php');
    }

    // Zugriff auf diese Note muss serverseitig validiert werden
    try {
        $note = fetch_note_detail_for_ctx($pdo, $ctx, $noteId);
        if (!$note) {
            flash_add('error', 'Keine Berechtigung oder Note existiert nicht.');
            redirect('/noten.php');
        }

        if ($action === 'seen') {
            // "gesehen" macht nur Sinn, wenn der User Berufsbildner-Kontext hat
            if (!$isBb) {
                flash_add('error', 'Nur Berufsbildner können als gesehen markieren.');
                redirect('/noten.php');
            }
            mark_note_seen($pdo, $noteId, (int)$ctx['berufsbildner_id']);
            flash_add('success', 'Als gesehen markiert.');
            redirect('/noten.php');
        }

        if ($action === 'comment') {
            $txt = trim((string)($_POST['kommentar_text'] ?? ''));

            if ($txt === '') {
                flash_add('error', 'Kommentar darf nicht leer sein.');
                redirect('/noten.php');
            }
            if (mb_strlen($txt, 'UTF-8') > 2000) {
                flash_add('error', 'Kommentar zu lang (max. 2000 Zeichen).');
                redirect('/noten.php');
            }

            add_note_comment($pdo, $noteId, (int)$ctx['user_id'], $txt);
            flash_add('success', 'Kommentar gespeichert.');
            redirect('/noten.php');
        }

        flash_add('error', 'Ungültige Aktion.');
        redirect('/noten.php');

    } catch (Throwable $e) {
        app_log_exception('Noten list action failed', $e, [
            'action' => $action,
            'note_id' => $noteId,
            'user_id' => $ctx['user_id'] ?? null,
        ]);
        flash_add('error', 'Interner Fehler.');
        redirect('/noten.php');
    }
}

try {
    // letzter Kommentar pro Note
    $lastCommentJoin = '
        LEFT JOIN (
            SELECT note_id, MAX(kommentar_id) AS last_kommentar_id
            FROM noten_kommentare
            GROUP BY note_id
        ) lk ON lk.note_id = n.note_id
        LEFT JOIN noten_kommentare nk ON nk.kommentar_id = lk.last_kommentar_id
        LEFT JOIN benutzer cb ON cb.benutzer_id = nk.autor_benutzer_id
    ';

    if ($isAdmin) {
        // gesehen: irgendein Berufsbildner
        $seenJoin = '
            LEFT JOIN (
                SELECT note_id, MAX(gesehen_am) AS gesehen_am
                FROM noten_gesehen
                GROUP BY note_id
            ) ng ON ng.note_id = n.note_id
        ';

        $sql = '
            SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                   k.name AS kategorie,
                   COALESCE(f.name, m.titel) AS objekt_name,
                   s.bezeichnung AS semester,
                   b.benutzername AS lernender_username,
                   ng.gesehen_am AS gesehen_am,
                   nk.kommentar_text AS last_comment_text,
                   nk.erstellt_am AS last_comment_at,
                   cb.benutzername AS last_comment_author
            FROM noten n
            JOIN kategorien k ON k.kategorie_id = n.kategorie_id
            JOIN semester s ON s.semester_id = n.semester_id
            JOIN lernende l ON l.lernender_id = n.lernender_id
            JOIN benutzer b ON b.benutzer_id = l.benutzer_id
            LEFT JOIN faecher f ON f.fach_id = n.fach_id
            LEFT JOIN module m ON m.modul_id = n.modul_id
            ' . $seenJoin . '
            ' . $lastCommentJoin . '
            WHERE n.geloescht_am IS NULL
            ORDER BY n.pruefungsdatum DESC
            LIMIT 200
        ';

        $notes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } elseif ($isLearner) {
        // gesehen: irgendein Berufsbildner
        $seenJoin = '
            LEFT JOIN (
                SELECT note_id, MAX(gesehen_am) AS gesehen_am
                FROM noten_gesehen
                GROUP BY note_id
            ) ng ON ng.note_id = n.note_id
        ';

        $sql = '
            SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                   k.name AS kategorie,
                   COALESCE(f.name, m.titel) AS objekt_name,
                   s.bezeichnung AS semester,
                   NULL AS lernender_username,
                   ng.gesehen_am AS gesehen_am,
                   nk.kommentar_text AS last_comment_text,
                   nk.erstellt_am AS last_comment_at,
                   cb.benutzername AS last_comment_author
            FROM noten n
            JOIN kategorien k ON k.kategorie_id = n.kategorie_id
            JOIN semester s ON s.semester_id = n.semester_id
            LEFT JOIN faecher f ON f.fach_id = n.fach_id
            LEFT JOIN module m ON m.modul_id = n.modul_id
            ' . $seenJoin . '
            ' . $lastCommentJoin . '
            WHERE n.geloescht_am IS NULL
              AND n.lernender_id = :lid
            ORDER BY n.pruefungsdatum DESC
            LIMIT 200
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':lid' => (int)$ctx['lernender_id']]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } elseif ($isBb) {
        // gesehen: von MIR als Berufsbildner (wichtig: 2 verschiedene Parameter-Namen wegen PDO HY093)
        $seenJoin = '
            LEFT JOIN (
                SELECT note_id, MAX(gesehen_am) AS gesehen_am
                FROM noten_gesehen
                WHERE berufsbildner_id = :bbid_seen
                GROUP BY note_id
            ) ng ON ng.note_id = n.note_id
        ';

        $sql = '
            SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                   k.name AS kategorie,
                   COALESCE(f.name, m.titel) AS objekt_name,
                   s.bezeichnung AS semester,
                   b.benutzername AS lernender_username,
                   ng.gesehen_am AS gesehen_am,
                   nk.kommentar_text AS last_comment_text,
                   nk.erstellt_am AS last_comment_at,
                   cb.benutzername AS last_comment_author
            FROM noten n
            JOIN kategorien k ON k.kategorie_id = n.kategorie_id
            JOIN semester s ON s.semester_id = n.semester_id
            JOIN lernende l ON l.lernender_id = n.lernender_id
            JOIN benutzer b ON b.benutzer_id = l.benutzer_id
            JOIN betreuungen bt ON bt.lernender_id = n.lernender_id
            LEFT JOIN faecher f ON f.fach_id = n.fach_id
            LEFT JOIN module m ON m.modul_id = n.modul_id
            ' . $seenJoin . '
            ' . $lastCommentJoin . '
            WHERE n.geloescht_am IS NULL
              AND bt.berufsbildner_id = :bbid_where
              AND bt.gueltig_von <= CURDATE()
              AND (bt.gueltig_bis IS NULL OR bt.gueltig_bis >= CURDATE())
            ORDER BY n.pruefungsdatum DESC
            LIMIT 200
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':bbid_seen' => (int)$ctx['berufsbildner_id'],
            ':bbid_where' => (int)$ctx['berufsbildner_id'],
        ]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } else {
        $error = 'Kein gültiges Profil (weder Lernender noch Berufsbildner noch Admin).';
    }

} catch (Throwable $e) {
    $error = 'Interner Fehler beim Laden der Noten.';
    app_log_exception('Load notes failed', $e, [
        'user_id' => $ctx['user_id'] ?? null,
    ]);
}

// kleine Helper für Anzeige
function short_text(string $s, int $max = 60): string
{
    $s = trim(preg_replace('/\s+/', ' ', $s));
    if (mb_strlen($s, 'UTF-8') <= $max) return $s;
    return mb_substr($s, 0, $max - 1, 'UTF-8') . '…';
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Noten – Notenportal</title>
</head>
<body>
  <h1>Noten</h1>

  <?= flash_render_html() ?>

  <p>
    Eingeloggt als: <b><?= h((string)$ctx['username']) ?></b>
    (Rollen: <?= h(implode(', ', (array)$ctx['roles'])) ?>)
  </p>

  <p>
    <a href="/dashboard.php">Dashboard</a>
    <?php if ($canManage): ?>
      | <a href="/noten_create.php">+ Neue Note</a>
    <?php endif; ?>
    | <form method="post" action="/logout.php" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit">Logout</button>
      </form>
  </p>

  <?php if ($error !== ''): ?>
    <p style="color:red;font-weight:bold;"><?= h($error) ?></p>
  <?php elseif (!$notes): ?>
    <p>Keine Noten gefunden.</p>
  <?php else: ?>
    <table border="1" cellpadding="6" cellspacing="0">
      <tr>
        <th>Datum</th>
        <th>Kategorie</th>
        <th>Fach/Modul</th>
        <th>Semester</th>
        <th>Note</th>
        <th>Gewichtung %</th>
        <th>Gesehen</th>
        <th>Neuster Kommentar</th>
        <?php if ($canSeeLearnerColumn): ?>
          <th>Lernender</th>
        <?php endif; ?>
        <th>Details</th>
        <th>Aktionen</th>
      </tr>

      <?php foreach ($notes as $n): ?>
        <?php
          $seen = !empty($n['gesehen_am']);
          $seenText = $seen ? 'Ja' : 'Nein';
          $commentText = (string)($n['last_comment_text'] ?? '');
          $commentAt = (string)($n['last_comment_at'] ?? '');
          $commentAuthor = (string)($n['last_comment_author'] ?? '');

          $commentDisplay = '';
          if ($commentText !== '') {
              $commentDisplay = short_text($commentText, 60);
              if ($commentAuthor !== '') $commentDisplay .= ' (' . $commentAuthor . ')';
              if ($commentAt !== '') $commentDisplay .= ' ' . $commentAt;
          }
        ?>
        <tr>
          <td><?= h((string)$n['pruefungsdatum']) ?></td>
          <td><?= h((string)$n['kategorie']) ?></td>
          <td><?= h((string)$n['objekt_name']) ?></td>
          <td><?= h((string)$n['semester']) ?></td>
          <td><?= h((string)$n['note_wert']) ?></td>
          <td><?= h((string)($n['gewichtung_prozent'] ?? '')) ?></td>
          <td><?= h($seenText) ?></td>
          <td><?= $commentDisplay !== '' ? h($commentDisplay) : '—' ?></td>

          <?php if ($canSeeLearnerColumn): ?>
            <td><?= h((string)($n['lernender_username'] ?? '')) ?></td>
          <?php endif; ?>

          <td><a href="/noten_detail.php?note_id=<?= h((string)$n['note_id']) ?>">Öffnen</a></td>

          <td>
            <?php if ($isBb && !$seen): ?>
              <form method="post" action="/noten.php" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="seen">
                <input type="hidden" name="note_id" value="<?= h((string)$n['note_id']) ?>">
                <button type="submit">Gesehen</button>
              </form>
            <?php endif; ?>

            <form method="post" action="/noten.php" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="comment">
              <input type="hidden" name="note_id" value="<?= h((string)$n['note_id']) ?>">
              <input type="text" name="kommentar_text" size="22" maxlength="2000" placeholder="Kommentar…" required>
              <button type="submit">Senden</button>
            </form>

            <?php if ($canManage): ?>
              | <a href="/noten_edit.php?note_id=<?= h((string)$n['note_id']) ?>">Bearbeiten</a>
              <form method="post" action="/noten_delete.php" style="display:inline;" onsubmit="return confirm('Wirklich löschen?');">
                <?= csrf_field() ?>
                <input type="hidden" name="note_id" value="<?= h((string)$n['note_id']) ?>">
                <button type="submit">Löschen</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

    <p>
      Hinweis: “Gesehen” bedeutet bei Berufsbildnern “von mir gesehen”. Bei Admin/Lernenden bedeutet es “mindestens ein Berufsbildner hat es gesehen”.
    </p>
  <?php endif; ?>
</body>
</html>
