<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/notes.php';

require_login();
header('Content-Type: text/html; charset=utf-8');

$isAdmin   = !empty($ctx['is_admin']);
$isLearner = !empty($ctx['lernender_id']);
$isBb      = !empty($ctx['berufsbildner_id']);

$canManage = ($isAdmin || $isLearner);        // erstellen/bearbeiten/löschen
$canSeeLearnerColumn = ($isAdmin || $isBb);   // Lernender-Spalte sehen

$error = '';
$notes = [];
$opt = [];

function short_text(string $s, int $max = 60): string
{
    $s = trim((string)preg_replace('/\s+/', ' ', $s));
    if (mb_strlen($s, 'UTF-8') <= $max) return $s;
    return mb_substr($s, 0, $max - 1, 'UTF-8') . '…';
}

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

    try {
        $note = fetch_note_detail_for_ctx($pdo, $ctx, $noteId);
        if (!$note) {
            flash_add('error', 'Keine Berechtigung oder Note existiert nicht.');
            redirect('/noten.php');
        }

        if ($action === 'seen') {
            if (empty($ctx['berufsbildner_id'])) {
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

// --- GET Filter ---
// Neues Schema: Modul-Filter über modul_belegung_id
$filters = [
    'lernender_id'       => ($isAdmin || $isBb) ? v_int_id($_GET['lernender_id'] ?? null) : null,
    'kategorie_id'       => v_int_id($_GET['kategorie_id'] ?? null),
    'semester_id'        => v_int_id($_GET['semester_id'] ?? null),
    'fach_id'            => v_int_id($_GET['fach_id'] ?? null),
    'modul_belegung_id'  => v_int_id($_GET['modul_belegung_id'] ?? null),

    // Backward-Compat: falls irgendwo noch modul_id kommt (filtert in notes.php über JOIN module)
    'modul_id'           => v_int_id($_GET['modul_id'] ?? null),

    'seen'               => (string)($_GET['seen'] ?? 'all'),
];

if (!in_array($filters['seen'], ['all', 'yes', 'no'], true)) {
    $filters['seen'] = 'all';
}

$sort = (string)($_GET['sort'] ?? 'date_desc');

try {
    $opt = load_note_filter_options($pdo, $ctx);              // liefert modul_belegungen (nicht module)
    $notes = fetch_notes_list_for_ctx_filtered($pdo, $ctx, $filters, $sort);
} catch (Throwable $e) {
    $error = 'Interner Fehler beim Laden der Noten.';
    app_log_exception('Load notes failed', $e, ['user_id' => $ctx['user_id'] ?? null]);
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
    <?php if ($canManage): ?> | <a href="/noten_create.php">+ Neue Note</a><?php endif; ?>
    | <form method="post" action="/logout.php" style="display:inline;">
        <?= csrf_field() ?><button type="submit">Logout</button>
      </form>
  </p>

  <h2>Filter</h2>
  <form method="get" action="/noten.php">
    <?php if ($isAdmin || $isBb): ?>
      <label>Lernender:
        <select name="lernender_id">
          <option value="">Alle</option>
          <?php foreach (($opt['lernende'] ?? []) as $l): ?>
            <option value="<?= h((string)$l['lernender_id']) ?>" <?= ((string)$filters['lernender_id'] === (string)$l['lernender_id']) ? 'selected' : '' ?>>
              <?= h((string)$l['benutzername']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      &nbsp;
    <?php endif; ?>

    <label>Kategorie:
      <select name="kategorie_id">
        <option value="">Alle</option>
        <?php foreach (($opt['kategorien'] ?? []) as $k): ?>
          <option value="<?= h((string)$k['kategorie_id']) ?>" <?= ((string)$filters['kategorie_id'] === (string)$k['kategorie_id']) ? 'selected' : '' ?>>
            <?= h((string)$k['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    &nbsp;

    <label>Semester:
      <select name="semester_id">
        <option value="">Alle</option>
        <?php foreach (($opt['semester'] ?? []) as $s): ?>
          <option value="<?= h((string)$s['semester_id']) ?>" <?= ((string)$filters['semester_id'] === (string)$s['semester_id']) ? 'selected' : '' ?>>
            <?= h((string)$s['bezeichnung']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    &nbsp;

    <label>Fach:
      <select name="fach_id">
        <option value="">Alle</option>
        <?php foreach (($opt['faecher'] ?? []) as $f): ?>
          <option value="<?= h((string)$f['fach_id']) ?>" <?= ((string)$filters['fach_id'] === (string)$f['fach_id']) ? 'selected' : '' ?>>
            <?= h((string)$f['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    &nbsp;

    <label>Modul-Belegung:
      <select name="modul_belegung_id">
        <option value="">Alle</option>
        <?php foreach (($opt['modul_belegungen'] ?? []) as $mb): ?>
          <?php
            // notes.php liefert meist "label" direkt; fallback bauen wir trotzdem
            $label = (string)($mb['label'] ?? '');
            if ($label === '') {
                $label = trim((string)($mb['modul_nummer'] ?? '') . ' ' . (string)($mb['titel'] ?? ''));
            }
          ?>
          <option value="<?= h((string)$mb['modul_belegung_id']) ?>" <?= ((string)$filters['modul_belegung_id'] === (string)$mb['modul_belegung_id']) ? 'selected' : '' ?>>
            <?= h($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    &nbsp;

    <label>Gesehen:
      <select name="seen">
        <option value="all" <?= $filters['seen'] === 'all' ? 'selected' : '' ?>>Alle</option>
        <option value="yes" <?= $filters['seen'] === 'yes' ? 'selected' : '' ?>>Ja</option>
        <option value="no"  <?= $filters['seen'] === 'no'  ? 'selected' : '' ?>>Nein</option>
      </select>
    </label>
    &nbsp;

    <label>Sortierung:
      <select name="sort">
        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Datum (neu → alt)</option>
        <option value="date_asc"  <?= $sort === 'date_asc'  ? 'selected' : '' ?>>Datum (alt → neu)</option>
        <option value="note_desc" <?= $sort === 'note_desc' ? 'selected' : '' ?>>Note (hoch → tief)</option>
        <option value="note_asc"  <?= $sort === 'note_asc'  ? 'selected' : '' ?>>Note (tief → hoch)</option>
        <option value="obj_asc"   <?= $sort === 'obj_asc'   ? 'selected' : '' ?>>Fach/Modul (A → Z)</option>
        <option value="obj_desc"  <?= $sort === 'obj_desc'  ? 'selected' : '' ?>>Fach/Modul (Z → A)</option>
      </select>
    </label>

    <button type="submit">Anwenden</button>
    <a href="/noten.php">Reset</a>
  </form>

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
        <?php if ($canSeeLearnerColumn): ?><th>Lernender</th><?php endif; ?>
        <th>Details</th>
        <th>Aktionen</th>
      </tr>

      <?php foreach ($notes as $n): ?>
        <?php
          $seen = !empty($n['gesehen_am']);
          $seenText = $seen ? 'Ja' : 'Nein';

          $commentText   = (string)($n['last_comment_text'] ?? '');
          $commentAt     = (string)($n['last_comment_at'] ?? '');
          $commentAuthor = (string)($n['last_comment_author'] ?? '');

          $commentDisplay = '—';
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
          <td><?= h($commentDisplay) ?></td>

          <?php if ($canSeeLearnerColumn): ?>
            <td><?= h((string)($n['lernender_username'] ?? '')) ?></td>
          <?php endif; ?>

          <td><a href="/noten_detail.php?note_id=<?= h((string)$n['note_id']) ?>">Öffnen</a></td>

          <td>
            <?php if (!empty($ctx['berufsbildner_id']) && !$seen): ?>
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

  <?php endif; ?>
</body>
</html>
