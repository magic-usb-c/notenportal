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

$isAdmin = !empty($ctx['is_admin']);
$fixedLernenderId = $isAdmin ? null : (int)($ctx['lernender_id'] ?? 0);

$values = [
    'lernender_id'       => $isAdmin ? '' : (string)$fixedLernenderId,
    'kategorie_id'       => '',
    'semester_id'        => '',
    'fach_id'            => '',
    'modul_belegung_id'  => '',
    'gruppe_id'          => '',
    'titel'              => '',
    'pruefungsdatum'     => date('Y-m-d'),
    'note_wert'          => '',
    'gewichtung_prozent' => '100',
];

$errors = [];
$opt = [];

try {
    // Wichtig: bei Lernenden die eigene lernender_id mitgeben, sonst ist modul_belegungen leer
    $opt = load_note_form_options($pdo, $isAdmin, $fixedLernenderId);
} catch (Throwable $e) {
    app_log_exception('Load note form options failed', $e, ['user_id' => $ctx['user_id'] ?? null]);
    http_response_code(500);
    exit('Internal Server Error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    require_csrf();

    foreach ($values as $k => $v) {
        $values[$k] = trim((string)($_POST[$k] ?? $v));
    }

    // --- Validierung ---
    $data = [];

    $data['kategorie_id'] = v_int_id($_POST['kategorie_id'] ?? null);
    if (!$data['kategorie_id']) $errors['kategorie_id'] = 'Kategorie fehlt.';

    $data['semester_id'] = v_int_id($_POST['semester_id'] ?? null);
    if (!$data['semester_id']) $errors['semester_id'] = 'Semester fehlt.';

    $data['pruefungsdatum'] = v_date_ymd($_POST['pruefungsdatum'] ?? null);
    if (!$data['pruefungsdatum']) $errors['pruefungsdatum'] = 'Datum ist ungültig.';

    $data['note_wert'] = v_grade($_POST['note_wert'] ?? null);
    if (!$data['note_wert']) $errors['note_wert'] = 'Note muss zwischen 1.0 und 6.0 liegen.';

    $data['gewichtung_prozent'] = v_weight_percent($_POST['gewichtung_prozent'] ?? null);
    if ($data['gewichtung_prozent'] === null) {
        $data['gewichtung_prozent'] = 100;
    }

    $data['fach_id'] = v_int_id($_POST['fach_id'] ?? null);
    $data['modul_belegung_id'] = v_int_id($_POST['modul_belegung_id'] ?? null);
    $data['gruppe_id'] = v_int_id($_POST['gruppe_id'] ?? null);

    $data['titel'] = trim((string)($_POST['titel'] ?? ''));
    if ($data['titel'] === '') {
        $data['titel'] = null;
    } elseif (mb_strlen($data['titel'], 'UTF-8') > 200) {
        $errors['titel'] = 'Titel zu lang (max. 200 Zeichen).';
    }

    // XOR Fach vs Modul-Belegung
    if (!$data['fach_id'] && !$data['modul_belegung_id']) {
        $errors['objekt'] = 'Wähle ein Fach oder eine Modul-Belegung.';
    }
    if ($data['fach_id'] && $data['modul_belegung_id']) {
        $errors['objekt'] = 'Wähle entweder Fach oder Modul-Belegung, nicht beides.';
    }

    // Lernender bestimmen
    if ($isAdmin) {
        $data['lernender_id'] = v_int_id($_POST['lernender_id'] ?? null);
        if (!$data['lernender_id']) $errors['lernender_id'] = 'Lernender fehlt.';
    } else {
        $data['lernender_id'] = $fixedLernenderId;
        if (!$data['lernender_id']) $errors['lernender_id'] = 'Kein Lernenden-Profil gefunden.';
    }

    // Wenn Modul-Belegung gewählt: prüfen, dass sie zum Lernenden passt (und Gruppe dazu passt)
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
            } elseif ((int)$row['lernender_id'] !== (int)$data['lernender_id'] && !$isAdmin) {
                $errors['objekt'] = 'Diese Modul-Belegung gehört nicht zu dir.';
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
                $g = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$g) {
                    $errors['gruppe_id'] = 'Ungültige Gruppe für diese Modul-Belegung.';
                }
            }
        } catch (Throwable $e) {
            app_log_exception('Validate modul_belegung/gruppe failed', $e, [
                'user_id' => $ctx['user_id'] ?? null,
                'modul_belegung_id' => $data['modul_belegung_id'],
                'gruppe_id' => $data['gruppe_id'],
            ]);
            $errors['form'] = 'Interner Fehler bei der Validierung.';
        }
    }

    // Wenn Fach gewählt: gruppe_id muss leer sein
    if (!$errors && $data['fach_id']) {
        $data['gruppe_id'] = null;
    }

    if (!$errors) {
        try {
            $newId = insert_note($pdo, $data, (int)($ctx['user_id'] ?? 0));
            app_log('info', 'Note created', [
                'note_id' => $newId,
                'by_user_id' => $ctx['user_id'] ?? null,
                'for_lernender_id' => $data['lernender_id'] ?? null,
            ]);
            flash_add('success', 'Note erstellt.');
            redirect('/noten.php');
        } catch (Throwable $e) {
            app_log_exception('Create note failed', $e, ['by_user_id' => $ctx['user_id'] ?? null]);
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
  <title>Neue Note – Notenportal</title>
</head>
<body>
  <h1>Neue Note</h1>

  <?= flash_render_html() ?>

  <p><a href="/noten.php">Zurück</a></p>

  <?php if (!empty($errors['form'])): ?>
    <p style="color:red;font-weight:bold;"><?= h((string)$errors['form']) ?></p>
  <?php endif; ?>

  <form method="post" action="/noten_create.php">
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
      <?php if (!empty($errors['lernender_id'])): ?><br><small style="color:red;"><?= h($errors['lernender_id']) ?></small><?php endif; ?>
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
    <?php if (!empty($errors['kategorie_id'])): ?><br><small style="color:red;"><?= h($errors['kategorie_id']) ?></small><?php endif; ?>
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
    <?php if (!empty($errors['semester_id'])): ?><br><small style="color:red;"><?= h($errors['semester_id']) ?></small><?php endif; ?>
    <br><br>

    <label>
      Prüfungsdatum<br>
      <input type="date" name="pruefungsdatum" value="<?= h($values['pruefungsdatum']) ?>" required>
    </label>
    <?php if (!empty($errors['pruefungsdatum'])): ?><br><small style="color:red;"><?= h($errors['pruefungsdatum']) ?></small><?php endif; ?>
    <br><br>

    <label>
      Note (1.0–6.0)<br>
      <input type="text" name="note_wert" value="<?= h($values['note_wert']) ?>" placeholder="z.B. 5.0" required>
    </label>
    <?php if (!empty($errors['note_wert'])): ?><br><small style="color:red;"><?= h($errors['note_wert']) ?></small><?php endif; ?>
    <br><br>

    <label>
      Gewichtung % (0–100)<br>
      <input type="number" name="gewichtung_prozent" value="<?= h($values['gewichtung_prozent']) ?>" min="0" max="100">
    </label>
    <br><br>

    <label>
      Titel (optional)<br>
      <input type="text" name="titel" value="<?= h($values['titel']) ?>" maxlength="200" placeholder="z.B. Prüfung 1 / Test / Auftrag …">
    </label>
    <?php if (!empty($errors['titel'])): ?><br><small style="color:red;"><?= h($errors['titel']) ?></small><?php endif; ?>
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
            $label = (string)($mb['label'] ?? '');
            if ($label === '') {
                $label = trim((string)($mb['modul_nummer'] ?? '') . ' ' . (string)($mb['titel'] ?? ''));
            }
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
          <?php $gid = (string)$g['gruppe_id']; ?>
          <option value="<?= h($gid) ?>" <?= ($values['gruppe_id'] === $gid ? 'selected' : '') ?>>
            <?= h((string)$g['bezeichnung']) ?> (Belegung <?= h((string)$g['modul_belegung_id']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if (!empty($errors['gruppe_id'])): ?><br><small style="color:red;"><?= h($errors['gruppe_id']) ?></small><?php endif; ?>

    <?php if (!empty($errors['objekt'])): ?><br><small style="color:red;"><?= h($errors['objekt']) ?></small><?php endif; ?>
    <br><br>

    <button type="submit">Speichern</button>
  </form>
</body>
</html>
