<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/notes.php';

require_login();

if (!can_manage_notes($ctx)) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/html; charset=utf-8');

$noteId = v_int_id($_GET['note_id'] ?? null);
if (!$noteId) {
    http_response_code(400);
    exit('Bad Request');
}

$isAdmin = !empty($ctx['is_admin']);

function load_note_edit_options(PDO $pdo, bool $isAdmin, int $lernenderId): array
{
    $opt = [
        'kategorien' => [],
        'semester' => [],
        'faecher' => [],
        'lernende' => [],
        'modul_belegungen' => [],
        'gruppen' => [],
        // defensive: falls im Template noch irgendwo opt['module'] vorkommt
        'module' => [],
    ];

    $opt['kategorien'] = $pdo->query('SELECT kategorie_id, name FROM kategorien ORDER BY name')
        ->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $opt['semester'] = $pdo->query('SELECT semester_id, bezeichnung FROM semester ORDER BY semester_id')
        ->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $opt['faecher'] = $pdo->query('SELECT fach_id, name FROM faecher ORDER BY name')
        ->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($isAdmin) {
        $opt['lernende'] = $pdo->query(
            'SELECT l.lernender_id, b.benutzername
             FROM lernende l
             JOIN benutzer b ON b.benutzer_id = l.benutzer_id
             WHERE l.geloescht_am IS NULL
             ORDER BY b.benutzername'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Modul-Belegungen nur für den (ausgewählten) Lernenden
    $stmt = $pdo->prepare(
        'SELECT mb.modul_belegung_id, mb.modul_id, m.modul_nummer, m.titel
         FROM modul_belegungen mb
         JOIN module m ON m.modul_id = mb.modul_id
         WHERE mb.lernender_id = :lid
         ORDER BY m.modul_nummer, m.titel, mb.modul_belegung_id'
    );
    $stmt->execute([':lid' => $lernenderId]);
    $opt['modul_belegungen'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Gruppen für diese Modul-Belegungen
    // FIX: keine Spalten 'name' / 'ziel_summe_punkte' selektieren
    $stmt = $pdo->prepare(
        'SELECT g.gruppe_id, g.modul_belegung_id, g.bezeichnung
         FROM modul_note_gruppen g
         JOIN modul_belegungen mb ON mb.modul_belegung_id = g.modul_belegung_id
         WHERE mb.lernender_id = :lid
         ORDER BY g.modul_belegung_id, g.gruppe_id'
    );
    $stmt->execute([':lid' => $lernenderId]);
    $opt['gruppen'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return $opt;
}

$note = null;
$errors = [];
$values = [];
$opt = [];

try {
    $stmt = $pdo->prepare(
        'SELECT note_id, lernender_id, kategorie_id, semester_id, fach_id, modul_belegung_id, gruppe_id,
                titel, pruefungsdatum, note_wert, gewichtung_prozent
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

    $values = [
        'lernender_id'       => (string)($note['lernender_id'] ?? ''),
        'kategorie_id'       => (string)($note['kategorie_id'] ?? ''),
        'semester_id'        => (string)($note['semester_id'] ?? ''),
        'fach_id'            => (string)($note['fach_id'] ?? ''),
        'modul_belegung_id'  => (string)($note['modul_belegung_id'] ?? ''),
        'gruppe_id'          => (string)($note['gruppe_id'] ?? ''),
        'titel'              => (string)($note['titel'] ?? ''),
        'pruefungsdatum'     => (string)($note['pruefungsdatum'] ?? ''),
        'note_wert'          => (string)($note['note_wert'] ?? ''),
        'gewichtung_prozent' => (string)($note['gewichtung_prozent'] ?? '100'),
    ];

    $opt = load_note_edit_options($pdo, $isAdmin, (int)$note['lernender_id']);
} catch (Throwable $e) {
    app_log_exception('Load note edit failed', $e, ['note_id' => $noteId, 'user_id' => $ctx['user_id'] ?? null]);
    http_response_code(500);
    exit('Internal Server Error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    require_csrf();

    foreach ($values as $k => $v) {
        $values[$k] = trim((string)($_POST[$k] ?? $v));
    }

    $selectedLernenderId = $isAdmin
        ? (v_int_id($_POST['lernender_id'] ?? null) ?? (int)$note['lernender_id'])
        : (int)$note['lernender_id'];

    try {
        $opt = load_note_edit_options($pdo, $isAdmin, $selectedLernenderId);
    } catch (Throwable $e) {
        app_log_exception('Reload note edit options failed', $e, ['note_id' => $noteId, 'user_id' => $ctx['user_id'] ?? null]);
        $errors['form'] = 'Interner Fehler beim Laden der Auswahlwerte.';
    }

    $data = [];

    if ($isAdmin) {
        $data['lernender_id'] = v_int_id($_POST['lernender_id'] ?? null);
        if (!$data['lernender_id']) $errors['lernender_id'] = 'Lernender fehlt.';
    } else {
        $data['lernender_id'] = (int)$note['lernender_id'];
        if (isset($_POST['lernender_id']) && (int)$_POST['lernender_id'] !== (int)$note['lernender_id']) {
            $errors['lernender_id'] = 'Nicht erlaubt.';
        }
    }

    $data['kategorie_id'] = v_int_id($_POST['kategorie_id'] ?? null);
    if (!$data['kategorie_id']) $errors['kategorie_id'] = 'Kategorie fehlt.';

    $data['semester_id'] = v_int_id($_POST['semester_id'] ?? null);
    if (!$data['semester_id']) $errors['semester_id'] = 'Semester fehlt.';

    $data['pruefungsdatum'] = v_date_ymd($_POST['pruefungsdatum'] ?? null);
    if (!$data['pruefungsdatum']) $errors['pruefungsdatum'] = 'Datum ist ungültig.';

    $data['note_wert'] = v_grade($_POST['note_wert'] ?? null);
    if (!$data['note_wert']) $errors['note_wert'] = 'Note muss zwischen 1.0 und 6.0 liegen.';

    $data['gewichtung_prozent'] = v_weight_percent($_POST['gewichtung_prozent'] ?? null);
    if ($data['gewichtung_prozent'] === null) $data['gewichtung_prozent'] = 100;

    $data['titel'] = trim((string)($_POST['titel'] ?? ''));
    if ($data['titel'] === '') $data['titel'] = null;
    elseif (mb_strlen($data['titel'], 'UTF-8') > 150) $errors['titel'] = 'Titel zu lang (max. 150 Zeichen).';

    $data['fach_id'] = v_int_id($_POST['fach_id'] ?? null);
    $data['modul_belegung_id'] = v_int_id($_POST['modul_belegung_id'] ?? null);
    $data['gruppe_id'] = v_int_id($_POST['gruppe_id'] ?? null);

    if (!$data['fach_id'] && !$data['modul_belegung_id']) $errors['objekt'] = 'Wähle ein Fach oder eine Modul-Belegung.';
    if ($data['fach_id'] && $data['modul_belegung_id']) $errors['objekt'] = 'Wähle entweder Fach oder Modul-Belegung, nicht beides.';

    if ($data['fach_id']) $data['gruppe_id'] = null;

    if (!$errors && $data['modul_belegung_id']) {
        try {
            $stmt = $pdo->prepare(
                'SELECT lernender_id
                 FROM modul_belegungen
                 WHERE modul_belegung_id = :mbid
                 LIMIT 1'
            );
            $stmt->execute([':mbid' => (int)$data['modul_belegung_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $errors['objekt'] = 'Ungültige Modul-Belegung.';
            } elseif ((int)$row['lernender_id'] !== (int)$data['lernender_id']) {
                $errors['objekt'] = 'Diese Modul-Belegung gehört nicht zu diesem Lernenden.';
            }

            if (!$errors && $data['gruppe_id']) {
                $stmt = $pdo->prepare(
                    'SELECT gruppe_id
                     FROM modul_note_gruppen
                     WHERE gruppe_id = :gid AND modul_belegung_id = :mbid
                     LIMIT 1'
                );
                $stmt->execute([
                    ':gid' => (int)$data['gruppe_id'],
                    ':mbid' => (int)$data['modul_belegung_id'],
                ]);
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    $errors['gruppe_id'] = 'Ungültige Gruppe für diese Modul-Belegung.';
                }
            }
        } catch (Throwable $e) {
            app_log_exception('Validate modul_belegung/gruppe failed (edit)', $e, [
                'user_id' => $ctx['user_id'] ?? null,
                'note_id' => $noteId,
                'modul_belegung_id' => $data['modul_belegung_id'],
                'gruppe_id' => $data['gruppe_id'],
            ]);
            $errors['form'] = 'Interner Fehler bei der Validierung.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE noten
                 SET lernender_id = :lernender_id,
                     kategorie_id = :kategorie_id,
                     semester_id = :semester_id,
                     fach_id = :fach_id,
                     modul_belegung_id = :modul_belegung_id,
                     gruppe_id = :gruppe_id,
                     titel = :titel,
                     pruefungsdatum = :pruefungsdatum,
                     note_wert = :note_wert,
                     gewichtung_prozent = :gewichtung_prozent,
                     aktualisiert_von_benutzer_id = :aktualisiert_von
                 WHERE note_id = :id AND geloescht_am IS NULL
                 LIMIT 1'
            );

            $stmt->execute([
                ':id' => $noteId,
                ':lernender_id' => (int)$data['lernender_id'],
                ':kategorie_id' => (int)$data['kategorie_id'],
                ':semester_id' => (int)$data['semester_id'],
                ':fach_id' => $data['fach_id'] ? (int)$data['fach_id'] : null,
                ':modul_belegung_id' => $data['modul_belegung_id'] ? (int)$data['modul_belegung_id'] : null,
                ':gruppe_id' => $data['gruppe_id'] ? (int)$data['gruppe_id'] : null,
                ':titel' => $data['titel'],
                ':pruefungsdatum' => (string)$data['pruefungsdatum'],
                ':note_wert' => (string)$data['note_wert'],
                ':gewichtung_prozent' => (string)$data['gewichtung_prozent'],
                ':aktualisiert_von' => (int)($ctx['user_id'] ?? 0),
            ]);

            app_log('info', 'Note updated', [
                'note_id' => $noteId,
                'by_user_id' => $ctx['user_id'] ?? null,
            ]);

            flash_add('success', 'Note gespeichert.');
            redirect('/noten.php');
        } catch (Throwable $e) {
            app_log_exception('Update note failed', $e, ['note_id' => $noteId, 'by_user_id' => $ctx['user_id'] ?? null]);
            $errors['form'] = 'Interner Fehler beim Speichern.';
        }
    }
}

?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Note bearbeiten – Notenportal</title>
</head>
<body>
  <h1>Note bearbeiten</h1>

  <?= flash_render_html() ?>

  <p><a href="/noten.php">Zurück</a></p>

  <?php if (!empty($errors['form'])): ?>
    <p style="color:red;font-weight:bold;"><?= h((string)$errors['form']) ?></p>
  <?php endif; ?>

  <form method="post" action="/noten_edit.php?note_id=<?= h((string)$noteId) ?>">
    <?= csrf_field() ?>

    <?php if ($isAdmin): ?>
      <label>
        Lernender<br>
        <select name="lernender_id" required>
          <option value="">-- wählen --</option>
          <?php foreach (($opt['lernende'] ?? []) as $l): ?>
            <?php $id = (string)$l['lernender_id']; ?>
            <option value="<?= h($id) ?>" <?= ($values['lernender_id'] === $id ? 'selected' : '') ?>>
              <?= h((string)$l['benutzername']) ?> (ID <?= h($id) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php if (!empty($errors['lernender_id'])): ?><br><small style="color:red;"><?= h((string)$errors['lernender_id']) ?></small><?php endif; ?>
      <br><br>
    <?php endif; ?>

    <label>
      Kategorie<br>
      <select name="kategorie_id" required>
        <option value="">-- wählen --</option>
        <?php foreach (($opt['kategorien'] ?? []) as $k): ?>
          <?php $id = (string)$k['kategorie_id']; ?>
          <option value="<?= h($id) ?>" <?= ($values['kategorie_id'] === $id ? 'selected' : '') ?>>
            <?= h((string)$k['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if (!empty($errors['kategorie_id'])): ?><br><small style="color:red;"><?= h((string)$errors['kategorie_id']) ?></small><?php endif; ?>
    <br><br>

    <label>
      Semester<br>
      <select name="semester_id" required>
        <option value="">-- wählen --</option>
        <?php foreach (($opt['semester'] ?? []) as $s): ?>
          <?php $id = (string)$s['semester_id']; ?>
          <option value="<?= h($id) ?>" <?= ($values['semester_id'] === $id ? 'selected' : '') ?>>
            <?= h((string)$s['bezeichnung']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if (!empty($errors['semester_id'])): ?><br><small style="color:red;"><?= h((string)$errors['semester_id']) ?></small><?php endif; ?>
    <br><br>

    <label>
      Prüfungsdatum<br>
      <input type="date" name="pruefungsdatum" value="<?= h((string)$values['pruefungsdatum']) ?>" required>
    </label>
    <?php if (!empty($errors['pruefungsdatum'])): ?><br><small style="color:red;"><?= h((string)$errors['pruefungsdatum']) ?></small><?php endif; ?>
    <br><br>

    <label>
      Note (1.0–6.0)<br>
      <input type="text" name="note_wert" value="<?= h((string)$values['note_wert']) ?>" required>
    </label>
    <?php if (!empty($errors['note_wert'])): ?><br><small style="color:red;"><?= h((string)$errors['note_wert']) ?></small><?php endif; ?>
    <br><br>

    <label>
      Gewichtung %<br>
      <input type="text" name="gewichtung_prozent" value="<?= h((string)$values['gewichtung_prozent']) ?>" placeholder="z.B. 100 oder 33.33">
    </label>
    <br><br>

    <label>
      Titel (optional)<br>
      <input type="text" name="titel" value="<?= h((string)$values['titel']) ?>" maxlength="150">
    </label>
    <?php if (!empty($errors['titel'])): ?><br><small style="color:red;"><?= h((string)$errors['titel']) ?></small><?php endif; ?>
    <br><br>

    <label>
      Fach (optional, wenn Modul-Belegung gewählt wird leer lassen)<br>
      <select name="fach_id">
        <option value="">-- kein Fach --</option>
        <?php foreach (($opt['faecher'] ?? []) as $f): ?>
          <?php $id = (string)$f['fach_id']; ?>
          <option value="<?= h($id) ?>" <?= ($values['fach_id'] === $id ? 'selected' : '') ?>>
            <?= h((string)$f['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <br><br>

    <label>
      Modul-Belegung (optional, wenn Fach gewählt wird leer lassen)<br>
      <select name="modul_belegung_id">
        <option value="">-- keine Modul-Belegung --</option>
        <?php foreach (($opt['modul_belegungen'] ?? []) as $mb): ?>
          <?php
            $id = (string)$mb['modul_belegung_id'];
            $label = trim((string)$mb['modul_nummer'] . ' ' . (string)$mb['titel']);
          ?>
          <option value="<?= h($id) ?>" <?= ($values['modul_belegung_id'] === $id ? 'selected' : '') ?>>
            <?= h($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <br><br>

    <label>
      Gruppe (optional, nur bei Modul-Belegung sinnvoll)<br>
      <select name="gruppe_id">
        <option value="">-- keine Gruppe --</option>
        <?php foreach (($opt['gruppen'] ?? []) as $g): ?>
          <?php
            $gid = (string)$g['gruppe_id'];
            $glabel = (string)($g['bezeichnung'] ?? '');
            if ($glabel === '') $glabel = 'Gruppe ' . $gid;
            $glabel .= ' (Belegung ' . (string)$g['modul_belegung_id'] . ')';
          ?>
          <option value="<?= h($gid) ?>" <?= ($values['gruppe_id'] === $gid ? 'selected' : '') ?>>
            <?= h($glabel) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if (!empty($errors['gruppe_id'])): ?><br><small style="color:red;"><?= h((string)$errors['gruppe_id']) ?></small><?php endif; ?>

    <?php if (!empty($errors['objekt'])): ?><br><small style="color:red;"><?= h((string)$errors['objekt']) ?></small><?php endif; ?>
    <br><br>

    <button type="submit">Speichern</button>
  </form>
</body>
</html>
