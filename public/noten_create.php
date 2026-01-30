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

$values = [
    'lernender_id' => $isAdmin ? '' : (string)($ctx['lernender_id'] ?? ''),
    'kategorie_id' => '',
    'semester_id' => '',
    'fach_id' => '',
    'modul_id' => '',
    'pruefungsdatum' => date('Y-m-d'),
    'note_wert' => '',
    'gewichtung_prozent' => '100',
];

$errors = [];

try {
    $opt = load_note_form_options($pdo, $isAdmin);
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

    $validated = validate_note_form($_POST, $isAdmin, $isAdmin ? null : (int)($ctx['lernender_id'] ?? 0));
    $errors = $validated['errors'];
    if ($validated['ok']) {
        try {
            $newId = insert_note($pdo, $validated['data'], (int)($ctx['user_id'] ?? 0));
            app_log('info', 'Note created', [
                'note_id' => $newId,
                'by_user_id' => $ctx['user_id'] ?? null,
                'for_lernender_id' => $validated['data']['lernender_id'] ?? null,
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

  <p>
    <a href="/noten.php">Zurück</a>
  </p>

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
          <?php foreach ($opt['lernende'] as $l): ?>
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
        <?php foreach ($opt['kategorien'] as $k): ?>
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
        <?php foreach ($opt['semester'] as $s): ?>
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
      Fach (optional, wenn Modul gewählt wird leer lassen)<br>
      <select name="fach_id">
        <option value="">-- kein Fach --</option>
        <?php foreach ($opt['faecher'] as $f): ?>
          <?php $id = (string)$f['fach_id']; ?>
          <option value="<?= h($id) ?>" <?= ($values['fach_id'] === $id ? 'selected' : '') ?>>
            <?= h((string)$f['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <br><br>

    <label>
      Modul (optional, wenn Fach gewählt wird leer lassen)<br>
      <select name="modul_id">
        <option value="">-- kein Modul --</option>
        <?php foreach ($opt['module'] as $m): ?>
          <?php $id = (string)$m['modul_id']; ?>
          <option value="<?= h($id) ?>" <?= ($values['modul_id'] === $id ? 'selected' : '') ?>>
            <?= h((string)$m['titel']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <?php if (!empty($errors['objekt'])): ?><br><small style="color:red;"><?= h($errors['objekt']) ?></small><?php endif; ?>
    <br><br>

    <button type="submit">Speichern</button>
  </form>
</body>
</html>
